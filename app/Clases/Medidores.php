<?php

class Medidores
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function listar(int $page = 1, int $perPage = 30): array
    {
        $page = max(1, $page);
        $perPage = (int) $perPage;
        $allowAll = $perPage <= 0;

        $stmtCount = $this->db->query(
            "SELECT COUNT(*) AS total
             FROM medidores m
             INNER JOIN usuarios_servicio u ON u.id = m.usuario_id
             INNER JOIN domicilios d ON d.id = m.domicilio_id"
        );
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
                m.id AS medidor_id,
                m.numero AS medidor,
                m.estado,
                m.created_at,
                u.id AS usuario_id,
                u.nombre AS usuario,
                d.id AS domicilio_id,
                d.ruta,
                c.nombre AS comunidad
             FROM medidores m
             INNER JOIN usuarios_servicio u ON u.id = m.usuario_id
             INNER JOIN domicilios d ON d.id = m.domicilio_id
             LEFT JOIN comunidades c ON c.id = d.comunidad_id
             ORDER BY m.id DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $medidores = $stmt->fetchAll();

        return [
            'medidores' => $medidores,
            'pagination' => [
                'page' => $page,
                'per_page' => $allowAll ? 0 : $perPage,
                'effective_per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => $total > 0 ? $offset + count($medidores) : 0,
            ],
        ];
    }

    public function usuariosDisponibles(): array
    {
        $stmt = $this->db->query(
            "SELECT
                u.id AS usuario_id,
                u.nombre AS usuario,
                d.id AS domicilio_id,
                d.ruta
             FROM usuarios_servicio u
             INNER JOIN domicilios d ON d.usuario_id = u.id
             WHERE u.activo = 1
                AND d.activo = 1
             ORDER BY u.nombre ASC"
        );

        return $stmt->fetchAll();
    }

    public function obtener(int $medidorId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                m.id AS medidor_id,
                m.numero AS medidor,
                m.estado,
                m.usuario_id,
                m.domicilio_id
             FROM medidores m
             WHERE m.id = :medidor_id
             LIMIT 1"
        );
        $stmt->execute(['medidor_id' => $medidorId]);
        $medidor = $stmt->fetch();

        if (!$medidor) {
            throw new RuntimeException('No se encontro el medidor solicitado.');
        }

        return $medidor;
    }

    public function guardar(array $input): array
    {
        $data = $this->normalizar($input);
        $errors = $this->validar($data);

        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        $this->validarNumeroUnico($data['medidor']);
        $domicilioId = $this->obtenerDomicilioDeUsuario($data['usuario_id']);

        $stmt = $this->db->prepare(
            "INSERT INTO medidores
                (usuario_id, domicilio_id, numero, estado)
             VALUES
                (:usuario_id, :domicilio_id, :numero, :estado)"
        );
        $stmt->execute([
            'usuario_id' => $data['usuario_id'],
            'domicilio_id' => $domicilioId,
            'numero' => $data['medidor'],
            'estado' => $this->mapearEstado($data['estado']),
        ]);

        return $this->obtener((int) $this->db->lastInsertId());
    }

    public function actualizar(array $input): array
    {
        $medidorId = (int) ($input['medidor_id'] ?? 0);
        $data = $this->normalizar($input, false);
        $errors = $this->validar($data, false);

        if ($medidorId <= 0) {
            $errors['medidor_id'] = 'No se recibio el medidor a editar.';
        }

        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        $this->validarNumeroUnico($data['medidor'], $medidorId);

        $stmt = $this->db->prepare(
            "UPDATE medidores
             SET numero = :numero,
                 estado = :estado
             WHERE id = :medidor_id"
        );
        $stmt->execute([
            'numero' => $data['medidor'],
            'estado' => $this->mapearEstado($data['estado']),
            'medidor_id' => $medidorId,
        ]);

        return $this->obtener($medidorId);
    }

    private function normalizar(array $input, bool $requiereUsuario = true): array
    {
        return [
            'usuario_id' => $requiereUsuario ? (int) ($input['usuario_id'] ?? 0) : 0,
            'medidor' => $this->limpiarCodigo(Request::cleanString($input['medidor'] ?? null), 60),
            'estado' => Request::cleanString($input['estado'] ?? 'Activo'),
        ];
    }

    private function validar(array $data, bool $requiereUsuario = true): array
    {
        $errors = [];

        if ($requiereUsuario && $data['usuario_id'] <= 0) {
            $errors['usuario_id'] = 'Selecciona el usuario al que pertenece el medidor.';
        }

        if (!$data['medidor']) {
            $errors['medidor'] = 'Captura el nombre o numero del medidor.';
        } elseif (!preg_match('/^[A-Z0-9-]+$/', $data['medidor'])) {
            $errors['medidor'] = 'El medidor solo puede llevar letras, numeros y guiones.';
        }

        return $errors;
    }

    private function validarNumeroUnico(string $numero, int $medidorIdExcluir = 0): void
    {
        $stmt = $this->db->prepare('SELECT id FROM medidores WHERE numero = :numero AND id <> :id LIMIT 1');
        $stmt->execute([
            'numero' => $numero,
            'id' => $medidorIdExcluir,
        ]);

        if ($stmt->fetch()) {
            throw new RuntimeException('Ya existe un medidor con ese nombre o numero.');
        }
    }

    private function obtenerDomicilioDeUsuario(int $usuarioId): int
    {
        $stmt = $this->db->prepare(
            "SELECT id
             FROM domicilios
             WHERE usuario_id = :usuario_id
             ORDER BY activo DESC, id DESC
             LIMIT 1"
        );
        $stmt->execute(['usuario_id' => $usuarioId]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new RuntimeException('El usuario seleccionado no tiene domicilio registrado.');
        }

        return (int) $row['id'];
    }

    private function mapearEstado(?string $estado): string
    {
        $estado = mb_strtolower($estado ?? 'activo', 'UTF-8');

        switch ($estado) {
            case 'sin medidor':
                return 'sin_medidor';
            case 'reemplazado':
                return 'reemplazado';
            case 'inactivo':
                return 'inactivo';
            default:
                return 'activo';
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
}
