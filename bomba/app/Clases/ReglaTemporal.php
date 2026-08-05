<?php

require_once __DIR__ . '/BitacoraBomba.php';

/**
 * Regla temporal: un horario extra por un rango de fechas especifico (ej.
 * compensar un dia sin luz), independiente de la regla automatica permanente.
 * No la reemplaza ni entra en conflicto con ella - el verificador (cron)
 * revisa ambas por separado y enciende la bomba si cualquiera de las dos
 * aplica en ese momento.
 */
class ReglaTemporal
{
    private PDO $db;
    private BitacoraBomba $bitacora;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->bitacora = new BitacoraBomba($db);
    }

    public function obtenerActiva(): ?array
    {
        // Auto-limpieza: si ya paso su fecha de fin, se desactiva sola aqui
        // mismo, sin que nadie tenga que acordarse de borrarla a mano.
        $this->db->exec(
            "UPDATE bomba_regla_temporal SET activa = 0 WHERE activa = 1 AND fecha_fin < CURDATE()"
        );

        $stmt = $this->db->query("SELECT * FROM bomba_regla_temporal WHERE activa = 1 ORDER BY id DESC LIMIT 1");
        $row = $stmt->fetch();

        return $row ? $this->formatear($row) : null;
    }

    public function guardar(array $usuario, array $input): array
    {
        $fechaInicio = trim((string) ($input['fecha_inicio'] ?? ''));
        $fechaFin = trim((string) ($input['fecha_fin'] ?? ''));
        $horaInicio = trim((string) ($input['hora_inicio'] ?? ''));
        $horaFin = trim((string) ($input['hora_fin'] ?? ''));
        $forzar = filter_var($input['forzar'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $errors = [];

        if (!$this->fechaValida($fechaInicio)) {
            $errors['fecha_inicio'] = 'Captura una fecha de inicio valida.';
        }

        if (!$this->fechaValida($fechaFin)) {
            $errors['fecha_fin'] = 'Captura una fecha de fin valida.';
        }

        if (!$this->horaValida($horaInicio)) {
            $errors['hora_inicio'] = 'Captura una hora de inicio valida.';
        }

        if (!$this->horaValida($horaFin)) {
            $errors['hora_fin'] = 'Captura una hora de fin valida.';
        }

        if (empty($errors) && $fechaInicio > $fechaFin) {
            $errors['fecha_fin'] = 'La fecha de fin debe ser igual o despues de la fecha de inicio.';
        }

        if (empty($errors) && $fechaInicio === $fechaFin && $horaInicio >= $horaFin) {
            $errors['hora_fin'] = 'La hora de fin debe ser despues de la hora de inicio.';
        }

        if (empty($errors) && $fechaFin < date('Y-m-d')) {
            $errors['fecha_fin'] = 'La fecha de fin ya paso.';
        }

        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->query(
                "SELECT * FROM bomba_regla_temporal WHERE activa = 1 AND fecha_fin >= CURDATE() ORDER BY id DESC LIMIT 1 FOR UPDATE"
            );
            $actual = $stmt->fetch();

            if ($actual && !$forzar) {
                $this->db->rollBack();

                return [
                    'requiere_confirmacion' => true,
                    'regla_actual' => $this->formatear($actual),
                ];
            }

            if ($actual) {
                $stmt = $this->db->prepare(
                    "UPDATE bomba_regla_temporal SET activa = 0, reemplazada_at = NOW() WHERE id = :id"
                );
                $stmt->execute(['id' => $actual['id']]);
            }

            $stmt = $this->db->prepare(
                "INSERT INTO bomba_regla_temporal
                    (fecha_inicio, fecha_fin, hora_inicio, hora_fin, activa, creado_por_usuario_id, creado_por_nombre)
                 VALUES (:fecha_inicio, :fecha_fin, :hora_inicio, :hora_fin, 1, :usuario_id, :usuario_nombre)"
            );
            $stmt->execute([
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'hora_inicio' => $horaInicio,
                'hora_fin' => $horaFin,
                'usuario_id' => (int) $usuario['id'],
                'usuario_nombre' => (string) $usuario['nombre'],
            ]);

            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }

        $this->bitacora->registrar([
            'usuario_bomba_id' => (int) ($usuario['id'] ?? 0),
            'nombre_usuario' => (string) ($usuario['nombre'] ?? ''),
            'rol' => (string) ($usuario['rol'] ?? ''),
            'accion' => $actual ? 'regla_temporal_reemplazada' : 'regla_temporal_creada',
            'descripcion' => ($actual ? 'Regla temporal reemplazada' : 'Regla temporal creada')
                . ": {$fechaInicio} a {$fechaFin}, {$horaInicio}-{$horaFin}.",
            'payload_json' => [
                'anterior' => $actual ? $this->formatear($actual) : null,
                'nueva' => [
                    'fecha_inicio' => $fechaInicio,
                    'fecha_fin' => $fechaFin,
                    'hora_inicio' => $horaInicio,
                    'hora_fin' => $horaFin,
                ],
            ],
        ]);

        return [
            'requiere_confirmacion' => false,
            'regla' => $this->obtenerActiva(),
        ];
    }

    public function cancelar(array $usuario): void
    {
        $stmt = $this->db->query(
            "SELECT * FROM bomba_regla_temporal WHERE activa = 1 ORDER BY id DESC LIMIT 1"
        );
        $actual = $stmt->fetch();

        if (!$actual) {
            return;
        }

        $stmt = $this->db->prepare(
            "UPDATE bomba_regla_temporal SET activa = 0, reemplazada_at = NOW() WHERE id = :id"
        );
        $stmt->execute(['id' => $actual['id']]);

        $this->bitacora->registrar([
            'usuario_bomba_id' => (int) ($usuario['id'] ?? 0),
            'nombre_usuario' => (string) ($usuario['nombre'] ?? ''),
            'rol' => (string) ($usuario['rol'] ?? ''),
            'accion' => 'regla_temporal_cancelada',
            'descripcion' => 'Regla temporal cancelada manualmente.',
        ]);
    }

    private function fechaValida(string $valor): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return false;
        }

        $partes = explode('-', $valor);

        return checkdate((int) $partes[1], (int) $partes[2], (int) $partes[0]);
    }

    private function horaValida(string $valor): bool
    {
        return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $valor);
    }

    private function formatear(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'fecha_inicio' => (string) $row['fecha_inicio'],
            'fecha_fin' => (string) $row['fecha_fin'],
            'hora_inicio' => substr((string) $row['hora_inicio'], 0, 5),
            'hora_fin' => substr((string) $row['hora_fin'], 0, 5),
            'creado_por_nombre' => (string) $row['creado_por_nombre'],
            'created_at' => (string) $row['created_at'],
        ];
    }
}
