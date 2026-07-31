<?php

class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $configPath = __DIR__ . '/../Config/database.php';
        if (!is_file($configPath)) {
            $configPath = __DIR__ . '/../Config/database.example.php';
        }

        $config = require $configPath;

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        try {
            self::$connection = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
        } catch (PDOException $exception) {
            // Diagnostico temporal: hay un 500 en todo el sitio raiz y el log
            // de Hostinger esta saturado de ruido ajeno. Se deja constancia
            // exacta del motivo aqui para poder verlo sin depender de ese log.
            @file_put_contents(
                __DIR__ . '/../db_connect_error.log',
                date('Y-m-d H:i:s') . ' ' . $exception->getMessage() . PHP_EOL,
                FILE_APPEND
            );
            throw $exception;
        }

        return self::$connection;
    }
}
