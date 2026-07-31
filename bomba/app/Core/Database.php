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

        self::$connection = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            // "time_zone" alinea el CURRENT_TIMESTAMP/NOW() de MySQL (que por
            // defecto corre en UTC en el servidor) con la hora de Mexico que ya
            // usa PHP (ver date_default_timezone_set en bootstrap.php) — sin
            // esto, columnas como created_at (llenadas automaticamente por
            // MySQL, no por PHP) quedan varias horas adelantadas.
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '-06:00'",
        ]);

        return self::$connection;
    }
}
