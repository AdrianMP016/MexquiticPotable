-- MySQL dump 10.13  Distrib 9.1.0, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: mezquitic_agua
-- ------------------------------------------------------
-- Server version	8.0.42

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `bitacora_sistema`
--

DROP TABLE IF EXISTS `bitacora_sistema`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bitacora_sistema` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_sistema_id` int unsigned DEFAULT NULL,
  `nombre_usuario` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rol` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modulo` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `accion` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `referencia_tipo` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referencia_id` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `payload_json` longtext COLLATE utf8mb4_unicode_ci,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bitacora_usuario` (`usuario_sistema_id`),
  KEY `idx_bitacora_modulo` (`modulo`),
  KEY `idx_bitacora_created_at` (`created_at`),
  CONSTRAINT `fk_bitacora_usuario` FOREIGN KEY (`usuario_sistema_id`) REFERENCES `usuarios_sistema` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=306 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `comunidades`
--

DROP TABLE IF EXISTS `comunidades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `comunidades` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prefijo_ruta` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `prefijo_ruta` (`prefijo_ruta`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `conceptos_cobro`
--

DROP TABLE IF EXISTS `conceptos_cobro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conceptos_cobro` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('consumo','cooperacion','multa','recargo','ajuste') COLLATE utf8mb4_unicode_ci NOT NULL,
  `monto_default` decimal(10,2) NOT NULL DEFAULT '0.00',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `domicilios`
--

DROP TABLE IF EXISTS `domicilios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `domicilios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint unsigned NOT NULL,
  `comunidad_id` int unsigned NOT NULL,
  `calle` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `colonia` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitud` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `longitud` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `google_place_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modo_ubicacion` enum('google_maps','manual','aproximada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `referencia_ubicacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fachada_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ruta` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_domicilio_usuario` (`usuario_id`),
  KEY `idx_domicilio_comunidad` (`comunidad_id`),
  KEY `idx_domicilio_google_place` (`google_place_id`),
  KEY `idx_domicilio_ruta` (`ruta`),
  KEY `idx_domicilio_modo_ubicacion` (`modo_ubicacion`),
  CONSTRAINT `fk_domicilios_comunidad` FOREIGN KEY (`comunidad_id`) REFERENCES `comunidades` (`id`),
  CONSTRAINT `fk_domicilios_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_servicio` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=684 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lecturas`
--

DROP TABLE IF EXISTS `lecturas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lecturas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `medidor_id` bigint unsigned NOT NULL,
  `periodo_id` int unsigned NOT NULL,
  `lectura_anterior` decimal(12,2) NOT NULL DEFAULT '0.00',
  `lectura_actual` decimal(12,2) NOT NULL DEFAULT '0.00',
  `consumo_m3` decimal(12,2) GENERATED ALWAYS AS (greatest((`lectura_actual` - `lectura_anterior`),0)) STORED,
  `capturado_por_id` int unsigned DEFAULT NULL,
  `fecha_captura` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `latitud` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `longitud` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `foto_medicion_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `medidores`
--

DROP TABLE IF EXISTS `medidores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `medidores` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint unsigned NOT NULL,
  `domicilio_id` bigint unsigned NOT NULL,
  `numero` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` enum('activo','inactivo','reemplazado','sin_medidor') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'activo',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_medidor_numero` (`numero`),
  KEY `idx_medidor_domicilio` (`domicilio_id`),
  KEY `idx_medidor_usuario` (`usuario_id`),
  CONSTRAINT `fk_medidores_domicilio` FOREIGN KEY (`domicilio_id`) REFERENCES `domicilios` (`id`),
  CONSTRAINT `fk_medidores_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_servicio` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=684 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pagos`
--

DROP TABLE IF EXISTS `pagos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pagos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `recibo_id` bigint unsigned NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `fecha_pago` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `metodo` enum('efectivo','transferencia','spei','tarjeta','otro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'efectivo',
  `referencia` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capturado_por_id` int unsigned DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_pagos_recibo` (`recibo_id`),
  KEY `fk_pagos_capturado_por` (`capturado_por_id`),
  CONSTRAINT `fk_pagos_capturado_por` FOREIGN KEY (`capturado_por_id`) REFERENCES `usuarios_sistema` (`id`),
  CONSTRAINT `fk_pagos_recibo` FOREIGN KEY (`recibo_id`) REFERENCES `recibos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `periodos_bimestrales`
--

DROP TABLE IF EXISTS `periodos_bimestrales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `periodos_bimestrales` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `anio` smallint unsigned NOT NULL,
  `bimestre` tinyint unsigned NOT NULL,
  `mes_inicio` tinyint unsigned NOT NULL,
  `mes_fin` tinyint unsigned NOT NULL,
  `nombre` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `fecha_emision` date DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `estado` enum('abierto','cerrado','cancelado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'abierto',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_periodo` (`anio`,`bimestre`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `recibo_detalles`
--

DROP TABLE IF EXISTS `recibo_detalles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recibo_detalles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `recibo_id` bigint unsigned NOT NULL,
  `concepto_id` int unsigned DEFAULT NULL,
  `descripcion` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cantidad` decimal(10,2) NOT NULL DEFAULT '1.00',
  `precio_unitario` decimal(10,2) NOT NULL DEFAULT '0.00',
  `importe` decimal(10,2) GENERATED ALWAYS AS ((`cantidad` * `precio_unitario`)) STORED,
  PRIMARY KEY (`id`),
  KEY `fk_recibo_detalles_recibo` (`recibo_id`),
  KEY `fk_recibo_detalles_concepto` (`concepto_id`),
  CONSTRAINT `fk_recibo_detalles_concepto` FOREIGN KEY (`concepto_id`) REFERENCES `conceptos_cobro` (`id`),
  CONSTRAINT `fk_recibo_detalles_recibo` FOREIGN KEY (`recibo_id`) REFERENCES `recibos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `recibos`
--

DROP TABLE IF EXISTS `recibos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recibos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `folio` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `usuario_id` bigint unsigned NOT NULL,
  `domicilio_id` bigint unsigned NOT NULL,
  `medidor_id` bigint unsigned NOT NULL,
  `periodo_id` int unsigned NOT NULL,
  `lectura_id` bigint unsigned DEFAULT NULL,
  `consumo_m3` decimal(12,2) NOT NULL DEFAULT '0.00',
  `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
  `multas` decimal(10,2) NOT NULL DEFAULT '0.00',
  `cooperaciones` decimal(10,2) NOT NULL DEFAULT '0.00',
  `recargos` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `estado` enum('generado','enviado','pagado','cancelado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'generado',
  `recibo_entregado` tinyint(1) NOT NULL DEFAULT '0',
  `fecha_entrega` datetime DEFAULT NULL,
  `imagen_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pdf_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_recibo_folio` (`folio`),
  UNIQUE KEY `uq_recibo_medidor_periodo` (`medidor_id`,`periodo_id`),
  KEY `idx_recibos_usuario` (`usuario_id`),
  KEY `idx_recibos_periodo` (`periodo_id`),
  KEY `fk_recibos_domicilio` (`domicilio_id`),
  KEY `fk_recibos_lectura` (`lectura_id`),
  CONSTRAINT `fk_recibos_domicilio` FOREIGN KEY (`domicilio_id`) REFERENCES `domicilios` (`id`),
  CONSTRAINT `fk_recibos_lectura` FOREIGN KEY (`lectura_id`) REFERENCES `lecturas` (`id`),
  CONSTRAINT `fk_recibos_medidor` FOREIGN KEY (`medidor_id`) REFERENCES `medidores` (`id`),
  CONSTRAINT `fk_recibos_periodo` FOREIGN KEY (`periodo_id`) REFERENCES `periodos_bimestrales` (`id`),
  CONSTRAINT `fk_recibos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_servicio` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rutas`
--

DROP TABLE IF EXISTS `rutas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rutas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `comunidad_id` int unsigned NOT NULL,
  `codigo` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ruta_codigo` (`codigo`),
  KEY `idx_ruta_comunidad` (`comunidad_id`),
  CONSTRAINT `fk_rutas_comunidad` FOREIGN KEY (`comunidad_id`) REFERENCES `comunidades` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=674 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `staging_padron_excel`
--

DROP TABLE IF EXISTS `staging_padron_excel`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staging_padron_excel` (
  `id_excel` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `medidor` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ruta` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lect_sep24` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lect_nov24` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lect_ene25` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lect_mar25` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lect_may25` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lect_jul25` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lect_sep25` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lect_nov25` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lect_ene26` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lect_mar26` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `calle` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `colonia` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitud` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `longitud` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `telegram_envios`
--

DROP TABLE IF EXISTS `telegram_envios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `telegram_envios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `recibo_id` bigint unsigned NOT NULL,
  `chat_id` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` enum('pendiente','enviado','error') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `telegram_message_id` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_mensaje` text COLLATE utf8mb4_unicode_ci,
  `enviado_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_telegram_estado` (`estado`),
  KEY `fk_telegram_recibo` (`recibo_id`),
  CONSTRAINT `fk_telegram_recibo` FOREIGN KEY (`recibo_id`) REFERENCES `recibos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usuarios_servicio`
--

DROP TABLE IF EXISTS `usuarios_servicio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios_servicio` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `padron_id` bigint unsigned DEFAULT NULL,
  `nombre` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefono` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `whatsapp` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ruta_id` bigint unsigned DEFAULT NULL,
  `telegram_chat_id` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuario_padron_id` (`padron_id`),
  KEY `idx_usuario_nombre` (`nombre`),
  KEY `idx_usuario_ruta` (`ruta_id`),
  CONSTRAINT `fk_usuarios_servicio_ruta` FOREIGN KEY (`ruta_id`) REFERENCES `rutas` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=684 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usuarios_sistema`
--

DROP TABLE IF EXISTS `usuarios_sistema`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios_sistema` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `usuario` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefono` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correo` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ultimo_login_at` datetime DEFAULT NULL,
  `ultimo_login_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ultimo_acceso_at` datetime DEFAULT NULL,
  `ultimo_acceso_modulo` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ultimo_password_change_at` datetime DEFAULT NULL,
  `rol` enum('admin','capturista','cobrador','verificador','solo_lectura') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cobrador',
  `telegram_user_id` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_usuarios_sistema_usuario` (`usuario`),
  UNIQUE KEY `uk_usuarios_sistema_correo` (`correo`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary view structure for view `vw_recibos_pendientes_telegram`
--

DROP TABLE IF EXISTS `vw_recibos_pendientes_telegram`;
/*!50001 DROP VIEW IF EXISTS `vw_recibos_pendientes_telegram`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vw_recibos_pendientes_telegram` AS SELECT 
 1 AS `recibo_id`,
 1 AS `folio`,
 1 AS `usuario`,
 1 AS `telegram_chat_id`,
 1 AS `ruta`,
 1 AS `comunidad`,
 1 AS `periodo`,
 1 AS `total`,
 1 AS `imagen_path`*/;
SET character_set_client = @saved_cs_client;


