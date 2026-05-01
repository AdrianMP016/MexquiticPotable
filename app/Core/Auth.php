<?php

require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/../Clases/BitacoraSistema.php';

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
    private BitacoraSistema $bitacora;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->bitacora = new BitacoraSistema($db);
    }

    public function login(string $usuario, string $password, string $modulo): array
    {
        $usuario = trim($usuario);
        $password = trim($password);
        $modulo = $this->normalizarModulo($modulo);
        SessionManager::setContext($modulo);

        if ($usuario === '' || $password === '') {
            throw new HttpException('Captura usuario y contraseña.', 422);
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
                password_hash,
                ultimo_login_at,
                ultimo_login_ip,
                ultimo_acceso_at,
                ultimo_acceso_modulo
             FROM usuarios_sistema
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

        if (!$this->canAccessModule((string) $user['rol'], $modulo)) {
            throw new HttpException('Tu perfil no tiene acceso a este entorno.', 403);
        }

        $payload = $this->buildSessionUser($user);

        SessionManager::regenerate();
        SessionManager::setUser($payload);

        $stmt = $this->db->prepare(
            "UPDATE usuarios_sistema
             SET ultimo_login_at = NOW(),
                 ultimo_login_ip = :ip,
                 ultimo_acceso_at = NOW(),
                 ultimo_acceso_modulo = :modulo
             WHERE id = :id"
        );
        $stmt->execute([
            'ip' => $this->requestIp(),
            'modulo' => $modulo,
            'id' => (int) $payload['id'],
        ]);

        $this->registrarBitacora($payload, $modulo, 'login', 'Inicio de sesion correcto.');

        return $this->sessionPayload($modulo);
    }

    public function logout(?string $modulo = null): void
    {
        if ($modulo !== null) {
            SessionManager::setContext($this->normalizarModulo($modulo));
        }

        $user = $this->user();
        if ($user) {
            $this->registrarBitacora($user, SessionManager::context(), 'logout', 'Sesion cerrada manualmente.');
        }

        SessionManager::clear();
    }

    public function recuperarPassword(string $usuario, string $telefono, string $nuevaPassword): array
    {
        $usuario = trim($usuario);
        $telefono = preg_replace('/\D+/', '', $telefono) ?? '';
        $nuevaPassword = trim($nuevaPassword);

        if ($usuario === '' || $telefono === '' || $nuevaPassword === '') {
            throw new HttpException('Completa usuario, telefono y nueva contraseña.', 422);
        }

        if (mb_strlen($nuevaPassword, 'UTF-8') < 6) {
            throw new HttpException('La nueva contraseña debe tener al menos 6 caracteres.', 422);
        }

        $stmt = $this->db->prepare(
            "SELECT id, nombre, usuario, telefono, rol, activo
             FROM usuarios_sistema
             WHERE usuario = :usuario
             LIMIT 1"
        );
        $stmt->execute(['usuario' => $usuario]);
        $row = $stmt->fetch();

        if (!$row || !(int) ($row['activo'] ?? 0)) {
            throw new HttpException('No se encontro una cuenta activa con ese usuario.', 404);
        }

        $telefonoGuardado = preg_replace('/\D+/', '', (string) ($row['telefono'] ?? '')) ?? '';
        if ($telefonoGuardado === '' || $telefonoGuardado !== $telefono) {
            throw new HttpException('El telefono no coincide con el registrado para esta cuenta.', 403);
        }

        $stmt = $this->db->prepare(
            "UPDATE usuarios_sistema
             SET password_hash = :password_hash,
                 ultimo_password_change_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute([
            'password_hash' => password_hash($nuevaPassword, PASSWORD_DEFAULT),
            'id' => (int) $row['id'],
        ]);

        $this->registrarBitacora($row, 'sistema', 'recuperacion_password', 'Contraseña restablecida desde la pantalla de acceso.');

        return ['usuario' => $row['usuario']];
    }

    public function sessionPayload(?string $requestedModule = null): array
    {
        $user = $this->user();
        if (!$user) {
            throw new HttpException('La sesión ya no está activa.', 401);
        }

        $currentModule = $this->normalizarModulo($requestedModule ?: SessionManager::context());

        return [
            'user' => $user,
            'current_module' => $currentModule,
            'modules' => [
                'plataforma' => $this->canAccessModule((string) $user['rol'], 'plataforma'),
                'cobro' => $this->canAccessModule((string) $user['rol'], 'cobro'),
                'verificador' => $this->canAccessModule((string) $user['rol'], 'verificador'),
            ],
            'destinos' => [
                'plataforma' => 'index.php',
                'cobro' => 'pago-campo.php',
                'verificador' => 'verificador.php',
            ],
            'destino_actual' => $this->defaultDestination($user),
            'logout_url' => 'logout.php?module=' . rawurlencode($currentModule),
            'login_urls' => [
                'plataforma' => 'login-admin.php',
                'cobro' => 'login-cobro.php',
                'verificador' => 'login-verificador.php',
            ],
        ];
    }

    public function user(): ?array
    {
        return SessionManager::user();
    }

    public function userId(): ?int
    {
        return SessionManager::userId();
    }

    public function requireModule(string $modulo): array
    {
        $payload = $this->sessionPayload($modulo);
        $user = $payload['user'];

        if (!$this->canAccessModule((string) $user['rol'], $modulo)) {
            throw new HttpException('Tu perfil no tiene acceso a este entorno.', 403);
        }

        return $user;
    }

    public function registrarAccesoModulo(string $modulo, string $descripcion = 'Ingreso a entorno'): void
    {
        $user = $this->user();
        if (!$user) {
            return;
        }

        $stmt = $this->db->prepare(
            "UPDATE usuarios_sistema
             SET ultimo_acceso_at = NOW(),
                 ultimo_acceso_modulo = :modulo
             WHERE id = :id"
        );
        $stmt->execute([
            'modulo' => $modulo,
            'id' => (int) $user['id'],
        ]);

        $this->registrarBitacora($user, $modulo, 'acceso_modulo', $descripcion);
    }

    public function canAccessModule(string $rol, string $modulo): bool
    {
        $map = [
            'admin' => ['plataforma', 'cobro', 'verificador'],
            'capturista' => ['plataforma'],
            'solo_lectura' => ['plataforma'],
            'cobrador' => ['cobro'],
            'verificador' => ['verificador'],
        ];

        $rol = trim($rol);
        $modulo = $this->normalizarModulo($modulo);

        return in_array($modulo, $map[$rol] ?? [], true);
    }

    public function defaultDestination(?array $user = null): string
    {
        $user = $user ?: $this->user();
        $rol = (string) ($user['rol'] ?? '');

        if ($rol === 'cobrador') {
            return 'pago-campo.php';
        }

        if ($rol === 'verificador') {
            return 'verificador.php';
        }

        return 'index.php';
    }

    public function normalizarModulo(string $modulo): string
    {
        $modulo = strtolower(trim($modulo));
        return in_array($modulo, ['plataforma', 'cobro', 'verificador'], true) ? $modulo : 'plataforma';
    }

    private function buildSessionUser(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'nombre' => (string) $user['nombre'],
            'usuario' => (string) $user['usuario'],
            'telefono' => $user['telefono'],
            'correo' => $user['correo'] ?? null,
            'rol' => (string) $user['rol'],
            'activo' => (int) $user['activo'],
        ];
    }

    private function registrarBitacora(array $user, string $modulo, string $accion, string $descripcion): void
    {
        $this->bitacora->registrar([
            'usuario_sistema_id' => (int) ($user['id'] ?? 0),
            'nombre_usuario' => (string) ($user['nombre'] ?? ''),
            'rol' => (string) ($user['rol'] ?? ''),
            'modulo' => $modulo,
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
