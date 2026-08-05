<?php

require_once __DIR__ . '/ShellyClient.php';
require_once __DIR__ . '/BitacoraBomba.php';
require_once __DIR__ . '/ConfigBomba.php';
require_once __DIR__ . '/WebPushClient.php';

class Activaciones
{
    private PDO $db;
    private ShellyClient $shelly;
    private BitacoraBomba $bitacora;
    private ConfigBomba $configBomba;
    private WebPushClient $webPush;

    public function __construct(PDO $db, ShellyClient $shelly)
    {
        $this->db = $db;
        $this->shelly = $shelly;
        $this->bitacora = new BitacoraBomba($db);
        $this->configBomba = new ConfigBomba($db);
        $this->webPush = new WebPushClient($db);
    }

    private static function formatoHorasMinutos(int $segundos): string
    {
        $horas = intdiv($segundos, 3600);
        $minutos = intdiv($segundos % 3600, 60);

        if ($horas <= 0 && $minutos <= 0) {
            return '0 minutos';
        }

        $partes = [];
        if ($horas > 0) {
            $partes[] = $horas . ' ' . ($horas === 1 ? 'hora' : 'horas');
        }
        if ($minutos > 0 || $horas <= 0) {
            $partes[] = $minutos . ' ' . ($minutos === 1 ? 'minuto' : 'minutos');
        }

        return implode(' ', $partes);
    }

    private function notificarPush(string $titulo, string $cuerpo): void
    {
        try {
            $this->webPush->enviarATodos($titulo, $cuerpo);
        } catch (Throwable $exception) {
            // Un push que falla no debe tumbar la accion real de la bomba.
        }
    }

    public function estado(): array
    {
        // El cron de Hostinger es el respaldo que siempre cierra un cronometro
        // vencido (por si nadie tiene la pantalla abierta), pero aqui tambien lo
        // revisamos en cada consulta de estado: asi, mientras alguien esta viendo
        // el panel (que refresca cada pocos segundos), el apagado se siente casi
        // instantaneo en vez de esperar al siguiente tick del cron (hasta 1 min).
        $this->cerrarCronometroSiVencido();

        // El circuito es de pulso (Marcha/Paro), no de rele sostenido: el Shelly
        // no tiene forma de reportar si la bomba esta realmente encendida, asi
        // que "encendido" se calcula a partir de nuestra propia bitacora de
        // activaciones (hay o no una fila abierta), no preguntandole al Shelly.
        $temperatura = null;
        try {
            $temperatura = $this->shelly->leerSensorTemperatura();
        } catch (Throwable $exception) {
            $temperatura = null;
        }

        $abierta = $this->obtenerActivacionAbierta();

        return [
            'encendido' => $abierta !== null,
            'temperatura' => $temperatura,
            'activacion_actual' => $abierta,
            'espera_restante_segundos' => $this->esperaRestante(),
        ];
    }

    /**
     * Regresa la regla automatica PERMANENTE activa si su ventana (dias +
     * horario) cubre el momento actual, o null si no hay ninguna o no aplica
     * ahora mismo.
     */
    private function reglaQueAplicaAhora(): ?array
    {
        $ahora = new DateTime('now');
        $diaIso = (int) $ahora->format('N');
        $horaActual = $ahora->format('H:i:s');

        $regla = $this->db->query("SELECT * FROM bomba_regla_automatica WHERE activa = 1 LIMIT 1")->fetch();
        $aplica = $regla
            && in_array($diaIso, array_map('intval', explode(',', (string) $regla['dias_semana'])), true)
            && $horaActual >= $regla['hora_inicio']
            && $horaActual < $regla['hora_fin'];

        return $aplica ? $regla : null;
    }

    /**
     * Regresa la regla TEMPORAL activa si su rango de fechas + horario cubre
     * el momento actual, o null si no hay ninguna o no aplica ahora mismo.
     * Es independiente de la regla permanente: nunca se reemplazan entre si.
     */
    private function reglaTemporalQueAplicaAhora(): ?array
    {
        $ahora = new DateTime('now');
        $fechaHoy = $ahora->format('Y-m-d');
        $horaActual = $ahora->format('H:i:s');

        $regla = $this->db->query(
            "SELECT * FROM bomba_regla_temporal WHERE activa = 1 AND fecha_fin >= CURDATE() ORDER BY id DESC LIMIT 1"
        )->fetch();
        $aplica = $regla
            && $fechaHoy >= $regla['fecha_inicio']
            && $fechaHoy <= $regla['fecha_fin']
            && $horaActual >= $regla['hora_inicio']
            && $horaActual < $regla['hora_fin'];

        return $aplica ? $regla : null;
    }

    private function cerrarCronometroSiVencido(): void
    {
        $this->db->beginTransaction();
        $abierta = $this->obtenerActivacionAbiertaForUpdate();

        if (!$abierta || $abierta['origen'] !== 'cronometro') {
            $this->db->rollBack();
            return;
        }

        $transcurrido = time() - strtotime((string) $abierta['inicio_at']);
        if ($transcurrido < (int) $abierta['cronometro_duracion_segundos']) {
            $this->db->rollBack();
            return;
        }

        $ahora = new DateTime('now');
        $regla = $this->reglaQueAplicaAhora();
        $reglaTemporal = $this->reglaTemporalQueAplicaAhora();
        $sigueRegla = $regla !== null || $reglaTemporal !== null;

        $finAt = $ahora->format('Y-m-d H:i:s');
        $this->cerrarActivacion($abierta, $finAt, 'cronometro_expirado');

        if ($sigueRegla) {
            $stmt = $this->db->prepare(
                "INSERT INTO bomba_activaciones (origen, regla_automatica_id, inicio_at)
                 VALUES ('automatico', :regla_id, :inicio)"
            );
            $stmt->execute([
                'regla_id' => $regla !== null ? (int) $regla['id'] : null,
                'inicio' => $finAt,
            ]);
        }

        $this->db->commit();

        if ($sigueRegla) {
            $this->bitacora->registrar([
                'accion' => 'cronometro_expirado',
                'descripcion' => 'El cronometro termino, pero la bomba sigue encendida porque hay una programacion activa.',
            ]);
            return;
        }

        $this->shelly->pulsarParo();
        $this->bitacora->registrar([
            'accion' => 'cronometro_expirado',
            'descripcion' => 'El cronometro termino y la bomba se apago automaticamente.',
        ]);
        $this->notificarPush('Bomba apagada', 'El cronometro termino y la bomba se apago sola.');
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

            $ahora = date('Y-m-d H:i:s');

            // Si en este momento hay una regla automatica (permanente o
            // temporal) que deberia estar controlando la bomba, un encendido
            // manual le devuelve el control a esa regla (en vez de quedar
            // "suelto" como manual) para que el verificador la siga apagando
            // sola a la hora que corresponde.
            $reglaActiva = $this->reglaQueAplicaAhora();
            $reglaTemporalActiva = $this->reglaTemporalQueAplicaAhora();
            $algunaReglaAplica = $reglaActiva !== null || $reglaTemporalActiva !== null;

            $stmt = $this->db->prepare(
                "INSERT INTO bomba_activaciones
                    (origen, iniciado_por_usuario_id, iniciado_por_nombre, regla_automatica_id, inicio_at)
                 VALUES (:origen, :uid, :nombre, :regla_id, :inicio)"
            );
            $stmt->execute([
                'origen' => $algunaReglaAplica ? 'automatico' : 'manual',
                'uid' => (int) $usuario['id'],
                'nombre' => (string) $usuario['nombre'],
                'regla_id' => $reglaActiva !== null ? (int) $reglaActiva['id'] : null,
                'inicio' => $ahora,
            ]);
            $nuevaId = (int) $this->db->lastInsertId();

            $this->marcarComando();
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }

        // Hablar con Shelly Cloud puede tardar varios segundos: se hace ya sin
        // transaccion ni candado de fila abiertos, para no dejar ocupada la
        // conexion a la base de datos mientras se espera la respuesta.
        try {
            $this->shelly->pulsarInicio();
        } catch (Throwable $exception) {
            $this->cerrarActivacionPorId($nuevaId, $ahora, date('Y-m-d H:i:s'), 'error');
            throw $exception;
        }

        $descripcionEncendido = $algunaReglaAplica
            ? 'Encendido manual de la bomba (dentro del horario de la programacion: se le devuelve el control a la regla'
                . ($reglaTemporalActiva !== null && $reglaActiva === null ? ' temporal' : '') . ').'
            : 'Encendido manual de la bomba.';
        $this->registrarBitacora($usuario, 'encendido_manual', $descripcionEncendido);
        $this->notificarPush('Bomba encendida', (string) $usuario['nombre'] . ' encendio la bomba manualmente.');

        return $this->estado();
    }

    public function apagarManual(array $usuario): array
    {
        $this->verificarProteccion();

        $this->db->beginTransaction();
        $abierta = $this->obtenerActivacionAbiertaForUpdate();
        $this->marcarComando();
        $this->db->commit();

        $this->shelly->pulsarParo();

        if ($abierta) {
            $this->cerrarActivacion($abierta, date('Y-m-d H:i:s'), 'manual');
        }

        $this->registrarBitacora($usuario, 'apagado_manual', 'Apagado manual de la bomba.');
        $this->notificarPush('Bomba apagada', (string) $usuario['nombre'] . ' apago la bomba manualmente.');

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
            $nuevaId = (int) $this->db->lastInsertId();

            $this->marcarComando();
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }

        try {
            $this->shelly->pulsarInicio();
        } catch (Throwable $exception) {
            $this->cerrarActivacionPorId($nuevaId, $ahora, date('Y-m-d H:i:s'), 'error');
            throw $exception;
        }

        $this->registrarBitacora($usuario, 'cronometro_iniciado', 'Cronometro iniciado por ' . self::formatoHorasMinutos($duracionSegundos) . '.');
        $this->notificarPush('Bomba encendida', (string) $usuario['nombre'] . ' inicio un cronometro.');

        return $this->estado();
    }

    public function cancelarCronometro(array $usuario): array
    {
        $this->verificarProteccion();

        $this->db->beginTransaction();
        $abierta = $this->obtenerActivacionAbiertaForUpdate();

        if (!$abierta || $abierta['origen'] !== 'cronometro') {
            $this->db->rollBack();
            throw new HttpException('No hay un cronometro activo para cancelar.', 409);
        }

        $this->marcarComando();
        $this->db->commit();

        $this->shelly->pulsarParo();
        $this->cerrarActivacion($abierta, date('Y-m-d H:i:s'), 'manual');

        $this->registrarBitacora($usuario, 'cronometro_cancelado', 'Cronometro cancelado manualmente.');
        $this->notificarPush('Bomba apagada', (string) $usuario['nombre'] . ' cancelo el cronometro.');

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
                'segundos' => $segundos,
                'horas' => round($segundos / 3600, 1),
                'veces' => $veces,
            ];
        }

        return [
            'anio' => $anio,
            'mes' => $mes,
            'dias' => $dias,
            'total_segundos' => $totalSegundos,
            'total_horas' => round($totalSegundos / 3600, 1),
            'total_veces' => $totalVeces,
        ];
    }

    public function diagnosticoCron(): array
    {
        return [
            'cron_ultima_ejecucion_at' => ($v = $this->configBomba->obtener('cron_ultima_ejecucion_at', '')) !== '' ? $v : null,
            'cron_ultimo_resultado' => ($v = $this->configBomba->obtener('cron_ultimo_resultado', '')) !== '' ? $v : null,
            'hora_servidor_actual' => date('Y-m-d H:i:s'),
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

    private function cerrarActivacionPorId(int $id, string $inicioAt, string $finAt, string $finMotivo): void
    {
        $duracion = max(0, strtotime($finAt) - strtotime($inicioAt));

        $stmt = $this->db->prepare(
            "UPDATE bomba_activaciones
             SET fin_at = :fin_at, duracion_segundos = :duracion, fin_motivo = :fin_motivo
             WHERE id = :id"
        );
        $stmt->execute([
            'fin_at' => $finAt,
            'duracion' => $duracion,
            'fin_motivo' => $finMotivo,
            'id' => $id,
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
