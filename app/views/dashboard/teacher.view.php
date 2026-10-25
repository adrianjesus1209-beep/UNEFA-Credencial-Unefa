<?php
// este archivo es el panel de control del profesor
// le muestra su horario de clases semanal y le da acceso a pasar asistencia con qr o a mano
$rolDocente = Usuario::normalizarRol($_SESSION['rol_usuario'] ?? '');
if (!in_array($rolDocente, ['Profesor', 'Coordinador Academico', 'Director', 'Super Administrador'], true)) {
    header('Location: index.php?page=dashboard');
    exit;
}

$id_usuario = (int)$_SESSION['id_usuario'];
$nombre = $_SESSION['nombre_usuario'] ?? 'Docente';

// Obtener el horario registrado por el Coordinador Academico para este Profesor
if (in_array($rolDocente, ['Coordinador Academico', 'Director', 'Super Administrador'], true)) {
    $stmtHorario = $conexion->query(
        "SELECT s.*, m.codigo_materia, m.nombre_materia,
                CONCAT(pt.nombre, ' ', pt.apellido) AS titular_nombre,
                (SELECT COUNT(*) FROM inscripcion_seccion ins WHERE ins.id_seccion = s.id AND ins.estado = 'Inscrito') AS total_inscritos,
                (SELECT COUNT(*) FROM asistencia a JOIN sesion_clase sc ON a.id_sesion = sc.id WHERE sc.id_seccion = s.id AND sc.fecha_clase = CURDATE() AND a.estado_asistencia = 'Presente') AS presentes_hoy
         FROM seccion s
         JOIN materia m ON s.id_materia = m.id
         JOIN usuario ut ON s.id_docente = ut.id
         JOIN perfil pt ON ut.id_perfil = pt.id
         WHERE s.estado = 'Activa'
         ORDER BY FIELD(s.dia_semana, 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'), s.hora_inicio ASC"
    );
    $miHorario = $stmtHorario->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmtHorario = $conexion->prepare(
        "SELECT s.*, m.codigo_materia, m.nombre_materia,
                CONCAT(pt.nombre, ' ', pt.apellido) AS titular_nombre,
                (SELECT COUNT(*) FROM inscripcion_seccion ins WHERE ins.id_seccion = s.id AND ins.estado = 'Inscrito') AS total_inscritos,
                (SELECT COUNT(*) FROM asistencia a JOIN sesion_clase sc ON a.id_sesion = sc.id WHERE sc.id_seccion = s.id AND sc.fecha_clase = CURDATE() AND a.estado_asistencia = 'Presente') AS presentes_hoy
         FROM seccion s
         JOIN materia m ON s.id_materia = m.id
         JOIN usuario ut ON s.id_docente = ut.id
         JOIN perfil pt ON ut.id_perfil = pt.id
         WHERE s.id_docente = :id1 
           AND s.estado = 'Activa'
           AND s.id_materia IN (SELECT id_materia FROM docente_materia WHERE id_docente = :id2)
         ORDER BY FIELD(s.dia_semana, 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'), s.hora_inicio ASC"
    );
    $stmtHorario->execute([':id1' => $id_usuario, ':id2' => $id_usuario]);
    $miHorario = $stmtHorario->fetchAll(PDO::FETCH_ASSOC);
}

$totalSecciones = count($miHorario);
$totalAlumnos   = array_sum(array_column($miHorario, 'total_inscritos'));
$totalPresentes = array_sum(array_column($miHorario, 'presentes_hoy'));

// Obtener las materias asignadas formalmente por Coordinacion
if (in_array($rolDocente, ['Coordinador Academico', 'Director', 'Super Administrador'], true)) {
    $stmtMisMaterias = $conexion->query(
        "SELECT dm.id_docente, m.id as id_materia, m.codigo_materia, m.nombre_materia, m.semestre, m.uc
         FROM docente_materia dm
         JOIN materia m ON dm.id_materia = m.id
         ORDER BY m.semestre IS NULL, m.semestre ASC, m.nombre_materia ASC"
    );
    $misMateriasAsignadas = $stmtMisMaterias->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmtMisMaterias = $conexion->prepare(
        "SELECT m.id as id_materia, m.codigo_materia, m.nombre_materia, m.semestre, m.uc,
                CONCAT(cp.nombre, ' ', cp.apellido) AS coordinador_nombre
         FROM docente_materia dm
         JOIN materia m ON dm.id_materia = m.id
         LEFT JOIN usuario cu ON dm.id_coordinador = cu.id
         LEFT JOIN perfil cp ON cu.id_perfil = cp.id
         WHERE dm.id_docente = :id
         ORDER BY m.semestre IS NULL, m.semestre ASC, m.nombre_materia ASC"
    );
    $stmtMisMaterias->execute([':id' => $id_usuario]);
    $misMateriasAsignadas = $stmtMisMaterias->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="container py-4 mt-3">
    <div class="p-4 mb-4 rounded-4 bg-white shadow-sm d-flex flex-column flex-md-row align-items-center justify-content-between gap-3" style="border-left: 6px solid #7b1fa2 !important;">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-4 p-3 text-white d-flex align-items-center justify-content-center" style="background: linear-gradient(135deg, #7b1fa2, #4a148c); width: 68px; height: 68px;">
                <i class="fas fa-chalkboard-teacher fa-2x"></i>
            </div>
            <div>
                <span class="badge rounded-pill px-3 py-1 mb-1 text-white" style="background:#7b1fa2;">
                    NIVEL 3 · PROFESOR (DOCENTE TITULAR)
                </span>
                <h2 class="fw-bold mb-0 text-dark">Bienvenido, Prof. <?= htmlspecialchars($nombre) ?></h2>
                <p class="text-muted small mb-0">Gestion de Asistencias por QR, Horario Registrado por Coordinacion y Carnet Digital Docente.</p>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="index.php?page=asistencias" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" style="background:#7b1fa2;border-color:#7b1fa2;">
                <i class="fas fa-qrcode me-2"></i>Control y Escaneo de Asistencias
            </a>
            <a href="index.php?page=mi_carnet" class="btn btn-outline-dark rounded-pill px-3 fw-bold">
                <i class="fas fa-id-card me-1"></i>Mi Carnet
            </a>
            <a href="index.php?page=mi_qr" class="btn btn-outline-secondary rounded-pill px-3 fw-bold">
                <i class="fas fa-qrcode me-1"></i>Mi QR
            </a>
            <a href="index.php?page=mi_perfil" class="btn btn-outline-secondary rounded-pill px-3 fw-bold">
                <i class="fas fa-user me-1"></i>Perfil
            </a>
        </div>
    </div>

    <!-- Visualizacion Oficial del Ciclo Academico (Solo Lectura) -->
    <?php PeriodoHelper::renderBannerCiclo($conexion, 'Profesor'); ?>

    <!-- Resumen Rapido -->
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6 col-12">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="text-muted small fw-bold">MATERIAS ASIGNADAS</div>
                <div class="display-6 fw-bold text-dark my-1"><?= count($misMateriasAsignadas) ?></div>
                <small class="text-muted"><i class="fas fa-book-reader me-1"></i>Por Coordinacion</small>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-12">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="text-muted small fw-bold">SECCIONES EN HORARIO</div>
                <div class="display-6 fw-bold text-primary my-1"><?= $totalSecciones ?></div>
                <small class="text-muted"><i class="fas fa-calendar-check me-1"></i>Ingenieria de Sistemas</small>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-12">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="text-muted small fw-bold">ESTUDIANTES EN SECCIONES</div>
                <div class="display-6 fw-bold text-dark my-1"><?= $totalAlumnos ?></div>
                <small class="text-muted"><i class="fas fa-users me-1"></i>Habilitados para asistencia</small>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-12">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="text-muted small fw-bold">ASISTENCIAS HOY (<?= date('d/m/Y') ?>)</div>
                <div class="display-6 fw-bold text-success my-1"><?= $totalPresentes ?></div>
                <small class="text-success"><i class="fas fa-qrcode me-1"></i>QR y Pase Manual</small>
            </div>
        </div>
    </div>

    <!-- TARJETA: MATERIAS A CARGO ASIGNADAS POR COORDINACION -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-book me-2 text-primary"></i>Mis Asignaturas a Cargo</h5>
                <small class="text-muted">Materias formalmente asignadas por el Coordinador Academico.</small>
            </div>
            <span class="badge rounded-pill px-3 py-1.5 fw-bold" style="background:#003366; color:#ffffff;">
                <?= count($misMateriasAsignadas) ?> asignaturas
            </span>
        </div>
        <div class="card-body p-4">
            <?php if (empty($misMateriasAsignadas)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-folder-open fa-3x mb-2 opacity-25"></i>
                    <p class="mb-0 fw-semibold">Aun no tienes materias asignadas formalmente por el Coordinador Academico.</p>
                </div>
            <?php else: ?>
                <div class="row g-2">
                    <?php foreach ($misMateriasAsignadas as $mat): 
                        $semTxt = ($mat['semestre'] !== null) ? (($mat['semestre'] === 0) ? 'CINU' : "Semestre {$mat['semestre']}") : 'Electiva';
                        $esEt = (strpos($mat['codigo_materia'], 'ET-') === 0);
                        $esEnt = (strpos($mat['codigo_materia'], 'ENT-') === 0);
                        $badgeBg = $esEt ? 'bg-primary-subtle text-primary border-primary-subtle' : ($esEnt ? 'bg-warning-subtle text-dark border-warning-subtle' : 'bg-light text-dark border');
                    ?>
                        <div class="col-xl-4 col-md-6 col-12">
                            <div class="p-3 rounded-4 border bg-light h-100 d-flex flex-column justify-content-between" style="border-left: 4px solid #003366 !important;">
                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="badge rounded-pill fw-bold" style="background:#003366; color:#fff; font-size:0.72rem;">
                                            <?= htmlspecialchars($mat['codigo_materia']) ?>
                                        </span>
                                        <span class="badge <?= $badgeBg ?> rounded-pill" style="font-size:0.68rem;">
                                            <?= $semTxt ?> · <?= (int)$mat['uc'] ?> UC
                                        </span>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-0 mt-2" style="font-size:0.88rem; line-height:1.25;">
                                        <?= htmlspecialchars($mat['nombre_materia']) ?>
                                    </h6>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Seccion de Horarios y Cronograma Semanal del Docente -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="fw-bold mb-0 text-dark"><i class="fas fa-calendar-alt me-2 text-primary"></i>Mi Horario de Clases Semanal</h4>
            <small class="text-muted">Cronograma académico en bloques de clase con margen de tolerancia configurado.</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="btn-group btn-group-sm p-1 bg-light rounded-pill border shadow-xs" role="group">
                <button type="button" class="btn btn-sm rounded-pill fw-bold btn-primary text-white" id="btn-vista-matriz" onclick="cambiarVistaDocente('matriz')">
                    <i class="fas fa-calendar-week me-1"></i>Cronograma Semanal
                </button>
                <button type="button" class="btn btn-sm rounded-pill fw-bold text-muted btn-light" id="btn-vista-lista" onclick="cambiarVistaDocente('lista')">
                    <i class="fas fa-list me-1"></i>Lista Resumen
                </button>
            </div>
            <a href="index.php?page=asistencias" class="btn btn-sm btn-success rounded-pill px-3 py-1.5 fw-bold shadow-sm">
                <i class="fas fa-clipboard-check me-1"></i>Asistencias QR
            </a>
        </div>
    </div>

    <!-- Vista 1: Matriz de Cronograma Semanal (Estilo Oficial UNEFA) -->
    <div id="contenedor-horario-matriz">
        <?php
        $seccionesMatriz = $miHorario;
        $esEditable      = true;
        $rolVista        = 'Profesor';
        $tituloMatriz    = 'Mi Cronograma Semanal de Clases';
        require 'app/views/coordinator/partials/horario_matrix.php';
        ?>
    </div>

    <!-- Vista 2: Tabla de Horario Docente Tradicional -->
    <div id="contenedor-horario-lista" style="display:none;" class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th class="ps-4" style="min-width: 220px;">Asignatura y Seccion</th>
                        <th style="min-width: 170px;">Dia y Horario</th>
                        <th style="min-width: 180px;">Chance Limite (Tolerancia)</th>
                        <th style="min-width: 110px;">Aula / Lab</th>
                        <th class="text-center" style="min-width: 90px;">Matricula</th>
                        <th class="text-center" style="min-width: 110px;">Hoy</th>
                        <th class="text-end pe-4" style="min-width: 240px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($miHorario)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fas fa-calendar-times fa-3x mb-2 opacity-25 d-block"></i>
                                Aun no tienes secciones asignadas en tu horario por el Coordinador Academico.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($miHorario as $h): 
                            $tolMin = (int)($h['tolerancia_minutos'] ?? 15);
                            $tsIni = strtotime('2000-01-01 ' . $h['hora_inicio']);
                            $tsFin = strtotime('2000-01-01 ' . $h['hora_fin']);
                            $tsLim = $tsIni + ($tolMin * 60);
                            $hIniTxt = date('h:i A', $tsIni);
                            $hFinTxt = date('h:i A', $tsFin);
                            $hLimTxt = date('h:i A', $tsLim);
                        ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark fs-6" style="line-height:1.35;"><?= htmlspecialchars($h['nombre_materia']) ?></div>
                                    <div class="d-flex flex-wrap align-items-center gap-1.5 mt-1">
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-0.5 fw-bold" style="font-size:0.75rem;">
                                            <?= htmlspecialchars($h['nombre_seccion']) ?>
                                        </span>
                                        <span class="text-muted small">· <?= htmlspecialchars($h['codigo_materia']) ?></span>
                                    </div>
                                    <?php if (in_array($rolDocente, ['Coordinador Academico', 'Director', 'Super Administrador'], true)): ?>
                                        <small class="text-muted d-block mt-1" style="font-size:0.72rem;">
                                            <i class="fas fa-user-tie me-1"></i>Prof. <?= htmlspecialchars($h['titular_nombre']) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-primary rounded-pill px-2.5 py-1 fw-bold"><?= htmlspecialchars($h['dia_semana']) ?></span>
                                    <div class="fw-bold text-dark mt-1" style="font-size:0.88rem;">
                                        <i class="far fa-clock text-primary me-1"></i><?= $hIniTxt ?> - <?= $hFinTxt ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-warning-subtle text-dark border border-warning-subtle rounded-pill px-2.5 py-1 fw-bold" style="font-size:0.75rem;">
                                        <i class="fas fa-hourglass-half text-warning me-1"></i>Hasta las <?= $hLimTxt ?>
                                    </span>
                                    <div class="small text-muted mt-1" style="font-size:0.72rem;">+<?= $tolMin ?> min de tolerancia</div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border rounded-pill px-2.5 py-1">
                                        <i class="fas fa-door-open me-1 text-secondary"></i><?= htmlspecialchars($h['aula']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary-subtle text-dark border rounded-pill px-2.5 py-1 fw-bold">
                                        <?= (int)$h['total_inscritos'] ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 fw-bold">
                                        <i class="fas fa-check-circle me-1"></i><?= (int)$h['presentes_hoy'] ?> presentes
                                    </span>
                                </td>
                                <td class="text-end pe-4 text-nowrap">
                                    <div class="d-inline-flex align-items-center gap-1.5">
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-bold text-nowrap" 
                                                onclick='abrirModalEditarHorario(<?= json_encode($h) ?>)'
                                                title="Configurar hora de entrada, salida y chance limite de llegada">
                                            <i class="fas fa-clock me-1"></i>Horario y Chance
                                        </button>
                                        <a href="index.php?page=asistencias&section_id=<?= (int)$h['id'] ?>" 
                                           class="btn btn-sm btn-primary rounded-pill px-3 py-1 fw-bold text-nowrap" 
                                           style="background:#003366;border-color:#003366;"
                                           title="Pasar lista o escanear QR">
                                            <i class="fas fa-qrcode me-1"></i>Asistencia
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function cambiarVistaDocente(tipo) {
    const cMatriz = document.getElementById('contenedor-horario-matriz');
    const cLista  = document.getElementById('contenedor-horario-lista');
    const bMatriz = document.getElementById('btn-vista-matriz');
    const bLista  = document.getElementById('btn-vista-lista');

    if (tipo === 'matriz') {
        if (cMatriz) cMatriz.style.display = 'block';
        if (cLista) cLista.style.display = 'none';
        bMatriz.className = 'btn btn-sm rounded-pill fw-bold btn-primary text-white';
        bLista.className  = 'btn btn-sm rounded-pill fw-bold text-muted btn-light';
    } else {
        if (cMatriz) cMatriz.style.display = 'none';
        if (cLista) cLista.style.display = 'block';
        bMatriz.className = 'btn btn-sm rounded-pill fw-bold text-muted btn-light';
        bLista.className  = 'btn btn-sm rounded-pill fw-bold btn-primary text-white';
    }
}
</script>

<!-- Modal Unificado para Modificar Horario -->
<?php require 'app/views/coordinator/partials/modal_horario_editor.php'; ?>
