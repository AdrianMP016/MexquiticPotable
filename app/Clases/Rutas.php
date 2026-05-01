<?php

class Rutas
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function listar(int $page = 1, int $perPage = 25): array
    {
        $page = max(1, $page);
        $perPage = (int) $perPage;
        $allowAll = $perPage <= 0;

        $stmtCount = $this->db->query("SELECT COUNT(*) AS total FROM rutas");
        $total = (int) ($stmtCount->fetch()['total'] ?? 0);

        if ($allowAll) {
            $perPage = $total > 0 ? $total : 1;
        } else {
            $perPage = max(1, min($perPage, 500));
        }

        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $stmt = $this->db->prepare(
            "SELECT
                r.id AS ruta_id,
                r.codigo,
                r.nombre,
                r.descripcion,
                r.activo,
                r.created_at,
                c.id AS comunidad_id,
                c.nombre AS comunidad
             FROM rutas r
             LEFT JOIN comunidades c ON c.id = r.comunidad_id
             ORDER BY r.activo DESC, r.codigo ASC, r.id DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rutas = $stmt->fetchAll();

        return [
            'rutas' => $rutas,
            'pagination' => [
                'page' => $page,
                'per_page' => $allowAll ? 0 : $perPage,
                'effective_per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => $total > 0 ? $offset + count($rutas) : 0,
            ],
        ];
    }

    public function catalogo(int $comunidadId = 0): array
    {
        $sql = "SELECT
                    r.id AS ruta_id,
                    r.codigo,
                    r.nombre,
                    r.descripcion,
                    r.comunidad_id,
                    c.nombre AS comunidad
                FROM rutas r
                LEFT JOIN comunidades c ON c.id = r.comunidad_id
                WHERE r.activo = 1";
        $params = [];

        if ($comunidadId > 0) {
            $sql .= " AND r.comunidad_id = :comunidad_id";
            $params['comunidad_id'] = $comunidadId;
        }

        $sql .= " ORDER BY r.codigo ASC, r.nombre ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function obtener(int $rutaId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                r.id AS ruta_id,
                r.codigo,
                r.nombre,
                r.descripcion,
                r.activo,
                r.comunidad_id
             FROM rutas r
             WHERE r.id = :ruta_id
             LIMIT 1"
        );
        $stmt->execute(['ruta_id' => $rutaId]);
        $ruta = $stmt->fetch();

        if (!$ruta) {
            throw new RuntimeException('No se encontro la ruta solicitada.');
        }

        return $ruta;
    }

    public function guardar(array $input): array
    {
        $data = $this->normalizar($input);
        $errors = $this->validar($data);

        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        $this->validarCodigoUnico($data['codigo']);

        $stmt = $this->db->prepare(
            "INSERT INTO rutas
                (comunidad_id, codigo, nombre, descripcion, activo)
             VALUES
                (:comunidad_id, :codigo, :nombre, :descripcion, 1)"
        );
        $stmt->execute([
            'comunidad_id' => $data['comunidad_id'],
            'codigo' => $data['codigo'],
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'],
        ]);

        return $this->obtener((int) $this->db->lastInsertId());
    }

    public function actualizar(array $input): array
    {
        $rutaId = (int) ($input['ruta_id'] ?? 0);
        $data = $this->normalizar($input);
        $errors = $this->validar($data);

        if ($rutaId <= 0) {
            $errors['ruta_id'] = 'No se recibio la ruta a editar.';
        }

        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        $this->validarCodigoUnico($data['codigo'], $rutaId);

        $stmt = $this->db->prepare(
            "UPDATE rutas
             SET comunidad_id = :comunidad_id,
                 codigo = :codigo,
                 nombre = :nombre,
                 descripcion = :descripcion
             WHERE id = :ruta_id"
        );
        $stmt->execute([
            'comunidad_id' => $data['comunidad_id'],
            'codigo' => $data['codigo'],
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'],
            'ruta_id' => $rutaId,
        ]);

        $this->sincronizarCodigoEnDomicilios($rutaId, $data['codigo']);

        return $this->obtener($rutaId);
    }

    public function darDeBaja(int $rutaId): array
    {
        if ($rutaId <= 0) {
            throw new RuntimeException('No se recibio la ruta a dar de baja.');
        }

        $stmt = $this->db->prepare(
            "UPDATE rutas
             SET activo = 0
             WHERE id = :ruta_id"
        );
        $stmt->execute(['ruta_id' => $rutaId]);

        return $this->obtener($rutaId);
    }

    private function normalizar(array $input): array
    {
        return [
            'comunidad_id' => (int) ($input['comunidad_id'] ?? 0),
            'codigo' => $this->limpiarCodigo(Request::cleanString($input['codigo'] ?? null), 25),
            'nombre' => $this->mayusculas(Request::cleanString($input['nombre'] ?? null)),
            'descripcion' => $this->mayusculas(Request::cleanString($input['descripcion'] ?? null)),
        ];
    }

    private function validar(array $data): array
    {
        $errors = [];

        if ($data['comunidad_id'] <= 0) {
            $errors['comunidad_id'] = 'Selecciona la comunidad de la ruta.';
        }

        if (!$data['codigo']) {
            $errors['codigo'] = 'Captura el codigo de la ruta.';
        } elseif (!preg_match('/^[A-Z0-9-]+$/', $data['codigo'])) {
            $errors['codigo'] = 'La ruta solo puede llevar letras, numeros y guiones.';
        }

        if (!$data['nombre']) {
            $errors['nombre'] = 'Captura el nombre de la ruta.';
        }

        return $errors;
    }

    private function validarCodigoUnico(string $codigo, int $rutaIdExcluir = 0): void
    {
        $stmt = $this->db->prepare('SELECT id FROM rutas WHERE codigo = :codigo AND id <> :id LIMIT 1');
        $stmt->execute([
            'codigo' => $codigo,
            'id' => $rutaIdExcluir,
        ]);

        if ($stmt->fetch()) {
            throw new RuntimeException('Ya existe una ruta con ese codigo.');
        }
    }

    private function limpiarCodigo(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $code = preg_replace('/[^A-Z0-9-]/', '', mb_strtoupper($value, 'UTF-8'));
        return $code === '' ? null : substr($code, 0, $maxLength);
    }

    private function sincronizarCodigoEnDomicilios(int $rutaId, string $codigo): void
    {
        $stmt = $this->db->prepare(
            "UPDATE domicilios d
             INNER JOIN usuarios_servicio u ON u.id = d.usuario_id
             SET d.ruta = :codigo
             WHERE u.ruta_id = :ruta_id"
        );
        $stmt->execute([
            'codigo' => $codigo,
            'ruta_id' => $rutaId,
        ]);
    }

    private function mayusculas(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_strtoupper($value, 'UTF-8');
    }
}
