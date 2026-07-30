-- Esquema de la base de datos para Control de Bomba (proyecto aislado, no toca mezquitic_agua)
--
-- Uso en Hostinger (hPanel):
--   1. Crea una base de datos nueva y un usuario con permisos sobre ella (hPanel > Bases de datos).
--   2. Importa este archivo con phpMyAdmin (o "Importar" en hPanel) sobre esa base vacia.
--   3. No hace falta insertar nada mas a mano: la primera vez que cargue cualquier
--      pagina de bomba/, SystemBootstrap.php crea el usuario admin/admin y los valores
--      de configuracion por defecto automaticamente.
--
-- Uso en local (WAMP):
--   mysql -uroot -padmin < mezquitic_bomba_schema.sql

CREATE DATABASE IF NOT EXISTS mezquitic_bomba CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mezquitic_bomba;

-- Evita errores de llave foranea si este archivo se vuelve a importar sobre una base ya existente.
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------

DROP TABLE IF EXISTS `usuarios_bomba`;
CREATE TABLE `usuarios_bomba` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `usuario` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol` enum('admin','operador') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'operador',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `ultimo_login_at` datetime DEFAULT NULL,
  `ultimo_login_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_usuarios_bomba_usuario` (`usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

DROP TABLE IF EXISTS `bomba_regla_automatica`;
CREATE TABLE `bomba_regla_automatica` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `dias_semana` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT '1',
  `creado_por_usuario_id` int unsigned DEFAULT NULL,
  `creado_por_nombre` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reemplazada_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_regla_activa` (`activa`),
  KEY `fk_regla_usuario` (`creado_por_usuario_id`),
  CONSTRAINT `fk_regla_usuario` FOREIGN KEY (`creado_por_usuario_id`) REFERENCES `usuarios_bomba` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

DROP TABLE IF EXISTS `bomba_activaciones`;
CREATE TABLE `bomba_activaciones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `origen` enum('manual','cronometro','automatico') COLLATE utf8mb4_unicode_ci NOT NULL,
  `iniciado_por_usuario_id` int unsigned DEFAULT NULL,
  `iniciado_por_nombre` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `regla_automatica_id` int unsigned DEFAULT NULL,
  `inicio_at` datetime NOT NULL,
  `fin_at` datetime DEFAULT NULL,
  `duracion_segundos` int unsigned DEFAULT NULL,
  `fin_motivo` enum('manual','cronometro_expirado','regla_fin','error','forzado') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cronometro_duracion_segundos` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_activaciones_inicio` (`inicio_at`),
  KEY `idx_activaciones_fin_null` (`fin_at`),
  KEY `idx_activaciones_origen` (`origen`),
  KEY `fk_activaciones_usuario` (`iniciado_por_usuario_id`),
  KEY `fk_activaciones_regla` (`regla_automatica_id`),
  CONSTRAINT `fk_activaciones_regla` FOREIGN KEY (`regla_automatica_id`) REFERENCES `bomba_regla_automatica` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_activaciones_usuario` FOREIGN KEY (`iniciado_por_usuario_id`) REFERENCES `usuarios_bomba` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

DROP TABLE IF EXISTS `bitacora_bomba`;
CREATE TABLE `bitacora_bomba` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_bomba_id` int unsigned DEFAULT NULL,
  `nombre_usuario` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rol` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accion` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `referencia_tipo` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referencia_id` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `payload_json` longtext COLLATE utf8mb4_unicode_ci,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bitacora_bomba_usuario` (`usuario_bomba_id`),
  KEY `idx_bitacora_bomba_accion` (`accion`),
  KEY `idx_bitacora_bomba_created_at` (`created_at`),
  CONSTRAINT `fk_bitacora_bomba_usuario` FOREIGN KEY (`usuario_bomba_id`) REFERENCES `usuarios_bomba` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

DROP TABLE IF EXISTS `bomba_config`;
CREATE TABLE `bomba_config` (
  `clave` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('string','number','bool','json') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'string',
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
