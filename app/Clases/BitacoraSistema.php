<?php

class BitacoraSistema
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function registrar(array $payload): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO bitacora_sistema
                (usuario_sistema_id, nombre_usuario, rol, modulo, accion, referencia_tipo, referencia_id,
                 descripcion, payload_json, ip, user_agent)
             VALUES
                (:usuario_sistema_id, :nombre_usuario, :rol, :modulo, :accion, :referencia_tipo, :referencia_id,
                 :descripcion, :payload_json, :ip, :user_agent)"
        );

        $stmt->execute([
            'usuario_sistema_id' => $payload['usuario_sistema_id'] ?? null,
            'nombre_usuario' => $payload['nombre_usuario'] ?? null,
            'rol' => $payload['rol'] ?? null,
            'modulo' => $payload['modulo'] ?? 'sistema',
            'accion' => $payload['accion'] ?? 'evento',
            'referencia_tipo' => $payload['referencia_tipo'] ?? null,
            'referencia_id' => $payload['referencia_id'] ?? null,
            'descripcion' => $payload['descripcion'] ?? null,
            'payload_json' => !empty($payload['payload_json']) ? json_encode($payload['payload_json'], JSON_UNESCAPED_UNICODE) : null,
            'ip' => $payload['ip'] ?? null,
            'user_agent' => $payload['user_agent'] ?? null,
        ]);
    }

    public function listar(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 100));
        $offset = ($page - 1) * $perPage;

        $params = [];
        $conditions = [];
        $modulo = trim((string) ($filters['modulo'] ?? ''));
        $usuario = trim((string) ($filters['usuario'] ?? ''));
        $accion = trim((string) ($filters['accion'] ?? ''));
        $fechaDesde = trim((string) ($filters['fecha_desde'] ?? ''));
        $fechaHasta = trim((string) ($filters['fecha_hasta'] ?? ''));

        if ($modulo !== '') {
            $conditions[] = 'b.modulo = :modulo';
            $params['modulo'] = $modulo;
        }

        if ($usuario !== '') {
            $conditions[] = 'b.nombre_usuario = :usuario';
            $params['usuario'] = $usuario;
        }

        if ($accion !== '') {
            $conditions[] = 'b.accion = :accion';
            $params['accion'] = $accion;
        }

        if ($fechaDesde !== '') {
            $conditions[] = 'b.created_at >= :fecha_desde';
            $params['fecha_desde'] = $fechaDesde . ' 00:00:00';
        }

        if ($fechaHasta !== '') {
            $conditions[] = 'b.created_at < DATE_ADD(:fecha_hasta, INTERVAL 1 DAY)';
            $params['fecha_hasta'] = $fechaHasta;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countStmt = $this->db->prepare("SELECT COUNT(*) AS total FROM bitacora_sistema b $where");
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['total'] ?? 0);
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $stmt = $this->db->prepare(
            "SELECT
                b.id,
                b.usuario_sistema_id,
                b.nombre_usuario,
                b.rol,
                b.modulo,
                b.accion,
                b.referencia_tipo,
                b.referencia_id,
                b.descripcion,
                b.payload_json,
                b.ip,
                b.user_agent,
                b.created_at
             FROM bitacora_sistema b
             $where
             ORDER BY b.id DESC
             LIMIT :limit OFFSET :offset"
        );

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }

        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'logs' => array_map(function (array $row): array {
                if (!empty($row['payload_json'])) {
                    $decoded = json_decode((string) $row['payload_json'], true);
                    $row['payload_json'] = is_array($decoded) ? $decoded : null;
                }

                return $row;
            }, $stmt->fetchAll()),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => $total > 0 ? min($offset + $perPage, $total) : 0,
            ],
            'filters' => [
                'modulo' => $modulo,
                'usuario' => $usuario,
                'accion' => $accion,
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
            ],
        ];
    }

    public function catalogos(): array
    {
        $acciones = $this->db->query(
            "SELECT DISTINCT accion
             FROM bitacora_sistema
             WHERE accion IS NOT NULL AND accion <> ''
             ORDER BY accion ASC"
        )->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $usuarios = $this->db->query(
            "SELECT DISTINCT nombre_usuario
             FROM bitacora_sistema
             WHERE nombre_usuario IS NOT NULL AND nombre_usuario <> ''
             ORDER BY nombre_usuario ASC"
        )->fetchAll(PDO::FETCH_COLUMN) ?: [];

        return [
            'acciones' => array_values(array_filter(array_map('strval', $acciones))),
            'usuarios' => array_values(array_filter(array_map('strval', $usuarios))),
        ];
    }
}
