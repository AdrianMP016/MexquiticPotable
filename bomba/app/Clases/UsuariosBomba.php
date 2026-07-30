<?php

class UsuariosBomba
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function listar(): array
    {
        $stmt = $this->db->query(
            "SELECT id, nombre, usuario, rol, activo, ultimo_login_at, created_at
             FROM usuarios_bomba
             ORDER BY nombre ASC"
        );

        return $stmt->fetchAll();
    }

    public function obtener(int $id): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, nombre, usuario, rol, activo, ultimo_login_at, created_at
             FROM usuarios_bomba WHERE id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new RuntimeException('No se encontro el usuario solicitado.');
        }

        return $row;
    }

    public function guardar(array $input): array
    {
        $data = $this->normalizar($input);
        $errors = $this->validar($data, true);

        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        $stmt = $this->db->prepare(
            "INSERT INTO usuarios_bomba (nombre, usuario, password_hash, rol, activo)
             VALUES (:nombre, :usuario, :password_hash, :rol, :activo)"
        );
        $stmt->execute([
            'nombre' => $data['nombre'],
            'usuario' => $data['usuario'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'rol' => $data['rol'],
            'activo' => $data['activo'],
        ]);

        return $this->obtener((int) $this->db->lastInsertId());
    }

    public function actualizar(array $input): array
    {
        $id = (int) ($input['id'] ?? 0);

        if ($id <= 0) {
            throw new RuntimeException('No se recibio el usuario a actualizar.');
        }

        $data = $this->normalizar($input);
        $errors = $this->validar($data, false, $id);

        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        $stmt = $this->db->prepare(
            "UPDATE usuarios_bomba
             SET nombre = :nombre, usuario = :usuario, rol = :rol, activo = :activo
             WHERE id = :id"
        );
        $stmt->execute([
            'nombre' => $data['nombre'],
            'usuario' => $data['usuario'],
            'rol' => $data['rol'],
            'activo' => $data['activo'],
            'id' => $id,
        ]);

        return $this->obtener($id);
    }

    public function restablecerPassword(int $id, string $password): array
    {
        if ($id <= 0 || trim($password) === '') {
            throw new RuntimeException('Captura la nueva contraseña del usuario.');
        }

        if (mb_strlen($password, 'UTF-8') < 6) {
            throw new RuntimeException('La contraseña debe tener al menos 6 caracteres.');
        }

        $stmt = $this->db->prepare("UPDATE usuarios_bomba SET password_hash = :password_hash WHERE id = :id");
        $stmt->execute([
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'id' => $id,
        ]);

        return $this->obtener($id);
    }

    public function darDeBaja(int $id, int $currentUserId): array
    {
        if ($id <= 0) {
            throw new RuntimeException('No se recibio el usuario a dar de baja.');
        }

        if ($id === $currentUserId) {
            throw new RuntimeException('No puedes darte de baja a ti mismo.');
        }

        $actual = $this->obtener($id);

        if ((string) $actual['rol'] === 'admin' && (int) $actual['activo'] === 1) {
            $stmt = $this->db->query("SELECT COUNT(*) AS total FROM usuarios_bomba WHERE rol='admin' AND activo=1");
            $totalAdmins = (int) ($stmt->fetch()['total'] ?? 0);

            if ($totalAdmins <= 1) {
                throw new RuntimeException('Debe quedar al menos un administrador activo.');
            }
        }

        $stmt = $this->db->prepare("UPDATE usuarios_bomba SET activo = 0 WHERE id = :id");
        $stmt->execute(['id' => $id]);

        return $this->obtener($id);
    }

    private function normalizar(array $input): array
    {
        return [
            'nombre' => trim((string) ($input['nombre'] ?? '')),
            'usuario' => trim((string) ($input['usuario'] ?? '')),
            'password' => trim((string) ($input['password'] ?? '')),
            'rol' => in_array($input['rol'] ?? '', ['admin', 'operador'], true) ? $input['rol'] : 'operador',
            'activo' => filter_var($input['activo'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
        ];
    }

    private function validar(array $data, bool $esNuevo, int $idActual = 0): array
    {
        $errors = [];

        if ($data['nombre'] === '') {
            $errors['nombre'] = 'Captura el nombre.';
        }

        if ($data['usuario'] === '') {
            $errors['usuario'] = 'Captura el usuario.';
        } else {
            $stmt = $this->db->prepare(
                "SELECT id FROM usuarios_bomba WHERE usuario = :usuario AND id <> :id LIMIT 1"
            );
            $stmt->execute(['usuario' => $data['usuario'], 'id' => $idActual]);
            if ($stmt->fetch()) {
                $errors['usuario'] = 'Ya existe un usuario con ese nombre de acceso.';
            }
        }

        if ($esNuevo && mb_strlen($data['password'], 'UTF-8') < 6) {
            $errors['password'] = 'La contraseña debe tener al menos 6 caracteres.';
        }

        return $errors;
    }
}
