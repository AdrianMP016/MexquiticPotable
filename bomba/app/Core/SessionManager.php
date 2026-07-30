<?php

class SessionManager
{
    private const SESSION_KEY = 'mexquiticbomba_auth';
    private const COOKIE_NAME = 'mexquiticbomba_session';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name(self::COOKIE_NAME);

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/bomba/',
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
}
