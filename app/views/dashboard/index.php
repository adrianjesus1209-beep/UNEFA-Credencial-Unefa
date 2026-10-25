<?php
// este archivo es el repartidor principal del panel de control
// segun el rol de quien inicio sesion lo manda a su pantalla correspondiente (director, profesor, alumno, etc)
if (!isset($_SESSION['rol_usuario'])) {
    header('Location: index.php?page=login');
    exit;
}

// limpiamos el rol para comparar correctamente
$rol = Usuario::normalizarRol($_SESSION['rol_usuario']);

switch ($rol) {
    case 'Super Administrador':
        require_once 'app/views/dashboard/superadmin.view.php';
        break;
    case 'Director':
        require_once 'app/views/dashboard/director.view.php';
        break;
    case 'Coordinador Academico':
        require_once 'app/views/dashboard/simple.view.php';
        break;
    case 'Profesor':
        require_once 'app/views/dashboard/teacher.view.php';
        break;
    case 'Estudiante':
        require_once 'app/views/dashboard/student.view.php';
        break;
    case 'Aspirante':
        header('Location: index.php?page=upload_docs');
        exit;
    default:
        header('Location: index.php?page=home');
        exit;
}
