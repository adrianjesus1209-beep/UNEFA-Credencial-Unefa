<?php
// este archivo sirve como el puente que conecta toda la pagina web con la base de datos mysql.
// si la base de datos o las tablas todavia no existen en la computadora, este codigo las crea todas
// automaticamente con sus materias, usuarios y preguntas de prueba.
class Database {
    // datos para el servidor de base de datos
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    // esta funcion configura automaticamente los datos segun estemos en la computadora o en infinityfree
    public function __construct() {
        $hostActual = $_SERVER['HTTP_HOST'] ?? '';
        $esLocal = in_array($hostActual, ['localhost', '127.0.0.1'], true)
                || str_starts_with($hostActual, '192.168.')
                || str_starts_with($hostActual, '10.')
                || php_sapi_name() === 'cli';

        if ($esLocal) {
            // conexion para xampp en computadora local
            $this->host = "localhost";
            $this->db_name = "credencial_unefa_db";
            $this->username = "root";
            $this->password = "";
        } else {
            // conexion para el servidor en linea de infinityfree
            $this->host = "sql211.infinityfree.com";
            $this->db_name = "if0_42110102_credencial_unefa_db";
            $this->username = "if0_42110102";
            $this->password = "3sZQtQiXvV";
        }
    }

    // esta funcion intenta conectarse directamente a la base de datos de la unefa.
    // si la conexion tiene exito, devuelve el enlace listo para usar.
    // si falla porque la base de datos todavia no ha sido creada, llama a la funcion de abajo
    // para crearla desde cero sin que tengamos que hacer nada manual.
    public function getConnection() {
        if ($this->conn !== null) {
            return $this->conn;
        }

        try {
            // conexion rapida directa a la base de datos activa
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("SET NAMES utf8mb4");
            $this->conn->exec("SET time_zone = '-04:00'");
            return $this->conn;
        } catch (PDOException $e) {
            $hostActual = $_SERVER['HTTP_HOST'] ?? '';
            $esLocal = in_array($hostActual, ['localhost', '127.0.0.1'], true) || str_starts_with($hostActual, '192.168.') || php_sapi_name() === 'cli';
            if (!$esLocal) {
                // si en infinityfree la crearon como if0_42110102_credencial_unefa sin el _db final, intentamos con ese nombre
                try {
                    $this->db_name = "if0_42110102_credencial_unefa";
                    $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", $this->username, $this->password);
                    $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $this->conn->exec("SET NAMES utf8mb4");
                    $this->conn->exec("SET time_zone = '-04:00'");
                    return $this->conn;
                } catch (PDOException $e2) {
                    die("<div style='font-family:sans-serif;padding:20px;text-align:center;'><h2>Error de conexion a la base de datos</h2><p>" . htmlspecialchars($e->getMessage()) . "</p></div>");
                }
            }
            // si es local y la base de datos aun no existe, inicializamos el esquema completo
            return $this->inicializarEsquemaBD();
        }
    }

    // esta funcion crea la base de datos completa con todas sus tablas si es la primera vez que se ejecuta el sistema
    private function inicializarEsquemaBD() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";charset=utf8mb4", $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("SET NAMES utf8mb4");
            $this->conn->exec("CREATE DATABASE IF NOT EXISTS `$this->db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            $this->conn->exec("USE `$this->db_name`;");

            // tabla para registrar las carreras
            $this->conn->exec("CREATE TABLE IF NOT EXISTS `carrera` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `codigo_carrera` varchar(20) NOT NULL,
                `nombre_carrera` varchar(100) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `codigo_carrera` (`codigo_carrera`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            // tabla para guardar los datos personales de la persona como nombre, cedula y telefono
            $this->conn->exec("CREATE TABLE IF NOT EXISTS `perfil` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `tipo_documento` varchar(2) DEFAULT 'V',
                `cedula` varchar(20) NOT NULL,
                `nombre` varchar(50) NOT NULL,
                `segundo_nombre` varchar(50) DEFAULT NULL,
                `apellido` varchar(50) NOT NULL,
                `segundo_apellido` varchar(50) DEFAULT NULL,
                `telefono` varchar(20) DEFAULT NULL,
                `direccion` text DEFAULT NULL,
                `id_carrera` int(11) DEFAULT NULL,
                `foto_perfil` varchar(255) DEFAULT NULL,
                `fecha_carnetizacion` datetime DEFAULT NULL,
                `id_usuario` int(11) DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `id_carrera` (`id_carrera`),
                KEY `cedula` (`cedula`),
                KEY `id_usuario` (`id_usuario`),
                CONSTRAINT `perfil_ibfk_1` FOREIGN KEY (`id_carrera`) REFERENCES `carrera` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            // tabla para guardar las cuentas de acceso con su correo y clave secreta
            $this->conn->exec("CREATE TABLE IF NOT EXISTS `usuario` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `id_perfil` int(11) NOT NULL,
                `correo` varchar(100) NOT NULL,
                `clave` varchar(255) NOT NULL,
                `rol` varchar(20) NOT NULL,
                `estado` enum('Pendiente','Aprobado','Rechazado','Inactivo') NOT NULL DEFAULT 'Pendiente',
                PRIMARY KEY (`id`),
                UNIQUE KEY `correo` (`correo`),
                KEY `id_perfil` (`id_perfil`),
                CONSTRAINT `usuario_ibfk_1` FOREIGN KEY (`id_perfil`) REFERENCES `perfil` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            // tabla para guardar todas las materias de la carrera
            $this->conn->exec("CREATE TABLE IF NOT EXISTS `materia` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `codigo_materia` varchar(20) NOT NULL,
                `nombre_materia` varchar(100) NOT NULL,
                `semestre` int(11) DEFAULT NULL,
                `uc` int(11) DEFAULT NULL,
                `orden` int(11) DEFAULT 1,
                PRIMARY KEY (`id`),
                UNIQUE KEY `codigo_materia` (`codigo_materia`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            try { $this->conn->exec("ALTER TABLE `materia` ADD `orden` int(11) DEFAULT 1 AFTER `uc`"); } catch (PDOException $e) { if (strpos($e->getMessage(), 'Duplicate') === false) throw $e; }

            // tabla para guardar las secciones de clase asignadas a los profesores
            $this->conn->exec("CREATE TABLE IF NOT EXISTS `seccion` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `id_materia` int(11) NOT NULL,
                `id_docente` int(11) NOT NULL,
                `nombre_seccion` varchar(20) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `id_materia` (`id_materia`),
                KEY `id_docente` (`id_docente`),
                CONSTRAINT `seccion_ibfk_1` FOREIGN KEY (`id_materia`) REFERENCES `materia` (`id`) ON DELETE CASCADE,
                CONSTRAINT `seccion_ibfk_2` FOREIGN KEY (`id_docente`) REFERENCES `usuario` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            // tabla para las solicitudes de inscripcion de los estudiantes
            $this->conn->exec("CREATE TABLE IF NOT EXISTS `solicitud_inscripcion` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `id_estudiante` int(11) NOT NULL,
                `id_seccion` int(11) NOT NULL,
                `estado` enum('Pendiente','Aceptada','Rechazada') NOT NULL DEFAULT 'Pendiente',
                `nota` int(11) DEFAULT 0,
                `ciclo_cerrado` tinyint(1) NOT NULL DEFAULT 0,
                `valido_coordinador` tinyint(1) DEFAULT NULL,
                `fecha_solicitud` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `id_estudiante` (`id_estudiante`),
                KEY `id_seccion` (`id_seccion`),
                CONSTRAINT `solicitud_inscripcion_ibfk_1` FOREIGN KEY (`id_estudiante`) REFERENCES `usuario` (`id`) ON DELETE CASCADE,
                CONSTRAINT `solicitud_inscripcion_ibfk_2` FOREIGN KEY (`id_seccion`) REFERENCES `seccion` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            try { $this->conn->exec("ALTER TABLE `solicitud_inscripcion` ADD `ciclo_cerrado` tinyint(1) NOT NULL DEFAULT 0 AFTER `nota`"); } catch (PDOException $e) { if (strpos($e->getMessage(), 'Duplicate') === false) throw $e; }
            try { $this->conn->exec("ALTER TABLE `solicitud_inscripcion` ADD `valido_coordinador` tinyint(1) DEFAULT NULL AFTER `ciclo_cerrado`"); } catch (PDOException $e) { if (strpos($e->getMessage(), 'Duplicate') === false) throw $e; }
            try { $this->conn->exec("ALTER TABLE `perfil` ADD `semestre_actual` int(11) NOT NULL DEFAULT 0 AFTER `fecha_carnetizacion`"); } catch (PDOException $e) { if (strpos($e->getMessage(), 'Duplicate') === false) throw $e; }
            try { $this->conn->exec("ALTER TABLE `perfil` ADD `id_usuario` int(11) DEFAULT NULL AFTER `fecha_carnetizacion`"); } catch (PDOException $e) { if (strpos($e->getMessage(), 'Duplicate') === false) throw $e; }
            try { $this->conn->exec("ALTER TABLE `perfil` ADD CONSTRAINT `perfil_ibfk_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`) ON DELETE SET NULL"); } catch (PDOException $e) { if (strpos($e->getMessage(), 'Duplicate') === false) throw $e; }
            try { $this->conn->exec("UPDATE perfil p JOIN usuario u ON u.id_perfil = p.id SET p.id_usuario = u.id WHERE p.id_usuario IS NULL"); } catch (PDOException $e) {}

            // tabla para guardar las prelaciones (que materias hay que pasar antes de poder ver otra)
            $this->conn->exec("CREATE TABLE IF NOT EXISTS `prelacion` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `id_materia` int(11) NOT NULL,
                `id_prerrequisito` int(11) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_prelacion` (`id_materia`,`id_prerrequisito`),
                CONSTRAINT `prelacion_ibfk_1` FOREIGN KEY (`id_materia`) REFERENCES `materia` (`id`) ON DELETE CASCADE,
                CONSTRAINT `prelacion_ibfk_2` FOREIGN KEY (`id_prerrequisito`) REFERENCES `materia` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            // tabla para registrar los archivos y documentos que suben los aspirantes
            $this->conn->exec("CREATE TABLE IF NOT EXISTS `registro_documentos` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `id_usuario` int(11) NOT NULL,
                `nombre_archivo` varchar(255) NOT NULL,
                `ruta` varchar(255) NOT NULL,
                `tipo` enum('Word','PDF','Imagen') NOT NULL DEFAULT 'PDF',
                `estado` enum('Pendiente','Aprobado','Rechazado') NOT NULL DEFAULT 'Pendiente',
                `observaciones` text DEFAULT NULL,
                `fecha_subida` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `id_usuario` (`id_usuario`),
                CONSTRAINT `registro_documentos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            // tabla para las preguntas de seguridad secretas
            $this->conn->exec("CREATE TABLE IF NOT EXISTS `preguntas_seguridad` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `texto_pregunta` varchar(255) NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            // tabla para guardar las respuestas secretas cifradas de cada persona
            $this->conn->exec("CREATE TABLE IF NOT EXISTS `respuestas_seguridad_usuario` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `id_usuario` int(11) NOT NULL,
                `id_pregunta` int(11) NOT NULL,
                `hash_respuesta` varchar(255) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `id_usuario` (`id_usuario`),
                KEY `id_pregunta` (`id_pregunta`),
                CONSTRAINT `respuestas_seguridad_usuario_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`) ON DELETE CASCADE,
                CONSTRAINT `respuestas_seguridad_usuario_ibfk_2` FOREIGN KEY (`id_pregunta`) REFERENCES `preguntas_seguridad` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            // tabla bitacora para registrar cada movimiento importante en el sistema (auditoria)
            $this->conn->exec("CREATE TABLE IF NOT EXISTS `bitacora` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            // tabla para contar intentos de contrasena fallidos y bloquear ataques
            $this->conn->exec("CREATE TABLE IF NOT EXISTS `intentos_fallidos` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `direccion_ip` varchar(45) NOT NULL,
                `tipo` varchar(20) NOT NULL DEFAULT 'login',
                `identificador` varchar(100) DEFAULT NULL,
                `fecha_intento` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `direccion_ip` (`direccion_ip`),
                KEY `tipo` (`tipo`),
                KEY `fecha_intento` (`fecha_intento`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            // aqui insertamos la carrera principal: Ingenieria de Sistemas
            $this->conn->exec("INSERT IGNORE INTO carrera (codigo_carrera, nombre_carrera) VALUES ('SYS', 'Ingenieria de Sistemas')");

            // aqui llenamos todas las materias del pensum de ingenieria si la tabla esta vacia
            $conteo = $this->conn->query("SELECT COUNT(*) FROM materia")->fetchColumn();
            if ($conteo == 0) {
                $materias_seed = [
                    ['CINU-FEV', 'Filosofia Etica y Valores', 0, 3, 1],
                    ['CINU-LYC', 'Lenguaje y Comunicacion', 0, 3, 2],
                    ['CINU-MAT', 'Matematica', 0, 3, 3],
                    ['MAT-101', 'Matematica I', 1, 5, 1],
                    ['GEO-101', 'Geometria Analitica', 1, 4, 2],
                    ['HSC-101', 'Hombre, Sociedad, Ciencia y Tecnologia', 1, 3, 3],
                    ['DIB-101', 'Dibujo', 1, 2, 4],
                    ['EDA-101', 'Educacion Ambiental', 1, 2, 5],
                    ['SEM-101', 'Seminario I', 1, 1, 6],
                    ['ING-101', 'Ingles I', 1, 3, 7],
                    ['DIN-101', 'Defensa Integral de la Nacion I', 1, 3, 8],
                    ['MAT-201', 'Matematica II', 2, 5, 1],
                    ['FIS-201', 'Fisica I', 2, 5, 2],
                    ['ALG-201', 'Algebra Lineal', 2, 4, 3],
                    ['QUI-201', 'Quimica General', 2, 4, 4],
                    ['SEM-201', 'Seminario II', 2, 1, 5],
                    ['ING-201', 'Ingles II', 2, 3, 6],
                    ['DIN-201', 'Defensa Integral de la Nacion II', 2, 3, 7],
                    ['MAT-301', 'Matematica III', 3, 5, 1],
                    ['FIS-301', 'Fisica II', 3, 5, 2],
                    ['PRO-301', 'Probabilidades y Estadistica', 3, 4, 3],
                    ['PRG-301', 'Programacion', 3, 3, 4],
                    ['SAD-301', 'Sistemas Administrativos', 3, 4, 5],
                    ['DIN-301', 'Defensa Integral de la Nacion III', 3, 3, 6],
                    ['CAL-401', 'Calculo Numerico', 4, 4, 1],
                    ['LOG-401', 'Logica Matematica', 4, 4, 2],
                    ['TSI-401', 'Teoria de Sistemas', 4, 4, 3],
                    ['LP1-401', 'Lenguaje de Programacion I', 4, 5, 4],
                    ['PDA-401', 'Procesamiento de Datos', 4, 4, 5],
                    ['SPR-401', 'Sistemas de Produccion', 4, 4, 6],
                    ['DIN-401', 'Defensa Integral de la Nacion IV', 4, 3, 7],
                    ['INO-501', 'Investigacion de Operaciones', 5, 5, 1],
                    ['TGR-501', 'Teoria de Grafos', 5, 4, 2],
                    ['CIR-501', 'Circuitos Logicos', 5, 4, 3],
                    ['BD0-501', 'Base de Datos', 5, 4, 4],
                    ['ASI-501', 'Analisis de Sistemas', 5, 4, 5],
                    ['LP2-501', 'Lenguaje de Programacion II', 5, 5, 6],
                    ['DIN-501', 'Defensa Integral de la Nacion V', 5, 3, 7],
                    ['ONL-601', 'Optimizacion No Lineal', 6, 5, 1],
                    ['PES-601', 'Procesos Estocasticos', 6, 4, 2],
                    ['ARC-601', 'Arquitectura del Computador', 6, 5, 3],
                    ['SOP-601', 'Sistemas Operativos', 6, 4, 4],
                    ['DSI-601', 'Diseno de Sistemas', 6, 4, 5],
                    ['LP3-601', 'Lenguaje de Programacion III', 6, 5, 6],
                    ['DIN-601', 'Defensa Integral de la Nacion VI', 6, 3, 7],
                    ['SIM-701', 'Simulacion y Modelos', 7, 5, 1],
                    ['MIN-701', 'Metodologia de la Investigacion', 7, 4, 2],
                    ['GIN-701', 'Gerencia de la Informatica', 7, 4, 3],
                    ['RED-701', 'Redes', 7, 4, 4],
                    ['ISI-701', 'Implantacion de Sistemas', 7, 4, 5],
                    ['ELT-701', 'Electiva Tecnica', 7, 3, 6],
                    ['ELN-701', 'Electiva No Tecnica', 7, 3, 7],
                    ['DIN-701', 'Defensa Integral de la Nacion VII', 7, 3, 8],
                    ['TDE-801', 'Teoria de Decisiones', 8, 4, 1],
                    ['MLE-801', 'Marco Legal para el Ejercicio', 8, 4, 2],
                    ['TEL-801', 'Teleprocesos', 8, 4, 3],
                    ['AUS-801', 'Auditoria de Sistemas', 8, 4, 4],
                    ['ELT-801', 'Electiva Tecnica', 8, 3, 5],
                    ['ELN-801', 'Electiva No Tecnica', 8, 3, 6],
                    ['DIN-801', 'Defensa Integral de la Nacion VIII', 8, 3, 7],
                    ['PAS-901', 'Pasantia o TEG', 9, 10, 1],
                    ['ET-AS', 'Arquitectura de Software', null, 3, 1],
                    ['ET-IA', 'Inteligencia Artificial', null, 3, 2],
                    ['ET-RL', 'Redes de Area Local', null, 3, 3],
                    ['ET-SABD', 'Sistemas Avanzados de Bases de Datos', null, 3, 4]
                ];
                $stmt_m = $this->conn->prepare("INSERT INTO materia (codigo_materia, nombre_materia, semestre, uc, orden) VALUES (?, ?, ?, ?, ?)");
                foreach ($materias_seed as $m) {
                    $stmt_m->execute($m);
                }
            }

            // aqui guardamos que materias necesitas haber aprobado antes (prelaciones)
            $conteo_prel = $this->conn->query("SELECT COUNT(*) FROM prelacion")->fetchColumn();
            if ($conteo_prel == 0) {
                $prelaciones_seed = [
                    ['ALG-201', 'GEO-101'], ['ALG-201', 'MAT-101'], ['FIS-201', 'GEO-101'], ['FIS-201', 'MAT-101'],
                    ['ING-201', 'ING-101'], ['MAT-201', 'MAT-101'], ['SEM-201', 'SEM-101'],
                    ['FIS-301', 'FIS-201'], ['FIS-301', 'MAT-201'], ['MAT-301', 'MAT-201'], ['PRO-301', 'MAT-201'],
                    ['PRG-301', 'ALG-201'], ['CAL-401', 'MAT-301'], ['LP1-401', 'PRG-301'], ['LOG-401', 'PRO-301'],
                    ['PDA-401', 'PRG-301'], ['SPR-401', 'SAD-301'], ['ASI-501', 'TSI-401'], ['BD0-501', 'TSI-401'],
                    ['CIR-501', 'LOG-401'], ['INO-501', 'CAL-401'], ['LP2-501', 'LP1-401'], ['TGR-501', 'PRO-301'],
                    ['ARC-601', 'CIR-501'], ['DSI-601', 'ASI-501'], ['LP3-601', 'LP2-501'], ['ONL-601', 'INO-501'],
                    ['PES-601', 'INO-501'], ['SOP-601', 'BD0-501'], ['GIN-701', 'ARC-601'], ['ISI-701', 'DSI-601'],
                    ['RED-701', 'SOP-601'], ['SIM-701', 'ONL-601'], ['SIM-701', 'PES-601'], ['AUS-801', 'ISI-701'],
                    ['TEL-801', 'RED-701'], ['TDE-801', 'SIM-701'], ['DIN-201', 'DIN-101'], ['DIN-301', 'DIN-201'],
                    ['DIN-401', 'DIN-301'], ['DIN-501', 'DIN-401'], ['DIN-601', 'DIN-501'], ['DIN-701', 'DIN-601'],
                    ['DIN-801', 'DIN-701']
                ];
                $stmt_p = $this->conn->prepare("INSERT INTO prelacion (id_materia, id_prerrequisito) VALUES ((SELECT id FROM materia WHERE codigo_materia = ? LIMIT 1), (SELECT id FROM materia WHERE codigo_materia = ? LIMIT 1))");
                foreach ($prelaciones_seed as $p) {
                    $stmt_p->execute($p);
                }
            }

            // aqui guardamos preguntas de seguridad comunes
            $conteo = $this->conn->query("SELECT COUNT(*) FROM preguntas_seguridad")->fetchColumn();
            if ($conteo == 0) {
                $preguntas_seed = [
                    ['En que ciudad naciste?'],
                    ['Cual es el primer nombre de tu madre?'],
                    ['Como se llamaba tu primer colegio?'],
                    ['Como se llamaba tu primera mascota?'],
                    ['Cual es tu segundo nombre?'],
                    ['En que mes es tu cumpleanos?'],
                    ['Cual es tu fruta favorita?'],
                    ['Que idioma hablas en casa?'],
                    ['Cual es tu color favorito?']
                ];
                $stmt_q = $this->conn->prepare("INSERT INTO preguntas_seguridad (texto_pregunta) VALUES (?)");
                foreach ($preguntas_seed as $pq) {
                    $stmt_q->execute($pq);
                }
            }

            // creamos la cuenta por defecto de la coordinadora para pruebas iniciales
            $clave_seed = password_hash('12345678', PASSWORD_BCRYPT);
            $verificar_coord = $this->conn->query("SELECT COUNT(*) FROM usuario WHERE correo = 'coordinadora@unefa.edu.ve'")->fetchColumn();
            if ($verificar_coord == 0) {
                $this->conn->exec("INSERT INTO perfil (tipo_documento, cedula, nombre, apellido) VALUES ('V', '12345678', 'Coordinadora', 'UNEFA')");
                $id_perfil_coord = $this->conn->lastInsertId();
                $this->conn->exec("INSERT INTO usuario (id_perfil, correo, clave, rol, estado) VALUES ($id_perfil_coord, 'coordinadora@unefa.edu.ve', '$clave_seed', 'Coordinador', 'Aprobado')");
                $this->conn->exec("UPDATE perfil SET id_usuario = (SELECT id FROM usuario WHERE id_perfil = $id_perfil_coord) WHERE id = $id_perfil_coord");
            }

            // tabla para manejar los periodos academicos como 2026-I
            $this->conn->exec("CREATE TABLE IF NOT EXISTS `periodo_academico` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `nombre` varchar(50) NOT NULL,
                `fecha_inicio` date NOT NULL,
                `fecha_fin` date DEFAULT NULL,
                `estado` varchar(20) NOT NULL DEFAULT 'Planificado',
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            try { $this->conn->exec("ALTER TABLE `periodo_academico` ADD `estado` varchar(20) NOT NULL DEFAULT 'Planificado' AFTER `fecha_fin`"); } catch (PDOException $e) { if (strpos($e->getMessage(), 'Duplicate') === false) throw $e; }
            try { $this->conn->exec("ALTER TABLE `periodo_academico` ADD `semanas_duracion` int(11) NOT NULL DEFAULT 16 AFTER `fecha_fin`"); } catch (PDOException $e) { if (strpos($e->getMessage(), 'Duplicate') === false) throw $e; }
            try { $this->conn->exec("ALTER TABLE `sesion_clase` ADD `numero_semana` int(11) DEFAULT NULL AFTER `tipo_sesion`"); } catch (PDOException $e) { if (strpos($e->getMessage(), 'Duplicate') === false) throw $e; }
            try { $this->conn->exec("ALTER TABLE `sesion_clase` ADD `id_periodo` int(11) DEFAULT NULL AFTER `numero_semana`"); } catch (PDOException $e) { if (strpos($e->getMessage(), 'Duplicate') === false) throw $e; }
            try {
                $this->conn->exec("UPDATE `periodo_academico` SET `estado` = 'Activo' WHERE `activo` = 1 AND `estado` = 'Planificado'");
                $this->conn->exec("ALTER TABLE `periodo_academico` DROP COLUMN `activo`");
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'check that column/key exists') === false && strpos($e->getMessage(), 'Unknown column') === false) throw $e;
            }
            try { $this->conn->exec("ALTER TABLE `solicitud_inscripcion` ADD `id_periodo` int(11) DEFAULT NULL AFTER `id_seccion`"); } catch (PDOException $e) { if (strpos($e->getMessage(), 'Duplicate') === false) throw $e; }
            try { $this->conn->exec("ALTER TABLE `solicitud_inscripcion` ADD CONSTRAINT `solicitud_inscripcion_ibfk_3` FOREIGN KEY (`id_periodo`) REFERENCES `periodo_academico` (`id`) ON DELETE SET NULL"); } catch (PDOException $e) { if (strpos($e->getMessage(), 'Duplicate') === false) throw $e; }

            $countPeriodos = $this->conn->query("SELECT COUNT(*) FROM periodo_academico")->fetchColumn();
            if ($countPeriodos == 0) {
                $currentYear = date('Y');
                $this->conn->exec("INSERT INTO periodo_academico (nombre, fecha_inicio, estado) VALUES ('{$currentYear}-I', '{$currentYear}-01-01', 'Activo')");
            }

            // tabla para el cronograma de eventos y fechas importantes de la universidad
            $this->conn->exec("CREATE TABLE IF NOT EXISTS `cronograma_evento` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `anio` int(11) NOT NULL,
                `categoria` varchar(50) NOT NULL,
                `periodo` varchar(100) NOT NULL,
                `descripcion` varchar(300) NOT NULL,
                `fecha_inicio` date NOT NULL,
                `fecha_fin` date NOT NULL,
                `id_periodo` int(11) DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `anio` (`anio`),
                KEY `categoria` (`categoria`),
                KEY `id_periodo` (`id_periodo`),
                CONSTRAINT `cronograma_evento_ibfk_periodo` FOREIGN KEY (`id_periodo`) REFERENCES `periodo_academico` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $this->conn->exec("SET time_zone = '-04:00';");
        } catch(PDOException $excepcion) {
            die("Error de Base de Datos: " . $excepcion->getMessage());
        }
        return $this->conn;
    }
}
