<?php
// este archivo es el panel de control del coordinador academico
// muestra el calendario de clases de lunes a sabado con las materias y profesores asignados
$rolCoord = Usuario::normalizarRol($_SESSION['rol_usuario'] ?? '');
if (!in_array($rolCoord, ['Coordinador Academico', 'Super Administrador', 'Director'], true)) {
    header('Location: index.php?page=dashboard');
    exit;
}

$theme_color = '#003366';
$theme_bg    = 'rgba(0, 51, 102, 0.05)';

$stmtFoto = $conexion->prepare("SELECT p.foto_perfil FROM usuario u JOIN perfil p ON u.id_perfil = p.id WHERE u.id = :id");
$stmtFoto->execute([':id' => $_SESSION['id_usuario']]);
$foto_perfil = $stmtFoto->fetchColumn();
$tieneFoto = !empty($foto_perfil) && $foto_perfil !== 'default.svg' && file_exists('public/uploads/profiles/' . $foto_perfil);
$urlFoto = $tieneFoto ? URLROOT . '/uploads/profiles/' . rawurlencode($foto_perfil) : '';

$totalAspirantes = (int)$conexion->query("SELECT COUNT(*) FROM usuario WHERE rol = 'Aspirante' AND estado = 'Pendiente'")->fetchColumn();
$totalSecciones  = (int)$conexion->query("SELECT COUNT(*) FROM seccion WHERE estado = 'Activa'")->fetchColumn();
$totalDocentes   = (int)$conexion->query("SELECT COUNT(*) FROM usuario WHERE rol IN ('Profesor','Docente') AND estado = 'Aprobado'")->fetchColumn();
$totalAlumnos    = (int)$conexion->query("SELECT COUNT(*) FROM usuario WHERE rol = 'Estudiante' AND estado = 'Aprobado'")->fetchColumn();

// Cargar todas las secciones y horarios activos para el Cronograma Semanal
$stmtSecciones = $conexion->query(
    "SELECT s.*, m.codigo_materia, m.nombre_materia, m.semestre, m.uc,
            CONCAT(pt.nombre, ' ', pt.apellido) AS titular_nombre,
            (SELECT COUNT(*) FROM inscripcion_seccion ins WHERE ins.id_seccion = s.id AND ins.estado = 'Inscrito') AS total_estudiantes
     FROM seccion s
     JOIN materia m ON s.id_materia = m.id
     JOIN usuario ut ON s.id_docente = ut.id
     JOIN perfil pt ON ut.id_perfil = pt.id
     WHERE s.estado = 'Activa'
     ORDER BY s.dia_semana, s.hora_inicio, s.nombre_seccion"
);
$seccionesMatriz = $stmtSecciones->fetchAll(PDO::FETCH_ASSOC);

// Materias y profesores para el modal de asignacion rapida
$stmtM = $conexion->query("SELECT id, codigo_materia, nombre_materia, semestre, uc FROM materia ORDER BY semestre IS NULL, semestre ASC, nombre_materia ASC");
$materiasLista = $stmtM ? $stmtM->fetchAll(PDO::FETCH_ASSOC) : [];

$stmtP = $conexion->query("SELECT u.id, p.nombre, p.apellido, p.cedula FROM usuario u JOIN perfil p ON u.id_perfil = p.id WHERE u.rol IN ('Profesor','Docente') AND u.estado = 'Aprobado' ORDER BY p.apellido, p.nombre");
$profesoresTitulares = $stmtP ? $stmtP->fetchAll(PDO::FETCH_ASSOC) : [];

// Informacion del ciclo academico activo
$periodoActivoCoord = PeriodoHelper::getPeriodoActivo($conexion);
$cicloCoord         = PeriodoHelper::getDetalleCiclo($periodoActivoCoord);
?>

<style>
/* Estilos profesionales compactos para el Panel de Coordinación */
.coord-dashboard-wrapper {
    max-width: 1420px;
    margin: 0 auto;
    width: 100%;
}

.coord-welcome-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-left: 4px solid #003366 !important;
    border-radius: 12px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.03);
    padding: 0.65rem 1.15rem;
}

.coord-kpi-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.02);
    padding: 0.55rem 0.85rem;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.coord-kpi-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
}

.coord-kpi-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    flex-shrink: 0;
}

.coord-kpi-blue   { background: #eff6ff; color: #1d4ed8; }
.coord-kpi-indigo { background: #f5f3ff; color: #6d28d9; }
.coord-kpi-green  { background: #f0fdf4; color: #15803d; }
.coord-kpi-orange { background: #fff7ed; color: #c2410c; }
.coord-kpi-cyan   { background: #ecfeff; color: #0e7490; }

.coord-kpi-label {
    font-size: 0.62rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    color: #64748b;
    margin-bottom: 1px;
    display: block;
}

.coord-kpi-val {
    font-size: 1.25rem;
    font-weight: 800;
    line-height: 1.1;
    color: #0f172a;
}

.coord-kpi-hint {
    font-size: 0.65rem;
    color: #94a3b8;
    display: block;
}

.coord-gateway-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 0.65rem 0.9rem;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.02);
    transition: all 0.15s ease;
}

.coord-gateway-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    border-color: #cbd5e1;
}

.coord-gateway-arrow {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-size: 0.68rem;
    transition: all 0.15s ease;
}

.coord-gateway-card:hover .coord-gateway-arrow {
    background: #003366;
    color: #ffffff;
    border-color: #003366;
}
</style>

<div class="coord-dashboard-wrapper px-2.5 py-2">
    <!-- Mensajes de Estado Flash -->
    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-xs mb-2 py-1.5 px-3 small border-0" style="background:#e8f5e9;color:#2e7d32;">
            <i class="fas fa-check-circle me-1.5"></i><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-xs mb-2 py-1.5 px-3 small border-0" style="background:#ffebee;color:#c62828;">
            <i class="fas fa-exclamation-circle me-1.5"></i><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Encabezado Unificado y Compacto: Identidad de Coordinacion + Ciclo Academico Activo -->
    <div class="coord-welcome-card mb-2 py-2 px-3">
        <div class="d-flex flex-column flex-lg-row align-items-center justify-content-between gap-2.5">
            <!-- Identidad + Estado y Semana del Ciclo -->
            <div class="d-flex align-items-center gap-2.5 min-w-0">
                <div class="rounded-circle overflow-hidden d-flex align-items-center justify-content-center flex-shrink-0 border shadow-xs" 
                    style="width: 38px; height: 38px; border: 2px solid #003366 !important; background: #f0f7ff;">
                    <?php if ($tieneFoto): ?>
                        <img src="<?= $urlFoto ?>" alt="Foto" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                        <i class="fas fa-user-shield" style="font-size:1.05rem;color:#003366;"></i>
                    <?php endif; ?>
                </div>
                <div class="min-w-0">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-0.5">
                        <span class="fw-bold text-dark text-truncate" style="font-size:0.92rem;">
                            <?= htmlspecialchars($_SESSION['nombre_usuario']) ?>
                        </span>
                        <span class="text-muted">·</span>
                        <span class="fw-bold text-dark" style="font-size:0.80rem;">Ciclo <?= htmlspecialchars($cicloCoord['nombre']) ?></span>
                        <?php if ($cicloCoord['es_vigente_hoy']): ?>
                            <span class="badge rounded-pill fw-bold" style="background:#dcfce7; color:#166534; font-size:0.68rem;">
                                Semana <?= $cicloCoord['semana_actual'] ?>/<?= $cicloCoord['total_semanas'] ?>
                            </span>
                        <?php elseif ($cicloCoord['fase'] === 'por_iniciar'): ?>
                            <span class="badge rounded-pill fw-bold" style="background:#fef3c7; color:#92400e; font-size:0.68rem;">
                                Inicia <?= $cicloCoord['fecha_inicio_txt'] ?>
                            </span>
                        <?php elseif ($cicloCoord['fase'] === 'finalizado'): ?>
                            <span class="badge rounded-pill fw-bold" style="background:#fee2e2; color:#991b1b; font-size:0.68rem;">
                                Finalizado
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="text-muted small d-flex flex-wrap align-items-center gap-2" style="font-size:0.72rem;">
                        <?php if ($cicloCoord['existe']): ?>
                            <span><i class="far fa-calendar-alt text-primary me-1"></i><?= $cicloCoord['fecha_inicio_txt'] ?> al <?= $cicloCoord['fecha_fin_txt'] ?></span>
                            <span>·</span>
                            <span>Avance: <?= $cicloCoord['progreso'] ?>%</span>
                        <?php else: ?>
                            <span>Sin ciclo activo. Configure uno para habilitar asistencias.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Acciones Rápidas Unificadas -->
            <div class="d-flex flex-wrap gap-1.5 align-items-center justify-content-center justify-content-lg-end flex-shrink-0">
                <button type="button" class="btn btn-sm rounded-pill px-2.5 py-1 fw-bold shadow-xs text-white" 
                        style="background:#003366; font-size:0.74rem;" 
                        data-bs-toggle="modal" data-bs-target="#modalPersonalizarCiclo" title="Personalizar fechas y semanas del ciclo">
                    <i class="fas fa-calendar-check me-1"></i>Ciclo Académico
                </button>
                <button type="button" class="btn btn-outline-primary rounded-pill px-2.5 py-1 fw-semibold btn-sm shadow-xs" 
                        data-bs-toggle="modal" data-bs-target="#modalEstadisticasCoordinacion" style="font-size:0.74rem;">
                    <i class="fas fa-chart-pie me-1"></i>Estadísticas
                </button>
                <a href="index.php?page=asistencias" class="btn btn-outline-dark rounded-pill px-2.5 py-1 fw-semibold btn-sm shadow-xs" style="font-size:0.74rem;">
                    <i class="fas fa-clipboard-check me-1"></i>Asistencias
                </a>
                <a href="index.php?page=gestion_periodos" class="btn btn-outline-secondary rounded-pill px-2.5 py-1 fw-semibold btn-sm shadow-xs" style="font-size:0.74rem;">
                    <i class="fas fa-history me-1"></i>Historial
                </a>
                <a href="index.php?page=mi_perfil" class="btn btn-outline-secondary rounded-pill px-2.5 py-1 fw-semibold btn-sm shadow-xs" style="font-size:0.74rem;">
                    <i class="fas fa-user me-1"></i>Perfil
                </a>
            </div>
        </div>
    </div>

    <!-- Accesos Rápidos Compactos a Módulos de Control de Estudios -->
    <div class="row g-2 mb-2">
        <div class="col-12 col-md-4">
            <a href="index.php?page=gestion_docentes" class="text-decoration-none">
                <div class="coord-gateway-card h-100 py-2 px-2.5" style="border-left: 3px solid #5c6bc0 !important;">
                    <div class="d-flex align-items-center gap-2">
                        <div class="coord-kpi-icon coord-kpi-indigo" style="width:30px;height:30px;font-size:0.8rem;">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <h6 class="fw-bold mb-0 text-dark" style="font-size:0.82rem;">Docentes y Materias</h6>
                            <small class="text-muted text-truncate d-block" style="font-size:0.68rem;">Gestión de asignaturas</small>
                        </div>
                        <div class="coord-gateway-arrow flex-shrink-0" style="width:24px;height:24px;font-size:0.6rem;">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-12 col-md-4">
            <a href="index.php?page=carnetizacion" class="text-decoration-none">
                <div class="coord-gateway-card h-100 py-2 px-2.5" style="border-left: 3px solid #0284c7 !important;">
                    <div class="d-flex align-items-center gap-2">
                        <div class="coord-kpi-icon coord-kpi-cyan" style="width:30px;height:30px;font-size:0.8rem;">
                            <i class="fas fa-camera"></i>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <h6 class="fw-bold mb-0 text-dark" style="font-size:0.82rem;">Carnetización</h6>
                            <small class="text-muted text-truncate d-block" style="font-size:0.68rem;">Apertura Nivel 2 oficial</small>
                        </div>
                        <div class="coord-gateway-arrow flex-shrink-0" style="width:24px;height:24px;font-size:0.6rem;">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-12 col-md-4">
            <a href="index.php?page=approve_registration" class="text-decoration-none">
                <div class="coord-gateway-card h-100 py-2 px-2.5" style="border-left: 3px solid #ea580c !important;">
                    <div class="d-flex align-items-center gap-2">
                        <div class="coord-kpi-icon coord-kpi-orange" style="width:30px;height:30px;font-size:0.8rem;">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <h6 class="fw-bold mb-0 text-dark" style="font-size:0.82rem;">Aspirantes</h6>
                            <small class="text-muted text-truncate d-block" style="font-size:0.68rem;"><?= $totalAspirantes ?> recaudos pendientes</small>
                        </div>
                        <div class="coord-gateway-arrow flex-shrink-0" style="width:24px;height:24px;font-size:0.6rem;">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- CRONOGRAMA SEMANAL OFICIAL: MATRIZ DE HORARIOS (LUNES A SABADO) -->
    <!-- ============================================================ -->
    <?php
    $esEditable   = true;
    $rolVista     = 'Coordinador';
    $tituloMatriz = 'Cronograma Semanal de Clases (Matriz Oficial UNEFA)';
    require 'app/views/coordinator/partials/horario_matrix.php';
    ?>
</div>

<!-- Modal de Estadísticas del Sistema (Coordinación) -->
<div class="modal fade" id="modalEstadisticasCoordinacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header text-white border-0 py-3 px-4" style="background:#003366;">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white" style="width:34px;height:34px;background:rgba(255,255,255,0.15);">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold mb-0 text-white" style="font-size:1.05rem;">Estadísticas del Sistema</h6>
                        <small class="text-white-50" style="font-size:0.75rem;">Resumen de Control de Estudios e Inscripción</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="row g-3">
                    <div class="col-12 col-sm-6">
                        <div class="card border rounded-4 p-3 bg-white h-100 shadow-xs">
                            <div class="d-flex align-items-center gap-3">
                                <div class="coord-kpi-icon coord-kpi-blue flex-shrink-0" style="width:48px;height:48px;font-size:1.2rem;border-radius:12px;">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div>
                                    <span class="coord-kpi-label">Horarios Activos</span>
                                    <div class="coord-kpi-val text-primary" style="font-size:1.6rem;"><?= $totalSecciones ?></div>
                                    <span class="coord-kpi-hint">Secciones programadas</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6">
                        <div class="card border rounded-4 p-3 bg-white h-100 shadow-xs">
                            <div class="d-flex align-items-center gap-3">
                                <div class="coord-kpi-icon coord-kpi-indigo flex-shrink-0" style="width:48px;height:48px;font-size:1.2rem;border-radius:12px;">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                </div>
                                <div>
                                    <span class="coord-kpi-label">Profesores</span>
                                    <div class="coord-kpi-val text-dark" style="font-size:1.6rem;"><?= $totalDocentes ?></div>
                                    <span class="coord-kpi-hint">Docentes asignados</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6">
                        <div class="card border rounded-4 p-3 bg-white h-100 shadow-xs">
                            <div class="d-flex align-items-center gap-3">
                                <div class="coord-kpi-icon coord-kpi-green flex-shrink-0" style="width:48px;height:48px;font-size:1.2rem;border-radius:12px;">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                                <div>
                                    <span class="coord-kpi-label">Estudiantes</span>
                                    <div class="coord-kpi-val text-success" style="font-size:1.6rem;"><?= $totalAlumnos ?></div>
                                    <span class="coord-kpi-hint">Matriculados activos</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6">
                        <a href="index.php?page=approve_registration" class="text-decoration-none">
                            <div class="card border rounded-4 p-3 bg-white h-100 shadow-xs coord-gateway-card">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="coord-kpi-icon coord-kpi-orange flex-shrink-0" style="width:48px;height:48px;font-size:1.2rem;border-radius:12px;">
                                        <i class="fas fa-user-check"></i>
                                    </div>
                                    <div class="flex-grow-1 min-w-0">
                                        <span class="coord-kpi-label">Aspirantes</span>
                                        <div class="coord-kpi-val <?= $totalAspirantes > 0 ? 'text-warning' : 'text-dark' ?>" style="font-size:1.6rem;"><?= $totalAspirantes ?></div>
                                        <span class="coord-kpi-hint"><?= $totalAspirantes > 0 ? 'Por validar · Clic para revisar' : 'Al día' ?></span>
                                    </div>
                                    <div class="coord-gateway-arrow flex-shrink-0">
                                        <i class="fas fa-chevron-right"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white py-2 px-3 border-top d-flex justify-content-end">
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 fw-bold" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Unificado para Crear y Modificar Horario -->
<?php require 'app/views/coordinator/partials/modal_horario_editor.php'; ?>

<!-- Modal Exclusivo de Personalizacion de Ciclo Academico -->
<?php PeriodoHelper::renderModalPersonalizarCiclo($conexion, 'index.php?page=dashboard'); ?>
