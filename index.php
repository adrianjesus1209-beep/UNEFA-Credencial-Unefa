<?php
// aqui empieza el corazon de todo el sistema siceu de la unefa.
// este archivo recibe todas las visitas, decide que pantalla mostrar o que accion ejecutar
// (como iniciar sesion, guardar una foto, tomar asistencia o inscribir materias).
ob_start();
session_start();

// aqui cuidamos la sesion del usuario para que nadie pueda robar su conexion
if (!isset($_SESSION['INIT'])) {
    session_regenerate_id(true);
    $_SESSION['INIT'] = true;
}
date_default_timezone_set('America/Caracas');
define('URLROOT', 'public');
require_once 'app/core/Database.php';
require_once 'app/controllers/AuthController.php';
require_once 'app/helpers/AuditHelper.php';
require_once 'app/helpers/PeriodoHelper.php';
require_once 'app/helpers/ReportesPDF.php';

// este codigo secreto csrf sirve como un sello de seguridad para que ningun atacante envie formularios falsos
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// esta funcion revisa si una computadora ha intentado poner claves malas muchas veces para bloquearla por 15 minutos
function verificarRateLimit($conexion, $tipo = 'login', $max_intentos = 10, $ventana_minutos = 15) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt = $conexion->prepare("SELECT COUNT(*) FROM intentos_fallidos WHERE direccion_ip = :ip AND tipo = :tipo AND fecha_intento > DATE_SUB(NOW(), INTERVAL :ventana MINUTE)");
    $stmt->execute([':ip' => $ip, ':tipo' => $tipo, ':ventana' => $ventana_minutos]);
    return (int)$stmt->fetchColumn() >= $max_intentos;
}

// esta funcion anota en la base de datos cuando alguien se equivoca al poner su clave
function registrarIntentoFallido($conexion, $tipo = 'login', $identificador = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt = $conexion->prepare("INSERT INTO intentos_fallidos (direccion_ip, tipo, identificador) VALUES (:ip, :tipo, :ident)");
    $stmt->execute([':ip' => $ip, ':tipo' => $tipo, ':ident' => $identificador]);
}

// esta funcion borra los intentos fallidos cuando la persona logra escribir su clave correctamente
function limpiarIntentosFallidos($conexion, $tipo = 'login') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt = $conexion->prepare("DELETE FROM intentos_fallidos WHERE direccion_ip = :ip AND tipo = :tipo");
    $stmt->execute([':ip' => $ip, ':tipo' => $tipo]);
}

// esta funcion convierte el numero del semestre a un texto amigable como 1ro, 2do o CINU
function labelSemestre($semestre_actual) {
    $labels = [0 => 'CINU', 1 => '1ro', 2 => '2do', 3 => '3ro', 4 => '4to', 5 => '5to', 6 => '6to', 7 => '7mo', 8 => '8vo'];
    $idx = (int)($semestre_actual ?? 0);
    return $labels[$idx] ?? ($idx + 1) . 'to';
}

// esta funcion revisa si el usuario tiene permiso de coordinacion (coordinador, director o super admin)
function tieneRolCoordinacion() {
    $rol = $_SESSION['rol_usuario'] ?? '';
    return in_array($rol, ['Coordinador Academico', 'Coordinador', 'Director', 'Super Administrador'], true);
}

// esta funcion revisa si el usuario tiene permiso de profesor o docente
function tieneRolDocencia() {
    $rol = $_SESSION['rol_usuario'] ?? '';
    return in_array($rol, ['Profesor', 'Docente', 'Coordinador Academico', 'Coordinador', 'Director', 'Super Administrador'], true);
}

// aqui revisamos que cualquier formulario enviado por el usuario tenga el sello de seguridad valido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['action'])) {
    $token_enviado = $_POST['csrf_token'] ?? '';
    if (empty($token_enviado) || !hash_equals($_SESSION['csrf_token'], $token_enviado)) {
        $_SESSION['error'] = "Error de seguridad: Token CSRF invalido. Intente de nuevo.";
        header('Location: index.php?page=login');
        exit;
    }
}

$base_datos = new Database();
$conexion = $base_datos->getConnection();
$controlador_auth = new AuthController($conexion);

// si el usuario ya inicio sesion, revisamos en la base de datos que no haya sido desactivado
if (isset($_SESSION['id_usuario'])) {
    $stmtCheck = $conexion->prepare("SELECT estado, rol FROM usuario WHERE id = :id LIMIT 1");
    $stmtCheck->execute([':id' => $_SESSION['id_usuario']]);
    $uRow = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    if ($uRow && $uRow['estado'] === 'Inactivo') {
        session_destroy();
        header('Location: index.php?page=login');
        exit;
    }
    if ($uRow) {
        $_SESSION['rol_usuario'] = Usuario::normalizarRol($uRow['rol']);
    }
}

// esta funcion revisa si el semestre actual esta en estado activo para permitir inscribir materias
function periodoPermiteInscripciones($conexion) {
    $stmt = $conexion->query("SELECT id, estado, nombre FROM periodo_academico ORDER BY FIELD(estado, 'Activo', 'Planificado', 'Finalizado') LIMIT 1");
    $periodo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$periodo) {
        $_SESSION['error'] = "No hay un periodo academico configurado.";
        return false;
    }
    if ($periodo['estado'] !== 'Activo') {
        $_SESSION['error'] = "El periodo academico «{$periodo['nombre']}» esta {$periodo['estado']}. No se permiten preinscripciones en este momento.";
        return false;
    }
    return $periodo['id'];
}

$accion = $_GET['action'] ?? null;
// aqui empieza el repartidor de acciones del sistema: procesa los formularios y peticiones
if ($accion) {
    switch ($accion) {
        // aqui van las acciones que solo puede hacer el director: nombrar o registrar a un coordinador academico
        // ============================================================
        case 'assign_coordinator':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['rol_usuario']) && in_array($_SESSION['rol_usuario'], ['Director', 'Super Administrador'])) {
                $csrf_token = $_POST['csrf_token'] ?? '';
                if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
                    header('Location: index.php?page=dashboard');
                    exit;
                }
                $id_carrera = intval($_POST['id_carrera'] ?? 1);
                $id_coordinador = intval($_POST['id_coordinador'] ?? 0);
                if ($id_carrera > 0 && $id_coordinador > 0) {
                    $stmtCheck = $conexion->prepare("SELECT id, correo FROM usuario WHERE id = :id AND rol IN ('Coordinador Academico', 'Coordinador') LIMIT 1");
                    $stmtCheck->execute([':id' => $id_coordinador]);
                    $coordInfo = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                    if ($coordInfo) {
                        $stmtUpd = $conexion->prepare("UPDATE carrera SET id_coordinador = :idc WHERE id = :id");
                        $stmtUpd->execute([':idc' => $id_coordinador, ':id' => $id_carrera]);
                        AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Asignar Coordinador', "El Director asigno al Coordinador ID $id_coordinador ({$coordInfo['correo']}) a Ingenieria de Sistemas");
                        $_SESSION['success'] = "Coordinador Academico asignado exitosamente a la carrera Ingenieria de Sistemas.";
                    } else {
                        $_SESSION['error'] = "El usuario seleccionado no posee el rol de Coordinador Academico.";
                    }
                }
            }
            header('Location: index.php?page=dashboard');
            exit;
            break;

        case 'register_coordinator_by_director':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['rol_usuario']) && in_array($_SESSION['rol_usuario'], ['Director', 'Super Administrador'])) {
                $csrf_token = $_POST['csrf_token'] ?? '';
                if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
                    header('Location: index.php?page=dashboard');
                    exit;
                }
                require_once 'app/models/Usuario.php';
                $modelo = new Usuario($conexion);
                $datos = [
                    'tipo_documento'   => trim($_POST['tipo_documento'] ?? 'V'),
                    'cedula'           => trim(preg_replace('/[^0-9]/', '', $_POST['cedula'] ?? '')),
                    'nombre'           => trim($_POST['nombre'] ?? ''),
                    'segundo_nombre'   => '',
                    'apellido'         => trim($_POST['apellido'] ?? ''),
                    'segundo_apellido' => '',
                    'telefono'         => trim($_POST['telefono'] ?? ''),
                    'direccion'        => 'Sede UNEFA',
                    'correo'           => trim($_POST['correo'] ?? ''),
                    'nombre_usuario'   => trim($_POST['correo'] ?? ''),
                    'clave'            => $_POST['clave'] ?? '12345678',
                    'rol'              => 'Coordinador Academico',
                    'id_carrera'       => 1,
                    'estado'           => 'Aprobado',
                    'preguntas'        => []
                ];
                try {
                    if ($modelo->registrar($datos)) {
                        AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Registrar Coordinador', "El Director registro nuevo Coordinador Academico: {$datos['nombre']} {$datos['apellido']}");
                        $_SESSION['success'] = "Nuevo Coordinador Academico registrado y credencial QR generada.";
                    }
                } catch (Exception $e) {
                    $_SESSION['error'] = "Error al registrar Coordinador: " . $e->getMessage();
                }
            }
            header('Location: index.php?page=dashboard');
            exit;
            break;

        // aqui van las acciones del coordinador academico: profesores, horarios y secciones
        case 'register_teacher_coord':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && tieneRolCoordinacion()) {
                $csrf_token = $_POST['csrf_token'] ?? '';
                if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
                    header('Location: index.php?page=gestion_docentes');
                    exit;
                }
                $rol_docente = 'Profesor';
                require_once 'app/models/Usuario.php';
                $modelo = new Usuario($conexion);
                $datos = [
                    'tipo_documento'   => trim($_POST['tipo_documento'] ?? 'V'),
                    'cedula'           => trim(preg_replace('/[^0-9]/', '', $_POST['cedula'] ?? '')),
                    'nombre'           => trim($_POST['nombre'] ?? ''),
                    'segundo_nombre'   => '',
                    'apellido'         => trim($_POST['apellido'] ?? ''),
                    'segundo_apellido' => '',
                    'telefono'         => trim($_POST['telefono'] ?? ''),
                    'direccion'        => 'UNEFA Nucleo Guacara',
                    'correo'           => trim($_POST['correo'] ?? ''),
                    'nombre_usuario'   => trim($_POST['correo'] ?? ''),
                    'clave'            => $_POST['clave'] ?? '12345678',
                    'rol'              => $rol_docente,
                    'id_carrera'       => 1,
                    'estado'           => 'Aprobado',
                    'preguntas'        => []
                ];
                try {
                    $nuevo_id = $modelo->registrar($datos);
                    if ($nuevo_id) {
                        // aqui asignamos las materias marcadas al registrar al profesor nuevo
                        $materias = $_POST['materias'] ?? [];
                        if (!is_array($materias)) {
                            $materias = !empty($materias) ? [$materias] : [];
                        }
                        if (!empty($materias)) {
                            $stmtDM = $conexion->prepare("INSERT IGNORE INTO docente_materia (id_docente, id_materia, id_coordinador) VALUES (:doc, :mat, :coord)");
                            foreach ($materias as $mid) {
                                $mid = (int)$mid;
                                if ($mid > 0) {
                                    $stmtDM->execute([':doc' => $nuevo_id, ':mat' => $mid, ':coord' => $_SESSION['id_usuario']]);
                                }
                            }
                        }
                        $total_mat = count($materias);
                        AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Registro Profesor', "Registro Profesor: {$datos['nombre']} {$datos['apellido']} ({$datos['correo']}) con {$total_mat} materias asignadas");
                        $_SESSION['success'] = "Profesor registrado exitosamente con {$total_mat} materia(s) asignada(s). Recuerde tomar su fotografía oficial en Carnetización para habilitar su Carnet Digital.";
                    }
                } catch (Exception $e) {
                    $_SESSION['error'] = "Error al registrar: " . $e->getMessage();
                }
            }
            $returnUrl = !empty($_POST['return_url']) ? $_POST['return_url'] : 'index.php?page=gestion_docentes';
            header('Location: ' . $returnUrl);
            exit;
            break;

        case 'assign_teacher_subjects':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && tieneRolCoordinacion()) {
                $csrf_token = $_POST['csrf_token'] ?? '';
                if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
                    header('Location: index.php?page=gestion_docentes');
                    exit;
                }
                $id_docente = intval($_POST['id_docente'] ?? 0);
                $materias   = $_POST['materias'] ?? [];
                if (!is_array($materias)) {
                    $materias = !empty($materias) ? [$materias] : [];
                }
                if ($id_docente > 0) {
                    $conexion->prepare("DELETE FROM docente_materia WHERE id_docente = ?")->execute([$id_docente]);
                    if (!empty($materias)) {
                        $stmtDM = $conexion->prepare("INSERT IGNORE INTO docente_materia (id_docente, id_materia, id_coordinador) VALUES (:doc, :mat, :coord)");
                        foreach ($materias as $mid) {
                            $mid = (int)$mid;
                            if ($mid > 0) {
                                $stmtDM->execute([':doc' => $id_docente, ':mat' => $mid, ':coord' => $_SESSION['id_usuario']]);
                            }
                        }
                    }
                    $total_mats = count($materias);
                    AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Asignar Materias a Profesor', "Actualizo materias del docente ID $id_docente ($total_mats materias)");
                    $_SESSION['success'] = "Asignaturas del profesor actualizadas exitosamente ($total_mats asignadas).";
                } else {
                    $_SESSION['error'] = "Profesor no valido.";
                }
            }
            $returnUrl = !empty($_POST['return_url']) ? $_POST['return_url'] : 'index.php?page=gestion_docentes';
            header('Location: ' . $returnUrl);
            exit;
            break;

        case 'toggle_teacher_status_coord':
            if (tieneRolCoordinacion()) {
                $csrf_token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
                if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
                    $_SESSION['error'] = 'Token CSRF invalido.';
                    header('Location: index.php?page=gestion_docentes');
                    exit;
                }

                $id_docente = intval($_POST['id_docente'] ?? $_GET['id_docente'] ?? 0);
                if ($id_docente > 0) {
                    $stmt = $conexion->prepare("SELECT u.id, u.estado, u.rol, p.nombre, p.apellido FROM usuario u JOIN perfil p ON u.id_perfil = p.id WHERE u.id = :id AND u.rol IN ('Profesor', 'Docente') LIMIT 1");
                    $stmt->execute([':id' => $id_docente]);
                    $docData = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($docData) {
                        $nuevoEstado = ($docData['estado'] === 'Aprobado') ? 'Inactivo' : 'Aprobado';
                        $stmtUpd = $conexion->prepare("UPDATE usuario SET estado = :estado WHERE id = :id");
                        $stmtUpd->execute([':estado' => $nuevoEstado, ':id' => $id_docente]);

                        $accionTxt = ($nuevoEstado === 'Inactivo') ? 'desactivo y oculto' : 'reactivo';
                        AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Estado Profesor', "Se $accionTxt al profesor {$docData['nombre']} {$docData['apellido']} (ID $id_docente) a estado $nuevoEstado");

                        $_SESSION['success'] = "Profesor {$docData['nombre']} {$docData['apellido']} " . ($nuevoEstado === 'Inactivo' ? 'desactivado y oculto del listado principal' : 'reactivado exitosamente') . ".";
                    } else {
                        $_SESSION['error'] = "Profesor no encontrado o no tiene rol docente.";
                    }
                }
            }
            $returnUrl = !empty($_POST['return_url']) ? $_POST['return_url'] : 'index.php?page=gestion_docentes';
            header('Location: ' . $returnUrl);
            exit;
            break;

        case 'create_section_schedule':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && tieneRolCoordinacion()) {
                $csrf_token = $_POST['csrf_token'] ?? '';
                if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
                    header('Location: index.php?page=gestion_docentes');
                    exit;
                }
                $id_materia     = intval($_POST['id_materia'] ?? 0);
                $nombre_seccion = trim($_POST['nombre_seccion'] ?? '');
                if (($nombre_seccion === '__OTRA__' || empty($nombre_seccion)) && !empty($_POST['nombre_seccion_custom'])) {
                    $nombre_seccion = trim($_POST['nombre_seccion_custom']);
                }
                $id_docente     = intval($_POST['id_docente'] ?? 0);
                $dia_semana     = trim($_POST['dia_semana'] ?? 'Lunes');
                $hora_inicio    = trim($_POST['hora_inicio'] ?? '07:30');
                $hora_fin       = trim($_POST['hora_fin'] ?? '09:50');
                $tolerancia     = max(0, min(120, intval($_POST['tolerancia_minutos'] ?? 15)));
                $aula           = trim($_POST['aula'] ?? 'Aula 01');

                if ($id_materia > 0 && $id_docente > 0 && $nombre_seccion !== '') {
                    $stmtPer = $conexion->query("SELECT id FROM periodo_academico WHERE estado = 'Activo' LIMIT 1");
                    $id_periodo = $stmtPer->fetchColumn() ?: 1;

                    // aqui guardamos que este profesor dicta esta materia en la tabla docente_materia
                    $conexion->prepare("INSERT IGNORE INTO docente_materia (id_docente, id_materia, id_coordinador) VALUES (:doc, :mat, :coord)")
                        ->execute([':doc' => $id_docente, ':mat' => $id_materia, ':coord' => $_SESSION['id_usuario']]);

                    $stmtIns = $conexion->prepare("INSERT INTO seccion (id_materia, id_docente, nombre_seccion, dia_semana, hora_inicio, hora_fin, aula, id_periodo, tolerancia_minutos) VALUES (:mat, :doc, :nom, :dia, :hi, :hf, :aula, :per, :tol)");
                    $stmtIns->execute([
                        ':mat'  => $id_materia,
                        ':doc'  => $id_docente,
                        ':nom'  => $nombre_seccion,
                        ':dia'  => $dia_semana,
                        ':hi'   => $hora_inicio,
                        ':hf'   => $hora_fin,
                        ':aula' => $aula,
                        ':per'  => $id_periodo,
                        ':tol'  => $tolerancia
                    ]);
                    $id_sec_nueva = $conexion->lastInsertId();

                    // aqui dejamos creada la sesion del dia de hoy lista para pasar asistencia
                    $stmtSes = $conexion->prepare("INSERT INTO sesion_clase (id_seccion, fecha_clase, tipo_sesion, abierta_por, estado, observaciones, hora_inicio, hora_fin, tolerancia_minutos) VALUES (:sec, CURDATE(), 'Regular', :usr, 'Abierta', 'Sesion de Clase Regular', :hi, :hf, :tol)");
                    $stmtSes->execute([
                        ':sec' => $id_sec_nueva, 
                        ':usr' => $_SESSION['id_usuario'],
                        ':hi'  => $hora_inicio,
                        ':hf'  => $hora_fin,
                        ':tol' => $tolerancia
                    ]);

                    AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Registrar Horario', "Creo seccion $nombre_seccion ($dia_semana $hora_inicio-$hora_fin, tol: {$tolerancia}m, $aula) para docente ID $id_docente");
                    $_SESSION['success'] = "Horario y seccion «{$nombre_seccion}» registrados correctamente con {$tolerancia} min de chance limite.";
                } else {
                    $_SESSION['error'] = "Complete todos los datos obligatorios del horario.";
                }
            }
            $returnUrl = !empty($_POST['return_url']) ? $_POST['return_url'] : 'index.php?page=gestion_docentes';
            header('Location: ' . $returnUrl);
            exit;
            break;

        case 'enroll_student_section':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && tieneRolCoordinacion()) {
                $csrf_token = $_POST['csrf_token'] ?? '';
                if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
                    header('Location: index.php?page=gestion_docentes');
                    exit;
                }
                $id_estudiante = intval($_POST['id_estudiante'] ?? 0);
                $id_seccion    = intval($_POST['id_seccion'] ?? 0);
                if ($id_estudiante > 0 && $id_seccion > 0) {
                    $stmtPer = $conexion->query("SELECT id FROM periodo_academico WHERE estado = 'Activo' LIMIT 1");
                    $id_periodo = $stmtPer->fetchColumn() ?: 1;

                    $stmtIns = $conexion->prepare("INSERT IGNORE INTO inscripcion_seccion (id_estudiante, id_seccion, id_periodo, estado) VALUES (:est, :sec, :per, 'Inscrito')");
                    $stmtIns->execute([':est' => $id_estudiante, ':sec' => $id_seccion, ':per' => $id_periodo]);
                    require_once 'app/models/Usuario.php';
                    $modeloU = new Usuario($conexion);
                    $modeloU->generarCredencialQR($id_estudiante);
                    AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Asignar Estudiante a Seccion', "Asigno estudiante ID $id_estudiante a seccion ID $id_seccion");
                    $_SESSION['success'] = "Estudiante asignado exitosamente a la seccion.";
                }
            }
            if (!empty($_POST['return_asistencias'])) {
                header('Location: index.php?page=asistencias&section_id=' . intval($_POST['id_seccion'] ?? 0));
            } else {
                header('Location: index.php?page=gestion_docentes');
            }
            exit;
            break;

        case 'update_teacher_schedule':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && tieneRolDocencia()) {
                $csrf_token = $_POST['csrf_token'] ?? '';
                if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
                    $_SESSION['error'] = "Token de seguridad invalido.";
                    header('Location: ' . ($_POST['return_url'] ?? 'index.php?page=dashboard'));
                    exit;
                }
                $id_seccion        = intval($_POST['id_seccion'] ?? 0);
                $hora_inicio       = trim($_POST['hora_inicio'] ?? '');
                $hora_fin          = trim($_POST['hora_fin'] ?? '');
                $tolerancia        = max(0, min(120, intval($_POST['tolerancia_minutos'] ?? 15)));
                $aula              = trim($_POST['aula'] ?? '');
                $dia_semana        = trim($_POST['dia_semana'] ?? '');
                $id_docente        = intval($_POST['id_docente'] ?? 0);
                $nombre_seccion    = trim($_POST['nombre_seccion'] ?? '');
                if (($nombre_seccion === '__OTRA__' || empty($nombre_seccion)) && !empty($_POST['nombre_seccion_custom'])) {
                    $nombre_seccion = trim($_POST['nombre_seccion_custom']);
                }

                if ($id_seccion > 0 && !empty($hora_inicio) && !empty($hora_fin)) {
                    // Validar permisos del docente sobre esta seccion si no es coordinador
                    if (!tieneRolCoordinacion()) {
                        $stmtCheck = $conexion->prepare("SELECT id FROM seccion WHERE id = :sec AND id_docente = :doc");
                        $stmtCheck->execute([':sec' => $id_seccion, ':doc' => $_SESSION['id_usuario']]);
                        if (!$stmtCheck->fetch()) {
                            $_SESSION['error'] = "No tienes permisos para modificar el horario de esta seccion.";
                            header('Location: ' . ($_POST['return_url'] ?? 'index.php?page=dashboard'));
                            exit;
                        }
                    }

                    $setClauses = ["hora_inicio = :hi", "hora_fin = :hf", "tolerancia_minutos = :tol"];
                    $paramsUp = [
                        ':hi'  => $hora_inicio,
                        ':hf'  => $hora_fin,
                        ':tol' => $tolerancia,
                        ':sec' => $id_seccion
                    ];
                    if (!empty($aula)) {
                        $setClauses[] = "aula = :aula";
                        $paramsUp[':aula'] = $aula;
                    }
                    if (tieneRolCoordinacion() && !empty($dia_semana)) {
                        $setClauses[] = "dia_semana = :dia";
                        $paramsUp[':dia'] = $dia_semana;
                    }
                    if (tieneRolCoordinacion() && $id_docente > 0) {
                        $setClauses[] = "id_docente = :doc";
                        $paramsUp[':doc'] = $id_docente;
                    }
                    if (tieneRolCoordinacion() && !empty($nombre_seccion) && $nombre_seccion !== '__OTRA__') {
                        $setClauses[] = "nombre_seccion = :nom_sec";
                        $paramsUp[':nom_sec'] = $nombre_seccion;
                    }

                    $sqlUp = "UPDATE seccion SET " . implode(", ", $setClauses) . " WHERE id = :sec";
                    $stmtUpSec = $conexion->prepare($sqlUp);
                    $stmtUpSec->execute($paramsUp);

                    // Sincronizar sesion_clase de hoy si existe y esta abierta
                    $stmtUpSes = $conexion->prepare("UPDATE sesion_clase SET hora_inicio = :hi, hora_fin = :hf, tolerancia_minutos = :tol WHERE id_seccion = :sec AND fecha_clase = CURDATE() AND estado = 'Abierta'");
                    $stmtUpSes->execute([
                        ':hi'  => $hora_inicio,
                        ':hf'  => $hora_fin,
                        ':tol' => $tolerancia,
                        ':sec' => $id_seccion
                    ]);

                    AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Configurar Horario y Tolerancia', "Actualizo horario seccion ID $id_seccion: $dia_semana Entrada $hora_inicio, Salida $hora_fin, Tolerancia $tolerancia min");
                    $_SESSION['success'] = "Horario semanal configurado exitosamente: Entrada $hora_inicio, Salida $hora_fin con chance limite de $tolerancia minutos.";
                } else {
                    $_SESSION['error'] = "Datos de horario incompletos.";
                }
            }
            $return_url = $_POST['return_url'] ?? 'index.php?page=dashboard';
            header('Location: ' . $return_url);
            exit;
            break;

        case 'delete_section_schedule':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && tieneRolCoordinacion()) {
                $csrf_token = $_POST['csrf_token'] ?? '';
                if (hash_equals($_SESSION['csrf_token'], $csrf_token)) {
                    $id_sec = intval($_POST['id_seccion'] ?? 0);
                    if ($id_sec > 0) {
                        // revisamos si esta clase ya tiene asistencias guardadas para no borrar el historial
                        $stmtAsist = $conexion->prepare("SELECT COUNT(*) FROM asistencia a JOIN sesion_clase sc ON a.id_sesion = sc.id WHERE sc.id_seccion = :id");
                        $stmtAsist->execute([':id' => $id_sec]);
                        $totalAsist = (int)$stmtAsist->fetchColumn();

                        if ($totalAsist > 0) {
                            $stmtDel = $conexion->prepare("UPDATE seccion SET estado = 'Inactiva' WHERE id = :id");
                            $stmtDel->execute([':id' => $id_sec]);
                            AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Desactivar Horario', "Desactivo seccion ID $id_sec (historial de asistencia conservado)");
                            $_SESSION['success'] = "Clase retirada del horario semanal (se conservo el registro previo de asistencias).";
                        } else {
                            // si no tiene ninguna asistencia tomada, se puede borrar por completo
                            $conexion->prepare("DELETE FROM asistencia WHERE id_sesion IN (SELECT id FROM sesion_clase WHERE id_seccion = :id)")->execute([':id' => $id_sec]);
                            $conexion->prepare("DELETE FROM sesion_clase WHERE id_seccion = :id")->execute([':id' => $id_sec]);
                            $conexion->prepare("DELETE FROM inscripcion_seccion WHERE id_seccion = :id")->execute([':id' => $id_sec]);
                            $stmtDel = $conexion->prepare("DELETE FROM seccion WHERE id = :id");
                            $stmtDel->execute([':id' => $id_sec]);
                            AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Eliminar Horario', "Elimino seccion ID $id_sec permanentemente");
                            $_SESSION['success'] = "Clase eliminada del horario semanal exitosamente.";
                        }
                    }
                }
            }
            $return_url = $_POST['return_url'] ?? 'index.php?page=gestion_docentes';
            header('Location: ' . $return_url);
            exit;
            break;

        // aqui van las acciones de asistencia: escanear codigos qr o pasar lista a mano
        case 'scan_qr_attendance':
            header('Content-Type: application/json');
            if (!tieneRolDocencia()) {
                echo json_encode(['success' => false, 'message' => 'Sin permisos para registrar asistencia.']);
                exit;
            }
            $body = json_decode(file_get_contents('php://input'), true);
            $csrf_in = $body['csrf_token'] ?? '';
            if (!hash_equals($_SESSION['csrf_token'], $csrf_in)) {
                echo json_encode(['success' => false, 'message' => 'Token CSRF invalido.']);
                exit;
            }
            $id_seccion = intval($body['section_id'] ?? ($body['id_seccion'] ?? 0));
            $fecha = trim($body['fecha'] ?? date('Y-m-d'));
            $id_sesion = intval($body['id_sesion'] ?? 0);
            $qr_data = trim($body['qr_content'] ?? ($body['qr_data'] ?? ''));

            if ($id_seccion <= 0 || $qr_data === '') {
                echo json_encode(['success' => false, 'message' => 'Datos de seccion o QR incompletos.']);
                exit;
            }

            // revisamos que el semestre academico este activo antes de dejar pasar asistencia
            $valCiclo = PeriodoHelper::validarRegistroAsistencia($conexion, $fecha);
            if (!$valCiclo['permitido']) {
                echo json_encode(['success' => false, 'message' => $valCiclo['mensaje']]);
                exit;
            }
            $numSemanaCiclo = (int)($valCiclo['semana'] ?? 1);
            $idPeriodoCiclo = (int)($valCiclo['periodo']['id'] ?? null);

            // aqui buscamos o creamos la sesion de clase de hoy para guardar la lista
            if ($id_sesion <= 0) {
                $stmtSes = $conexion->prepare("SELECT id FROM sesion_clase WHERE id_seccion = :sec AND fecha_clase = :fecha LIMIT 1");
                $stmtSes->execute([':sec' => $id_seccion, ':fecha' => $fecha]);
                $id_sesion = (int)$stmtSes->fetchColumn();
                if ($id_sesion <= 0) {
                    $stmtInsSes = $conexion->prepare("INSERT INTO sesion_clase (id_seccion, fecha_clase, tipo_sesion, numero_semana, id_periodo, abierta_por, estado, observaciones) VALUES (:sec, :fecha, 'Regular', :sem, :per, :usr, 'Abierta', :obs)");
                    $stmtInsSes->execute([
                        ':sec'   => $id_seccion,
                        ':fecha' => $fecha,
                        ':sem'   => $numSemanaCiclo,
                        ':per'   => $idPeriodoCiclo,
                        ':usr'   => $_SESSION['id_usuario'],
                        ':obs'   => "Sesion Semana {$numSemanaCiclo}"
                    ]);
                    $id_sesion = (int)$conexion->lastInsertId();
                } else {
                    $conexion->prepare("UPDATE sesion_clase SET numero_semana = :sem, id_periodo = :per WHERE id = :id AND (numero_semana IS NULL OR id_periodo IS NULL)")->execute([
                        ':sem' => $numSemanaCiclo,
                        ':per' => $idPeriodoCiclo,
                        ':id'  => $id_sesion
                    ]);
                }
            }

            // aqui identificamos al estudiante usando el codigo qr, su cedula o su correo
            $estudiante = null;
            $qr_normalizado = str_replace("\r\n", "\n", $qr_data);
            $stmtQr = $conexion->prepare("SELECT u.id, u.rol, u.estado, u.correo, p.nombre, p.segundo_nombre, p.apellido, p.segundo_apellido, p.cedula, p.tipo_documento, p.telefono, p.foto_perfil, p.fecha_carnetizacion FROM credencial_qr cq JOIN usuario u ON cq.id_usuario = u.id JOIN perfil p ON u.id_perfil = p.id WHERE (cq.qr_token = :qr1 OR cq.qr_datos = :qr2 OR REPLACE(cq.qr_datos, '\r\n', '\n') = :qr3) AND cq.estado = 'Activo' LIMIT 1");
            $stmtQr->execute([':qr1' => $qr_data, ':qr2' => $qr_data, ':qr3' => $qr_normalizado]);
            $estudiante = $stmtQr->fetch(PDO::FETCH_ASSOC);

            if (!$estudiante) {
                $cedula_buscar = '';
                if (preg_match('/Cedula\s*[:\-]?\s*[VEPM]?\s*\-?\s*([0-9]{5,12})/i', $qr_data, $mCed)) {
                    $cedula_buscar = $mCed[1];
                } elseif (preg_match('/\b([0-9]{6,10})\b/', $qr_data, $mNum)) {
                    $cedula_buscar = $mNum[1];
                } else {
                    $cedula_buscar = preg_replace('/[^0-9]/', '', $qr_data);
                }

                if ($cedula_buscar !== '') {
                    $stmtCed = $conexion->prepare("SELECT u.id, u.rol, u.estado, u.correo, p.nombre, p.segundo_nombre, p.apellido, p.segundo_apellido, p.cedula, p.tipo_documento, p.telefono, p.foto_perfil, p.fecha_carnetizacion FROM usuario u JOIN perfil p ON u.id_perfil = p.id WHERE p.cedula = :ced LIMIT 1");
                    $stmtCed->execute([':ced' => $cedula_buscar]);
                    $estudiante = $stmtCed->fetch(PDO::FETCH_ASSOC);
                }

                if (!$estudiante && preg_match('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $qr_data, $mMail)) {
                    $stmtMail = $conexion->prepare("SELECT u.id, u.rol, u.estado, u.correo, p.nombre, p.segundo_nombre, p.apellido, p.segundo_apellido, p.cedula, p.tipo_documento, p.telefono, p.foto_perfil, p.fecha_carnetizacion FROM usuario u JOIN perfil p ON u.id_perfil = p.id WHERE LOWER(u.correo) = LOWER(:correo) LIMIT 1");
                    $stmtMail->execute([':correo' => $mMail[1]]);
                    $estudiante = $stmtMail->fetch(PDO::FETCH_ASSOC);
                }
            }

            if (!$estudiante) {
                echo json_encode(['success' => false, 'message' => 'Codigo QR no reconocido en el sistema.']);
                exit;
            }

            if ($estudiante['rol'] === 'Aspirante' || $estudiante['estado'] !== 'Aprobado') {
                echo json_encode(['success' => false, 'message' => 'El usuario es un Aspirante no aprobado y no puede registrar asistencia.']);
                exit;
            }

            if ($estudiante['rol'] !== 'Estudiante') {
                echo json_encode(['success' => false, 'message' => "El usuario escaneado tiene rol de '{$estudiante['rol']}'. Solo los estudiantes pueden registrar asistencia."]);
                exit;
            }

            if (empty($estudiante['foto_perfil']) || $estudiante['foto_perfil'] === 'default.svg' || empty($estudiante['fecha_carnetizacion'])) {
                echo json_encode(['success' => false, 'message' => 'El estudiante aun no tiene el Carnet/QR aperturado (falta la toma de fotografia por parte del Coordinador Academico en la universidad).']);
                exit;
            }

            // aqui consultamos los datos de la materia y de la seccion activa
            $stmtSecInfo = $conexion->prepare("SELECT s.id, s.nombre_seccion, s.id_materia, m.nombre_materia, m.codigo_materia FROM seccion s JOIN materia m ON s.id_materia = m.id WHERE s.id = :sec LIMIT 1");
            $stmtSecInfo->execute([':sec' => $id_seccion]);
            $secInfo = $stmtSecInfo->fetch(PDO::FETCH_ASSOC);
            if (!$secInfo) {
                echo json_encode(['success' => false, 'message' => 'Seccion no encontrada o no disponible.']);
                exit;
            }

            $id_materia     = (int)$secInfo['id_materia'];
            $nombre_materia = $secInfo['nombre_materia'];
            $nombre_seccion = $secInfo['nombre_seccion'];
            $nombreEst      = trim($estudiante['nombre'] . ' ' . $estudiante['apellido']);
            $cedulaEst      = preg_replace('/[^0-9]/', '', $estudiante['cedula']);

            // primer chequeo: revisar si el estudiante de verdad esta inscrito en esta seccion
            $stmtCheckIns = $conexion->prepare("SELECT id, estado FROM inscripcion_seccion WHERE id_estudiante = :est AND id_seccion = :sec AND estado = 'Inscrito' LIMIT 1");
            $stmtCheckIns->execute([':est' => $estudiante['id'], ':sec' => $id_seccion]);
            $inscritoActual = $stmtCheckIns->fetch(PDO::FETCH_ASSOC);

            if (!$inscritoActual) {
                // revisamos si esta inscrito pero en otra seccion diferente de la misma materia
                $stmtOtraSec = $conexion->prepare("SELECT s2.nombre_seccion FROM inscripcion_seccion ins2 JOIN seccion s2 ON ins2.id_seccion = s2.id WHERE ins2.id_estudiante = :est AND s2.id_materia = :mat AND ins2.estado = 'Inscrito' LIMIT 1");
                $stmtOtraSec->execute([':est' => $estudiante['id'], ':mat' => $id_materia]);
                $otraSecNombre = $stmtOtraSec->fetchColumn();

                if ($otraSecNombre) {
                    $msgNoIns = "El alumno {$nombreEst} pertenece a la Seccion '{$otraSecNombre}' de {$nombre_materia}, no a esta seccion ({$nombre_seccion}). Solo los estudiantes inscritos pueden registrar asistencia.";
                } else {
                    $msgNoIns = "El alumno {$nombreEst} no esta inscrito en la materia {$nombre_materia} (Seccion {$nombre_seccion}). Solo los estudiantes debidamente inscritos pueden registrar asistencia.";
                }

                echo json_encode([
                    'success'     => false,
                    'message'     => $msgNoIns,
                    'no_inscrito' => true,
                    'estudiante'  => [
                        'nombre_completo' => $nombreEst,
                        'cedula'          => $cedulaEst
                    ]
                ]);
                exit;
            }

            // segundo chequeo: revisar si el estudiante ya habia pasado asistencia el dia de hoy
            $stmtAsistPrevia = $conexion->prepare("SELECT id, estado_asistencia, hora_registro, observacion FROM asistencia WHERE id_sesion = :ses AND id_estudiante = :est LIMIT 1");
            $stmtAsistPrevia->execute([':ses' => $id_sesion, ':est' => $estudiante['id']]);
            $asistPrevia = $stmtAsistPrevia->fetch(PDO::FETCH_ASSOC);

            if ($asistPrevia) {
                $horaPrevia = !empty($asistPrevia['hora_registro']) ? date('h:i A', strtotime($asistPrevia['hora_registro'])) : '--:--';
                $estadoPrevio = $asistPrevia['estado_asistencia'];
                $msgYaPaso = "El alumno {$nombreEst} ya paso asistencia el dia de hoy a las {$horaPrevia} (Registrado como: {$estadoPrevio}).";
                echo json_encode([
                    'success'            => false,
                    'message'            => $msgYaPaso,
                    'ya_paso_asistencia' => true,
                    'hora_previa'        => $horaPrevia,
                    'estado_previo'      => $estadoPrevio,
                    'estudiante'         => [
                        'nombre_completo' => $nombreEst,
                        'cedula'          => $cedulaEst
                    ]
                ]);
                exit;
            }

            // aqui consultamos la hora de inicio de la clase y cuantos minutos de chance tiene para llegar
            $stmtInfoHor = $conexion->prepare("SELECT s.hora_inicio, s.hora_fin, s.tolerancia_minutos, sc.hora_inicio AS ses_hi, sc.hora_fin AS ses_hf, sc.tolerancia_minutos AS ses_tol FROM seccion s LEFT JOIN sesion_clase sc ON sc.id = :ses WHERE s.id = :sec");
            $stmtInfoHor->execute([':ses' => $id_sesion, ':sec' => $id_seccion]);
            $infoH = $stmtInfoHor->fetch(PDO::FETCH_ASSOC);

            $horaInicioSec = $infoH['ses_hi'] ?: ($infoH['hora_inicio'] ?: '08:00:00');
            $toleranciaMin = intval($infoH['ses_tol'] ?? ($infoH['tolerancia_minutos'] ?? 15));

            $tsInicio = strtotime(date('Y-m-d ') . $horaInicioSec);
            $tsLimite = $tsInicio + ($toleranciaMin * 60);
            $horaLimiteTxt = date('h:i A', $tsLimite);
            $horaLlegadaTxt = date('h:i A');
            $ahora = time();

            $esTardio = ($ahora > $tsLimite);
            $estadoAsistencia = $esTardio ? 'Tardio' : 'Presente';
            $obsTolerancia = $esTardio 
                ? "Llegada con retraso ({$horaLlegadaTxt}) - Chance limite era {$horaLimiteTxt}"
                : "Llegada a tiempo ({$horaLlegadaTxt}) - Dentro del chance limite (Hasta {$horaLimiteTxt})";

            // aqui guardamos la asistencia en la base de datos (Presente o Tardio)
            $stmtAsist = $conexion->prepare("INSERT INTO asistencia (id_sesion, id_estudiante, estado_asistencia, metodo_registro, registrado_por, observacion, hora_registro) VALUES (:ses, :est, :estado, 'QR', :reg, :obs, NOW()) ON DUPLICATE KEY UPDATE estado_asistencia = :estado2, metodo_registro = 'QR', registrado_por = :reg2, observacion = :obs2, hora_registro = NOW()");
            $stmtAsist->execute([
                ':ses'     => $id_sesion,
                ':est'     => $estudiante['id'],
                ':estado'  => $estadoAsistencia,
                ':reg'     => $_SESSION['id_usuario'],
                ':obs'     => $obsTolerancia,
                ':estado2' => $estadoAsistencia,
                ':reg2'    => $_SESSION['id_usuario'],
                ':obs2'    => $obsTolerancia
            ]);

            // aqui cargamos el modelo del usuario para refrescar la credencial qr
            require_once 'app/models/Usuario.php';
            $modeloU = new Usuario($conexion);
            $modeloU->generarCredencialQR($estudiante['id']);
            $datosLimpios = $modeloU->obtenerDatosLimpiadosQR($estudiante['id']);

            // preparamos los datos limpios sin tildes ni simbolos para responder
            $nombreLimpio   = $datosLimpios['nombre_completo'] ?? Usuario::limpiarTextoSinEspeciales($estudiante['nombre'] . ' ' . $estudiante['apellido']);
            $cedulaLimpia   = $datosLimpios['cedula'] ?? preg_replace('/[^0-9]/', '', $estudiante['cedula']);
            $telefonoLimpio = $datosLimpios['telefono'] ?? (preg_replace('/[^0-9]/', '', $estudiante['telefono'] ?? '') ?: 'No registrado');
            $correoLimpio   = $datosLimpios['correo'] ?? $estudiante['correo'];
            $seccionLimpia  = $nombre_seccion;

            // anotamos en la bitacora de seguridad que se le tomo asistencia con exito
            AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Asistencia QR', "Registro asistencia por QR ($estadoAsistencia) a $nombreLimpio ($cedulaLimpia) en sesion ID $id_sesion. $obsTolerancia");

            // armamos el mensajito de texto para avisar si llego a tiempo o con retraso
            $msgResultado = $esTardio 
                ? "Asistencia registrada CON RETRASO (Llego: {$horaLlegadaTxt} - Limite era: {$horaLimiteTxt})" 
                : "Asistencia registrada A TIEMPO (Llego: {$horaLlegadaTxt} - Limite: {$horaLimiteTxt})";

            // le mandamos la respuesta al navegador en formato json para que la pantalla la muestre bonita
            echo json_encode([
                'success'           => true,
                'message'           => $msgResultado,
                'student_id'        => (int)$estudiante['id'],
                'estado_asistencia' => $estadoAsistencia,
                'es_tardio'         => $esTardio,
                'hora_llegada'      => $horaLlegadaTxt,
                'hora_limite'       => $horaLimiteTxt,
                'tolerancia_minutos'=> $toleranciaMin,
                'nombre_completo'   => $nombreLimpio,
                'titular'           => $nombreLimpio,
                'cedula'            => $cedulaLimpia,
                'telefono'          => $telefonoLimpio,
                'correo'            => $correoLimpio,
                'seccion'           => $seccionLimpia,
                'rol'               => $estudiante['rol'],
                'estudiante'        => $estudiante
            ]);
            exit;
            break;

        // ============================================================
        // ASISTENCIA MANUAL PUESTA A MANO POR EL PROFESOR
        // ============================================================
        case 'manual_attendance':
            // revisamos que quien envia los datos sea profesor o coordinador
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && tieneRolDocencia()) {
                // leemos el paquete de datos json enviado desde la pagina
                $rawInput = file_get_contents('php://input');
                $jsonBody = json_decode($rawInput, true);
                if (is_array($jsonBody) && isset($jsonBody['student_id'])) {
                    header('Content-Type: application/json');
                    $csrf_token = $jsonBody['csrf_token'] ?? '';
                    // llave secreta contra ataques
                    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
                        echo json_encode(['success' => false, 'message' => 'Token CSRF invalido.']);
                        exit;
                    }
                    $id_est = intval($jsonBody['student_id'] ?? 0);
                    $id_seccion = intval($jsonBody['section_id'] ?? 0);
                    $fecha = trim($jsonBody['fecha'] ?? date('Y-m-d'));
                    $estado = trim($jsonBody['estado'] ?? 'Presente');
                    $observacion = trim($jsonBody['observacion'] ?? '');

                    // verificamos que el estado sea uno de los permitidos
                    if ($id_est <= 0 || $id_seccion <= 0 || !in_array($estado, ['Presente', 'Tardio', 'Ausente', 'Justificado'], true)) {
                        echo json_encode(['success' => false, 'message' => 'Datos invalidos.']);
                        exit;
                    }

                    // comprobamos que la fecha este dentro del ciclo de clases activo
                    $valCiclo = PeriodoHelper::validarRegistroAsistencia($conexion, $fecha);
                    if (!$valCiclo['permitido']) {
                        echo json_encode(['success' => false, 'message' => $valCiclo['mensaje']]);
                        exit;
                    }
                    $numSemanaCiclo = (int)($valCiclo['semana'] ?? 1);
                    $idPeriodoCiclo = (int)($valCiclo['periodo']['id'] ?? null);

                    // revisamos si el alumno de verdad esta inscrito en esta materia y seccion
                    $stmtCheckMan = $conexion->prepare("SELECT id FROM inscripcion_seccion WHERE id_estudiante = :est AND id_seccion = :sec AND estado = 'Inscrito' LIMIT 1");
                    $stmtCheckMan->execute([':est' => $id_est, ':sec' => $id_seccion]);
                    if (!$stmtCheckMan->fetch()) {
                        echo json_encode(['success' => false, 'message' => 'El estudiante no esta formalmente inscrito en esta seccion.']);
                        exit;
                    }

                    // buscamos la sesion de clase de este dia o la creamos si es la primera vez
                    $stmtSes = $conexion->prepare("SELECT id FROM sesion_clase WHERE id_seccion = :sec AND fecha_clase = :fecha LIMIT 1");
                    $stmtSes->execute([':sec' => $id_seccion, ':fecha' => $fecha]);
                    $id_sesion = (int)$stmtSes->fetchColumn();
                    if ($id_sesion <= 0) {
                        $stmtInsSes = $conexion->prepare("INSERT INTO sesion_clase (id_seccion, fecha_clase, tipo_sesion, numero_semana, id_periodo, abierta_por, estado, observaciones) VALUES (:sec, :fecha, 'Regular', :sem, :per, :usr, 'Abierta', :obs)");
                        $stmtInsSes->execute([
                            ':sec'   => $id_seccion,
                            ':fecha' => $fecha,
                            ':sem'   => $numSemanaCiclo,
                            ':per'   => $idPeriodoCiclo,
                            ':usr'   => $_SESSION['id_usuario'],
                            ':obs'   => "Sesion Semana {$numSemanaCiclo}"
                        ]);
                        $id_sesion = (int)$conexion->lastInsertId();
                    } else {
                        $conexion->prepare("UPDATE sesion_clase SET numero_semana = :sem, id_periodo = :per WHERE id = :id AND (numero_semana IS NULL OR id_periodo IS NULL)")->execute([
                            ':sem' => $numSemanaCiclo,
                            ':per' => $idPeriodoCiclo,
                            ':id'  => $id_sesion
                        ]);
                    }

                    // guardamos o actualizamos la asistencia de ese estudiante
                    $stmtUp = $conexion->prepare("INSERT INTO asistencia (id_sesion, id_estudiante, estado_asistencia, metodo_registro, registrado_por, observacion, hora_registro) VALUES (:ses, :est, :est_val, 'Manual', :reg, :obs, NOW()) ON DUPLICATE KEY UPDATE estado_asistencia = :est_val2, metodo_registro = 'Manual', registrado_por = :reg2, observacion = :obs2, hora_registro = NOW()");
                    $stmtUp->execute([
                        ':ses'      => $id_sesion,
                        ':est'      => $id_est,
                        ':est_val'  => $estado,
                        ':reg'      => $_SESSION['id_usuario'],
                        ':obs'      => $observacion,
                        ':est_val2' => $estado,
                        ':reg2'     => $_SESSION['id_usuario'],
                        ':obs2'     => $observacion
                    ]);
                    // dejamos nota en el libro de auditoria
                    AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Asistencia Manual', "Marco estado '$estado' al estudiante ID $id_est en sesion ID $id_sesion");
                    echo json_encode(['success' => true]);
                    exit;
                }
            }
            header('Location: index.php?page=asistencias');
            exit;
            break;

        // ============================================================
        // DESCARGA DE HISTORIAL Y AUDITORIA EXCLUSIVA DEL SUPER ADMINISTRADOR
        // ============================================================
        case 'export_audit_log':
            // solo el super administrador tiene permiso de descargar este archivo
            if (!isset($_SESSION['rol_usuario']) || $_SESSION['rol_usuario'] !== 'Super Administrador') {
                $_SESSION['error'] = "Acceso denegado: La generacion de archivos de auditoria es exclusiva del Super Administrador.";
                header('Location: index.php?page=dashboard');
                exit;
            }
            $csrf_token = $_GET['csrf_token'] ?? '';
            if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
                header('Location: index.php?page=auditoria');
                exit;
            }
            // anotamos que el super administrador descargo la bitacora completa
            AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Exportar Archivo Auditoria', 'El Super Administrador genero archivo de registro de auditoria (.log)');
            $registros = AuditHelper::obtenerUltimos($conexion, 1000);
            
            // armamos el encabezado del archivo de texto plano
            $contenido_log = "================================================================================\n";
            $contenido_log .= "UNEFA - NUCLEO GUACARA | ARCHIVO DE REGISTRO DE AUDITORIA (BITACORA CENTRAL)\n";
            $contenido_log .= "Generado exclusivamente por: Super Administrador\n";
            $contenido_log .= "Fecha de generacion: " . date('d/m/Y H:i:s') . "\n";
            $contenido_log .= "================================================================================\n\n";
            // pegamos cada accion fila por fila
            foreach ($registros as $r) {
                $linea = sprintf(
                    "[%s] | ROL: %-22s | USUARIO: %-28s | IP: %-15s | ACCION: %-22s | DETALLE: %s\n",
                    $r['fecha_hora'],
                    $r['rol'] ?? 'Sistema',
                    $r['correo'] ?? 'Sistema',
                    $r['direccion_ip'] ?? '127.0.0.1',
                    $r['accion'],
                    $r['detalle'] ?? '-'
                );
                $contenido_log .= $linea;
            }
            // le decimos al navegador que esto es un archivo de texto descargable con extension punto log
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="auditoria_unefa_' . date('Ymd_His') . '.log"');
            echo $contenido_log;
            exit;
            break;

        // ============================================================
        // REPORTES EN PDF DEL SUPER ADMINISTRADOR
        // ============================================================
        case 'generate_pdf':
            // solo el super administrador puede sacar estos reportes en pdf
            if (!isset($_SESSION['rol_usuario']) || $_SESSION['rol_usuario'] !== 'Super Administrador') {
                $_SESSION['error'] = "Acceso denegado: Los reportes de auditoria son exclusivos del Super Administrador.";
                header('Location: index.php?page=dashboard');
                exit;
            }
            $tipo = $_GET['tipo'] ?? '';
            $preview = isset($_GET['preview']);
            $csrf_token = $_GET['csrf_token'] ?? '';
            if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
                header('Location: index.php?page=auditoria');
                exit;
            }
            // nombres bonitos para los archivos descargados segun el reporte
            $nombres = [
                'aspirantes'   => 'auditoria_aspirantes_validados_rechazados',
                'asistencias'  => 'auditoria_marcaje_asistencias_qr_manual',
                'cuentas'      => 'auditoria_cuentas_personal_docente_admin',
                'estudiantes'  => 'reporte_estudiantes',
                'docentes'     => 'reporte_profesores',
                'bitacora'     => 'bitacora_actividad'
            ];
            // que funcion llamar dentro de la clase encargada del pdf
            $metodos = [
                'aspirantes'   => 'reporteAuditoriaAspirantes',
                'asistencias'  => 'reporteAuditoriaAsistencias',
                'cuentas'      => 'reporteAuditoriaCuentas',
                'estudiantes'  => 'reporteEstudiantes',
                'docentes'     => 'reporteDocentes',
                'bitacora'     => 'reporteBitacora'
            ];
            if (!isset($nombres[$tipo])) {
                header('Location: index.php?page=auditoria');
                exit;
            }
            // creamos el documento en pdf en tamano carta horizontal
            $pdf = new ReportesPDF('L', 'mm', 'A4');
            $pdf->SetTitle($nombres[$tipo]);
            $contenido = $pdf->{$metodos[$tipo]}($conexion);
            AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Reporte PDF Auditoria', "El Super Administrador genero reporte PDF de $tipo");
            $dispo = $preview ? 'inline' : 'attachment';
            header('Content-Type: application/pdf');
            header("Content-Disposition: {$dispo}; filename=\"{$nombres[$tipo]}.pdf\"");
            echo $contenido;
            exit;
            break;

        // ============================================================
        // GUARDAR FOTO Y HABILITAR CARNET DE ESTUDIANTE
        // ============================================================
        case 'save_photo':
            // respondemos en formato json para que la camara web no recargue toda la pagina
            header('Content-Type: application/json');
            // solo el personal de coordinacion o direccion puede guardar la foto oficial
            if (!tieneRolCoordinacion()) {
                echo json_encode(['success' => false, 'message' => 'Sin permisos.']);
                exit;
            }
            // leemos los datos que envio el navegador con la foto en texto base64
            $body     = json_decode(file_get_contents('php://input'), true);
            $est_id   = intval($body['est_id']   ?? 0);
            $photoB64 = $body['photo']            ?? '';
            $csrf_in  = $body['csrf_token']       ?? '';
            // revisamos que la llave de seguridad sea correcta y que la foto no venga vacia
            if (!hash_equals($_SESSION['csrf_token'], $csrf_in) || $est_id === 0 || empty($photoB64)) {
                echo json_encode(['success' => false, 'message' => 'Datos invalidos.']);
                exit;
            }

            // comprobamos que el estudiante este formalmente aprobado antes de tomarle foto
            $stmtCheck = $conexion->prepare("SELECT estado FROM usuario WHERE id = :id LIMIT 1");
            $stmtCheck->execute([':id' => $est_id]);
            $estado_actual = $stmtCheck->fetchColumn();
            
            if ($estado_actual !== 'Aprobado') {
                echo json_encode(['success' => false, 'message' => 'No autorizado: El estudiante debe estar aprobado para ser carnetizado.']);
                exit;
            }

            // comprobamos que la foto venga en formato jpg o png
            if (!preg_match('/^data:image\/(jpeg|jpg|png);base64,/', $photoB64)) {
                echo json_encode(['success' => false, 'message' => 'Formato de imagen no permitido. Use JPG o PNG.']);
                exit;
            }

            // quitamos la cabecera del texto en base64 para convertirlo en archivo real
            $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $photoB64);
            $decoded   = base64_decode($imageData);
            if (!$decoded) {
                echo json_encode(['success' => false, 'message' => 'Imagen invalida.']);
                exit;
            }

            // carpeta donde se guardan las fotos de perfil de todos
            $dir = 'public/uploads/profiles/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);

            // buscamos la cedula para ponerle nombre al archivo y borrar la foto vieja si tenia
            $stmtOld = $conexion->prepare("SELECT p.foto_perfil, p.cedula FROM perfil p JOIN usuario u ON u.id_perfil = p.id WHERE u.id = :id LIMIT 1");
            $stmtOld->execute([':id' => $est_id]);
            $rowOld = $stmtOld->fetch(PDO::FETCH_ASSOC);
            $old = $rowOld['foto_perfil'] ?? null;
            $cedNum = preg_replace('/[^0-9]/', '', $rowOld['cedula'] ?? (string)$est_id);
            $filename = 'foto_' . $cedNum . '.jpg';
            $filepath = $dir . $filename;
            if ($old && $old !== 'default.svg' && file_exists($dir . $old)) @unlink($dir . $old);
            file_put_contents($filepath, $decoded);

            // guardamos tambien una copia en la carpeta propia de la cedula del alumno
            $dirCedula = 'public/uploads/' . $cedNum . '/';
            if (!is_dir($dirCedula)) mkdir($dirCedula, 0777, true);
            foreach (glob($dirCedula . 'foto_carnet*.*') ?: [] as $fOld) {
                if (is_file($fOld)) @unlink($fOld);
            }
            file_put_contents($dirCedula . 'foto_carnet.jpg', $decoded);

            // actualizamos la base de datos con el nombre del archivo y la fecha de hoy
            $stmtUp = $conexion->prepare("UPDATE perfil p JOIN usuario u ON u.id_perfil = p.id SET p.foto_perfil = :pic, p.fecha_carnetizacion = NOW() WHERE u.id = :id");
            $stmtUp->execute([':pic' => $filename, ':id' => $est_id]);

            // refrescamos la credencial qr del alumno para que incluya su nueva foto
            require_once 'app/models/Usuario.php';
            $modeloU = new Usuario($conexion);
            $modeloU->generarCredencialQR($est_id);

            // anotamos en el libro de auditoria que el alumno fue carnetizado
            AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Carnetizacion', "Tomo fotografia oficial y aperturo Carnet/QR al estudiante ID $est_id");
            echo json_encode(['success' => true]);
            exit;
            break;

        // ============================================================
        // GUARDAR FOTO Y HABILITAR CARNET DE PROFESOR O PERSONAL
        // ============================================================
        case 'save_teacher_photo':
            // respondemos en formato json para la ventanita modal de la camara
            header('Content-Type: application/json');
            // solo coordinacion o direccion pueden registrar fotos a los docentes
            if (!tieneRolCoordinacion()) {
                echo json_encode(['success' => false, 'message' => 'Sin permisos.']);
                exit;
            }
            $body     = json_decode(file_get_contents('php://input'), true);
            $doc_id   = intval($body['doc_id']   ?? 0);
            $photoB64 = $body['photo']            ?? '';
            $csrf_in  = $body['csrf_token']       ?? '';
            // revisamos que la llave de seguridad sea correcta
            if (!hash_equals($_SESSION['csrf_token'], $csrf_in) || $doc_id === 0 || empty($photoB64)) {
                echo json_encode(['success' => false, 'message' => 'Datos invalidos.']);
                exit;
            }

            // comprobamos que el archivo de imagen sea jpg o png
            if (!preg_match('/^data:image\/(jpeg|jpg|png);base64,/', $photoB64)) {
                echo json_encode(['success' => false, 'message' => 'Formato de imagen no permitido. Use JPG o PNG.']);
                exit;
            }

            // decodificamos el texto de la imagen para convertirlo en archivo real
            $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $photoB64);
            $decoded   = base64_decode($imageData);
            if (!$decoded) {
                echo json_encode(['success' => false, 'message' => 'Imagen invalida.']);
                exit;
            }
            $dir = 'public/uploads/profiles/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            // buscamos la cedula para guardarlo con su numero correspondiente
            $stmtOld = $conexion->prepare("SELECT p.foto_perfil, p.cedula FROM perfil p JOIN usuario u ON u.id_perfil = p.id WHERE u.id = :id LIMIT 1");
            $stmtOld->execute([':id' => $doc_id]);
            $rowOld = $stmtOld->fetch(PDO::FETCH_ASSOC);
            $old = $rowOld['foto_perfil'] ?? null;
            $cedNum = preg_replace('/[^0-9]/', '', $rowOld['cedula'] ?? (string)$doc_id);
            $filename = 'foto_' . $cedNum . '.jpg';
            $filepath = $dir . $filename;
            if ($old && $old !== 'default.svg' && file_exists($dir . $old)) @unlink($dir . $old);
            file_put_contents($filepath, $decoded);

            // guardamos tambien en la carpeta individual de la cedula del profesor
            $dirCedula = 'public/uploads/' . $cedNum . '/';
            if (!is_dir($dirCedula)) mkdir($dirCedula, 0777, true);
            foreach (glob($dirCedula . 'foto_carnet*.*') ?: [] as $fOld) {
                if (is_file($fOld)) @unlink($fOld);
            }
            file_put_contents($dirCedula . 'foto_carnet.jpg', $decoded);

            // actualizamos la tabla de perfiles con la nueva foto y la fecha de hoy
            $stmtUp = $conexion->prepare("UPDATE perfil p JOIN usuario u ON u.id_perfil = p.id SET p.foto_perfil = :pic, p.fecha_carnetizacion = NOW() WHERE u.id = :id");
            $stmtUp->execute([':pic' => $filename, ':id' => $doc_id]);

            // regeneramos la credencial qr del profesor para que aparezca con su foto
            require_once 'app/models/Usuario.php';
            $modeloU = new Usuario($conexion);
            $modeloU->generarCredencialQR($doc_id);
            // anotamos en el libro de auditoria
            AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Carnetizacion', "Tomo fotografia oficial y actualizo Carnet/QR al personal ID $doc_id");
            echo json_encode(['success' => true]);
            exit;
            break;

        // ============================================================
        // SIMULADOR DE VENCIMIENTO DEL CARNET PARA PRUEBAS
        // ============================================================
        case 'simulate_expiry':
            // respondemos en formato json para los botones de prueba del simulador
            header('Content-Type: application/json');
            if (!tieneRolCoordinacion()) {
                echo json_encode(['success' => false, 'message' => 'Sin permisos.']);
                exit;
            }
            $body   = json_decode(file_get_contents('php://input'), true);
            $est_id = intval($body['est_id'] ?? 0);
            $type   = $body['type'] ?? 'total';
            if ($est_id === 0) {
                echo json_encode(['success' => false, 'message' => 'ID invalido.']);
                exit;
            }
            // calculamos la fecha segun lo que queramos simular: hoy, hace 8 dias o hace 13 meses
            $date = $type === 'reset' ? date('Y-m-d H:i:s') : ($type === 'window' ? date('Y-m-d H:i:s', strtotime('-8 days')) : date('Y-m-d H:i:s', strtotime('-13 months')));
            $stmt = $conexion->prepare("UPDATE perfil p JOIN usuario u ON u.id_perfil = p.id SET p.fecha_carnetizacion = :date WHERE u.id = :id");
            $stmt->execute([':date' => $date, ':id' => $est_id]);
            echo json_encode(['success' => true]);
            exit;
            break;

        // ============================================================
        // ACCIONES DE INICIO DE SESION, REGISTRO Y DOCUMENTOS
        // ============================================================
        case 'login':
            // si intentaron entrar muchas veces mal bloqueamos por 15 minutos para que no adivinen contrasenas
            if (verificarRateLimit($conexion, 'login', 10, 15)) {
                $_SESSION['error'] = "Demasiados intentos fallidos. Espere 15 minutos.";
                header('Location: index.php?page=login');
                exit;
            }
            $controlador_auth->ingresar();
            break;
        case 'register':
            // solo permitimos que nuevos alumnos se registren si las inscripciones estan abiertas
            if (!periodoPermiteInscripciones($conexion)) {
                header('Location: index.php?page=login');
                exit;
            }
            $controlador_auth->registrar();
            break;
        case 'upload_docs': $controlador_auth->subirDocumentos(); break;
        case 'subir_individual': $controlador_auth->subirIndividual(); break;
        case 'reset_password':
            // protegemos contra demasiados intentos seguidos de cambiar clave
            if (verificarRateLimit($conexion, 'reset_password', 10, 15)) {
                $_SESSION['error'] = "Demasiados intentos de recuperacion. Espere 15 minutos.";
                header('Location: index.php?page=reset_password');
                exit;
            }
            $controlador_auth->resetPassword();
            break;
        case 'logout': $controlador_auth->salir(); break;

        // ============================================================
        // ACTUALIZAR DATOS DE UN ESTUDIANTE POR PARTE DE COORDINACION
        // ============================================================
        case 'update_student_profile':
            // solo el personal de coordinacion puede corregir datos de aspirantes o estudiantes
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && tieneRolCoordinacion()) {
                $csrf_token = $_POST['csrf_token'] ?? '';
                // verificamos la llave de seguridad
                if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
                    if (isset($_GET['ajax'])) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Error de seguridad.']);
                        exit;
                    }
                    header('Location: index.php?page=approve_registration');
                    exit;
                }
                $id_perfil = $_POST['id_perfil'] ?? null;
                // recogemos los datos limpiecitos
                $datos = [
                    'cedula' => trim($_POST['cedula'] ?? ''),
                    'nombre' => trim($_POST['nombre'] ?? ''),
                    'segundo_nombre' => trim($_POST['segundo_nombre'] ?? ''),
                    'apellido' => trim($_POST['apellido'] ?? ''),
                    'segundo_apellido' => trim($_POST['segundo_apellido'] ?? ''),
                    'telefono' => trim($_POST['telefono'] ?? ''),
                    'direccion' => trim($_POST['direccion'] ?? '')
                ];
                // revisamos que al menos no falte cedula nombre o apellido
                if ($id_perfil && !empty($datos['cedula']) && !empty($datos['nombre']) && !empty($datos['apellido'])) {
                    // buscamos la cedula vieja para ver si cambio
                    $stmtOld = $conexion->prepare("SELECT cedula, id_usuario FROM perfil WHERE id = :id LIMIT 1");
                    $stmtOld->execute([':id' => $id_perfil]);
                    $rowOldP = $stmtOld->fetch(PDO::FETCH_ASSOC);
                    $oldCedula = $rowOldP['cedula'] ?? null;
                    $uidPerfil = (int)($rowOldP['id_usuario'] ?? 0);

                    require_once 'app/models/Usuario.php';
                    $modelo = new Usuario($conexion);
                    $modelo->actualizarPerfilCompleto($id_perfil, $datos);

                    // si la cedula cambio le cambiamos el nombre a la carpetita de sus documentos para no perderlos
                    if ($oldCedula && $datos['cedula'] && $oldCedula !== $datos['cedula']) {
                        $oldNum = preg_replace('/[^0-9]/', '', $oldCedula);
                        $newNum = preg_replace('/[^0-9]/', '', $datos['cedula']);
                        $oldDir = 'public/uploads/' . $oldNum;
                        $newDir = 'public/uploads/' . $newNum;
                        if ($oldNum !== $newNum && is_dir($oldDir) && !is_dir($newDir)) {
                            @rename($oldDir, $newDir);
                        }
                        // actualizamos las rutas guardadas en la base de datos
                        $oldPattern = 'public/uploads/' . $oldNum . '/';
                        $newPattern = 'public/uploads/' . $newNum . '/';
                        $upd = $conexion->prepare("UPDATE registro_documentos SET ruta = REPLACE(ruta, :old, :new) WHERE ruta LIKE :like");
                        $upd->execute([':old' => $oldPattern, ':new' => $newPattern, ':like' => $oldPattern . '%']);
                    }
                    // si ya tenia usuario creado le refrescamos su credencial qr
                    if ($uidPerfil > 0) {
                        $modelo->generarCredencialQR($uidPerfil);
                    }
                    // anotamos en la bitacora
                    AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Actualizar Perfil', "Actualizo perfil ID $id_perfil ({$datos['nombre']} {$datos['apellido']})");
                    if (isset($_GET['ajax'])) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true]);
                        exit;
                    }
                    header('Location: index.php?page=approve_registration&msg=profile_updated');
                } else {
                    if (isset($_GET['ajax'])) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
                        exit;
                    }
                    $_SESSION['error'] = "Datos incompletos.";
                    header('Location: index.php?page=approve_registration');
                }
            }
            exit;
            break;

        // ============================================================
        // CAMBIAR ESTADO DE USUARIO ENTRE ACTIVO E INACTIVO
        // ============================================================
        case 'toggle_user_status':
            // respondemos con texto json para que el boton en pantalla cambie de color sin recargar
            header('Content-Type: application/json');
            if (!tieneRolCoordinacion()) {
                echo json_encode(['success' => false, 'message' => 'Sin permisos.']);
                exit;
            }
            $body = json_decode(file_get_contents('php://input'), true);
            $id   = intval($body['id'] ?? 0);
            $csrf = $body['csrf_token'] ?? '';
            // verificamos que la peticion sea legitima
            if (!hash_equals($_SESSION['csrf_token'], $csrf) || $id === 0) {
                echo json_encode(['success' => false, 'message' => 'Datos invalidos.']);
                exit;
            }
            // buscamos el rol y estado actual del usuario
            $stmt = $conexion->prepare("SELECT estado, rol FROM usuario WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($userData === false) {
                echo json_encode(['success' => false, 'message' => 'Usuario no encontrado.']);
                exit;
            }
            // regla de proteccion: nadie puede desactivar al director o al super administrador
            if (in_array($userData['rol'], ['Super Administrador', 'Director'], true)) {
                echo json_encode(['success' => false, 'message' => 'No puedes desactivar a una autoridad superior.']);
                exit;
            }
            // si estaba aprobado lo pasamos a inactivo y si estaba inactivo lo volvemos a activar
            $estado = $userData['estado'];
            $nuevo = ($estado === 'Aprobado') ? 'Inactivo' : 'Aprobado';
            $accion_lbl = ($nuevo === 'Inactivo') ? 'Desactivo' : 'Reincorporo';
            $modelo = new Usuario($conexion);
            $modelo->actualizarEstadoUsuario($id, $nuevo);
            // anotamos en la bitacora de seguridad
            AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Estado Usuario', "$accion_lbl al usuario ID $id ($estado -> $nuevo)");
            echo json_encode(['success' => true, 'nuevo_estado' => $nuevo]);
            exit;
            break;

        // ============================================================
        // APROBAR O RECHAZAR DOCUMENTO INDIVIDUAL DE UN ASPIRANTE
        // ============================================================
        case 'approve_document':
            // solo coordinacion puede revisar y dar el visto bueno a los requisitos
            if (tieneRolCoordinacion()) {
                $csrf_token = $_GET['csrf_token'] ?? '';
                if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
                    if (isset($_GET['ajax'])) {
                        echo json_encode(['success' => false, 'message' => 'Token CSRF invalido']);
                        exit;
                    }
                    header('Location: index.php?page=approve_registration');
                    exit;
                }
                $id_doc = $_GET['id'] ?? null;
                $estado = $_GET['status'] ?? null;
                // verificamos que la decision sea aprobado o rechazado
                if ($id_doc && in_array($estado, ['Aprobado', 'Rechazado'])) {
                    $u_model = new Usuario($conexion);
                    $obs = $_GET['obs'] ?? '';
                    $u_model->actualizarEstadoDocumento($id_doc, $estado, $obs);
                    AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Documento ' . $estado, "Documento ID $id_doc $estado" . ($obs ? " ($obs)" : ""));
                    if (isset($_GET['ajax'])) {
                        echo json_encode(['success' => true, 'status' => $estado]);
                        exit;
                    }
                }
            }
            header('Location: index.php?page=approve_registration');
            exit;
            break;

        // ============================================================
        // BUSQUEDA EN TIEMPO REAL DE ESTUDIANTES PARA CARNETIZAR
        // ============================================================
        case 'search_students_ajax':
            // respondemos con lista en formato json mientras el usuario va escribiendo
            header('Content-Type: application/json');
            if (!tieneRolCoordinacion()) {
                echo json_encode(['success' => false, 'message' => 'Sin permisos']);
                exit;
            }
            $busqueda = trim($_GET['q'] ?? '');
            $estudiantes = [];
            // si escribieron algo buscamos coincidencias por cedula nombre o apellido
            if ($busqueda !== '') {
                $like = "%{$busqueda}%";
                $stmt = $conexion->prepare("SELECT u.id, u.correo, p.nombre, p.segundo_nombre, p.apellido, p.segundo_apellido, p.cedula, p.tipo_documento, p.foto_perfil, p.fecha_carnetizacion FROM usuario u JOIN perfil p ON u.id_perfil = p.id WHERE u.rol = 'Estudiante' AND (p.cedula LIKE :q OR p.nombre LIKE :q OR p.segundo_nombre LIKE :q OR p.apellido LIKE :q OR p.segundo_apellido LIKE :q OR u.correo LIKE :q) ORDER BY p.apellido, p.nombre LIMIT 15");
                $stmt->execute([':q' => $like]);
                $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            echo json_encode(['success' => true, 'data' => $estudiantes]);
            exit;
            break;

        // ============================================================
        // CARGAR FICHA Y TARJETA DEL ESTUDIANTE SELECCIONADO
        // ============================================================
        case 'get_student_details_ajax':
            header('Content-Type: application/json');
            if (!tieneRolCoordinacion()) {
                echo json_encode(['success' => false, 'message' => 'Sin permisos']);
                exit;
            }
            $id = intval($_GET['id'] ?? 0);
            $stmt = $conexion->prepare("SELECT u.id, u.correo, u.estado, p.nombre, p.segundo_nombre, p.apellido, p.segundo_apellido, p.cedula, p.tipo_documento, p.foto_perfil, p.fecha_carnetizacion FROM usuario u JOIN perfil p ON u.id_perfil = p.id WHERE u.id = :id AND u.rol = 'Estudiante' LIMIT 1");
            $stmt->execute([':id' => $id]);
            $est_data = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$est_data) {
                echo json_encode(['success' => false, 'message' => 'Estudiante no encontrado']);
                exit;
            }
            // renderizamos la vista parcial con el circulo de la camara y la informacion
            ob_start();
            include 'app/views/coordinator/partials/student_card.php';
            $html = ob_get_clean();
            echo json_encode(['success' => true, 'data' => $est_data, 'html' => $html]);
            exit;
            break;

        // ============================================================
        // BUSQUEDA EN TIEMPO REAL DE PROFESORES Y PERSONAL
        // ============================================================
        case 'search_teachers_ajax':
            header('Content-Type: application/json');
            if (!tieneRolCoordinacion()) {
                echo json_encode(['success' => false, 'message' => 'Sin permisos']);
                exit;
            }
            $busqueda = trim($_GET['q'] ?? '');
            $docentes = [];
            // buscamos coincidencias entre los roles que dan clases o coordinan
            if ($busqueda !== '') {
                $like = "%{$busqueda}%";
                $stmt = $conexion->prepare("SELECT u.id, u.correo, u.rol, p.nombre, p.segundo_nombre, p.apellido, p.segundo_apellido, p.cedula, p.tipo_documento, p.foto_perfil, p.fecha_carnetizacion FROM usuario u JOIN perfil p ON u.id_perfil = p.id WHERE u.rol IN ('Profesor', 'Docente', 'Coordinador Academico', 'Director', 'Super Administrador') AND (p.cedula LIKE :q OR p.nombre LIKE :q OR p.segundo_nombre LIKE :q OR p.apellido LIKE :q OR p.segundo_apellido LIKE :q OR u.correo LIKE :q) ORDER BY p.apellido, p.nombre LIMIT 15");
                $stmt->execute([':q' => $like]);
                $docentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            echo json_encode(['success' => true, 'data' => $docentes]);
            exit;
            break;

        // ============================================================
        // CARGAR FICHA Y TARJETA DEL DOCENTE SELECCIONADO
        // ============================================================
        case 'get_teacher_details_ajax':
            header('Content-Type: application/json');
            if (!tieneRolCoordinacion()) {
                echo json_encode(['success' => false, 'message' => 'Sin permisos']);
                exit;
            }
            $id = intval($_GET['id'] ?? 0);
            $stmt = $conexion->prepare("SELECT u.id, u.correo, u.rol, p.nombre, p.segundo_nombre, p.apellido, p.segundo_apellido, p.cedula, p.tipo_documento, p.foto_perfil, p.fecha_carnetizacion FROM usuario u JOIN perfil p ON u.id_perfil = p.id WHERE u.id = :id AND u.rol IN ('Profesor', 'Docente', 'Coordinador Academico', 'Director', 'Super Administrador') LIMIT 1");
            $stmt->execute([':id' => $id]);
            $est_data = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$est_data) {
                echo json_encode(['success' => false, 'message' => 'Personal no encontrado']);
                exit;
            }
            // renderizamos la ficha del profesor con los botones de camara y opciones
            ob_start();
            include 'app/views/coordinator/partials/teacher_card.php';
            $html = ob_get_clean();
            echo json_encode(['success' => true, 'data' => $est_data, 'html' => $html]);
            exit;
            break;

        // ============================================================
        // ACTUALIZAR PERFIL PROPIO Y CAMBIAR CONTRASENA
        // ============================================================
        case 'update_own_profile':
            // nadie puede modificar perfil sin haber iniciado sesion
            if (!isset($_SESSION['id_usuario'])) {
                header('Location: index.php?page=login');
                exit;
            }
            $csrf_in = $_POST['csrf_token'] ?? '';
            if (!hash_equals($_SESSION['csrf_token'], $csrf_in)) {
                $_SESSION['error'] = 'Token invalido.';
                header('Location: index.php?page=mi_perfil');
                exit;
            }
            $modeloUsuario = new Usuario($conexion);
            $user_id = (int)$_SESSION['id_usuario'];
            $stmt = $conexion->prepare("SELECT id_perfil FROM usuario WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $user_id]);
            $id_perfil = $stmt->fetchColumn();
            if ($id_perfil) {
                $rolNormalizado = Usuario::normalizarRol($_SESSION['rol_usuario'] ?? '');
                // regla para estudiantes: solo pueden cambiar su telefono y su clave
                // sus nombres y cedula quedan protegidos para evitar suplantaciones
                if ($rolNormalizado === 'Estudiante') {
                    $telefono = trim($_POST['telefono'] ?? '');
                    $modeloUsuario->actualizarPerfilEstudiante($id_perfil, $telefono);

                    // revision de cambio de contrasena si escribieron alguna
                    $clave_actual = $_POST['clave_actual'] ?? ($_POST['pass_old'] ?? '');
                    $nueva_clave  = $_POST['nueva_clave'] ?? ($_POST['pass_new'] ?? '');
                    $confirmar_clave = $_POST['confirmar_clave'] ?? ($_POST['pass_confirm'] ?? '');
                    if (!empty($nueva_clave)) {
                        // pedimos que tenga minimo 8 caracteres para que sea segura
                        if (strlen($nueva_clave) < 8) {
                            $_SESSION['error'] = 'La nueva contrasena debe tener al menos 8 caracteres.';
                            header('Location: index.php?page=mi_perfil');
                            exit;
                        }
                        // comprobamos que escribio la misma dos veces para no equivocarse
                        if ($nueva_clave !== $confirmar_clave) {
                            $_SESSION['error'] = 'La confirmacion de la nueva contrasena no coincide.';
                            header('Location: index.php?page=mi_perfil');
                            exit;
                        }
                        // verificamos que la clave vieja que escribio sea la correcta
                        $stmtClave = $conexion->prepare("SELECT clave FROM usuario WHERE id = :id LIMIT 1");
                        $stmtClave->execute([':id' => $user_id]);
                        $hashActual = $stmtClave->fetchColumn();
                        if (!password_verify($clave_actual, $hashActual)) {
                            $_SESSION['error'] = 'La contrasena actual es incorrecta.';
                            header('Location: index.php?page=mi_perfil');
                            exit;
                        }
                        $modeloUsuario->actualizarClave($user_id, $nueva_clave);
                        AuditHelper::registrar($conexion, $user_id, 'Actualizar Contrasena', 'El estudiante actualizo su contrasena de acceso');
                    }
                    AuditHelper::registrar($conexion, $user_id, 'Actualizar Perfil Estudiante', 'El estudiante actualizo su telefono de contacto');
                    $_SESSION['success'] = 'Perfil y datos de contacto actualizados correctamente.';
                } else {
                    // para profesores y directivos permitimos actualizar nombres direccion y telefono
                    $datos = [
                        'nombre' => trim($_POST['nombre'] ?? ''),
                        'segundo_nombre' => trim($_POST['segundo_nombre'] ?? ''),
                        'apellido' => trim($_POST['apellido'] ?? ''),
                        'segundo_apellido' => trim($_POST['segundo_apellido'] ?? ''),
                        'telefono' => trim($_POST['telefono'] ?? ''),
                        'direccion' => trim($_POST['direccion'] ?? ''),
                    ];
                    if (!empty($datos['nombre']) && !empty($datos['apellido'])) {
                        $modeloUsuario->actualizarMiPerfil($id_perfil, $datos);
                        $_SESSION['nombre_completo'] = trim("{$datos['nombre']} {$datos['segundo_nombre']} {$datos['apellido']} {$datos['segundo_apellido']}");

                        $clave_actual = $_POST['clave_actual'] ?? '';
                        $nueva_clave  = $_POST['nueva_clave'] ?? '';
                        $confirmar_clave = $_POST['confirmar_clave'] ?? '';
                        if (!empty($nueva_clave) && strlen($nueva_clave) >= 8 && $nueva_clave === $confirmar_clave) {
                            $stmtClave = $conexion->prepare("SELECT clave FROM usuario WHERE id = :id LIMIT 1");
                            $stmtClave->execute([':id' => $user_id]);
                            $hashActual = $stmtClave->fetchColumn();
                            if (password_verify($clave_actual, $hashActual)) {
                                $modeloUsuario->actualizarClave($user_id, $nueva_clave);
                            }
                        }
                        AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Actualizar Perfil', "Actualizo su propio perfil");
                        $_SESSION['success'] = 'Perfil actualizado correctamente.';
                    }
                }
            }
            header('Location: index.php?page=mi_perfil');
            exit;
            break;

        // ============================================================
        // GENERAR CONSTANCIA DE ESTUDIOS EN FORMATO PDF
        // ============================================================
        case 'constancia_estudio':
            // solo un estudiante registrado puede descargar su propia constancia
            if (!isset($_SESSION['id_usuario']) || $_SESSION['rol_usuario'] !== 'Estudiante') {
                header('Location: index.php?page=login');
                exit;
            }
            $csrf_token = $_GET['csrf_token'] ?? '';
            if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
                header('Location: index.php?page=dashboard');
                exit;
            }
            $pdf = new ReportesPDF('P', 'mm', 'A4');
            $contenido = $pdf->constanciaEstudio($conexion, $_SESSION['id_usuario']);
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="constancia_estudio.pdf"');
            echo $contenido;
            exit;
            break;

        // ============================================================
        // GENERAR CONSTANCIA DE HORARIO E INSCRIPCION EN PDF
        // ============================================================
        case 'constancia_inscripcion':
            if (!isset($_SESSION['id_usuario']) || $_SESSION['rol_usuario'] !== 'Estudiante') {
                header('Location: index.php?page=login');
                exit;
            }
            $csrf_token = $_GET['csrf_token'] ?? '';
            if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
                header('Location: index.php?page=dashboard');
                exit;
            }
            $pdf = new ReportesPDF('P', 'mm', 'A4');
            $contenido = $pdf->constanciaInscripcion($conexion, $_SESSION['id_usuario']);
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="constancia_horario_inscripcion.pdf"');
            echo $contenido;
            exit;
            break;

        // ============================================================
        // INSCRIBIR MATERIA O SECCION POR PARTE DEL ESTUDIANTE
        // ============================================================
        case 'enroll':
        case 'enroll_subject':
            if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol_usuario'] ?? '') !== 'Estudiante') {
                header('Location: index.php?page=login');
                exit;
            }
            // comprobamos que el periodo permita inscripciones en este momento
            $id_periodo = periodoPermiteInscripciones($conexion);
            if (!$id_periodo) {
                header('Location: index.php?page=enrollment');
                exit;
            }
            $csrf_token = $_GET['csrf_token'] ?? $_POST['csrf_token'] ?? '';
            if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
                $_SESSION['error'] = "Token de seguridad invalido.";
                header('Location: index.php?page=enrollment');
                exit;
            }
            $id_seccion = intval($_GET['seccion'] ?? $_POST['seccion'] ?? 0);
            if ($id_seccion > 0) {
                // buscamos los datos de la materia y de la seccion elegida
                $stmtSecInfo = $conexion->prepare("SELECT s.id, s.nombre_seccion, m.id as id_materia, m.semestre, m.nombre_materia, m.uc, u.rol as docente_rol FROM seccion s JOIN materia m ON s.id_materia = m.id JOIN usuario u ON s.id_docente = u.id WHERE s.id = :id");
                $stmtSecInfo->execute([':id' => $id_seccion]);
                $dataSec = $stmtSecInfo->fetch(PDO::FETCH_ASSOC);

                if ($dataSec) {
                    $id_materia = $dataSec['id_materia'];
                    $sem_target = (int)$dataSec['semestre'];

                    // revisamos las materias que el alumno ya aprobo con nota 10 o mas
                    $stmtHist = $conexion->prepare("SELECT m.id, m.semestre FROM solicitud_inscripcion si JOIN seccion s ON si.id_seccion = s.id JOIN materia m ON s.id_materia = m.id WHERE si.id_estudiante = :est AND si.estado = 'Aceptada' AND si.nota >= 10");
                    $stmtHist->execute([':est' => $_SESSION['id_usuario']]);
                    $historial = $stmtHist->fetchAll(PDO::FETCH_ASSOC);
                    $ids_aceptadas = array_column($historial, 'id');

                    // verificamos si la materia pide materias previas aprobadas (prelaciones)
                    $stmtPrel = $conexion->prepare("SELECT id_prerrequisito FROM prelacion WHERE id_materia = :id");
                    $stmtPrel->execute([':id' => $id_materia]);
                    $prelaciones = $stmtPrel->fetchAll(PDO::FETCH_COLUMN);
                    $prelaciones_ok = true;
                    foreach ($prelaciones as $pid) {
                        if (!in_array($pid, $ids_aceptadas)) {
                            $prelaciones_ok = false;
                            break;
                        }
                    }

                    // no permitimos inscribir mas de 30 unidades de credito en total
                    $stmtUcActual = $conexion->prepare("SELECT SUM(m.uc) as total_uc FROM solicitud_inscripcion si JOIN seccion s ON si.id_seccion = s.id JOIN materia m ON s.id_materia = m.id WHERE si.id_estudiante = :est AND si.estado IN ('Pendiente', 'Aceptada')");
                    $stmtUcActual->execute([':est' => $_SESSION['id_usuario']]);
                    $total_actual_uc = (int)$stmtUcActual->fetchColumn();
                    $materia_uc = (int)$dataSec['uc'];

                    if ($total_actual_uc + $materia_uc > 30) {
                        $_SESSION['error'] = "Has superado el limite de 30 UC permitidas por periodo. Total acumulado: " . ($total_actual_uc + $materia_uc);
                        header('Location: index.php?page=enrollment');
                        exit;
                    }

                    // si paso todas las materias previas guardamos su inscripcion
                    if ($prelaciones_ok) {
                        // 1. guardamos la solicitud de inscripcion aceptada directamente
                        $stmtCheckExist = $conexion->prepare("SELECT id, estado FROM solicitud_inscripcion WHERE id_estudiante = :est AND id_seccion = :sec");
                        $stmtCheckExist->execute([':est' => $_SESSION['id_usuario'], ':sec' => $id_seccion]);
                        $existente = $stmtCheckExist->fetch(PDO::FETCH_ASSOC);
                        if (!$existente) {
                            $stmtInsSol = $conexion->prepare("INSERT INTO solicitud_inscripcion (id_estudiante, id_seccion, id_periodo, estado, nota) VALUES (:est, :sec, :per, 'Aceptada', 0)");
                            $stmtInsSol->execute([':est' => $_SESSION['id_usuario'], ':sec' => $id_seccion, ':per' => $id_periodo]);
                        } else {
                            $stmtUpSol = $conexion->prepare("UPDATE solicitud_inscripcion SET estado = 'Aceptada', id_periodo = :per WHERE id = :id");
                            $stmtUpSol->execute([':id' => $existente['id'], ':per' => $id_periodo]);
                        }

                        // 2. agregamos al estudiante en la lista de la seccion para asistencias y horario
                        $stmtInsSec = $conexion->prepare("INSERT INTO inscripcion_seccion (id_estudiante, id_seccion, id_periodo, estado) VALUES (:est, :sec, :per, 'Inscrito') ON DUPLICATE KEY UPDATE estado = 'Inscrito', id_periodo = :per");
                        $stmtInsSec->execute([':est' => $_SESSION['id_usuario'], ':sec' => $id_seccion, ':per' => $id_periodo]);

                        // 3. refrescamos la credencial qr del estudiante con su nueva materia
                        require_once 'app/models/Usuario.php';
                        $modeloU = new Usuario($conexion);
                        $modeloU->generarCredencialQR($_SESSION['id_usuario']);

                        AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Inscripcion de Materia', "Estudiante se inscribio en {$dataSec['nombre_materia']} (Seccion {$dataSec['nombre_seccion']})");
                        $_SESSION['success'] = "Te has inscrito exitosamente en " . $dataSec['nombre_materia'] . " (" . $dataSec['nombre_seccion'] . ")";
                    } else {
                        $_SESSION['error'] = "No cumples con las materias de prelacion requeridas para cursar esta asignatura.";
                    }
                } else {
                    $_SESSION['error'] = "La seccion seleccionada no existe.";
                }
            }
            header('Location: index.php?page=enrollment');
            exit;
            break;

        // ============================================================
        // RETIRAR UNA MATERIA INSCRITA
        // ============================================================
        case 'drop_subject':
            if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol_usuario'] ?? '') !== 'Estudiante') {
                header('Location: index.php?page=login');
                exit;
            }
            // solo se puede retirar mientras las inscripciones esten activas
            $id_periodo = periodoPermiteInscripciones($conexion);
            if (!$id_periodo) {
                $_SESSION['error'] = "El periodo academico actual no permite modificaciones en la inscripcion.";
                header('Location: index.php?page=enrollment');
                exit;
            }
            $csrf_token = $_GET['csrf_token'] ?? $_POST['csrf_token'] ?? '';
            if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
                $_SESSION['error'] = "Token de seguridad invalido.";
                header('Location: index.php?page=enrollment');
                exit;
            }
            $id_seccion = intval($_GET['seccion'] ?? $_POST['seccion'] ?? 0);
            if ($id_seccion > 0) {
                $stmtM = $conexion->prepare("SELECT m.nombre_materia, s.nombre_seccion FROM seccion s JOIN materia m ON s.id_materia = m.id WHERE s.id = :id");
                $stmtM->execute([':id' => $id_seccion]);
                $infoM = $stmtM->fetch(PDO::FETCH_ASSOC);

                // borramos de la solicitud y de la seccion
                $stmtDel1 = $conexion->prepare("DELETE FROM solicitud_inscripcion WHERE id_estudiante = :est AND id_seccion = :sec");
                $stmtDel1->execute([':est' => $_SESSION['id_usuario'], ':sec' => $id_seccion]);

                $stmtDel2 = $conexion->prepare("DELETE FROM inscripcion_seccion WHERE id_estudiante = :est AND id_seccion = :sec");
                $stmtDel2->execute([':est' => $_SESSION['id_usuario'], ':sec' => $id_seccion]);

                // refrescamos la credencial qr
                require_once 'app/models/Usuario.php';
                $modeloU = new Usuario($conexion);
                $modeloU->generarCredencialQR($_SESSION['id_usuario']);

                $nombreMat = $infoM['nombre_materia'] ?? "Seccion #$id_seccion";
                AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Retiro de Materia', "Estudiante retiro la asignatura {$nombreMat}");
                $_SESSION['success'] = "Has retirado la asignatura {$nombreMat} correctamente.";
            }
            header('Location: index.php?page=enrollment');
            exit;
            break;

        // ============================================================
        // EVENTOS DEL CALENDARIO Y CRONOGRAMA ACADEMICO
        // ============================================================
        case 'add_crono_evento':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && tieneRolCoordinacion()) {
                $csrf_token = $_POST['csrf_token'] ?? '';
                if (hash_equals($_SESSION['csrf_token'], $csrf_token)) {
                    $anio = intval($_POST['anio'] ?? 0);
                    $categoria = trim($_POST['categoria'] ?? '');
                    $periodo = trim($_POST['periodo'] ?? '') ?: trim($_POST['periodo_select'] ?? '');
                    $descripcion = trim($_POST['descripcion'] ?? '') ?: trim($_POST['descripcion_select'] ?? '');
                    $fecha_inicio = trim($_POST['fecha_inicio'] ?? '');
                    $fecha_fin = trim($_POST['fecha_fin'] ?? '');
                    $id_periodo = !empty($_POST['id_periodo']) ? intval($_POST['id_periodo']) : null;
                    if ($anio > 0 && $categoria !== '' && $periodo !== '' && $descripcion !== '' && $fecha_inicio !== '' && $fecha_fin !== '') {
                        $stmt = $conexion->prepare("INSERT INTO cronograma_evento (anio, categoria, periodo, descripcion, fecha_inicio, fecha_fin, id_periodo) VALUES (:anio, :cat, :per, :desc, :fi, :ff, :idp)");
                        $stmt->execute([':anio' => $anio, ':cat' => $categoria, ':per' => $periodo, ':desc' => $descripcion, ':fi' => $fecha_inicio, ':ff' => $fecha_fin, ':idp' => $id_periodo]);
                        AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Cronograma', "Agrego evento: $descripcion");
                        $_SESSION['success'] = "Evento agregado correctamente.";
                    }
                }
            }
            header('Location: index.php?page=cronograma');
            exit;
            break;

        case 'edit_crono_evento':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && tieneRolCoordinacion()) {
                $csrf_token = $_POST['csrf_token'] ?? '';
                if (hash_equals($_SESSION['csrf_token'], $csrf_token)) {
                    $id = intval($_POST['id'] ?? 0);
                    $periodo = trim($_POST['periodo'] ?? '') ?: trim($_POST['edit_periodo_select'] ?? '');
                    $descripcion = trim($_POST['descripcion'] ?? '') ?: trim($_POST['edit_descripcion_select'] ?? '');
                    $categoria = trim($_POST['categoria'] ?? '');
                    $fecha_inicio = trim($_POST['fecha_inicio'] ?? '');
                    $fecha_fin = trim($_POST['fecha_fin'] ?? '');
                    $id_periodo = !empty($_POST['id_periodo']) ? intval($_POST['id_periodo']) : null;
                    if ($id > 0 && $periodo !== '' && $descripcion !== '' && $categoria !== '') {
                        $stmt = $conexion->prepare("UPDATE cronograma_evento SET categoria = :cat, periodo = :per, descripcion = :desc, fecha_inicio = :fi, fecha_fin = :ff, id_periodo = :idp WHERE id = :id");
                        $stmt->execute([':cat' => $categoria, ':per' => $periodo, ':desc' => $descripcion, ':fi' => $fecha_inicio, ':ff' => $fecha_fin, ':idp' => $id_periodo, ':id' => $id]);
                        AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Cronograma', "Edito evento ID $id");
                        $_SESSION['success'] = "Evento actualizado correctamente.";
                    }
                }
            }
            header('Location: index.php?page=cronograma');
            exit;
            break;

        case 'delete_crono_evento':
            if (tieneRolCoordinacion()) {
                $id = intval($_GET['id'] ?? 0);
                $csrf_token = $_GET['csrf_token'] ?? '';
                if ($id > 0 && hash_equals($_SESSION['csrf_token'], $csrf_token)) {
                    $conexion->prepare("DELETE FROM cronograma_evento WHERE id = :id")->execute([':id' => $id]);
                    AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Cronograma', "Elimino evento ID $id");
                    $_SESSION['success'] = "Evento eliminado correctamente.";
                }
            }
            header('Location: index.php?page=cronograma');
            exit;
            break;

        // ============================================================
        // CONFIGURAR SEMANAS Y FECHAS DEL CICLO ACADEMICO
        // ============================================================
        case 'save_ciclo_config':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && tieneRolCoordinacion()) {
                $csrf_token = $_POST['csrf_token'] ?? '';
                if (hash_equals($_SESSION['csrf_token'], $csrf_token)) {
                    $id_periodo = intval($_POST['id_periodo'] ?? 0);
                    $nombre = trim($_POST['nombre'] ?? '');
                    $fecha_inicio = trim($_POST['fecha_inicio'] ?? '');
                    $fecha_fin = trim($_POST['fecha_fin'] ?? '');
                    $semanas_duracion = max(1, min(52, intval($_POST['semanas_duracion'] ?? 16)));
                    $hacer_activo = !empty($_POST['hacer_activo']);
                    $return_url = !empty($_POST['return_url']) ? $_POST['return_url'] : 'index.php?page=dashboard';

                    if ($nombre !== '' && $fecha_inicio !== '') {
                        if (empty($fecha_fin)) {
                            $diasSuma = ($semanas_duracion * 7) - 1;
                            $fecha_fin = date('Y-m-d', strtotime("$fecha_inicio + {$diasSuma} days"));
                        }

                        if ($hacer_activo) {
                            $conexion->prepare("UPDATE periodo_academico SET estado = 'Planificado' WHERE estado = 'Activo'")->execute();
                        }

                        $estadoNuevo = $hacer_activo ? 'Activo' : 'Planificado';

                        if ($id_periodo > 0) {
                            $stmt = $conexion->prepare("UPDATE periodo_academico SET nombre = :nom, fecha_inicio = :fi, fecha_fin = :ff, semanas_duracion = :sem, estado = :est WHERE id = :id");
                            $stmt->execute([
                                ':nom' => $nombre,
                                ':fi'  => $fecha_inicio,
                                ':ff'  => $fecha_fin,
                                ':sem' => $semanas_duracion,
                                ':est' => $estadoNuevo,
                                ':id'  => $id_periodo
                            ]);
                            AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Personalizar Ciclo', "Actualizo ciclo academico ID $id_periodo ($nombre, $semanas_duracion semanas, $fecha_inicio a $fecha_fin)");
                            $_SESSION['success'] = "Ciclo academico '$nombre' actualizado: Semana a semana configurado exitosamente ({$semanas_duracion} semanas).";
                        } else {
                            $stmt = $conexion->prepare("INSERT INTO periodo_academico (nombre, fecha_inicio, fecha_fin, semanas_duracion, estado) VALUES (:nom, :fi, :ff, :sem, :est)");
                            $stmt->execute([
                                ':nom' => $nombre,
                                ':fi'  => $fecha_inicio,
                                ':ff'  => $fecha_fin,
                                ':sem' => $semanas_duracion,
                                ':est' => $estadoNuevo
                            ]);
                            $newId = (int)$conexion->lastInsertId();
                            AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Crear Ciclo', "Creo ciclo academico ID $newId ($nombre, $semanas_duracion semanas)");
                            $_SESSION['success'] = "Nuevo ciclo academico '$nombre' establecido exitosamente ({$semanas_duracion} semanas).";
                        }
                    }
                }
            }
            $return_url = !empty($_POST['return_url']) ? $_POST['return_url'] : 'index.php?page=dashboard';
            header('Location: ' . $return_url);
            exit;
            break;

        // ============================================================
        // GESTION DE PERIODOS ACADEMICOS POR COORDINACION
        // ============================================================
        case 'add_periodo':
            // crear un nuevo lapso o semestre con su fecha de inicio y fin
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && tieneRolCoordinacion()) {
                $csrf_token = $_POST['csrf_token'] ?? '';
                if (hash_equals($_SESSION['csrf_token'], $csrf_token)) {
                    $nombre = trim($_POST['nombre'] ?? '');
                    $fecha_inicio = trim($_POST['fecha_inicio'] ?? '');
                    $fecha_fin = trim($_POST['fecha_fin'] ?? '');
                    if ($nombre !== '' && $fecha_inicio !== '') {
                        $stmt = $conexion->prepare("INSERT INTO periodo_academico (nombre, fecha_inicio, fecha_fin) VALUES (:nom, :fi, :ff)");
                        $stmt->execute([':nom' => $nombre, ':fi' => $fecha_inicio, ':ff' => $fecha_fin ?: null]);
                        AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Crear Periodo', "Creo periodo academico: $nombre");
                        $_SESSION['success'] = "Periodo academico '$nombre' creado correctamente.";
                    }
                }
            }
            header('Location: index.php?page=gestion_periodos');
            exit;
            break;

        case 'activate_periodo':
            // encender un periodo para que sea el oficial de las clases
            if (tieneRolCoordinacion()) {
                $id = intval($_GET['id'] ?? 0);
                $csrf_token = $_GET['csrf_token'] ?? '';
                if ($id > 0 && hash_equals($_SESSION['csrf_token'], $csrf_token)) {
                    // ponemos los demas en espera y activamos el seleccionado
                    $conexion->prepare("UPDATE periodo_academico SET estado = 'Planificado' WHERE estado = 'Activo'")->execute();
                    $conexion->prepare("UPDATE periodo_academico SET estado = 'Activo' WHERE id = :id")->execute([':id' => $id]);
                    AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Iniciar Periodo', "Inicio periodo academico ID $id");
                    $_SESSION['success'] = "Periodo iniciado correctamente.";
                }
            }
            header('Location: index.php?page=gestion_periodos');
            exit;
            break;

        case 'finalize_periodo':
            // cerrar y dar por terminado un semestre que ya culmino
            if (tieneRolCoordinacion()) {
                $id = intval($_GET['id'] ?? 0);
                $csrf_token = $_GET['csrf_token'] ?? '';
                if ($id > 0 && hash_equals($_SESSION['csrf_token'], $csrf_token)) {
                    $conexion->prepare("UPDATE periodo_academico SET estado = 'Finalizado', fecha_fin = CURDATE() WHERE id = :id AND estado = 'Activo'")->execute([':id' => $id]);
                    AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Finalizar Periodo', "Finalizo periodo academico ID $id");
                    $_SESSION['success'] = "Periodo finalizado correctamente.";
                }
            }
            header('Location: index.php?page=gestion_periodos');
            exit;
            break;

        case 'delete_periodo':
            // eliminar un periodo que nunca se uso
            if (tieneRolCoordinacion()) {
                $id = intval($_GET['id'] ?? 0);
                $csrf_token = $_GET['csrf_token'] ?? '';
                if ($id > 0 && hash_equals($_SESSION['csrf_token'], $csrf_token)) {
                    $conexion->prepare("DELETE FROM periodo_academico WHERE id = :id AND estado != 'Activo'")->execute([':id' => $id]);
                    AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Eliminar Periodo', "Elimino periodo academico ID $id");
                    $_SESSION['success'] = "Periodo eliminado correctamente.";
                }
            }
            header('Location: index.php?page=gestion_periodos');
            exit;
            break;
    }
}

// ============================================================
// APROBACION O RECHAZO DE ASPIRANTES POR PARTE DE COORDINACION
// ============================================================
if (isset($_GET['approve_id']) || isset($_GET['reject_id'])) {
    if (!tieneRolCoordinacion()) {
        header('Location: index.php?page=login');
        exit;
    }
    require_once 'app/models/Usuario.php';
    $modeloUsuario = new Usuario($conexion);
    $csrf_token = $_GET['csrf_token'] ?? '';
    if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        header('Location: index.php?page=approve_registration');
        exit;
    }
    // si presionaron el boton de aprobar pasamos el aspirante a estudiante oficial
    if (isset($_GET['approve_id'])) {
        $id_est = intval($_GET['approve_id']);
        $modeloUsuario->aprobarAspiranteAEstudiante($id_est);
        AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Aprobar Aspirante', "Aprobo expediente del Aspirante ID $id_est (pasa a Estudiante; pendiente toma de fotografia en Carnetizacion para aperturar Carnet/QR)");
        $_SESSION['success'] = "Expediente aprobado. El postulante ahora es Estudiante; tome su fotografia oficial en Carnetizacion para aperturar su Carnet Digital y Codigo QR.";
        header('Location: index.php?page=approve_registration&msg=approved');
        exit;
    }
    // si presionaron el boton de rechazar borramos los archivos subidos para no dejar basura
    if (isset($_GET['reject_id'])) {
        $id_rej = intval($_GET['reject_id']);
        $u_rej = $modeloUsuario->obtenerUsuarioPorId($id_rej);
        $docs_rej = $modeloUsuario->obtenerDocumentosUsuario($id_rej);
        foreach ($docs_rej as $dr) {
            $modeloUsuario->actualizarEstadoDocumento($dr['id'], 'Rechazado', 'Expediente rechazado');
        }
        if ($u_rej && !empty($u_rej['cedula'])) {
            $cedRej = preg_replace('/[^0-9]/', '', $u_rej['cedula']);
            $dirRej = 'public/uploads/' . $cedRej . '/';
            if (is_dir($dirRej)) {
                foreach (glob($dirRej . '*.*') ?: [] as $fRej) {
                    if (is_file($fRej)) @unlink($fRej);
                }
            }
        }
        $modeloUsuario->actualizarEstadoUsuario($id_rej, 'Rechazado');
        AuditHelper::registrar($conexion, $_SESSION['id_usuario'], 'Rechazar Aspirante', "Rechazo la postulacion y elimino archivos rechazados del Aspirante ID $id_rej");
        header('Location: index.php?page=approve_registration&msg=rejected');
        exit;
    }
}

// ============================================================
// VER PLANILLA DE PREINSCRIPCION PARA DESCARGAR O IMPRIMIR
// ============================================================
if (isset($_GET['page']) && $_GET['page'] === 'planilla') {
    if (!isset($_SESSION['id_usuario_temp']) && !isset($_SESSION['id_usuario'])) {
        header('Location: index.php?page=login');
        exit;
    }
    require_once 'app/views/auth/planilla.view.php';
    exit;
}

// ============================================================
// ENRUTADOR PRINCIPAL: DECIDE QUE PANTALLA MOSTRAR EN EL SISTEMA
// ============================================================
$pagina = !empty($_GET['page']) ? trim($_GET['page']) : 'home';

// regla estricta: un aspirante no puede ver el panel de estudiantes hasta que coordinacion apruebe sus papeles
if (isset($_SESSION['rol_usuario']) && $_SESSION['rol_usuario'] === 'Aspirante') {
    $paginas_permitidas_aspirante = ['home', 'prelacion', 'login', 'upload_docs', 'planilla'];
    if (!in_array($pagina, $paginas_permitidas_aspirante, true)) {
        $_SESSION['error'] = "Acceso restringido: Como Aspirante (Postulante / Pre-inscrito), debe esperar a que el Coordinador Academico apruebe su expediente para acceder al panel estudiantil y Carnet QR.";
        header('Location: index.php?page=upload_docs');
        exit;
    }
}

// regla estricta: la bitacora de auditoria es solo para los ojos del super administrador
if ($pagina === 'auditoria' && (!isset($_SESSION['rol_usuario']) || $_SESSION['rol_usuario'] !== 'Super Administrador')) {
    $_SESSION['error'] = "Acceso denegado: El modulo de Auditoria y archivos de registro es exclusivo del Super Administrador.";
    header('Location: index.php?page=dashboard');
    exit;
}

// cargamos la barra de arriba y el menu de navegacion
require_once 'app/views/layout/header.php';

echo '<div class="unefa-zoom-wrapper flex-grow-1 d-flex flex-column">';

// aqui elegimos el archivo de vista correspondiente segun la pagina pedida
switch ($pagina) {
    // pantallas para entrar registrarse o recuperar clave
    case 'login':
        require_once 'app/views/auth/login.php';
        break;
    case 'register':
        require_once 'app/views/auth/register.php';
        break;
    case 'reset_password':
        require_once 'app/views/auth/reset_password.php';
        break;
    case 'upload_docs':
        require_once 'app/views/auth/upload_docs.php';
        break;

    // pantallas exclusivas de coordinacion direccion y super administrador
    case 'approve_registration':
        if (!tieneRolCoordinacion()) {
            header('Location: index.php?page=login');
            exit;
        }
        require_once 'app/views/coordinator/dashboard.php';
        break;
    case 'gestion_docentes':
        if (!tieneRolCoordinacion()) {
            header('Location: index.php?page=login');
            exit;
        }
        require_once 'app/views/coordinator/teacher_management.php';
        break;
    case 'carnetizacion':
        if (!tieneRolCoordinacion()) {
            header('Location: index.php?page=login');
            exit;
        }
        require_once 'app/views/coordinator/carnetizacion.php';
        break;
    case 'gestion_carnets':
        if (!tieneRolCoordinacion()) {
            header('Location: index.php?page=login');
            exit;
        }
        require_once 'app/views/coordinator/carnet_simulator.php';
        break;
    case 'documentos_usuarios':
        if (!tieneRolCoordinacion()) {
            header('Location: index.php?page=login');
            exit;
        }
        require_once 'app/views/coordinator/documentos_usuarios.php';
        break;
    case 'gestion_periodos':
        if (!tieneRolCoordinacion()) {
            header('Location: index.php?page=login');
            exit;
        }
        require_once 'app/views/coordinator/gestion_periodos.php';
        break;
    case 'cronograma':
        if (!isset($_SESSION['id_usuario'])) {
            header('Location: index.php?page=login');
            exit;
        }
        require_once 'app/views/coordinator/cronograma.php';
        break;

    // pantalla principal de bienvenida y pase de lista
    case 'dashboard':
        if (!isset($_SESSION['id_usuario'])) {
            header('Location: index.php?page=login');
            exit;
        }
        require_once 'app/views/dashboard/index.php';
        break;
    case 'asistencias':
        if (!tieneRolDocencia()) {
            header('Location: index.php?page=login');
            exit;
        }
        require_once 'app/views/dashboard/asistencias.view.php';
        break;

    // informacion publica de pensum y materias previas
    case 'contenidos':
        header('Location: https://unefazuliaingsistemas.wordpress.com/la-ingenieria-de-sistemas/contenidos-programaticos/');
        exit;
        break;
    case 'prelacion':
        require_once 'app/views/home/prelacion.php';
        break;

    // inscripcion de materias para el alumno
    case 'enrollment':
        if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol_usuario'] ?? '') !== 'Estudiante') {
            header('Location: index.php?page=login');
            exit;
        }
        require_once 'app/views/dashboard/enrollment.view.php';
        break;

    // carnet digital oficial y codigo qr para identificacion
    case 'mi_qr':
        if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol_usuario'] ?? '') === 'Aspirante') {
            header('Location: index.php?page=login');
            exit;
        }
        require_once 'app/views/student/qr.php';
        break;
    case 'mi_carnet':
    case 'mi_carnet_docente':
        if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol_usuario'] ?? '') === 'Aspirante') {
            header('Location: index.php?page=login');
            exit;
        }
        require_once 'app/views/student/carnet.php';
        break;

    // auditoria y registros de seguridad solo para el super administrador
    case 'auditoria':
        if (!isset($_SESSION['rol_usuario']) || $_SESSION['rol_usuario'] !== 'Super Administrador') {
            $_SESSION['error'] = "Acceso denegado: El modulo de Auditoria y archivos de registro es exclusivo del Super Administrador.";
            header('Location: index.php?page=dashboard');
            exit;
        }
        require_once 'app/views/reports/auditoria.php';
        break;

    // editar el telefono o la clave propia
    case 'mi_perfil':
        if (!isset($_SESSION['id_usuario'])) {
            header('Location: index.php?page=login');
            exit;
        }
        require_once 'app/views/profile/mi_perfil.php';
        break;

    // pagina inicial del portal con los heroes de la patria
    case 'home':
    default:
        $heroes = require_once 'app/views/home/heroes.php';
        $id_heroe = $_GET['id'] ?? null;
        $heroe = $id_heroe && isset($heroes[$id_heroe]) ? $heroes[$id_heroe] : null;
        if ($heroe) {
            require_once 'app/views/home/heroe_info.php';
        } else {
            require_once 'app/views/home/index.php';
        }
        break;
}

echo '</div>';

echo '</main>';

// barra flotante lateral con los enlaces a las redes sociales en la pagina de inicio
if (!isset($_GET['page']) || $_GET['page'] === 'home'):
?>
    <div class="social-sidebar">
        <a href="https://x.com/Unefa_VEN?t=FhK2uslLRmCrIa9sjQIEEA&s=09" target="_blank" class="social-link social-x" title="X">
            <img src="<?php echo URLROOT; ?>/assets/img/redes/X-Twitter.webp" alt="X">
        </a>
        <a href="https://www.youtube.com/channel/UCU1YFZgV-ENQkfHRspsK9nA" target="_blank" class="social-link social-yt" title="YouTube">
            <img src="<?php echo URLROOT; ?>/assets/img/redes/Youtube.webp" alt="YouTube">
        </a>
        <a href="https://www.instagram.com/unefa_ve?igsh=MXJvcjFkMXJ5Z3NzMg%3D%3D" target="_blank" class="social-link social-ig" title="Instagram">
            <img src="<?php echo URLROOT; ?>/assets/img/redes/Instagram.webp" alt="Instagram">
        </a>
        <a href="https://www.facebook.com/share/1BKuAut1dg/" target="_blank" class="social-link social-fb" title="Facebook">
            <img src="<?php echo URLROOT; ?>/assets/img/redes/Facebook.webp" alt="Facebook">
        </a>
        <a href="https://www.tiktok.com/@unefa_ve?_t=8iwcWCLFEAA&_r=1" target="_blank" class="social-link social-tk" title="TikTok">
            <img src="<?php echo URLROOT; ?>/assets/img/redes/Tiktok.webp" alt="TikTok">
        </a>
    </div>
<?php endif;

// incluimos el pie de pagina con los creditos y scripts
require_once 'app/views/layout/footer.php';
