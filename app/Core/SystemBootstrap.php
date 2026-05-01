<?php

require_once __DIR__ . '/../Clases/BitacoraSistema.php';

class SystemBootstrap
{
    private static bool $bootstrapped = false;

    public static function ensure(PDO $db): void
    {
        if (self::$bootstrapped) {
            return;
        }

        self::$bootstrapped = true;
        self::ensureUsuariosSistema($db);
        self::ensureBitacora($db);
        self::ensureDefaultAdmin($db);
        self::ensureDefaultAccessUsers($db);
    }

    private static function ensureUsuariosSistema(PDO $db): void
    {
        if (!self::columnExists($db, 'usuarios_sistema', 'usuario')) {
            $db->exec("ALTER TABLE usuarios_sistema ADD COLUMN usuario VARCHAR(60) NULL AFTER nombre");
        }

        if (!self::columnExists($db, 'usuarios_sistema', 'correo')) {
            $db->exec("ALTER TABLE usuarios_sistema ADD COLUMN correo VARCHAR(150) NULL AFTER telefono");
        }

        if (!self::columnExists($db, 'usuarios_sistema', 'password_hash')) {
            $db->exec("ALTER TABLE usuarios_sistema ADD COLUMN password_hash VARCHAR(255) NULL AFTER correo");
        }

        if (!self::columnExists($db, 'usuarios_sistema', 'ultimo_login_at')) {
            $db->exec("ALTER TABLE usuarios_sistema ADD COLUMN ultimo_login_at DATETIME NULL AFTER password_hash");
        }

        if (!self::columnExists($db, 'usuarios_sistema', 'ultimo_login_ip')) {
            $db->exec("ALTER TABLE usuarios_sistema ADD COLUMN ultimo_login_ip VARCHAR(45) NULL AFTER ultimo_login_at");
        }

        if (!self::columnExists($db, 'usuarios_sistema', 'ultimo_acceso_at')) {
            $db->exec("ALTER TABLE usuarios_sistema ADD COLUMN ultimo_acceso_at DATETIME NULL AFTER ultimo_login_ip");
        }

        if (!self::columnExists($db, 'usuarios_sistema', 'ultimo_acceso_modulo')) {
            $db->exec("ALTER TABLE usuarios_sistema ADD COLUMN ultimo_acceso_modulo VARCHAR(60) NULL AFTER ultimo_acceso_at");
        }

        if (!self::columnExists($db, 'usuarios_sistema', 'ultimo_password_change_at')) {
            $db->exec("ALTER TABLE usuarios_sistema ADD COLUMN ultimo_password_change_at DATETIME NULL AFTER ultimo_acceso_modulo");
        }

        if (!self::columnExists($db, 'usuarios_sistema', 'updated_at')) {
            $db->exec("ALTER TABLE usuarios_sistema ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        }

        $db->exec(
            "ALTER TABLE usuarios_sistema
             MODIFY COLUMN rol ENUM('admin','capturista','cobrador','verificador','solo_lectura') NOT NULL DEFAULT 'cobrador'"
        );

        $stmt = $db->query("SELECT id, nombre, usuario FROM usuarios_sistema ORDER BY id");
        $rows = $stmt->fetchAll();
        $used = [];

        foreach ($rows as $row) {
            $username = trim((string) ($row['usuario'] ?? ''));
            if ($username !== '') {
                $used[mb_strtolower($username, 'UTF-8')] = true;
                continue;
            }

            $base = self::slugUsuario((string) ($row['nombre'] ?? 'usuario'));
            if ($base === '') {
                $base = 'usuario';
            }

            $candidate = $base;
            $suffix = 1;
            while (isset($used[mb_strtolower($candidate, 'UTF-8')])) {
                $suffix++;
                $candidate = $base . $suffix;
            }

            $used[mb_strtolower($candidate, 'UTF-8')] = true;

            $update = $db->prepare("UPDATE usuarios_sistema SET usuario = :usuario WHERE id = :id");
            $update->execute([
                'usuario' => $candidate,
                'id' => (int) $row['id'],
            ]);
        }

        $db->exec("ALTER TABLE usuarios_sistema MODIFY COLUMN usuario VARCHAR(60) NOT NULL");

        if (!self::indexExists($db, 'usuarios_sistema', 'uk_usuarios_sistema_usuario')) {
            $db->exec("ALTER TABLE usuarios_sistema ADD UNIQUE KEY uk_usuarios_sistema_usuario (usuario)");
        }

        if (!self::indexExists($db, 'usuarios_sistema', 'uk_usuarios_sistema_correo')) {
            $db->exec("ALTER TABLE usuarios_sistema ADD UNIQUE KEY uk_usuarios_sistema_correo (correo)");
        }
    }

    private static function ensureBitacora(PDO $db): void
    {
        $db->exec(
            "CREATE TABLE IF NOT EXISTS bitacora_sistema (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                usuario_sistema_id INT UNSIGNED NULL,
                nombre_usuario VARCHAR(120) NULL,
                rol VARCHAR(40) NULL,
                modulo VARCHAR(60) NOT NULL,
                accion VARCHAR(80) NOT NULL,
                referencia_tipo VARCHAR(80) NULL,
                referencia_id VARCHAR(120) NULL,
                descripcion TEXT NULL,
                payload_json LONGTEXT NULL,
                ip VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_bitacora_usuario (usuario_sistema_id),
                KEY idx_bitacora_modulo (modulo),
                KEY idx_bitacora_created_at (created_at),
                CONSTRAINT fk_bitacora_usuario
                    FOREIGN KEY (usuario_sistema_id) REFERENCES usuarios_sistema (id)
                    ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private static function ensureDefaultAdmin(PDO $db): void
    {
        $stmt = $db->query("SELECT COUNT(*) AS total FROM usuarios_sistema WHERE rol = 'admin'");
        $totalAdmins = (int) ($stmt->fetch()['total'] ?? 0);

        if ($totalAdmins > 0) {
            return;
        }

        $passwordHash = password_hash('admin', PASSWORD_DEFAULT);
        $stmt = $db->prepare(
            "INSERT INTO usuarios_sistema
                (nombre, usuario, telefono, correo, password_hash, rol, activo, ultimo_password_change_at)
             VALUES
                ('Administrador del sistema', 'admin', NULL, NULL, :password_hash, 'admin', 1, NOW())"
        );
        $stmt->execute(['password_hash' => $passwordHash]);
    }

    private static function ensureDefaultAccessUsers(PDO $db): void
    {
        self::ensureAccessUser(
            $db,
            'cobro',
            'COBRO',
            'cobrador',
            'cobro'
        );

        self::ensureAccessUser(
            $db,
            'verificador',
            'VERIFICADOR',
            'verificador',
            'verificador'
        );
    }

    private static function ensureAccessUser(PDO $db, string $username, string $nombre, string $rol, string $password): void
    {
        $stmt = $db->prepare("SELECT id FROM usuarios_sistema WHERE usuario = :usuario LIMIT 1");
        $stmt->execute(['usuario' => $username]);

        if ($stmt->fetch()) {
            return;
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $insert = $db->prepare(
            "INSERT INTO usuarios_sistema
                (nombre, usuario, telefono, correo, password_hash, rol, activo, ultimo_password_change_at)
             VALUES
                (:nombre, :usuario, NULL, NULL, :password_hash, :rol, 1, NOW())"
        );
        $insert->execute([
            'nombre' => $nombre,
            'usuario' => $username,
            'password_hash' => $passwordHash,
            'rol' => $rol,
        ]);
    }

    private static function columnExists(PDO $db, string $table, string $column): bool
    {
        $table = str_replace('`', '``', $table);
        $column = str_replace("'", "\\'", $column);
        $stmt = $db->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private static function indexExists(PDO $db, string $table, string $index): bool
    {
        $table = str_replace('`', '``', $table);
        $index = str_replace("'", "\\'", $index);
        $stmt = $db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$index}'");
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private static function slugUsuario(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('/[^a-z0-9]+/', '.', $value) ?? '';
        return trim($value, '.');
    }
}
