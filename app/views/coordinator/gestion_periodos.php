<?php
// este archivo es la pantalla para organizar los periodos y semestres del ano
// aqui coordinacion crea un nuevo lapso, define cuantas semanas dura y decide cual periodo esta activo
if (!isset($_SESSION['rol_usuario']) || !in_array($_SESSION['rol_usuario'], ['Coordinador Academico', 'Coordinador', 'Super Administrador', 'Director'])) {
    header('Location: index.php?page=login');
    exit;
}

$hoy = date('Y-m-d');

// revisamos si el periodo actual ya llego a su fecha de vencimiento
$periodoVencido = $conexion->query("SELECT id, nombre FROM periodo_academico WHERE estado = 'Activo' AND fecha_fin IS NOT NULL AND fecha_fin < CURDATE() LIMIT 1")->fetch(PDO::FETCH_ASSOC);

// buscamos el periodo que esta activo actualmente
$stmt = $conexion->query("SELECT * FROM periodo_academico WHERE estado = 'Activo' LIMIT 1");
$periodoActivo = $stmt->fetch(PDO::FETCH_ASSOC);

// traemos la lista completa de todos los periodos ordenados por estado
$periodos = $conexion->query("
    SELECT * FROM periodo_academico
    ORDER BY FIELD(estado, 'Activo', 'Planificado', 'Finalizado'), fecha_inicio DESC
")->fetchAll(PDO::FETCH_ASSOC);

$stats = [];
$detalles = [];
if (!empty($periodos)) {
    $ids = array_column($periodos, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmtStats = $conexion->prepare("
        SELECT id_periodo,
            COUNT(*) as total_inscripciones,
            0 as pendientes,
            COUNT(*) as ciclos_cerrados,
            COUNT(*) as ciclos_validados
        FROM inscripcion_seccion
        WHERE id_periodo IN ($placeholders)
        GROUP BY id_periodo
    ");
    $stmtStats->execute($ids);
    foreach ($stmtStats->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $stats[$s['id_periodo']] = $s;
    }

    $stmtDet = $conexion->prepare("
        SELECT ins.id_periodo,
            CONCAT(p.nombre, ' ', p.apellido) AS docente,
            m.nombre_materia, m.semestre, s.id AS seccion_id,
            COUNT(ins.id) AS total_estudiantes,
            COUNT(ins.id) AS cerrados,
            COUNT(ins.id) AS validados
        FROM inscripcion_seccion ins
        JOIN seccion s ON ins.id_seccion = s.id
        JOIN materia m ON s.id_materia = m.id
        JOIN usuario u ON s.id_docente = u.id
        JOIN perfil p ON u.id_perfil = p.id
        WHERE ins.id_periodo IN ($placeholders)
        GROUP BY ins.id_periodo, s.id, m.id
        ORDER BY m.semestre, m.nombre_materia
    ");
    $stmtDet->execute($ids);
    foreach ($stmtDet->fetchAll(PDO::FETCH_ASSOC) as $d) {
        $detalles[$d['id_periodo']][] = $d;
    }
}
?>
<div class="container py-5 mt-5">
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h1 class="display-6 fw-bold mb-1" style="color:var(--coord-primary);"><i class="fas fa-calendar-alt me-3"></i>Periodos academicos</h1>
            <p class="text-muted mb-0">Gestione los periodos academicos, personalice su duracion semana a semana y controle las fechas lectivas.</p>
        </div>
        <div class="col-auto d-flex gap-2">
            <a href="index.php?page=cronograma" class="btn btn-sm rounded-pill px-3 fw-bold" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;">
                <i class="fas fa-calendar-alt me-2"></i>Cronograma
            </a>
            <a href="index.php?page=dashboard" class="btn btn-sm rounded-pill px-3 fw-bold" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;">
                <i class="fas fa-arrow-left me-2"></i>Volver al Panel
            </a>
        </div>
    </div>

    <?php if ($periodoVencido): ?>
    <div class="alert alert-warning alert-dismissible fade show rounded-4 shadow-sm mb-4 border-0" role="alert" style="background:#fffbeb;color:#92400e;border-left:5px solid #f59e0b!important;">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Periodo vencido:</strong> «<?= htmlspecialchars($periodoVencido['nombre']) ?>» tiene fecha de fin anterior a hoy. Finalicelo o actualice la fecha.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($periodoActivo): 
        $cicloDetalle = PeriodoHelper::getDetalleCiclo($periodoActivo); 
    ?>
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="border-left: 5px solid <?= $periodoVencido ? '#f59e0b' : '#003366' ?> !important;">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:54px;height:54px;background:#dcfce7;color:#166534;">
                        <i class="fas fa-play-circle fa-2x"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-success rounded-pill px-3 py-1.5 fw-bold">Periodo Activo</span>
                            <?php if ($cicloDetalle['es_vigente_hoy']): ?>
                                <span class="badge rounded-pill px-3 py-1.5 fw-bold" style="background:#e0f2fe;color:#0369a1;">
                                    <i class="fas fa-calendar-week me-1"></i>Semana <?= $cicloDetalle['semana_actual'] ?> de <?= $cicloDetalle['total_semanas'] ?> (En Curso)
                                </span>
                            <?php elseif ($periodoVencido): ?>
                                <span class="badge bg-warning text-dark rounded-pill px-3 py-1.5 fw-bold"><i class="fas fa-clock me-1"></i>Vencido</span>
                            <?php endif; ?>
                        </div>
                        <h3 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($periodoActivo['nombre']) ?></h3>
                        <small class="text-muted">
                            Inicio: <strong><?= $cicloDetalle['fecha_inicio_txt'] ?></strong> · 
                            Fin: <strong><?= $cicloDetalle['fecha_fin_txt'] ?></strong> · 
                            Duracion: <strong><?= $cicloDetalle['total_semanas'] ?> Semanas</strong>
                        </small>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" style="background:#003366;border-color:#003366;" data-bs-toggle="modal" data-bs-target="#modalEditarPeriodo_<?= $periodoActivo['id'] ?>">
                        <i class="fas fa-sliders-h me-1.5"></i>Personalizar Ciclo y Fechas
                    </button>
                    <a href="index.php?action=finalize_periodo&id=<?= $periodoActivo['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                    class="btn btn-outline-warning rounded-pill px-4 fw-bold"
                    onclick="event.preventDefault(); (async ()=>{ if(await confirmar('¿Finalizar el periodo <?= htmlspecialchars($periodoActivo['nombre']) ?>? Se cerraran todas las inscripciones activas.')) window.location.href=this.href; })()">
                        <i class="fas fa-flag-checkered me-2"></i>Finalizar Periodo
                    </a>
                </div>
            </div>

            <!-- Progreso y Progresion Semanal -->
            <div class="p-3 rounded-3 mb-3" style="background:#f8fafc;border:1px solid #e2e8f0;">
                <div class="d-flex justify-content-between align-items-center mb-1 text-muted small">
                    <span class="fw-bold text-dark"><i class="fas fa-stream text-primary me-1"></i>Progresion Oficial del Ciclo Academico por Semanas</span>
                    <span class="fw-bold"><?= $cicloDetalle['texto_estado'] ?> (<?= $cicloDetalle['progreso'] ?>%)</span>
                </div>
                <div class="progress rounded-pill" style="height: 8px;">
                    <div class="progress-bar rounded-pill" style="width: <?= $cicloDetalle['progreso'] ?>%; background: linear-gradient(90deg, #003366, #0284c7);"></div>
                </div>
            </div>

            <!-- Desglose Semana a Semana Desplegable -->
            <div class="mt-2">
                <button class="btn btn-sm btn-link text-decoration-none fw-bold p-0 text-primary" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSemanasCiclo">
                    <i class="fas fa-list-ol me-1"></i>Ver Cronograma Desglosado Semana a Semana (<?= count($cicloDetalle['lista_semanas']) ?> semanas) <i class="fas fa-chevron-down ms-1"></i>
                </button>
                <div class="collapse mt-3" id="collapseSemanasCiclo">
                    <div class="row g-2">
                        <?php foreach ($cicloDetalle['lista_semanas'] as $sem): ?>
                            <div class="col-xl-3 col-lg-4 col-md-6 col-12">
                                <div class="p-2.5 rounded-3 border d-flex align-items-center justify-content-between" style="background:<?= $sem['es_actual'] ? '#e0f2fe' : ($sem['es_pasada'] ? '#f8fafc' : '#ffffff') ?>; border-color:<?= $sem['es_actual'] ? '#0284c7' : '#e2e8f0' ?> !important;">
                                    <div>
                                        <div class="fw-bold small d-flex align-items-center gap-1.5" style="color:<?= $sem['es_actual'] ? '#0369a1' : '#1e293b' ?>;">
                                            <span><?= $sem['nombre'] ?></span>
                                            <?php if ($sem['es_actual']): ?>
                                                <span class="badge rounded-pill bg-primary" style="font-size:0.62rem;">Actual</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-muted" style="font-size:0.75rem;">
                                            <?= $sem['inicio_txt'] ?> al <?= $sem['fin_txt'] ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <?php if ($sem['es_actual']): ?>
                                            <i class="fas fa-play text-primary"></i>
                                        <?php elseif ($sem['es_pasada']): ?>
                                            <i class="fas fa-check text-success" title="Semana transcurrida"></i>
                                        <?php else: ?>
                                            <i class="fas fa-clock text-muted opacity-50" title="Semana programada"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="border-left: 5px solid #f59e0b !important;">
        <div class="card-body p-4 d-flex align-items-center gap-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:50px;height:50px;background:#fef3c7;">
                <i class="fas fa-pause-circle fa-xl" style="color:#d97706;"></i>
            </div>
            <div>
                <span class="badge bg-warning rounded-pill px-3 py-2 mb-1">Sin Periodo Activo</span>
                <p class="mb-0 text-muted">No hay ningun periodo academico activo. Cree uno nuevo o inicie un periodo planificado para habilitar las asistencias.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header border-0 p-4" style="background:var(--coord-primary);color:#fff;">
            <h5 class="fw-bold mb-0"><i class="fas fa-plus-circle me-2"></i>Crear Nuevo Periodo</h5>
        </div>
        <div class="card-body p-4">
            <form method="POST" action="index.php?action=save_ciclo_config" class="row g-3 align-items-end">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="return_url" value="index.php?page=gestion_periodos">
                <div class="col-12 col-md-3">
                    <label class="form-label fw-bold small text-muted">Nombre del Periodo</label>
                    <input type="text" name="nombre" class="form-control" placeholder="Ej: 2026-I" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">Fecha de Inicio</label>
                    <input type="date" name="fecha_inicio" id="nuevo_fi" class="form-control" required onchange="calcNuevoPeriodo()">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">Fecha de Fin (opcional)</label>
                    <input type="date" name="fecha_fin" id="nuevo_ff" class="form-control" onchange="calcNuevoPeriodo()">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small text-muted">Semanas</label>
                    <input type="number" name="semanas_duracion" id="nuevo_sem" class="form-control" value="16" min="1" max="52" required onchange="ajustarNuevoFF()">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn w-100 rounded-pill fw-bold shadow-sm" style="background:var(--coord-primary);color:#fff;" title="Guardar Periodo">
                        <i class="fas fa-save"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-0 p-4 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0"><i class="fas fa-list me-2 text-muted"></i>Todos los Periodos</h5>
            <span class="badge bg-dark rounded-pill px-3"><?= count($periodos) ?> registrados</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr class="text-muted small fw-bold">
                        <th class="ps-4 py-4">PERIODO</th>
                        <th>INICIO</th>
                        <th>FIN</th>
                        <th>ESTADO</th>
                        <th class="text-center">INSCRIPCIONES</th>
                        <th class="text-center">CICLOS</th>
                        <th class="text-end pe-4">ACCION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($periodos)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3 opacity-25"></i>
                                <p class="text-muted fw-bold">No hay periodos academicos registrados. Cree uno nuevo arriba.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($periodos as $p):
                            $s = $stats[$p['id']] ?? null;
                            $total_insc = $s ? (int)$s['total_inscripciones'] : 0;
                            $pendientes = $s ? (int)$s['pendientes'] : 0;
                            $cerrados = $s ? (int)$s['ciclos_cerrados'] : 0;
                            $validados = $s ? (int)$s['ciclos_validados'] : 0;
                        ?>
                            <tr class="<?= $p['estado'] === 'Activo' ? 'table-success' : '' ?>">
                                <td class="ps-4 py-4">
                                    <div class="fw-bold h6 mb-0"><?= htmlspecialchars($p['nombre']) ?></div>
                                    <small class="text-muted">Creado: <?= date('d/m/Y', strtotime($p['created_at'])) ?></small>
                                </td>
                                <td><?= date('d/m/Y', strtotime($p['fecha_inicio'])) ?></td>
                                <td><?= $p['fecha_fin'] ? date('d/m/Y', strtotime($p['fecha_fin'])) : '<span class="text-muted">—</span>' ?></td>
                                <td>
                                    <?php if ($p['estado'] === 'Activo'): ?>
                                        <span class="badge bg-success rounded-pill px-3 py-2"><i class="fas fa-play me-1"></i>Activo</span>
                                        <?php if ($p['fecha_fin'] && $p['fecha_fin'] < $hoy): ?>
                                            <span class="badge bg-warning text-dark rounded-pill px-3 py-2 ms-1"><i class="fas fa-clock me-1"></i>Vencido</span>
                                        <?php endif; ?>
                                    <?php elseif ($p['estado'] === 'Planificado'): ?>
                                        <span class="badge bg-info text-dark rounded-pill px-3 py-2"><i class="fas fa-clock me-1"></i>Planificado</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary rounded-pill px-3 py-2"><i class="fas fa-flag-checkered me-1"></i>Finalizado</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold"><?= $total_insc ?></span>
                                    <?php if ($pendientes > 0): ?>
                                        <span class="badge bg-warning ms-1"><?= $pendientes ?> pend.</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php $det = $detalles[$p['id']] ?? []; ?>
                                    <?php if (empty($det)): ?>
                                        <span class="text-muted small">—</span>
                                    <?php else:
                                        $total_cerrados = array_sum(array_column($det, 'cerrados'));
                                        $total_validados = array_sum(array_column($det, 'validados'));
                                        $total_est = array_sum(array_column($det, 'total_estudiantes'));
                                        $pct = $total_est > 0 ? round($total_cerrados / $total_est * 100) : 0;
                                        $color = $total_cerrados === $total_est ? 'success' : ($total_cerrados > 0 ? 'warning' : 'danger');
                                    ?>
                                        <a href="#" class="text-decoration-none" data-bs-toggle="modal" data-bs-target="#modalCiclos_<?= $p['id'] ?>">
                                            <span class="badge bg-<?= $color ?> rounded-pill px-3 py-2"><?= $pct ?>%</span>
                                        </a>
                                        <small class="d-block text-muted"><?= $total_cerrados ?>/<?= $total_est ?> cerrados</small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2.5 py-1 fw-bold me-1" 
                                            data-bs-toggle="modal" data-bs-target="#modalEditarPeriodo_<?= $p['id'] ?>" title="Editar fechas y semanas">
                                        <i class="fas fa-edit me-1"></i>Editar
                                    </button>
                                    <?php if ($p['estado'] === 'Planificado'): ?>
                                        <a href="index.php?action=activate_periodo&id=<?= $p['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                        class="btn btn-sm btn-success rounded-pill px-3 fw-bold me-1"
                                        onclick="event.preventDefault(); (async ()=>{ if(await confirmar('¿Iniciar el periodo <?= htmlspecialchars($p['nombre']) ?>? Se desactivara cualquier otro periodo activo.')) window.location.href=this.href; })()">
                                            <i class="fas fa-play me-1"></i>Iniciar
                                        </a>
                                        <a href="index.php?action=delete_periodo&id=<?= $p['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                        class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-bold"
                                        onclick="event.preventDefault(); (async ()=>{ if(await confirmar('¿Eliminar el periodo <?= htmlspecialchars($p['nombre']) ?>? Esta accion no se puede deshacer.')) window.location.href=this.href; })()">
                                            <i class="fas fa-trash me-1"></i>
                                        </a>
                                    <?php elseif ($p['estado'] === 'Activo'): ?>
                                        <span class="text-success fw-bold small"><i class="fas fa-check-circle me-1"></i>En curso</span>
                                    <?php else: ?>
                                        <span class="text-muted small fw-bold"><i class="fas fa-check me-1"></i>Finalizado</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php foreach ($periodos as $p): $det = $detalles[$p['id']] ?? []; if (!empty($det)): ?>
<!-- Modal de Cierre de Ciclos por Materia -->
<div class="modal fade" id="modalCiclos_<?= $p['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 p-4" style="background:var(--coord-primary);color:#fff;">
                <h5 class="modal-title fw-bold"><i class="fas fa-sync-alt me-2"></i>Ciclos — <?= htmlspecialchars($p['nombre']) ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr class="text-muted small fw-bold">
                                <th class="py-3">DOCENTE</th>
                                <th>MATERIA</th>
                                <th class="text-center">SEM.</th>
                                <th class="text-center">ESTUDIANTES</th>
                                <th class="text-center">CERRADOS</th>
                                <th class="text-center">VALIDADOS</th>
                                <th class="text-center">ESTADO</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($det as $d):
                                $completado = $d['cerrados'] == $d['total_estudiantes'];
                                $avance = $d['total_estudiantes'] > 0 ? round($d['cerrados'] / $d['total_estudiantes'] * 100) : 0;
                                $estado_cerrado = $completado ? 'Completado' : ($d['cerrados'] > 0 ? 'En progreso' : 'Sin cerrar');
                                $badge_color = $completado ? 'success' : ($d['cerrados'] > 0 ? 'warning text-dark' : 'danger');
                            ?>
                            <tr>
                                <td><span class="fw-medium small"><?= htmlspecialchars($d['docente']) ?></span></td>
                                <td><span class="fw-bold small"><?= htmlspecialchars($d['nombre_materia']) ?></span></td>
                                <td class="text-center"><?= (int)$d['semestre'] ?></td>
                                <td class="text-center fw-bold"><?= (int)$d['total_estudiantes'] ?></td>
                                <td class="text-center"><span class="badge bg-<?= $completado ? 'success' : 'secondary' ?> rounded-pill px-3"><?= (int)$d['cerrados'] ?></span></td>
                                <td class="text-center"><span class="badge bg-<?= $d['validados'] > 0 ? 'success' : 'secondary' ?> rounded-pill px-3"><?= (int)$d['validados'] ?></span></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $badge_color ?> rounded-pill px-3"><?= $estado_cerrado ?> (<?= $avance ?>%)</span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<?php endif; endforeach; ?>

<?php foreach ($periodos as $p): 
    $semP = max(1, (int)($p['semanas_duracion'] ?? 16));
?>
<!-- Modal de Edicion / Personalizacion de Periodo Academico -->
<div class="modal fade" id="modalEditarPeriodo_<?= $p['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header text-white border-0 py-3 px-4" style="background:#003366;">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white" style="width:34px;height:34px;background:rgba(255,255,255,0.15);">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold mb-0 text-white" style="font-size:1.05rem;">Personalizar <?= htmlspecialchars($p['nombre']) ?></h6>
                        <small class="text-white-50" style="font-size:0.75rem;">Ajuste de fechas oficiales y conteo de semanas</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?action=save_ciclo_config">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="id_periodo" value="<?= $p['id'] ?>">
                <input type="hidden" name="return_url" value="index.php?page=gestion_periodos">

                <div class="modal-body p-4 bg-light">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark mb-1">Nombre o Codigo del Periodo</label>
                        <input type="text" name="nombre" class="form-control form-control-sm rounded-3 shadow-none" value="<?= htmlspecialchars($p['nombre']) ?>" required maxlength="50">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small text-dark mb-1">Fecha de Inicio</label>
                            <input type="date" name="fecha_inicio" id="edit_fi_<?= $p['id'] ?>" class="form-control form-control-sm rounded-3 shadow-none" value="<?= htmlspecialchars($p['fecha_inicio']) ?>" required onchange="calcEditSem(<?= $p['id'] ?>)">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-dark mb-1">Fecha de Fin</label>
                            <input type="date" name="fecha_fin" id="edit_ff_<?= $p['id'] ?>" class="form-control form-control-sm rounded-3 shadow-none" value="<?= htmlspecialchars($p['fecha_fin'] ?? '') ?>" required onchange="calcEditSem(<?= $p['id'] ?>)">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark mb-1">Duracion en Semanas</label>
                        <div class="input-group input-group-sm">
                            <input type="number" name="semanas_duracion" id="edit_sem_<?= $p['id'] ?>" class="form-control rounded-start-3 shadow-none fw-bold" value="<?= $semP ?>" min="1" max="52" required onchange="ajustarEditFF(<?= $p['id'] ?>)">
                            <span class="input-group-text bg-white">semanas</span>
                        </div>
                    </div>

                    <div class="mb-2">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="hacer_activo" id="checkActivo_<?= $p['id'] ?>" value="1" <?= $p['estado'] === 'Activo' ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold small text-dark" for="checkActivo_<?= $p['id'] ?>">
                                Periodo Academico Activo en el Campus
                            </label>
                        </div>
                        <small class="text-muted" style="font-size:0.72rem;">
                            Regira de inmediato la toma de asistencias y las semanas lectivas.
                        </small>
                    </div>
                </div>

                <div class="modal-footer border-0 p-3 bg-white justify-content-between">
                    <button type="button" class="btn btn-sm btn-light rounded-pill px-3 fw-bold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm rounded-pill px-4 fw-bold text-white shadow-xs" style="background:#003366;">
                        <i class="fas fa-check-circle me-1"></i>Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
// calcula automaticamente cuantas semanas de clase hay entre la fecha de inicio y la fecha de fin
function calcNuevoPeriodo() {
    const fi = document.getElementById('nuevo_fi').value;
    const ff = document.getElementById('nuevo_ff').value;
    if (!fi || !ff) return;
    const diff = Math.floor((new Date(ff) - new Date(fi)) / (1000 * 60 * 60 * 24)) + 1;
    if (diff > 0) {
        document.getElementById('nuevo_sem').value = Math.max(1, Math.ceil(diff / 7));
    }
}

// calcula la fecha de fin sumando las semanas elegidas (por ejemplo 16 semanas) a partir de la fecha de inicio
function ajustarNuevoFF() {
    const fi = document.getElementById('nuevo_fi').value;
    const sem = parseInt(document.getElementById('nuevo_sem').value) || 16;
    if (!fi || sem <= 0) return;
    const d = new Date(fi);
    d.setDate(d.getDate() + (sem * 7) - 1);
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    document.getElementById('nuevo_ff').value = `${yyyy}-${mm}-${dd}`;
}

// hace el mismo calculo de semanas cuando se esta editando un periodo que ya existia
function calcEditSem(id) {
    const fi = document.getElementById('edit_fi_' + id).value;
    const ff = document.getElementById('edit_ff_' + id).value;
    if (!fi || !ff) return;
    const diff = Math.floor((new Date(ff) - new Date(fi)) / (1000 * 60 * 60 * 24)) + 1;
    if (diff > 0) {
        document.getElementById('edit_sem_' + id).value = Math.max(1, Math.ceil(diff / 7));
    }
}

// ajusta la fecha de fin al editar un periodo existente
function ajustarEditFF(id) {
    const fi = document.getElementById('edit_fi_' + id).value;
    const sem = parseInt(document.getElementById('edit_sem_' + id).value) || 16;
    if (!fi || sem <= 0) return;
    const d = new Date(fi);
    d.setDate(d.getDate() + (sem * 7) - 1);
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    document.getElementById('edit_ff_' + id).value = `${yyyy}-${mm}-${dd}`;
}
</script>
