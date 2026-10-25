<?php
// esta pagina muestra el codigo qr oficial de la unefa para marcar asistencia
// sirve para que el estudiante o profesor lo muestre desde su telefono
if (!isset($_SESSION['id_usuario'])) {
    header('Location: index.php?page=login');
    exit;
}

// si es aspirante no tiene qr todavia asi que lo mandamos a sus documentos
$rolActual = Usuario::normalizarRol($_SESSION['rol_usuario'] ?? '');
if ($rolActual === 'Aspirante') {
    header('Location: index.php?page=upload_docs');
    exit;
}

// identificamos de quien es el qr (el propio usuario o alguien que ve coordinacion)
$id_usuario = (int)$_SESSION['id_usuario'];
if (in_array($rolActual, ['Coordinador Academico', 'Director', 'Super Administrador'], true) && !empty($_GET['id_usuario'])) {
    $id_usuario = (int)$_GET['id_usuario'];
}

// buscamos la informacion del perfil y los datos limpiecitos para el qr
$modeloU = new Usuario($conexion);
$credQR = $modeloU->obtenerCredencialQR($id_usuario);
$datos = $modeloU->obtenerPerfilCompleto($id_usuario);
$datosQR = $modeloU->obtenerDatosLimpiadosQR($id_usuario);

// si por alguna razon no existen los datos mostramos error
if (!$datos || !$datosQR) {
    echo "<div class='container mt-5'><div class='alert alert-danger'>Error: No se pudo cargar el perfil institucional.</div></div>";
    exit;
}

$rolObjetivo = Usuario::normalizarRol($datos['rol'] ?? $rolActual);
$esEstudiante = ($rolObjetivo === 'Estudiante');
$error_qr = null;
// si el expediente aun no fue aprobado no mostramos el codigo qr
if ($datos['estado'] !== 'Aprobado' || $rolObjetivo === 'Aspirante') {
    $error_qr = "No disponible: Tu expediente aun no ha sido aprobado por el Coordinador Academico.";
}

// variables simples con los datos limpios de la persona
$nombre   = $datosQR['nombre_completo'];
$cedula   = $datosQR['cedula'];
$telefono = $datosQR['telefono'];
$correo   = $datosQR['correo'];
$seccion  = $datosQR['seccion'];
$qrPayload = $credQR['qr_datos'] ?? $datosQR['qr_datos'];
$qrToken   = $credQR['qr_token'] ?? $cedula;
?>

<!-- aqui cargamos los programas de javascript que dibujan el codigo qr en la pantalla -->
<script src="<?= URLROOT ?>/assets/qrcode/qrcode.min.js"></script>
<script src="<?= URLROOT ?>/assets/qrcode/qrious.min.js"></script>

<div class="container py-5 mt-3">
    <?php if ($error_qr): ?>
    <!-- aviso por si el aspirante todavia no fue aprobado -->
    <div class="row justify-content-center mt-4">
        <div class="col-md-6 text-center">
            <div class="card border-0 shadow-lg rounded-4 p-5">
                <i class="fas fa-camera fa-4x mb-4 text-warning opacity-50"></i>
                <h3 class="fw-bold mb-3" style="color:#003366;">Credencial QR No Aperturada</h3>
                <p class="text-muted mb-4"><?= htmlspecialchars($error_qr) ?></p>
                <a href="index.php?page=dashboard" class="btn btn-primary rounded-pill px-5 fw-bold" style="background:#003366;border-color:#003366;">
                    <i class="fas fa-arrow-left me-2"></i>Volver al Panel
                </a>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- tarjeta bonita que contiene el codigo qr y los datos del estudiante -->
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
                <div class="card-header text-white text-center py-4" style="background: #003366;">
                    <i class="fas fa-qrcode fs-1 mb-2 d-block"></i>
                    <h4 class="mb-0 fw-bold">Credencial QR de Asistencia</h4>
                    <p class="mb-0 mt-1 small opacity-75">Seccion <?= htmlspecialchars($seccion) ?></p>
                </div>

                <div class="card-body text-center p-4">
                    <!-- aqui adentro el programa de javascript pinta el dibujo del qr -->
                    <div class="d-flex justify-content-center align-items-center my-2 mx-auto p-3 bg-white rounded-3 shadow-sm"
                        style="width:260px; height:260px; border:1px solid #e2e8f0;">
                        <div id="qrcode"></div>
                    </div>

                    <!-- cajita con la lista de datos personales escritos en texto claro -->
                    <div class="mt-4 p-3 rounded-4 text-start" style="background:#f1f5f9; border:1px solid #e2e8f0;">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="text-white rounded-circle d-flex align-items-center justify-content-center"
                                style="width:28px;height:28px;background:#003366;">
                                <i class="fas fa-id-badge small"></i>
                            </span>
                            <strong class="text-dark">Datos del Codigo QR</strong>
                        </div>
                        <div class="row g-2 small">
                            <div class="col-5 text-muted">Nombre Completo</div>
                            <div class="col-7 fw-bold"><?= htmlspecialchars($nombre) ?></div>
                            <div class="col-5 text-muted">Cedula</div>
                            <div class="col-7 fw-bold"><?= htmlspecialchars($cedula) ?></div>
                            <div class="col-5 text-muted">Telefono</div>
                            <div class="col-7 fw-bold"><?= htmlspecialchars($telefono) ?></div>
                            <div class="col-5 text-muted">Correo</div>
                            <div class="col-7 fw-bold text-truncate"><?= htmlspecialchars($correo) ?></div>
                            <div class="col-5 text-muted">Seccion</div>
                            <div class="col-7 fw-bold text-primary"><?= htmlspecialchars($seccion) ?></div>
                        </div>
                    </div>

                    <!-- botones para descargar imprimir o ver el carnet completo -->
                    <div class="mt-4 d-grid gap-2 d-print-none">
                        <button class="btn btn-primary rounded-pill py-2 fw-bold" onclick="downloadQR()"
                                style="background:#003366; border-color:#003366;">
                            <i class="fas fa-download me-2"></i>Descargar Imagen QR
                        </button>
                        <button class="btn btn-outline-dark rounded-pill py-2 fw-bold" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Imprimir Credencial QR
                        </button>
                        <a href="index.php?page=mi_carnet<?= ($id_usuario !== (int)$_SESSION['id_usuario']) ? '&id_usuario=' . $id_usuario : '' ?>" class="btn btn-outline-success rounded-pill py-2 fw-bold">
                            <i class="fas fa-id-card me-2"></i>Ver Carnet Digital Oficial
                        </a>
                        <a href="index.php?page=dashboard" class="btn btn-outline-secondary rounded-pill py-2">
                            <i class="fas fa-arrow-left me-2"></i>Volver al Panel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// cuando la pagina abre generamos el dibujo del codigo qr con los datos del alumno
document.addEventListener('DOMContentLoaded', function () {
    const qrContainer = document.getElementById('qrcode');
    if (!qrContainer) return;
    const qrContent   = <?= json_encode($qrPayload) ?>;

    try {
        // usamos la libreria principal qrious
        if (typeof QRious !== 'undefined') {
            const canvas = document.createElement('canvas');
            canvas.style.maxWidth  = '220px';
            canvas.style.width     = '100%';
            canvas.style.height    = 'auto';
            qrContainer.appendChild(canvas);

            new QRious({
                element:    canvas,
                value:      qrContent,
                size:       340,
                padding:    28,
                background: '#ffffff',
                foreground: '#000000',
                level:      'M'
            });
        // o si no usamos la alternativa qrcode
        } else if (typeof QRCode !== 'undefined') {
            new QRCode(qrContainer, {
                text: qrContent,
                width: 220,
                height: 220,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.M
            });
        }
    } catch (e) {
        console.error('Error al generar el QR:', e);
    }
});

// esta funcion convierte el dibujo en un archivo de imagen png y lo descarga al telefono
function downloadQR() {
    const canvas = document.querySelector('#qrcode canvas');
    const img    = document.querySelector('#qrcode img');
    const src    = canvas ? canvas.toDataURL('image/png') : (img ? img.src : '');
    if (src) {
        const link = document.createElement('a');
        link.href     = src;
        link.download = 'QR_UNEFA_<?= preg_replace('/[^0-9]/', '', $cedula) ?>.png';
        link.click();
    }
}
</script>
