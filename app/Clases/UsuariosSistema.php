<?php

require_once __DIR__ . '/../Clases/BitacoraSistema.php';

class UsuariosSistema
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function listar(string $termino = '', int $page = 1, int $perPage = 25): array
    {
        $page = max(1, $page);
        $perPage = (int) $perPage;
        $allowAll = $perPage <= 0;

        $where = '';
        $params = [];

        $termino = trim($termino);
        if ($termino !== '') {
            $where = "WHERE nombre LIKE :termino OR usuario LIKE :termino OR rol LIKE :termino OR telefono LIKE :termino";
            $params['termino'] = '%' . $termino . '%';
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) AS total FROM usuarios_sistema $where");
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        if ($allowAll) {
            $perPage = $total > 0 ? $total : 1;
        } else {
            $perPage = max(1, min($perPage, 100));
        }

        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $stmt = $this->db->prepare(
            "SELECT
                id,
                nombre,
                usuario,
                telefono,
                correo,
                rol,
                activo,
                ultimo_login_at,
                ultimo_login_ip,
                ultimo_acceso_at,
                ultimo_acceso_modulo,
                created_at
             FROM usuarios_sistema
             $where
             ORDER BY rol = 'admin' DESC, activo DESC, nombre ASC, id ASC
             LIMIT :limit OFFSET :offset"
        );

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'usuarios' => $stmt->fetchAll(),
            'pagination' => [
                'page' => $page,
                'per_page' => $allowAll ? 0 : $perPage,
                'effective_per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => $total > 0 ? min($offset + $perPage, $total) : 0,
            ],
        ];
    }

    public function obtener(int $id): array
    {
        if ($id <= 0) {
            throw new RuntimeException('No se recibió el usuario del sistema a consultar.');
        }

        $stmt = $this->db->prepare(
            "SELECT
                id,
                nombre,
                usuario,
                telefono,
                correo,
                rol,
                activo,
                ultimo_login_at,
                ultimo_login_ip,
                ultimo_acceso_at,
                ultimo_acceso_modulo,
                created_at
             FROM usuarios_sistema
             WHERE id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new RuntimeException('No se encontró el usuario del sistema solicitado.');
        }

        return $row;
    }

    public function guardar(array $input): array
    {
        $data = $this->normalizar($input, true);
        $this->validar($data, true);

        $stmt = $this->db->prepare(
            "INSERT INTO usuarios_sistema
                (nombre, usuario, telefono, correo, password_hash, rol, activo, ultimo_password_change_at)
             VALUES
                (:nombre, :usuario, :telefono, :correo, :password_hash, :rol, :activo, NOW())"
        );
        $stmt->execute([
            'nombre' => $data['nombre'],
            'usuario' => $data['usuario'],
            'telefono' => $data['telefono'],
            'correo' => $data['correo'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'rol' => $data['rol'],
            'activo' => $data['activo'],
        ]);

        return $this->obtener((int) $this->db->lastInsertId());
    }

    public function actualizar(array $input): array
    {
        $id = (int) ($input['usuario_sistema_id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('No se recibió el usuario del sistema a actualizar.');
        }

        $actual = $this->obtener($id);
        $data = $this->normalizar($input, false);
        $this->validar($data, false, $id, $actual);

        $stmt = $this->db->prepare(
            "UPDATE usuarios_sistema
             SET nombre = :nombre,
                 usuario = :usuario,
                 telefono = :telefono,
                 correo = :correo,
                 rol = :rol,
                 activo = :activo
             WHERE id = :id"
        );
        $stmt->execute([
            'nombre' => $data['nombre'],
            'usuario' => $data['usuario'],
            'telefono' => $data['telefono'],
            'correo' => $data['correo'],
            'rol' => $data['rol'],
            'activo' => $data['activo'],
            'id' => $id,
        ]);

        return $this->obtener($id);
    }

    public function restablecerPassword(array $input): array
    {
        $id = (int) ($input['usuario_sistema_id'] ?? 0);
        $password = trim((string) ($input['password'] ?? ''));

        if ($id <= 0 || $password === '') {
            throw new RuntimeException('Captura la nueva contraseña del usuario del sistema.');
        }

        if (mb_strlen($password, 'UTF-8') < 6) {
            throw new RuntimeException('La contraseña debe tener al menos 6 caracteres.');
        }

        $stmt = $this->db->prepare(
            "UPDATE usuarios_sistema
             SET password_hash = :password_hash,
                 ultimo_password_change_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute([
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'id' => $id,
        ]);

        return $this->obtener($id);
    }

    private function normalizar(array $input, bool $requirePassword): array
    {
        $telefono = preg_replace('/\D+/', '', (string) ($input['telefono'] ?? '')) ?? '';

        return [
            'nombre' => trim((string) ($input['nombre'] ?? '')),
            'usuario' => trim((string) ($input['usuario'] ?? '')),
            'telefono' => $telefono !== '' ? $telefono : null,
            'correo' => trim((string) ($input['correo'] ?? '')) ?: null,
            'password' => trim((string) ($input['password'] ?? '')),
            'rol' => trim((string) ($input['rol'] ?? 'cobrador')),
            'activo' => in_array((string) ($input['activo'] ?? '1'), ['1', 'true', 'on', 'si'], true) ? 1 : 0,
            'require_password' => $requirePassword,
        ];
    }

    private function validar(array $data, bool $isCreate, int $ignoreId = 0, array $actual = []): void
    {
        $errors = [];
        $roles = ['admin', 'cobrador', 'verificador'];

        if ($data['nombre'] === '') {
            $errors['nombre'] = 'Captura el nombre del usuario del sistema.';
        }

        if ($data['usuario'] === '') {
            $errors['usuario'] = 'Captura el usuario de acceso.';
        }

        if ($isCreate && mb_strlen($data['password'], 'UTF-8') < 6) {
            $errors['password'] = 'La contraseña inicial debe tener al menos 6 caracteres.';
        }

        if (!in_array($data['rol'], $roles, true)) {
            $errors['rol'] = 'Selecciona un rol válido para la plataforma.';
        }

        if ($data['correo'] !== null && !filter_var($data['correo'], FILTER_VALIDATE_EMAIL)) {
            $errors['correo'] = 'Captura un correo válido o déjalo vacío.';
        }

        if ($data['telefono'] !== null && strlen($data['telefono']) !== 10) {
                $errors['telefono'] = 'El teléfono debe tener 10 dígitos.';
        }

        $stmt = $this->db->prepare("SELECT id FROM usuarios_sistema WHERE usuario = :usuario AND id <> :id LIMIT 1");
        $stmt->execute([
            'usuario' => $data['usuario'],
            'id' => $ignoreId,
        ]);
        if ($stmt->fetch()) {
            $errors['usuario'] = 'Ya existe otro usuario del sistema con ese acceso.';
        }

        if ($data['correo'] !== null) {
            $stmt = $this->db->prepare("SELECT id FROM usuarios_sistema WHERE correo = :correo AND id <> :id LIMIT 1");
            $stmt->execute([
                'correo' => $data['correo'],
                'id' => $ignoreId,
            ]);
            if ($stmt->fetch()) {
                $errors['correo'] = 'Ese correo ya está ligado a otra cuenta del sistema.';
            }
        }

        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }
    }
}
