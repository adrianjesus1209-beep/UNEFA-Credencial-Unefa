<?php
// este archivo es el panel principal del estudiante
// le muestra sus materias, el horario de la semana, su porcentaje de asistencia y su carnet digital
if ($_SESSION['rol_usuario'] !== 'Estudiante') {
    header('Location: index.php?page=dashboard');
    exit;
}

$id_usuario = (int)$_SESSION['id_usuario'];
$nombre = $_SESSION['nombre_usuario'] ?? 'Estudiante';

$stmt_est_notify = $conexion->prepare(
    "SELECT u.estado, p.foto_perfil, p.fecha_carnetizacion, p.cedula, p.tipo_documento
     FROM usuario u
     JOIN perfil p ON u.id_perfil = p.id
     WHERE u.id = :id LIMIT 1"
);
$stmt_est_notify->execute([':id' => $id_usuario]);
$info_est = $stmt_est_notify->fetch(PDO::FETCH_ASSOC);
$estado_cuenta = $info_est['estado'] ?? 'Pendiente';
$tiene_carnet_aperturado = !empty($info_est['foto_perfil'])
    && $info_est['foto_perfil'] !== 'default.svg'
    && !empty($info_est['fecha_carnetizacion'])
    && file_exists('public/uploads/profiles/' . $info_est['foto_perfil']);

// 1. MIS MATERIAS Y ASISTENCIA CON RESUMEN PORCENTUAL Y VISUAL
$stmt_horario = $conexion->prepare(
    "SELECT m.id AS id_materia, m.nombre_materia, m.codigo_materia, m.semestre,
            s.id AS id_seccion, s.nombre_seccion, s.dia_semana, s.hora_inicio, s.hora_fin, s.aula, s.tolerancia_minutos,
            CONCAT(pdoc.nombre, ' ', pdoc.apellido) AS docente_titular,
            (SELECT COUNT(*) FROM sesion_clase sc WHERE sc.id_seccion = s.id) AS total_clases_dadas,
            (SELECT COUNT(*) FROM asistencia a JOIN sesion_clase sc ON a.id_sesion = sc.id WHERE sc.id_seccion = s.id AND a.id_estudiante = :id1 AND (a.estado_asistencia = 'Presente' OR a.estado_asistencia = 'Tardio')) AS asistencias_materia,
            (SELECT COUNT(*) FROM asistencia a JOIN sesion_clase sc ON a.id_sesion = sc.id WHERE sc.id_seccion = s.id AND a.id_estudiante = :id2 AND a.estado_asistencia = 'Ausente') AS inasistencias_materia,
            (SELECT COUNT(*) FROM asistencia a JOIN sesion_clase sc ON a.id_sesion = sc.id WHERE sc.id_seccion = s.id AND a.id_estudiante = :id3 AND a.estado_asistencia = 'Justificado') AS justificadas_materia,
            (SELECT COUNT(*) FROM asistencia a JOIN sesion_clase sc ON a.id_sesion = sc.id WHERE sc.id_seccion = s.id AND a.id_estudiante = :id4 AND a.estado_asistencia = 'Tardio') AS tardios_materia
     FROM inscripcion_seccion ins
     JOIN seccion s ON ins.id_seccion = s.id
     JOIN materia m ON s.id_materia = m.id
     JOIN usuario udoc ON s.id_docente = udoc.id
     JOIN perfil pdoc ON udoc.id_perfil = pdoc.id
     WHERE ins.id_estudiante = :id0 AND ins.estado = 'Inscrito'
     ORDER BY FIELD(s.dia_semana, 'Lunes','Martes','Miercoles','Jueves','Viernes','Sabado'), s.hora_inicio ASC"
);
$stmt_horario->execute([
    ':id0' => $id_usuario,
    ':id1' => $id_usuario,
    ':id2' => $id_usuario,
    ':id3' => $id_usuario,
    ':id4' => $id_usuario
]);
$horario_asignado = $stmt_horario->fetchAll(PDO::FETCH_ASSOC);

// 2. EXPEDIENTE Y DOCUMENTOS EN MODO SOLO LECTURA
$stmtDocs = $conexion->prepare(
    "SELECT id, nombre_archivo, ruta, tipo, fecha_subida, estado, observaciones
     FROM registro_documentos
     WHERE id_usuario = :id
     ORDER BY id ASC"
);
$stmtDocs->execute([':id' => $id_usuario]);
$documentos_expediente = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);

// 3. HISTORIAL GENERAL DE ASISTENCIAS
$stmt_asist = $conexion->prepare(
    "SELECT a.estado_asistencia AS estado, a.metodo_registro, a.hora_registro, a.observacion,
            sc.fecha_clase AS fecha, sc.observaciones AS tema,
            s.nombre_seccion, m.nombre_materia, m.codigo_materia
     FROM asistencia a
     JOIN sesion_clase sc ON a.id_sesion = sc.id
     JOIN seccion s ON sc.id_seccion = s.id
     JOIN materia m ON s.id_materia = m.id
     WHERE a.id_estudiante = :id
     ORDER BY sc.fecha_clase DESC, a.hora_registro DESC
     LIMIT 25"
);
$stmt_asist->execute([':id' => $id_usuario]);
$historial_asistencias = $stmt_asist->fetchAll(PDO::FETCH_ASSOC);

$total_presentes = 0;
$total_tardios = 0;
$total_ausentes = 0;
$total_justificados = 0;
foreach ($historial_asistencias as $ha) {
    if ($ha['estado'] === 'Presente') $total_presentes++;
    elseif ($ha['estado'] === 'Tardio') $total_tardios++;
    elseif ($ha['estado'] === 'Ausente') $total_ausentes++;
    elseif ($ha['estado'] === 'Justificado') $total_justificados++;
}
?>

<div class="container py-5 mt-3">
    <!-- Aviso de Carnet Pendiente si no tiene foto -->
    <?php if (!$tiene_carnet_aperturado): ?>
        <div class="alert alert-info rounded-4 shadow-sm border-0 p-4 mb-4 d-flex align-items-center gap-3">
            <i class="fas fa-id-badge fa-2x text-primary"></i>
            <div>
                <h6 class="fw-bold mb-1 text-dark">Codigo QR Activo · Carnet Digital Bloqueado</h6>
                <p class="mb-0 small text-muted">
                    Tu <strong>Codigo QR</strong> ya se encuentra activo para registrar tu asistencia en las clases. Para habilitar la descarga de tu <strong>Carnet Digital Oficial</strong>, acude a la Coordinacion Academica para la toma de la fotografia institucional.
                </p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Banner Principal del Estudiante -->
    <div class="p-4 p-md-5 mb-5 rounded-4 bg-white shadow-sm border-0 d-flex flex-column flex-md-row align-items-center justify-content-between gap-4" style="border-bottom: 6px solid #003366 !important;">
        <div class="d-flex flex-column flex-md-row align-items-center gap-4 text-center text-md-start">
            <div class="rounded-circle p-4" style="background: #e8eeff; color: #003366; font-size: 3rem;">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div>
                <div class="badge bg-primary px-3 py-2 rounded-pill mb-2 text-uppercase" style="background: #003366 !important; letter-spacing: 1px;">NIVEL 2 · ESTUDIANTE REGULAR</div>
                <h1 class="fw-bold h2 mb-1">Hola, <?= htmlspecialchars($nombre) ?></h1>
                <p class="text-muted mb-0">Consulta tus asignaturas, porcentaje de asistencia, credencial digital y expediente institucional.</p>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 justify-content-center">
            <a href="index.php?page=enrollment" class="btn btn-warning rounded-pill px-4 fw-bold shadow-sm text-dark">
                <i class="fas fa-edit me-2"></i>Inscribir Materias
            </a>
            <a href="index.php?page=mi_qr" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
                <i class="fas fa-qrcode me-2"></i>Mi Codigo QR
            </a>
            <?php if ($tiene_carnet_aperturado): ?>
                <a href="index.php?page=mi_carnet" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" style="background:#003366;border:none;">
                    <i class="fas fa-id-card me-2"></i>Mi Carnet Digital
                </a>
            <?php else: ?>
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" disabled title="Requiere fotografia oficial tomada por Coordinacion">
                    <i class="fas fa-lock me-2"></i>Carnet Bloqueado
                </button>
            <?php endif; ?>
            <a href="index.php?page=mi_perfil" class="btn btn-outline-dark rounded-pill px-3 fw-bold">
                <i class="fas fa-user me-1"></i>Perfil
            </a>
        </div>
    </div>

    <!-- Visualizacion Oficial del Ciclo Academico (Solo Lectura) -->
    <?php PeriodoHelper::renderBannerCiclo($conexion, 'Estudiante'); ?>

    <!-- SECCION 1: MIS MATERIAS Y ASISTENCIA (RESUMEN PORCENTUAL Y VISUAL) -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
        <div class="card-header border-0 fw-bold py-3 px-4 d-flex flex-wrap align-items-center justify-content-between gap-2" style="background:#003366;color:white;">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-book-reader me-1"></i>Mis Materias y Horario
                <span class="badge bg-light text-dark rounded-pill"><?= count($horario_asignado) ?> inscritas</span>
            </div>
            
            <div class="d-flex flex-wrap align-items-center gap-2">
                <?php if (!empty($horario_asignado)): ?>
                    <div class="btn-group btn-group-sm p-0.5 bg-white bg-opacity-25 rounded-pill" role="group">
                        <button type="button" class="btn btn-sm rounded-pill fw-bold btn-light text-dark shadow-xs" id="btn-estudiante-matriz" onclick="cambiarVistaEstudiante('matriz')">
                            <i class="fas fa-calendar-week me-1"></i>Cronograma Semanal
                        </button>
                        <button type="button" class="btn btn-sm rounded-pill fw-bold text-white" id="btn-estudiante-tarjetas" onclick="cambiarVistaEstudiante('tarjetas')">
                            <i class="fas fa-th-large me-1"></i>Tarjetas de Materias
                        </button>
                    </div>
                <?php endif; ?>
                <a href="index.php?page=enrollment" class="btn btn-sm btn-outline-light rounded-pill px-3 fw-bold">
                    <i class="fas fa-edit me-1"></i>Inscribir Materias
                </a>
            </div>
        </div>
        <div class="card-body p-4">
            <?php if (empty($horario_asignado)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-calendar-times fa-3x mb-3 opacity-25"></i>
                    <h5 class="fw-bold text-dark">No tienes materias inscritas para este periodo</h5>
                    <p class="small mb-3">Has formalizado tu admision como Estudiante regular. Inscribe tus asignaturas para generar tu horario y registrar asistencia.</p>
                    <a href="index.php?page=enrollment" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                        <i class="fas fa-edit me-2"></i>Inscribir Materias Ahora
                    </a>
                </div>
            <?php else: ?>
                <!-- Vista 1: Matriz de Cronograma Semanal (Estilo Oficial UNEFA) -->
                <div id="contenedor-estudiante-matriz">
                    <?php
                    $seccionesMatriz = $horario_asignado;
                    $esEditable      = false;
                    $rolVista        = 'Estudiante';
                    $tituloMatriz    = 'Mi Cronograma Semanal de Clases (Ingeniería de Sistemas)';
                    require 'app/views/coordinator/partials/horario_matrix.php';
                    ?>
                </div>

                <!-- Vista 2: Tarjetas de Materia y Asistencia -->
                <div id="contenedor-estudiante-tarjetas" style="display:none;" class="row g-4">
                    <?php foreach ($horario_asignado as $m): 
                        $clasesDadas = (int)$m['total_clases_dadas'];
                        $presentes   = (int)$m['asistencias_materia'];
                        $ausentes    = (int)$m['inasistencias_materia'];
                        $justificadas= (int)$m['justificadas_materia'];

                        if ($clasesDadas > 0) {
                            $porcentaje = round(($presentes / $clasesDadas) * 100);
                        } else {
                            $porcentaje = 100;
                        }

                        if ($porcentaje >= 75) {
                            $colorBarra = '#16a34a';
                            $estadoTexto = 'Regular (Buen Nivel)';
                            $badgeEstado = 'bg-success';
                        } elseif ($porcentaje >= 50) {
                            $colorBarra = '#eab308';
                            $estadoTexto = 'Atencion (Riesgo)';
                            $badgeEstado = 'bg-warning text-dark';
                        } else {
                            $colorBarra = '#dc2626';
                            $estadoTexto = 'Critico (Inasistencias Altas)';
                            $badgeEstado = 'bg-danger';
                        }
                    ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card border shadow-sm rounded-4 h-100 p-4" style="border-top: 4px solid <?= $colorBarra ?> !important;">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <span class="badge bg-light text-dark border small fw-bold"><?= htmlspecialchars($m['codigo_materia']) ?></span>
                                    <span class="badge bg-primary rounded-pill small ms-1"><?= htmlspecialchars($m['nombre_seccion']) ?></span>
                                </div>
                                <span class="badge <?= $badgeEstado ?> rounded-pill px-2 py-1 small"><?= $porcentaje ?>% Asistencia</span>
                            </div>

                            <h5 class="fw-bold mb-1 text-dark" style="min-height: 48px;"><?= htmlspecialchars($m['nombre_materia']) ?></h5>
                            <p class="small text-muted mb-3"><i class="fas fa-chalkboard-teacher me-1"></i> Prof. <?= htmlspecialchars($m['docente_titular']) ?></p>

                            <!-- Barra de Progreso Visual de Asistencia -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between small text-muted mb-1">
                                    <span>Cumplimiento de Asistencia</span>
                                    <span class="fw-bold" style="color:<?= $colorBarra ?>;"><?= $porcentaje ?>%</span>
                                </div>
                                <div class="progress rounded-pill" style="height: 10px;">
                                    <div class="progress-bar rounded-pill" role="progressbar" style="width: <?= $porcentaje ?>%; background-color: <?= $colorBarra ?>;" aria-valuenow="<?= $porcentaje ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>

                            <div class="row g-2 text-center small mb-3">
                                <div class="col-4">
                                    <div class="p-2 rounded-3 bg-light border">
                                        <div class="text-success fw-bold"><?= $presentes ?></div>
                                        <div class="text-muted" style="font-size:0.7rem;">Presentes</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-2 rounded-3 bg-light border">
                                        <div class="text-danger fw-bold"><?= $ausentes ?></div>
                                        <div class="text-muted" style="font-size:0.7rem;">Faltas</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-2 rounded-3 bg-light border">
                                        <div class="text-dark fw-bold"><?= $clasesDadas ?></div>
                                        <div class="text-muted" style="font-size:0.7rem;">Sesiones</div>
                                    </div>
                                </div>
                            </div>

                            <?php
                            $tolMateria = (int)($m['tolerancia_minutos'] ?? 15);
                            $tsMInicio = strtotime('2000-01-01 ' . $m['hora_inicio']);
                            $tsMFin = strtotime('2000-01-01 ' . $m['hora_fin']);
                            $tsMLimite = $tsMInicio + ($tolMateria * 60);
                            $horaEmpiezaTxt = date('h:i A', $tsMInicio);
                            $horaAcabaTxt = date('h:i A', $tsMFin);
                            $horaLimiteTxt = date('h:i A', $tsMLimite);
                            ?>
                            <div class="border-top pt-2 mt-auto">
                                <div class="d-flex justify-content-between align-items-center small mb-2">
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 fw-bold">
                                        <i class="far fa-calendar-alt me-1"></i><?= htmlspecialchars($m['dia_semana']) ?>
                                    </span>
                                    <span class="badge bg-light text-dark border rounded-pill px-2 py-1">
                                        <i class="fas fa-door-open me-1"></i><?= htmlspecialchars($m['aula']) ?>
                                    </span>
                                </div>
                                <div class="p-2 rounded-3 bg-light border" style="font-size:0.75rem;">
                                    <div class="d-flex justify-content-between text-muted mb-1">
                                        <span><strong>Empieza:</strong> <?= $horaEmpiezaTxt ?></span>
                                        <span><strong>Acaba:</strong> <?= $horaAcabaTxt ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center pt-1 border-top">
                                        <span class="fw-bold text-dark">
                                            <i class="fas fa-hourglass-half text-warning me-1"></i>Chance limite:
                                        </span>
                                        <span class="badge bg-warning text-dark fw-bold px-2 py-1">
                                            Hasta <?= $horaLimiteTxt ?> (+<?= $tolMateria ?>m)
                                        </span>
                                    </div>
                                    <div class="text-muted text-end mt-1" style="font-size:0.67rem;">
                                        Llegadas despues seran marcadas como Con Retraso.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function cambiarVistaEstudiante(tipo) {
        const cMatriz = document.getElementById('contenedor-estudiante-matriz');
        const cTarjetas = document.getElementById('contenedor-estudiante-tarjetas');
        const bMatriz = document.getElementById('btn-estudiante-matriz');
        const bTarjetas = document.getElementById('btn-estudiante-tarjetas');

        if (tipo === 'matriz') {
            if (cMatriz) cMatriz.style.display = 'block';
            if (cTarjetas) cTarjetas.style.display = 'none';
            bMatriz.className = 'btn btn-sm rounded-pill fw-bold btn-light text-dark shadow-xs';
            bTarjetas.className = 'btn btn-sm rounded-pill fw-bold text-white';
        } else {
            if (cMatriz) cMatriz.style.display = 'none';
            if (cTarjetas) cTarjetas.style.display = 'flex';
            bMatriz.className = 'btn btn-sm rounded-pill fw-bold text-white';
            bTarjetas.className = 'btn btn-sm rounded-pill fw-bold btn-light text-dark shadow-xs';
        }
    }
    </script>

    <!-- SECCION 2: EXPEDIENTE Y DOCUMENTOS (COMPRIMIDO CON ACCESO A MODAL) -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white" style="border-left: 5px solid #003366 !important;">
        <div class="card-body p-4">
            <div class="row align-items-center gy-3">
                <div class="col-xl-5 col-lg-5 col-12">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-4 d-flex align-items-center justify-content-center text-primary flex-shrink-0" style="background: rgba(0, 51, 102, 0.08); width: 50px; height: 50px;">
                            <i class="fas fa-folder-open fa-2x" style="color: #003366;"></i>
                        </div>
                        <div>
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <h6 class="fw-bold text-dark mb-0 fs-6">Expediente de Recaudos y Documentos Oficiales</h6>
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 small fw-semibold">
                                    <i class="fas fa-check-circle me-1"></i><?= count($documentos_expediente) ?> Requisitos Aprobados
                                </span>
                            </div>
                            <p class="text-muted small mb-0">
                                Recaudos de admision validados por Control de Estudios (Modo solo lectura).
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-xl-7 col-lg-7 col-12">
                    <div class="d-flex flex-wrap gap-2 justify-content-lg-end align-items-center">
                        <button type="button" class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalExpedienteCompleto">
                            <i class="fas fa-folder-open"></i>
                            <span>Ver Expediente</span>
                        </button>
                        
                        <div class="btn-group shadow-sm rounded-pill border bg-white" style="overflow: hidden;">
                            <a href="index.php?action=constancia_estudio&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-sm btn-light bg-white border-0 px-3 py-2 fw-semibold text-secondary" title="Descargar Constancia de Estudio">
                                <i class="fas fa-file-pdf text-danger me-1"></i> Constancia
                            </a>
                            <a href="index.php?action=constancia_inscripcion&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-sm btn-light bg-white border-0 border-start px-3 py-2 fw-semibold text-secondary" title="Descargar Comprobante de Horario">
                                <i class="fas fa-clock text-primary me-1"></i> Horario
                            </a>
                            <a href="index.php?page=planilla" target="_blank" class="btn btn-sm btn-light bg-white border-0 border-start px-3 py-2 fw-semibold text-secondary" title="Ver Planilla de Pre-Inscripcion">
                                <i class="fas fa-file-alt text-info me-1"></i> Planilla
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SECCION 3: HISTORIAL GENERAL DE ASISTENCIAS -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header border-0 fw-bold py-3 px-4 d-flex flex-wrap align-items-center justify-content-between gap-2" style="background:#003366;color:white;">
            <div>
                <i class="fas fa-clipboard-check me-2"></i>Historial de Marcajes de Asistencia
            </div>
            <div class="d-flex flex-wrap gap-2 small">
                <span class="badge bg-success rounded-pill px-3">Presente: <?= $total_presentes ?></span>
                <span class="badge bg-warning text-dark rounded-pill px-3">Tardio: <?= $total_tardios ?></span>
                <span class="badge bg-danger rounded-pill px-3">Ausente: <?= $total_ausentes ?></span>
                <span class="badge bg-info text-dark rounded-pill px-3">Justificado: <?= $total_justificados ?></span>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($historial_asistencias)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-qrcode fa-3x mb-2 opacity-25"></i>
                    <p class="mb-0 small">Aun no tienes registros de asistencia en el periodo activo.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="small text-muted bg-light">
                            <tr>
                                <th class="ps-4 py-3">Fecha</th>
                                <th>Materia / Seccion</th>
                                <th>Tema de la Sesion</th>
                                <th>Metodo de Marcaje</th>
                                <th class="text-end pe-4">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historial_asistencias as $ha): ?>
                            <tr>
                                <td class="ps-4 small fw-semibold"><?= date('d/m/Y', strtotime($ha['fecha'])) ?></td>
                                <td>
                                    <div class="fw-bold small" style="color:#003366;"><?= htmlspecialchars($ha['nombre_materia']) ?></div>
                                    <div class="text-muted" style="font-size:0.75rem;"><?= htmlspecialchars($ha['nombre_seccion']) ?></div>
                                </td>
                                <td class="small text-muted"><?= htmlspecialchars($ha['tema'] ?: 'Sesion de clase regular') ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border small">
                                        <i class="fas <?= $ha['metodo_registro'] === 'QR' ? 'fa-qrcode text-primary' : 'fa-hand-pointer text-secondary' ?> me-1"></i>
                                        <?= htmlspecialchars($ha['metodo_registro']) ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <?php
                                    $bClass = 'bg-secondary';
                                    $iconEstado = 'fa-check';
                                    if ($ha['estado'] === 'Presente') {
                                        $bClass = 'bg-success';
                                        $iconEstado = 'fa-check';
                                    } elseif ($ha['estado'] === 'Tardio') {
                                        $bClass = 'bg-warning text-dark';
                                        $iconEstado = 'fa-user-clock';
                                    } elseif ($ha['estado'] === 'Justificado') {
                                        $bClass = 'bg-info text-dark';
                                        $iconEstado = 'fa-file-medical';
                                    } elseif ($ha['estado'] === 'Ausente') {
                                        $bClass = 'bg-danger';
                                        $iconEstado = 'fa-times';
                                    }
                                    ?>
                                    <span class="badge <?= $bClass ?> rounded-pill px-3 py-1">
                                        <i class="fas <?= $iconEstado ?> me-1"></i><?= htmlspecialchars($ha['estado']) ?>
                                    </span>
                                    <?php if (!empty($ha['observacion'])): ?>
                                        <small class="d-block text-muted mt-1" style="font-size:0.72rem;"><?= htmlspecialchars($ha['observacion']) ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- MODAL COMPLETO DE EXPEDIENTE DE RECAUDOS (SOLO LECTURA) -->
<div class="modal fade" id="modalExpedienteCompleto" tabindex="-1" aria-labelledby="modalExpedienteCompletoLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 py-3 px-4" style="background:#003366; color:white;">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-folder-open text-warning fa-lg me-1"></i>
                    <div>
                        <h6 class="modal-title fw-bold mb-0" id="modalExpedienteCompletoLabel">
                            Expediente Digital de Recaudos Oficiales
                        </h6>
                        <small class="text-white-50" style="font-size:0.75rem;">
                            Control de Estudios - UNEFA | Expediente Verificado
                        </small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-success rounded-pill px-3 py-1 small fw-semibold">
                        <i class="fas fa-check-double me-1"></i><?= count($documentos_expediente) ?> Recaudos
                    </span>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
            </div>

            <div class="modal-body p-0">
                <!-- Banner Informativo -->
                <div class="bg-light border-bottom p-3 px-4 d-flex align-items-center gap-3">
                    <i class="fas fa-shield-alt text-primary fa-lg"></i>
                    <div class="small text-muted">
                        <strong>Modo Solo Lectura Institucional:</strong> Los recaudos cargados en tu etapa de pre-inscripcion han sido validados formalmente por Control de Estudios. Como estudiante regular, estos archivos se conservan en modo solo lectura para consulta y descarga, quedando estrictamente prohibida su modificacion o eliminacion.
                    </div>
                </div>

                <div class="row g-0">
                    <!-- Lista de Documentos (Columna Izquierda) -->
                    <div class="col-lg-5 border-end" style="max-height: 560px; overflow-y: auto;">
                        <div class="p-3 bg-light border-bottom text-muted small fw-bold text-uppercase d-flex justify-content-between align-items-center">
                            <span>Recaudos del Expediente</span>
                            <span class="badge bg-secondary-subtle text-secondary"><?= count($documentos_expediente) ?> archivos</span>
                        </div>
                        <div class="list-group list-group-flush" id="listaDocsExpediente">
                            <?php foreach ($documentos_expediente as $idx => $doc): 
                                $rutaLimpia = trim(str_replace('\\', '/', $doc['ruta']));
                                $rutaLimpia = ltrim($rutaLimpia, '/');
                                
                                $rutaFisica = null;
                                if (file_exists($rutaLimpia)) {
                                    $rutaFisica = $rutaLimpia;
                                } elseif (file_exists('public/' . $rutaLimpia)) {
                                    $rutaFisica = 'public/' . $rutaLimpia;
                                }
                                
                                $existeFisico = ($rutaFisica !== null);
                                $urlVisual = '';
                                if ($existeFisico) {
                                    $partes = explode('/', $rutaFisica);
                                    $partesCodificadas = array_map('rawurlencode', $partes);
                                    $urlVisual = implode('/', $partesCodificadas);
                                }
                                $extDoc = strtolower(pathinfo($rutaFisica ?? $doc['ruta'], PATHINFO_EXTENSION));

                                $iconoTipo = 'fa-file';
                                $colorIcono = '#64748b';
                                if ($doc['tipo'] === 'PDF') {
                                    $iconoTipo = 'fa-file-pdf';
                                    $colorIcono = '#dc2626';
                                } elseif ($doc['tipo'] === 'Word') {
                                    $iconoTipo = 'fa-file-word';
                                    $colorIcono = '#2563eb';
                                } elseif ($doc['tipo'] === 'Imagen') {
                                    $iconoTipo = 'fa-file-image';
                                    $colorIcono = '#16a34a';
                                }
                            ?>
                            <div class="list-group-item list-group-item-action p-3 d-flex align-items-center justify-content-between border-bottom item-doc-expediente <?= $idx === 0 ? 'active-item' : '' ?>" 
                                 style="cursor: pointer; transition: all 0.2s ease;"
                                 data-url="<?= htmlspecialchars($urlVisual) ?>"
                                 data-nombre="<?= htmlspecialchars($doc['nombre_archivo']) ?>"
                                 data-tipo="<?= htmlspecialchars($doc['tipo']) ?>"
                                 data-ext="<?= htmlspecialchars($extDoc) ?>"
                                 data-fecha="<?= date('d/m/Y h:i A', strtotime($doc['fecha_subida'])) ?>"
                                 onclick="seleccionarDocExpediente(this)">
                                <div class="d-flex align-items-center gap-3 overflow-hidden me-2">
                                    <div class="rounded-3 p-2 text-center flex-shrink-0" style="background: <?= $colorIcono ?>15; color: <?= $colorIcono ?>; width: 38px; height: 38px;">
                                        <i class="fas <?= $iconoTipo ?>"></i>
                                    </div>
                                    <div class="overflow-hidden">
                                        <div class="fw-bold text-dark text-truncate small mb-1" title="<?= htmlspecialchars($doc['nombre_archivo']) ?>">
                                            <?= htmlspecialchars($doc['nombre_archivo']) ?>
                                        </div>
                                        <div class="d-flex align-items-center gap-2 small text-muted" style="font-size: 0.72rem;">
                                            <span class="badge bg-light text-dark border px-2 py-0"><?= htmlspecialchars($doc['tipo']) ?></span>
                                            <span><?= date('d/m/Y', strtotime($doc['fecha_subida'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                    <?php if ($existeFisico): ?>
                                        <span class="badge bg-success rounded-pill px-2 py-1 small">Aprobado</span>
                                        <a href="<?= htmlspecialchars($urlVisual) ?>" download class="btn btn-sm btn-light border rounded-circle text-muted" title="Descarga directa" onclick="event.stopPropagation();" style="width: 28px; height: 28px; padding: 0; display: inline-flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-download small"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-secondary rounded-pill px-2 py-1 small">Archivado</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Panel de Previsualizacion en Vivo (Columna Derecha) -->
                    <div class="col-lg-7 d-flex flex-column bg-dark" style="min-height: 480px; max-height: 560px;">
                        <!-- Barra superior del visor -->
                        <div class="py-2 px-3 bg-black bg-opacity-50 text-white d-flex align-items-center justify-content-between border-bottom border-secondary border-opacity-25">
                            <div class="d-flex align-items-center gap-2 overflow-hidden me-2">
                                <i class="fas fa-eye text-warning small"></i>
                                <span class="fw-bold small text-truncate" id="visorTituloDoc">Previsualizacion</span>
                            </div>
                            <div class="d-flex gap-2 flex-shrink-0">
                                <a id="visorBtnPestana" href="#" target="_blank" rel="noopener noreferrer" class="btn btn-outline-light btn-sm rounded-pill px-3 fw-bold small" title="Abrir en pestaña independiente">
                                    <i class="fas fa-external-link-alt me-1"></i> Abrir
                                </a>
                                <a id="visorBtnDescarga" href="#" download class="btn btn-primary btn-sm rounded-pill px-3 fw-bold small" title="Descargar archivo">
                                    <i class="fas fa-download me-1"></i> Descargar
                                </a>
                            </div>
                        </div>

                        <!-- Area de renderizado del documento -->
                        <div class="flex-grow-1 p-0 d-flex align-items-center justify-content-center overflow-hidden position-relative" id="visorCuerpoDoc" style="background:#0f172a;">
                            <div class="text-white-50 text-center p-4">
                                <i class="fas fa-folder-open fa-3x mb-3 text-secondary opacity-50"></i>
                                <p class="small mb-0">Selecciona un documento de la lista para previsualizarlo.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light border-0 py-2 px-4 justify-content-between">
                <div class="d-flex align-items-center gap-2 small text-muted">
                    <i class="fas fa-shield-alt text-success"></i>
                    <span>Expediente Digital Certificado - UNEFA</span>
                </div>
                <div class="d-flex gap-2">
                    <a href="index.php?action=constancia_estudio&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3 fw-semibold">
                        <i class="fas fa-file-pdf text-danger me-1"></i> Constancia
                    </a>
                    <a href="index.php?action=constancia_inscripcion&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3 fw-semibold">
                        <i class="fas fa-clock text-primary me-1"></i> Horario
                    </a>
                    <button type="button" class="btn btn-secondary btn-sm rounded-pill px-4 fw-bold" data-bs-dismiss="modal">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.item-doc-expediente:hover {
    background-color: #f8fafc;
}
.item-doc-expediente.active-item {
    background-color: #f1f5f9;
    border-left: 4px solid #003366 !important;
}
</style>

<script>
function seleccionarDocExpediente(elemento) {
    document.querySelectorAll('.item-doc-expediente').forEach(function(el) {
        el.classList.remove('active-item');
        el.style.borderLeft = 'none';
    });
    
    elemento.classList.add('active-item');
    elemento.style.borderLeft = '4px solid #003366';
    
    var url = elemento.getAttribute('data-url') || '';
    var nombre = elemento.getAttribute('data-nombre') || 'Documento';
    var ext = (elemento.getAttribute('data-ext') || '').toLowerCase();
    
    var tituloEl = document.getElementById('visorTituloDoc');
    var cuerpoEl = document.getElementById('visorCuerpoDoc');
    var btnPestana = document.getElementById('visorBtnPestana');
    var btnDescarga = document.getElementById('visorBtnDescarga');
    
    if (tituloEl) tituloEl.innerText = nombre;
    if (btnPestana) btnPestana.href = url;
    if (btnDescarga) btnDescarga.href = url;
    
    if (!url) {
        cuerpoEl.innerHTML = '<div class="text-white-50 text-center p-4"><i class="fas fa-file-archive fa-3x mb-2 opacity-50"></i><p class="small mb-0">El archivo se encuentra archivado fisicamente.</p></div>';
        return;
    }
    
    cuerpoEl.innerHTML = '<div class="text-white p-4 text-center"><i class="fas fa-spinner fa-spin fa-2x mb-2"></i><p class="small mb-0">Cargando...</p></div>';
    
    setTimeout(function() {
        if (['jpg', 'jpeg', 'png', 'gif', 'webp'].indexOf(ext) !== -1) {
            cuerpoEl.innerHTML = '<div class="p-3 w-100 h-100 d-flex align-items-center justify-content-center"><img src="' + url + '" alt="' + nombre + '" style="max-width: 100%; max-height: 500px; object-fit: contain; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.5);"></div>';
        } else if (ext === 'pdf') {
            cuerpoEl.innerHTML = '<iframe src="' + url + '" style="width: 100%; height: 500px; border: none;"></iframe>';
        } else if (['doc', 'docx'].indexOf(ext) !== -1) {
            cuerpoEl.innerHTML = '<div class="text-white text-center p-5">' +
                '<i class="fas fa-file-word fa-4x mb-3 text-info"></i>' +
                '<h5 class="fw-bold mb-2 text-white">Documento Microsoft Word</h5>' +
                '<p class="text-white-50 small mb-4">Los archivos .doc y .docx se descargan directamente a tu dispositivo para abrirlos en Word o LibreOffice.</p>' +
                '<a href="' + url + '" download class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">' +
                    '<i class="fas fa-download me-2"></i>Descargar Archivo Word' +
                '</a>' +
            '</div>';
        } else {
            cuerpoEl.innerHTML = '<div class="text-white text-center p-5">' +
                '<i class="fas fa-file fa-4x mb-3 text-warning"></i>' +
                '<h5 class="fw-bold mb-2 text-white">Archivo ' + ext.toUpperCase() + '</h5>' +
                '<a href="' + url + '" download class="btn btn-light rounded-pill px-4 fw-bold shadow-sm mt-2">' +
                    '<i class="fas fa-download me-2"></i>Descargar Archivo' +
                '</a>' +
            '</div>';
        }
    }, 100);
}

document.addEventListener('DOMContentLoaded', function() {
    var modalExpedienteEl = document.getElementById('modalExpedienteCompleto');
    if (modalExpedienteEl) {
        modalExpedienteEl.addEventListener('shown.bs.modal', function () {
            var primerItem = document.querySelector('.item-doc-expediente');
            if (primerItem) {
                seleccionarDocExpediente(primerItem);
            }
        });
    }
});
</script>
