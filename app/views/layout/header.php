<?php
// aqui empieza el encabezado general que se muestra arriba en todas las paginas del sitio.
// incluye los tipos de letra, los estilos visuales de bootstrap y la barra de navegacion.
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="SAMEORIGIN">
    <meta http-equiv="Referrer-Policy" content="strict-origin-when-cross-origin">
    <title>UNEFA - Excelencia Educativa Abierta al Pueblo</title>
    <link rel="icon" type="image/png" href="<?php echo URLROOT; ?>/assets/img/logos/logo_unefa.png">
    <link href="<?php echo URLROOT; ?>/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo URLROOT; ?>/css/estilos.css?v=13" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<?php
// aqui revisamos en que pagina estamos parados para saber si le aplicamos el zoom general del sistema
// o si la dejamos en tamano normal (como la portada de inicio o la pantalla de login).
$paginaActual = !empty($_GET['page']) ? trim($_GET['page']) : 'home';
$esExentaZoom = in_array(strtolower($paginaActual), ['home', 'login', 'logon', 'prelacion'], true);
$claseZoom = !$esExentaZoom ? 'unefa-sistema-zoom' : '';
?>
<body class="d-flex flex-column min-vh-100 <?php echo $claseZoom; ?>" style="margin:0">

    <!-- aqui va la cintilla oficial tricolor con los logos institucionales -->
    <div class="barra-superior">
        <img src="<?php echo URLROOT; ?>/assets/img/banners/cintilla.jpg" alt="Cintilla Gubernamental" class="barra-superior-cintilla">
    </div>

    <!-- aqui va la barra de navegacion con el logo de la unefa, los enlaces y el boton de usuario -->
    <nav class="navbar navbar-expand-lg barra-navegacion-propia">
        <div class="container-fluid px-4">
            <a class="navbar-brand d-flex align-items-center gap-3" href="index.php">
                <img src="<?php echo URLROOT; ?>/assets/img/logos/logo_unefa.png" alt="Logo UNEFA" class="rounded-circle logo-header">
                <div class="d-none d-md-block">
                    <div class="fw-bold lh-1 unefa-sigla">UNEFA</div>
                    <div class="text-muted lh-1 unefa-eslogan">Excelencia Educativa</div>
                </div>
            </a>

            <button class="navbar-toggler border-0 shadow-none cursor-pointer" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <i class="fas fa-bars fs-4 icono-nav-toggler"></i>
            </button>

            <div class="collapse navbar-collapse justify-content-end" id="mainNav">
                <ul class="navbar-nav align-items-center gap-1">
                    <?php 
                    // si el usuario es un aspirante nuevo, no le mostramos enlaces como pensum o prelacion para que primero suba sus documentos
                    $esAspiranteNav = (isset($_SESSION['rol_usuario']) && $_SESSION['rol_usuario'] === 'Aspirante') || isset($_SESSION['id_usuario_temp']);
                    if (!$esAspiranteNav): 
                    ?>
                        <li class="nav-item"><a class="enlace-navegacion nav-link" href="index.php"><i class="fas fa-home me-1"></i>Inicio</a></li>
                        <li class="nav-item"><a class="enlace-navegacion nav-link" href="https://unefazuliaingsistemas.wordpress.com/la-ingenieria-de-sistemas/contenidos-programaticos/" target="_blank"><i class="fas fa-book me-1"></i>Pensum</a></li>
                        <li class="nav-item"><a class="enlace-navegacion nav-link" href="index.php?page=prelacion"><i class="fas fa-project-diagram me-1"></i>Prelacion</a></li>
                    <?php endif; ?>
                    <li class="nav-item ms-lg-3">
                        <!-- si el usuario ya inicio sesion, mostramos su nombre en un boton azul con un menu desplegable -->
                        <?php if (isset($_SESSION['id_usuario'])): ?>
                            <div class="dropdown">
                                <button class="boton-primario-personalizado btn btn-sm px-4 py-2 rounded-pill fw-bold dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-user-circle me-2"></i><?php echo htmlspecialchars($_SESSION['nombre_usuario']); ?>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm rounded-4 mt-2">
                                    <li class="px-3 py-1">
                                        <span class="badge bg-primary rounded-pill w-100"><?php echo htmlspecialchars($_SESSION['rol_usuario'] ?? ''); ?></span>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <?php if (($_SESSION['rol_usuario'] ?? '') !== 'Aspirante'): ?>
                                        <li><a class="dropdown-item small text-uppercase fw-bold" href="index.php?page=dashboard"><i class="fas fa-home me-2"></i>Panel</a></li>
                                        <?php if (($_SESSION['rol_usuario'] ?? '') === 'Estudiante'): ?>
                                            <li><a class="dropdown-item small text-uppercase fw-bold text-primary" href="index.php?page=enrollment"><i class="fas fa-edit me-2"></i>Inscribir Materias</a></li>
                                        <?php endif; ?>
                                        <li><a class="dropdown-item small text-uppercase fw-bold" href="index.php?page=mi_carnet"><i class="fas fa-id-card me-2"></i>Mi Carnet Digital</a></li>
                                        <li><a class="dropdown-item small text-uppercase fw-bold" href="index.php?page=mi_qr"><i class="fas fa-qrcode me-2"></i>Mi Codigo QR</a></li>
                                        <li><a class="dropdown-item small text-uppercase fw-bold" href="index.php?page=mi_perfil"><i class="fas fa-user me-2"></i>Mi Perfil</a></li>
                                    <?php else: ?>
                                        <li><a class="dropdown-item small text-uppercase fw-bold" href="index.php?page=upload_docs"><i class="fas fa-file-upload me-2"></i>Mis Documentos</a></li>
                                    <?php endif; ?>
                                    <?php if (isset($_SESSION['rol_usuario']) && in_array($_SESSION['rol_usuario'], ['Estudiante', 'Aspirante'])): ?>
                                        <li><a class="dropdown-item small text-uppercase fw-bold" href="index.php?page=planilla" target="_blank"><i class="fas fa-file-alt me-2"></i>Ver Planilla</a></li>
                                    <?php endif; ?>
                                    <?php if (isset($_SESSION['rol_usuario']) && $_SESSION['rol_usuario'] === 'Super Administrador'): ?>
                                        <li><a class="dropdown-item small text-uppercase fw-bold" href="index.php?page=auditoria"><i class="fas fa-clipboard-check me-2"></i>Auditoria Exclusiva</a></li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item small text-uppercase fw-bold text-danger" href="index.php?action=logout"><i class="fas fa-right-from-bracket me-2"></i>Cerrar sesion</a></li>
                                </ul>
                            </div>
                        <?php elseif (isset($_SESSION['id_usuario_temp'])): ?>
                            <div class="d-flex align-items-center gap-2">
                                <a href="index.php?page=upload_docs" class="boton-primario-personalizado btn btn-sm px-3 py-2 rounded-pill fw-bold">
                                    <i class="fas fa-file-upload me-1"></i>Aspirante
                                </a>
                                <a href="index.php?action=logout" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-bold">Salir</a>
                            </div>
                        <?php else: ?>
                            <!-- si la persona todavia no ha iniciado sesion mostramos el boton de entrar -->
                            <a href="index.php?page=login" class="boton-primario-personalizado btn btn-sm px-4 py-2 rounded-pill fw-bold">
                                <i class="fas fa-user-lock me-2"></i>Entrar al Sistema
                            </a>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <main style="flex: 1 0 auto;" class="d-flex flex-column">
