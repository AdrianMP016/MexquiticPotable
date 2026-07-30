<?php

require_once __DIR__ . '/BitacoraBomba.php';

class ReglaAutomatica
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
        $stmt = $this->db->query("SELECT * FROM bomba_regla_automatica WHERE activa = 1 ORDER BY id DESC LIMIT 1");
        $row = $stmt->fetch();

        return $row ? $this->formatear($row) : null;
    }

    public function guardar(array $usuario, array $input): array
    {
        $horaInicio = trim((string) ($input['hora_inicio'] ?? ''));
        $horaFin = trim((string) ($input['hora_fin'] ?? ''));
        $dias = array_values(array_unique(array_filter(array_map('intval', (array) ($input['dias_semana'] ?? [])))));
        $forzar = filter_var($input['forzar'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $errors = [];

        if (!$this->horaValida($horaInicio)) {
            $errors['hora_inicio'] = 'Captura una hora de inicio valida.';
        }

        if (!$this->horaValida($horaFin)) {
            $errors['hora_fin'] = 'Captura una hora de fin valida.';
        }

        if (empty($dias)) {
            $errors['dias_semana'] = 'Selecciona al menos un dia.';
        }

        foreach ($dias as $dia) {
            if ($dia < 1 || $dia > 7) {
                $errors['dias_semana'] = 'Dias invalidos.';
                break;
            }
        }

        if (empty($errors) && $horaInicio >= $horaFin) {
            $errors['hora_fin'] = 'La hora de fin debe ser despues de la hora de inicio.';
        }

        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        sort($dias);
        $diasCsv = implode(',', $dias);

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->query(
                "SELECT * FROM bomba_regla_automatica WHERE activa = 1 ORDER BY id DESC LIMIT 1 FOR UPDATE"
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
                    "UPDATE bomba_regla_automatica SET activa = 0, reemplazada_at = NOW() WHERE id = :id"
                );
                $stmt->execute(['id' => $actual['id']]);
            }

            $stmt = $this->db->prepare(
                "INSERT INTO bomba_regla_automatica
                    (hora_inicio, hora_fin, dias_semana, activa, creado_por_usuario_id, creado_por_nombre)
                 VALUES (:hora_inicio, :hora_fin, :dias_semana, 1, :usuario_id, :usuario_nombre)"
            );
            $stmt->execute([
                'hora_inicio' => $horaInicio,
                'hora_fin' => $horaFin,
                'dias_semana' => $diasCsv,
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
            'accion' => $actual ? 'regla_reemplazada' : 'regla_creada',
            'descripcion' => ($actual ? 'Regla automatica reemplazada' : 'Regla automatica creada')
                . ": {$horaInicio}-{$horaFin} ({$this->diasTexto($diasCsv)}).",
            'payload_json' => [
                'anterior' => $actual ? $this->formatear($actual) : null,
                'nueva' => ['hora_inicio' => $horaInicio, 'hora_fin' => $horaFin, 'dias_semana' => $dias],
            ],
        ]);

        return [
            'requiere_confirmacion' => false,
            'regla' => $this->obtenerActiva(),
        ];
    }

    private function horaValida(string $valor): bool
    {
        return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $valor);
    }

    private function diasTexto(string $csv): string
    {
        $nombres = [1 => 'Lun', 2 => 'Mar', 3 => 'Mie', 4 => 'Jue', 5 => 'Vie', 6 => 'Sab', 7 => 'Dom'];
        $dias = array_filter(array_map('intval', explode(',', $csv)));
        $textos = array_map(static fn (int $dia): string => $nombres[$dia] ?? '', $dias);

        return implode(', ', array_filter($textos));
    }

    private function formatear(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'hora_inicio' => substr((string) $row['hora_inicio'], 0, 5),
            'hora_fin' => substr((string) $row['hora_fin'], 0, 5),
            'dias_semana' => array_map('intval', explode(',', (string) $row['dias_semana'])),
            'dias_semana_texto' => $this->diasTexto((string) $row['dias_semana']),
            'creado_por_nombre' => (string) $row['creado_por_nombre'],
            'created_at' => (string) $row['created_at'],
        ];
    }
}
