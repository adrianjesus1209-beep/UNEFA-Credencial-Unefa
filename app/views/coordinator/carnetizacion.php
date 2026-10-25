<?php
// aqui empieza la pantalla de carnetizacion de la unefa.
// aqui los coordinadores o directores pueden tomarle la foto a los estudiantes o profesores
// para ponerla en su carnet digital oficial.
// primero revisamos si el usuario tiene permiso para entrar a esta seccion;
// si no es coordinador, director ni super administrador, lo mandamos al inicio de sesion.
if (!isset($_SESSION['rol_usuario']) || !in_array($_SESSION['rol_usuario'], ['Coordinador Academico', 'Coordinador', 'Super Administrador', 'Director'])) {
    header('Location: index.php?page=login');
    exit;
}

// aqui revisamos si estamos atendiendo a un estudiante o a un profesor.
// por defecto comenzamos en estudiante, pero si en el enlace dice tipo=docente cambiamos todo para profesores.
$tipo = $_GET['tipo'] ?? 'estudiante';
$es_docente = ($tipo === 'docente');
$rol_filter  = $es_docente ? 'Profesor' : 'Estudiante';
$label_s     = $es_docente ? 'Profesor' : 'Estudiante';
$label_p     = $es_docente ? 'Profesores' : 'Estudiantes';
$label_t     = $es_docente ? 'profesor' : 'estudiante';
$icono       = $es_docente ? 'fa-chalkboard-teacher' : 'fa-user-graduate';
$tipo_other  = $es_docente ? 'estudiante' : 'docente';
$label_other = $es_docente ? 'Estudiante' : 'Profesor';
$icono_other = $es_docente ? 'fa-user-graduate' : 'fa-chalkboard-teacher';

// aqui guardamos lo que el usuario haya escrito en el buscador de la pantalla,
// como por ejemplo una cedula, un nombre o un correo.
$busqueda = trim($_GET['q'] ?? '');
$estudiantes = [];
$where_rol = $es_docente ? "u.rol IN ('Profesor', 'Docente', 'Coordinador Academico', 'Director', 'Super Administrador')" : "u.rol = 'Estudiante'";

// si el usuario escribio algo para buscar, hacemos una consulta que busque coincidencias.
// si no escribio nada, mostramos los primeros cincuenta usuarios aprobados por orden de apellido.
if ($busqueda !== '') {
    $like = "%{$busqueda}%";
    $stmt = $conexion->prepare("SELECT u.id, u.correo, u.estado, p.nombre, p.segundo_nombre, p.apellido, p.segundo_apellido, p.cedula, p.tipo_documento, p.foto_perfil, p.fecha_carnetizacion FROM usuario u JOIN perfil p ON u.id_perfil = p.id WHERE $where_rol AND (p.cedula LIKE :q OR p.nombre LIKE :q OR p.segundo_nombre LIKE :q OR p.apellido LIKE :q OR p.segundo_apellido LIKE :q OR u.correo LIKE :q) ORDER BY p.apellido, p.nombre LIMIT 20");
    $stmt->execute([':q' => $like]);
    $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $conexion->prepare("SELECT u.id, u.correo, u.estado, p.nombre, p.segundo_nombre, p.apellido, p.segundo_apellido, p.cedula, p.tipo_documento, p.foto_perfil, p.fecha_carnetizacion FROM usuario u JOIN perfil p ON u.id_perfil = p.id WHERE $where_rol AND u.estado = 'Aprobado' ORDER BY p.apellido, p.nombre LIMIT 50");
    $stmt->execute();
    $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// aqui revisamos si seleccionaron a una persona en especifico para cargar su tarjeta con la camara
$est_id   = intval($_GET['est'] ?? 0);
$est_data = null;
if ($est_id > 0) {
    $stmt = $conexion->prepare("SELECT u.id, u.correo, u.estado, p.nombre, p.segundo_nombre, p.apellido, p.segundo_apellido, p.cedula, p.tipo_documento, p.foto_perfil, p.fecha_carnetizacion FROM usuario u JOIN perfil p ON u.id_perfil = p.id WHERE u.id = :id AND $where_rol LIMIT 1");
    $stmt->execute([':id' => $est_id]);
    $est_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

// estas variables definen cual archivo de tarjeta cargamos y que nombre de accion usamos al guardar
$partial = $es_docente ? 'app/views/coordinator/partials/teacher_card.php' : 'app/views/coordinator/partials/student_card.php';
$search_action = $es_docente ? 'search_teachers_ajax' : 'search_students_ajax';
$detail_action = $es_docente ? 'get_teacher_details_ajax' : 'get_student_details_ajax';
$save_action   = $es_docente ? 'save_teacher_photo' : 'save_photo';
$param_id      = $es_docente ? 'doc_id' : 'est_id';
$page_param    = 'carnetizacion' . ($es_docente ? '&tipo=docente' : '');
?>

<div class="container py-5 mt-3">

    <!-- titulo principal y boton para regresar al panel de control -->
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="p-3 rounded-3 d-flex align-items-center justify-content-center" style="background:linear-gradient(135deg,#003366,#005c99);width:52px;height:52px;">
                <i class="fas fa-camera text-white fa-lg"></i>
            </div>
            <div>
                <h1 class="fw-bold mb-0" style="color:#003366;font-size:1.65rem;">Carnetizacion</h1>
                <p class="text-muted mb-0 small">Captura y asigna la foto oficial del carnet universitario</p>
            </div>
        </div>
        <a href="index.php?page=dashboard" class="btn btn-sm rounded-pill px-3 fw-bold" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;transition:all .2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
            <i class="fas fa-arrow-left me-2"></i>Volver
        </a>
    </div>

    <!-- barra superior con botones para alternar entre estudiantes y docentes y la caja de busqueda -->
    <div class="card border-0 rounded-4 mb-4 overflow-hidden" style="box-shadow:0 1px 3px rgba(0,0,0,0.06),0 1px 2px rgba(0,0,0,0.04);">
        <div class="p-3" style="background:#f8fafc;">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div class="d-flex rounded-pill overflow-hidden" style="background:#e2e8f0;padding:3px;flex-shrink:0;">
                    <a href="index.php?page=carnetizacion&tipo=estudiante<?= $busqueda ? '&q='.urlencode($busqueda) : '' ?><?= $est_id && !$es_docente ? '&est='.$est_id : '' ?>"
                    class="px-3 py-2 text-decoration-none small fw-bold rounded-pill transition-all"
                    style="background:<?= $es_docente ? 'transparent' : '#fff' ?>;color:<?= $es_docente ? '#64748b' : '#003366' ?>;box-shadow:<?= $es_docente ? 'none' : '0 1px 3px rgba(0,0,0,0.12)' ?>;">
                        <i class="fas fa-user-graduate me-1"></i>Estudiante
                    </a>
                    <a href="index.php?page=carnetizacion&tipo=docente<?= $busqueda ? '&q='.urlencode($busqueda) : '' ?><?= $est_id && $es_docente ? '&est='.$est_id : '' ?>"
                    class="px-3 py-2 text-decoration-none small fw-bold rounded-pill transition-all"
                    style="background:<?= $es_docente ? '#fff' : 'transparent' ?>;color:<?= $es_docente ? '#003366' : '#64748b' ?>;box-shadow:<?= $es_docente ? '0 1px 3px rgba(0,0,0,0.12)' : 'none' ?>;">
                        <i class="fas fa-chalkboard-teacher me-1"></i>Docente
                    </a>
                </div>
                <form method="GET" action="index.php" class="d-flex gap-2 flex-grow-1" style="min-width:260px;flex-wrap:wrap;">
                    <input type="hidden" name="page" value="carnetizacion">
                    <input type="hidden" name="tipo" value="<?= $tipo ?>">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 rounded-start-pill" style="border-color:#d1d9e6;">
                            <i class="fas fa-search text-muted" style="font-size:.9rem;"></i>
                        </span>
                        <input type="text" name="q" id="searchInput" value="<?= htmlspecialchars($busqueda) ?>"
                            class="form-control border-start-0 rounded-end-pill"
                            style="border-color:#d1d9e6;font-size:.95rem;padding:.45rem 1rem;outline:none;box-shadow:none;"
                            placeholder="Cedula, nombre o correo..."
                            maxlength="50"
                            autofocus oninput="this.value = this.value.replace(/[<>]/g, ''); liveSearch()">
                    </div>
                    <button type="submit" class="btn fw-bold rounded-pill px-4" style="background:#003366;color:#fff;border:none;font-size:.9rem;">
                        Buscar
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- estructura de dos columnas: a la izquierda la lista de personas y a la derecha la camara y foto -->
    <div class="row g-4">
        <!-- columna izquierda: lista de resultados -->
        <div class="col-lg-<?= $est_data ? '5' : '12' ?>" id="resultsColumn">
            <div class="card border-0 h-100" style="box-shadow:0 1px 3px rgba(0,0,0,0.06),0 1px 2px rgba(0,0,0,0.04);border-radius:14px;">
                <div class="card-header bg-white border-0 px-4 pt-4 pb-0">
                    <div class="d-flex align-items-center justify-content-between">
                        <h6 class="fw-bold mb-0" style="color:#475569;font-size:.8rem;letter-spacing:.3px;">
                            <i class="fas fa-list me-2" style="color:#94a3b8;"></i>Resultados
                            <span class="badge rounded-pill ms-1" style="background:#e2e8f0;color:#475569;font-size:.65rem;"><?= count($estudiantes) ?></span>
                        </h6>
                    </div>
                </div>
                <div class="card-body p-3" id="resultsContainer">
                    <?php if ($busqueda === ''): ?>
                        <?php if (empty($estudiantes)): ?>
                        <div class="text-center py-5 text-muted">
                            <div class="mb-3 d-inline-flex align-items-center justify-content-center rounded-circle" style="width:56px;height:56px;background:#f1f5f9;">
                                <i class="fas fa-search" style="color:#94a3b8;"></i>
                            </div>
                            <p style="color:#64748b;font-size:.9rem;font-weight:500;">No hay <?= strtolower($label_p) ?> aprobados aun</p>
                            <p style="color:#94a3b8;font-size:.8rem;">Escribe cedula, nombre o correo electronico</p>
                        </div>
                        <?php else: ?>
                        <div class="d-flex flex-column gap-2" id="resultsList">
                        <?php foreach ($estudiantes as $e): 
                            $tieneFotoItem = (!empty($e['foto_perfil']) && $e['foto_perfil'] !== 'default.svg' && !empty($e['fecha_carnetizacion']) && file_exists('public/uploads/profiles/' . $e['foto_perfil']));
                        ?>
                            <a href="index.php?page=carnetizacion&tipo=<?= $tipo ?>&est=<?= $e['id'] ?>"
                            class="text-decoration-none rounded-3 p-3 transition-all"
                            style="background:<?= ($est_id === intval($e['id'])) ? '#f0f7ff' : '#fff' ?>;border:1px solid <?= ($est_id === intval($e['id'])) ? '#b3d4f7' : '#eef2f6' ?>;<?= ($est_id === intval($e['id'])) ? 'border-left:3px solid #003366;' : '' ?>">
                                <div class="d-flex align-items-center gap-3">
                                    <?php if ($tieneFotoItem): ?>
                                        <img src="public/uploads/profiles/<?= htmlspecialchars($e['foto_perfil']) ?>"
                                            class="rounded-circle" style="width:38px;height:38px;object-fit:cover;border:2px solid #e2e8f0;">
                                    <?php else: ?>
                                        <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white"
                                            style="width:38px;height:38px;background:linear-gradient(135deg,#003366,#005c99);font-size:.75rem;">
                                            <?= mb_strtoupper(mb_substr($e['nombre'],0,1).mb_substr($e['apellido'],0,1), 'UTF-8') ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="fw-bold" style="color:#1e293b;font-size:.9rem;"><?= htmlspecialchars(trim("{$e['nombre']} {$e['segundo_nombre']} {$e['apellido']} {$e['segundo_apellido']}")) ?></div>
                                        <div class="small" style="color:#94a3b8;"><?= htmlspecialchars(($e['tipo_documento'] ?? 'V').'-'.$e['cedula']) ?></div>
                                    </div>
                                    <?php if ($tieneFotoItem): ?>
                                        <span class="badge rounded-pill" style="background:#dcfce7;color:#166534;font-size:.65rem;font-weight:600;"><i class="fas fa-check me-1"></i>Carnetizado</span>
                                    <?php else: ?>
                                        <span class="badge rounded-pill" style="background:#fef3c7;color:#92400e;font-size:.65rem;font-weight:600;"><i class="fas fa-camera me-1"></i>Sin foto</span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    <?php elseif (empty($estudiantes)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-user-slash fa-3x mb-3 opacity-25"></i>
                            <p>No se encontraron <?= strtolower($label_p) ?> con esa busqueda.</p>
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-2" id="resultsList">
                        <?php foreach ($estudiantes as $e): 
                            $tieneFotoItem = (!empty($e['foto_perfil']) && $e['foto_perfil'] !== 'default.svg' && !empty($e['fecha_carnetizacion']) && file_exists('public/uploads/profiles/' . $e['foto_perfil']));
                        ?>
                            <a href="index.php?page=carnetizacion&tipo=<?= $tipo ?>&q=<?= urlencode($busqueda) ?>&est=<?= $e['id'] ?>"
                            class="text-decoration-none rounded-3 p-3 transition-all"
                            style="background:<?= ($est_id === intval($e['id'])) ? '#f0f7ff' : '#fff' ?>;border:1px solid <?= ($est_id === intval($e['id'])) ? '#b3d4f7' : '#eef2f6' ?>;<?= ($est_id === intval($e['id'])) ? 'border-left:3px solid #003366;' : '' ?>">
                                <div class="d-flex align-items-center gap-3">
                                    <?php if ($tieneFotoItem): ?>
                                        <img src="public/uploads/profiles/<?= htmlspecialchars($e['foto_perfil']) ?>"
                                            class="rounded-circle" style="width:38px;height:38px;object-fit:cover;border:2px solid #e2e8f0;">
                                    <?php else: ?>
                                        <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white"
                                            style="width:38px;height:38px;background:linear-gradient(135deg,#003366,#005c99);font-size:.75rem;">
                                            <?= mb_strtoupper(mb_substr($e['nombre'],0,1).mb_substr($e['apellido'],0,1), 'UTF-8') ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="fw-bold" style="color:#1e293b;font-size:.9rem;"><?= htmlspecialchars(trim("{$e['nombre']} {$e['segundo_nombre']} {$e['apellido']} {$e['segundo_apellido']}")) ?></div>
                                        <div class="small" style="color:#94a3b8;"><?= htmlspecialchars(($e['tipo_documento'] ?? 'V').'-'.$e['cedula']) ?></div>
                                    </div>
                                    <?php if ($tieneFotoItem): ?>
                                        <span class="badge rounded-pill" style="background:#dcfce7;color:#166534;font-size:.65rem;font-weight:600;"><i class="fas fa-check me-1"></i>Carnetizado</span>
                                    <?php else: ?>
                                        <span class="badge rounded-pill" style="background:#fef3c7;color:#92400e;font-size:.65rem;font-weight:600;"><i class="fas fa-camera me-1"></i>Sin foto</span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- columna derecha: tarjeta con los controles de la camara o aviso para seleccionar -->
        <div class="col-lg-<?= $est_data ? '7' : '5' ?> <?= $est_data ? '' : 'd-none' ?>" id="detailContainer" <?= $est_data ? 'style="scroll-margin-top:6rem;"' : '' ?>>
            <?php if ($est_data): ?>
                <?php include $partial; ?>
            <?php else: ?>
                <div class="card border-0 h-100 d-flex align-items-center justify-content-center" style="box-shadow:0 1px 3px rgba(0,0,0,0.06),0 1px 2px rgba(0,0,0,0.04);border-radius:14px;min-height:300px;">
                    <div class="card-body text-center py-5">
                        <div class="mb-3 d-inline-flex align-items-center justify-content-center rounded-circle" style="width:64px;height:64px;background:#f1f5f9;">
                            <i class="fas fa-user-circle fa-2x" style="color:#94a3b8;"></i>
                        </div>
                        <h5 style="color:#94a3b8;font-size:1rem;font-weight:600;">Selecciona un <?= strtolower($label_s) ?></h5>
                        <p style="color:#cbd5e1;font-size:.85rem;">Realiza una busqueda y elige un resultado</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.nav-pills .nav-link.active { background:#fff !important; color:#003366 !important; box-shadow:0 1px 3px rgba(0,0,0,0.1); }
#searchInput:focus + .input-group-text, .input-group:focus-within .input-group-text { border-color:#b3d4f7 !important; }
</style>

<script>
// aqui guardamos en variables de javascript los datos que envio php para usarlos en el navegador
let EST_ID  = <?= json_encode($est_id) ?>;
const CSRF    = <?= json_encode($_SESSION['csrf_token']) ?>;
const TIPO    = <?= json_encode($tipo) ?>;
const ES_DOCENTE = TIPO === 'docente';
const SEARCH_ACTION = ES_DOCENTE ? 'search_teachers_ajax' : 'search_students_ajax';
const DETAIL_ACTION = ES_DOCENTE ? 'get_teacher_details_ajax' : 'get_student_details_ajax';
const SAVE_ACTION   = ES_DOCENTE ? 'save_teacher_photo' : 'save_photo';
const PARAM_ID      = ES_DOCENTE ? 'doc_id' : 'est_id';

let stream     = null;
let photoData  = null;
let facingMode = 'user';

// esta funcion sirve para abrir directamente la aplicacion de camara de fotos del celular
// al hacer clic en el boton azul grande de tomar foto
function abrirCamaraNativa() {
    stopCamera();
    const nativeInput = document.getElementById('camera-native-input');
    if (nativeInput) {
        nativeInput.click();
    }
}

// esta funcion apaga la camara por completo, quita el video y vuelve a poner
// la pantalla en su estado original limpio para que no gaste bateria ni memoria
function stopCamera() {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
    }
    const guide = document.getElementById('guide-overlay');
    if (guide) guide.style.display = 'none';
    const place = document.getElementById('cam-placeholder');
    if (place) place.style.display = 'flex';
    const video = document.getElementById('video');
    if (video) video.style.display = 'none';
    const canvas = document.getElementById('canvas');
    if (canvas) canvas.style.display = 'none';
    const activeTabId = document.querySelector('.nav-link.active')?.id;
    const isWebcam = activeTabId === 'webcam-tab';
    const btnNative = document.getElementById('btn-native-cam');
    if (btnNative) btnNative.style.display = isWebcam ? 'inline-block' : 'none';
    const btnStart = document.getElementById('btn-start');
    if (btnStart) btnStart.style.display = isWebcam ? 'inline-block' : 'none';
    const btnCapture = document.getElementById('btn-capture');
    if (btnCapture) btnCapture.style.display = 'none';
    const btnSwitch = document.getElementById('btn-switch');
    if (btnSwitch) btnSwitch.style.display = 'none';
    const btnRetry = document.getElementById('btn-retry');
    if (btnRetry) btnRetry.style.display = 'none';
    const btnSave = document.getElementById('btn-save');
    if (btnSave) btnSave.style.display = 'none';
}

// esta funcion cambia entre la camara frontal y la trasera del celular o la computadora
async function switchCamera() {
    facingMode = (facingMode === 'user') ? 'environment' : 'user';
    const nativeInput = document.getElementById('camera-native-input');
    if (nativeInput) {
        nativeInput.setAttribute('capture', facingMode);
    }
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
        await startCamera();
    }
    const btn = document.getElementById('btn-switch');
    if (btn) {
        btn.innerHTML = '<i class="fas fa-sync-alt me-1"></i>' + (facingMode === 'user' ? 'Frontal' : 'Trasera');
    }
}

// esta funcion recibe la foto tomada por la camara del telefono, la recorta
// como un cuadrado centrado y la pone en pantalla para revisarla antes de guardar
function handleNativeCameraCapture(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const reader = new FileReader();
    reader.onload = function(e) {
        const img = new Image();
        img.onload = function() {
            const canvas = document.getElementById('canvas');
            if (!canvas) return;
            const size = 640;
            canvas.width = size;
            canvas.height = size;
            const ctx = canvas.getContext('2d');

            // recortamos un cuadrado centrado para que la cara quede en el medio
            const minDim = Math.min(img.width, img.height);
            const sx = (img.width - minDim) / 2;
            const sy = (img.height - minDim) / 2;

            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, size, size);
            ctx.drawImage(img, sx, sy, minDim, minDim, 0, 0, size, size);

            photoData = canvas.toDataURL('image/jpeg', 0.92);

            // mostramos el cuadro con la foto y el circulo guia encima
            const video = document.getElementById('video');
            if (video) video.style.display = 'none';
            const place = document.getElementById('cam-placeholder');
            if (place) place.style.display = 'none';
            canvas.style.display = 'block';
            const guide = document.getElementById('guide-overlay');
            if (guide) guide.style.display = 'block';

            // actualizamos los botones para mostrar los de guardar o repetir
            const btnNative = document.getElementById('btn-native-cam');
            if (btnNative) btnNative.style.display = 'none';
            const btnStart = document.getElementById('btn-start');
            if (btnStart) btnStart.style.display = 'none';
            const btnCapture = document.getElementById('btn-capture');
            if (btnCapture) btnCapture.style.display = 'none';
            const btnSwitch = document.getElementById('btn-switch');
            if (btnSwitch) btnSwitch.style.display = 'none';
            const btnRetry = document.getElementById('btn-retry');
            if (btnRetry) btnRetry.style.display = 'inline-block';
            const btnSave = document.getElementById('btn-save');
            if (btnSave) btnSave.style.display = 'inline-block';
        };
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
}

// esta funcion recibe una foto que el usuario selecciono desde sus carpetas en la computadora
function handleFileUpload(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = new Image();
            img.onload = function() {
                const canvas = document.getElementById('canvas');
                if (!canvas) return;
                const size = 640;
                canvas.width = size;
                canvas.height = size;
                const ctx = canvas.getContext('2d');

                const minDim = Math.min(img.width, img.height);
                const sx = (img.width - minDim) / 2;
                const sy = (img.height - minDim) / 2;

                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, size, size);
                ctx.drawImage(img, sx, sy, minDim, minDim, 0, 0, size, size);

                photoData = canvas.toDataURL('image/jpeg', 0.92);

                const uploadUi = document.getElementById('upload-ui');
                if (uploadUi) uploadUi.style.display = 'none';
                const preview = document.getElementById('preview-upload');
                if (preview) {
                    preview.src = photoData;
                    preview.style.display = 'block';
                }
                const btnRetry = document.getElementById('btn-retry');
                if (btnRetry) btnRetry.style.display = 'inline-block';
                const btnSave = document.getElementById('btn-save');
                if (btnSave) btnSave.style.display = 'inline-block';
                const btnNative = document.getElementById('btn-native-cam');
                if (btnNative) btnNative.style.display = 'none';
                const btnStart = document.getElementById('btn-start');
                if (btnStart) btnStart.style.display = 'none';
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
}

// esta funcion prende el video en vivo de la camara web en una computadora
// si el navegador no permite video en vivo porque falta conexion segura https,
// abre directamente la camara del dispositivo
async function startCamera() {
    const video = document.getElementById('video');
    const place = document.getElementById('cam-placeholder');
    const canvas = document.getElementById('canvas');
    const guide = document.getElementById('guide-overlay');

    const hasMediaDevices = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    const hasLegacyGetUserMedia = !!(navigator.getUserMedia || navigator.webkitGetUserMedia || navigator.mozGetUserMedia || navigator.msGetUserMedia);

    if (hasMediaDevices || hasLegacyGetUserMedia) {
        try {
            const constraints = { 
                video: { 
                    width: { ideal: 640 }, 
                    height: { ideal: 640 }, 
                    facingMode: facingMode 
                }, 
                audio: false 
            };
            
            if (hasMediaDevices) {
                stream = await navigator.mediaDevices.getUserMedia(constraints);
            } else {
                const legacy = navigator.getUserMedia || navigator.webkitGetUserMedia || navigator.mozGetUserMedia || navigator.msGetUserMedia;
                stream = await new Promise((resolve, reject) => legacy.call(navigator, constraints, resolve, reject));
            }

            if (stream && stream.getVideoTracks && stream.getVideoTracks().length > 0) {
                if (video) {
                    video.style.display = 'block';
                    video.srcObject = stream;
                    await video.play();
                }
                
                if (place) place.style.display = 'none';
                if (canvas) canvas.style.display = 'none';
                if (guide) guide.style.display = 'block';
                
                const btnNative = document.getElementById('btn-native-cam');
                if (btnNative) btnNative.style.display = 'none';
                document.getElementById('btn-start').style.display = 'none';
                document.getElementById('btn-capture').style.display = 'inline-block';
                const btnSwitch = document.getElementById('btn-switch');
                if (btnSwitch) {
                    btnSwitch.style.display = 'inline-block';
                    btnSwitch.innerHTML = '<i class="fas fa-sync-alt me-1"></i>' + (facingMode === 'user' ? 'Frontal' : 'Trasera');
                }
                document.getElementById('btn-retry').style.display = 'none';
                document.getElementById('btn-save').style.display = 'none';
                return;
            }
        } catch(e) {
            console.warn('getUserMedia fallo:', e);
        }
    }

    // si el navegador no deja abrir video en vivo, abrimos la aplicacion de camara nativa
    abrirCamaraNativa();
}

// esta funcion revisa si estamos navegando por http normal en vez de https con candado
// y muestra el boton verde para pasar a https si hace falta
function initHttpsSwitch() {
    if (window.location.protocol === 'http:') {
        const httpsBox = document.getElementById('cam-https-switch-box');
        const httpsBtn = document.getElementById('cam-btn-switch-https');
        if (httpsBox) httpsBox.style.display = 'block';
        if (httpsBtn) {
            const hostClean = window.location.hostname;
            httpsBtn.href = 'https://' + hostClean + (window.location.port ? ':443' : '') + window.location.pathname + window.location.search;
        }
    }
}

// esta funcion sirve para limpiar la pantalla y repetir la foto si salio mal
function retryCamera() {
    photoData = null;
    const canvas = document.getElementById('canvas');
    if (canvas) canvas.style.display = 'none';
    const guide = document.getElementById('guide-overlay');
    if (guide) guide.style.display = 'none';
    const place = document.getElementById('cam-placeholder');
    if (place) place.style.display = 'flex';
    const video = document.getElementById('video');
    if (video) video.style.display = 'none';
    const preview = document.getElementById('preview-upload');
    if (preview) preview.style.display = 'none';
    const uploadUi = document.getElementById('upload-ui');
    if (uploadUi) uploadUi.style.display = 'block';
    const fileInput = document.getElementById('file-input');
    if (fileInput) fileInput.value = '';
    const nativeInput = document.getElementById('camera-native-input');
    if (nativeInput) nativeInput.value = '';
    const btnSave = document.getElementById('btn-save');
    if (btnSave) btnSave.style.display = 'none';
    const btnRetry = document.getElementById('btn-retry');
    if (btnRetry) btnRetry.style.display = 'none';
    const btnNative = document.getElementById('btn-native-cam');
    if (btnNative) btnNative.style.display = 'inline-block';
    const btnStart = document.getElementById('btn-start');
    if (btnStart) btnStart.style.display = 'inline-block';
}

// esta funcion congela el video de la camara como si fuera una fotografia fija
function capturePhoto() {
    const video  = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    canvas.width  = video.videoWidth  || 640;
    canvas.height = video.videoHeight || 640;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    photoData = canvas.toDataURL('image/jpeg', 0.92);
    stopCamera();
    const place = document.getElementById('cam-placeholder');
    if (place) place.style.display = 'none';
    canvas.style.display = 'block';
    const guide = document.getElementById('guide-overlay');
    if (guide) guide.style.display = 'block';
    document.getElementById('btn-capture').style.display = 'none';
    document.getElementById('btn-retry').style.display = 'inline-block';
    document.getElementById('btn-save').style.display = 'inline-block';
}

// esta funcion manda la foto por internet al servidor para guardarla en el perfil del usuario
async function savePhoto() {
    if (!photoData) return;
    const btn = document.getElementById('btn-save');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';
    try {
        const body = {};
        body[PARAM_ID] = EST_ID;
        body.photo = photoData;
        body.csrf_token = CSRF;
        const res = await fetch('index.php?action=' + SAVE_ACTION, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.success) {
            const label = ES_DOCENTE ? 'docente' : 'estudiante';
            notificar(`Foto guardada. El carnet del ${label} ha sido actualizado.`, 'success');
            btn.style.display = 'none';
            document.getElementById('btn-retry').style.display = 'none';
        } else {
            notificar(data.message, 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save me-2"></i>Guardar Foto';
        }
    } catch(e) {
        notificar('Error de red al guardar la foto: ' + e.message, 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save me-2"></i>Guardar Foto';
    }
}

// esta funcion sirve para hacer pruebas de simulacion de carnet vencido
async function simulate(estId, type) {
    if(!await confirmar('Simular vencimiento para este estudiante? Se ajustara la fecha en la BD.')) return;
    try {
        const res = await fetch('index.php?action=simulate_expiry', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ est_id: estId, type: type, csrf_token: CSRF })
        });
        const data = await res.json();
        if(data.success) location.reload();
        else notificar('Error: ' + data.message, 'error');
    } catch(e) { notificar('Error: ' + e.message, 'error'); }
}

// cuando la pagina termina de cargar en la pantalla iniciamos los eventos
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form[action="index.php"] input[name="page"][value="carnetizacion"]')?.closest('form');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            liveSearch.flush && liveSearch.flush();
        });
    }

    const detailEl = document.getElementById('detailContainer');
    if (detailEl && <?= json_encode($est_id > 0) ?>) {
        setTimeout(function () {
            detailEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 300);
    }
    initHttpsSwitch();
});

// aqui guardamos en la memoria temporal los resultados que ya buscamos para que no tenga que volver a pedirlos
const searchCache = new Map();
let searchTimer;
let pendingQuery = '';
let activeController = null;

// esta funcion busca en vivo a las personas mientras el usuario escribe en el teclado
function liveSearch() {
    const input = document.getElementById('searchInput');
    const query = input.value.trim();
    const container = document.getElementById('resultsContainer');
    const counter = document.querySelector('.card-header h6');

    if (query.length < 1) {
        if (activeController) { activeController.abort(); activeController = null; }
        if (container) container.innerHTML = `
            <div class="text-center py-4 text-muted">
                <i class="fas fa-keyboard fa-2x mb-2 opacity-25"></i>
                <p class="small mb-0">Escribe para buscar resultados</p>
            </div>`;
        if (counter) counter.textContent = 'Resultados (0)';
        return;
    }

    if (searchCache.has(query)) {
        renderResults(searchCache.get(query), query);
        if (counter) counter.textContent = 'Resultados (' + searchCache.get(query).length + ')';
        return;
    }

    if (pendingQuery === query) return;
    pendingQuery = query;

    if (counter) counter.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Buscando...';
    clearTimeout(searchTimer);

    // esperamos 150 milisegundos para no saturar al servidor con cada letra que se escribe
    searchTimer = setTimeout(() => {
        if (activeController) activeController.abort();
        activeController = new AbortController();

        fetch(`index.php?action=${SEARCH_ACTION}&q=${encodeURIComponent(query)}`, { signal: activeController.signal })
            .then(res => {
                if (!res.ok) throw new Error('Error HTTP ' + res.status);
                return res.json();
            })
            .then(data => {
                if (pendingQuery !== query) return;
                if (data.success) {
                    searchCache.set(query, data.data);
                    if (searchCache.size > 50) {
                        const firstKey = searchCache.keys().next().value;
                        searchCache.delete(firstKey);
                    }
                    renderResults(data.data, query);
                    if (counter) counter.textContent = 'Resultados (' + data.data.length + ')';
                } else {
                    if (counter) counter.textContent = 'Error en busqueda';
                }
            })
            .catch(err => {
                if (err.name === 'AbortError') return;
                if (counter) counter.textContent = 'Error de conexion';
            });
    }, 150);
}

liveSearch.flush = function () {
    clearTimeout(searchTimer);
    pendingQuery = '';
    liveSearch();
};

// esta funcion dibuja la lista de personas encontradas en el recuadro izquierdo
function renderResults(users, query) {
    const container = document.getElementById('resultsContainer');
    if (!container) return;
    const label = ES_DOCENTE ? 'docentes' : 'estudiantes';
    if (users.length === 0) {
        container.innerHTML = `
            <div class="text-center py-5 text-muted">
                <i class="fas fa-user-slash fa-3x mb-3 opacity-25"></i>
                <p>No se encontraron ${label} con "${query}".</p>
            </div>`;
        return;
    }
    let html = '<div class="list-group list-group-flush" id="resultsList">';
    users.forEach(e => {
        const fullName = `${e.nombre} ${e.segundo_nombre || ''} ${e.apellido} ${e.segundo_apellido || ''}`.trim();
        const activeClass = (parseInt(e.id) === EST_ID) ? 'border-primary bg-primary-subtle' : '';
        const doc = (e.tipo_documento || 'V') + '-' + e.cedula;
        const tieneFotoValida = (e.foto_perfil && e.foto_perfil !== 'default.svg' && e.fecha_carnetizacion);
        let avatar = '';
        if (tieneFotoValida) {
            avatar = `<img src="public/uploads/profiles/${e.foto_perfil}" class="rounded-circle" style="width:42px;height:42px;object-fit:cover;">`;
        } else {
            const initials = (e.nombre.substring(0,1) + e.apellido.substring(0,1)).toUpperCase();
            avatar = `<div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white" style="width:42px;height:42px;background:#003366;font-size:.85rem;">${initials}</div>`;
        }
        const badge = tieneFotoValida
            ? '<span class="badge bg-success rounded-pill"><i class="fas fa-check me-1"></i>Carnetizado</span>'
            : '<span class="badge bg-warning text-dark rounded-pill"><i class="fas fa-camera me-1"></i>Sin foto</span>';
        html += `
            <a href="javascript:void(0)" onclick="selectStudent(${e.id})"
            class="list-group-item list-group-item-action rounded-3 mb-2 border ${activeClass}"
            id="sl-${e.id}"
            style="transition:all .2s;">
                <div class="d-flex align-items-center gap-3">
                    ${avatar}
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-bold text-dark">${fullName}</div>
                        <div class="small text-muted">${doc} · ${e.correo}</div>
                    </div>
                    ${badge}
                </div>
            </a>`;
    });
    html += '</div>';
    container.innerHTML = html;
    const counter = document.querySelector('.card-header h6');
    if (counter) counter.innerHTML = `Resultados (${users.length})`;
}

// esta funcion carga los datos y la tarjeta de la persona que tocamos en la lista
async function selectStudent(id) {
    if (stream) stopCamera();
    const container = document.getElementById('detailContainer');
    container.style.opacity = '0.5';
    container.style.pointerEvents = 'none';
    try {
        const res = await fetch(`index.php?action=${DETAIL_ACTION}&id=${id}`);
        const data = await res.json();
        if (data.success) {
            EST_ID = id;
            container.innerHTML = data.html;
            initHttpsSwitch();
            document.getElementById('resultsColumn')?.classList.replace('col-lg-12', 'col-lg-5');
            container.classList.replace('col-lg-5', 'col-lg-7');
            container.classList.remove('d-none');
            document.querySelectorAll('#resultsList .list-group-item').forEach(item => {
                item.classList.remove('border-primary', 'bg-primary-subtle');
            });
            const selected = document.getElementById('sl-' + id);
            if (selected) selected.classList.add('border-primary', 'bg-primary-subtle');
        } else {
            notificar('Error: ' + data.message, 'error');
        }
    } catch(e) {
        notificar('Error de conexion', 'error');
    } finally {
        container.style.opacity = '1';
        container.style.pointerEvents = 'all';
    }
}
</script>
