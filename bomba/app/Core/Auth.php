<?php

require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/../Clases/BitacoraBomba.php';

class HttpException extends RuntimeException
{
    private int $statusCode;

    public function __construct(string $message, int $statusCode = 400)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}

class Auth
{
    private PDO $db;
    private BitacoraBomba $bitacora;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->bitacora = new BitacoraBomba($db);
    }

    public function login(string $usuario, string $password): array
    {
        $usuario = trim($usuario);
        $password = trim($password);

        if ($usuario === '' || $password === '') {
            throw new HttpException('Captura usuario y contraseña.', 422);
        }

        $stmt = $this->db->prepare(
            "SELECT id, nombre, usuario, rol, activo, password_hash
             FROM usuarios_bomba
             WHERE usuario = :usuario
             LIMIT 1"
        );
        $stmt->execute(['usuario' => $usuario]);
        $user = $stmt->fetch();

        if (!$user || !(int) ($user['activo'] ?? 0)) {
            throw new HttpException('La cuenta no esta disponible para iniciar sesion.', 403);
        }

        if (empty($user['password_hash']) || !password_verify($password, (string) $user['password_hash'])) {
            throw new HttpException('Las credenciales no coinciden.', 401);
        }

        $payload = $this->buildSessionUser($user);

        SessionManager::regenerate();
        SessionManager::setUser($payload);

        $stmt = $this->db->prepare(
            "UPDATE usuarios_bomba SET ultimo_login_at = NOW(), ultimo_login_ip = :ip WHERE id = :id"
        );
        $stmt->execute([
            'ip' => $this->requestIp(),
            'id' => (int) $payload['id'],
        ]);

        $this->registrarBitacora($payload, 'login', 'Inicio de sesion correcto.');

        return $payload;
    }

    public function logout(): void
    {
        $user = $this->user();
        if ($user) {
            $this->registrarBitacora($user, 'logout', 'Sesion cerrada manualmente.');
        }

        SessionManager::clear();
    }

    public function user(): ?array
    {
        return SessionManager::user();
    }

    public function userId(): ?int
    {
        return SessionManager::userId();
    }

    public function requireLogin(): array
    {
        $user = $this->user();

        if (!$user) {
            throw new HttpException('La sesión ya no está activa.', 401);
        }

        return $user;
    }

    public function requireRol(array $rolesPermitidos): array
    {
        $user = $this->requireLogin();

        if (!in_array((string) $user['rol'], $rolesPermitidos, true)) {
            throw new HttpException('Tu perfil no tiene acceso a esta accion.', 403);
        }

        return $user;
    }

    private function buildSessionUser(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'nombre' => (string) $user['nombre'],
            'usuario' => (string) $user['usuario'],
            'rol' => (string) $user['rol'],
            'activo' => (int) $user['activo'],
        ];
    }

    private function registrarBitacora(array $user, string $accion, string $descripcion): void
    {
        $this->bitacora->registrar([
            'usuario_bomba_id' => (int) ($user['id'] ?? 0),
            'nombre_usuario' => (string) ($user['nombre'] ?? ''),
            'rol' => (string) ($user['rol'] ?? ''),
            'accion' => $accion,
            'descripcion' => $descripcion,
            'ip' => $this->requestIp(),
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    }

    private function requestIp(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'), 0, 45);
    }
}
