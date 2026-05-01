<?php

class SessionManager
{
    private const SESSION_KEY = 'mexquitic_auth';
    private const DEFAULT_CONTEXT = 'plataforma';
    private const COOKIE_BASE = 'mexquitic_session_';
    private static string $context = self::DEFAULT_CONTEXT;

    public static function normalizeContext(?string $context): string
    {
        $context = strtolower(trim((string) $context));
        return in_array($context, ['plataforma', 'cobro', 'verificador'], true)
            ? $context
            : self::DEFAULT_CONTEXT;
    }

    public static function setContext(?string $context): void
    {
        $normalized = self::normalizeContext($context);

        if (session_status() === PHP_SESSION_ACTIVE) {
            if (session_name() !== self::sessionName($normalized)) {
                throw new RuntimeException('No se puede cambiar el contexto de sesion mientras la sesion ya esta activa.');
            }

            self::$context = $normalized;
            return;
        }

        self::$context = $normalized;
    }

    public static function context(): string
    {
        return self::$context;
    }

    public static function start(?string $context = null): void
    {
        if ($context !== null) {
            self::setContext($context);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name(self::sessionName(self::$context));

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    public static function setUser(array $user): void
    {
        self::start();
        $_SESSION[self::SESSION_KEY] = $user;
        $_SESSION[self::SESSION_KEY]['session_context'] = self::$context;
    }

    public static function user(): ?array
    {
        self::start();
        return isset($_SESSION[self::SESSION_KEY]) && is_array($_SESSION[self::SESSION_KEY])
            ? $_SESSION[self::SESSION_KEY]
            : null;
    }

    public static function userId(): ?int
    {
        $user = self::user();
        return $user ? (int) ($user['id'] ?? 0) : null;
    }

    public static function clear(): void
    {
        self::start();
        unset($_SESSION[self::SESSION_KEY]);
        session_regenerate_id(true);
    }

    private static function sessionName(string $context): string
    {
        return self::COOKIE_BASE . self::normalizeContext($context);
    }
}
