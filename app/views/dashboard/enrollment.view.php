<?php
// este archivo es la pantalla donde el estudiante inscribe sus materias
// aqui revisa cuales materias puede cursar segun las materias que ya aprobo y el limite de 30 unidades de credito
if (!isset($_SESSION['rol_usuario']) || $_SESSION['rol_usuario'] !== 'Estudiante') {
    header('Location: index.php?page=dashboard');
    exit;
}

$id_usuario = (int)$_SESSION['id_usuario'];

// 1. revisamos si el periodo academico actual esta abierto y permite inscripciones
$stmtPer = $conexion->query("SELECT id, estado, nombre FROM periodo_academico ORDER BY FIELD(estado, 'Activo', 'Planificado', 'Finalizado') LIMIT 1");
$periodoActual = $stmtPer->fetch(PDO::FETCH_ASSOC);
$periodoPermite = $periodoActual && $periodoActual['estado'] === 'Activo';
$id_periodo_activo = $periodoActual ? (int)$periodoActual['id'] : 0;

// 2. buscamos el perfil del alumno con su carrera
$stmt = $conexion->prepare("
    SELECT p.*, c.nombre_carrera 
    FROM perfil p 
    JOIN usuario u ON p.id = u.id_perfil 
    LEFT JOIN carrera c ON p.id_carrera = c.id 
    WHERE u.id = :id
");
$stmt->execute([':id' => $id_usuario]);
$perfil = $stmt->fetch(PDO::FETCH_ASSOC);

// 3. traemos las materias que ya aprobo con nota mayor o igual a 10 para revisar prelaciones
$stmtHist = $conexion->prepare("
    SELECT m.id, m.codigo_materia, m.nombre_materia, m.semestre, si.nota, si.estado 
    FROM solicitud_inscripcion si 
    JOIN seccion s ON si.id_seccion = s.id 
    JOIN materia m ON s.id_materia = m.id 
    WHERE si.id_estudiante = :id AND si.estado = 'Aceptada' AND si.nota >= 10
");
$stmtHist->execute([':id' => $id_usuario]);
$materias_aprobadas_raw = $stmtHist->fetchAll(PDO::FETCH_ASSOC);
$ids_materias_aprobadas = array_map('intval', array_column($materias_aprobadas_raw, 'id'));

// 4. buscamos las materias que ya tiene inscritas en este momento
$stmtSol = $conexion->prepare("
    SELECT si.id as id_solicitud, si.id_seccion, si.estado, si.nota,
           s.nombre_seccion, s.aula, s.dia_semana, s.hora_inicio, s.hora_fin,
           m.id as id_materia, m.codigo_materia, m.nombre_materia, m.uc, m.semestre,
           dp.nombre as docente_nombre, dp.apellido as docente_apellido
    FROM solicitud_inscripcion si
    JOIN seccion s ON si.id_seccion = s.id
    JOIN materia m ON s.id_materia = m.id
    LEFT JOIN usuario u ON s.id_docente = u.id
    LEFT JOIN perfil dp ON u.id_perfil = dp.id
    WHERE si.id_estudiante = :id AND si.estado IN ('Pendiente', 'Aceptada')
");
$stmtSol->execute([':id' => $id_usuario]);
$mis_inscripciones = $stmtSol->fetchAll(PDO::FETCH_ASSOC);

// sumamos los creditos acumulados para no pasarse de 30 uc
$materia_inscrita_actual = [];
$creditos_inscritos = 0;
foreach ($mis_inscripciones as $ins) {
    $materia_inscrita_actual[(int)$ins['id_materia']] = $ins;
    $creditos_inscritos += (int)$ins['uc'];
}

// 5. Mapa de Prelaciones
$prelaciones_raw = $conexion->query("
    SELECT p.id_materia, p.id_prerrequisito, pr.codigo_materia as pre_cod, pr.nombre_materia as pre_nom
    FROM prelacion p
    JOIN materia pr ON p.id_prerrequisito = pr.id
")->fetchAll(PDO::FETCH_ASSOC);
$mapa_prelaciones = [];
foreach ($prelaciones_raw as $p) {
    $mapa_prelaciones[(int)$p['id_materia']][] = [
        'id'     => (int)$p['id_prerrequisito'],
        'codigo' => $p['pre_cod'],
        'nombre' => $p['pre_nom']
    ];
}

// 6. Catalogo de Materias por Semestre (incluye semestres 0 al 8 y Electivas 99)
$materias_todas = $conexion->query("SELECT * FROM materia ORDER BY semestre IS NULL, semestre ASC, orden ASC, nombre_materia ASC")->fetchAll(PDO::FETCH_ASSOC);
$materias_por_semestre = [];
foreach ($materias_todas as $m) {
    $sem = ($m['semestre'] !== null) ? (int)$m['semestre'] : null;
    if ($sem === null) {
        $materias_por_semestre[99][] = $m;
        continue;
    }
    $materias_por_semestre[$sem][] = $m;
}

// 7. Secciones Ofertadas por Materia
$stmtSec = $conexion->prepare("
    SELECT sec.*, p.nombre as docente_nombre, p.apellido as docente_apellido
    FROM seccion sec
    JOIN usuario u ON sec.id_docente = u.id
    JOIN perfil p ON u.id_perfil = p.id
    WHERE sec.id_materia = :id AND u.rol IN ('Profesor', 'Docente', 'Coordinador Academico', 'Coordinador')
    ORDER BY sec.nombre_seccion ASC
");
$secciones_por_materia = [];
foreach ($materias_todas as $m) {
    $stmtSec->execute([':id' => $m['id']]);
    $secciones_por_materia[(int)$m['id']] = $stmtSec->fetchAll(PDO::FETCH_ASSOC);
}

// 8. Etiquetas de Semestre y Calculo de Nivel Desbloqueado
$etiquetas_semestre = [
    0 => 'CINU',
    1 => '1er Semestre',
    2 => '2do Semestre',
    3 => '3er Semestre',
    4 => '4to Semestre',
    5 => '5to Semestre',
    6 => '6to Semestre',
    7 => '7mo Semestre',
    8 => '8vo Semestre',
    99 => 'Electivas'
];

$semestre_desbloqueado = 0;
// Si CINU esta aprobado, desbloquear al menos el semestre 1
if (isset($materias_por_semestre[0]) && !empty($materias_por_semestre[0])) {
    $ids_cinu = array_map('intval', array_column($materias_por_semestre[0], 'id'));
    $cinu_completo = count(array_intersect($ids_cinu, $ids_materias_aprobadas)) >= count($ids_cinu);
    if ($cinu_completo) {
        $semestre_desbloqueado = 1;
    }
} else {
    $semestre_desbloqueado = 1;
}

// Verificar semestres subsiguientes
for ($s = 1; $s <= 8; $s++) {
    if (!isset($materias_por_semestre[$s]) || empty($materias_por_semestre[$s])) continue;
    $ids_s = array_map('intval', array_column($materias_por_semestre[$s], 'id'));
    if (count(array_intersect($ids_s, $ids_materias_aprobadas)) >= count($ids_s)) {
        if ($s + 1 > $semestre_desbloqueado) {
            $semestre_desbloqueado = $s + 1;
        }
    } else {
        break;
    }
}

// Semestre asignado en perfil si es mayor
$semestre_perfil = (int)($perfil['semestre_actual'] ?? 0);
if ($semestre_perfil > $semestre_desbloqueado) {
    $semestre_desbloqueado = min($semestre_perfil, 8);
}

// Semestre activo por defecto al cargar la vista
$semestre_activo_default = $semestre_desbloqueado;

// Semestres que ya fueron aprobados en su totalidad
$semestres_aprobados = [];
foreach ($materias_por_semestre as $sem_num => $mats) {
    if ($sem_num === 99) {
        $ids_s = array_map('intval', array_column($mats, 'id'));
        if (count(array_intersect($ids_s, $ids_materias_aprobadas)) >= 2) {
            $semestres_aprobados[] = 99;
        }
        continue;
    }
    $ids_s = array_map('intval', array_column($mats, 'id'));
    if (!empty($ids_s) && count(array_intersect($ids_s, $ids_materias_aprobadas)) >= count($ids_s)) {
        $semestres_aprobados[] = $sem_num;
    }
}

$photoUrl = !empty($perfil['foto_perfil']) 
    ? 'uploads/profiles/' . rawurlencode($perfil['foto_perfil']) 
    : 'https://ui-avatars.com/api/?name='.urlencode($perfil['nombre'] . ' ' . $perfil['apellido']).'&background=003366&color=fff';
?>

<div class="container-fluid py-4 px-lg-5">
    
    <!-- NOTIFICACIONES FLASH -->
    <?php if (isset($_SESSION['success']) && !empty($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4 d-flex align-items-center gap-3" role="alert">
            <i class="fas fa-check-circle fa-lg text-success"></i>
            <div class="flex-grow-1 fw-bold"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error']) && !empty($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4 d-flex align-items-center gap-3" role="alert">
            <i class="fas fa-exclamation-circle fa-lg text-danger"></i>
            <div class="flex-grow-1 fw-bold"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- TARJETA DE RESUMEN DEL ESTUDIANTE Y ESTADO ACADEMICO -->
    <div class="card border-0 shadow-sm rounded-4 bg-white p-4 mb-4">
        <div class="row align-items-center g-3">
            <div class="col-xl-6 col-lg-6 col-md-12">
                <div class="d-flex align-items-center gap-3">
                    <img src="<?= $photoUrl ?>" alt="Foto Estudiante" class="rounded-circle border shadow-sm" style="width:65px; height:65px; object-fit:cover;">
                    <div>
                        <h4 class="fw-bold mb-1" style="color:#003366;"><?= htmlspecialchars($perfil['nombre'] . ' ' . $perfil['apellido']) ?></h4>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-light text-dark border px-3 py-1 rounded-pill fw-bold">
                                <?= ($perfil['tipo_documento'] ?? 'V') . '-' . $perfil['cedula'] ?>
                            </span>
                            <span class="text-muted small">
                                <i class="fas fa-graduation-cap me-1"></i><?= htmlspecialchars($perfil['nombre_carrera'] ?? 'Ingenieria de Sistemas') ?>
                            </span>
                            <span class="badge rounded-pill px-3 py-1" style="background:#003366; color:#fff;">
                                <i class="fas fa-calendar-alt me-1"></i>Periodo <?= htmlspecialchars($periodoActual['nombre'] ?? '2026-I') ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6 col-lg-6 col-md-12">
                <div class="d-flex flex-wrap justify-content-lg-end align-items-center gap-3">
                    <div class="p-3 bg-light rounded-4 border text-center" style="min-width:140px;">
                        <small class="d-block text-muted text-uppercase fw-bold" style="font-size:0.65rem;">Materias Inscritas</small>
                        <span class="fs-5 fw-bold text-dark"><?= count($mis_inscripciones) ?></span>
                    </div>

                    <div class="p-3 bg-light rounded-4 border" style="min-width:180px;">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small class="text-muted text-uppercase fw-bold" style="font-size:0.65rem;">Creditos (UC)</small>
                            <span class="fw-bold <?= $creditos_inscritos >= 30 ? 'text-danger' : 'text-success' ?>"><?= $creditos_inscritos ?> / 30</span>
                        </div>
                        <?php $porcentaje_uc = min(100, round(($creditos_inscritos / 30) * 100)); ?>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar <?= $creditos_inscritos >= 30 ? 'bg-danger' : 'bg-success' ?>" role="progressbar" style="width: <?= $porcentaje_uc ?>%"></div>
                        </div>
                    </div>

                    <div class="d-flex flex-column gap-2">
                        <a href="index.php?page=student" class="btn btn-outline-dark rounded-pill px-3 py-2 fw-bold small text-nowrap">
                            <i class="fas fa-arrow-left me-1"></i>Panel Principal
                        </a>
                        <?php if (!empty($mis_inscripciones)): ?>
                            <a href="index.php?action=constancia_inscripcion&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-success rounded-pill px-3 py-2 fw-bold small text-nowrap shadow-sm">
                                <i class="fas fa-file-pdf me-1"></i>Comprobante PDF
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ALERTA DE PERIODO CERRADO SI APLICA -->
    <?php if (!$periodoPermite): ?>
        <div class="alert alert-warning border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center gap-3" style="background:#fffbeb; border-left: 5px solid #f59e0b !important;">
            <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
            <div>
                <strong class="text-dark">Inscripciones Cerradas para este Periodo</strong><br>
                <span class="small text-muted">El periodo academico actual se encuentra en estado <strong><?= htmlspecialchars($periodoActual['estado'] ?? 'Cerrado') ?></strong>. Puedes consultar la oferta academica en modo solo lectura.</span>
            </div>
        </div>
    <?php endif; ?>

    <!-- SECCION 1: MIS ASIGNATURAS INSCRITAS EN EL PERIODO ACTUAL -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
        <div class="card-header border-0 py-3 px-4 d-flex justify-content-between align-items-center" style="background:#003366; color:#ffffff;">
            <div class="fw-bold fs-6">
                <i class="fas fa-clipboard-check me-2 text-warning"></i>Mis Asignaturas Inscritas
                <span class="badge bg-light text-dark rounded-pill ms-2"><?= count($mis_inscripciones) ?> en curso</span>
            </div>
            <?php if (!empty($mis_inscripciones)): ?>
                <a href="index.php?action=constancia_inscripcion&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-sm btn-warning rounded-pill px-3 fw-bold text-dark shadow-sm">
                    <i class="fas fa-download me-1"></i>Descargar Comprobante Oficial
                </a>
            <?php endif; ?>
        </div>

        <div class="card-body p-4">
            <?php if (empty($mis_inscripciones)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-folder-open fa-3x mb-3 opacity-25"></i>
                    <h6 class="fw-bold text-dark">Aun no te has inscrito en asignaturas para este periodo</h6>
                    <p class="small text-muted mb-0">Revisa la oferta academica por nivel que se muestra abajo y formaliza tu inscripcion en las secciones habilitadas.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-uppercase text-muted">
                            <tr>
                                <th style="width:120px;">Codigo</th>
                                <th>Asignatura</th>
                                <th>Seccion</th>
                                <th>Docente</th>
                                <th class="text-center">Creditos</th>
                                <th class="text-center">Estado</th>
                                <?php if ($periodoPermite): ?><th class="text-end">Accion</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mis_inscripciones as $ins): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-2 rounded-pill fw-bold">
                                            <?= htmlspecialchars($ins['codigo_materia']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($ins['nombre_materia']) ?></div>
                                        <small class="text-muted">
                                            <?= ($ins['semestre'] === '0' || $ins['semestre'] === 0) ? 'Curso Introductorio (CINU)' : "Semestre {$ins['semestre']}" ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border px-3 py-1 rounded-pill fw-bold">
                                            <i class="fas fa-layer-group me-1 text-primary"></i><?= htmlspecialchars($ins['nombre_seccion']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="small fw-semibold text-dark">
                                            <i class="fas fa-user-tie me-1 text-secondary"></i><?= htmlspecialchars($ins['docente_nombre'] . ' ' . $ins['docente_apellido']) ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-primary"><?= (int)$ins['uc'] ?> U.C.</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success-subtle text-success border border-success rounded-pill px-3 py-2">
                                            <i class="fas fa-check-circle me-1"></i>Inscrito
                                        </span>
                                    </td>
                                    <?php if ($periodoPermite): ?>
                                        <td class="text-end">
                                            <a href="index.php?action=drop_subject&seccion=<?= $ins['id_seccion'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" 
                                               class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-bold"
                                               onclick="return confirm('¿Confirmas que deseas retirar la asignatura ' + <?= json_encode($ins['nombre_materia']) ?> + '?');">
                                                <i class="fas fa-trash-alt me-1"></i>Retirar
                                            </a>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="4" class="text-end fw-bold text-dark">Total de Creditos Inscritos:</th>
                                <th class="text-center fw-bold text-success fs-6"><?= $creditos_inscritos ?> / 30 U.C.</th>
                                <th colspan="<?= $periodoPermite ? '2' : '1' ?>"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- SECCION 2: OFERTA ACADEMICA POR NIVEL -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-0 pt-3 px-4 pb-1">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <div>
                    <h5 class="fw-bold mb-1" style="color:#003366; font-size:1.1rem;">
                        <i class="fas fa-book-reader me-2"></i>Oferta Academica por Nivel
                    </h5>
                    <p class="text-muted mb-0" style="font-size:0.85rem;">Selecciona el semestre para inscribirte directamente en su seccion habilitada.</p>
                </div>
                <div class="d-flex align-items-center gap-3" style="font-size:0.80rem;">
                    <span class="text-muted"><i class="fas fa-check-circle text-success me-1"></i>Aprobado</span>
                    <span class="text-muted"><i class="fas fa-lock text-muted me-1"></i>Bloqueado</span>
                </div>
            </div>

            <!-- PESTAnAS HORIZONTALES DE SEMESTRE -->
            <ul class="nav nav-pills gap-2 pb-3 flex-nowrap overflow-auto" id="pills-semestres" role="tablist" style="scrollbar-width: thin;">
                <?php foreach ($etiquetas_semestre as $sem_num => $sem_label): 
                    $is_active = ($sem_num === $semestre_activo_default);
                    $is_locked = ($sem_num === 99) ? ($semestre_desbloqueado < 7) : ($sem_num > $semestre_desbloqueado);
                    $is_done   = in_array($sem_num, $semestres_aprobados);
                ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-pill fw-bold px-3 py-2 text-nowrap <?= $is_active ? 'active' : '' ?>"
                            id="tab-sem-<?= $sem_num ?>" 
                            data-bs-toggle="pill" 
                            data-bs-target="#pane-sem-<?= $sem_num ?>" 
                            type="button" 
                            role="tab"
                            aria-controls="pane-sem-<?= $sem_num ?>" 
                            aria-selected="<?= $is_active ? 'true' : 'false' ?>"
                            title="<?= ($sem_num === 0) ? 'Curso Introductorio (CINU)' : (($sem_num === 99) ? 'Electivas Tecnicas y No Tecnicas (7mo y 8vo)' : "Semestre $sem_num") ?>"
                            style="font-size:0.85rem; line-height:1.3; <?= $is_active ? 'background:#003366 !important; color:#fff !important;' : 'background:#f8f9fa; color:#495057;' ?>">
                        <?php if ($is_done): ?>
                            <i class="fas fa-check-circle text-success me-1" style="font-size:0.78rem;"></i>
                        <?php elseif ($is_locked): ?>
                            <i class="fas fa-lock text-muted me-1" style="font-size:0.78rem;"></i>
                        <?php endif; ?>
                        <?= $sem_label ?>
                    </button>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="card-body p-4 pt-2">
            <?php
            // Funcion auxiliar para renderizar cada tarjeta de asignatura
            $render_materia_card = function($m, $is_locked) use ($materia_inscrita_actual, $ids_materias_aprobadas, $mapa_prelaciones, $secciones_por_materia, $periodoPermite, $creditos_inscritos) {
                $mid = (int)$m['id'];
                $inscrita_info = $materia_inscrita_actual[$mid] ?? null;
                $esta_inscrita = ($inscrita_info !== null);
                $esta_aprobada = in_array($mid, $ids_materias_aprobadas);
                
                // Validar prelaciones de esta materia
                $prelaciones_de_materia = $mapa_prelaciones[$mid] ?? [];
                $cumple_prelaciones = true;
                foreach ($prelaciones_de_materia as $pre) {
                    if (!in_array($pre['id'], $ids_materias_aprobadas)) {
                        $cumple_prelaciones = false;
                    }
                }

                $es_defensa = (strpos($m['codigo_materia'], 'DIN-') === 0 || strpos($m['codigo_materia'], 'DEF-') === 0);
                $es_et = (strpos($m['codigo_materia'], 'ET-') === 0);
                $es_ent = (strpos($m['codigo_materia'], 'ENT-') === 0);
                $secciones_disp = $secciones_por_materia[$mid] ?? [];
            ?>
            <div class="col-xxl-4 col-xl-4 col-md-6 col-12">
                <div class="card h-100 border rounded-4 shadow-sm p-3 d-flex flex-column justify-content-between <?= $esta_inscrita ? 'border-success bg-success bg-opacity-10' : '' ?>" style="transition:0.2s; padding:1rem 1.15rem !important;">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge rounded-pill fw-bold" style="background:#e0e7ff; color:#1e40af; font-size:0.72rem; padding:0.22rem 0.55rem;">
                                <?= htmlspecialchars($m['codigo_materia']) ?>
                            </span>
                            <div class="d-flex gap-1 align-items-center">
                                <?php if ($es_defensa): ?>
                                    <span class="badge bg-dark-subtle text-dark rounded-pill px-2 py-0.5" style="font-size:0.66rem;">Defensa</span>
                                <?php elseif ($es_et): ?>
                                    <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-0.5" style="font-size:0.66rem;">Tecnica</span>
                                <?php elseif ($es_ent): ?>
                                    <span class="badge bg-warning-subtle text-dark rounded-pill px-2 py-0.5" style="font-size:0.66rem;">No Tecnica</span>
                                <?php endif; ?>
                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-0.5 fw-bold" style="font-size:0.68rem;">
                                    <?= (int)$m['uc'] ?> U.C.
                                </span>
                            </div>
                        </div>

                        <h6 class="fw-bold text-dark mb-1" style="font-size:0.92rem; line-height:1.25;"><?= htmlspecialchars($m['nombre_materia']) ?></h6>

                        <!-- PRELACIONES DE LA MATERIA SI EXISTEN -->
                        <?php if (!empty($prelaciones_de_materia)): ?>
                            <div class="small mb-2 d-flex align-items-center flex-wrap gap-1" style="font-size:0.72rem;">
                                <span class="text-muted"><i class="fas fa-link me-1"></i>Prelacion:</span>
                                <?php foreach ($prelaciones_de_materia as $pre): 
                                    $pre_ok = in_array($pre['id'], $ids_materias_aprobadas);
                                ?>
                                    <span class="badge <?= $pre_ok ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> rounded-pill px-2 py-0.5" style="font-size:0.65rem;" title="<?= htmlspecialchars($pre['nombre']) ?>">
                                        <i class="fas <?= $pre_ok ? 'fa-check' : 'fa-times' ?> me-0.5"></i><?= htmlspecialchars($pre['codigo']) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- PIE DE TARJETA: ESTADO Y ACCIONES -->
                    <div class="pt-2 border-top mt-2">
                        <?php if ($esta_inscrita): ?>
                            <!-- ESTADO: YA INSCRITA -->
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-1">
                                <div>
                                    <span class="badge bg-success text-white py-1 px-2.5 rounded-pill" style="font-size:0.75rem;">
                                        <i class="fas fa-check-circle me-1"></i>Sec. <?= htmlspecialchars($inscrita_info['nombre_seccion']) ?>
                                    </span>
                                    <div class="text-muted mt-1" style="font-size:0.72rem;">
                                        <i class="fas fa-user-tie me-1"></i>Prof. <?= htmlspecialchars($inscrita_info['docente_nombre'] . ' ' . $inscrita_info['docente_apellido']) ?>
                                    </div>
                                </div>
                                <?php if ($periodoPermite): ?>
                                    <a href="index.php?action=drop_subject&seccion=<?= $inscrita_info['id_seccion'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" 
                                       class="btn btn-sm btn-outline-danger rounded-pill px-2.5 py-1 fw-bold"
                                       style="font-size:0.72rem;"
                                       onclick="return confirm('¿Confirmas que deseas retirar la asignatura ' + <?= json_encode($m['nombre_materia']) ?> + '?');">
                                        <i class="fas fa-trash-alt me-0.5"></i>Retirar
                                    </a>
                                <?php endif; ?>
                            </div>

                        <?php elseif ($esta_aprobada): ?>
                            <!-- ESTADO: APROBADA EN PERIODOS PREVIOS -->
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-primary text-white py-1 px-2.5 rounded-pill" style="font-size:0.75rem;">
                                    <i class="fas fa-check-double me-1"></i>Aprobada
                                </span>
                                <span class="text-muted" style="font-size:0.72rem;">Completada</span>
                            </div>

                        <?php elseif ($is_locked): ?>
                            <!-- ESTADO: NIVEL BLOQUEADO -->
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted" style="font-size:0.72rem;">
                                    <i class="fas fa-lock me-1"></i>Semestre previo requerido
                                </span>
                                <button class="btn btn-sm btn-light border text-muted rounded-pill px-2.5 py-1" style="font-size:0.72rem;" disabled>Bloqueada</button>
                            </div>

                        <?php elseif (!$cumple_prelaciones): ?>
                            <!-- ESTADO: PRELACIONES PENDIENTES -->
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-danger fw-semibold" style="font-size:0.72rem;">
                                    <i class="fas fa-exclamation-circle me-1"></i>Prelacion pendiente
                                </span>
                                <button class="btn btn-sm btn-light border text-danger rounded-pill px-2.5 py-1" style="font-size:0.72rem;" disabled>Pendiente</button>
                            </div>

                        <?php elseif (empty($secciones_disp)): ?>
                            <!-- ESTADO: SIN SECCION APERTURADA -->
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted" style="font-size:0.72rem;">
                                    <i class="fas fa-clock me-1"></i>En programacion
                                </span>
                                <button class="btn btn-sm btn-light border text-muted rounded-pill px-2.5 py-1" style="font-size:0.72rem;" disabled>No Aperturada</button>
                            </div>

                        <?php else: ?>
                            <!-- DISPONIBLE PARA INSCRIBIR -->
                            <?php if (count($secciones_disp) === 1): 
                                $sec = $secciones_disp[0];
                            ?>
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-1">
                                    <div>
                                        <div class="fw-bold text-primary" style="font-size:0.78rem;">
                                            <i class="fas fa-users me-1"></i>Sec. <?= htmlspecialchars($sec['nombre_seccion']) ?>
                                        </div>
                                        <div class="text-muted" style="font-size:0.74rem;">
                                            <i class="fas fa-chalkboard-teacher me-1"></i>Prof. <?= htmlspecialchars($sec['docente_nombre'] . ' ' . $sec['docente_apellido']) ?>
                                        </div>
                                    </div>

                                    <?php if (!$periodoPermite): ?>
                                        <button class="btn btn-sm btn-secondary rounded-pill px-2.5 py-1" style="font-size:0.72rem;" disabled>Cerrado</button>
                                    <?php elseif ($creditos_inscritos + (int)$m['uc'] > 30): ?>
                                        <button class="btn btn-sm btn-outline-warning rounded-pill px-2.5 py-1" style="font-size:0.72rem;" disabled title="Supera 30 UC">Limite UC</button>
                                    <?php else: ?>
                                        <a href="index.php?action=enroll_subject&seccion=<?= $sec['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" 
                                           class="btn btn-sm rounded-pill px-3 py-1.5 fw-bold text-white shadow-sm"
                                           style="background:#003366; border-color:#003366; font-size:0.78rem;"
                                           onclick="return confirm('¿Confirmas tu inscripcion en ' + <?= json_encode($m['nombre_materia']) ?> + ' (Seccion ' + <?= json_encode($sec['nombre_seccion']) ?> + ')?');">
                                            <i class="fas fa-plus-circle me-1"></i>Inscribir
                                        </a>
                                    <?php endif; ?>
                                </div>

                            <?php else: ?>
                                <!-- MULTIPLES SECCIONES -->
                                <form method="POST" action="index.php?action=enroll_subject" class="d-flex align-items-center gap-1 flex-wrap">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <select name="seccion" class="form-select form-select-sm rounded-pill flex-grow-1" style="font-size:0.74rem; padding:0.2rem 0.55rem;" required>
                                        <?php foreach ($secciones_disp as $sec): ?>
                                            <option value="<?= $sec['id'] ?>">
                                                Sec. <?= htmlspecialchars($sec['nombre_seccion']) ?> (<?= htmlspecialchars($sec['docente_nombre'] . ' ' . $sec['docente_apellido']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <?php if (!$periodoPermite): ?>
                                        <button type="button" class="btn btn-sm btn-secondary rounded-pill px-2.5 py-1" style="font-size:0.72rem;" disabled>Cerrado</button>
                                    <?php elseif ($creditos_inscritos + (int)$m['uc'] > 30): ?>
                                        <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-2.5 py-1" style="font-size:0.72rem;" disabled>Limite UC</button>
                                    <?php else: ?>
                                        <button type="submit" 
                                                class="btn btn-sm rounded-pill px-3 py-1.5 fw-bold text-white shadow-sm text-nowrap"
                                                style="background:#003366; border-color:#003366; font-size:0.78rem;"
                                                onclick="return confirm('¿Confirmas tu inscripcion en la seccion seleccionada para ' + <?= json_encode($m['nombre_materia']) ?> + '?');">
                                            <i class="fas fa-plus-circle me-1"></i>Inscribir
                                        </button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>

                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php
            };
            ?>

            <div class="tab-content" id="pills-semestres-content">
                <?php foreach ($etiquetas_semestre as $sem_num => $sem_label): 
                    $is_active = ($sem_num === $semestre_activo_default);
                    $is_locked = ($sem_num === 99) ? ($semestre_desbloqueado < 7) : ($sem_num > $semestre_desbloqueado);
                    $materias_sem = $materias_por_semestre[$sem_num] ?? [];
                ?>
                <div class="tab-pane fade <?= $is_active ? 'show active' : '' ?>" 
                     id="pane-sem-<?= $sem_num ?>" 
                     role="tabpanel" 
                     aria-labelledby="tab-sem-<?= $sem_num ?>" 
                     tabindex="0">

                    <?php if ($is_locked): ?>
                        <div class="alert alert-light border rounded-3 py-3 px-4 mb-3 d-flex align-items-center gap-3">
                            <i class="fas fa-lock fa-lg text-muted opacity-50"></i>
                            <div>
                                <h6 class="fw-bold mb-0 text-dark" style="font-size:0.90rem;"><?= ($sem_num === 99) ? 'Electivas Disponibles a partir de 7mo Semestre' : 'Nivel Posterior Bloqueado' ?></h6>
                                <span class="text-muted" style="font-size:0.80rem;"><?= ($sem_num === 99) ? 'Las asignaturas electivas tecnicas y no tecnicas se habilitan al cursar el 7mo u 8vo semestre.' : 'Para habilitar la inscripcion en este nivel academico, debes aprobar previamente las materias del semestre anterior.' ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($materias_sem)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-layer-group fa-2x mb-2 opacity-25"></i>
                            <h6 class="fw-bold text-dark" style="font-size:0.90rem;">No hay asignaturas registradas para <?= htmlspecialchars($sem_label) ?></h6>
                        </div>
                    <?php elseif ($sem_num === 99): ?>
                        <?php
                            $mats_tecnicas = array_filter($materias_sem, fn($m) => strpos($m['codigo_materia'], 'ET-') === 0);
                            $mats_no_tecnicas = array_filter($materias_sem, fn($m) => strpos($m['codigo_materia'], 'ENT-') === 0);
                            $mats_otras = array_filter($materias_sem, fn($m) => strpos($m['codigo_materia'], 'ET-') !== 0 && strpos($m['codigo_materia'], 'ENT-') !== 0);
                        ?>

                        <!-- 1. ELECTIVAS TECNICAS -->
                        <?php if (!empty($mats_tecnicas)): ?>
                            <div class="mb-4">
                                <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3 flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge rounded-pill px-2.5 py-1 fw-bold" style="background:#003366; color:#ffffff; font-size:0.75rem;">1</span>
                                        <h6 class="fw-bold mb-0 text-dark" style="font-size:0.92rem;">
                                            <i class="fas fa-microchip text-primary me-2"></i>ELECTIVAS TÉCNICAS
                                        </h6>
                                    </div>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1" style="font-size:0.72rem;">
                                        Para ser elegidas en el 7mo y 8vo Semestre
                                    </span>
                                </div>
                                <div class="row g-3">
                                    <?php foreach ($mats_tecnicas as $m) { $render_materia_card($m, $is_locked); } ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- 2. ELECTIVAS NO TECNICAS -->
                        <?php if (!empty($mats_no_tecnicas)): ?>
                            <div class="mb-3">
                                <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3 flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge rounded-pill px-2.5 py-1 fw-bold" style="background:#003366; color:#ffffff; font-size:0.75rem;">2</span>
                                        <h6 class="fw-bold mb-0 text-dark" style="font-size:0.92rem;">
                                            <i class="fas fa-briefcase text-warning-emphasis me-2"></i>ELECTIVAS NO TÉCNICAS
                                        </h6>
                                    </div>
                                    <span class="badge bg-warning-subtle text-dark border border-warning-subtle rounded-pill px-2.5 py-1" style="font-size:0.72rem;">
                                        Para ser elegidas en el 7mo y 8vo Semestre
                                    </span>
                                </div>
                                <div class="row g-3">
                                    <?php foreach ($mats_no_tecnicas as $m) { $render_materia_card($m, $is_locked); } ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($mats_otras)): ?>
                            <div class="mb-3">
                                <div class="row g-3">
                                    <?php foreach ($mats_otras as $m) { $render_materia_card($m, $is_locked); } ?>
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($materias_sem as $m) { $render_materia_card($m, $is_locked); } ?>
                        </div>
                    <?php endif; ?>

                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</div>

<script>
// esta funcion cambia el color de las pestanas de semestre para poner azul oscuro la que este seleccionada
document.addEventListener('DOMContentLoaded', function() {
    const tabButtons = document.querySelectorAll('#pills-semestres button[data-bs-toggle="pill"]');
    tabButtons.forEach(button => {
        button.addEventListener('shown.bs.tab', function(e) {
            tabButtons.forEach(btn => {
                btn.style.setProperty('background', '#f8f9fa', 'important');
                btn.style.setProperty('color', '#495057', 'important');
            });
            e.target.style.setProperty('background', '#003366', 'important');
            e.target.style.setProperty('color', '#ffffff', 'important');
        });
    });
});
</script>
