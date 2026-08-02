<?php

class SystemBootstrap
{
    private static bool $bootstrapped = false;

    public static function ensure(PDO $db): void
    {
        if (self::$bootstrapped) {
            return;
        }

        self::$bootstrapped = true;
        self::ensureUsuariosBomba($db);
        self::ensureBitacoraBomba($db);
        self::ensureReglaAutomatica($db);
        self::ensureActivaciones($db);
        self::ensureConfig($db);
        self::ensurePushSubscripciones($db);
        self::ensureDefaultAdmin($db);
    }

    private static function ensureUsuariosBomba(PDO $db): void
    {
        $db->exec(
            "CREATE TABLE IF NOT EXISTS usuarios_bomba (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                nombre VARCHAR(120) NOT NULL,
                usuario VARCHAR(60) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                rol ENUM('admin','operador') NOT NULL DEFAULT 'operador',
                activo TINYINT(1) NOT NULL DEFAULT 1,
                ultimo_login_at DATETIME NULL,
                ultimo_login_ip VARCHAR(45) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_usuarios_bomba_usuario (usuario)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private static function ensureBitacoraBomba(PDO $db): void
    {
        $db->exec(
            "CREATE TABLE IF NOT EXISTS bitacora_bomba (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                usuario_bomba_id INT UNSIGNED NULL,
                nombre_usuario VARCHAR(120) NULL,
                rol VARCHAR(40) NULL,
                accion VARCHAR(80) NOT NULL,
                referencia_tipo VARCHAR(80) NULL,
                referencia_id VARCHAR(120) NULL,
                descripcion TEXT NULL,
                payload_json LONGTEXT NULL,
                ip VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_bitacora_bomba_usuario (usuario_bomba_id),
                KEY idx_bitacora_bomba_accion (accion),
                KEY idx_bitacora_bomba_created_at (created_at),
                CONSTRAINT fk_bitacora_bomba_usuario
                    FOREIGN KEY (usuario_bomba_id) REFERENCES usuarios_bomba (id)
                    ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private static function ensureReglaAutomatica(PDO $db): void
    {
        $db->exec(
            "CREATE TABLE IF NOT EXISTS bomba_regla_automatica (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                hora_inicio TIME NOT NULL,
                hora_fin TIME NOT NULL,
                dias_semana VARCHAR(20) NOT NULL,
                activa TINYINT(1) NOT NULL DEFAULT 1,
                creado_por_usuario_id INT UNSIGNED NULL,
                creado_por_nombre VARCHAR(120) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                reemplazada_at DATETIME NULL,
                PRIMARY KEY (id),
                KEY idx_regla_activa (activa),
                CONSTRAINT fk_regla_usuario
                    FOREIGN KEY (creado_por_usuario_id) REFERENCES usuarios_bomba (id)
                    ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private static function ensureActivaciones(PDO $db): void
    {
        $db->exec(
            "CREATE TABLE IF NOT EXISTS bomba_activaciones (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                origen ENUM('manual','cronometro','automatico') NOT NULL,
                iniciado_por_usuario_id INT UNSIGNED NULL,
                iniciado_por_nombre VARCHAR(120) NULL,
                regla_automatica_id INT UNSIGNED NULL,
                inicio_at DATETIME NOT NULL,
                fin_at DATETIME NULL,
                duracion_segundos INT UNSIGNED NULL,
                fin_motivo ENUM('manual','cronometro_expirado','regla_fin','error','forzado') NULL,
                cronometro_duracion_segundos INT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_activaciones_inicio (inicio_at),
                KEY idx_activaciones_fin_null (fin_at),
                KEY idx_activaciones_origen (origen),
                CONSTRAINT fk_activaciones_usuario
                    FOREIGN KEY (iniciado_por_usuario_id) REFERENCES usuarios_bomba (id)
                    ON DELETE SET NULL,
                CONSTRAINT fk_activaciones_regla
                    FOREIGN KEY (regla_automatica_id) REFERENCES bomba_regla_automatica (id)
                    ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private static function ensureConfig(PDO $db): void
    {
        $db->exec(
            "CREATE TABLE IF NOT EXISTS bomba_config (
                clave VARCHAR(80) NOT NULL,
                valor VARCHAR(255) NOT NULL,
                tipo ENUM('string','number','bool','json') NOT NULL DEFAULT 'string',
                descripcion VARCHAR(255) NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (clave)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $seeds = [
            [
                'clave' => 'proteccion_delay_segundos',
                'valor' => '2',
                'tipo' => 'number',
                'descripcion' => 'Segundos de espera obligatoria entre comandos de encendido/apagado.',
            ],
            [
                'clave' => 'ultimo_comando_at',
                'valor' => '',
                'tipo' => 'string',
                'descripcion' => 'Marca de tiempo del ultimo comando enviado a Shelly (control de espera).',
            ],
            [
                'clave' => 'cron_ultima_ejecucion_at',
                'valor' => '',
                'tipo' => 'string',
                'descripcion' => 'Ultima vez que corrio el verificador automatico (diagnostico).',
            ],
            [
                'clave' => 'cron_ultimo_resultado',
                'valor' => '',
                'tipo' => 'string',
                'descripcion' => 'Resultado de la ultima corrida del cron (ok / error: ...).',
            ],
            [
                'clave' => 'sim_relay_encendido',
                'valor' => '0',
                'tipo' => 'bool',
                'descripcion' => 'Estado simulado del rele mientras no hay credenciales reales de Shelly Cloud.',
            ],
            [
                'clave' => 'sim_temperatura_c',
                'valor' => '22.5',
                'tipo' => 'number',
                'descripcion' => 'Temperatura simulada del sensor H&T mientras no hay credenciales reales de Shelly Cloud.',
            ],
            [
                'clave' => 'sim_humedad_pct',
                'valor' => '45',
                'tipo' => 'number',
                'descripcion' => 'Humedad simulada del sensor H&T mientras no hay credenciales reales de Shelly Cloud.',
            ],
            [
                'clave' => 'cache_sensor_leido_en',
                'valor' => '',
                'tipo' => 'string',
                'descripcion' => 'Momento en que se consulto realmente Shelly Cloud por ultima vez (para no exceder su limite de peticiones).',
            ],
            [
                'clave' => 'cache_sensor_temperatura_c',
                'valor' => '',
                'tipo' => 'number',
                'descripcion' => 'Ultima temperatura leida de Shelly Cloud (cache).',
            ],
            [
                'clave' => 'cache_sensor_humedad_pct',
                'valor' => '',
                'tipo' => 'number',
                'descripcion' => 'Ultima humedad leida de Shelly Cloud (cache).',
            ],
            [
                'clave' => 'cache_sensor_bateria_pct',
                'valor' => '',
                'tipo' => 'number',
                'descripcion' => 'Ultima bateria leida de Shelly Cloud (cache).',
            ],
            [
                'clave' => 'cache_sensor_actualizado_at',
                'valor' => '',
                'tipo' => 'string',
                'descripcion' => 'Marca de tiempo que reporto el propio sensor Shelly en su ultimo reporte (cache).',
            ],
        ];

        $stmt = $db->prepare(
            "INSERT INTO bomba_config (clave, valor, tipo, descripcion)
             VALUES (:clave, :valor, :tipo, :descripcion)
             ON DUPLICATE KEY UPDATE
                tipo = VALUES(tipo),
                descripcion = VALUES(descripcion)"
        );

        foreach ($seeds as $seed) {
            $stmt->execute($seed);
        }
    }

    private static function ensurePushSubscripciones(PDO $db): void
    {
        $db->exec(
            "CREATE TABLE IF NOT EXISTS bomba_push_subscripciones (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                usuario_bomba_id INT UNSIGNED NULL,
                endpoint TEXT NOT NULL,
                endpoint_hash CHAR(64) NOT NULL,
                p256dh_key VARCHAR(255) NOT NULL,
                auth_key VARCHAR(255) NOT NULL,
                user_agent VARCHAR(255) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_push_endpoint_hash (endpoint_hash),
                CONSTRAINT fk_push_usuario
                    FOREIGN KEY (usuario_bomba_id) REFERENCES usuarios_bomba (id)
                    ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private static function ensureDefaultAdmin(PDO $db): void
    {
        $stmt = $db->query("SELECT COUNT(*) AS total FROM usuarios_bomba WHERE rol = 'admin'");
        $totalAdmins = (int) ($stmt->fetch()['total'] ?? 0);

        if ($totalAdmins > 0) {
            return;
        }

        $passwordHash = password_hash('admin', PASSWORD_DEFAULT);
        $stmt = $db->prepare(
            "INSERT INTO usuarios_bomba (nombre, usuario, password_hash, rol, activo)
             VALUES ('Administrador', 'admin', :password_hash, 'admin', 1)"
        );
        $stmt->execute(['password_hash' => $passwordHash]);
    }
}
