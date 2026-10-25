<?php
// aqui empieza la pantalla donde se arma y se muestra el carnet digital de la unefa.
// si la persona que entro no ha iniciado sesion con su usuario y clave,
// la mandamos directo a la pantalla de login para que no pueda ver nada sin permiso.
if (!isset($_SESSION['id_usuario'])) {
    header('Location: index.php?page=login');
    exit;
}

// aqui revisamos que tipo de usuario es la persona que esta mirando la pantalla.
// si todavia es un aspirante y no un estudiante aprobado, lo mandamos a subir sus papeles
// porque todavia no tiene derecho a tener carnet universitario.
$rolActual = Usuario::normalizarRol($_SESSION['rol_usuario'] ?? '');
if ($rolActual === 'Aspirante') {
    header('Location: index.php?page=upload_docs');
    exit;
}

// aqui guardamos el numero de carnet o id de la persona que vamos a mostrar.
// si el que esta viendo la pagina es un jefe grande como el director o el coordinador,
// le permitimos pasar el id de otro alumno o profesor por la barra de direccion para ver su carnet.
$id_usuario = (int)$_SESSION['id_usuario'];
if (in_array($rolActual, ['Coordinador Academico', 'Director', 'Super Administrador'], true) && !empty($_GET['id_usuario'])) {
    $id_usuario = (int)$_GET['id_usuario'];
}

// aqui nos conectamos con la caja de datos del usuario para buscar su codigo qr si ya lo tiene guardado.
$modeloU = new Usuario($conexion);
$credQR = $modeloU->obtenerCredencialQR($id_usuario);

// aqui le hacemos una pregunta a la base de datos para traer el nombre completo,
// la cedula, el correo, la carrera y la foto oficial de la persona.
$stmt = $conexion->prepare("SELECT u.correo, u.rol, u.estado, p.nombre, p.segundo_nombre, p.apellido, p.segundo_apellido, p.cedula, p.tipo_documento, c.nombre_carrera, p.foto_perfil, p.fecha_carnetizacion, p.cargo FROM usuario u JOIN perfil p ON u.id_perfil = p.id LEFT JOIN carrera c ON p.id_carrera = c.id WHERE u.id = :id LIMIT 1");
$stmt->execute([':id' => $id_usuario]);
$datos = $stmt->fetch(PDO::FETCH_ASSOC);

// si por alguna razon no encontramos a la persona en la base de datos,
// mostramos un cartel rojo avisando que hubo un problema y frenamos la pagina.
if (!$datos) {
    echo "<div class='container mt-5'><div class='alert alert-danger'>Error: No se pudo cargar el perfil institucional para el carnet.</div></div>";
    exit;
}

// aqui limpiamos y preparamos el texto del nombre y la cedula en letras mayusculas bien ordenadas.
$rolObjetivo = Usuario::normalizarRol($datos['rol'] ?? $rolActual);
$esEstudiante = ($rolObjetivo === 'Estudiante');
$nombre  = strtoupper(trim("{$datos['apellido']} {$datos['segundo_apellido']}")) . ' ' . strtoupper(trim("{$datos['nombre']} {$datos['segundo_nombre']}"));
$cedula  = ($datos['tipo_documento'] ?? 'V') . '-' . $datos['cedula'];
$correo  = $datos['correo'];
$carrera = strtoupper($datos['nombre_carrera'] ?? 'INGENIERIA DE SISTEMAS');

// este bloque switch sirve como un repartidor de plantillas:
// segun sea estudiante, profesor, director o coordinador, le pone de fondo
// el archivo pdf que le corresponde con sus colores y titulos oficiales.
switch ($rolObjetivo) {
    case 'Estudiante':
        // para los alumnos usamos el carnet azul de estudiante y buscamos en que aula o seccion estan inscritos
        $plantillaArchivo = 'CARNET_UNEFA_Plantilla_Estudiante.pdf';
        $tituloCarnet     = 'Carnet Digital Estudiantil';
        $rolEtiqueta      = 'ESTUDIANTE';

        $stmtSec = $conexion->prepare("SELECT s.nombre_seccion FROM inscripcion_seccion ins JOIN seccion s ON ins.id_seccion = s.id WHERE ins.id_estudiante = :id AND ins.estado = 'Inscrito' ORDER BY ins.id DESC LIMIT 1");
        $stmtSec->execute([':id' => $id_usuario]);
        $secRow  = $stmtSec->fetch(PDO::FETCH_ASSOC);
        $seccion = $secRow ? strtoupper($secRow['nombre_seccion']) : $carrera;
        break;

    case 'Profesor':
    case 'Docente':
        // para los profesores usamos la plantilla de docente y mostramos la materia o seccion donde dan clase
        $plantillaArchivo = 'CARNET_UNEFA_Plantilla_Docente.pdf';
        $tituloCarnet     = 'Carnet Digital Docente';
        $rolEtiqueta      = 'DOCENTE';

        $stmtSec = $conexion->prepare("SELECT s.nombre_seccion, m.nombre_materia FROM seccion s JOIN materia m ON s.id_materia = m.id WHERE s.id_docente = :id1 ORDER BY s.id DESC LIMIT 1");
        $stmtSec->execute([':id1' => $id_usuario]);
        $secRow  = $stmtSec->fetch(PDO::FETCH_ASSOC);
        $seccion = $secRow ? strtoupper($secRow['nombre_seccion']) : strtoupper($datos['cargo'] ?: 'DOCENTE');
        break;

    case 'Coordinador Academico':
    case 'Coordinador':
        // para el coordinador usamos su plantilla propia
        $plantillaArchivo = 'CARNET_UNEFA_Plantilla_Coordinador.pdf';
        $tituloCarnet     = 'Carnet Digital Coordinacion Academica';
        $rolEtiqueta      = 'COORDINADOR ACADEMICO';
        $seccion          = strtoupper($datos['cargo'] ?: 'COORDINACION ACADEMICA');
        break;

    case 'Director':
        // para la maxima autoridad usamos la plantilla del director
        $plantillaArchivo = 'CARNET_UNEFA_Plantilla_Director.pdf';
        $tituloCarnet     = 'Carnet Digital Directivo';
        $rolEtiqueta      = 'DIRECTOR';
        $seccion          = strtoupper($datos['cargo'] ?: 'DIRECCION INSTITUCIONAL');
        break;

    case 'Super Administrador':
    case 'SuperAdmin':
        // para los encargados del sistema de computacion usamos la plantilla de super admin
        $plantillaArchivo = 'CARNET_UNEFA_Plantilla_Super_Administrador.pdf';
        $tituloCarnet     = 'Carnet Digital Super Administrador';
        $rolEtiqueta      = 'SUPER ADMINISTRADOR';
        $seccion          = strtoupper($datos['cargo'] ?: 'ADMINISTRACION DEL SISTEMA');
        break;

    default:
        // si no sabemos que rol tiene por descarte usamos la plantilla basica
        $plantillaArchivo = 'CARNET_UNEFA_Plantilla_Estudiante.pdf';
        $tituloCarnet     = 'Carnet Digital Institucional (' . htmlspecialchars($rolObjetivo) . ')';
        $rolEtiqueta      = strtoupper($rolObjetivo);
        $seccion          = strtoupper($datos['cargo'] ?: $carrera);
        break;
}

// aqui revisamos si el archivo del carnet realmente existe en la carpeta publica del servidor.
// si alguien borro el archivo sin querer, usamos una plantilla de auxilio para que el sistema no se caiga.
if (!file_exists('public/carnet/' . $plantillaArchivo)) {
    if (file_exists('public/carnet/CARNET_UNEFA_Plantilla_Docente.pdf')) {
        $plantillaArchivo = 'CARNET_UNEFA_Plantilla_Docente.pdf';
    } elseif (file_exists('public/carnet/CARNET_UNEFA_Plantilla_Estudiante.pdf')) {
        $plantillaArchivo = 'CARNET_UNEFA_Plantilla_Estudiante.pdf';
    }
}
$pdfUrl = URLROOT . '/carnet/' . rawurlencode($plantillaArchivo);

// aqui calculamos la fecha en que se vence el carnet que siempre es diciembre del proximo ano
$vence = 'DIC ' . (date('Y') + 1);

// aqui preparamos el texto que va a quedar adentro del codigo qr cuando una camara lo escanee
$datosQR   = $modeloU->obtenerDatosLimpiadosQR($id_usuario);
$qrPayload = $credQR['qr_datos'] ?? ($datosQR['qr_datos'] ?? "Nombre Completo {$nombre}\nCedula {$datos['cedula']}\nCorreo {$correo}\nSeccion {$seccion}");
$qrToken   = $credQR['qr_token'] ?? ($datosQR['qr_token'] ?? $datos['cedula']);

// este candado sirve para no dejar que nadie descargue o mire su carnet si todavia no se ha tomado la foto oficial
// o si su expediente no ha sido aprobado por los jefes de la universidad
$error_carnet = null;
$tieneFotoOficial = (!empty($datos['foto_perfil']) && $datos['foto_perfil'] !== 'default.svg' && !empty($datos['fecha_carnetizacion']) && file_exists('public/uploads/profiles/' . $datos['foto_perfil']));

if ($datos['estado'] !== 'Aprobado' || $rolObjetivo === 'Aspirante') {
    $error_carnet = "No disponible: Tu expediente aun no ha sido aprobado por el Coordinador Academico.";
} elseif (!$tieneFotoOficial && in_array($rolObjetivo, ['Estudiante', 'Profesor', 'Docente'], true)) {
    $error_carnet = "Carnet Digital Bloqueado: Tu Codigo QR institucional esta activo para el registro de asistencias, pero tu Carnet Digital oficial permanecera bloqueado hasta que el Coordinador Academico realice la toma de tu fotografia oficial en Carnetizacion.";
}

// aqui elegimos la foto que vamos a dibujar: si ya tiene foto oficial usamos esa,
// si no tiene ninguna foto le ponemos la silueta generica de color gris
if (!empty($datos['foto_perfil']) && file_exists('public/uploads/profiles/' . $datos['foto_perfil'])) {
    $photoUrl = URLROOT . '/uploads/profiles/' . rawurlencode($datos['foto_perfil']);
} else {
    $photoUrl = URLROOT . '/uploads/profiles/default.svg';
}
?>

<!-- aqui llamamos a las librerias que sirven para leer el archivo pdf y dibujar el codigo qr en la pantalla -->
<script src="<?= URLROOT ?>/assets/qrcode/pdf.min.js"></script>
<script src="<?= URLROOT ?>/assets/qrcode/qrious.min.js"></script>
<script src="<?= URLROOT ?>/assets/qrcode/qrcode.min.js"></script>
<script src="<?= URLROOT ?>/assets/qrcode/jspdf.umd.min.js"></script>

<style>
/* estas reglas de estilo sirven para acomodar la tarjeta en el centro y que no se vea borrosa en celulares */
.carnet-page    { background:#f8fafc; min-height:calc(100vh - 80px); }
.fw-black       { font-weight:900; }
.carnet-wrapper { max-width:420px; width:100%; }
.carnet-canvas-layer {
    display:block;
    border-radius:12px;
    max-width:420px;
    width:100%;
    height:auto;
    image-rendering: -webkit-optimize-contrast;
    image-rendering: crisp-edges;
}
.carnet-overlay {
    position:absolute;
    top:0; left:0; right:0; bottom:0;
    width:100% !important;
    height:100% !important;
    border-radius:12px;
}
.carnet-loading {
    position:absolute; inset:0;
    background:#f1f5f9; border-radius:12px;
    display:flex; flex-direction:column;
    align-items:center; justify-content:center;
    min-height:200px;
}
@media print {
    .d-print-none   { display:none !important; }
    .carnet-wrapper { max-width:100%; }
}
</style>

<div class="container py-4 carnet-page">
    <?php if ($error_carnet): ?>
        <!-- este pedacito de pantalla se muestra solo cuando el carnet esta bloqueado por falta de foto o aprobacion -->
        <div class="row justify-content-center mt-5">
            <div class="col-md-6 text-center">
                <div class="card border-0 shadow-lg rounded-4 p-5">
                    <i class="fas fa-lock fa-4x mb-4 text-warning opacity-50"></i>
                    <h3 class="fw-bold mb-3" style="color:#003366;">Carnet No Disponible</h3>
                    <p class="text-muted mb-4"><?= htmlspecialchars($error_carnet) ?></p>
                    <a href="index.php?page=dashboard" class="btn btn-primary rounded-pill px-5 fw-bold" style="background:#003366;border-color:#003366;">
                        <i class="fas fa-arrow-left me-2"></i>Volver
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
    <!-- esta cabecera muestra el titulo grande y los botones para imprimir o ver el qr -->
    <div class="text-center mb-4 d-print-none">
        <h2 class="fw-black" style="color:#003366;"><?= $tituloCarnet ?></h2>
        <p class="text-muted small mb-1">Formato oficial universitario con Codigo QR verificable localmente.</p>
        <span class="badge bg-primary rounded-pill px-3 py-1">Token QR: <?= htmlspecialchars($qrToken) ?></span>
    </div>

    <!-- estos botones permiten descargar el carnet en pdf para imprimirlo o volver al panel principal -->
    <div class="d-flex justify-content-center gap-3 mt-3 mb-4 flex-wrap d-print-none">
        <button onclick="downloadCarnetPDF()" class="btn btn-success rounded-pill px-4 fw-bold" id="btn-download-pdf">
            <i class="fas fa-file-pdf me-2"></i>Descargar PDF (Ambos Lados)
        </button>
        <a href="index.php?page=mi_qr<?= ($id_usuario !== (int)$_SESSION['id_usuario']) ? '&id_usuario=' . $id_usuario : '' ?>" class="btn btn-outline-primary rounded-pill px-4 fw-bold">
            <i class="fas fa-qrcode me-2"></i>Ver QR Escaneable
        </a>
        <?php if ($id_usuario !== (int)$_SESSION['id_usuario']): ?>
            <a href="index.php?page=gestion_carnets" class="btn btn-outline-dark rounded-pill px-4 fw-bold">
                <i class="fas fa-id-card-alt me-2"></i>Gestion de Carnets
            </a>
        <?php endif; ?>
        <a href="index.php?page=dashboard" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="fas fa-arrow-left me-2"></i>Volver al Panel
        </a>
    </div>

    <!-- aqui dibujamos las dos caras del carnet una al lado de la otra: la cara delantera y la cara trasera -->
    <div class="d-flex flex-column flex-lg-row justify-content-center align-items-center gap-5">
        <!-- caja con la cara de adelante donde va la foto el nombre y el qr -->
        <div class="carnet-wrapper shadow-lg">
            <div class="carnet-label mb-2 text-muted small fw-bold text-center">
                <i class="fas fa-credit-card me-1"></i>CARA FRONTAL
            </div>
            <div class="position-relative" id="carnet-container-front">
                <canvas id="canvas-pdf-front"  class="carnet-canvas-layer"></canvas>
                <canvas id="canvas-data-front" class="carnet-canvas-layer carnet-overlay"></canvas>
                <div id="loading-front" class="carnet-loading">
                    <div class="spinner-border" style="color:#003366;" role="status"></div>
                    <div class="mt-2 text-muted small">Cargando plantilla oficial...</div>
                </div>
            </div>
        </div>

        <!-- caja con la cara de atras donde estan las normas de la universidad -->
        <div class="carnet-wrapper shadow-lg">
            <div class="carnet-label mb-2 text-muted small fw-bold text-center">
                <i class="fas fa-id-card me-1"></i>CARA TRASERA
            </div>
            <div class="position-relative" id="carnet-container-back">
                <canvas id="canvas-pdf-back" class="carnet-canvas-layer"></canvas>
                <div id="loading-back" class="carnet-loading">
                    <div class="spinner-border" style="color:#003366;" role="status"></div>
                    <div class="mt-2 text-muted small">Cargando plantilla oficial...</div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// este paquete de datos guarda toda la informacion de la persona lista para que el programa la pinte en el dibujo
const CARNET_DATA = {
    name:     <?= json_encode($nombre) ?>,
    idNumber: <?= json_encode($cedula) ?>,
    career:   <?= json_encode($carrera) ?>,
    section:  <?= json_encode($seccion) ?>,
    role:     <?= json_encode($rolEtiqueta) ?>,
    vence:    <?= json_encode($vence) ?>,
    photoUrl: <?= json_encode($photoUrl) ?>,
    qrData:   <?= json_encode($qrPayload) ?>,
    pdfUrl:   <?= json_encode($pdfUrl) ?>
};

// aqui le decimos al lector de pdf donde esta su archivo de ayuda para procesar el documento
pdfjsLib.GlobalWorkerOptions.workerSrc = '<?= URLROOT ?>/assets/qrcode/pdf.worker.min.js';

// esta variable scale sirve para hacer el dibujo dos veces y media mas grande
// para que cuando alguien imprima el carnet en una hoja de papel las letras salgan nitidas y no borrosas
const SCALE = 2.5;
let pdfDoc  = null;

// esta funcion principal se encarga de abrir el archivo pdf del fondo y llamar a los pintores de las dos caras
async function loadAndRenderPDF() {
    try {
        pdfDoc = await pdfjsLib.getDocument(CARNET_DATA.pdfUrl).promise;
        await Promise.all([
            renderPage(1, 'canvas-pdf-front', 'loading-front', drawFrontOverlay),
            renderPage(2, 'canvas-pdf-back',  'loading-back',  null)
        ]);
    } catch (err) {
        console.error('PDF load error:', err);
        document.getElementById('loading-front').style.display = 'none';
        document.getElementById('loading-back').style.display = 'none';
    }
}

// esta funcion agarra una sola hoja del pdf y la dibuja como una foto en el lienzo de la pantalla
async function renderPage(pageNum, canvasId, loadingId, overlayCallback) {
    const page     = await pdfDoc.getPage(pageNum);
    const viewport = page.getViewport({ scale: SCALE });
    const canvas   = document.getElementById(canvasId);
    const ctx      = canvas.getContext('2d');
    canvas.width   = viewport.width;
    canvas.height  = viewport.height;
    await page.render({ canvasContext: ctx, viewport }).promise;
    document.getElementById(loadingId).style.display = 'none';
    if (overlayCallback) await overlayCallback(canvas.width, canvas.height);
}

// esta funcion es la mas importante de la cara delantera porque agarra la foto,
// escribe la cedula, el nombre, el rol y dibuja el codigo qr en su lugar exacto
async function drawFrontOverlay(pdfW, pdfH) {
    const overlay = document.getElementById('canvas-data-front');
    overlay.width  = pdfW;
    overlay.height = pdfH;
    const ctx = overlay.getContext('2d');

    // aqui calculamos el tamano y la posicion de la foto redonda y la pegamos en el carnet
    const photoSize = pdfW * 0.505;
    const photoX    = (pdfW - photoSize) / 2;
    const photoY    = pdfH * 0.202;
    await drawRoundedPhoto(ctx, CARNET_DATA.photoUrl, photoX, photoY, photoSize, photoSize, 40 * SCALE);

    const cx = pdfW * 0.50;
    const ty = pdfH * 0.635;

    ctx.textAlign = 'center';

    // aqui escribimos el numero de cedula en color negro oscuro
    ctx.font      = `bold ${26 * SCALE}px 'Arial Narrow', Arial, sans-serif`;
    ctx.fillStyle = '#0a0a0a';
    ctx.fillText(`${CARNET_DATA.idNumber}`, cx, ty);

    // aqui escribimos el nombre de la persona y si es muy largo le bajamos el tamano de letra para que no se salga
    let nameFontSize = 20 * SCALE;
    ctx.font      = `bold ${nameFontSize}px 'Arial Narrow', Arial, sans-serif`;
    ctx.fillStyle = '#0a0a0a';
    while (ctx.measureText(CARNET_DATA.name).width > pdfW * 0.88 && nameFontSize > 12 * SCALE) {
        nameFontSize -= 1 * SCALE;
        ctx.font = `bold ${nameFontSize}px 'Arial Narrow', Arial, sans-serif`;
    }
    ctx.fillText(CARNET_DATA.name, cx, ty + 29 * SCALE);

    // aqui escribimos el rol o la carrera debajo del nombre
    let subFontSize = 15 * SCALE;
    ctx.font      = `bold ${subFontSize}px 'Arial Narrow', Arial, sans-serif`;
    ctx.fillStyle = '#111111';
    const subText = (CARNET_DATA.section && CARNET_DATA.section !== CARNET_DATA.role) 
        ? `${CARNET_DATA.role}  ·  ${CARNET_DATA.section}` 
        : CARNET_DATA.role;
    while (ctx.measureText(subText).width > pdfW * 0.88 && subFontSize > 10 * SCALE) {
        subFontSize -= 1 * SCALE;
        ctx.font = `bold ${subFontSize}px 'Arial Narrow', Arial, sans-serif`;
    }
    ctx.fillText(subText, cx, ty + 56 * SCALE);

    // aqui escribimos el mes y ano en que se vence el carnet al lado derecho del qr
    ctx.font      = `bold ${16 * SCALE}px Arial, sans-serif`;
    ctx.fillStyle = '#000000';
    ctx.fillText(CARNET_DATA.vence, pdfW * 0.485, pdfH * 0.882);

    // aqui calculamos el tamano y la posicion del codigo qr para que quede bien centrado en su cajita blanca
    const qrSize = Math.round(170 * SCALE);
    const qrX    = Math.round(pdfW * 0.165 - qrSize / 2);
    const qrY    = Math.round(pdfH * 0.857 - qrSize / 2);
    const pad    = Math.round(6 * SCALE);
    const rad    = Math.round(8 * SCALE);

    // aqui pintamos una base blanca con esquinas redonditas abajo del qr para tapar cualquier dibujo que sobre
    ctx.save();
    ctx.fillStyle = '#ffffff';
    roundRect(ctx, qrX - pad, qrY - pad, qrSize + pad * 2, qrSize + pad * 2, rad);
    ctx.fill();
    ctx.restore();

    // aqui creamos el codigo qr con la maxima calidad posible en una hoja oculta de mil pixeles
    const tmpCanvas = document.createElement('canvas');
    new QRious({
        element:    tmpCanvas,
        value:      CARNET_DATA.qrData,
        size:       1024,
        level:      'M',
        padding:    null,
        background: '#ffffff',
        foreground: '#000000'
    });

    // aqui apagamos el filtro borroso del navegador para que los cuadritos negros del qr salgan super filosos y faciles de escanear
    ctx.save();
    ctx.imageSmoothingEnabled = false;
    ctx.mozImageSmoothingEnabled = false;
    ctx.webkitImageSmoothingEnabled = false;
    ctx.msImageSmoothingEnabled = false;
    ctx.drawImage(tmpCanvas, qrX, qrY, qrSize, qrSize);
    ctx.restore();

    ctx.textAlign = 'left';
}

// esta funcion ayuda a recortar la foto del usuario en forma de cuadrado con esquinas redonditas
function drawRoundedPhoto(ctx, src, x, y, w, h, r) {
    return new Promise(resolve => {
        const img = new Image();
        img.crossOrigin = 'Anonymous';
        img.onload = () => {
            ctx.save();
            ctx.fillStyle = '#ffffff';
            roundRect(ctx, x, y, w, h, r); ctx.fill();
            ctx.beginPath();
            roundRect(ctx, x, y, w, h, r); ctx.clip();
            ctx.drawImage(img, x, y, w, h);
            ctx.restore();
            resolve();
        };
        img.onerror = () => {
            ctx.save();
            ctx.fillStyle = '#e2e8f0';
            roundRect(ctx, x, y, w, h, r); ctx.fill();
            ctx.restore();
            resolve();
        };
        img.src = src;
    });
}

// esta funcion es una regla para dibujar un rectangulo que tiene las esquinas curvas en vez de puntiagudas
function roundRect(ctx, x, y, w, h, r) {
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.lineTo(x + w - r, y);
    ctx.quadraticCurveTo(x + w, y, x + w, y + r);
    ctx.lineTo(x + w, y + h - r);
    ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
    ctx.lineTo(x + r, y + h);
    ctx.quadraticCurveTo(x, y + h, x, y + h - r);
    ctx.lineTo(x, y + r);
    ctx.quadraticCurveTo(x, y, x + r, y);
    ctx.closePath();
}

// esta funcion une la parte de adelante y la parte de atras en un solo archivo pdf para que el usuario se lo descargue
async function downloadCarnetPDF() {
    const btn = document.getElementById('btn-download-pdf');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generando PDF...';

    const pdfFront  = document.getElementById('canvas-pdf-front');
    const dataFront = document.getElementById('canvas-data-front');
    const merged    = document.createElement('canvas');
    merged.width    = pdfFront.width;
    merged.height   = pdfFront.height;
    const mCtx      = merged.getContext('2d');
    mCtx.drawImage(pdfFront,  0, 0);
    mCtx.drawImage(dataFront, 0, 0);

    const backCanvas = document.getElementById('canvas-pdf-back');
    const { jsPDF }  = window.jspdf;
    const pdf = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });

    const pageW = 297, pageH = 210, margin = 8;
    const cardW = (pageW - margin * 3) / 2;
    const cardH = pageH - margin * 2;

    pdf.addImage(merged.toDataURL('image/jpeg', 0.98),      'JPEG', margin, margin, cardW, cardH, '', 'FAST');
    pdf.addImage(backCanvas.toDataURL('image/jpeg', 0.98), 'JPEG', margin * 2 + cardW, margin, cardW, cardH, '', 'FAST');
    const cleanRole = (CARNET_DATA.role || 'USUARIO').replace(/\s+/g, '_');
    const cleanCed  = (CARNET_DATA.idNumber || 'DOC').replace(/[^a-zA-Z0-9]/g, '');
    pdf.save(`Carnet_UNEFA_${cleanRole}_${cleanCed}.pdf`);

    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-file-pdf me-2"></i>Descargar PDF (Ambos Lados)';
}

// aqui le decimos al navegador que apenas termine de cargar toda la pagina llame a pintar el carnet
document.addEventListener('DOMContentLoaded', loadAndRenderPDF);
</script>
