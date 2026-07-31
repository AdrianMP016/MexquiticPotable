<?php

require_once __DIR__ . '/ShellyClient.php';
require_once __DIR__ . '/BitacoraBomba.php';
require_once __DIR__ . '/ConfigBomba.php';

class Activaciones
{
    private PDO $db;
    private ShellyClient $shelly;
    private BitacoraBomba $bitacora;
    private ConfigBomba $configBomba;

    public function __construct(PDO $db, ShellyClient $shelly)
    {
        $this->db = $db;
        $this->shelly = $shelly;
        $this->bitacora = new BitacoraBomba($db);
        $this->configBomba = new ConfigBomba($db);
    }

    public function estado(): array
    {
        $relay = $this->shelly->obtenerEstadoRelay();

        $temperatura = null;
        try {
            $temperatura = $this->shelly->leerSensorTemperatura();
        } catch (Throwable $exception) {
            $temperatura = null;
        }

        return [
            'encendido' => (bool) $relay['encendido'],
            'temperatura' => $temperatura,
            'activacion_actual' => $this->obtenerActivacionAbierta(),
            'espera_restante_segundos' => $this->esperaRestante(),
        ];
    }

    public function encenderManual(array $usuario): array
    {
        $this->verificarProteccion();

        $this->db->beginTransaction();
        try {
            if ($this->obtenerActivacionAbiertaForUpdate()) {
                $this->db->rollBack();
                throw new HttpException('La bomba ya esta encendida.', 409);
            }

            $this->shelly->encenderRelay();
            $ahora = date('Y-m-d H:i:s');

            $stmt = $this->db->prepare(
                "INSERT INTO bomba_activaciones (origen, iniciado_por_usuario_id, iniciado_por_nombre, inicio_at)
                 VALUES ('manual', :uid, :nombre, :inicio)"
            );
            $stmt->execute([
                'uid' => (int) $usuario['id'],
                'nombre' => (string) $usuario['nombre'],
                'inicio' => $ahora,
            ]);

            $this->marcarComando();
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }

        $this->registrarBitacora($usuario, 'encendido_manual', 'Encendido manual de la bomba.');

        return $this->estado();
    }

    public function apagarManual(array $usuario): array
    {
        $this->verificarProteccion();

        $this->db->beginTransaction();
        try {
            $abierta = $this->obtenerActivacionAbiertaForUpdate();

            $this->shelly->apagarRelay();
            $ahora = date('Y-m-d H:i:s');

            if ($abierta) {
                $this->cerrarActivacion($abierta, $ahora, 'manual');
            }

            $this->marcarComando();
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }

        $this->registrarBitacora($usuario, 'apagado_manual', 'Apagado manual de la bomba.');

        return $this->estado();
    }

    public function iniciarCronometro(array $usuario, int $duracionSegundos): array
    {
        if ($duracionSegundos <= 0) {
            throw new InvalidArgumentException(json_encode(
                ['duracion' => 'Indica cuanto tiempo quieres que trabaje la bomba.'],
                JSON_UNESCAPED_UNICODE
            ));
        }

        $this->verificarProteccion();

        $this->db->beginTransaction();
        try {
            if ($this->obtenerActivacionAbiertaForUpdate()) {
                $this->db->rollBack();
                throw new HttpException('La bomba ya esta encendida.', 409);
            }

            $this->shelly->encenderRelay();
            $ahora = date('Y-m-d H:i:s');

            $stmt = $this->db->prepare(
                "INSERT INTO bomba_activaciones
                    (origen, iniciado_por_usuario_id, iniciado_por_nombre, inicio_at, cronometro_duracion_segundos)
                 VALUES ('cronometro', :uid, :nombre, :inicio, :duracion)"
            );
            $stmt->execute([
                'uid' => (int) $usuario['id'],
                'nombre' => (string) $usuario['nombre'],
                'inicio' => $ahora,
                'duracion' => $duracionSegundos,
            ]);

            $this->marcarComando();
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }

        $this->registrarBitacora($usuario, 'cronometro_iniciado', 'Cronometro iniciado por ' . $duracionSegundos . ' segundos.');

        return $this->estado();
    }

    public function cancelarCronometro(array $usuario): array
    {
        $this->verificarProteccion();

        $this->db->beginTransaction();
        try {
            $abierta = $this->obtenerActivacionAbiertaForUpdate();

            if (!$abierta || $abierta['origen'] !== 'cronometro') {
                $this->db->rollBack();
                throw new HttpException('No hay un cronometro activo para cancelar.', 409);
            }

            $this->shelly->apagarRelay();
            $this->cerrarActivacion($abierta, date('Y-m-d H:i:s'), 'manual');

            $this->marcarComando();
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }

        $this->registrarBitacora($usuario, 'cronometro_cancelado', 'Cronometro cancelado manualmente.');

        return $this->estado();
    }

    public function estadisticas(string $periodo): array
    {
        [$desde, $hasta] = $this->rangoPeriodo($periodo);

        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(duracion_segundos), 0) AS segundos, COUNT(*) AS veces
             FROM bomba_activaciones
             WHERE fin_at IS NOT NULL AND inicio_at BETWEEN :desde AND :hasta"
        );
        $stmt->execute(['desde' => $desde, 'hasta' => $hasta]);
        $row = $stmt->fetch();

        $segundos = (int) ($row['segundos'] ?? 0);
        $veces = (int) ($row['veces'] ?? 0);

        $abierta = $this->obtenerActivacionAbierta();
        if ($abierta && $abierta['inicio_at'] >= $desde && $abierta['inicio_at'] <= $hasta) {
            $segundos += max(0, time() - strtotime((string) $abierta['inicio_at']));
            $veces++;
        }

        return [
            'periodo' => $periodo,
            'segundos' => $segundos,
            'horas' => round($segundos / 3600, 1),
            'veces' => $veces,
        ];
    }

    public function resumenMensual(int $anio, int $mes): array
    {
        $anio = max(2020, min(2100, $anio));
        $mes = max(1, min(12, $mes));

        $desde = sprintf('%04d-%02d-01 00:00:00', $anio, $mes);
        $hasta = (new DateTime($desde))->modify('first day of next month')->format('Y-m-d 00:00:00');

        $stmt = $this->db->prepare(
            "SELECT DATE(inicio_at) AS dia, SUM(duracion_segundos) AS segundos, COUNT(*) AS veces
             FROM bomba_activaciones
             WHERE fin_at IS NOT NULL AND inicio_at >= :desde AND inicio_at < :hasta
             GROUP BY DATE(inicio_at)"
        );
        $stmt->execute(['desde' => $desde, 'hasta' => $hasta]);

        $porDia = [];
        foreach ($stmt->fetchAll() as $fila) {
            $porDia[(string) $fila['dia']] = [
                'segundos' => (int) $fila['segundos'],
                'veces' => (int) $fila['veces'],
            ];
        }

        $abierta = $this->obtenerActivacionAbierta();
        if ($abierta && $abierta['inicio_at'] >= $desde && $abierta['inicio_at'] < $hasta) {
            $dia = substr((string) $abierta['inicio_at'], 0, 10);
            $segundosAbierta = max(0, time() - strtotime((string) $abierta['inicio_at']));
            $porDia[$dia] = [
                'segundos' => ($porDia[$dia]['segundos'] ?? 0) + $segundosAbierta,
                'veces' => ($porDia[$dia]['veces'] ?? 0) + 1,
            ];
        }

        $dias = [];
        $totalSegundos = 0;
        $totalVeces = 0;
        $diasEnMes = (int) (new DateTime($desde))->format('t');

        for ($d = 1; $d <= $diasEnMes; $d++) {
            $fecha = sprintf('%04d-%02d-%02d', $anio, $mes, $d);
            $segundos = $porDia[$fecha]['segundos'] ?? 0;
            $veces = $porDia[$fecha]['veces'] ?? 0;
            $totalSegundos += $segundos;
            $totalVeces += $veces;

            $dias[] = [
                'fecha' => $fecha,
                'dia' => $d,
                'horas' => round($segundos / 3600, 1),
                'veces' => $veces,
            ];
        }

        return [
            'anio' => $anio,
            'mes' => $mes,
            'dias' => $dias,
            'total_horas' => round($totalSegundos / 3600, 1),
            'total_veces' => $totalVeces,
        ];
    }

    public function detalleDia(string $fecha): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw new InvalidArgumentException(json_encode(['fecha' => 'Fecha invalida.'], JSON_UNESCAPED_UNICODE));
        }

        $stmt = $this->db->prepare(
            "SELECT origen, iniciado_por_nombre, inicio_at, fin_at, duracion_segundos, fin_motivo
             FROM bomba_activaciones
             WHERE DATE(inicio_at) = :fecha
             ORDER BY inicio_at ASC"
        );
        $stmt->execute(['fecha' => $fecha]);

        return $stmt->fetchAll();
    }

    private function cerrarActivacion(array $abierta, string $finAt, string $finMotivo): void
    {
        $duracion = max(0, strtotime($finAt) - strtotime((string) $abierta['inicio_at']));

        $stmt = $this->db->prepare(
            "UPDATE bomba_activaciones
             SET fin_at = :fin_at, duracion_segundos = :duracion, fin_motivo = :fin_motivo
             WHERE id = :id"
        );
        $stmt->execute([
            'fin_at' => $finAt,
            'duracion' => $duracion,
            'fin_motivo' => $finMotivo,
            'id' => $abierta['id'],
        ]);
    }

    private function obtenerActivacionAbierta(): ?array
    {
        $stmt = $this->db->query("SELECT * FROM bomba_activaciones WHERE fin_at IS NULL ORDER BY id DESC LIMIT 1");
        return $stmt->fetch() ?: null;
    }

    private function obtenerActivacionAbiertaForUpdate(): ?array
    {
        $stmt = $this->db->query("SELECT * FROM bomba_activaciones WHERE fin_at IS NULL ORDER BY id DESC LIMIT 1 FOR UPDATE");
        return $stmt->fetch() ?: null;
    }

    private function verificarProteccion(): void
    {
        $delay = (int) $this->configBomba->obtenerNumero('proteccion_delay_segundos', 2);
        $ultimo = $this->configBomba->obtener('ultimo_comando_at', '');

        if ($ultimo === '') {
            return;
        }

        $ultimoTs = strtotime($ultimo);
        if ($ultimoTs === false) {
            return;
        }

        $transcurrido = time() - $ultimoTs;
        if ($transcurrido < $delay) {
            throw new HttpException('Espera ' . ($delay - $transcurrido) . ' segundos antes de otro comando.', 429);
        }
    }

    private function marcarComando(): void
    {
        $this->configBomba->establecer('ultimo_comando_at', date('Y-m-d H:i:s'));
    }

    private function esperaRestante(): int
    {
        $delay = (int) $this->configBomba->obtenerNumero('proteccion_delay_segundos', 2);
        $ultimo = $this->configBomba->obtener('ultimo_comando_at', '');

        if ($ultimo === '') {
            return 0;
        }

        $ultimoTs = strtotime($ultimo);
        if ($ultimoTs === false) {
            return 0;
        }

        return max(0, $delay - (time() - $ultimoTs));
    }

    private function rangoPeriodo(string $periodo): array
    {
        $hoy = new DateTime('now');

        switch ($periodo) {
            case 'semana':
                $desde = (clone $hoy)->modify('monday this week')->setTime(0, 0, 0);
                break;
            case 'mes':
                $desde = (clone $hoy)->modify('first day of this month')->setTime(0, 0, 0);
                break;
            case 'anio':
                $desde = (clone $hoy)->modify('first day of january this year')->setTime(0, 0, 0);
                break;
            case 'dia':
            default:
                $desde = (clone $hoy)->setTime(0, 0, 0);
        }

        return [$desde->format('Y-m-d H:i:s'), $hoy->format('Y-m-d H:i:s')];
    }

    private function registrarBitacora(array $usuario, string $accion, string $descripcion): void
    {
        $this->bitacora->registrar([
            'usuario_bomba_id' => (int) ($usuario['id'] ?? 0),
            'nombre_usuario' => (string) ($usuario['nombre'] ?? ''),
            'rol' => (string) ($usuario['rol'] ?? ''),
            'accion' => $accion,
            'descripcion' => $descripcion,
        ]);
    }
}
