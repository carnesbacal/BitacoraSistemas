-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: carnes_bacal
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `anuncios`
--

DROP TABLE IF EXISTS `anuncios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `anuncios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(200) NOT NULL,
  `contenido` text NOT NULL,
  `tipo` enum('info','aviso','urgente','exito') NOT NULL DEFAULT 'info',
  `icono` varchar(50) DEFAULT 'megaphone',
  `sucursal_id` int(11) DEFAULT NULL,
  `rol_id` int(11) DEFAULT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL COMMENT 'NULL = sin fecha límite',
  `fijado` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = se fija arriba',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_por_id` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_activo_vigencia` (`activo`,`fecha_inicio`,`fecha_fin`),
  KEY `idx_sucursal` (`sucursal_id`),
  KEY `idx_rol` (`rol_id`),
  KEY `fk_anun_creador` (`creado_por_id`),
  CONSTRAINT `fk_anun_creador` FOREIGN KEY (`creado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_anun_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_anun_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `anuncios`
--

LOCK TABLES `anuncios` WRITE;
/*!40000 ALTER TABLE `anuncios` DISABLE KEYS */;
/*!40000 ALTER TABLE `anuncios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `anuncios_lecturas`
--

DROP TABLE IF EXISTS `anuncios_lecturas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `anuncios_lecturas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `anuncio_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `leido_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_anuncio_usuario` (`anuncio_id`,`usuario_id`),
  KEY `idx_usuario` (`usuario_id`),
  CONSTRAINT `fk_lect_anuncio` FOREIGN KEY (`anuncio_id`) REFERENCES `anuncios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lect_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `anuncios_lecturas`
--

LOCK TABLES `anuncios_lecturas` WRITE;
/*!40000 ALTER TABLE `anuncios_lecturas` DISABLE KEYS */;
/*!40000 ALTER TABLE `anuncios_lecturas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `areas`
--

DROP TABLE IF EXISTS `areas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `areas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `color` varchar(20) DEFAULT '#6B7280',
  `icono` varchar(50) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `areas`
--

LOCK TABLES `areas` WRITE;
/*!40000 ALTER TABLE `areas` DISABLE KEYS */;
INSERT INTO `areas` VALUES (1,'Cajas',NULL,'#D97706',NULL,1,'2026-05-20 13:52:18','2026-05-20 13:52:18'),(2,'Contabilidad',NULL,'#DC2626',NULL,1,'2026-05-20 13:52:18','2026-05-20 13:52:18'),(3,'Gerencia',NULL,'#2563EB',NULL,1,'2026-05-20 13:52:18','2026-05-20 13:52:18'),(4,'Auditoría',NULL,'#7C3AED',NULL,1,'2026-05-20 13:52:18','2026-05-20 13:52:18'),(5,'Almacén',NULL,'#9333EA',NULL,1,'2026-05-20 13:52:18','2026-05-20 13:52:18'),(6,'Pedidos',NULL,'#EA580C',NULL,1,'2026-05-20 13:52:18','2026-05-20 13:52:18'),(7,'Seguridad e Higiene',NULL,'#16A34A',NULL,1,'2026-05-20 13:52:18','2026-05-20 13:52:18'),(8,'Diseño',NULL,'#22C55E',NULL,1,'2026-05-20 13:52:18','2026-05-20 13:52:18'),(9,'RH',NULL,'#6B7280',NULL,1,'2026-05-20 13:52:18','2026-05-20 13:52:18'),(10,'Reparto',NULL,'#EA580C',NULL,1,'2026-05-20 13:52:18','2026-05-20 13:52:18'),(11,'Carnicería',NULL,'#2563EB',NULL,1,'2026-05-20 13:52:18','2026-05-20 13:52:18'),(12,'Cuarto Frío',NULL,'#D97706',NULL,1,'2026-05-20 13:52:18','2026-05-20 13:52:18'),(13,'Mantenimiento',NULL,'#6B7280',NULL,1,'2026-05-20 13:52:18','2026-05-20 13:52:18'),(14,'Proyectos Especiales',NULL,'#7C3AED',NULL,1,'2026-05-20 13:52:18','2026-05-20 13:52:18'),(15,'Oficina',NULL,'#6B7280',NULL,1,'2026-05-20 13:52:18','2026-05-20 13:52:18'),(16,'Cocina',NULL,'#16A34A',NULL,1,'2026-05-20 13:52:18','2026-05-20 13:52:18'),(17,'Guardias',NULL,'#9333EA',NULL,1,'2026-05-20 13:52:18','2026-05-20 13:52:18'),(18,'Taller',NULL,'#DC2626',NULL,1,'2026-05-20 13:52:18','2026-05-20 13:52:18'),(19,'Sistemas','Área de Sistemas y Soporte técnico del Grano de Oro','#10B981',NULL,1,'2026-05-21 14:06:35','2026-05-21 14:06:35');
/*!40000 ALTER TABLE `areas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `auditoria_sistema`
--

DROP TABLE IF EXISTS `auditoria_sistema`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auditoria_sistema` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `accion` varchar(100) NOT NULL,
  `entidad` varchar(50) DEFAULT NULL,
  `entidad_id` int(11) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `creado_en` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_accion` (`accion`),
  KEY `idx_fecha` (`creado_en`),
  CONSTRAINT `auditoria_sistema_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=122 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auditoria_sistema`
--

LOCK TABLES `auditoria_sistema` WRITE;
/*!40000 ALTER TABLE `auditoria_sistema` DISABLE KEYS */;
INSERT INTO `auditoria_sistema` VALUES (1,1,'logout',NULL,NULL,'Cierre de sesión','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-25 16:39:24'),(2,1,'login',NULL,NULL,'Inicio de sesión exitoso','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-25 16:39:32'),(3,1,'crear_planta','sucursal_plantas',1,'Planta: Tienda','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-25 16:40:44'),(4,1,'crear_planta','sucursal_plantas',2,'Planta: Oficinas','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-25 16:41:04'),(5,1,'crear_planta','sucursal_plantas',3,'Planta: 3er Piso','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-25 16:41:09'),(6,1,'generar_backup',NULL,NULL,'Backup manual generado: backup_2026-05-25_164152.sql.gz (12.9 KB, método: mysqldump)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-25 16:41:52'),(7,1,'crear_usuario','usuarios',12,'Usuario lfrodriguez creado','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-25 16:43:16'),(8,1,'crear_usuario','usuarios',13,'Usuario aegarcia creado','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-25 16:44:32'),(9,1,'crear_usuario','usuarios',14,'Usuario jacruz creado','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-25 16:46:38'),(10,1,'logout',NULL,NULL,'Cierre de sesión','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-26 11:39:50'),(11,1,'login',NULL,NULL,'Inicio de sesión exitoso','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-26 11:39:55'),(12,1,'login',NULL,NULL,'Inicio de sesión exitoso','192.168.1.54','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-26 11:40:16'),(13,1,'logout',NULL,NULL,'Cierre de sesión','192.168.1.54','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-26 11:41:00'),(14,1,'login',NULL,NULL,'Inicio de sesión exitoso','192.168.1.54','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-26 11:41:19'),(15,1,'logout',NULL,NULL,'Cierre de sesión','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-26 12:06:41'),(16,1,'login',NULL,NULL,'Inicio de sesión exitoso','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-26 12:15:44'),(17,1,'login',NULL,NULL,'Inicio de sesión exitoso','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-26 13:37:43'),(18,1,'login',NULL,NULL,'Inicio de sesión exitoso','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-27 08:58:02'),(19,1,'crear_regla','reglas_asignacion',2,'Regla Urgencia','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-27 08:59:29'),(20,1,'login',NULL,NULL,'Inicio de sesión exitoso','192.168.1.20','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-27 09:08:12'),(21,1,'generar_backup',NULL,NULL,'Backup manual generado: backup_2026-05-27_092300.sql.gz (16.6 KB, método: mysqldump)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-27 09:23:01'),(22,1,'descargar_backup','backups_realizados',2,'Descargó backup backup_2026-05-27_092300.sql.gz','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-27 09:23:04'),(23,1,'logout',NULL,NULL,'Cierre de sesión','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-27 09:27:33'),(24,12,'login',NULL,NULL,'Inicio de sesión exitoso','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-27 09:28:03'),(25,12,'cambio_password','usuarios',12,'Cambio de contraseña','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-27 09:28:16'),(26,12,'logout',NULL,NULL,'Cierre de sesión','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-27 09:28:28'),(27,1,'login',NULL,NULL,'Inicio de sesión exitoso','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-27 09:36:57'),(28,1,'logout',NULL,NULL,'Cierre de sesión','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-27 09:46:28'),(29,1,'login',NULL,NULL,'Inicio de sesión exitoso','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-27 09:46:51'),(30,1,'logout',NULL,NULL,'Cierre de sesión','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-27 10:14:41'),(31,12,'login',NULL,NULL,'Inicio de sesión exitoso','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-27 10:20:44'),(32,12,'subir_plano','sucursal_plantas',1,'Plano: uploads/planos/plano_p1_1779914597.png','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-27 13:43:17'),(33,12,'subir_plano','sucursal_plantas',2,'Plano: uploads/planos/plano_p2_1779914615.png','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-27 13:43:35'),(34,12,'subir_plano','sucursal_plantas',3,'Plano: uploads/planos/plano_p3_1779914625.png','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-27 13:43:45'),(35,12,'logout',NULL,NULL,'Cierre de sesión','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-27 14:04:55'),(36,1,'login',NULL,NULL,'Inicio de sesión exitoso','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-27 14:05:18'),(37,1,'login',NULL,NULL,'Inicio de sesión exitoso','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-27 17:00:14'),(38,1,'crear_proveedor','proveedores',5,'Proveedor Uni-Red','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-27 17:19:04'),(39,1,'crear_proveedor','proveedores',6,'Proveedor Telcel','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-27 17:25:51'),(40,1,'crear_proveedor','proveedores',7,'Proveedor Odoo','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-27 17:30:04'),(41,1,'editar_proveedor','proveedores',4,'Proveedor Sipcons','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-27 17:35:50'),(42,1,'editar_proveedor','proveedores',4,'Proveedor Sipcons','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-27 17:38:33'),(43,1,'crear_incidencia','incidencias',35,'Folio INC-BAC-2026-0034','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-27 17:51:58'),(44,1,'login',NULL,NULL,'Inicio de sesión exitoso','100.85.54.82','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-28 11:01:37'),(45,1,'crear_usuario','usuarios',15,'Usuario jlcorral creado','100.85.54.82','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-28 11:03:00'),(46,1,'crear_usuario','usuarios',16,'Usuario ovazquez creado','100.85.54.82','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-28 11:04:06'),(47,1,'logout',NULL,NULL,'Cierre de sesión','100.85.54.82','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-28 11:04:18'),(48,15,'login',NULL,NULL,'Inicio de sesión exitoso','100.85.54.82','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-28 11:04:41'),(49,15,'cambio_password','usuarios',15,'Cambio de contraseña','100.85.54.82','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-28 11:05:02'),(50,15,'logout',NULL,NULL,'Cierre de sesión','100.85.54.82','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-28 11:05:24'),(51,13,'login',NULL,NULL,'Inicio de sesión exitoso','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-28 12:07:34'),(52,13,'cambio_password','usuarios',13,'Cambio de contraseña','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-28 12:07:55'),(53,1,'editar_perfil','usuarios',1,'Editó su perfil','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-28 12:21:53'),(54,1,'logout',NULL,NULL,'Cierre de sesión','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-28 12:26:53'),(55,12,'login',NULL,NULL,'Inicio de sesión exitoso','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-28 15:41:00'),(56,12,'crear_incidencia','incidencias',36,'Folio INC-BAC-2026-0035','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-28 15:46:49'),(57,12,'generar_backup',NULL,NULL,'Backup manual generado: backup_2026-05-28_155202.sql.gz (23.1 KB, método: mysqldump)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-28 15:52:04'),(58,12,'descargar_backup','backups_realizados',3,'Descargó backup backup_2026-05-28_155202.sql.gz','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-28 15:52:06'),(59,12,'descargar_backup','backups_realizados',3,'Descargó backup backup_2026-05-28_155202.sql.gz','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-28 15:52:53'),(60,12,'logout',NULL,NULL,'Cierre de sesión','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-28 16:21:36'),(61,1,'crear_incidencia','incidencias',37,'Folio INC-BAC-2026-0036','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-28 18:07:44'),(62,1,'logout',NULL,NULL,'Cierre de sesión','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-28 18:15:50'),(63,12,'login',NULL,NULL,'Inicio de sesión exitoso','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-28 18:16:00'),(64,12,'login',NULL,NULL,'Inicio de sesión exitoso','192.168.1.136','Mozilla/5.0 (iPhone; CPU iPhone OS 26_4_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/148.0.7778.166 Mobile/15E148 Safari/604.1','2026-05-28 18:17:23'),(65,12,'logout',NULL,NULL,'Cierre de sesión','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-29 11:50:46'),(66,1,'login',NULL,NULL,'Inicio de sesión exitoso','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-29 11:51:14'),(67,1,'logout',NULL,NULL,'Cierre de sesión','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-29 12:59:04'),(68,13,'login',NULL,NULL,'Inicio de sesión exitoso','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-29 14:38:53'),(69,13,'logout',NULL,NULL,'Cierre de sesión','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-29 14:44:54'),(70,1,'login',NULL,NULL,'Inicio de sesión exitoso','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-29 14:45:29'),(71,1,'crear_equipo','equipos',1,'Equipo BAC01','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-29 16:12:12'),(72,1,'crear_equipo','equipos',2,'Equipo BAC02','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 12:40:46'),(73,1,'crear_equipo','equipos',3,'Equipo BACA03','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 12:42:38'),(74,1,'crear_equipo','equipos',4,'Equipo BAC04','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 12:50:50'),(75,1,'editar_equipo','equipos',3,'Equipo BAC03','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 12:51:22'),(76,1,'crear_equipo','equipos',5,'Equipo BAC05','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 12:55:27'),(77,1,'crear_equipo','equipos',6,'Equipo BAC06','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 12:57:58'),(78,1,'crear_equipo','equipos',7,'Equipo BAC07','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 13:00:38'),(79,1,'editar_equipo','equipos',6,'Equipo BAC06','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 13:02:34'),(80,1,'crear_equipo','equipos',8,'Equipo BAC08','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 13:11:49'),(81,1,'crear_equipo','equipos',9,'Equipo BAC09','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 13:13:05'),(82,1,'crear_equipo','equipos',10,'Equipo BAC10','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 13:36:20'),(83,1,'crear_equipo','equipos',11,'Equipo BAC11','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 13:38:14'),(84,1,'crear_equipo','equipos',12,'Equipo BAC12','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 13:39:40'),(85,1,'crear_equipo','equipos',13,'Equipo BAC13','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 13:54:40'),(86,1,'crear_equipo','equipos',14,'Equipo BAC14','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 14:32:05'),(87,1,'crear_equipo','equipos',15,'Equipo BAC15','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 14:36:47'),(88,1,'crear_equipo','equipos',16,'Equipo BAC16','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 14:40:07'),(89,1,'crear_equipo','equipos',17,'Equipo BAC17','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 14:52:00'),(90,1,'crear_equipo','equipos',18,'Equipo BAC18','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 14:53:52'),(91,1,'crear_equipo','equipos',19,'Equipo BAC20','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 15:11:47'),(92,1,'crear_equipo','equipos',20,'Equipo BAC21','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 17:06:23'),(93,1,'crear_equipo','equipos',21,'Equipo BAC22','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 17:07:21'),(94,1,'crear_equipo','equipos',22,'Equipo BAC 23','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 17:08:38'),(95,1,'crear_equipo','equipos',23,'Equipo BAC 24','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 17:10:07'),(96,1,'editar_equipo','equipos',22,'Equipo BAC23','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 17:10:20'),(97,1,'editar_equipo','equipos',23,'Equipo BAC24','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 17:10:26'),(98,1,'crear_equipo','equipos',24,'Equipo BAC25','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 17:11:36'),(99,1,'crear_equipo','equipos',25,'Equipo BAC26','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 17:12:19'),(100,1,'crear_equipo','equipos',26,'Equipo BAC27','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 17:31:25'),(101,1,'crear_equipo','equipos',27,'Equipo BAC28','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 17:33:23'),(102,1,'crear_equipo','equipos',28,'Equipo BAC30','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 17:34:20'),(103,1,'editar_equipo','equipos',28,'Equipo BAC29','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 17:34:31'),(104,1,'crear_equipo','equipos',29,'Equipo BAC30','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 17:35:09'),(105,1,'crear_equipo','equipos',30,'Equipo BAC31','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 17:38:18'),(106,1,'crear_equipo','equipos',31,'Equipo BAC32','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 17:39:10'),(107,1,'crear_equipo','equipos',32,'Equipo BAC33','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 17:40:07'),(108,1,'crear_incidencia','incidencias',38,'Folio INC-BAC-2026-0037','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 17:45:24'),(109,1,'crear_equipo','equipos',33,'Equipo BAC34','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 17:50:42'),(110,1,'crear_equipo','equipos',34,'Equipo BAC35','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 17:51:17'),(111,1,'crear_incidencia','incidencias',39,'Folio INC-BAC-2026-0038','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-30 17:55:22'),(112,1,'crear_incidencia','incidencias',40,'Folio INC-BAC-2026-0039','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-31 06:38:52'),(113,1,'crear_equipo','equipos',35,'Equipo BAC36','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-31 06:41:23'),(114,1,'crear_equipo','equipos',36,'Equipo BAC37','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-31 06:42:26'),(115,1,'editar_equipo','equipos',21,'Equipo BAC22','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-31 06:42:40'),(116,1,'login',NULL,NULL,'Inicio de sesión exitoso','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-31 07:44:52'),(117,1,'logout',NULL,NULL,'Cierre de sesión','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-31 10:03:06'),(118,1,'logout',NULL,NULL,'Cierre de sesión','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-31 10:03:51'),(119,1,'login',NULL,NULL,'Inicio de sesión exitoso','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-31 10:31:44'),(120,1,'login',NULL,NULL,'Inicio de sesión exitoso','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-31 12:30:46'),(121,1,'eliminar_backup','backups_realizados',1,'Eliminó backup','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-31 13:05:56');
/*!40000 ALTER TABLE `auditoria_sistema` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `backups_realizados`
--

DROP TABLE IF EXISTS `backups_realizados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backups_realizados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_archivo` varchar(255) NOT NULL,
  `tamano_bytes` bigint(20) NOT NULL DEFAULT 0,
  `tipo` enum('manual','automatico') NOT NULL DEFAULT 'manual',
  `realizado_por_id` int(11) DEFAULT NULL COMMENT 'Null si fue automatico',
  `notas` varchar(255) DEFAULT NULL,
  `exitoso` tinyint(1) NOT NULL DEFAULT 1,
  `mensaje_error` text DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_creado` (`creado_en`),
  KEY `fk_backup_usuario` (`realizado_por_id`),
  CONSTRAINT `fk_backup_usuario` FOREIGN KEY (`realizado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `backups_realizados`
--

LOCK TABLES `backups_realizados` WRITE;
/*!40000 ALTER TABLE `backups_realizados` DISABLE KEYS */;
INSERT INTO `backups_realizados` VALUES (2,'backup_2026-05-27_092300.sql.gz',16995,'manual',1,NULL,1,NULL,'2026-05-27 16:23:01'),(3,'backup_2026-05-28_155202.sql.gz',23624,'manual',12,NULL,1,NULL,'2026-05-28 22:52:04');
/*!40000 ALTER TABLE `backups_realizados` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categorias`
--

DROP TABLE IF EXISTS `categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `color` varchar(20) DEFAULT '#6B7280',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categorias`
--

LOCK TABLES `categorias` WRITE;
/*!40000 ALTER TABLE `categorias` DISABLE KEYS */;
INSERT INTO `categorias` VALUES (1,'Hardware',NULL,'#DC2626',1,'2026-05-20 13:52:18'),(2,'Software',NULL,'#2563EB',1,'2026-05-20 13:52:18'),(3,'Red e Internet',NULL,'#16A34A',1,'2026-05-20 13:52:18'),(4,'Telefonía',NULL,'#7C3AED',1,'2026-05-20 13:52:18'),(5,'Seguridad',NULL,'#EA580C',1,'2026-05-20 13:52:18'),(6,'Punto de Venta',NULL,'#D97706',1,'2026-05-20 13:52:18'),(7,'Cámaras CCTV',NULL,'#9333EA',1,'2026-05-20 13:52:18'),(8,'Alarmas',NULL,'#DC2626',1,'2026-05-20 13:52:18'),(9,'Impresión',NULL,'#6B7280',1,'2026-05-20 13:52:18'),(10,'Soporte a usuario',NULL,'#22C55E',1,'2026-05-20 13:52:18'),(11,'Mantenimiento',NULL,'#0EA5E9',1,'2026-05-20 13:52:18'),(12,'Otro',NULL,'#6B7280',1,'2026-05-20 13:52:18');
/*!40000 ALTER TABLE `categorias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categorias_palabras_clave`
--

DROP TABLE IF EXISTS `categorias_palabras_clave`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categorias_palabras_clave` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `categoria_id` int(11) NOT NULL,
  `palabra` varchar(60) NOT NULL COMMENT 'Palabra o frase clave (lowercase, sin acentos)',
  `peso` int(11) NOT NULL DEFAULT 1 COMMENT 'Mayor peso = más específica',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cat_palabra` (`categoria_id`,`palabra`),
  KEY `idx_palabra` (`palabra`),
  CONSTRAINT `fk_kw_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=89 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categorias_palabras_clave`
--

LOCK TABLES `categorias_palabras_clave` WRITE;
/*!40000 ALTER TABLE `categorias_palabras_clave` DISABLE KEYS */;
INSERT INTO `categorias_palabras_clave` VALUES (1,1,'computadora',2,'2026-05-24 20:30:05'),(2,1,'compu',1,'2026-05-24 20:30:05'),(3,1,'pc',1,'2026-05-24 20:30:05'),(4,1,'laptop',2,'2026-05-24 20:30:05'),(5,1,'monitor',2,'2026-05-24 20:30:05'),(6,1,'pantalla',1,'2026-05-24 20:30:05'),(7,1,'teclado',2,'2026-05-24 20:30:05'),(8,1,'mouse',2,'2026-05-24 20:30:05'),(9,1,'raton',1,'2026-05-24 20:30:05'),(10,1,'cable',1,'2026-05-24 20:30:05'),(11,1,'cargador',2,'2026-05-24 20:30:05'),(12,1,'fuente',1,'2026-05-24 20:30:05'),(13,1,'disco duro',2,'2026-05-24 20:30:05'),(14,1,'memoria',1,'2026-05-24 20:30:05'),(15,1,'puerto usb',2,'2026-05-24 20:30:05'),(16,1,'no enciende',2,'2026-05-24 20:30:05'),(17,1,'no prende',2,'2026-05-24 20:30:05'),(32,9,'impresora',3,'2026-05-24 20:30:05'),(33,9,'imprimir',2,'2026-05-24 20:30:05'),(34,9,'imprime',2,'2026-05-24 20:30:05'),(35,9,'ticket',1,'2026-05-24 20:30:05'),(36,9,'tickets',1,'2026-05-24 20:30:05'),(37,9,'toner',3,'2026-05-24 20:30:05'),(38,9,'tinta',2,'2026-05-24 20:30:05'),(39,9,'cartucho',3,'2026-05-24 20:30:05'),(40,9,'papel',1,'2026-05-24 20:30:05'),(41,9,'atasco',2,'2026-05-24 20:30:05'),(47,6,'pos',3,'2026-05-24 20:30:05'),(48,6,'caja',2,'2026-05-24 20:30:05'),(49,6,'cobro',2,'2026-05-24 20:30:05'),(50,6,'venta',1,'2026-05-24 20:30:05'),(51,6,'cobrar',2,'2026-05-24 20:30:05'),(52,6,'terminal',1,'2026-05-24 20:30:05'),(53,6,'lector',1,'2026-05-24 20:30:05'),(54,6,'codigo de barras',3,'2026-05-24 20:30:05'),(55,6,'scanner',2,'2026-05-24 20:30:05'),(56,6,'sistema de cobro',3,'2026-05-24 20:30:05'),(62,3,'internet',3,'2026-05-24 20:30:05'),(63,3,'red',2,'2026-05-24 20:30:05'),(64,3,'wifi',3,'2026-05-24 20:30:05'),(65,3,'wi-fi',3,'2026-05-24 20:30:05'),(66,3,'router',3,'2026-05-24 20:30:05'),(67,3,'modem',3,'2026-05-24 20:30:05'),(68,3,'cable de red',3,'2026-05-24 20:30:05'),(69,3,'ethernet',2,'2026-05-24 20:30:05'),(70,3,'sin conexion',3,'2026-05-24 20:30:05'),(71,3,'sin internet',3,'2026-05-24 20:30:05'),(72,3,'lento',1,'2026-05-24 20:30:05'),(73,3,'no conecta',2,'2026-05-24 20:30:05'),(77,2,'software',3,'2026-05-24 20:30:05'),(78,2,'sistema',2,'2026-05-24 20:30:05'),(79,2,'aplicacion',2,'2026-05-24 20:30:05'),(80,2,'programa',2,'2026-05-24 20:30:05'),(81,2,'app',1,'2026-05-24 20:30:05'),(82,2,'error',1,'2026-05-24 20:30:05'),(83,2,'congelado',2,'2026-05-24 20:30:05'),(84,2,'pantalla azul',3,'2026-05-24 20:30:05'),(85,2,'cierra solo',3,'2026-05-24 20:30:05'),(86,2,'no abre',2,'2026-05-24 20:30:05'),(87,2,'actualizacion',2,'2026-05-24 20:30:05'),(88,2,'update',1,'2026-05-24 20:30:05');
/*!40000 ALTER TABLE `categorias_palabras_clave` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comentario_reacciones`
--

DROP TABLE IF EXISTS `comentario_reacciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comentario_reacciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `comentario_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `emoji` varchar(10) NOT NULL COMMENT 'Emoji unicode',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_react` (`comentario_id`,`usuario_id`,`emoji`),
  KEY `idx_comentario` (`comentario_id`),
  KEY `fk_react_usuario` (`usuario_id`),
  CONSTRAINT `fk_react_comentario` FOREIGN KEY (`comentario_id`) REFERENCES `incidencias_comentarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_react_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comentario_reacciones`
--

LOCK TABLES `comentario_reacciones` WRITE;
/*!40000 ALTER TABLE `comentario_reacciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `comentario_reacciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `equipo_fotos`
--

DROP TABLE IF EXISTS `equipo_fotos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `equipo_fotos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipo_id` int(11) NOT NULL,
  `ruta` varchar(255) NOT NULL COMMENT 'Ruta relativa (assets/equipos/...)',
  `descripcion` varchar(255) DEFAULT NULL,
  `es_portada` tinyint(1) NOT NULL DEFAULT 0,
  `subido_por_id` int(11) DEFAULT NULL,
  `tamano_bytes` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_equipo` (`equipo_id`),
  KEY `fk_foto_usuario` (`subido_por_id`),
  CONSTRAINT `fk_foto_equipo` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_foto_usuario` FOREIGN KEY (`subido_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipo_fotos`
--

LOCK TABLES `equipo_fotos` WRITE;
/*!40000 ALTER TABLE `equipo_fotos` DISABLE KEYS */;
/*!40000 ALTER TABLE `equipo_fotos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `equipo_transferencias`
--

DROP TABLE IF EXISTS `equipo_transferencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `equipo_transferencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipo_id` int(11) NOT NULL,
  `sucursal_origen_id` int(11) DEFAULT NULL COMMENT 'Null si era equipo nuevo recien llegado',
  `sucursal_destino_id` int(11) NOT NULL,
  `area_origen_id` int(11) DEFAULT NULL,
  `area_destino_id` int(11) DEFAULT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `fecha_transferencia` date NOT NULL,
  `realizado_por_id` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_equipo` (`equipo_id`),
  KEY `idx_fecha` (`fecha_transferencia`),
  KEY `fk_trans_origen` (`sucursal_origen_id`),
  KEY `fk_trans_destino` (`sucursal_destino_id`),
  KEY `fk_trans_area_origen` (`area_origen_id`),
  KEY `fk_trans_area_destino` (`area_destino_id`),
  KEY `fk_trans_usuario` (`realizado_por_id`),
  CONSTRAINT `fk_trans_area_destino` FOREIGN KEY (`area_destino_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_trans_area_origen` FOREIGN KEY (`area_origen_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_trans_destino` FOREIGN KEY (`sucursal_destino_id`) REFERENCES `sucursales` (`id`),
  CONSTRAINT `fk_trans_equipo` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_trans_origen` FOREIGN KEY (`sucursal_origen_id`) REFERENCES `sucursales` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_trans_usuario` FOREIGN KEY (`realizado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipo_transferencias`
--

LOCK TABLES `equipo_transferencias` WRITE;
/*!40000 ALTER TABLE `equipo_transferencias` DISABLE KEYS */;
/*!40000 ALTER TABLE `equipo_transferencias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `equipos`
--

DROP TABLE IF EXISTS `equipos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `equipos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_inventario` varchar(50) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `tipo` varchar(50) DEFAULT NULL,
  `marca` varchar(100) DEFAULT NULL,
  `modelo` varchar(100) DEFAULT NULL,
  `numero_serie` varchar(100) DEFAULT NULL,
  `sucursal_id` int(11) NOT NULL,
  `planta_id` int(11) DEFAULT NULL,
  `area_id` int(11) DEFAULT NULL,
  `proveedor_id` int(11) DEFAULT NULL,
  `fecha_compra` date DEFAULT NULL,
  `costo_compra` decimal(12,2) DEFAULT NULL,
  `vida_util_meses` int(11) DEFAULT NULL COMMENT 'Vida util estimada en meses (60 = 5 años)',
  `fecha_baja` date DEFAULT NULL,
  `motivo_baja` varchar(255) DEFAULT NULL,
  `ubicacion` varchar(255) DEFAULT NULL,
  `responsable_id` int(11) DEFAULT NULL,
  `fecha_adquisicion` date DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `estado_vida` enum('nuevo','en_uso','en_reparacion','dado_de_baja') NOT NULL DEFAULT 'en_uso',
  `creado_en` datetime DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `pos_x` decimal(5,2) DEFAULT NULL COMMENT '% desde el borde izquierdo',
  `pos_y` decimal(5,2) DEFAULT NULL COMMENT '% desde el borde superior',
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_inventario` (`codigo_inventario`),
  KEY `area_id` (`area_id`),
  KEY `responsable_id` (`responsable_id`),
  KEY `idx_sucursal_area` (`sucursal_id`,`area_id`),
  KEY `idx_tipo` (`tipo`),
  KEY `fk_equipo_proveedor` (`proveedor_id`),
  KEY `idx_pos` (`pos_x`,`pos_y`),
  KEY `fk_equipo_planta` (`planta_id`),
  CONSTRAINT `equipos_ibfk_1` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`),
  CONSTRAINT `equipos_ibfk_2` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `equipos_ibfk_3` FOREIGN KEY (`responsable_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_equipo_planta` FOREIGN KEY (`planta_id`) REFERENCES `sucursal_plantas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_equipo_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipos`
--

LOCK TABLES `equipos` WRITE;
/*!40000 ALTER TABLE `equipos` DISABLE KEYS */;
INSERT INTO `equipos` VALUES (1,'BAC01','PC ROSA AVALOS','PC',NULL,NULL,NULL,1,NULL,2,NULL,NULL,NULL,48,NULL,NULL,NULL,NULL,NULL,NULL,1,'en_uso','2026-05-29 16:12:12','2026-05-29 16:12:12',NULL,NULL),(2,'BAC02','PC DANIELA MEDINA','PC',NULL,NULL,NULL,1,NULL,2,NULL,NULL,NULL,48,NULL,NULL,NULL,NULL,NULL,NULL,1,'en_uso','2026-05-30 12:40:46','2026-05-30 12:40:46',NULL,NULL),(3,'BAC03','PC NADIA GUERRERO','PC',NULL,NULL,NULL,1,NULL,2,NULL,NULL,NULL,NULL,NULL,NULL,'OFICINA SEGUNDO PISO',NULL,NULL,NULL,1,'en_uso','2026-05-30 12:42:38','2026-05-30 12:51:22',NULL,NULL),(4,'BAC04','PC RECEPCION','PC','DELL',NULL,NULL,1,NULL,2,NULL,NULL,NULL,36,NULL,NULL,'OFICINA SEGUNDO PISO',NULL,NULL,NULL,1,'en_uso','2026-05-30 12:50:50','2026-05-30 12:50:50',NULL,NULL),(5,'BAC05','PC LUIS RODRIGUEZ','PC','DELL',NULL,NULL,1,NULL,19,NULL,NULL,NULL,24,NULL,NULL,'OFICINA SEGUNDO PISO',NULL,NULL,NULL,1,'en_uso','2026-05-30 12:55:27','2026-05-30 12:55:27',NULL,NULL),(6,'BAC06','PC ABRAHAM GARCIA','PC','LENOVO','THINKCENTRE',NULL,1,NULL,19,NULL,NULL,NULL,NULL,NULL,NULL,'OFICINA SEGUNDO PISO',NULL,NULL,NULL,1,'en_uso','2026-05-30 12:57:58','2026-05-30 13:02:34',NULL,NULL),(7,'BAC07','PC JORGE CRUZ','PC','DELL',NULL,NULL,1,NULL,19,NULL,NULL,NULL,NULL,NULL,NULL,'OFICINA SEGUNDO PISO',NULL,NULL,NULL,1,'en_uso','2026-05-30 13:00:38','2026-05-30 13:00:38',NULL,NULL),(8,'BAC08','PC JULIAN','PC',NULL,NULL,NULL,1,NULL,14,NULL,NULL,NULL,NULL,NULL,NULL,'OFICINA SEGUNDO PISO',NULL,NULL,NULL,1,'en_uso','2026-05-30 13:11:49','2026-05-30 13:11:49',NULL,NULL),(9,'BAC09','PC DANIEL MONTAÑO','PC','DELL',NULL,NULL,1,NULL,4,NULL,NULL,NULL,24,NULL,NULL,'OFICINA SEGUNDO PISO',NULL,NULL,NULL,1,'en_uso','2026-05-30 13:13:05','2026-05-30 13:13:05',NULL,NULL),(10,'BAC10','AUXILIAR DE AUDITORIA','PC',NULL,'THINKCENTRE',NULL,1,NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,'OFICINA SEGUNDO PISO',NULL,NULL,NULL,1,'en_uso','2026-05-30 13:36:20','2026-05-30 13:36:20',NULL,NULL),(11,'BAC11','PC PAOLA BARRERA','PC',NULL,NULL,NULL,1,NULL,8,NULL,NULL,NULL,48,NULL,NULL,'OFICINA SEGUNDO PISO',NULL,NULL,NULL,1,'en_uso','2026-05-30 13:38:14','2026-05-30 13:38:14',NULL,NULL),(12,'BAC12','PC AISLIN CAMPOS','PC','DELL',NULL,NULL,1,NULL,8,NULL,NULL,NULL,NULL,NULL,NULL,'OFICINA SEGUNDO PISO',NULL,NULL,NULL,1,'en_uso','2026-05-30 13:39:40','2026-05-30 13:39:40',NULL,NULL),(13,'BAC13','PC JANETH LOPEZ','PC','DELL',NULL,NULL,1,NULL,8,NULL,NULL,NULL,36,NULL,NULL,'OFICINA SEGUNDO PISO',NULL,NULL,NULL,1,'en_uso','2026-05-30 13:54:40','2026-05-30 13:54:40',NULL,NULL),(14,'BAC14','SALA DE JUNTAS','PC','DELL',NULL,NULL,1,NULL,15,NULL,NULL,NULL,36,NULL,NULL,'OFICINA SEGUNDO PISO',NULL,NULL,NULL,1,'en_uso','2026-05-30 14:32:05','2026-05-30 14:32:05',NULL,NULL),(15,'BAC15','PC BOVEDA','PC','DELL',NULL,NULL,1,NULL,15,NULL,NULL,NULL,24,NULL,NULL,'OFICINA SEGUNDO PISO',NULL,NULL,NULL,1,'en_uso','2026-05-30 14:36:47','2026-05-30 14:36:47',NULL,NULL),(16,'BAC16','PC GABRIEL HERRERA','PC','DELL',NULL,NULL,1,NULL,7,NULL,NULL,NULL,24,NULL,NULL,'OFICINA SEGURIDAD E HIGIENE',NULL,NULL,NULL,1,'en_uso','2026-05-30 14:40:07','2026-05-30 14:40:07',NULL,NULL),(17,'BAC17','PC BRIDGET DE ALBA','PC',NULL,NULL,NULL,1,NULL,7,NULL,NULL,NULL,36,NULL,NULL,'OFICINA SEGURIDAD E HIGIENE',NULL,NULL,NULL,1,'en_uso','2026-05-30 14:52:00','2026-05-30 14:52:00',NULL,NULL),(18,'BAC18','PC MARIA REYES','PC',NULL,NULL,NULL,1,NULL,9,NULL,NULL,NULL,36,NULL,NULL,'OFICINA DE RH',NULL,NULL,NULL,1,'en_uso','2026-05-30 14:53:52','2026-05-30 14:53:52',NULL,NULL),(19,'BAC20','PC JESSICA ALEGRIA','PC',NULL,NULL,NULL,1,NULL,9,NULL,NULL,NULL,36,NULL,NULL,'OFICINA DE RH',NULL,NULL,NULL,1,'en_uso','2026-05-30 15:11:47','2026-05-30 15:11:47',NULL,NULL),(20,'BAC21','PC JUAN LUIS','PC',NULL,NULL,NULL,1,NULL,3,NULL,NULL,NULL,36,NULL,NULL,'OFICINA GERENCIA/VENTAS',NULL,NULL,NULL,1,'en_uso','2026-05-30 17:06:23','2026-05-30 17:06:23',NULL,NULL),(21,'BAC22','PC ANA VENTAS','PC',NULL,NULL,NULL,1,NULL,15,NULL,NULL,NULL,NULL,NULL,NULL,'OFICINA GERENCIA/VENTAS',NULL,NULL,NULL,1,'en_uso','2026-05-30 17:07:21','2026-05-31 06:42:40',NULL,NULL),(22,'BAC23','PC BRENDA VENTA','PC','DELL',NULL,NULL,1,NULL,15,NULL,NULL,NULL,NULL,NULL,NULL,'OFICINA GERENCIA/VENTAS',NULL,NULL,NULL,1,'en_uso','2026-05-30 17:08:38','2026-05-30 17:10:20',NULL,NULL),(23,'BAC24','PC STEPHANIA VENTAS','PC','DELL',NULL,NULL,1,NULL,15,NULL,NULL,NULL,NULL,NULL,NULL,'OFICINA GERENCIA/VENTAS',NULL,NULL,NULL,1,'en_uso','2026-05-30 17:10:07','2026-05-30 17:10:26',NULL,NULL),(24,'BAC25','PC ALBERTO MARTINEZ','PC','DELL',NULL,NULL,1,NULL,3,NULL,NULL,NULL,24,NULL,NULL,'OFICINA GERENCIA/VENTAS',NULL,NULL,NULL,1,'en_uso','2026-05-30 17:11:36','2026-05-30 17:11:36',NULL,NULL),(25,'BAC26','PC MIGUEL GARCIA','PC','LENOVO',NULL,NULL,1,NULL,3,NULL,NULL,NULL,24,NULL,NULL,'OFICINA GERENCIA/VENTAS',NULL,NULL,NULL,1,'en_uso','2026-05-30 17:12:19','2026-05-30 17:12:19',NULL,NULL),(26,'BAC27','PC GUILLERMO SILVAS','PC',NULL,'THINKCENTRE',NULL,1,NULL,3,NULL,NULL,NULL,24,NULL,NULL,'OFICINA GERENCIA/VENTAS',NULL,NULL,NULL,1,'en_uso','2026-05-30 17:31:25','2026-05-30 17:31:25',NULL,NULL),(27,'BAC28','CAJA 1','PC','DELL',NULL,NULL,1,NULL,1,NULL,NULL,NULL,24,NULL,NULL,'PRIMER PISO',NULL,NULL,NULL,1,'en_uso','2026-05-30 17:33:23','2026-05-30 17:33:23',NULL,NULL),(28,'BAC29','CAJA 2','PC','DELL',NULL,NULL,1,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,'PRIMER PISO',NULL,NULL,NULL,1,'en_uso','2026-05-30 17:34:20','2026-05-30 17:34:31',NULL,NULL),(29,'BAC30','CAJA 3','PC','DELL',NULL,NULL,1,NULL,1,NULL,NULL,NULL,24,NULL,NULL,'PRIMER PISO',NULL,NULL,NULL,1,'en_uso','2026-05-30 17:35:09','2026-05-30 17:35:09',NULL,NULL),(30,'BAC31','CAJA 4','PC','DELL',NULL,NULL,1,NULL,1,NULL,NULL,NULL,24,NULL,NULL,'PRIMER PISO',NULL,NULL,NULL,1,'en_uso','2026-05-30 17:38:18','2026-05-30 17:38:18',NULL,NULL),(31,'BAC32','CAJA 5','PC','DELL',NULL,NULL,1,NULL,1,NULL,NULL,NULL,24,NULL,NULL,'PRIMER PISO',NULL,NULL,NULL,1,'en_uso','2026-05-30 17:39:10','2026-05-30 17:39:10',NULL,NULL),(32,'BAC33','CAJA 6','PC','DELL',NULL,NULL,1,NULL,1,NULL,NULL,NULL,24,NULL,NULL,'PRIMER PISO',NULL,NULL,NULL,1,'en_uso','2026-05-30 17:40:07','2026-05-30 17:40:07',NULL,NULL),(33,'BAC34','CAJA 7','PC','DELL',NULL,NULL,1,NULL,1,NULL,NULL,NULL,24,NULL,NULL,'PRIMER PISO',NULL,NULL,NULL,1,'en_uso','2026-05-30 17:50:42','2026-05-30 17:50:42',NULL,NULL),(34,'BAC35','CAJA 8','PC','DELL',NULL,NULL,1,NULL,1,NULL,NULL,NULL,24,NULL,NULL,'PRIMER PISO',NULL,NULL,NULL,1,'en_uso','2026-05-30 17:51:17','2026-05-30 17:51:17',NULL,NULL),(35,'BAC36','PC COCINA','PC','DELL',NULL,NULL,1,NULL,16,NULL,NULL,NULL,24,NULL,NULL,'PRIMER PISO',NULL,NULL,NULL,1,'en_uso','2026-05-31 06:41:23','2026-05-31 06:41:23',NULL,NULL),(36,'BAC37','CHECADOR DE PRECIOS','PC','DELL',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,12,NULL,NULL,'PRIMER PISO',NULL,NULL,NULL,1,'en_uso','2026-05-31 06:42:26','2026-05-31 06:42:26',NULL,NULL);
/*!40000 ALTER TABLE `equipos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estados`
--

DROP TABLE IF EXISTS `estados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `estados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `orden` int(11) NOT NULL,
  `color` varchar(20) NOT NULL DEFAULT '#6B7280',
  `es_inicial` tinyint(1) NOT NULL DEFAULT 0,
  `es_final` tinyint(1) NOT NULL DEFAULT 0,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estados`
--

LOCK TABLES `estados` WRITE;
/*!40000 ALTER TABLE `estados` DISABLE KEYS */;
INSERT INTO `estados` VALUES (1,'Abierta',1,'#DC2626',1,0,'Recién registrada, sin atender',1),(2,'Asignada',2,'#EA580C',0,0,'Asignada a un técnico',1),(3,'En proceso',3,'#D97706',0,0,'Siendo atendida activamente',1),(4,'En espera',4,'#6B7280',0,0,'Esperando información, partes o terceros',1),(5,'Resuelta',5,'#0EA5E9',0,0,'Solucionada, pendiente de confirmación',1),(6,'Completada',6,'#16A34A',0,1,'Confirmada y cerrada',1),(7,'Cancelada',7,'#6B7280',0,1,'Anulada sin resolución',1);
/*!40000 ALTER TABLE `estados` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `importaciones`
--

DROP TABLE IF EXISTS `importaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `importaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` enum('usuarios','equipos','incidencias') NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `total_filas` int(11) NOT NULL DEFAULT 0,
  `exitosos` int(11) NOT NULL DEFAULT 0,
  `fallidos` int(11) NOT NULL DEFAULT 0,
  `errores_json` text DEFAULT NULL COMMENT 'JSON con detalles de errores por fila',
  `realizado_por_id` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_fecha` (`creado_en`),
  KEY `fk_import_usuario` (`realizado_por_id`),
  CONSTRAINT `fk_import_usuario` FOREIGN KEY (`realizado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `importaciones`
--

LOCK TABLES `importaciones` WRITE;
/*!40000 ALTER TABLE `importaciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `importaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `incidencias`
--

DROP TABLE IF EXISTS `incidencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `incidencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `folio` varchar(30) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `descripcion` text NOT NULL,
  `sucursal_id` int(11) NOT NULL,
  `area_id` int(11) NOT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `subcategoria_id` int(11) DEFAULT NULL,
  `tipo_trabajo_id` int(11) DEFAULT NULL,
  `severidad_id` int(11) NOT NULL,
  `estado_id` int(11) NOT NULL,
  `origen_reporte_id` int(11) DEFAULT NULL,
  `equipo_id` int(11) DEFAULT NULL,
  `reportado_por_id` int(11) NOT NULL,
  `reportante_nombre` varchar(150) DEFAULT NULL,
  `reportante_puesto` varchar(100) DEFAULT NULL,
  `asignado_a_id` int(11) DEFAULT NULL,
  `proveedor_escalado_id` int(11) DEFAULT NULL,
  `resuelto_por_id` int(11) DEFAULT NULL,
  `causa_raiz` text DEFAULT NULL,
  `solucion` text DEFAULT NULL,
  `recomendaciones` text DEFAULT NULL,
  `acciones_preventivas` text DEFAULT NULL,
  `es_reincidencia` tinyint(1) NOT NULL DEFAULT 0,
  `incidencia_padre_id` int(11) DEFAULT NULL,
  `veces_recurrida` int(11) NOT NULL DEFAULT 0,
  `fecha_evento` datetime NOT NULL,
  `fecha_atencion` datetime DEFAULT NULL,
  `fecha_resolucion` datetime DEFAULT NULL,
  `fecha_cierre` datetime DEFAULT NULL,
  `tiempo_respuesta_min` int(11) DEFAULT NULL,
  `tiempo_resolucion_min` int(11) DEFAULT NULL,
  `sla_cumplido` tinyint(1) DEFAULT NULL,
  `fecha_limite_sla` datetime DEFAULT NULL,
  `confirmado_por_reportante` tinyint(1) DEFAULT 0,
  `fecha_confirmacion` datetime DEFAULT NULL,
  `calificacion_servicio` int(11) DEFAULT NULL,
  `comentario_reportante` text DEFAULT NULL,
  `creado_en` datetime DEFAULT current_timestamp(),
  `creado_por_id` int(11) NOT NULL,
  `actualizado_en` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por_id` int(11) DEFAULT NULL,
  `archivada` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 si está archivada (resuelta hace >1 año)',
  `fecha_archivado` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `folio` (`folio`),
  KEY `categoria_id` (`categoria_id`),
  KEY `subcategoria_id` (`subcategoria_id`),
  KEY `tipo_trabajo_id` (`tipo_trabajo_id`),
  KEY `estado_id` (`estado_id`),
  KEY `origen_reporte_id` (`origen_reporte_id`),
  KEY `resuelto_por_id` (`resuelto_por_id`),
  KEY `incidencia_padre_id` (`incidencia_padre_id`),
  KEY `creado_por_id` (`creado_por_id`),
  KEY `actualizado_por_id` (`actualizado_por_id`),
  KEY `idx_folio` (`folio`),
  KEY `idx_sucursal_estado` (`sucursal_id`,`estado_id`),
  KEY `idx_area` (`area_id`),
  KEY `idx_severidad` (`severidad_id`),
  KEY `idx_asignado` (`asignado_a_id`),
  KEY `idx_reportado_por` (`reportado_por_id`),
  KEY `idx_equipo` (`equipo_id`),
  KEY `idx_fecha_evento` (`fecha_evento`),
  KEY `idx_reincidencia` (`es_reincidencia`,`incidencia_padre_id`),
  KEY `idx_busqueda_reincidencia` (`equipo_id`,`categoria_id`,`fecha_evento`),
  KEY `fk_incidencia_proveedor` (`proveedor_escalado_id`),
  KEY `idx_archivada` (`archivada`),
  CONSTRAINT `fk_incidencia_proveedor` FOREIGN KEY (`proveedor_escalado_id`) REFERENCES `proveedores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incidencias_ibfk_1` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`),
  CONSTRAINT `incidencias_ibfk_10` FOREIGN KEY (`reportado_por_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `incidencias_ibfk_11` FOREIGN KEY (`asignado_a_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incidencias_ibfk_12` FOREIGN KEY (`resuelto_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incidencias_ibfk_13` FOREIGN KEY (`incidencia_padre_id`) REFERENCES `incidencias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incidencias_ibfk_14` FOREIGN KEY (`creado_por_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `incidencias_ibfk_15` FOREIGN KEY (`actualizado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incidencias_ibfk_2` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`),
  CONSTRAINT `incidencias_ibfk_3` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incidencias_ibfk_4` FOREIGN KEY (`subcategoria_id`) REFERENCES `subcategorias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incidencias_ibfk_5` FOREIGN KEY (`tipo_trabajo_id`) REFERENCES `tipos_trabajo` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incidencias_ibfk_6` FOREIGN KEY (`severidad_id`) REFERENCES `severidades` (`id`),
  CONSTRAINT `incidencias_ibfk_7` FOREIGN KEY (`estado_id`) REFERENCES `estados` (`id`),
  CONSTRAINT `incidencias_ibfk_8` FOREIGN KEY (`origen_reporte_id`) REFERENCES `origenes_reporte` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incidencias_ibfk_9` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `incidencias`
--

LOCK TABLES `incidencias` WRITE;
/*!40000 ALTER TABLE `incidencias` DISABLE KEYS */;
INSERT INTO `incidencias` VALUES (2,'INC-BAC-2026-0001','Soporte de contraseña de caja','',1,1,1,NULL,1,4,6,1,NULL,1,'Cajera Beatriz','Cajera',13,NULL,13,NULL,'Se brindó apoyo al personal de caja debido a que no recordaban la contraseña de acceso. Se proporcionó la contraseña correcta y se verificó el acceso exitoso al sistema.',NULL,NULL,0,NULL,0,'2026-05-15 09:00:00',NULL,'2026-05-15 11:00:00','2026-05-15 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-15 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(3,'INC-BAC-2026-0002','Soporte de contraseña de PC','',1,2,1,NULL,1,4,6,1,NULL,1,'Nadia Guerrero','Personal',13,NULL,13,NULL,'Se brindó apoyo para el acceso al equipo de bóveda, realizando el desbloqueo del usuario del PC y verificando el acceso correcto al sistema.',NULL,NULL,0,NULL,0,'2026-05-16 09:00:00',NULL,'2026-05-16 11:00:00','2026-05-16 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-16 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(4,'INC-BAC-2026-0003','Cambio de Precios en Basculas','',1,3,1,NULL,1,4,6,1,NULL,1,'Juan Luis','Personal',13,NULL,13,NULL,'Se realizó la actualización de precios en básculas correspondientes a diversos productos, verificando la correcta aplicación de los cambios en el sistema.',NULL,NULL,0,NULL,0,'2026-05-17 09:00:00',NULL,'2026-05-17 11:00:00','2026-05-17 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-17 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(5,'INC-BAC-2026-0004','Silenciamiento de alarma contra incendios','',1,19,8,NULL,2,3,6,1,NULL,1,NULL,NULL,13,NULL,13,NULL,'Se atendió activación de alarma contra incendios, realizando el silenciamiento y verificación del sistema para restablecer su funcionamiento normal.',NULL,NULL,0,NULL,0,'2026-05-17 09:00:00',NULL,'2026-05-17 11:00:00','2026-05-17 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-17 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(6,'INC-BAC-2026-0005','Reinicio de sistema de alarma contra incendios','',1,19,8,NULL,2,3,6,1,NULL,1,NULL,NULL,13,NULL,13,NULL,'Se atendió una nueva activación de la alarma contra incendios, realizando el silenciamiento y verificación del sistema para restablecer su funcionamiento normal.',NULL,NULL,0,NULL,0,'2026-05-17 09:00:00',NULL,'2026-05-17 11:00:00','2026-05-17 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-17 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(7,'INC-BAC-2026-0006','Mal funcionamiento de perifericos','',1,4,1,NULL,1,4,6,1,NULL,1,'Arlette','Personal',12,NULL,12,NULL,'Se cambio periferico (Mouse) por error de desconexion, se dejo funcionando.',NULL,NULL,0,NULL,0,'2026-05-18 09:00:00',NULL,'2026-05-18 11:00:00','2026-05-18 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-18 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(8,'INC-BAC-2026-0007','Ticket de caja 2 borrosos','',1,1,6,NULL,6,3,6,1,NULL,1,'Cajera Ana Maria','Cajera',12,NULL,12,NULL,'Se limpio cabezal de impresion, se hicieron pruebas y se dejo funcionando.',NULL,NULL,0,NULL,0,'2026-05-18 09:00:00',NULL,'2026-05-18 11:00:00','2026-05-18 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-18 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(9,'INC-BAC-2026-0008','Problema con el funcionamiento de Compaq','',1,2,2,NULL,1,3,6,1,NULL,1,'Rosa Avalos','Personal',12,NULL,12,NULL,'Se reviso el equipo de Rosa, se detecto problema de cache en aplicacion de Compaq Contabilidad, se borro cache y se inicializo aplicacion, se dejo funcionando.',NULL,NULL,0,NULL,0,'2026-05-18 09:00:00',NULL,'2026-05-18 11:00:00','2026-05-18 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-18 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(10,'INC-BAC-2026-0009','Asistencia con factura','',1,1,6,NULL,6,3,6,1,NULL,1,'Cajera Betty','Cajera',12,NULL,12,NULL,'Se brindo asistencia con la generacion de una factura que tenia un codigo del sat erroneo, se corrigio codigo y se genero la factura.',NULL,NULL,0,NULL,0,'2026-05-19 09:00:00',NULL,'2026-05-19 11:00:00','2026-05-19 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-19 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(11,'INC-BAC-2026-0010','Actualizacion de verificacion de precios','',1,5,2,NULL,1,3,6,1,NULL,1,'Encargada de Almacen Gaby','Almacén',12,NULL,12,NULL,'Se actualizo base de datos de precios recientemente modificados por almacen.',NULL,NULL,0,NULL,0,'2026-05-19 09:00:00',NULL,'2026-05-19 11:00:00','2026-05-19 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-19 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(12,'INC-BAC-2026-0011','Asistencia con factura Global','',1,6,6,NULL,6,3,6,1,NULL,1,'Cajera Betty','Cajera',12,NULL,12,NULL,'Se brindo asistencia para generar una factura global y sus negativos.',NULL,NULL,0,NULL,0,'2026-05-19 09:00:00',NULL,'2026-05-19 11:00:00','2026-05-19 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-19 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(13,'INC-BAC-2026-0012','Solicitud de video de proveedores en tienda','',1,5,7,NULL,3,3,6,1,NULL,1,'Ivonne Almacen','Almacén',12,NULL,12,NULL,'Se descargo video solicitado por almacen por incidente con proveedor de Lala.',NULL,NULL,0,NULL,0,'2026-05-20 09:00:00',NULL,'2026-05-20 11:00:00','2026-05-20 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-20 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(14,'INC-BAC-2026-0013','Asistencia con escaneo de Layout Tienda','',1,7,9,NULL,5,3,6,1,NULL,1,'Bridget','Personal',12,NULL,12,NULL,'Se brindo asistencia para el escaneo de documento en formato LD.',NULL,NULL,0,NULL,0,'2026-05-20 09:00:00',NULL,'2026-05-20 11:00:00','2026-05-20 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-20 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(15,'INC-BAC-2026-0014','Generacion de Filtro en Back Office MrTienda','',1,3,6,NULL,6,3,6,1,NULL,1,'Juan Luis Martel','Personal',12,NULL,12,NULL,'Se brindo asistencia con la actualizacion de filtros en Mrtienda, se explico funcionamiento y se crearon filtros nuevos.',NULL,NULL,0,NULL,0,'2026-05-20 09:00:00',NULL,'2026-05-20 11:00:00','2026-05-20 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-20 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(16,'INC-BAC-2026-0015','Solicitud de asistencia con cajas trabadas','',1,1,6,NULL,6,3,6,1,NULL,1,'Cajera Jessica','Cajera',12,NULL,12,NULL,'Se solicito asistencia con cajas trabadas, al llegar las cajas funcionaban perfectamente, se espero en sitio para ver si volvia a fallar pero se mantuvo estable.',NULL,NULL,0,NULL,0,'2026-05-20 09:00:00',NULL,'2026-05-20 11:00:00','2026-05-20 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-20 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(17,'INC-BAC-2026-0016','Revision de telefono Cuarto Frio','',1,12,4,NULL,7,3,6,1,NULL,1,'Juan Luis Martel','Personal',12,NULL,12,NULL,'Se reviso telefono por peticion de Juan Luis, se encontro que estaba en “No Molestar” se reactivo sonido y se dejo funcionando.',NULL,NULL,0,NULL,0,'2026-05-20 09:00:00',NULL,'2026-05-20 11:00:00','2026-05-20 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-20 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(18,'INC-BAC-2026-0017','Asistencia con Mrtienda','',1,6,6,NULL,6,4,6,1,NULL,1,'Cajera Ana Maria','Cajera',12,NULL,12,NULL,'Se solicito apoyo por que el Mrtienda aparecia como fuera de linea, se reviso conexion UTP de telefono a PC y estaba desconectado por que movieron el telefono, se reconecto y se dejo funcionando.',NULL,NULL,0,NULL,0,'2026-05-20 09:00:00',NULL,'2026-05-20 11:00:00','2026-05-20 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-20 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(19,'INC-BAC-2026-0018','Creacion de scrip para Mrtienda','',1,1,6,NULL,6,3,6,1,NULL,1,NULL,NULL,12,NULL,12,NULL,'Se creo un scrip para que se reinicie el Mrtienda cuando este trabado, adicionalmente se agregaron 2 versiones mas para cobrar sin red y restablecer la red, se dejo en la barra de tareas y se capacito a las cajeras en turno el como y cuando utilizarlo.',NULL,NULL,0,NULL,0,'2026-05-21 09:00:00',NULL,'2026-05-21 11:00:00','2026-05-21 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-21 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(20,'INC-BAC-2026-0019','Configuracion de grabacion en DVR 3er Piso','',1,19,7,NULL,3,3,6,1,NULL,1,NULL,NULL,12,NULL,12,NULL,'Se configuro el modo de grabacion del DVR del tercer piso para que grabe continuo 24/7 y no solo cuando detecte movimiento.',NULL,NULL,0,NULL,0,'2026-05-21 09:00:00',NULL,'2026-05-21 11:00:00','2026-05-21 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-21 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(21,'INC-BAC-2026-0020','Duda por corte en caja','',1,1,6,NULL,6,3,6,1,NULL,1,'Cajera Betty','Cajera',12,NULL,12,NULL,'Cajera marco por duda sobre un corte en caja que no se utilizo, se explica que al no haber abierto caja no es necesario que se realice corte.',NULL,NULL,0,NULL,0,'2026-05-21 09:00:00',NULL,'2026-05-21 11:00:00','2026-05-21 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-21 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(22,'INC-BAC-2026-0021','Configuracion de aplicacion de Uber viajes','',1,7,2,NULL,1,3,6,1,NULL,1,'Bridget Seguridad e Higiene','Seguridad e Higiene',12,NULL,12,NULL,'Se configuro aplicacion de uber para vajes.',NULL,NULL,0,NULL,0,'2026-05-21 09:00:00',NULL,'2026-05-21 11:00:00','2026-05-21 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-21 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(23,'INC-BAC-2026-0022','Actualizacion de MrTienda','',1,19,6,NULL,6,3,6,1,NULL,1,NULL,NULL,14,NULL,14,NULL,'Se cargo la ultima actualizacion de MrTienda a los equipos de computo de Bacal, se hicieron multiples pruebas y se dejo funcionando',NULL,NULL,0,NULL,0,'2026-05-22 09:00:00',NULL,'2026-05-22 11:00:00','2026-05-22 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-22 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(24,'INC-BAC-2026-0023','Impresora Imprime Sola','',1,1,9,NULL,5,3,6,1,NULL,1,'Juan Luis Martel','Personal',13,NULL,13,NULL,'Primeramente se identificó el equipo que ocasionó la incidencia. Durante el análisis se detectó que múltiples procesos/servicios de impresión se encontraban en estado de pausa, lo que generaba la interrupción operativa.  Se realizó diagnóstico de conectividad y validación de parámetros de red, concluyendo que la configuración presentaba inconsistencias. Posteriormente, se efectuó la reconfiguración completa del dispositivo, incluyendo asignación y validación de dirección IP, parámetros de comunicación y pruebas de conectividad.  Finalmente, se realizaron pruebas funcionales y de operación, confirmando el restablecimiento correcto del servicio y dejando el equipo en funcionamiento estable.',NULL,NULL,0,NULL,0,'2026-05-22 09:00:00',NULL,'2026-05-22 11:00:00','2026-05-22 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-22 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(25,'INC-BAC-2026-0024','Asistencia con programa de camaras','',1,3,7,NULL,3,3,6,1,NULL,1,'Miguel Garcia','Personal',13,NULL,13,NULL,'Se realizó la configuración de los canales de cámaras en el software correspondiente, asignando y validando las direcciones IP de cada dispositivo para asegurar la correcta comunicación en red. Finalmente, se efectuaron pruebas de visualización y funcionamiento, dejando el sistema operando correctamente.',NULL,NULL,0,NULL,0,'2026-05-23 09:00:00',NULL,'2026-05-23 11:00:00','2026-05-23 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-23 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(26,'INC-BAC-2026-0025','Fallo de Conexion en Terminal de Cobro','',1,1,1,NULL,1,3,6,1,NULL,1,'Cajera Ana','Cajera',13,NULL,13,NULL,'Se realizó la reconexión del equipo a la red, debido a que se encontraba sin transmisión de datos. Se verificó que la falla no estuviera relacionada con la red de Telcel y posteriormente se efectuó un ajuste en el chip/SIM para restablecer la comunicación. Finalmente, se realizaron pruebas de conectividad, dejando el servicio funcionando correctamente.',NULL,NULL,0,NULL,0,'2026-05-23 09:00:00',NULL,'2026-05-23 11:00:00','2026-05-23 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-23 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(27,'INC-BAC-2026-0026','Fallo de escaner de caja','',1,1,6,NULL,6,3,6,1,NULL,1,'Cajera Yessica','Cajera',13,NULL,13,NULL,'Se detectó que la falla era de origen físico en la conexión del equipo. Se realizó el ajuste correspondiente en el cableado/conector y posteriormente se efectuaron pruebas de funcionamiento para validar la correcta operación del servicio.',NULL,NULL,0,NULL,0,'2026-05-23 09:00:00',NULL,'2026-05-23 11:00:00','2026-05-23 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-23 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(28,'INC-BAC-2026-0027','Re-activacion Nodo de Red','',1,19,3,NULL,4,3,6,1,NULL,1,NULL,NULL,13,NULL,13,NULL,'Durante la revisión rutinaria semanal de equipos, conexiones físicas y alarmas, se detectó un nodo sin conexión en el área de sala de juntas. Se realizó el reponchado del cableado y ajuste del nodo para restablecer la comunicación. Finalmente, se efectuaron pruebas de conectividad, dejando el punto en correcto funcionamiento.',NULL,NULL,0,NULL,0,'2026-05-24 09:00:00',NULL,'2026-05-24 11:00:00','2026-05-24 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-24 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(29,'INC-BAC-2026-0028','Mantenimiento a Cajas','',1,19,1,NULL,1,3,6,1,NULL,1,NULL,NULL,13,NULL,13,NULL,'Se realizó inspección y mantenimiento preventivo a los equipos de cómputo del área de cajas, verificando el estado físico y operativo de los dispositivos para asegurar su correcto funcionamiento.',NULL,NULL,0,NULL,0,'2026-05-24 09:00:00',NULL,'2026-05-24 11:00:00','2026-05-24 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-24 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(30,'INC-BAC-2026-0029','Fallo Bascula en caja','',1,1,6,NULL,6,3,6,1,NULL,1,'Cajera Marlen','Cajera',12,NULL,12,NULL,'Se detectó una falla durante el arranque del equipo al momento de encenderlo. Se realizó un reinicio previo a una nueva prueba de operación, logrando restablecer el funcionamiento correctamente. Finalmente, se verificó y calibró el equipo, quedando operando de manera normal.',NULL,NULL,0,NULL,0,'2026-05-25 09:00:00',NULL,'2026-05-25 11:00:00','2026-05-25 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-25 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(31,'INC-BAC-2026-0030','Error en generacion de factura','',1,1,6,NULL,6,3,6,1,NULL,1,'Cajera Ana Maria','Cajera',12,NULL,12,NULL,'Se solicito asistencia por no poder generar una factura, se desbloqueo serie de facturas y se dejo funcionando.',NULL,NULL,0,NULL,0,'2026-05-25 09:00:00',NULL,'2026-05-25 11:00:00','2026-05-25 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-25 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(32,'INC-BAC-2026-0031','Fallo en impresora termica','',1,1,6,NULL,6,3,6,1,NULL,1,'Cajera Ana Maria','Cajera',12,NULL,12,NULL,'Impresora con fallo de sensor de papel, se arreglo y se dejo funcionando.',NULL,NULL,0,NULL,0,'2026-05-26 09:00:00',NULL,'2026-05-26 11:00:00','2026-05-26 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-26 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(33,'INC-BAC-2026-0032','Bascula no imprime etiqutas','',1,11,1,NULL,1,3,4,1,NULL,1,'Carniceria','Carnicería',12,NULL,NULL,'Banda de 30 pines con daño físico','Fallo en banda de 30 pines',NULL,NULL,0,NULL,0,'2026-05-26 09:00:00','2026-05-28 18:22:22',NULL,NULL,3442,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-26 09:00:00',1,'2026-05-28 18:22:22',12,0,NULL),(34,'INC-BAC-2026-0033','Visita de proveedores de alarmas','',1,6,8,NULL,2,3,6,1,NULL,1,'Guardia','Guardia',12,NULL,12,NULL,'Proveedor de alarmas visito la sucursal para revision de una zona en fallo, se atendio y se dejo funcionando.',NULL,NULL,0,NULL,0,'2026-05-26 09:00:00',NULL,'2026-05-26 11:00:00','2026-05-26 11:00:00',NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,'2026-05-26 09:00:00',1,'2026-05-27 09:25:40',NULL,0,NULL),(35,'INC-BAC-2026-0034','Coordinación de actualización Compaq.','Se gestiono con proveedor Uni-Red la actualización del software por problemas con actualización de hidrocarburos en temas contables.',1,2,12,NULL,11,3,6,2,NULL,1,'Rosa Maria Avalos','Jefa de Contabilidad',12,NULL,12,'Actualizacion','Se actualizo servidor de compaq y se instalo nueva versión en los puestos de trabajo de las contadoras y RH.',NULL,NULL,0,NULL,0,'2026-05-27 14:40:00','2026-05-27 17:51:58','2026-05-28 15:42:18','2026-05-28 15:42:18',192,1310,0,'2026-05-28 14:40:00',0,NULL,NULL,NULL,'2026-05-27 17:51:58',1,'2026-05-28 15:42:18',12,0,NULL),(36,'INC-BAC-2026-0035','Fallo en impresora','Se solicito asistencia por llamada por impresora que no funciona.',1,3,9,18,14,4,6,2,NULL,12,'Gerente Miguel Angel','Gerente',12,NULL,12,'Duplicidad de IP','Se configuro IP que estaba duplicada por teléfono celular, se saco de la red dicho teléfono y se dejo funcionando.','Crear red específicamente para teléfonos y que no comparten la red publica.',NULL,0,NULL,0,'2026-05-28 15:31:00','2026-05-28 15:47:03','2026-05-28 15:47:35','2026-05-28 15:47:35',16,1,1,'2026-05-31 15:31:00',0,NULL,NULL,NULL,'2026-05-28 15:46:49',12,'2026-05-28 15:47:35',12,0,NULL),(37,'INC-BAC-2026-0036','Correccion de datos fiscales de cliente','Cajera marco porque un cliente tiene un reclamo sobre la generación de de una factura con datos erroneos.',1,1,6,NULL,11,4,6,2,NULL,1,NULL,NULL,12,NULL,1,'Cajera en pedidos dio de alta de forma errónea el régimen fiscal del cliente.','Se corrigió régimen fiscal y se genero factura correctamente.','Capacitar cajeras para dar de alta correctamente a los clientes.',NULL,0,NULL,0,'2026-05-28 18:40:00','2026-05-28 18:07:44','2026-05-28 18:07:52','2026-05-28 18:07:52',0,0,1,'2026-05-31 18:40:00',0,NULL,NULL,NULL,'2026-05-28 18:07:44',1,'2026-05-28 18:07:52',1,0,NULL),(38,'INC-BAC-2026-0037','FALLO SCANNER DE PRODUCTOS','Al momento de cambiar de turno de cajera y empezar a cobrar, noto que no estaba detectando los codigos de barras de los productos',1,1,12,NULL,9,4,6,2,28,1,'YESIKA','CAJERA',13,NULL,1,'La cajera reportó que el escáner no detectaba los códigos de barras de los productos.','Se realizó una revisión física de los puertos de conexión del escáner, efectuando los ajustes necesarios. Posteriormente, se reinició el equipo y se realizaron pruebas de funcionamiento, confirmando que el escáner operaba correctamente.','Verificar periódicamente el estado de las conexiones del escáner y evitar movimientos bruscos del cableado para prevenir fallas similares.',NULL,0,NULL,0,'2026-05-29 14:00:00','2026-05-30 17:45:24','2026-05-30 17:46:15','2026-05-30 17:46:15',1665,1,1,'2026-06-01 14:00:00',0,NULL,NULL,NULL,'2026-05-30 17:45:24',1,'2026-05-30 17:48:58',1,0,NULL),(39,'INC-BAC-2026-0038','FALLO IMPRESORA DE TICKETS','Se reporto fallo en la impresora de ticket',1,1,9,NULL,5,4,6,NULL,NULL,1,NULL,'CAJERA',13,NULL,1,'La cajera reporto que los tickets salian con la informacion incompleta','Se ajusto bien el rollo de impresion, se hizo prueba y se dejo funcionando',NULL,NULL,0,NULL,0,'2026-05-30 15:00:00','2026-05-30 17:55:22','2026-05-30 17:55:30','2026-05-30 17:55:30',175,0,1,'2026-06-02 15:00:00',0,NULL,NULL,NULL,'2026-05-30 17:55:22',1,'2026-05-30 17:55:30',1,0,NULL),(40,'INC-BAC-2026-0039','CORREOS ELIMINADOS','La cajera reportó la ausencia de correos con pedidos nuevos. Se verificó la bandeja de entrada del área de pedidos, sin encontrar mensajes pendientes. Posteriormente, se revisó la carpeta de correos eliminados, donde se localizaron los correos reportados.',1,1,10,NULL,14,4,6,2,32,1,'ARMIDA','CAJERA',13,NULL,1,'Los correos de pedidos fueron eliminados accidentalmente y quedaron almacenados en la carpeta de elementos eliminados, por lo que no eran visibles en la bandeja de entrada del área de pedidos.','Se revisó la carpeta de correos eliminados, donde se localizaron los mensajes reportados. Posteriormente, se restauraron los correos y se verificó su correcta disponibilidad para el usuario.','Se recomienda confirmar la ubicación de los correos en las diferentes carpetas del sistema (entrada, eliminados, archivados, entre otras).\r\nEn caso de desconocer el funcionamiento del entorno de correo electrónico, se recomienda brindar capacitación al personal para el correcto uso y gestión de las bandejas.',NULL,0,NULL,0,'2026-05-31 06:27:00','2026-05-31 06:38:52','2026-05-31 06:39:03','2026-05-31 06:39:03',12,0,1,'2026-06-03 06:27:00',0,NULL,NULL,NULL,'2026-05-31 06:38:52',1,'2026-05-31 06:39:03',1,0,NULL);
/*!40000 ALTER TABLE `incidencias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `incidencias_adjuntos`
--

DROP TABLE IF EXISTS `incidencias_adjuntos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `incidencias_adjuntos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `incidencia_id` int(11) NOT NULL,
  `nombre_original` varchar(255) NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `ruta` varchar(500) NOT NULL,
  `tipo_mime` varchar(100) DEFAULT NULL,
  `tamano_bytes` int(11) DEFAULT NULL,
  `momento` varchar(20) DEFAULT 'durante',
  `descripcion` varchar(255) DEFAULT NULL,
  `subido_por_id` int(11) NOT NULL,
  `subido_en` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `subido_por_id` (`subido_por_id`),
  KEY `idx_incidencia` (`incidencia_id`),
  CONSTRAINT `incidencias_adjuntos_ibfk_1` FOREIGN KEY (`incidencia_id`) REFERENCES `incidencias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `incidencias_adjuntos_ibfk_2` FOREIGN KEY (`subido_por_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `incidencias_adjuntos`
--

LOCK TABLES `incidencias_adjuntos` WRITE;
/*!40000 ALTER TABLE `incidencias_adjuntos` DISABLE KEYS */;
INSERT INTO `incidencias_adjuntos` VALUES (1,35,'CotizacionCompaq-27_MAY_2026.pdf','9ad52d7b477e121e226ed0abd84b8a42.pdf','uploads/2026/05/9ad52d7b477e121e226ed0abd84b8a42.pdf','application/pdf',99485,'durante',NULL,1,'2026-05-27 17:51:58'),(2,33,'image.jpg','f5769f62312ed8179656f35e1838d6d8.jpg','uploads/2026/05/f5769f62312ed8179656f35e1838d6d8.jpg','image/jpeg',3270966,'durante',NULL,12,'2026-05-28 18:21:08'),(3,40,'WhatsApp Image 2026-05-31 at 6.24.53 AM.jpeg','ef076ed16a7c47362bc998bb21724d55.jpeg','uploads/2026/05/ef076ed16a7c47362bc998bb21724d55.jpeg','image/jpeg',163767,'durante',NULL,1,'2026-05-31 06:38:52'),(4,40,'WhatsApp Image 2026-05-31 at 6.24.53 AM (1).jpeg','e2f42a1165ce7bd098f8d83c2934717c.jpeg','uploads/2026/05/e2f42a1165ce7bd098f8d83c2934717c.jpeg','image/jpeg',301481,'durante',NULL,1,'2026-05-31 06:38:52');
/*!40000 ALTER TABLE `incidencias_adjuntos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `incidencias_comentarios`
--

DROP TABLE IF EXISTS `incidencias_comentarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `incidencias_comentarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `incidencia_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `comentario` text NOT NULL,
  `es_interno` tinyint(1) NOT NULL DEFAULT 0,
  `creado_en` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_incidencia` (`incidencia_id`),
  CONSTRAINT `incidencias_comentarios_ibfk_1` FOREIGN KEY (`incidencia_id`) REFERENCES `incidencias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `incidencias_comentarios_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `incidencias_comentarios`
--

LOCK TABLES `incidencias_comentarios` WRITE;
/*!40000 ALTER TABLE `incidencias_comentarios` DISABLE KEYS */;
INSERT INTO `incidencias_comentarios` VALUES (1,33,12,'En espera de comprar banda de repuesto.',0,'2026-05-28 18:21:59');
/*!40000 ALTER TABLE `incidencias_comentarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `incidencias_etiquetas`
--

DROP TABLE IF EXISTS `incidencias_etiquetas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `incidencias_etiquetas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `incidencia_id` int(11) NOT NULL,
  `etiqueta` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_incidencia_etiqueta` (`incidencia_id`,`etiqueta`),
  KEY `idx_etiqueta` (`etiqueta`),
  CONSTRAINT `incidencias_etiquetas_ibfk_1` FOREIGN KEY (`incidencia_id`) REFERENCES `incidencias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `incidencias_etiquetas`
--

LOCK TABLES `incidencias_etiquetas` WRITE;
/*!40000 ALTER TABLE `incidencias_etiquetas` DISABLE KEYS */;
/*!40000 ALTER TABLE `incidencias_etiquetas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `incidencias_historial`
--

DROP TABLE IF EXISTS `incidencias_historial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `incidencias_historial` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `incidencia_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `accion` varchar(50) NOT NULL,
  `campo` varchar(100) DEFAULT NULL,
  `valor_anterior` text DEFAULT NULL,
  `valor_nuevo` text DEFAULT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `creado_en` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_incidencia` (`incidencia_id`),
  KEY `idx_fecha` (`creado_en`),
  CONSTRAINT `incidencias_historial_ibfk_1` FOREIGN KEY (`incidencia_id`) REFERENCES `incidencias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `incidencias_historial_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `incidencias_historial`
--

LOCK TABLES `incidencias_historial` WRITE;
/*!40000 ALTER TABLE `incidencias_historial` DISABLE KEYS */;
INSERT INTO `incidencias_historial` VALUES (1,35,1,'adjuntos_subidos','adjuntos',NULL,'1 archivo(s)','1 archivo(s) adjuntados al crear','2026-05-27 17:51:58'),(2,35,1,'creada',NULL,NULL,'INC-BAC-2026-0034','Incidencia creada con folio INC-BAC-2026-0034','2026-05-27 17:51:58'),(3,35,12,'campo_cambiado','solucion','','Se actualizo servidor de compaq y se instalo nueva versión en los puestos de trabajo de las contadoras y RH.','Solución modificado','2026-05-28 15:42:00'),(4,35,12,'campo_cambiado','causa_raiz','','Actualizacion','Causa raíz modificado','2026-05-28 15:42:00'),(5,35,12,'estado_cambiado','estado_id','1','6','Estado cambiado a Completada','2026-05-28 15:42:18'),(6,36,12,'creada',NULL,NULL,'INC-BAC-2026-0035','Incidencia creada con folio INC-BAC-2026-0035','2026-05-28 15:46:49'),(7,36,12,'asignado','asignado_a_id','','12','Asignado a Luis Fernando Rodriguez Cruz','2026-05-28 15:47:03'),(8,36,12,'estado_cambiado','estado_id','1','5','Estado cambiado a Resuelta','2026-05-28 15:47:07'),(9,36,12,'estado_cambiado','estado_id','5','6','Estado cambiado a Completada','2026-05-28 15:47:35'),(10,37,1,'creada',NULL,NULL,'INC-BAC-2026-0036','Incidencia creada con folio INC-BAC-2026-0036','2026-05-28 18:07:44'),(11,37,1,'estado_cambiado','estado_id','1','6','Estado cambiado a Completada','2026-05-28 18:07:52'),(12,33,12,'campo_cambiado','solucion','Fallo en cabezal de impresion, se revisa','Fallo en banda de 30 pines','Solución modificado','2026-05-28 18:19:33'),(13,33,12,'campo_cambiado','causa_raiz','','Banda de 30 pines con daño físico','Causa raíz modificado','2026-05-28 18:19:33'),(14,33,12,'adjuntos_subidos','adjuntos',NULL,'1 archivo(s)','1 archivo(s) adjuntados','2026-05-28 18:21:08'),(15,33,12,'estado_cambiado','estado_id','3','4','Estado cambiado a En espera','2026-05-28 18:22:22'),(16,38,1,'creada',NULL,NULL,'INC-BAC-2026-0037','Incidencia creada con folio INC-BAC-2026-0037','2026-05-30 17:45:24'),(17,38,1,'estado_cambiado','estado_id','1','6','Estado cambiado a Completada','2026-05-30 17:46:15'),(18,38,1,'campo_cambiado','solucion','','Se realizó una revisión física de los puertos de conexión del escáner, efectuando los ajustes necesarios. Posteriormente, se reinició el equipo y se realizaron pruebas de funcionamiento, confirmando que el escáner operaba correctamente.','Solución modificado','2026-05-30 17:48:58'),(19,38,1,'campo_cambiado','recomendaciones','','Verificar periódicamente el estado de las conexiones del escáner y evitar movimientos bruscos del cableado para prevenir fallas similares.','Recomendaciones modificado','2026-05-30 17:48:58'),(20,38,1,'campo_cambiado','causa_raiz','','La cajera reportó que el escáner no detectaba los códigos de barras de los productos.','Causa raíz modificado','2026-05-30 17:48:58'),(21,39,1,'creada',NULL,NULL,'INC-BAC-2026-0038','Incidencia creada con folio INC-BAC-2026-0038','2026-05-30 17:55:22'),(22,39,1,'estado_cambiado','estado_id','1','6','Estado cambiado a Completada','2026-05-30 17:55:30'),(23,40,1,'adjuntos_subidos','adjuntos',NULL,'2 archivo(s)','2 archivo(s) adjuntados al crear','2026-05-31 06:38:52'),(24,40,1,'creada',NULL,NULL,'INC-BAC-2026-0039','Incidencia creada con folio INC-BAC-2026-0039','2026-05-31 06:38:52'),(25,40,1,'estado_cambiado','estado_id','1','1','Estado cambiado a Abierta','2026-05-31 06:38:57'),(26,40,1,'estado_cambiado','estado_id','1','6','Estado cambiado a Completada','2026-05-31 06:39:03');
/*!40000 ALTER TABLE `incidencias_historial` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mantenimientos`
--

DROP TABLE IF EXISTS `mantenimientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mantenimientos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipo_id` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_programada` date NOT NULL,
  `hora_programada` time DEFAULT NULL,
  `asignado_a_id` int(11) DEFAULT NULL COMMENT 'Tecnico asignado',
  `proveedor_id` int(11) DEFAULT NULL COMMENT 'Si lo hace un proveedor externo',
  `estado` enum('programado','proximo','en_progreso','completado','cancelado','vencido') NOT NULL DEFAULT 'programado',
  `es_recurrente` tinyint(1) NOT NULL DEFAULT 0,
  `recurrencia_tipo` enum('dias','semanas','meses','anios') DEFAULT NULL,
  `recurrencia_valor` int(11) DEFAULT NULL COMMENT 'Cada cuantas unidades (ej. 3 meses)',
  `mantenimiento_padre_id` int(11) DEFAULT NULL COMMENT 'Si fue auto-generado, apunta al original',
  `fecha_inicio_real` datetime DEFAULT NULL,
  `fecha_completado` datetime DEFAULT NULL,
  `realizado_por_id` int(11) DEFAULT NULL COMMENT 'Quien lo ejecuto realmente',
  `resultado` text DEFAULT NULL COMMENT 'Notas de lo que se hizo',
  `costo` decimal(10,2) DEFAULT NULL,
  `incidencia_generada_id` int(11) DEFAULT NULL COMMENT 'Si se convirtio en incidencia',
  `creado_por_id` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_equipo` (`equipo_id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_fecha` (`fecha_programada`),
  KEY `idx_asignado` (`asignado_a_id`),
  KEY `idx_padre` (`mantenimiento_padre_id`),
  KEY `fk_mant_proveedor` (`proveedor_id`),
  KEY `fk_mant_realizado` (`realizado_por_id`),
  KEY `fk_mant_creador` (`creado_por_id`),
  KEY `fk_mant_incidencia` (`incidencia_generada_id`),
  CONSTRAINT `fk_mant_asignado` FOREIGN KEY (`asignado_a_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mant_creador` FOREIGN KEY (`creado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mant_equipo` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mant_incidencia` FOREIGN KEY (`incidencia_generada_id`) REFERENCES `incidencias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mant_padre` FOREIGN KEY (`mantenimiento_padre_id`) REFERENCES `mantenimientos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mant_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mant_realizado` FOREIGN KEY (`realizado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mantenimientos`
--

LOCK TABLES `mantenimientos` WRITE;
/*!40000 ALTER TABLE `mantenimientos` DISABLE KEYS */;
/*!40000 ALTER TABLE `mantenimientos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notificaciones`
--

DROP TABLE IF EXISTS `notificaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notificaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `mensaje` text DEFAULT NULL,
  `enlace` varchar(500) DEFAULT NULL,
  `leida` tinyint(1) NOT NULL DEFAULT 0,
  `leida_en` datetime DEFAULT NULL,
  `creada_en` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_usuario_leida` (`usuario_id`,`leida`),
  KEY `idx_fecha` (`creada_en`),
  CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notificaciones`
--

LOCK TABLES `notificaciones` WRITE;
/*!40000 ALTER TABLE `notificaciones` DISABLE KEYS */;
INSERT INTO `notificaciones` VALUES (1,12,'asignacion','Se te asignó INC-BAC-2026-0034','Coordinación de actualización Compaq. · Severidad: Media','/UtilidadesBacal/BitacoraSistemas/incidencia_ver.php?id=35',1,'2026-05-28 16:21:26','2026-05-27 17:51:58'),(2,1,'incidencia_resuelta','INC-BAC-2026-0034 resuelta','Coordinación de actualización Compaq.','/UtilidadesBacal/BitacoraSistemas/incidencia_ver.php?id=35',1,'2026-05-28 18:15:40','2026-05-28 15:42:18'),(3,12,'asignacion','Se te asignó INC-BAC-2026-0036','Correccion de datos fiscales de cliente · Severidad: Baja','/UtilidadesBacal/BitacoraSistemas/incidencia_ver.php?id=37',1,'2026-05-28 18:22:38','2026-05-28 18:07:44'),(4,12,'incidencia_resuelta','INC-BAC-2026-0036 resuelta','Correccion de datos fiscales de cliente','/UtilidadesBacal/BitacoraSistemas/incidencia_ver.php?id=37',1,'2026-05-28 18:22:34','2026-05-28 18:07:52'),(5,1,'comentario','Nuevo comentario en INC-BAC-2026-0032','Luis Fernando Rodriguez Cruz: \"En espera de comprar banda de repuesto.\"','/UtilidadesBacal/BitacoraSistemas/incidencia_ver.php?id=33',1,'2026-05-31 12:31:48','2026-05-28 18:21:59'),(6,1,'cambio_estado','INC-BAC-2026-0032: En espera','Bascula no imprime etiqutas','/UtilidadesBacal/BitacoraSistemas/incidencia_ver.php?id=33',1,'2026-05-31 12:31:50','2026-05-28 18:22:22'),(7,13,'asignacion','Se te asignó INC-BAC-2026-0037','FALLO SCANNER DE PRODUCTOS · Severidad: Baja','/UtilidadesBacal/BitacoraSistemas/incidencia_ver.php?id=38',0,NULL,'2026-05-30 17:45:24'),(8,13,'incidencia_resuelta','INC-BAC-2026-0037 resuelta','FALLO SCANNER DE PRODUCTOS','/UtilidadesBacal/BitacoraSistemas/incidencia_ver.php?id=38',0,NULL,'2026-05-30 17:46:15'),(9,13,'asignacion','Se te asignó INC-BAC-2026-0038','FALLO IMPRESORA DE TICKETS · Severidad: Baja','/UtilidadesBacal/BitacoraSistemas/incidencia_ver.php?id=39',0,NULL,'2026-05-30 17:55:22'),(10,13,'incidencia_resuelta','INC-BAC-2026-0038 resuelta','FALLO IMPRESORA DE TICKETS','/UtilidadesBacal/BitacoraSistemas/incidencia_ver.php?id=39',0,NULL,'2026-05-30 17:55:30'),(11,13,'asignacion','Se te asignó INC-BAC-2026-0039','CORREOS ELIMINADOS · Severidad: Baja','/UtilidadesBacal/BitacoraSistemas/incidencia_ver.php?id=40',0,NULL,'2026-05-31 06:38:52'),(12,13,'cambio_estado','INC-BAC-2026-0039: Abierta','CORREOS ELIMINADOS','/UtilidadesBacal/BitacoraSistemas/incidencia_ver.php?id=40',0,NULL,'2026-05-31 06:38:57'),(13,13,'incidencia_resuelta','INC-BAC-2026-0039 resuelta','CORREOS ELIMINADOS','/UtilidadesBacal/BitacoraSistemas/incidencia_ver.php?id=40',0,NULL,'2026-05-31 06:39:04');
/*!40000 ALTER TABLE `notificaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `origenes_reporte`
--

DROP TABLE IF EXISTS `origenes_reporte`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `origenes_reporte` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `origenes_reporte`
--

LOCK TABLES `origenes_reporte` WRITE;
/*!40000 ALTER TABLE `origenes_reporte` DISABLE KEYS */;
INSERT INTO `origenes_reporte` VALUES (1,'Presencial',1),(2,'Telefónico',1),(3,'WhatsApp',1),(4,'Correo electrónico',1),(5,'Sistema',1),(6,'Mantenimiento programado',1),(7,'Otro',1);
/*!40000 ALTER TABLE `origenes_reporte` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `plantillas_incidencias`
--

DROP TABLE IF EXISTS `plantillas_incidencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plantillas_incidencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL COMMENT 'Para mostrar en la lista de plantillas',
  `icono` varchar(50) DEFAULT 'file-text' COMMENT 'Nombre del icono Lucide',
  `color` varchar(7) DEFAULT '#6B7280',
  `titulo` varchar(255) DEFAULT NULL,
  `descripcion_inc` text DEFAULT NULL COMMENT 'Descripcion del problema pre-rellenada',
  `area_id` int(11) DEFAULT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `subcategoria_id` int(11) DEFAULT NULL,
  `tipo_trabajo_id` int(11) DEFAULT NULL,
  `severidad_id` int(11) DEFAULT NULL,
  `origen_reporte_id` int(11) DEFAULT NULL,
  `solucion_sugerida` text DEFAULT NULL COMMENT 'Solucion tipica para este problema',
  `usos` int(11) NOT NULL DEFAULT 0 COMMENT 'Veces que se ha usado esta plantilla',
  `creado_por_id` int(11) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_activo` (`activo`),
  KEY `idx_usos` (`usos`),
  KEY `fk_plantilla_area` (`area_id`),
  KEY `fk_plantilla_categoria` (`categoria_id`),
  KEY `fk_plantilla_subcategoria` (`subcategoria_id`),
  KEY `fk_plantilla_tipo` (`tipo_trabajo_id`),
  KEY `fk_plantilla_severidad` (`severidad_id`),
  KEY `fk_plantilla_origen` (`origen_reporte_id`),
  KEY `fk_plantilla_creador` (`creado_por_id`),
  CONSTRAINT `fk_plantilla_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_plantilla_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_plantilla_creador` FOREIGN KEY (`creado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_plantilla_origen` FOREIGN KEY (`origen_reporte_id`) REFERENCES `origenes_reporte` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_plantilla_severidad` FOREIGN KEY (`severidad_id`) REFERENCES `severidades` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_plantilla_subcategoria` FOREIGN KEY (`subcategoria_id`) REFERENCES `subcategorias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_plantilla_tipo` FOREIGN KEY (`tipo_trabajo_id`) REFERENCES `tipos_trabajo` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plantillas_incidencias`
--

LOCK TABLES `plantillas_incidencias` WRITE;
/*!40000 ALTER TABLE `plantillas_incidencias` DISABLE KEYS */;
INSERT INTO `plantillas_incidencias` VALUES (1,'Reset de contraseña','Usuario olvido su contraseña del sistema','key','#D97706','Solicitud de reseteo de contraseña','El usuario no puede acceder al sistema y solicita el reseteo de su contraseña.\n\nUsuario: \nMotivo: ',2,10,NULL,9,4,2,'1. Verificar identidad del usuario\n2. Resetear contraseña desde el panel admin\n3. Comunicar nueva contraseña temporal de forma segura\n4. Confirmar que el usuario pudo acceder y cambiar la contraseña',0,NULL,1,'2026-05-22 02:03:16','2026-05-22 02:03:16'),(2,'Impresora sin tinta/toner','Falta consumible en impresora','printer','#7C3AED','Impresora sin tinta/toner','La impresora no imprime por falta de consumible.\n\nUbicacion: \nModelo: \nTipo de consumible necesario: ',NULL,1,NULL,8,2,1,'1. Confirmar modelo exacto de impresora\n2. Verificar inventario de consumibles\n3. Reemplazar tinta/toner\n4. Imprimir pagina de prueba para validar',0,NULL,1,'2026-05-22 02:03:16','2026-05-22 02:03:16'),(3,'Internet caido / lento','Perdida de conectividad o lentitud','wifi-off','#DC2626','Sin conexion a internet','Se reporta perdida total/parcial de conexion a internet.\n\nAreas afectadas: \nDispositivos afectados: \nHora aproximada del incidente: ',NULL,3,NULL,9,2,2,'1. Verificar luces del modem/router\n2. Reiniciar equipos de red (apagar 30 segundos)\n3. Verificar cableado fisico\n4. Contactar al proveedor si persiste\n5. Documentar tiempo de inactividad',0,NULL,1,'2026-05-22 02:03:16','2026-05-22 02:03:16'),(4,'Falla en terminal POS','Terminal/caja registradora no funciona','monitor-x','#DC2626','Falla en terminal de punto de venta','La terminal de punto de venta presenta fallas.\n\nCaja: \nSintoma especifico: \nUltima operacion exitosa: ',1,6,NULL,9,1,2,'1. Verificar conexiones fisicas\n2. Reiniciar la terminal\n3. Validar conexion con servidor central\n4. Si persiste, habilitar caja de respaldo y escalar',0,NULL,1,'2026-05-22 02:03:16','2026-05-22 02:03:16'),(5,'Bascula descalibrada','Bascula marca peso incorrecto','scale','#EA580C','Bascula descalibrada o con error de pesaje','La bascula presenta lecturas incorrectas o erraticas.\n\nUbicacion: \nModelo: \nMargen de error observado: ',NULL,1,NULL,8,2,1,'1. Limpiar el plato y sensor\n2. Verificar nivelacion de la bascula\n3. Calibrar con pesa patron\n4. Si no calibra, agendar servicio tecnico especializado',0,NULL,1,'2026-05-22 02:03:16','2026-05-22 02:03:16'),(6,'PC lenta o con problemas','Equipo de computo con bajo rendimiento','cpu','#0EA5E9','Computadora con rendimiento lento','La PC presenta lentitud para realizar tareas normales.\n\nUbicacion: \nUsuario: \nSintomas especificos: ',NULL,1,NULL,9,4,2,'1. Revisar uso de CPU/RAM en administrador de tareas\n2. Limpieza de archivos temporales\n3. Escaneo antivirus\n4. Verificar inicio automatico de programas\n5. Si persiste, evaluar mantenimiento preventivo',0,NULL,1,'2026-05-22 02:03:16','2026-05-22 02:03:16'),(7,'Email no funciona','Problema con correo electronico corporativo','mail-x','#7C3AED','Falla en correo electronico','No se puede enviar/recibir correos.\n\nUsuario: \nCliente de correo: \nMensaje de error: ',NULL,2,NULL,9,2,2,'1. Verificar conectividad a internet\n2. Probar acceso por webmail\n3. Revisar configuracion SMTP/IMAP\n4. Verificar espacio en buzon\n5. Validar credenciales',0,NULL,1,'2026-05-22 02:03:16','2026-05-22 02:03:16'),(8,'Mantenimiento preventivo programado','Mantenimiento preventivo de rutina','wrench','#16A34A','Mantenimiento preventivo programado','Mantenimiento preventivo programado para mantener equipos en optimas condiciones.\n\nEquipo(s): \nTareas planeadas: ',NULL,1,NULL,8,4,1,'1. Limpieza interna y externa de equipos\n2. Verificacion de software actualizado\n3. Backup de informacion critica\n4. Pruebas de funcionamiento\n5. Documentar estado de cada componente',0,NULL,1,'2026-05-22 02:03:16','2026-05-22 02:03:16');
/*!40000 ALTER TABLE `plantillas_incidencias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proveedor_contactos`
--

DROP TABLE IF EXISTS `proveedor_contactos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proveedor_contactos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proveedor_id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL COMMENT 'Nombre de la persona contacto',
  `puesto` varchar(100) DEFAULT NULL COMMENT 'ej. Asesor de basculas, Soporte',
  `telefono` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `notas` varchar(255) DEFAULT NULL COMMENT 'ej. Solo turno matutino',
  `es_principal` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Marca el contacto principal',
  `orden` int(11) NOT NULL DEFAULT 0,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_proveedor` (`proveedor_id`),
  CONSTRAINT `fk_contacto_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proveedor_contactos`
--

LOCK TABLES `proveedor_contactos` WRITE;
/*!40000 ALTER TABLE `proveedor_contactos` DISABLE KEYS */;
INSERT INTO `proveedor_contactos` VALUES (1,1,'Alejandro Lozano','Contacto principal',NULL,'i.lozano@abasteo.mx',NULL,1,1,'2026-05-22 23:59:34'),(2,2,'Aldo Linares','Soporte tecnico','664 385 4983','aldolinares@netsistem.com.mx',NULL,1,1,'2026-05-22 23:59:34'),(3,3,'Deyanira Soto','Asesora de cuenta','662 555 8912','dsoto@metrocarrier.com.mx',NULL,1,1,'2026-05-22 23:59:34'),(6,5,'Maria Aguirre Barcenas','Ejecutiva de Cuenta','664 622 555 ext 1105','maguirre@uni-red.com.mx','Ejecutiva que nos brinda atención a Carnes Bacal.',1,1,'2026-05-28 00:19:04'),(7,6,'Lic. Lorena P. Acosta Zavala','Ejecutiva de Cuenta','6646749900','lorena.acosta@telcel.com','Ejecutiva que nos brinda atención a Carnes Bacal.',1,1,'2026-05-28 00:25:51'),(10,4,'Ernesto','Jefe de operacion Sipcons','664 108 6038','ernesto@sipcons.com','Solo hablar para emergencias.',1,1,'2026-05-28 00:38:33'),(11,4,'Chepe','Soporte POS MrTienda','664 120 9235',NULL,'Linea de software MrTienda (puntos de cobro)',0,2,'2026-05-28 00:38:33'),(12,4,'Yoany Oliva','Recepcion / Facturacion','6646300471','yoany@sipcons.com','Enviar correo para solicitar timbres de facturación.',0,3,'2026-05-28 00:38:33');
/*!40000 ALTER TABLE `proveedor_contactos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proveedor_marcas`
--

DROP TABLE IF EXISTS `proveedor_marcas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proveedor_marcas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proveedor_id` int(11) NOT NULL,
  `marca` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_proveedor_marca` (`proveedor_id`,`marca`),
  KEY `idx_proveedor` (`proveedor_id`),
  CONSTRAINT `fk_marca_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proveedor_marcas`
--

LOCK TABLES `proveedor_marcas` WRITE;
/*!40000 ALTER TABLE `proveedor_marcas` DISABLE KEYS */;
INSERT INTO `proveedor_marcas` VALUES (1,5,'Compaq y Sistema Tress'),(3,6,'Apple'),(4,6,'Motorola'),(2,6,'Samsung'),(5,6,'Xiaomi'),(6,6,'ZTE.'),(7,7,'Odoo');
/*!40000 ALTER TABLE `proveedor_marcas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proveedor_tipos_equipo`
--

DROP TABLE IF EXISTS `proveedor_tipos_equipo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proveedor_tipos_equipo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proveedor_id` int(11) NOT NULL,
  `tipo` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_proveedor_tipo` (`proveedor_id`,`tipo`),
  KEY `idx_proveedor` (`proveedor_id`),
  CONSTRAINT `fk_tipo_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proveedor_tipos_equipo`
--

LOCK TABLES `proveedor_tipos_equipo` WRITE;
/*!40000 ALTER TABLE `proveedor_tipos_equipo` DISABLE KEYS */;
INSERT INTO `proveedor_tipos_equipo` VALUES (2,1,'Laptop'),(1,1,'PC'),(3,1,'Perifericos'),(5,2,'Impresora'),(4,2,'PC'),(6,2,'Red'),(8,3,'Red'),(7,3,'Telefonia'),(18,4,'Bascula'),(19,4,'Software de cobro'),(20,4,'Terminal POS'),(12,5,'Software'),(13,6,'Teléfonos celulares'),(14,7,'Software');
/*!40000 ALTER TABLE `proveedor_tipos_equipo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proveedores`
--

DROP TABLE IF EXISTS `proveedores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proveedores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL COMMENT 'Nombre comercial',
  `razon_social` varchar(200) DEFAULT NULL,
  `rfc` varchar(20) DEFAULT NULL,
  `servicio` varchar(255) DEFAULT NULL COMMENT 'Descripcion corta del servicio que ofrece',
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `sitio_web` varchar(200) DEFAULT NULL,
  `horario_atencion` varchar(255) DEFAULT NULL COMMENT 'ej. Lun-Vie 9-18hr',
  `calificacion` tinyint(3) unsigned DEFAULT NULL COMMENT '1-5 estrellas',
  `notas` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_por_id` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_nombre` (`nombre`),
  KEY `idx_activo` (`activo`),
  KEY `fk_proveedor_creador` (`creado_por_id`),
  CONSTRAINT `fk_proveedor_creador` FOREIGN KEY (`creado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proveedores`
--

LOCK TABLES `proveedores` WRITE;
/*!40000 ALTER TABLE `proveedores` DISABLE KEYS */;
INSERT INTO `proveedores` VALUES (1,'Abasteo',NULL,NULL,'Proveedor de tecnologia',NULL,NULL,'i.lozano@abasteo.mx',NULL,'Lun-Vie 9:00-18:00',NULL,'Contacto principal: Alejandro Lozano',1,NULL,'2026-05-22 23:59:33','2026-05-22 23:59:33'),(2,'enetSystem',NULL,NULL,'Soporte tecnico',NULL,'664 385 4983','aldolinares@netsistem.com.mx',NULL,'Lun-Vie 9:00-18:00',NULL,'Contacto principal: Aldo Linares',1,NULL,'2026-05-22 23:59:33','2026-05-22 23:59:33'),(3,'Metrocarrier',NULL,NULL,'Lineas troncales',NULL,'662 555 8912','dsoto@metrocarrier.com.mx',NULL,'Lun-Vie 9:00-18:00',NULL,'Contacto principal: Deyanira Soto',1,NULL,'2026-05-22 23:59:33','2026-05-22 23:59:33'),(4,'Sipcons','Soluciones integrales de pesaje y control','SIP090423453','Punto de cobro y basculas','AV. DE LAS PERLAS 630 PLAYAS DE TIJUANA SECCION PLAYAS CORONADO, TIJUANA, BAJA CALIFORNIA 22504','664-630-0471','info@sipcons.com','https://www.sipcons.com.mx/contacto','Lun-Vie 9:00-18:00',5,'Marcar para problemas con el Mrtienda, basculas, punto de venta o solicitar timbres.',1,NULL,'2026-05-22 23:59:33','2026-05-28 00:38:33'),(5,'Uni-Red','UNI-RED COMPUTACION Y SISTEMAS SA DE CV','URC940202EZ1','Proveedor de Compaq Contabilidad, Nominas etc.','Boulevard Agua Caliente 10470 3-A, Tijuana, B.C. México, C.P. 22420','6646225555','contacto@uni-red.com.mx','https://uni-red.com.mx/','Lun-Vie 8 a 6 pm',5,NULL,1,1,'2026-05-28 00:19:04','2026-05-28 00:19:04'),(6,'Telcel','Radiomóvil Dipsa, S.A. de C.V','RDI841003QJ4','Proveedor de líneas telefónicas empresariales.','Paseo de los Héroes No.10698 Zona Rio, Tijuana BC, CP.22320','800 3630 800','noemi.ballesteros@telcel.com','https://empresas.mitelcel.com/v2/login','Lun-Vie 8 a 6 pm',5,'Anexo datos del portal MTE. \r\nLink: https://empresas.mitelcel.com/v2/login \r\nCorreo registrado:\r\ncfd_bacal@granodeoro.com.mx',1,1,'2026-05-28 00:25:51','2026-05-28 00:25:51'),(7,'Odoo','Odoo S.A,  Odoo México S. de R.L. de C.V','OTE2003102G1','ERP','Blvd. Miguel de Cervantes Saavedra 23, Col. Granada, Miguel Hidalgo, Ciudad de México, CP 11520','+52 33 1930 4495','vags@odoo.com','https://www.odoo.com','Lun-Vie 8 a 6 pm',NULL,NULL,1,1,'2026-05-28 00:30:04','2026-05-28 00:30:04');
/*!40000 ALTER TABLE `proveedores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recordatorios`
--

DROP TABLE IF EXISTS `recordatorios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recordatorios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL COMMENT 'A quién se le envía',
  `titulo` varchar(200) NOT NULL,
  `mensaje` varchar(500) DEFAULT NULL,
  `fecha_envio` datetime NOT NULL COMMENT 'Cuándo enviar',
  `enlace` varchar(255) DEFAULT NULL,
  `entidad` varchar(50) DEFAULT NULL,
  `entidad_id` int(11) DEFAULT NULL,
  `enviado` tinyint(1) NOT NULL DEFAULT 0,
  `enviado_en` timestamp NULL DEFAULT NULL,
  `creado_por_id` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_enviar` (`enviado`,`fecha_envio`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `fk_rec_creador` (`creado_por_id`),
  CONSTRAINT `fk_rec_creador` FOREIGN KEY (`creado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rec_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recordatorios`
--

LOCK TABLES `recordatorios` WRITE;
/*!40000 ALTER TABLE `recordatorios` DISABLE KEYS */;
/*!40000 ALTER TABLE `recordatorios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reglas_asignacion`
--

DROP TABLE IF EXISTS `reglas_asignacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reglas_asignacion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL COMMENT 'Nombre descriptivo de la regla',
  `descripcion` varchar(255) DEFAULT NULL,
  `sucursal_id` int(11) DEFAULT NULL,
  `area_id` int(11) DEFAULT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `tipo_trabajo_id` int(11) DEFAULT NULL,
  `severidad_id` int(11) DEFAULT NULL,
  `asignar_a_id` int(11) NOT NULL,
  `prioridad` int(11) NOT NULL DEFAULT 100 COMMENT 'Menor = se evalúa antes',
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `veces_aplicada` int(11) NOT NULL DEFAULT 0,
  `creado_por_id` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_activa_prioridad` (`activa`,`prioridad`),
  KEY `idx_sucursal` (`sucursal_id`),
  KEY `idx_area` (`area_id`),
  KEY `fk_regla_categoria` (`categoria_id`),
  KEY `fk_regla_tipo` (`tipo_trabajo_id`),
  KEY `fk_regla_severidad` (`severidad_id`),
  KEY `fk_regla_asignar` (`asignar_a_id`),
  KEY `fk_regla_creador` (`creado_por_id`),
  CONSTRAINT `fk_regla_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_regla_asignar` FOREIGN KEY (`asignar_a_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_regla_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_regla_creador` FOREIGN KEY (`creado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_regla_severidad` FOREIGN KEY (`severidad_id`) REFERENCES `severidades` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_regla_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_regla_tipo` FOREIGN KEY (`tipo_trabajo_id`) REFERENCES `tipos_trabajo` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reglas_asignacion`
--

LOCK TABLES `reglas_asignacion` WRITE;
/*!40000 ALTER TABLE `reglas_asignacion` DISABLE KEYS */;
INSERT INTO `reglas_asignacion` VALUES (2,'Urgencia','Tarea con prioridad inmediata.',NULL,NULL,NULL,NULL,1,12,1,1,0,1,'2026-05-27 15:59:29','2026-05-27 15:59:29');
/*!40000 ALTER TABLE `reglas_asignacion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `puede_administrar` tinyint(1) NOT NULL DEFAULT 0,
  `puede_ver_todas_sucursales` tinyint(1) NOT NULL DEFAULT 0,
  `puede_resolver` tinyint(1) NOT NULL DEFAULT 0,
  `puede_crear_solicitud` tinyint(1) NOT NULL DEFAULT 1,
  `puede_ver_reportes` tinyint(1) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Administrador','Control total del sistema, configura todo',1,1,1,1,1,1,'2026-05-20 13:52:18'),(2,'Ingeniero en Sistemas','Atiende y resuelve incidencias en todas las sucursales',0,1,1,1,1,1,'2026-05-20 13:52:18'),(3,'Gerente','Supervisa su sucursal y genera reportes',0,0,0,1,1,1,'2026-05-20 13:52:18'),(4,'Jefe de Área','Crea solicitudes de su área y da seguimiento',0,0,0,1,0,1,'2026-05-20 13:52:18'),(5,'Solo Lectura','Consulta y filtra sin modificar',0,1,0,0,1,1,'2026-05-20 13:52:18');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sesiones`
--

DROP TABLE IF EXISTS `sesiones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sesiones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `session_id` varchar(128) NOT NULL COMMENT 'PHP session_id()',
  `ip` varchar(45) DEFAULT NULL COMMENT 'IPv4 o IPv6',
  `user_agent` varchar(500) DEFAULT NULL,
  `dispositivo` varchar(100) DEFAULT NULL COMMENT 'Dispositivo detectado (Windows, Mac, Android, iPhone, etc)',
  `navegador` varchar(50) DEFAULT NULL COMMENT 'Navegador detectado',
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `motivo_cierre` varchar(100) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `ultima_actividad` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cerrada_en` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_session_id` (`session_id`),
  KEY `idx_usuario_activa` (`usuario_id`,`activa`),
  KEY `idx_creado` (`creado_en`),
  CONSTRAINT `fk_sesion_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sesiones`
--

LOCK TABLES `sesiones` WRITE;
/*!40000 ALTER TABLE `sesiones` DISABLE KEYS */;
INSERT INTO `sesiones` VALUES (1,1,'kf8na2iq9pt6snl8eog01928dl','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','Windows 10/11','Chrome',0,'logout normal','2026-05-25 23:39:32','2026-05-26 18:39:50','2026-05-26 18:39:50'),(2,1,'6bcumu40erfv4dldv8ip05jruu','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','Windows 10/11','Chrome',0,'logout normal','2026-05-26 18:39:55','2026-05-26 19:06:41','2026-05-26 19:06:41'),(3,1,'ob0akav6b67dpptt9kbi7jr1od','192.168.1.54','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','Windows 10/11','Chrome',0,'logout normal','2026-05-26 18:40:16','2026-05-26 18:41:00','2026-05-26 18:41:00'),(4,1,'9sls1gk6tok5u5bqc92sk2g07n','192.168.1.54','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','Windows 10/11','Chrome',1,NULL,'2026-05-26 18:41:19','2026-05-27 04:31:52',NULL),(5,1,'7ocdc5lo6i0nntlonlck323adf','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','Windows 10/11','Chrome',1,NULL,'2026-05-26 19:15:44','2026-05-26 19:55:27',NULL),(6,1,'lglo6kpmfla1jb5g0dfq5mei2t','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','Windows 10/11','Chrome',1,NULL,'2026-05-26 20:37:43','2026-05-27 04:27:55',NULL),(7,1,'dv7e6r12e236748vhe933dch7u','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','Windows 10/11','Chrome',0,'logout normal','2026-05-27 15:58:02','2026-05-27 16:27:33','2026-05-27 16:27:33'),(8,1,'oqdrd0dli6tuqcceani896cg19','192.168.1.20','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','Windows 10/11','Chrome',1,NULL,'2026-05-27 16:08:12','2026-05-27 16:08:27',NULL),(9,12,'3o6i9jdsjul9q4rp4f4p8ddk7h','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','Windows 10/11','Chrome',0,'logout normal','2026-05-27 16:28:03','2026-05-27 16:28:28','2026-05-27 16:28:28'),(10,1,'hkj2p4a2ku89f1of7avg6panb1','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','Windows 10/11','Chrome',0,'logout normal','2026-05-27 16:36:57','2026-05-27 16:46:28','2026-05-27 16:46:28'),(11,1,'34sse4aoi7c4hq4j7hqbaa9q66','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','Windows 10/11','Chrome',0,'logout normal','2026-05-27 16:46:51','2026-05-27 17:14:41','2026-05-27 17:14:41'),(12,12,'edhppcd5ncq6d6ifn7j4dpjsfp','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','Windows 10/11','Chrome',0,'logout normal','2026-05-27 17:20:44','2026-05-27 21:04:55','2026-05-27 21:04:55'),(13,1,'kk2g512ebdut2bh61dkr6gek90','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','Windows 10/11','Chrome',1,NULL,'2026-05-27 21:05:18','2026-05-27 23:47:07',NULL),(14,1,'on6dr2b3fet04kvujrfk9ektrm','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','Windows 10/11','Chrome',0,'logout normal','2026-05-28 00:00:14','2026-05-28 19:26:53','2026-05-28 19:26:53'),(15,1,'cva067j29jc8levp8014ttac3t','100.85.54.82','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','Windows 10/11','Chrome',0,'logout normal','2026-05-28 18:01:37','2026-05-28 18:04:18','2026-05-28 18:04:18'),(16,15,'fdo1rin2mrfkvld9e7fjmnrul1','100.85.54.82','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','Windows 10/11','Chrome',0,'logout normal','2026-05-28 18:04:41','2026-05-28 18:05:24','2026-05-28 18:05:24'),(17,13,'i63512qtk0i6830t3p1iavjqoc','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','Windows 10/11','Chrome',1,NULL,'2026-05-28 19:07:34','2026-05-28 19:34:55',NULL),(18,12,'1lhk49b4tgf9qejn1ctrnp3cqa','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','Windows 10/11','Chrome',0,'logout normal','2026-05-28 22:41:00','2026-05-28 23:21:36','2026-05-28 23:21:36'),(19,12,'jtjvsad12l2nvsigue9lfm9s6m','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','Windows 10/11','Chrome',0,'logout normal','2026-05-29 01:16:00','2026-05-29 18:50:46','2026-05-29 18:50:46'),(20,12,'i1lkjnk18susqhukd7hdj7j2in','192.168.1.136','Mozilla/5.0 (iPhone; CPU iPhone OS 26_4_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/148.0.7778.166 Mobile/15E148 Safari/604.1','iPhone','Safari',1,NULL,'2026-05-29 01:17:23','2026-05-29 01:21:59',NULL),(21,1,'sd65bpj7pf41i4v7sgtiemcous','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','Windows 10/11','Chrome',0,'logout normal','2026-05-29 18:51:14','2026-05-29 19:59:04','2026-05-29 19:59:04'),(22,13,'a7jifjeuqfpig7gr13ajt80p91','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','Windows 10/11','Chrome',0,'logout normal','2026-05-29 21:38:53','2026-05-29 21:44:54','2026-05-29 21:44:54'),(23,1,'hrrr5qfcr1naklpffisler9c70','192.168.1.152','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','Windows 10/11','Chrome',0,'logout normal','2026-05-29 21:45:29','2026-05-31 17:03:06','2026-05-31 17:03:06'),(24,1,'cqrf14q9pf3s9n62o8vhf4d9ot','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','Windows 10/11','Chrome',0,'logout normal','2026-05-31 14:44:52','2026-05-31 17:03:51','2026-05-31 17:03:51'),(25,1,'dbeb52v3p85o7knd5oatjs8o4n','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','Windows 10/11','Chrome',1,NULL,'2026-05-31 17:31:44','2026-05-31 17:39:27',NULL),(26,1,'02ksl0jbf8pejc93a6oo19t9su','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','Windows 10/11','Chrome',1,NULL,'2026-05-31 19:30:46','2026-05-31 20:06:03',NULL);
/*!40000 ALTER TABLE `sesiones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `severidades`
--

DROP TABLE IF EXISTS `severidades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `severidades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `nivel` int(11) NOT NULL,
  `color` varchar(20) NOT NULL DEFAULT '#6B7280',
  `sla_horas` int(11) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`),
  UNIQUE KEY `nivel` (`nivel`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `severidades`
--

LOCK TABLES `severidades` WRITE;
/*!40000 ALTER TABLE `severidades` DISABLE KEYS */;
INSERT INTO `severidades` VALUES (1,'Crítica',1,'#DC2626',2,'Operación detenida, requiere atención inmediata',1),(2,'Alta',2,'#EA580C',8,'Afectación importante a la operación',1),(3,'Media',3,'#D97706',24,'Afectación parcial, no detiene la operación',1),(4,'Baja',4,'#16A34A',72,'Sin afectación operativa, mejora o solicitud',1);
/*!40000 ALTER TABLE `severidades` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subcategorias`
--

DROP TABLE IF EXISTS `subcategorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subcategorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `categoria_id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_categoria_nombre` (`categoria_id`,`nombre`),
  CONSTRAINT `subcategorias_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subcategorias`
--

LOCK TABLES `subcategorias` WRITE;
/*!40000 ALTER TABLE `subcategorias` DISABLE KEYS */;
INSERT INTO `subcategorias` VALUES (1,1,'PC',NULL,1,'2026-05-20 13:52:18'),(2,1,'Laptop',NULL,1,'2026-05-20 13:52:18'),(3,1,'Periféricos',NULL,1,'2026-05-20 13:52:18'),(4,1,'Disco duro',NULL,1,'2026-05-20 13:52:18'),(5,2,'Sistema operativo',NULL,1,'2026-05-20 13:52:18'),(6,2,'Office',NULL,1,'2026-05-20 13:52:18'),(7,2,'Sistema de punto de venta',NULL,1,'2026-05-20 13:52:18'),(8,2,'Antivirus',NULL,1,'2026-05-20 13:52:18'),(9,3,'WiFi',NULL,1,'2026-05-20 13:52:18'),(10,3,'Cableado',NULL,1,'2026-05-20 13:52:18'),(11,3,'Internet',NULL,1,'2026-05-20 13:52:18'),(12,3,'VPN',NULL,1,'2026-05-20 13:52:18'),(13,10,'Contraseña',NULL,1,'2026-05-20 13:52:18'),(14,10,'Creación de cuenta',NULL,1,'2026-05-20 13:52:18'),(15,10,'Permisos',NULL,1,'2026-05-20 13:52:18'),(16,9,'Tóner / cartuchos',NULL,1,'2026-05-20 13:52:18'),(17,9,'Atasco de papel',NULL,1,'2026-05-20 13:52:18'),(18,9,'Configuración',NULL,1,'2026-05-20 13:52:18'),(19,7,'Instalación',NULL,1,'2026-05-21 14:08:57');
/*!40000 ALTER TABLE `subcategorias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sucursal_plantas`
--

DROP TABLE IF EXISTS `sucursal_plantas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sucursal_plantas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sucursal_id` int(11) NOT NULL,
  `nombre` varchar(80) NOT NULL COMMENT 'Ej: Planta baja, Piso 1, Bodega',
  `orden` int(11) NOT NULL DEFAULT 0 COMMENT 'Para ordenar las pestañas',
  `plano_url` varchar(255) DEFAULT NULL,
  `plano_subido_en` timestamp NULL DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sucursal` (`sucursal_id`,`orden`),
  CONSTRAINT `fk_planta_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sucursal_plantas`
--

LOCK TABLES `sucursal_plantas` WRITE;
/*!40000 ALTER TABLE `sucursal_plantas` DISABLE KEYS */;
INSERT INTO `sucursal_plantas` VALUES (1,1,'Tienda',1,'uploads/planos/plano_p1_1779914597.png','2026-05-27 20:43:17',1,'2026-05-25 23:40:44'),(2,1,'Oficinas',2,'uploads/planos/plano_p2_1779914615.png','2026-05-27 20:43:35',1,'2026-05-25 23:41:04'),(3,1,'3er Piso',3,'uploads/planos/plano_p3_1779914625.png','2026-05-27 20:43:45',1,'2026-05-25 23:41:09');
/*!40000 ALTER TABLE `sucursal_plantas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sucursales`
--

DROP TABLE IF EXISTS `sucursales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sucursales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `responsable` varchar(150) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`),
  UNIQUE KEY `codigo` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sucursales`
--

LOCK TABLES `sucursales` WRITE;
/*!40000 ALTER TABLE `sucursales` DISABLE KEYS */;
INSERT INTO `sucursales` VALUES (1,'Bacal','BAC','Av. Cruz del Sur 2025, Fracc. Las Huertas 3ra. Sección, Tijuana','(664) 972 06 31','Alberto Martinez',1,'2026-05-20 13:52:18','2026-05-25 09:35:53'),(2,'Ferias','FER','De las Ferias 84, Lomas Hipodromo, 22030 Tijuana, B.C.','664 104 1093','Omar',1,'2026-05-20 13:52:18','2026-05-25 09:35:41');
/*!40000 ALTER TABLE `sucursales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tipos_trabajo`
--

DROP TABLE IF EXISTS `tipos_trabajo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tipos_trabajo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `color` varchar(20) DEFAULT '#6B7280',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tipos_trabajo`
--

LOCK TABLES `tipos_trabajo` WRITE;
/*!40000 ALTER TABLE `tipos_trabajo` DISABLE KEYS */;
INSERT INTO `tipos_trabajo` VALUES (1,'PC',NULL,'#DC2626',1,'2026-05-20 13:52:18'),(2,'Alarmas',NULL,'#EA580C',1,'2026-05-20 13:52:18'),(3,'Cámaras',NULL,'#7C3AED',1,'2026-05-20 13:52:18'),(4,'Red',NULL,'#16A34A',1,'2026-05-20 13:52:18'),(5,'Impresora',NULL,'#6B7280',1,'2026-05-20 13:52:18'),(6,'Punto de Venta',NULL,'#D97706',1,'2026-05-20 13:52:18'),(7,'Telefonía',NULL,'#2563EB',1,'2026-05-20 13:52:18'),(8,'Mantenimiento Preventivo',NULL,'#0EA5E9',1,'2026-05-20 13:52:18'),(9,'Mantenimiento Correctivo',NULL,'#DC2626',1,'2026-05-20 13:52:18'),(10,'Instalación',NULL,'#22C55E',1,'2026-05-20 13:52:18'),(11,'Actualización',NULL,'#9333EA',1,'2026-05-20 13:52:18'),(12,'Respaldo',NULL,'#6B7280',1,'2026-05-20 13:52:18'),(13,'Capacitación',NULL,'#0EA5E9',1,'2026-05-20 13:52:18'),(14,'Otro',NULL,'#6B7280',1,'2026-05-20 13:52:18');
/*!40000 ALTER TABLE `tipos_trabajo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `nombre_completo` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL COMMENT 'Ruta relativa de la foto de perfil',
  `pagina_inicio_preferida` varchar(100) DEFAULT 'dashboard.php',
  `telefono` varchar(50) DEFAULT NULL,
  `rol_id` int(11) NOT NULL,
  `sucursal_id` int(11) DEFAULT NULL,
  `area_id` int(11) DEFAULT NULL,
  `puesto` varchar(100) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `ultimo_login` datetime DEFAULT NULL,
  `intentos_fallidos` int(11) NOT NULL DEFAULT 0,
  `bloqueado_hasta` datetime DEFAULT NULL,
  `debe_cambiar_password` tinyint(1) NOT NULL DEFAULT 0,
  `creado_en` datetime DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`),
  KEY `rol_id` (`rol_id`),
  KEY `idx_usuario_activo` (`usuario`,`activo`),
  KEY `idx_sucursal` (`sucursal_id`),
  KEY `idx_area` (`area_id`),
  CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `usuarios_ibfk_2` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE SET NULL,
  CONSTRAINT `usuarios_ibfk_3` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'admin','$2y$10$BVwhlXgJ8.fU8/y00Wh8ueWz2puyUKuadwYyahdi.RgoJKsVIIs7y','Administrador del Sistema',NULL,NULL,'dashboard.php',NULL,1,NULL,NULL,'Administrador',NULL,1,'2026-05-31 12:30:46',0,NULL,0,'2026-05-20 13:52:18','2026-05-31 12:30:46'),(12,'lfrodriguez','$2y$10$yRtmTpIZgznOBFpr3HVr2edjrPnmzUIywDFTajwJiyDu.nMdArLv.','Luis Fernando Rodriguez Cruz','lfrodriguez@granodeoro.com.mx',NULL,'dashboard.php','6642382390',1,NULL,19,'Encargado de Sistemas',NULL,1,'2026-05-28 18:17:22',0,NULL,0,'2026-05-25 16:43:16','2026-05-28 18:17:22'),(13,'aegarcia','$2y$10$ioqNtG7hGzzmAxmWVUzPdugP17AN./esQK7m9Zv12if5ZBfGCGCGq','Abraham Ezequiel Garcia Campos','aegarcia@granodeoro.com.mx',NULL,'dashboard.php','6641645154',2,NULL,19,'Sistemas',NULL,1,'2026-05-29 14:38:53',0,NULL,0,'2026-05-25 16:44:32','2026-05-29 14:38:53'),(14,'jacruz','$2y$10$PBJs54clmaYsZ77KNW9xT.xY7a73y2SjLsNttKATTIIRYT6xVdjQW','Jorge Antonio Cruz Lares','jacruz@granodeoro.com.mx',NULL,'dashboard.php','6645900659',2,1,19,'Sistemas',NULL,1,NULL,0,NULL,1,'2026-05-25 16:46:38','2026-05-25 16:46:38'),(15,'jlcorral','$2y$10$euA6ADn0PM9YDCblZ5wI0.ZySqTar8RZW/SC.00qCdsilYvItyrcm','Jose Luis Corral Terrazas','jlcorral@granodeoro.com.mx',NULL,'dashboard.php',NULL,1,NULL,NULL,'Dueño',NULL,1,'2026-05-28 11:04:41',0,NULL,0,'2026-05-28 11:03:00','2026-05-28 11:05:02'),(16,'ovazquez','$2y$10$YgDfgUPKcuoMBMKiBWEWQe3ML0K6YpC2.gdst02slGG0FeNZIXRZq','Omar Vazquez','ovazquez@granodeoro.com.mx',NULL,'dashboard.php',NULL,3,2,NULL,'Gerente de Sucursal',NULL,1,NULL,0,NULL,1,'2026-05-28 11:04:06','2026-05-28 11:04:06');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `v_estadisticas_sucursal`
--

DROP TABLE IF EXISTS `v_estadisticas_sucursal`;
/*!50001 DROP VIEW IF EXISTS `v_estadisticas_sucursal`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_estadisticas_sucursal` AS SELECT
 1 AS `sucursal_id`,
  1 AS `sucursal_nombre`,
  1 AS `total_incidencias`,
  1 AS `abiertas`,
  1 AS `cerradas`,
  1 AS `reincidencias`,
  1 AS `criticas_abiertas`,
  1 AS `tiempo_promedio_resolucion_min` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_incidencias_completas`
--

DROP TABLE IF EXISTS `v_incidencias_completas`;
/*!50001 DROP VIEW IF EXISTS `v_incidencias_completas`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_incidencias_completas` AS SELECT
 1 AS `id`,
  1 AS `folio`,
  1 AS `titulo`,
  1 AS `descripcion`,
  1 AS `fecha_evento`,
  1 AS `fecha_atencion`,
  1 AS `fecha_resolucion`,
  1 AS `fecha_cierre`,
  1 AS `tiempo_respuesta_min`,
  1 AS `tiempo_resolucion_min`,
  1 AS `es_reincidencia`,
  1 AS `veces_recurrida`,
  1 AS `incidencia_padre_id`,
  1 AS `solucion`,
  1 AS `recomendaciones`,
  1 AS `causa_raiz`,
  1 AS `sla_cumplido`,
  1 AS `creado_en`,
  1 AS `sucursal_id`,
  1 AS `sucursal_nombre`,
  1 AS `sucursal_codigo`,
  1 AS `area_id`,
  1 AS `area_nombre`,
  1 AS `area_color`,
  1 AS `categoria_id`,
  1 AS `categoria_nombre`,
  1 AS `categoria_color`,
  1 AS `subcategoria_nombre`,
  1 AS `tipo_trabajo_id`,
  1 AS `tipo_trabajo_nombre`,
  1 AS `tipo_trabajo_color`,
  1 AS `severidad_id`,
  1 AS `severidad_nombre`,
  1 AS `severidad_color`,
  1 AS `severidad_nivel`,
  1 AS `estado_id`,
  1 AS `estado_nombre`,
  1 AS `estado_color`,
  1 AS `estado_es_final`,
  1 AS `equipo_id`,
  1 AS `equipo_codigo`,
  1 AS `equipo_nombre`,
  1 AS `reportado_por_id`,
  1 AS `reportado_por_nombre`,
  1 AS `reportante_nombre`,
  1 AS `asignado_a_id`,
  1 AS `asignado_a_nombre`,
  1 AS `resuelto_por_id`,
  1 AS `resuelto_por_nombre` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `vault_accesos`
--

DROP TABLE IF EXISTS `vault_accesos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vault_accesos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entrada_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `accion` enum('ver_password','copiar_password','ver_entrada') NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_entrada` (`entrada_id`,`creado_en`),
  KEY `idx_usuario` (`usuario_id`,`creado_en`),
  CONSTRAINT `fk_acc_entrada` FOREIGN KEY (`entrada_id`) REFERENCES `vault_entradas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_acc_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vault_accesos`
--

LOCK TABLES `vault_accesos` WRITE;
/*!40000 ALTER TABLE `vault_accesos` DISABLE KEYS */;
/*!40000 ALTER TABLE `vault_accesos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vault_categorias`
--

DROP TABLE IF EXISTS `vault_categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vault_categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `familia` varchar(60) NOT NULL COMMENT 'Grupo visual: Acceso, Infraestructura, etc.',
  `familia_orden` int(11) NOT NULL DEFAULT 0,
  `nombre` varchar(100) NOT NULL,
  `icono` varchar(50) DEFAULT 'folder',
  `color` varchar(20) DEFAULT '#71717a',
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_familia` (`familia`,`orden`),
  KEY `idx_orden` (`orden`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vault_categorias`
--

LOCK TABLES `vault_categorias` WRITE;
/*!40000 ALTER TABLE `vault_categorias` DISABLE KEYS */;
INSERT INTO `vault_categorias` VALUES (1,'Acceso y seguridad',1,'Acceso a sistemas','flame','#DC2626',1,1,'2026-05-26 15:32:41'),(2,'Acceso y seguridad',1,'Cuentas y servicios web','globe','#0EA5E9',2,1,'2026-05-26 15:32:41'),(3,'Acceso y seguridad',1,'Credenciales sensibles','lock-keyhole','#7C2D12',3,1,'2026-05-26 15:32:41'),(4,'Infraestructura',2,'Red e infraestructura','network','#0891B2',10,1,'2026-05-26 15:32:41'),(5,'Infraestructura',2,'Servidores','server','#0F766E',11,1,'2026-05-26 15:32:41'),(6,'Infraestructura',2,'Backups y respaldos','cloud-download','#7C3AED',12,1,'2026-05-26 15:32:41'),(7,'Software e instaladores',3,'Sistemas operativos','disc','#1D4ED8',20,1,'2026-05-26 15:32:41'),(8,'Software e instaladores',3,'Instaladores de software','package','#7C3AED',21,1,'2026-05-26 15:32:41'),(9,'Software e instaladores',3,'Drivers','cpu','#9333EA',22,1,'2026-05-26 15:32:41'),(10,'Software e instaladores',3,'Herramientas y utilidades','wrench','#6366F1',23,1,'2026-05-26 15:32:41'),(11,'Software e instaladores',3,'Scripts y automatización','terminal','#0F172A',24,1,'2026-05-26 15:32:41'),(12,'Acceso remoto',4,'Escritorios remotos','monitor','#16A34A',30,1,'2026-05-26 15:32:41'),(13,'Acceso remoto',4,'VPN','shield-check','#059669',31,1,'2026-05-26 15:32:41'),(14,'Acceso remoto',4,'Accesos externos','log-in','#65A30D',32,1,'2026-05-26 15:32:41'),(15,'Documentación operativa',5,'Procedimientos','clipboard-list','#D97706',40,1,'2026-05-26 15:32:41'),(16,'Documentación operativa',5,'Manuales internos','book','#EA580C',41,1,'2026-05-26 15:32:41'),(17,'Documentación operativa',5,'Manuales de fabricante','book-open','#C2410C',42,1,'2026-05-26 15:32:41'),(18,'Documentación operativa',5,'Guías y tutoriales','graduation-cap','#92400E',43,1,'2026-05-26 15:32:41'),(19,'Diagramas y planos',6,'Diagramas de red','workflow','#0284C7',50,1,'2026-05-26 15:32:41'),(20,'Diagramas y planos',6,'Planos de oficina/tienda','map','#0369A1',51,1,'2026-05-26 15:32:41'),(21,'Diagramas y planos',6,'Diagramas eléctricos','zap','#F59E0B',52,1,'2026-05-26 15:32:41'),(22,'Diagramas y planos',6,'Diagramas de rack','layout-grid','#475569',53,1,'2026-05-26 15:32:41'),(23,'Datos y reportes',7,'Reportes y plantillas','file-spreadsheet','#16A34A',60,1,'2026-05-26 15:32:41'),(24,'Datos y reportes',7,'Directorios','contact','#15803D',61,1,'2026-05-26 15:32:41'),(25,'Datos y reportes',7,'Inventarios','boxes','#65A30D',62,1,'2026-05-26 15:32:41'),(26,'Datos y reportes',7,'Bases de datos','database','#1E40AF',63,1,'2026-05-26 15:32:41'),(27,'Legal y administrativo',8,'Documentos legales','scale','#7C2D12',70,1,'2026-05-26 15:32:41'),(28,'Legal y administrativo',8,'Licencias de software','key-round','#A16207',71,1,'2026-05-26 15:32:41'),(29,'Legal y administrativo',8,'Certificados','badge-check','#15803D',72,1,'2026-05-26 15:32:41'),(30,'Legal y administrativo',8,'Renovaciones','calendar-clock','#B45309',73,1,'2026-05-26 15:32:41'),(31,'Equipos y configuraciones',9,'Configuraciones específicas','sliders','#52525B',80,1,'2026-05-26 15:32:41'),(32,'Equipos y configuraciones',9,'Parámetros del sistema','settings-2','#3F3F46',81,1,'2026-05-26 15:32:41'),(33,'Equipos y configuraciones',9,'IPs y MAC addresses','binary','#27272A',82,1,'2026-05-26 15:32:41'),(34,'General',10,'Otros / Archivado','archive','#71717A',90,1,'2026-05-26 15:32:41'),(35,'General',10,'Acceso rápido','star','#EAB308',91,1,'2026-05-26 15:32:41');
/*!40000 ALTER TABLE `vault_categorias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vault_entradas`
--

DROP TABLE IF EXISTS `vault_entradas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vault_entradas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `categoria_id` int(11) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `url` varchar(500) DEFAULT NULL,
  `usuario` varchar(200) DEFAULT NULL,
  `password_cifrado` text DEFAULT NULL COMMENT 'AES-256 encrypted',
  `notas` text DEFAULT NULL COMMENT 'Markdown libre',
  `archivos` text DEFAULT NULL COMMENT 'Rutas UNC u observaciones, editable',
  `version_build` varchar(100) DEFAULT NULL COMMENT 'Para instaladores/drivers',
  `vencimiento` date DEFAULT NULL COMMENT 'Para licencias/certificados',
  `tags` varchar(500) DEFAULT NULL COMMENT 'Coma-separadas',
  `sucursal_id` int(11) DEFAULT NULL COMMENT 'NULL = todas / N/A',
  `sensibilidad` enum('normal','alta','critica') NOT NULL DEFAULT 'normal',
  `permisos_tipo` enum('todos','rol','sucursal','usuarios','admin') NOT NULL DEFAULT 'admin' COMMENT 'todos=visible para todos / rol=por roles_ids / sucursal=por sucursales_ids / usuarios=lista específica / admin=solo admin',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_por_id` int(11) DEFAULT NULL,
  `actualizado_por_id` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_categoria` (`categoria_id`),
  KEY `idx_sucursal` (`sucursal_id`),
  KEY `idx_nombre` (`nombre`),
  KEY `idx_activo` (`activo`),
  KEY `fk_vault_creador` (`creado_por_id`),
  KEY `fk_vault_actualizador` (`actualizado_por_id`),
  CONSTRAINT `fk_vault_actualizador` FOREIGN KEY (`actualizado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_vault_cat` FOREIGN KEY (`categoria_id`) REFERENCES `vault_categorias` (`id`),
  CONSTRAINT `fk_vault_creador` FOREIGN KEY (`creado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_vault_suc` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vault_entradas`
--

LOCK TABLES `vault_entradas` WRITE;
/*!40000 ALTER TABLE `vault_entradas` DISABLE KEYS */;
INSERT INTO `vault_entradas` VALUES (1,1,'Atlantis - Bacal',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'alta','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(2,1,'Atlantis - Ferias',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'alta','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(3,1,'NASS',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'alta','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(4,1,'MrTienda',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'alta','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(5,1,'MagicInfo',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'normal','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(6,1,'IntelligentAnalysis',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'normal','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(7,1,'MPsoft',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'normal','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(8,2,'Cuentas Google corporativas',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'critica','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(9,2,'Office 365 / Microsoft',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'critica','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(10,4,'Sonicwall - Bacal',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'critica','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(11,4,'Sonicwall - Ferias',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'critica','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(12,15,'Corte de cajero',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'normal','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(13,15,'Configurar MrTienda Adicional',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'normal','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(14,15,'PLU SCAL Manager - báscula',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'normal','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(15,15,'Error Factura Global - solución',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'normal','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(16,7,'Windows 11 Pro ISO',NULL,NULL,NULL,NULL,'Pendiente: actualizar ruta UNC del ISO',NULL,NULL,NULL,NULL,'normal','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(17,7,'Windows 10 Pro ISO',NULL,NULL,NULL,NULL,'Pendiente: actualizar ruta UNC del ISO',NULL,NULL,NULL,NULL,'normal','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(18,8,'Office 2019 Profesional',NULL,NULL,NULL,NULL,NULL,'2019',NULL,NULL,NULL,'normal','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(19,8,'MrTienda - instalador',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'normal','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(20,11,'SolucionadorRedes.bat',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'normal','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(21,19,'Mapa Red GDO',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'alta','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(22,20,'Layout oficina',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'normal','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(23,20,'Layout tienda',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'normal','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(24,20,'Layout 3er piso',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'normal','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(25,23,'Reporte de líneas',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'normal','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(26,23,'Macro para Nóminas',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'normal','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(27,23,'Calendario de Mantenimiento',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'normal','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(28,23,'Reporte de Mantenimientos Cámaras',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'normal','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(29,24,'Directorio de correos y extensiones',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'normal','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(30,24,'Directorio de Usuarios',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'normal','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(31,25,'Inventario de Básculas',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'normal','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(32,25,'Inventario de Cargadores',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'normal','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(33,25,'Configuración Rack',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'normal','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(34,27,'Representante y poderes Bacal',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'critica','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41'),(35,31,'Configuración Sonicwall',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'critica','admin',1,NULL,NULL,'2026-05-26 15:32:41','2026-05-26 15:32:41');
/*!40000 ALTER TABLE `vault_entradas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vault_favoritos`
--

DROP TABLE IF EXISTS `vault_favoritos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vault_favoritos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entrada_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_entrada_usuario` (`entrada_id`,`usuario_id`),
  KEY `fk_fav_usuario` (`usuario_id`),
  CONSTRAINT `fk_fav_entrada` FOREIGN KEY (`entrada_id`) REFERENCES `vault_entradas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fav_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vault_favoritos`
--

LOCK TABLES `vault_favoritos` WRITE;
/*!40000 ALTER TABLE `vault_favoritos` DISABLE KEYS */;
/*!40000 ALTER TABLE `vault_favoritos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vault_historial`
--

DROP TABLE IF EXISTS `vault_historial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vault_historial` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entrada_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `accion` enum('crear','editar','eliminar','password_cambiada','permisos_cambiados') NOT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_entrada` (`entrada_id`,`creado_en`),
  KEY `fk_hist_usuario` (`usuario_id`),
  CONSTRAINT `fk_hist_entrada` FOREIGN KEY (`entrada_id`) REFERENCES `vault_entradas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hist_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vault_historial`
--

LOCK TABLES `vault_historial` WRITE;
/*!40000 ALTER TABLE `vault_historial` DISABLE KEYS */;
/*!40000 ALTER TABLE `vault_historial` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vault_permisos`
--

DROP TABLE IF EXISTS `vault_permisos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vault_permisos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entrada_id` int(11) NOT NULL,
  `tipo` enum('rol','usuario','sucursal') NOT NULL,
  `referencia_id` int(11) NOT NULL COMMENT 'ID del rol, usuario o sucursal',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_entrada_tipo_ref` (`entrada_id`,`tipo`,`referencia_id`),
  KEY `idx_entrada` (`entrada_id`),
  CONSTRAINT `fk_perm_entrada` FOREIGN KEY (`entrada_id`) REFERENCES `vault_entradas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vault_permisos`
--

LOCK TABLES `vault_permisos` WRITE;
/*!40000 ALTER TABLE `vault_permisos` DISABLE KEYS */;
/*!40000 ALTER TABLE `vault_permisos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'carnes_bacal'
--

--
-- Dumping routines for database 'carnes_bacal'
--

--
-- Final view structure for view `v_estadisticas_sucursal`
--

/*!50001 DROP VIEW IF EXISTS `v_estadisticas_sucursal`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_estadisticas_sucursal` AS select `s`.`id` AS `sucursal_id`,`s`.`nombre` AS `sucursal_nombre`,count(`i`.`id`) AS `total_incidencias`,sum(case when `e`.`es_final` = 0 then 1 else 0 end) AS `abiertas`,sum(case when `e`.`es_final` = 1 then 1 else 0 end) AS `cerradas`,sum(case when `i`.`es_reincidencia` = 1 then 1 else 0 end) AS `reincidencias`,sum(case when `sev`.`nivel` = 1 and `e`.`es_final` = 0 then 1 else 0 end) AS `criticas_abiertas`,avg(`i`.`tiempo_resolucion_min`) AS `tiempo_promedio_resolucion_min` from (((`sucursales` `s` left join `incidencias` `i` on(`i`.`sucursal_id` = `s`.`id`)) left join `estados` `e` on(`i`.`estado_id` = `e`.`id`)) left join `severidades` `sev` on(`i`.`severidad_id` = `sev`.`id`)) group by `s`.`id`,`s`.`nombre` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_incidencias_completas`
--

/*!50001 DROP VIEW IF EXISTS `v_incidencias_completas`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_incidencias_completas` AS select `i`.`id` AS `id`,`i`.`folio` AS `folio`,`i`.`titulo` AS `titulo`,`i`.`descripcion` AS `descripcion`,`i`.`fecha_evento` AS `fecha_evento`,`i`.`fecha_atencion` AS `fecha_atencion`,`i`.`fecha_resolucion` AS `fecha_resolucion`,`i`.`fecha_cierre` AS `fecha_cierre`,`i`.`tiempo_respuesta_min` AS `tiempo_respuesta_min`,`i`.`tiempo_resolucion_min` AS `tiempo_resolucion_min`,`i`.`es_reincidencia` AS `es_reincidencia`,`i`.`veces_recurrida` AS `veces_recurrida`,`i`.`incidencia_padre_id` AS `incidencia_padre_id`,`i`.`solucion` AS `solucion`,`i`.`recomendaciones` AS `recomendaciones`,`i`.`causa_raiz` AS `causa_raiz`,`i`.`sla_cumplido` AS `sla_cumplido`,`i`.`creado_en` AS `creado_en`,`s`.`id` AS `sucursal_id`,`s`.`nombre` AS `sucursal_nombre`,`s`.`codigo` AS `sucursal_codigo`,`a`.`id` AS `area_id`,`a`.`nombre` AS `area_nombre`,`a`.`color` AS `area_color`,`c`.`id` AS `categoria_id`,`c`.`nombre` AS `categoria_nombre`,`c`.`color` AS `categoria_color`,`sc`.`nombre` AS `subcategoria_nombre`,`tt`.`id` AS `tipo_trabajo_id`,`tt`.`nombre` AS `tipo_trabajo_nombre`,`tt`.`color` AS `tipo_trabajo_color`,`sev`.`id` AS `severidad_id`,`sev`.`nombre` AS `severidad_nombre`,`sev`.`color` AS `severidad_color`,`sev`.`nivel` AS `severidad_nivel`,`e`.`id` AS `estado_id`,`e`.`nombre` AS `estado_nombre`,`e`.`color` AS `estado_color`,`e`.`es_final` AS `estado_es_final`,`eq`.`id` AS `equipo_id`,`eq`.`codigo_inventario` AS `equipo_codigo`,`eq`.`nombre` AS `equipo_nombre`,`rep`.`id` AS `reportado_por_id`,`rep`.`nombre_completo` AS `reportado_por_nombre`,`i`.`reportante_nombre` AS `reportante_nombre`,`asig`.`id` AS `asignado_a_id`,`asig`.`nombre_completo` AS `asignado_a_nombre`,`res`.`id` AS `resuelto_por_id`,`res`.`nombre_completo` AS `resuelto_por_nombre` from (((((((((((`incidencias` `i` left join `sucursales` `s` on(`i`.`sucursal_id` = `s`.`id`)) left join `areas` `a` on(`i`.`area_id` = `a`.`id`)) left join `categorias` `c` on(`i`.`categoria_id` = `c`.`id`)) left join `subcategorias` `sc` on(`i`.`subcategoria_id` = `sc`.`id`)) left join `tipos_trabajo` `tt` on(`i`.`tipo_trabajo_id` = `tt`.`id`)) left join `severidades` `sev` on(`i`.`severidad_id` = `sev`.`id`)) left join `estados` `e` on(`i`.`estado_id` = `e`.`id`)) left join `equipos` `eq` on(`i`.`equipo_id` = `eq`.`id`)) left join `usuarios` `rep` on(`i`.`reportado_por_id` = `rep`.`id`)) left join `usuarios` `asig` on(`i`.`asignado_a_id` = `asig`.`id`)) left join `usuarios` `res` on(`i`.`resuelto_por_id` = `res`.`id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-31 13:06:04
