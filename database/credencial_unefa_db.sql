-- ========================================================
-- SISTEMA UNIFICADO DE CREDENCIAL Y ASISTENCIA DIGITAL UNEFA
-- Base de Datos: credencial_unefa_db
-- ========================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "-04:00";
SET FOREIGN_KEY_CHECKS = 0;
-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: credencial_unefa_db
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
-- Table structure for table `asistencia`
--

DROP TABLE IF EXISTS `asistencia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asistencia` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_sesion` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `estado_asistencia` enum('Presente','Ausente','Justificado','Tardio') NOT NULL DEFAULT 'Presente',
  `metodo_registro` enum('QR','Manual') NOT NULL DEFAULT 'QR',
  `registrado_por` int(11) DEFAULT NULL,
  `observacion` varchar(255) DEFAULT NULL,
  `hora_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_sesion_estudiante` (`id_sesion`,`id_estudiante`),
  KEY `id_estudiante` (`id_estudiante`),
  CONSTRAINT `asistencia_est_fk` FOREIGN KEY (`id_estudiante`) REFERENCES `usuario` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asistencia_sesion_fk` FOREIGN KEY (`id_sesion`) REFERENCES `sesion_clase` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `asistencia`
--

LOCK TABLES `asistencia` WRITE;
/*!40000 ALTER TABLE `asistencia` DISABLE KEYS */;
/*!40000 ALTER TABLE `asistencia` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bitacora`
--

DROP TABLE IF EXISTS `bitacora`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bitacora` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) DEFAULT NULL,
  `accion` varchar(100) NOT NULL,
  `detalle` text DEFAULT NULL,
  `direccion_ip` varchar(45) DEFAULT NULL,
  `fecha_hora` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `id_usuario` (`id_usuario`),
  KEY `fecha_hora` (`fecha_hora`),
  CONSTRAINT `bitacora_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=115 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bitacora`
--

LOCK TABLES `bitacora` WRITE;
/*!40000 ALTER TABLE `bitacora` DISABLE KEYS */;
INSERT INTO `bitacora` VALUES (1,1,'Inicializacion del Sistema','Base de datos unificada inicializada con los 6 roles jerarquicos y carrera Ingenieria de Sistemas','127.0.0.1','2026-10-23 23:11:12'),(3,3,'Registro de Horarios','El Coordinador Academico registro horarios y secciones para Profesor (ID 4)','127.0.0.1','2026-10-23 23:11:12'),(5,3,'Inicio de Sesion','Ingreso exitoso con rol: Coordinador Academico','::1','2026-10-24 03:16:48'),(9,3,'Inicio de Sesion','Ingreso exitoso con rol: Coordinador Academico','::1','2026-10-24 03:58:50'),(10,3,'Documento Rechazado','Documento ID 1 Rechazado (Documento vencido)','::1','2026-10-24 03:59:43'),(11,3,'Documento Aprobado','Documento ID 2 Aprobado','::1','2026-10-24 03:59:47'),(12,3,'Documento Aprobado','Documento ID 3 Aprobado','::1','2026-10-24 03:59:47'),(13,3,'Documento Aprobado','Documento ID 4 Aprobado','::1','2026-10-24 03:59:48'),(14,3,'Documento Aprobado','Documento ID 5 Aprobado','::1','2026-10-24 03:59:49'),(15,3,'Documento Aprobado','Documento ID 6 Aprobado','::1','2026-10-24 03:59:50'),(16,3,'Documento Aprobado','Documento ID 7 Aprobado','::1','2026-10-24 03:59:51'),(17,3,'Documento Aprobado','Documento ID 8 Aprobado','::1','2026-10-24 03:59:51'),(20,3,'Inicio de Sesion','Ingreso exitoso con rol: Coordinador Academico','::1','2026-10-24 04:01:32'),(21,3,'Carnetizacion','Tomo fotografia oficial y aperturo Carnet/QR al estudiante ID 5','::1','2026-10-24 04:10:53'),(23,3,'Inicio de Sesion','Ingreso exitoso con rol: Coordinador Academico','::1','2026-10-24 04:15:00'),(24,3,'Asistencia Manual','Marco estado \'Justificado\' al estudiante ID 5 en sesion ID 2','::1','2026-10-24 04:16:30'),(25,1,'Inicio de Sesion','Ingreso exitoso con rol: Super Administrador','::1','2026-10-24 04:24:30'),(35,3,'Inicio de Sesion','Ingreso exitoso con rol: Coordinador Academico','::1','2026-10-24 05:42:29'),(36,3,'Documento Aprobado','Documento ID 1 Aprobado','::1','2026-10-24 05:42:45'),(49,3,'Inicio de Sesion','Ingreso exitoso con rol: Coordinador Academico','::1','2026-10-24 07:59:41'),(50,3,'Asignar Materias a Profesor','Actualizo materias del docente ID 4 (0 materias)','::1','2026-10-24 08:00:25'),(51,3,'Asignar Materias a Profesor','Actualizo materias del docente ID 4 (1 materias)','::1','2026-10-24 08:00:54'),(67,3,'Inicio de Sesion','Ingreso exitoso con rol: Coordinador Academico','192.168.100.220','2026-10-24 13:56:40'),(68,3,'Inicio de Sesion','Ingreso exitoso con rol: Coordinador Academico','::1','2026-10-24 14:16:01'),(69,3,'Registrar Horario','Creo seccion CINU-2629-D1 (Martes 08:30-09:15, tol: 15m, Aula 01) para docente ID 4','::1','2026-10-24 14:47:12'),(70,3,'Desactivar Horario','Desactivo seccion ID 25','::1','2026-10-24 14:47:26'),(71,3,'Desactivar Horario','Desactivo seccion ID 25','::1','2026-10-24 15:07:37'),(72,3,'Eliminar Horario','Elimino seccion ID 5 permanentemente','::1','2026-10-24 15:24:54'),(73,3,'Registrar Horario','Creo seccion CINU-2629-D1 (Miercoles 07:45-10:00, tol: 15m, Aula 01) para docente ID 4','::1','2026-10-24 15:25:09'),(74,3,'Inicio de Sesion','Ingreso exitoso con rol: Coordinador Academico','192.168.100.220','2026-10-24 15:33:19'),(78,3,'Inicio de Sesion','Ingreso exitoso con rol: Coordinador Academico','::1','2026-10-24 22:33:05'),(79,1,'Inicio de Sesion','Ingreso exitoso con rol: Super Administrador','::1','2026-10-25 00:11:28'),(81,3,'Inicio de Sesion','Ingreso exitoso con rol: Coordinador Academico','::1','2026-10-25 00:39:49'),(86,3,'Inicio de Sesion','Ingreso exitoso con rol: Coordinador Academico','::1','2026-10-25 00:54:08'),(89,3,'Inicio de Sesion','Ingreso exitoso con rol: Coordinador Academico','::1','2026-10-25 00:58:00'),(92,1,'Inicio de Sesion','Ingreso exitoso con rol: Super Administrador','::1','2026-10-25 10:47:50'),(93,1,'Exportar Archivo Auditoria','El Super Administrador genero archivo de registro de auditoria (.log)','::1','2026-10-25 10:48:17'),(95,3,'Inicio de Sesion','Ingreso exitoso con rol: Coordinador Academico','::1','2026-10-25 11:00:05'),(96,3,'Personalizar Ciclo','Actualizo ciclo academico ID 1 (2026-I, 16 semanas, 2026-01-15 a 2026-05-05)','::1','2026-10-25 11:00:31'),(97,3,'Inicio de Sesion','Ingreso exitoso con rol: Coordinador Academico','::1','2026-10-25 11:10:31'),(98,3,'Inicio de Sesion','Ingreso exitoso con rol: Coordinador Academico','::1','2026-10-25 11:27:35'),(99,1,'Inicio de Sesion','Ingreso exitoso con rol: Super Administrador','::1','2026-10-25 11:27:59'),(100,3,'Inicio de Sesion','Ingreso exitoso con rol: Coordinador Academico','::1','2026-10-25 11:28:57'),(102,3,'Registrar Horario','Creo seccion CINU-2629-D1 (Lunes 07:00-08:30, tol: 15m, Aula 01) para docente ID 8','::1','2026-10-25 11:32:14'),(103,3,'Eliminar Horario','Elimino seccion ID 70 permanentemente','::1','2026-10-25 11:32:42'),(104,3,'Inicio de Sesion','Ingreso exitoso con rol: Coordinador Academico','::1','2026-10-25 11:41:11'),(105,3,'Inicio de Sesion','Ingreso exitoso con rol: Coordinador Academico','::1','2026-10-25 11:42:37'),(106,3,'Inicio de Sesion','Ingreso exitoso con rol: Coordinador Academico','192.168.100.220','2026-10-25 11:53:10'),(112,3,'Eliminar Horario','Elimino seccion ID 71 permanentemente','192.168.100.220','2026-10-25 12:34:53'),(113,3,'Inicio de Sesion','Ingreso exitoso con rol: Coordinador Academico','192.168.100.220','2026-10-25 12:36:39'),(114,3,'Inicio de Sesion','Ingreso exitoso con rol: Coordinador Academico','::1','2026-10-25 15:43:26');
/*!40000 ALTER TABLE `bitacora` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `carrera`
--

DROP TABLE IF EXISTS `carrera`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `carrera` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_carrera` varchar(20) NOT NULL,
  `nombre_carrera` varchar(100) NOT NULL,
  `id_coordinador` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_carrera` (`codigo_carrera`)
) ENGINE=InnoDB AUTO_INCREMENT=488 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `carrera`
--

LOCK TABLES `carrera` WRITE;
/*!40000 ALTER TABLE `carrera` DISABLE KEYS */;
INSERT INTO `carrera` VALUES (1,'SYS','Ingenieria de Sistemas',3);
/*!40000 ALTER TABLE `carrera` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `credencial_qr`
--

DROP TABLE IF EXISTS `credencial_qr`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `credencial_qr` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `qr_token` varchar(128) NOT NULL,
  `qr_datos` text NOT NULL,
  `estado` enum('Activo','Inactivo','Revocado') NOT NULL DEFAULT 'Activo',
  `fecha_emision` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_vencimiento` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_usuario` (`id_usuario`),
  UNIQUE KEY `qr_token` (`qr_token`),
  CONSTRAINT `credencial_qr_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `credencial_qr`
--

LOCK TABLES `credencial_qr` WRITE;
/*!40000 ALTER TABLE `credencial_qr` DISABLE KEYS */;
INSERT INTO `credencial_qr` VALUES (1,1,'10000001','Nombre Completo SUPER SOPORTE ADMINISTRADOR TI\nCedula 10000001\nTelefono 04120000001\nCorreo superadmin@unefa.edu.ve\nSeccion 05S 2629 D1','Activo','2026-10-23 19:44:25','2027-10-23 19:44:25'),(3,3,'10000003','Nombre Completo COORDINADOR CONTROL ACADEMICO SISTEMAS\nCedula 10000003\nTelefono 04120000003\nCorreo coordinador@unefa.edu.ve\nSeccion 05S 2629 D1','Activo','2026-10-23 19:44:25','2027-10-23 19:44:25');
/*!40000 ALTER TABLE `credencial_qr` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cronograma_evento`
--

DROP TABLE IF EXISTS `cronograma_evento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cronograma_evento` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `anio` int(11) NOT NULL,
  `categoria` varchar(50) NOT NULL,
  `periodo` varchar(100) NOT NULL,
  `descripcion` varchar(300) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `id_periodo` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cronograma_evento`
--

LOCK TABLES `cronograma_evento` WRITE;
/*!40000 ALTER TABLE `cronograma_evento` DISABLE KEYS */;
INSERT INTO `cronograma_evento` VALUES (1,2026,'Pre-Inscripcion','2026-I','Recepcion de documentos digitales de Aspirantes y validacion por Coordinacion','2026-01-15','2026-03-30',1,'2026-10-23 23:11:11'),(2,2026,'Carnetizacion','2026-I','Jornada de emision de Carnets Digitales y Codigos QR de Asistencia','2026-02-01','2026-11-30',1,'2026-10-23 23:11:11'),(3,2026,'Control de Asistencias','2026-I','Registro continuo de asistencias mediante escaneo QR en aulas y laboratorios','2026-02-15','2026-12-10',1,'2026-10-23 23:11:11');
/*!40000 ALTER TABLE `cronograma_evento` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `docente_materia`
--

DROP TABLE IF EXISTS `docente_materia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `docente_materia` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_docente` int(11) NOT NULL,
  `id_materia` int(11) NOT NULL,
  `id_coordinador` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_doc_mat` (`id_docente`,`id_materia`),
  KEY `id_docente` (`id_docente`),
  KEY `id_materia` (`id_materia`),
  KEY `id_coordinador` (`id_coordinador`),
  CONSTRAINT `fk_dm_docente` FOREIGN KEY (`id_docente`) REFERENCES `usuario` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dm_materia` FOREIGN KEY (`id_materia`) REFERENCES `materia` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=82 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `docente_materia`
--

LOCK TABLES `docente_materia` WRITE;
/*!40000 ALTER TABLE `docente_materia` DISABLE KEYS */;
/*!40000 ALTER TABLE `docente_materia` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inscripcion_seccion`
--

DROP TABLE IF EXISTS `inscripcion_seccion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inscripcion_seccion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_estudiante` int(11) NOT NULL,
  `id_seccion` int(11) NOT NULL,
  `id_periodo` int(11) DEFAULT 1,
  `estado` enum('Inscrito','Retirado') NOT NULL DEFAULT 'Inscrito',
  `fecha_asignacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_est_sec` (`id_estudiante`,`id_seccion`),
  KEY `id_seccion` (`id_seccion`),
  CONSTRAINT `insc_sec_est_fk` FOREIGN KEY (`id_estudiante`) REFERENCES `usuario` (`id`) ON DELETE CASCADE,
  CONSTRAINT `insc_sec_sec_fk` FOREIGN KEY (`id_seccion`) REFERENCES `seccion` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inscripcion_seccion`
--

LOCK TABLES `inscripcion_seccion` WRITE;
/*!40000 ALTER TABLE `inscripcion_seccion` DISABLE KEYS */;
/*!40000 ALTER TABLE `inscripcion_seccion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `intentos_fallidos`
--

DROP TABLE IF EXISTS `intentos_fallidos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `intentos_fallidos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `direccion_ip` varchar(45) NOT NULL,
  `tipo` varchar(20) NOT NULL DEFAULT 'login',
  `identificador` varchar(100) DEFAULT NULL,
  `fecha_intento` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `direccion_ip` (`direccion_ip`),
  KEY `tipo` (`tipo`),
  KEY `fecha_intento` (`fecha_intento`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `intentos_fallidos`
--

LOCK TABLES `intentos_fallidos` WRITE;
/*!40000 ALTER TABLE `intentos_fallidos` DISABLE KEYS */;
/*!40000 ALTER TABLE `intentos_fallidos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `materia`
--

DROP TABLE IF EXISTS `materia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `materia` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_materia` varchar(20) NOT NULL,
  `nombre_materia` varchar(100) NOT NULL,
  `semestre` int(11) DEFAULT NULL,
  `uc` int(11) DEFAULT 3,
  `orden` int(11) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_materia` (`codigo_materia`)
) ENGINE=InnoDB AUTO_INCREMENT=127 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `materia`
--

LOCK TABLES `materia` WRITE;
/*!40000 ALTER TABLE `materia` DISABLE KEYS */;
INSERT INTO `materia` VALUES (4,'DIB-101','Dibujo',1,2,4),(5,'EDA-101','Educación Ambiental',1,2,5),(6,'GEO-101','Geometría Analítica',1,4,2),(7,'HSC-101','Hombre, Sociedad, Ciencia y Tecnología',1,3,3),(8,'ING-101','Inglés I',1,3,7),(9,'MAT-101','Matemática I',1,5,1),(10,'SEM-101','Seminario I',1,1,6),(11,'ALG-201','Álgebra Lineal',2,4,3),(13,'FIS-201','Física I',2,5,2),(14,'ING-201','Inglés II',2,3,6),(15,'MAT-201','Matemática II',2,5,1),(16,'QUI-201','Química General',2,4,4),(17,'SEM-201','Seminario II',2,1,5),(18,'FIS-301','Física II',3,5,2),(19,'MAT-301','Matemática III',3,5,1),(20,'PRO-301','Probabilidades y Estadística',3,4,3),(21,'PRG-301','Programación',3,3,4),(22,'SAD-301','Sistemas Administrativos',3,4,5),(23,'CAL-401','Cálculo Numérico',4,4,1),(24,'LP1-401','Lenguaje de Programación I',4,5,4),(25,'LOG-401','Lógica Matemática',4,4,2),(26,'PDA-401','Procesamiento de Datos',4,4,5),(27,'SPR-401','Sistemas de Producción',4,4,6),(28,'TSI-401','Teoría de Sistemas',4,4,3),(29,'ASI-501','Análisis de Sistemas',5,4,5),(30,'BD0-501','Base de Datos',5,4,4),(32,'CIR-501','Circuitos Lógicos',5,4,3),(33,'INO-501','Investigación de Operaciones',5,5,1),(34,'LP2-501','Lenguaje de Programación II',5,5,6),(35,'TGR-501','Teoría de Grafos',5,4,2),(36,'ARC-601','Arquitectura del Computador',6,5,3),(38,'DSI-601','Diseño de Sistemas',6,4,5),(39,'LP3-601','Lenguaje de Programación III',6,5,6),(40,'ONL-601','Optimización No Lineal',6,5,1),(41,'PES-601','Procesos Estocásticos',6,4,2),(42,'SOP-601','Sistemas Operativos',6,4,4),(43,'GIN-701','Gerencia de la Informática',7,4,3),(44,'ISI-701','Implantación de Sistemas',7,4,5),(45,'MIN-701','Metodología de la Investigación',7,4,2),(46,'RED-701','Redes',7,4,4),(47,'SIM-701','Simulación y Modelos',7,5,1),(48,'AUS-801','Auditoría de Sistemas',8,4,4),(49,'MLE-801','Marco Legal para el Ejercicio',8,4,2),(50,'TEL-801','Teleprocesos',8,4,3),(51,'TDE-801','Teoría de Decisiones',8,4,1),(52,'ET-AS','Arquitectura de Software',NULL,3,1),(53,'ET-IA','Inteligencia Artificial',NULL,3,2),(54,'ET-RL','Redes de Área Local',NULL,3,3),(55,'ET-SABD','Sistemas Avanzados de Bases de Datos',NULL,3,4),(56,'CINU-FEV','Filosofía Ética y Valores',0,3,1),(57,'CINU-LYC','Lenguaje y Comunicación',0,3,2),(58,'CINU-MAT','Matemática',0,3,3),(66,'DIN-101','Defensa Integral de la Nación I',1,3,8),(73,'DIN-201','Defensa Integral de la Nación II',2,3,7),(79,'DIN-301','Defensa Integral de la Nación III',3,3,6),(86,'DIN-401','Defensa Integral de la Nación IV',4,3,7),(93,'DIN-501','Defensa Integral de la Nación V',5,3,7),(100,'DIN-601','Defensa Integral de la Nación VI',6,3,7),(106,'ELT-701','Electiva Técnica',7,3,6),(107,'ELN-701','Electiva No Técnica',7,3,7),(108,'DIN-701','Defensa Integral de la Nación VII',7,3,8),(113,'ELT-801','Electiva Técnica',8,3,5),(114,'ELN-801','Electiva No Técnica',8,3,6),(115,'DIN-801','Defensa Integral de la Nación VIII',8,3,7),(116,'PAS-901','Pasantía o TEG',9,10,1),(121,'ET-TR','Tecnología de Redes',NULL,3,5),(122,'ENT-DOI','Decisiones Óptimas de Inversión',NULL,3,6),(123,'ENT-GDP','Gerencia de Proyectos',NULL,3,7),(124,'ENT-INF','Informática',NULL,3,8),(125,'ENT-IDM','Ingeniería de Métodos',NULL,3,9),(126,'ENT-PDG','Principios de Gerencia',NULL,3,10);
/*!40000 ALTER TABLE `materia` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `perfil`
--

DROP TABLE IF EXISTS `perfil`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `perfil` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cedula` varchar(20) NOT NULL,
  `tipo_documento` varchar(2) DEFAULT 'V',
  `nombre` varchar(50) NOT NULL,
  `segundo_nombre` varchar(50) DEFAULT NULL,
  `apellido` varchar(50) NOT NULL,
  `segundo_apellido` varchar(50) DEFAULT NULL,
  `id_carrera` int(11) DEFAULT 1,
  `foto_perfil` varchar(255) DEFAULT NULL,
  `fecha_carnetizacion` datetime DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `semestre_actual` int(11) NOT NULL DEFAULT 1,
  `direccion` text DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `cargo` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_carrera` (`id_carrera`),
  KEY `perfil_ibfk_usuario` (`id_usuario`),
  CONSTRAINT `perfil_ibfk_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `perfil`
--

LOCK TABLES `perfil` WRITE;
/*!40000 ALTER TABLE `perfil` DISABLE KEYS */;
INSERT INTO `perfil` VALUES (1,'10000001','V','SUPER','SOPORTE','ADMINISTRADOR','TI',1,'foto_oficial_1.jpg','2026-10-23 19:11:12',1,0,'Sede Central UNEFA - Soporte TI','04120000001','Soporte Tecnico Central / TI'),(3,'10000003','V','COORDINADOR','CONTROL','ACADEMICO','SISTEMAS',1,'foto_oficial_3.jpg','2026-10-23 19:11:12',3,0,'Coordinacion Ingenieria de Sistemas','04120000003','Coordinador de Ingenieria de Sistemas');
/*!40000 ALTER TABLE `perfil` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `periodo_academico`
--

DROP TABLE IF EXISTS `periodo_academico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `periodo_academico` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `semanas_duracion` int(11) NOT NULL DEFAULT 16,
  `estado` varchar(20) NOT NULL DEFAULT 'Activo',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `periodo_academico`
--

LOCK TABLES `periodo_academico` WRITE;
/*!40000 ALTER TABLE `periodo_academico` DISABLE KEYS */;
INSERT INTO `periodo_academico` VALUES (1,'2026-I','2026-01-15','2026-05-05',16,'Activo','2026-10-23 23:11:11');
/*!40000 ALTER TABLE `periodo_academico` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `preguntas_seguridad`
--

DROP TABLE IF EXISTS `preguntas_seguridad`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `preguntas_seguridad` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `texto_pregunta` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `preguntas_seguridad`
--

LOCK TABLES `preguntas_seguridad` WRITE;
/*!40000 ALTER TABLE `preguntas_seguridad` DISABLE KEYS */;
INSERT INTO `preguntas_seguridad` VALUES (1,'En que ciudad naciste?'),(2,'Cual es el primer nombre de tu madre?'),(3,'Como se llamaba tu primer colegio?'),(4,'Como se llamaba tu primera mascota?'),(5,'Cual es tu segundo nombre?'),(6,'En que mes es tu cumpleanos?'),(7,'Cual es tu fruta favorita?'),(8,'Que idioma hablas en casa?'),(9,'Cual es tu color favorito?');
/*!40000 ALTER TABLE `preguntas_seguridad` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prelacion`
--

DROP TABLE IF EXISTS `prelacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `prelacion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_materia` int(11) NOT NULL,
  `id_prerrequisito` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_prelacion` (`id_materia`,`id_prerrequisito`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prelacion`
--

LOCK TABLES `prelacion` WRITE;
/*!40000 ALTER TABLE `prelacion` DISABLE KEYS */;
INSERT INTO `prelacion` VALUES (5,11,6),(4,11,9),(3,13,6),(2,13,9),(7,14,8),(1,15,9),(6,17,10),(11,18,13),(10,18,15),(9,19,15),(12,20,15),(13,21,11),(15,23,19),(17,24,21),(16,25,20),(18,26,21),(19,27,22),(25,29,28),(24,30,28),(23,32,25),(21,33,23),(26,34,24),(22,35,20),(30,36,32),(32,38,29),(33,39,34),(28,40,33),(29,41,33),(31,42,30),(37,43,36),(39,44,38),(38,46,42),(35,47,40),(36,47,41),(43,48,44),(42,50,46),(41,51,47),(8,73,66),(14,79,73),(20,86,79),(27,93,86),(34,100,93),(40,108,100),(44,115,108);
/*!40000 ALTER TABLE `prelacion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `registro_documentos`
--

DROP TABLE IF EXISTS `registro_documentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `registro_documentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `ruta` varchar(255) NOT NULL,
  `tipo` enum('Word','PDF','Imagen','Excel') NOT NULL DEFAULT 'PDF',
  `fecha_subida` timestamp NOT NULL DEFAULT current_timestamp(),
  `estado` enum('Pendiente','Aprobado','Rechazado') NOT NULL DEFAULT 'Pendiente',
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `registro_documentos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `registro_documentos`
--

LOCK TABLES `registro_documentos` WRITE;
/*!40000 ALTER TABLE `registro_documentos` DISABLE KEYS */;
/*!40000 ALTER TABLE `registro_documentos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `respuestas_seguridad_usuario`
--

DROP TABLE IF EXISTS `respuestas_seguridad_usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `respuestas_seguridad_usuario` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `id_pregunta` int(11) NOT NULL,
  `hash_respuesta` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_usuario` (`id_usuario`),
  KEY `id_pregunta` (`id_pregunta`),
  CONSTRAINT `respuestas_seguridad_usuario_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`) ON DELETE CASCADE,
  CONSTRAINT `respuestas_seguridad_usuario_ibfk_2` FOREIGN KEY (`id_pregunta`) REFERENCES `preguntas_seguridad` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `respuestas_seguridad_usuario`
--

LOCK TABLES `respuestas_seguridad_usuario` WRITE;
/*!40000 ALTER TABLE `respuestas_seguridad_usuario` DISABLE KEYS */;
INSERT INTO `respuestas_seguridad_usuario` VALUES (1,1,1,'$2y$10$D.P1I5OjYmNXwQhV5oJrae1l7djuAeWu5OjwNZWdKv7i5p4iVXbjG'),(2,1,2,'$2y$10$D.P1I5OjYmNXwQhV5oJrae1l7djuAeWu5OjwNZWdKv7i5p4iVXbjG'),(3,1,3,'$2y$10$D.P1I5OjYmNXwQhV5oJrae1l7djuAeWu5OjwNZWdKv7i5p4iVXbjG'),(7,3,1,'$2y$10$D.P1I5OjYmNXwQhV5oJrae1l7djuAeWu5OjwNZWdKv7i5p4iVXbjG'),(8,3,2,'$2y$10$D.P1I5OjYmNXwQhV5oJrae1l7djuAeWu5OjwNZWdKv7i5p4iVXbjG'),(9,3,3,'$2y$10$D.P1I5OjYmNXwQhV5oJrae1l7djuAeWu5OjwNZWdKv7i5p4iVXbjG');
/*!40000 ALTER TABLE `respuestas_seguridad_usuario` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_rol` varchar(50) NOT NULL,
  `nivel_jerarquia` int(11) NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre_rol` (`nombre_rol`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Super Administrador',1,'Soporte Tecnico Central / TI - Acceso exclusivo a Auditoria y Bitacora'),(2,'Director',2,'Rector / Administrador de la Institucion - Asigna Coordinador de Carrera'),(3,'Coordinador Academico',3,'Control de Estudios - Aprueba Aspirantes, Registra Horarios y Carnets'),(4,'Profesor',4,'Docente / Titular de Materia - Gestiona Asistencias y Escaneo QR'),(5,'Estudiante',5,'Alumno Regular Aprobado - Acceso a Carnet Digital, QR y Horario tras Carnetizacion'),(6,'Aspirante',6,'Postulante / Pre-inscrito - Carga de Documentos de Inscripcion');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `seccion`
--

DROP TABLE IF EXISTS `seccion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `seccion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_materia` int(11) NOT NULL,
  `id_docente` int(11) NOT NULL,
  `id_periodo` int(11) DEFAULT 1,
  `nombre_seccion` varchar(30) NOT NULL,
  `dia_semana` varchar(30) NOT NULL DEFAULT 'Lunes',
  `hora_inicio` time NOT NULL DEFAULT '08:00:00',
  `hora_fin` time NOT NULL DEFAULT '10:00:00',
  `tolerancia_minutos` int(11) NOT NULL DEFAULT 15,
  `aula` varchar(50) NOT NULL DEFAULT 'Aula 01',
  `estado` enum('Activa','Inactiva') NOT NULL DEFAULT 'Activa',
  PRIMARY KEY (`id`),
  KEY `id_materia` (`id_materia`),
  KEY `id_docente` (`id_docente`),
  CONSTRAINT `seccion_ibfk_1` FOREIGN KEY (`id_materia`) REFERENCES `materia` (`id`) ON DELETE CASCADE,
  CONSTRAINT `seccion_ibfk_2` FOREIGN KEY (`id_docente`) REFERENCES `usuario` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `seccion`
--

LOCK TABLES `seccion` WRITE;
/*!40000 ALTER TABLE `seccion` DISABLE KEYS */;
/*!40000 ALTER TABLE `seccion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sesion_clase`
--

DROP TABLE IF EXISTS `sesion_clase`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sesion_clase` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_seccion` int(11) NOT NULL,
  `fecha_clase` date NOT NULL,
  `hora_inicio` time DEFAULT NULL,
  `hora_fin` time DEFAULT NULL,
  `tolerancia_minutos` int(11) NOT NULL DEFAULT 15,
  `tipo_sesion` enum('Regular','Extraordinaria') NOT NULL DEFAULT 'Regular',
  `numero_semana` int(11) DEFAULT NULL,
  `id_periodo` int(11) DEFAULT NULL,
  `abierta_por` int(11) NOT NULL,
  `estado` enum('Abierta','Cerrada') NOT NULL DEFAULT 'Abierta',
  `observaciones` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `id_seccion` (`id_seccion`),
  KEY `fecha_clase` (`fecha_clase`),
  CONSTRAINT `sesion_clase_sec_fk` FOREIGN KEY (`id_seccion`) REFERENCES `seccion` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sesion_clase`
--

LOCK TABLES `sesion_clase` WRITE;
/*!40000 ALTER TABLE `sesion_clase` DISABLE KEYS */;
/*!40000 ALTER TABLE `sesion_clase` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `solicitud_inscripcion`
--

DROP TABLE IF EXISTS `solicitud_inscripcion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `solicitud_inscripcion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_estudiante` int(11) NOT NULL,
  `id_seccion` int(11) NOT NULL,
  `id_periodo` int(11) DEFAULT NULL,
  `estado` enum('Pendiente','Aceptada','Rechazada') NOT NULL DEFAULT 'Pendiente',
  `nota` int(11) DEFAULT 0,
  `ciclo_cerrado` tinyint(1) NOT NULL DEFAULT 0,
  `valido_coordinador` tinyint(1) DEFAULT NULL,
  `fecha_solicitud` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `id_estudiante` (`id_estudiante`),
  KEY `id_seccion` (`id_seccion`),
  KEY `solicitud_inscripcion_ibfk_3` (`id_periodo`),
  CONSTRAINT `solicitud_inscripcion_ibfk_1` FOREIGN KEY (`id_estudiante`) REFERENCES `usuario` (`id`) ON DELETE CASCADE,
  CONSTRAINT `solicitud_inscripcion_ibfk_2` FOREIGN KEY (`id_seccion`) REFERENCES `seccion` (`id`) ON DELETE CASCADE,
  CONSTRAINT `solicitud_inscripcion_ibfk_3` FOREIGN KEY (`id_periodo`) REFERENCES `periodo_academico` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `solicitud_inscripcion`
--

LOCK TABLES `solicitud_inscripcion` WRITE;
/*!40000 ALTER TABLE `solicitud_inscripcion` DISABLE KEYS */;
/*!40000 ALTER TABLE `solicitud_inscripcion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuario`
--

DROP TABLE IF EXISTS `usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuario` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_perfil` int(11) NOT NULL,
  `nombre_usuario` varchar(100) NOT NULL,
  `correo` varchar(100) NOT NULL,
  `clave` varchar(255) NOT NULL,
  `rol` varchar(50) NOT NULL,
  `estado` enum('Pendiente','Aprobado','Rechazado','Inactivo') NOT NULL DEFAULT 'Pendiente',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `correo` (`correo`),
  UNIQUE KEY `nombre_usuario` (`nombre_usuario`),
  KEY `id_perfil` (`id_perfil`),
  CONSTRAINT `usuario_ibfk_1` FOREIGN KEY (`id_perfil`) REFERENCES `perfil` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario`
--

LOCK TABLES `usuario` WRITE;
/*!40000 ALTER TABLE `usuario` DISABLE KEYS */;
INSERT INTO `usuario` VALUES (1,1,'Super Administrador','superadmin@unefa.edu.ve','$2y$10$EumY0Mdeo6DGDogWQcCw.e.wPJBE.t3LNmmqoFct.xphczNQ6TZn.','Super Administrador','Aprobado','2026-10-23 23:11:12'),(3,3,'Coordinador Academico','coordinador@unefa.edu.ve','$2y$10$EumY0Mdeo6DGDogWQcCw.e.wPJBE.t3LNmmqoFct.xphczNQ6TZn.','Coordinador Academico','Aprobado','2026-10-23 23:11:12');
/*!40000 ALTER TABLE `usuario` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-25 11:45:24

SET FOREIGN_KEY_CHECKS = 1;