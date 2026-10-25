<?php
// aqui empieza la pantalla donde los profesores o coordinadores pasan asistencia a los estudiantes.
// pueden escanear el carnet con la camara del telefono, subir una foto del codigo qr o pasar lista a mano.
// revisamos si la persona que entra tiene permiso (profesor, coordinador, director o super administrador).
$rolUsuario = Usuario::normalizarRol($_SESSION['rol_usuario'] ?? '');
if (!in_array($rolUsuario, ['Profesor', 'Coordinador Academico', 'Director', 'Super Administrador'], true)) {
    header('Location: index.php?page=dashboard');
    exit;
}

$id_usuario = (int)$_SESSION['id_usuario'];
$fechaSeleccionada = $_GET['fecha'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaSeleccionada)) {
    $fechaSeleccionada = date('Y-m-d');
}

// aqui buscamos las secciones de clase que le corresponden a la persona segun su cargo.
// si es coordinador o director ve todas las secciones; si es profesor ve solo las que el dicta.
if (in_array($rolUsuario, ['Coordinador Academico', 'Director', 'Super Administrador'], true)) {
    $stmtSecs = $conexion->query(
        "SELECT s.*, m.codigo_materia, m.nombre_materia,
                CONCAT(pt.nombre, ' ', pt.apellido) AS titular_nombre
         FROM seccion s
         JOIN materia m ON s.id_materia = m.id
         JOIN usuario ut ON s.id_docente = ut.id
         JOIN perfil pt ON ut.id_perfil = pt.id
         WHERE s.estado = 'Activa'
         ORDER BY s.nombre_seccion ASC"
    );
    $secciones = $stmtSecs->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmtSecs = $conexion->prepare(
        "SELECT s.*, m.codigo_materia, m.nombre_materia,
                CONCAT(pt.nombre, ' ', pt.apellido) AS titular_nombre
         FROM seccion s
         JOIN materia m ON s.id_materia = m.id
         JOIN usuario ut ON s.id_docente = ut.id
         JOIN perfil pt ON ut.id_perfil = pt.id
         WHERE s.id_docente = :id1 AND s.estado = 'Activa'
         ORDER BY s.nombre_seccion ASC"
    );
    $stmtSecs->execute([':id1' => $id_usuario]);
    $secciones = $stmtSecs->fetchAll(PDO::FETCH_ASSOC);
}

$sectionId = (int)($_GET['section_id'] ?? ($secciones[0]['id'] ?? 0));
$seccionActual = null;
foreach ($secciones as $s) {
    if ((int)$s['id'] === $sectionId) {
        $seccionActual = $s;
        break;
    }
}
if (!$seccionActual && !empty($secciones)) {
    $seccionActual = $secciones[0];
    $sectionId = (int)$seccionActual['id'];
}

$estudiantesSeccion = [];
$mapaAsistencia = [];

if ($sectionId > 0) {
    $stmtEst = $conexion->prepare(
        "SELECT u.id AS id_estudiante, u.correo, p.nombre, p.segundo_nombre, p.apellido, p.segundo_apellido,
                p.cedula, p.tipo_documento, q.qr_token
         FROM inscripcion_seccion ins
         JOIN usuario u ON ins.id_estudiante = u.id
         JOIN perfil p ON u.id_perfil = p.id
         LEFT JOIN credencial_qr q ON q.id_usuario = u.id
         WHERE ins.id_seccion = :sec AND ins.estado = 'Inscrito'
         ORDER BY p.apellido ASC, p.nombre ASC"
    );
    $stmtEst->execute([':sec' => $sectionId]);
    $estudiantesSeccion = $stmtEst->fetchAll(PDO::FETCH_ASSOC);

    $stmtAsist = $conexion->prepare(
        "SELECT a.*
         FROM asistencia a
         JOIN sesion_clase sc ON a.id_sesion = sc.id
         WHERE sc.id_seccion = :sec AND sc.fecha_clase = :fecha"
    );
    $stmtAsist->execute([':sec' => $sectionId, ':fecha' => $fechaSeleccionada]);
    while ($row = $stmtAsist->fetch(PDO::FETCH_ASSOC)) {
        $mapaAsistencia[(int)$row['id_estudiante']] = $row;
    }
}

// aqui buscamos los estudiantes aprobados que aun no estan inscritos en esta seccion
$stmtDisp = $conexion->prepare(
    "SELECT u.id, p.nombre, p.apellido, p.cedula, p.tipo_documento
     FROM usuario u
     JOIN perfil p ON u.id_perfil = p.id
     WHERE u.rol = 'Estudiante' AND u.estado = 'Aprobado'
     AND u.id NOT IN (SELECT id_estudiante FROM inscripcion_seccion WHERE id_seccion = :sec AND estado = 'Inscrito')
     ORDER BY p.apellido, p.nombre"
);
$stmtDisp->execute([':sec' => $sectionId]);
$estudiantesDisponibles = $stmtDisp->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- aqui cargamos las librerias locales para que el navegador pueda leer codigos qr con la camara -->
<script src="<?= URLROOT ?>/assets/qrcode/html5-qrcode.min.js"></script>
<script src="<?= URLROOT ?>/assets/qrcode/jsqr.min.js"></script>

<div class="container py-4 mt-2">
    <!-- barrita superior que muestra en que semana del semestre estamos -->
    <?php
    $periodoActivoAsist = PeriodoHelper::getPeriodoActivo($conexion);
    $cicloDetalleAsist  = PeriodoHelper::getDetalleCiclo($periodoActivoAsist, $fechaSeleccionada);
    $semanaFecha        = PeriodoHelper::getSemanaDeFecha($periodoActivoAsist, $fechaSeleccionada);
    PeriodoHelper::renderBannerCiclo($conexion, $rolUsuario, "index.php?page=asistencias&section_id={$sectionId}&fecha={$fechaSeleccionada}");
    ?>

    <!-- cabecera con el titulo y el boton para abrir el escaner de codigos qr -->
    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge rounded-pill px-3 py-1.5 text-white fw-bold" style="background:#003366;">
                        <?= htmlspecialchars($seccionActual['nombre_seccion'] ?? 'Seccion') ?>
                    </span>
                    <h3 class="fw-bold mb-0 text-dark">
                        <?= htmlspecialchars($seccionActual['nombre_materia'] ?? 'Control de Asistencias') ?>
                    </h3>
                </div>
                <?php if ($seccionActual): 
                    $tolActual = (int)($seccionActual['tolerancia_minutos'] ?? 15);
                    $tsIniSec = strtotime('2000-01-01 ' . $seccionActual['hora_inicio']);
                    $tsLimSec = $tsIniSec + ($tolActual * 60);
                    $horaLimTxt = date('h:i A', $tsLimSec);
                ?>
                    <div class="d-flex flex-wrap align-items-center gap-2 text-muted small mt-1">
                        <span><i class="far fa-calendar-alt text-primary me-1"></i><?= htmlspecialchars($seccionActual['dia_semana']) ?> <?= substr($seccionActual['hora_inicio'], 0, 5) ?> a <?= substr($seccionActual['hora_fin'], 0, 5) ?></span>
                        <span>·</span>
                        <span><i class="fas fa-door-open text-secondary me-1"></i><?= htmlspecialchars($seccionActual['aula']) ?></span>
                        <span>·</span>
                        <span class="badge bg-warning-subtle text-dark border border-warning-subtle rounded-pill px-2.5 py-1">
                            <i class="fas fa-hourglass-half text-warning me-1"></i>Chance: Hasta <?= $horaLimTxt ?> (+<?= $tolActual ?>m)
                        </span>
                        <button type="button" class="btn btn-sm btn-link p-0 text-primary fw-bold text-decoration-none ms-1" onclick='abrirConfigurarHorario(<?= json_encode($seccionActual) ?>)' title="Modificar horas de entrada, salida o tolerancia">
                            <i class="fas fa-clock me-1"></i>Ajustar Horario
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#qrScannerModal" style="background:#003366;border-color:#003366;">
                    <i class="fas fa-qrcode me-2"></i>Escaner QR
                </button>
                <a href="index.php?page=dashboard" class="btn btn-outline-secondary rounded-pill px-3 py-2 fw-semibold">
                    <i class="fas fa-arrow-left me-1"></i>Volver
                </a>
            </div>
        </div>

        <!-- opciones rapidas para elegir la seccion y el dia del que queremos ver la asistencia -->
        <form method="GET" action="index.php" class="row g-2 align-items-center pt-3 border-top">
            <input type="hidden" name="page" value="asistencias">
            <div class="col-md-7 col-12">
                <select name="section_id" class="form-select form-select-sm rounded-pill" onchange="this.form.submit()">
                    <?php foreach ($secciones as $sec): ?>
                        <option value="<?= (int)$sec['id'] ?>" <?= ((int)$sec['id'] === $sectionId) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sec['nombre_seccion']) ?> · <?= htmlspecialchars($sec['nombre_materia']) ?> (<?= htmlspecialchars($sec['dia_semana']) ?> <?= substr($sec['hora_inicio'], 0, 5) ?> - <?= substr($sec['hora_fin'], 0, 5) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 col-8">
                <input type="date" name="fecha" value="<?= htmlspecialchars($fechaSeleccionada) ?>" class="form-control form-control-sm rounded-pill" onchange="this.form.submit()">
            </div>
            <div class="col-md-2 col-4 d-flex justify-content-end gap-1">
                <?php if (!empty($estudiantesDisponibles)): ?>
                <button type="button" class="btn btn-sm btn-outline-success rounded-pill fw-bold w-100" data-bs-toggle="modal" data-bs-target="#modalInscribirEst" title="Agregar estudiante a la seccion">
                    <i class="fas fa-user-plus me-1"></i>Agregar
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if ($seccionActual): ?>
    <!-- tabla limpia con la lista de los estudiantes inscritos y su estado de asistencia -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-users me-2 text-primary"></i>Estudiantes Inscritos</h5>
                <small class="text-muted">
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-0.5 fw-bold me-1">
                        <?= count($mapaAsistencia) ?> / <?= count($estudiantesSeccion) ?> registrados
                    </span>
                    Fecha: <strong><?= date('d/m/Y', strtotime($fechaSeleccionada)) ?></strong>
                    <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle px-2 py-0.5 ms-1 fw-bold">
                        <i class="fas fa-calendar-week me-1"></i>Semana <?= $semanaFecha ?> de <?= $cicloDetalleAsist['total_semanas'] ?>
                    </span>
                </small>
            </div>
            <button class="btn btn-sm btn-outline-dark rounded-pill px-3 fw-bold" onclick="window.print()">
                <i class="fas fa-print me-1"></i>Imprimir Lista
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th class="ps-4" style="min-width: 240px;">Estudiante</th>
                        <th class="text-center" style="min-width: 180px;">Estado de Asistencia</th>
                        <th class="text-end pe-4 d-print-none" style="min-width: 280px;">Marcar Asistencia</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($estudiantesSeccion)): ?>
                        <tr>
                            <td colspan="3" class="text-center py-5 text-muted">
                                <i class="fas fa-user-slash fa-3x mb-2 opacity-25 d-block"></i>
                                No hay estudiantes inscritos en esta seccion.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($estudiantesSeccion as $est):
                            $uid = (int)$est['id_estudiante'];
                            $reg = $mapaAsistencia[$uid] ?? null;
                            $estadoAsist = $reg['estado_asistencia'] ?? 'Sin registrar';
                            $metodo = $reg['metodo_registro'] ?? '-';
                            $hora = !empty($reg['hora_registro']) ? date('h:i A', strtotime($reg['hora_registro'])) : '';
                            $cedulaLimpia = preg_replace('/[^0-9]/', '', $est['cedula']);
                        ?>
                            <tr id="row-asist-<?= $uid ?>">
                                <td class="ps-4">
                                    <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($est['apellido'] . ' ' . $est['nombre']) ?></div>
                                    <div class="d-flex align-items-center gap-2 mt-1">
                                        <span class="badge bg-light text-dark border px-2 py-0.5 fw-semibold" style="font-size:0.75rem;">
                                            C.I. <?= htmlspecialchars($cedulaLimpia) ?>
                                        </span>
                                        <small class="text-muted"><?= htmlspecialchars($est['correo']) ?></small>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php if ($estadoAsist === 'Presente'): ?>
                                        <span class="badge bg-success rounded-pill px-3 py-1.5"><i class="fas fa-check me-1"></i>Presente (A tiempo)</span>
                                    <?php elseif ($estadoAsist === 'Tardio'): ?>
                                        <span class="badge bg-warning text-dark rounded-pill px-3 py-1.5"><i class="fas fa-user-clock me-1"></i>Tardio (Con retraso)</span>
                                    <?php elseif ($estadoAsist === 'Justificado'): ?>
                                        <span class="badge bg-info text-dark rounded-pill px-3 py-1.5"><i class="fas fa-file-medical me-1"></i>Justificado</span>
                                    <?php elseif ($estadoAsist === 'Ausente'): ?>
                                        <span class="badge bg-danger rounded-pill px-3 py-1.5"><i class="fas fa-times me-1"></i>Ausente</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border rounded-pill px-3 py-1.5">Sin registrar</span>
                                    <?php endif; ?>

                                    <?php if (!empty($hora) || !empty($reg['observacion'])): ?>
                                        <div class="small text-muted mt-1" style="font-size:0.72rem;">
                                            <?php if (!empty($hora)): ?>
                                                <span><i class="far fa-clock me-1"></i><?= $hora ?> · <?= htmlspecialchars($metodo) ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($reg['observacion'])): ?>
                                                <div class="text-truncate" style="max-width: 250px; margin: 0 auto;" title="<?= htmlspecialchars($reg['observacion']) ?>">
                                                    <?= htmlspecialchars($reg['observacion']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4 d-print-none text-nowrap">
                                    <div class="btn-group btn-group-sm rounded-pill p-1 bg-light border" role="group">
                                        <button type="button" class="btn btn-sm rounded-pill fw-bold <?= ($estadoAsist === 'Presente') ? 'btn-success text-white shadow-sm' : 'btn-light text-success' ?>" onclick="marcarAsistenciaManual(<?= $uid ?>, 'Presente')" title="Marcar Asistio (A tiempo)">
                                            <i class="fas fa-check me-1"></i> Presente
                                        </button>
                                        <button type="button" class="btn btn-sm rounded-pill fw-bold <?= ($estadoAsist === 'Tardio') ? 'btn-warning text-dark shadow-sm' : 'btn-light text-warning' ?>" onclick="marcarAsistenciaManual(<?= $uid ?>, 'Tardio')" title="Marcar con retraso (Tardio)">
                                            <i class="fas fa-user-clock me-1"></i> Tardio
                                        </button>
                                        <button type="button" class="btn btn-sm rounded-pill fw-bold <?= ($estadoAsist === 'Ausente') ? 'btn-danger text-white shadow-sm' : 'btn-light text-danger' ?>" onclick="marcarAsistenciaManual(<?= $uid ?>, 'Ausente')" title="Marcar Inasistente">
                                            <i class="fas fa-times me-1"></i> Ausente
                                        </button>
                                        <button type="button" class="btn btn-sm rounded-pill fw-bold <?= ($estadoAsist === 'Justificado') ? 'btn-info text-dark shadow-sm' : 'btn-light text-secondary' ?>" onclick="marcarAsistenciaManual(<?= $uid ?>, 'Justificado')" title="Justificar Inasistencia">
                                            <i class="fas fa-notes-medical"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ventanita modal donde se abre la camara para escanear el carnet de los alumnos -->
<div class="modal fade" id="qrScannerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header text-white border-0" style="background:#003366;">
                <h5 class="modal-title fw-bold"><i class="fas fa-qrcode me-2"></i>Escaner Local de Credenciales QR</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <!-- cuadro negro donde se reproduce el video de la camara en vivo -->
                <div id="qr-reader" class="rounded-4 overflow-hidden border bg-light mx-auto" style="width: 100%; min-height: 240px;"></div>

                <!-- aviso que sale si el navegador pide conexion segura para prender la camara -->
                <div id="qr-camera-fallback" class="rounded-4 border bg-light p-3 text-muted small" style="display:none;">
                    <div class="mb-2">
                        <i class="fas fa-video-slash fa-2x text-warning mb-2 d-block"></i>
                        <span id="qr-camera-error-msg" class="fw-semibold text-dark">La camara en vivo en navegadores moviles requiere conexion segura (HTTPS).</span>
                    </div>

                    <!-- boton para cambiar a conexion https con candadito seguro -->
                    <div id="qr-https-switch-box" class="my-2" style="display:none;">
                        <a id="qr-btn-switch-https" href="#" class="btn btn-sm btn-success rounded-pill px-3 py-2 fw-bold w-100 shadow-sm">
                            <i class="fas fa-lock me-1"></i> Activar Camara en Vivo con HTTPS
                        </a>
                        <div class="text-muted mt-1" style="font-size:0.75rem;">
                            (Al abrir, pulse <em>Configuracion avanzada</em> y luego <em>Acceder</em> una sola vez)
                        </div>
                    </div>

                    <div class="border-top pt-2 mt-2">
                        <span>Tambien puede escanear tomando una foto o cargando la imagen con los botones abajo:</span>
                    </div>
                </div>

                <!-- cuadro donde se ve la foto que el usuario subio para escanear -->
                <div id="qr-image-preview-box" class="rounded-4 border bg-white p-2 mt-2 mx-auto" style="display:none; max-width: 260px;">
                    <div class="small text-muted fw-bold mb-1">Imagen QR Cargada</div>
                    <img id="qr-image-preview" src="" alt="Vista previa QR" class="img-fluid rounded-3" style="max-height: 200px; object-fit: contain;">
                </div>

                <!-- lector oculto que procesa las imagenes de los codigos qr -->
                <div id="qr-file-reader-hidden" style="display:none;"></div>

                <div class="mt-3 p-3 bg-light rounded-4 border">
                    <p class="text-muted small mb-2 fw-bold">Escanear con Camara, abrir foto de QR o ingresar Cedula:</p>
                    <input type="file" id="qr-input-camera" accept="image/*" capture="environment" class="d-none">
                    <input type="file" id="qr-input-file" accept="image/*" class="d-none">

                    <div class="d-grid gap-2 mb-2">
                        <button type="button" class="btn btn-primary rounded-pill btn-sm fw-bold py-2" style="background:#003366;border-color:#003366;" onclick="document.getElementById('qr-input-camera').click()">
                            <i class="fas fa-camera me-2"></i>Tomar Foto del QR con Camara
                        </button>
                        <button type="button" class="btn btn-outline-primary rounded-pill btn-sm fw-bold py-2" onclick="document.getElementById('qr-input-file').click()">
                            <i class="fas fa-image me-2"></i>Abrir / Subir Imagen de Codigo QR
                        </button>
                    </div>

                    <div class="input-group input-group-sm mt-2">
                        <input type="text" id="manual-qr-token" class="form-control rounded-start-pill" placeholder="Ingresar Cedula o pegar datos del QR" onkeydown="if(event.key==='Enter'){event.preventDefault();procesarLecturaQR(this.value);}">
                        <button class="btn btn-primary rounded-end-pill px-3 fw-bold" type="button" onclick="procesarLecturaQR(document.getElementById('manual-qr-token').value)">
                            <i class="fas fa-check me-1"></i>Validar QR
                        </button>
                    </div>
                </div>

                <div id="qr-scan-result" class="mt-3" style="display:none;"></div>
            </div>
        </div>
    </div>
</div>

<!-- ventanita modal para inscribir a un estudiante en esta seccion de clase -->
<div class="modal fade" id="modalInscribirEst" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header bg-primary text-white border-0" style="background:#003366 !important;">
                <h5 class="modal-title fw-bold">Agregar Estudiante a la Seccion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="index.php?action=enroll_student_section" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="id_seccion" value="<?= $sectionId ?>">
                    <input type="hidden" name="return_asistencias" value="1">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Seleccionar Estudiante Aprobado</label>
                        <select name="id_estudiante" class="form-select" required>
                            <?php foreach ($estudiantesDisponibles as $ed): ?>
                                <option value="<?= (int)$ed['id'] ?>">
                                    <?= htmlspecialchars($ed['nombre'] . ' ' . $ed['apellido']) ?> (<?= htmlspecialchars($ed['cedula']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold">Inscribir en Seccion</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ventanita modal para que el profesor configure la hora de entrada y los minutos de tolerancia para llegar -->
<div class="modal fade" id="modalConfigurarHorarioDocente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header text-white border-0 py-3 px-4" style="background: linear-gradient(135deg, #003366, #005c99);">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-clock fa-lg"></i>
                    <h5 class="modal-title fw-bold mb-0">Configurar Horario y Chance Limite</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="index.php?action=update_teacher_schedule" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="id_seccion" id="cfg-seccion-id" value="<?= $sectionId ?>">
                <input type="hidden" name="return_url" value="index.php?page=asistencias&section_id=<?= $sectionId ?>&fecha=<?= htmlspecialchars($fechaSeleccionada) ?>">

                <div class="modal-body p-4">
                    <!-- resumen de los datos de la materia -->
                    <div class="p-3 rounded-4 bg-light border mb-3">
                        <div class="small text-muted fw-bold">SECCION Y ASIGNATURA</div>
                        <div class="fw-bold text-primary fs-6" id="cfg-materia-txt"><?= htmlspecialchars($seccionActual['nombre_materia'] ?? '') ?></div>
                        <div class="small text-muted" id="cfg-seccion-txt">Seccion: <?= htmlspecialchars($seccionActual['nombre_seccion'] ?? '') ?> · <?= htmlspecialchars($seccionActual['dia_semana'] ?? '') ?></div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-dark">
                                <i class="fas fa-sign-in-alt text-success me-1"></i>Hora Entrada (Empieza)
                            </label>
                            <input type="time" name="hora_inicio" id="cfg-hora-inicio" class="form-control form-control-lg" value="<?= substr($seccionActual['hora_inicio'] ?? '08:00', 0, 5) ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-dark">
                                <i class="fas fa-sign-out-alt text-danger me-1"></i>Hora Salida (Acaba)
                            </label>
                            <input type="time" name="hora_fin" id="cfg-hora-fin" class="form-control form-control-lg" value="<?= substr($seccionActual['hora_fin'] ?? '10:15', 0, 5) ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label small fw-bold text-dark mb-0">
                                <i class="fas fa-hourglass-half text-warning me-1"></i>Chance Limite de Llegada (Tolerancia)
                            </label>
                            <span class="badge bg-warning text-dark fw-bold" id="badge-tolerancia-val"><?= (int)($seccionActual['tolerancia_minutos'] ?? 15) ?> minutos</span>
                        </div>
                        <div class="input-group">
                            <input type="number" name="tolerancia_minutos" id="cfg-tolerancia" class="form-control" min="0" max="120" value="<?= (int)($seccionActual['tolerancia_minutos'] ?? 15) ?>" required>
                            <span class="input-group-text bg-white">minutos</span>
                        </div>
                        <!-- botones rapidos para elegir los minutos de tolerancia -->
                        <div class="d-flex flex-wrap gap-1 mt-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-0" style="font-size:0.75rem;" onclick="setTolerancia(5)">5 min</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-0" style="font-size:0.75rem;" onclick="setTolerancia(10)">10 min</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-0" style="font-size:0.75rem;" onclick="setTolerancia(15)">15 min</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-0" style="font-size:0.75rem;" onclick="setTolerancia(20)">20 min</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-0" style="font-size:0.75rem;" onclick="setTolerancia(30)">30 min</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-0" style="font-size:0.75rem;" onclick="setTolerancia(45)">45 min</button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">
                            <i class="fas fa-door-open text-primary me-1"></i>Aula / Laboratorio
                        </label>
                        <input type="text" name="aula" id="cfg-aula" class="form-control" value="<?= htmlspecialchars($seccionActual['aula'] ?? 'Aula 01') ?>">
                    </div>

                    <!-- cuadro que muestra como queda el horario calculado -->
                    <div class="p-3 rounded-4 border" style="background:#f0f7ff; border-color:#b8daff !important;">
                        <div class="small fw-bold text-primary mb-1">
                            <i class="fas fa-info-circle me-1"></i>Vista Previa para el Estudiante:
                        </div>
                        <ul class="list-unstyled mb-0 small text-dark">
                            <li><strong>Empieza la clase:</strong> <span id="pv-inicio">--:--</span></li>
                            <li><strong>Chance limite de llegada:</strong> <span id="pv-limite" class="fw-bold text-warning-emphasis">--:--</span> (<span id="pv-tol-min">15</span> min)</li>
                            <li><strong>Acaba la clase:</strong> <span id="pv-fin">--:--</span></li>
                        </ul>
                    <div class="p-3 rounded-4 border" style="background:#f0f7ff; border-color:#b8daff !important;">
                        <div class="small fw-bold text-primary mb-1">
                            <i class="fas fa-info-circle me-1"></i>Vista Previa para el Estudiante:
                        </div>
                        <ul class="list-unstyled mb-0 small text-dark">
                            <li><strong>Empieza la clase:</strong> <span id="pv-inicio">--:--</span></li>
                            <li><strong>Chance limite de llegada:</strong> <span id="pv-limite" class="fw-bold text-warning-emphasis">--:--</span> (<span id="pv-tol-min">15</span> min)</li>
                            <li><strong>Acaba la clase:</strong> <span id="pv-fin">--:--</span></li>
                        </ul>
                        <div class="small text-muted mt-2 border-top pt-2" style="font-size:0.72rem;">
                            Los estudiantes que escaneen el QR despues de la hora limite seran registrados automaticamente como <strong>Tardio (Con retraso)</strong>.
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 p-3 bg-light">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold" style="background:#003366; border-color:#003366;">
                        <i class="fas fa-save me-1"></i>Guardar Configuracion
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const CURRENT_SECTION_ID = <?= (int)$sectionId ?>;
const CURRENT_DATE       = <?= json_encode($fechaSeleccionada) ?>;
const CSRF_TOKEN         = <?= json_encode($_SESSION['csrf_token']) ?>;

let html5QrScanner = null;
let html5FileDecoder = null;

function activarTryHarder(scannerInstance) {
    try {
        if (scannerInstance && scannerInstance.qrcode) {
            if (scannerInstance.qrcode.primaryDecoder && scannerInstance.qrcode.primaryDecoder.hints) {
                scannerInstance.qrcode.primaryDecoder.hints.set(3, true);
            }
            if (scannerInstance.qrcode.secondaryDecoder && scannerInstance.qrcode.secondaryDecoder.hints) {
                scannerInstance.qrcode.secondaryDecoder.hints.set(3, true);
            }
        }
    } catch (e) {}
}

function crearCanvasConMargenBlanco(img, sx, sy, sw, sh, maxDim, binarizar) {
    const pad = 60;
    let targetW = sw;
    let targetH = sh;
    if (targetW > maxDim || targetH > maxDim) {
        const ratio = Math.min(maxDim / targetW, maxDim / targetH);
        targetW = Math.max(120, Math.round(targetW * ratio));
        targetH = Math.max(120, Math.round(targetH * ratio));
    } else if (targetW < 300 && targetH < 300) {
        const upRatio = 360 / Math.max(targetW, targetH, 1);
        targetW = Math.round(targetW * upRatio);
        targetH = Math.round(targetH * upRatio);
    }

    const canvas = document.createElement('canvas');
    canvas.width  = targetW + pad * 2;
    canvas.height = targetH + pad * 2;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.imageSmoothingEnabled = false;
    ctx.drawImage(img, sx, sy, sw, sh, pad, pad, targetW, targetH);

    if (binarizar) {
        const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const d = imgData.data;
        let sum = 0;
        let count = 0;
        for (let i = 0; i < d.length; i += 4) {
            const lum = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2];
            sum += lum;
            count++;
        }
        const avg = Math.min(200, Math.max(90, (sum / Math.max(count, 1)) * 0.92));
        for (let i = 0; i < d.length; i += 4) {
            const lum = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2];
            const val = lum < avg ? 0 : 255;
            d[i] = val;
            d[i + 1] = val;
            d[i + 2] = val;
            d[i + 3] = 255;
        }
        ctx.putImageData(imgData, 0, 0);
    }
    return canvas;
}

async function intentarDecodificarCanvas(canvas, fileDecoder) {
    // primer intento: deteccion rapida usando el lector directo del navegador si lo soporta
    if ('BarcodeDetector' in window) {
        try {
            const bd = new BarcodeDetector({ formats: ['qr_code'] });
            const hits = await bd.detect(canvas);
            if (hits && hits.length > 0 && hits[0].rawValue) {
                return hits[0].rawValue;
            }
        } catch (e) {}
    }

    // segundo intento: deteccion con la libreria jsqr que funciona muy bien en fotos tomadas a pantallas
    if (typeof jsQR !== 'undefined') {
        try {
            const ctx = canvas.getContext('2d', { willReadFrequently: true });
            const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(imgData.data, canvas.width, canvas.height, {
                inversionAttempts: "attemptBoth"
            });
            if (code && code.data) {
                return code.data;
            }
        } catch (e) {}
    }

    // tercer intento: decodificador de respaldo
    if (fileDecoder && fileDecoder.qrcode) {
        try {
            const res = await fileDecoder.qrcode.decodeRobustlyAsync(canvas);
            if (res && res.text) return res.text;
        } catch (e) {}

        if (fileDecoder.qrcode.secondaryDecoder) {
            try {
                const res2 = await fileDecoder.qrcode.secondaryDecoder.decodeAsync(canvas);
                if (res2 && res2.text) return res2.text;
            } catch (e) {}
        }
    }
    return null;
}

async function decodificarArchivoImagenQR(imageFile) {
    if (!imageFile) return;

    const resBox     = document.getElementById('qr-scan-result');
    const previewBox = document.getElementById('qr-image-preview-box');
    const previewImg = document.getElementById('qr-image-preview');
    const readerEl   = document.getElementById('qr-reader');
    const fallbackEl = document.getElementById('qr-camera-fallback');

    if (html5QrScanner && html5QrScanner.isScanning) {
        try { await html5QrScanner.stop(); } catch (e) {}
    }
    if (readerEl) readerEl.style.display = 'none';
    if (fallbackEl) fallbackEl.style.display = 'none';

    resBox.style.display = 'block';
    resBox.innerHTML = '<div class="spinner-border spinner-border-sm text-primary me-2"></div>Procesando imagen con detector de alta precision...';

    // primer paso: mostrar la imagen en pantalla para que el usuario vea lo que subio
    let dataUrl = '';
    try {
        dataUrl = await new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload  = ev => resolve(ev.target.result);
            reader.onerror = () => reject(new Error('No se pudo leer el archivo'));
            reader.readAsDataURL(imageFile);
        });
        if (previewBox && previewImg) {
            previewImg.src = dataUrl;
            previewBox.style.display = 'block';
        }
    } catch (e) {
        resBox.innerHTML = '<div class="alert alert-danger rounded-3 small mb-0">Error al leer la imagen seleccionada.</div>';
        return;
    }

    if (!html5FileDecoder && typeof Html5Qrcode !== 'undefined') {
        html5FileDecoder = new Html5Qrcode("qr-file-reader-hidden", { formatsToSupport: [0] });
        activarTryHarder(html5FileDecoder);
    }

    let decodedText = null;

    // segundo paso: intentar leer el codigo qr directamente del archivo
    if (html5FileDecoder && typeof html5FileDecoder.scanFile === 'function') {
        try {
            decodedText = await html5FileDecoder.scanFile(imageFile, false);
            if (decodedText) {
                await procesarLecturaQR(decodedText);
                return;
            }
        } catch (e) {}
    }

    // tercer paso: si no lo leyo directo, recortar la imagen en varias zonas y probar cada una
    const img = await new Promise((resolve, reject) => {
        const im = new Image();
        im.onload  = () => resolve(im);
        im.onerror = () => reject(new Error('Imagen no valida'));
        im.src = dataUrl;
    });

    const w = img.naturalWidth || img.width;
    const h = img.naturalHeight || img.height;

    // estas son las zonas de la imagen donde buscamos el qr (esquina izquierda, centro, derecha)
    const regiones = [
        { sx: 0, sy: 0, sw: w, sh: h, max: 1000, bin: false },
        { sx: 0, sy: 0, sw: w, sh: h, max: 700,  bin: false },
        { sx: 0, sy: 0, sw: w, sh: h, max: 1300, bin: false },
        { sx: 0, sy: 0, sw: w, sh: h, max: 850,  bin: true },
        // zona inferior izquierda donde va el qr en el carnet oficial de la unefa
        { sx: 0, sy: Math.round(h * 0.55), sw: Math.round(w * 0.50), sh: Math.round(h * 0.45), max: 700, bin: false },
        { sx: 0, sy: Math.round(h * 0.55), sw: Math.round(w * 0.50), sh: Math.round(h * 0.45), max: 700, bin: true },
        // zona inferior derecha
        { sx: Math.round(w * 0.50), sy: Math.round(h * 0.55), sw: Math.round(w * 0.50), sh: Math.round(h * 0.45), max: 700, bin: false },
        // zona central
        { sx: Math.round(w * 0.15), sy: Math.round(h * 0.15), sw: Math.round(w * 0.70), sh: Math.round(h * 0.70), max: 800, bin: false },
        { sx: Math.round(w * 0.15), sy: Math.round(h * 0.15), sw: Math.round(w * 0.70), sh: Math.round(h * 0.70), max: 800, bin: true },
        { sx: Math.round(w * 0.25), sy: Math.round(h * 0.25), sw: Math.round(w * 0.50), sh: Math.round(h * 0.50), max: 700, bin: false }
    ];

    for (const reg of regiones) {
        if (reg.sw < 20 || reg.sh < 20) continue;
        const canvas = crearCanvasConMargenBlanco(img, reg.sx, reg.sy, reg.sw, reg.sh, reg.max, reg.bin);
        decodedText = await intentarDecodificarCanvas(canvas, html5FileDecoder);
        if (decodedText) break;
    }

    if (decodedText) {
        await procesarLecturaQR(decodedText);
    } else {
        resBox.innerHTML = `
            <div class="alert alert-warning rounded-3 small text-start mb-0">
                <div class="fw-bold mb-1"><i class="fas fa-exclamation-triangle me-1"></i>No se pudo decodificar el QR en esta fotografia</div>
                <div class="mb-2">Las pantallas de computadora generan reflejos y lineas que dificultan la foto fija. Opciones rapidas:</div>
                <ul class="ps-3 mb-2" style="font-size:0.85rem;">
                    <li><strong>Recomendado:</strong> Pulse <em>Activar Camara en Vivo con HTTPS</em> para escanear en tiempo real.</li>
                    <li>O acerque mas la camara para que el cuadro blanco y negro del QR llene la mayor parte de la foto.</li>
                    <li>O escriba la <strong>Cedula del estudiante</strong> en el campo de texto abajo y pulse <em>Validar QR</em>.</li>
                </ul>
            </div>
        `;
    }
}

async function iniciarCamaraEnVivo() {
    const readerEl   = document.getElementById('qr-reader');
    const fallbackEl = document.getElementById('qr-camera-fallback');
    const httpsBox   = document.getElementById('qr-https-switch-box');
    const httpsBtn   = document.getElementById('qr-btn-switch-https');
    const errMsgEl   = document.getElementById('qr-camera-error-msg');

    if (!readerEl) return;

    // aqui armamos el enlace para cambiar a conexion segura https si hace falta
    if (window.location.protocol === 'http:') {
        if (httpsBox) httpsBox.style.display = 'block';
        if (httpsBtn) {
            const hostClean = window.location.hostname;
            httpsBtn.href = 'https://' + hostClean + (window.location.port ? ':443' : '') + window.location.pathname + window.location.search;
        }
    }

    const isSecure = window.isSecureContext || window.location.protocol === 'https:' || window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';

    if (!isSecure) {
        if (readerEl) readerEl.style.display = 'none';
        if (fallbackEl) fallbackEl.style.display = 'block';
        if (errMsgEl) {
            errMsgEl.textContent = 'En dispositivos moviles los navegadores exigen HTTPS para transmitir la camara en tiempo real.';
        }
        return;
    }

    try {
        if (typeof Html5Qrcode === 'undefined') {
            throw new Error('Libreria de escaneo no disponible.');
        }

        if (!html5QrScanner) {
            html5QrScanner = new Html5Qrcode("qr-reader", { formatsToSupport: [0] });
            activarTryHarder(html5QrScanner);
        }

        if (html5QrScanner.isScanning) {
            try { await html5QrScanner.stop(); } catch (e) {}
        }

        const scanSuccess = (decodedText) => {
            html5QrScanner.stop().catch(() => {});
            procesarLecturaQR(decodedText);
        };

        const config = {
            fps: 15,
            qrbox: function(viewfinderWidth, viewfinderHeight) {
                const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
                const qrboxSize = Math.floor(minEdge * 0.72);
                return { width: qrboxSize, height: qrboxSize };
            },
            aspectRatio: 1.0
        };

        // primer intento: pedirle al celular que use la camara trasera
        try {
            await html5QrScanner.start({ facingMode: "environment" }, config, scanSuccess, () => {});
            if (readerEl) readerEl.style.display = 'block';
            if (fallbackEl) fallbackEl.style.display = 'none';
            return;
        } catch (e1) {
            console.warn('Fallo facingMode environment:', e1);
        }

        // segundo intento: buscar en la lista de camaras disponibles en el equipo
        const devices = await Html5Qrcode.getCameras();
        if (devices && devices.length > 0) {
            let backCam = devices[0].id;
            for (const d of devices) {
                const lbl = (d.label || '').toLowerCase();
                if (lbl.includes('back') || lbl.includes('rear') || lbl.includes('trasera') || lbl.includes('environment')) {
                    backCam = d.id;
                    break;
                }
            }
            if (backCam === devices[0].id && devices.length > 1) {
                backCam = devices[devices.length - 1].id;
            }
            await html5QrScanner.start(backCam, config, scanSuccess, () => {});
            if (readerEl) readerEl.style.display = 'block';
            if (fallbackEl) fallbackEl.style.display = 'none';
            return;
        }

        throw new Error('No se detectaron dispositivos de cámara');
    } catch (err) {
        console.warn('Error al iniciar cámara en vivo:', err);
        if (readerEl) readerEl.style.display = 'none';
        if (fallbackEl) fallbackEl.style.display = 'block';
        if (errMsgEl) {
            errMsgEl.textContent = (err && err.message) ? err.message : 'No se pudo acceder a la cámara en tiempo real.';
        }
    }
}

// contador de cuantos alumnos pasaron asistencia en esta sesion para saber si recargar la pagina al cerrar
let asistenciasRegistradasSesion = 0;

// esta funcion limpia la pantalla y vuelve a prender la camara para escanear al siguiente alumno
function prepararSiguienteEscaneo() {
    const resBox      = document.getElementById('qr-scan-result');
    const previewBox  = document.getElementById('qr-image-preview-box');
    const previewImg  = document.getElementById('qr-image-preview');
    const manualInput = document.getElementById('manual-qr-token');
    const fileInput   = document.getElementById('qr-input-file');
    const cameraInput = document.getElementById('qr-input-camera');

    // escondemos los mensajes viejos
    if (resBox) {
        resBox.style.display = 'none';
        resBox.innerHTML = '';
    }
    // limpiamos la foto previa y las cajitas de texto
    if (previewBox) previewBox.style.display = 'none';
    if (previewImg) previewImg.src = '';
    if (manualInput) manualInput.value = '';
    if (fileInput) fileInput.value = '';
    if (cameraInput) cameraInput.value = '';

    // arrancamos la camara otra vez
    iniciarCamaraEnVivo();
}

// cuando la pagina termina de cargar preparamos los botones y los eventos
document.addEventListener('DOMContentLoaded', function() {
    const modalEl    = document.getElementById('qrScannerModal');
    const previewBox = document.getElementById('qr-image-preview-box');
    const resBox     = document.getElementById('qr-scan-result');

    // cuando se abre la ventanita modal del escaner
    if (modalEl) {
        modalEl.addEventListener('shown.bs.modal', function() {
            asistenciasRegistradasSesion = 0;
            if (previewBox) previewBox.style.display = 'none';
            if (resBox) resBox.style.display = 'none';
            iniciarCamaraEnVivo();
        });

        // cuando se cierra la ventanita apagamos la camara y recargamos si hubo asistencias nuevas
        modalEl.addEventListener('hidden.bs.modal', function() {
            if (html5QrScanner && html5QrScanner.isScanning) {
                html5QrScanner.stop().catch(() => {});
            }
            if (asistenciasRegistradasSesion > 0) {
                location.reload();
            }
        });
    }

    // escuchar cuando seleccionan un archivo de imagen con qr
    const fileInput   = document.getElementById('qr-input-file');
    const cameraInput = document.getElementById('qr-input-camera');

    if (fileInput) {
        fileInput.addEventListener('change', async function(e) {
            if (!e.target.files || e.target.files.length === 0) return;
            const file = e.target.files[0];
            await decodificarArchivoImagenQR(file);
            e.target.value = '';
        });
    }

    if (cameraInput) {
        cameraInput.addEventListener('change', async function(e) {
            if (!e.target.files || e.target.files.length === 0) return;
            const file = e.target.files[0];
            await decodificarArchivoImagenQR(file);
            e.target.value = '';
        });
    }

    // escuchar cambios en la hora de inicio hora de fin o tolerancia de clase
    const hiEl = document.getElementById('cfg-hora-inicio');
    const hfEl = document.getElementById('cfg-hora-fin');
    const tolEl = document.getElementById('cfg-tolerancia');
    if (hiEl) hiEl.addEventListener('input', updatePreviewHorario);
    if (hfEl) hfEl.addEventListener('input', updatePreviewHorario);
    if (tolEl) tolEl.addEventListener('input', updatePreviewHorario);
});

// funcion para quitar caracteres raros del texto que se muestra en pantalla
function limpiarTextoVista(str, esCorreo = false) {
    if (!str) return '';
    let s = String(str).trim();
    if (esCorreo) return s.replace(/[^a-zA-Z0-9@._-]/g, '');
    return s.replace(/[^a-zA-Z0-9áéíóúÁÉÍÓÚñÑüÜ .,;:()_\-\/\"\'¿?¡!]/g, '').trim();
}

// aqui se envia el contenido del codigo qr al servidor para registrar la asistencia
async function procesarLecturaQR(qrContent) {
    const resBox = document.getElementById('qr-scan-result');
    if (!qrContent || !qrContent.trim()) {
        if (resBox) {
            resBox.style.display = 'block';
            resBox.innerHTML = '<div class="alert alert-warning rounded-3 small mb-0"><i class="fas fa-exclamation-triangle me-1"></i>Ingrese la cedula o escanee un codigo QR para continuar.</div>';
        }
        return;
    }
    // mostramos una ruedita de carga mientras el servidor responde
    if (resBox) {
        resBox.style.display = 'block';
        resBox.innerHTML = '<div class="spinner-border spinner-border-sm text-primary me-2"></div>Verificando credencial QR en la seccion...';
    }

    try {
        // hacemos la llamada secreta al servidor con la cedula o texto del qr
        const resp = await fetch('index.php?action=scan_qr_attendance', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                qr_content: qrContent.trim(),
                section_id: CURRENT_SECTION_ID,
                fecha: CURRENT_DATE,
                csrf_token: CSRF_TOKEN
            })
        });
        const data = await resp.json();
        // si todo salio bien
        if (data.success) {
            asistenciasRegistradasSesion++;

            const nombre   = limpiarTextoVista(data.nombre_completo || data.titular || '');
            const cedula   = limpiarTextoVista(data.cedula || '');
            const telefono = limpiarTextoVista(data.telefono || '');
            const correo   = limpiarTextoVista(data.correo || '', true);
            const seccion  = limpiarTextoVista(data.seccion || '');
            const esTardio = data.es_tardio || data.estado_asistencia === 'Tardio';
            // elegimos color amarillo si llego tarde o verde si llego a tiempo
            const alertClass = esTardio ? 'alert-warning' : 'alert-success';
            const iconEstado = esTardio ? 'fa-user-clock text-warning' : 'fa-check-circle text-success';
            const badgeTxt = esTardio 
                ? '<span class="badge bg-warning text-dark px-2 py-1">Tardio (Con retraso)</span>' 
                : '<span class="badge bg-success text-white px-2 py-1">Presente (A tiempo)</span>';

            // cambiamos la fila del estudiante en la tabla sin tener que recargar la pagina completa
            if (data.student_id) {
                const rowEl = document.getElementById('row-asist-' + data.student_id);
                if (rowEl) {
                    const celdaEstado = rowEl.cells[1];
                    if (celdaEstado) {
                        celdaEstado.innerHTML = `
                            <span class="badge ${esTardio ? 'bg-warning text-dark' : 'bg-success text-white'} rounded-pill px-3 py-1.5">
                                <i class="fas ${iconEstado} me-1"></i>${esTardio ? 'Tardio (Con retraso)' : 'Presente (A tiempo)'}
                            </span>
                            <div class="small text-muted mt-1" style="font-size:0.72rem;">
                                <span><i class="far fa-clock me-1"></i>${data.hora_llegada || ''} · QR</span>
                                <div class="text-truncate" style="max-width: 250px; margin: 0 auto;">${limpiarTextoVista(data.message)}</div>
                            </div>
                        `;
                    }
                }
            }

            // mostramos el cuadro con todos los datos del alumno y el boton de escanear siguiente
            resBox.innerHTML = `
                <div class="alert ${alertClass} rounded-4 text-start small mb-3 shadow-sm border">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="fw-bold fs-6"><i class="fas ${iconEstado} me-1"></i>${limpiarTextoVista(data.message)}</div>
                        <div>${badgeTxt}</div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-sm-6"><strong>Hora Llegada:</strong> ${data.hora_llegada || '--:--'}</div>
                        <div class="col-sm-6"><strong>Chance Limite:</strong> ${data.hora_limite || '--:--'}</div>
                        <div class="col-12 border-top pt-2 mt-1"><strong>Estudiante:</strong> ${nombre}</div>
                        <div class="col-sm-6"><strong>Cedula:</strong> ${cedula}</div>
                        <div class="col-sm-6"><strong>Telefono:</strong> ${telefono}</div>
                        <div class="col-12"><strong>Correo:</strong> ${correo}</div>
                        <div class="col-12"><strong>Seccion:</strong> ${seccion}</div>
                    </div>
                </div>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold btn-sm shadow-sm" style="background:#003366;border-color:#003366;" onclick="prepararSiguienteEscaneo()">
                        <i class="fas fa-qrcode me-1"></i>Escanear Siguiente Estudiante
                    </button>
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-3 fw-bold btn-sm" data-bs-dismiss="modal">
                        <i class="fas fa-check me-1"></i>Listo / Cerrar
                    </button>
                </div>
            `;
        } else {
            // si dio error avisamos si ya habia pasado o si no esta inscrito
            const tituloError = data.ya_paso_asistencia 
                ? 'Asistencia Ya Registrada Hoy' 
                : (data.no_inscrito ? 'Estudiante No Inscrito en la Seccion' : 'Validacion de Asistencia');
            const iconError = data.ya_paso_asistencia ? 'fa-user-check' : 'fa-user-times';

            let estInfoHtml = '';
            if (data.estudiante && data.estudiante.nombre_completo) {
                estInfoHtml = `
                    <div class="p-2 bg-white rounded-3 border text-dark mt-2">
                        <div><strong>Estudiante:</strong> ${limpiarTextoVista(data.estudiante.nombre_completo)}</div>
                        ${data.estudiante.cedula ? `<div><strong>Cedula:</strong> ${limpiarTextoVista(data.estudiante.cedula)}</div>` : ''}
                    </div>
                `;
            }

            resBox.innerHTML = `
                <div class="alert alert-danger rounded-4 text-start small mb-3 shadow-sm border">
                    <div class="fw-bold fs-6 mb-1 text-danger">
                        <i class="fas ${iconError} me-1"></i>${tituloError}
                    </div>
                    <div class="mb-1">${limpiarTextoVista(data.message || 'Credencial QR no valida.')}</div>
                    ${estInfoHtml}
                </div>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-outline-primary rounded-pill px-4 fw-bold btn-sm" onclick="prepararSiguienteEscaneo()">
                        <i class="fas fa-redo me-1"></i>Intentar con Otro Estudiante
                    </button>
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-3 fw-bold btn-sm" data-bs-dismiss="modal">
                        Cerrar
                    </button>
                </div>
            `;
        }
    } catch (err) {
        // error si se cae la conexion a internet
        resBox.innerHTML = `
            <div class="alert alert-danger rounded-3 small mb-2">Error de conexion al validar el QR.</div>
            <div class="text-center">
                <button type="button" class="btn btn-outline-primary rounded-pill btn-sm fw-bold px-3" onclick="prepararSiguienteEscaneo()">
                    <i class="fas fa-redo me-1"></i>Reintentar
                </button>
            </div>
        `;
    }
}

// esta funcion sirve para cuando el profesor hace click en el boton para poner presente o ausente a mano
async function marcarAsistenciaManual(studentId, estado) {
    let observacion = '';
    // si es justificado o tarde le pedimos que escriba la razon
    if (estado === 'Justificado') {
        observacion = prompt('Indique el motivo de la justificacion:', 'Justificativo medico / institucional') || 'Justificado';
    } else if (estado === 'Tardio') {
        observacion = prompt('Observacion de llegada con retraso (opcional):', 'Llegada con retraso') || 'Llegada tarde';
    }

    try {
        const resp = await fetch('index.php?action=manual_attendance', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                student_id: studentId,
                section_id: CURRENT_SECTION_ID,
                fecha: CURRENT_DATE,
                estado: estado,
                observacion: observacion,
                csrf_token: CSRF_TOKEN
            })
        });
        const data = await resp.json();
        if (data.success) {
            location.reload();
        } else {
            notificar(data.message || 'No se pudo actualizar la asistencia.', 'error');
        }
    } catch (err) {
        notificar('Error de red al guardar asistencia.', 'error');
    }
}

// convierte la hora de formato 24 horas a formato con AM y PM
function formatTime12(timeStr) {
    if (!timeStr) return '--:--';
    const parts = timeStr.split(':');
    let h = parseInt(parts[0], 10);
    const m = parts[1] || '00';
    if (isNaN(h)) return timeStr;
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12;
    h = h ? h : 12;
    const hStr = h < 10 ? '0' + h : h;
    return `${hStr}:${m} ${ampm}`;
}

// calcula en vivo hasta que hora tiene chance de llegar el estudiante segun la tolerancia
function updatePreviewHorario() {
    const hiEl = document.getElementById('cfg-hora-inicio');
    const hfEl = document.getElementById('cfg-hora-fin');
    const tolEl = document.getElementById('cfg-tolerancia');
    if (!hiEl || !hfEl || !tolEl) return;

    const hi = hiEl.value;
    const hf = hfEl.value;
    const tol = parseInt(tolEl.value, 10) || 0;

    document.getElementById('pv-inicio').textContent = formatTime12(hi);
    document.getElementById('pv-fin').textContent = formatTime12(hf);
    document.getElementById('pv-tol-min').textContent = tol;
    document.getElementById('badge-tolerancia-val').textContent = tol + ' minutos';

    // sumamos la hora de inicio mas los minutos de tolerancia
    if (hi) {
        const parts = hi.split(':');
        const h = parseInt(parts[0], 10) || 0;
        const m = parseInt(parts[1], 10) || 0;
        const totalMin = h * 60 + m + tol;
        const limH = Math.floor(totalMin / 60) % 24;
        const limM = totalMin % 60;
        const limStr = (limH < 10 ? '0' + limH : limH) + ':' + (limM < 10 ? '0' + limM : limM);
        document.getElementById('pv-limite').textContent = 'Hasta las ' + formatTime12(limStr);
    } else {
        document.getElementById('pv-limite').textContent = '--:--';
    }
}

// botones rapidos para poner 5, 10, 15 o 20 minutos de tolerancia con un solo toque
function setTolerancia(min) {
    const tolEl = document.getElementById('cfg-tolerancia');
    if (tolEl) {
        tolEl.value = min;
        updatePreviewHorario();
    }
}

// abre la ventanita modal para cambiar el horario o tolerancia de la clase
function abrirConfigurarHorario(sec) {
    document.getElementById('cfg-seccion-id').value = sec.id;
    document.getElementById('cfg-materia-txt').textContent = sec.nombre_materia + ' (' + sec.codigo_materia + ')';
    document.getElementById('cfg-seccion-txt').textContent = 'Seccion: ' + sec.nombre_seccion + ' · ' + sec.dia_semana;
    document.getElementById('cfg-hora-inicio').value = sec.hora_inicio ? sec.hora_inicio.substr(0, 5) : '08:00';
    document.getElementById('cfg-hora-fin').value = sec.hora_fin ? sec.hora_fin.substr(0, 5) : '10:15';
    document.getElementById('cfg-tolerancia').value = sec.tolerancia_minutos !== undefined ? sec.tolerancia_minutos : 15;
    document.getElementById('cfg-aula').value = sec.aula || 'Aula 01';

    updatePreviewHorario();
    const modal = new bootstrap.Modal(document.getElementById('modalConfigurarHorarioDocente'));
    modal.show();
}
</script>
