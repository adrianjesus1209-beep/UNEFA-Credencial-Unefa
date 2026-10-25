<?php
// aqui empieza el modulo donde el coordinador academico maneja a los profesores, sus materias y los horarios.
// primero revisamos si el usuario que entra tiene permiso (coordinador academico, super administrador o director).
// si no tiene ese cargo, lo sacamos de aqui y lo mandamos a la pantalla de iniciar sesion.
$rolCoord = Usuario::normalizarRol($_SESSION['rol_usuario'] ?? '');
if (!in_array($rolCoord, ['Coordinador Academico', 'Super Administrador', 'Director'], true)) {
    header('Location: index.php?page=login');
    exit;
}

// aqui le pedimos a la base de datos la lista de todos los profesores registrados.
// traemos su nombre, cedula, correo, telefono y si ya tienen creado su codigo qr.
$profesoresTitulares = $conexion->query(
    "SELECT u.id, u.correo, u.rol, u.estado, p.nombre, p.apellido, p.cedula, p.tipo_documento, p.telefono, q.qr_token
     FROM usuario u
     JOIN perfil p ON u.id_perfil = p.id
     LEFT JOIN credencial_qr q ON q.id_usuario = u.id
     WHERE u.rol IN ('Profesor', 'Docente')
     ORDER BY p.apellido, p.nombre"
)->fetchAll(PDO::FETCH_ASSOC);

// aqui buscamos todas las materias oficiales que existen en la carrera ordenadas por semestre.
$materiasLista = $conexion->query(
    "SELECT id, codigo_materia, nombre_materia, semestre, uc FROM materia ORDER BY semestre IS NULL, semestre ASC, nombre_materia ASC"
)->fetchAll(PDO::FETCH_ASSOC);

// aqui buscamos que materias tiene asignadas cada profesor en la universidad
// y las guardamos agrupadas dentro de una cajita llamada materiasPorDocente.
$stmtDM = $conexion->query(
    "SELECT dm.id_docente, m.id as id_materia, m.codigo_materia, m.nombre_materia, m.semestre, m.uc
     FROM docente_materia dm
     JOIN materia m ON dm.id_materia = m.id
     ORDER BY m.semestre IS NULL, m.semestre ASC, m.nombre_materia ASC"
);
$materiasPorDocente = [];
while ($row = $stmtDM->fetch(PDO::FETCH_ASSOC)) {
    $materiasPorDocente[(int)$row['id_docente']][] = $row;
}

// aqui buscamos las secciones de clase que estan activas, con su dia, su hora de clase y cuantos alumnos inscritos tiene.
$seccionesLista = $conexion->query(
    "SELECT s.*, m.codigo_materia, m.nombre_materia, m.semestre, m.uc,
            CONCAT(pt.nombre, ' ', pt.apellido) AS titular_nombre,
            (SELECT COUNT(*) FROM inscripcion_seccion ins WHERE ins.id_seccion = s.id AND ins.estado = 'Inscrito') AS total_estudiantes
     FROM seccion s
     JOIN materia m ON s.id_materia = m.id
     JOIN usuario ut ON s.id_docente = ut.id
     JOIN perfil pt ON ut.id_perfil = pt.id
     WHERE s.estado = 'Activa'
     ORDER BY s.dia_semana, s.hora_inicio, s.nombre_seccion"
)->fetchAll(PDO::FETCH_ASSOC);

// aqui traemos a todos los estudiantes que estan aprobados para poder meterlos en una seccion de clase si hace falta.
$estudiantesActivos = $conexion->query(
    "SELECT u.id, p.nombre, p.apellido, p.cedula, p.tipo_documento
     FROM usuario u
     JOIN perfil p ON u.id_perfil = p.id
     WHERE u.rol = 'Estudiante' AND u.estado = 'Aprobado'
     ORDER BY p.apellido, p.nombre"
)->fetchAll(PDO::FETCH_ASSOC);

// aqui tenemos una lista de nombres estandar de las secciones de la unefa segun el semestre para facilitar llenarlas.
if (!isset($seccionesCatalogOpciones)) {
    $seccionesRegistradas = [];
    if (isset($conexion)) {
        $stmtSecDb = $conexion->query("SELECT DISTINCT nombre_seccion FROM seccion WHERE estado = 'Activa' AND nombre_seccion IS NOT NULL AND nombre_seccion != '' ORDER BY nombre_seccion ASC");
        if ($stmtSecDb) {
            $seccionesRegistradas = $stmtSecDb->fetchAll(PDO::FETCH_COLUMN);
        }
    }

    $seccionesCatalogOpciones = [
        'Ciclo Basico (1ro a 4to Semestre y CINU)' => [
            'CINU-2629-D1' => 'CINU-2629-D1 (CINU · Diurno 1)',
            '01S-2629-D1'  => '01S-2629-D1 (1er Semestre · Diurno 1)',
            '02S-2629-D1'  => '02S-2629-D1 (2do Semestre · Diurno 1)',
            '03S-2629-D1'  => '03S-2629-D1 (3er Semestre · Diurno 1)',
            '04S-2629-D1'  => '04S-2629-D1 (4to Semestre · Diurno 1)',
        ],
        'Ciclo Profesional (5to a 9no Semestre)' => [
            '05S-2629-D1'  => '05S-2629-D1 (5to Semestre · Diurno 1)',
            '06S-2629-D1'  => '06S-2629-D1 (6to Semestre · Diurno 1)',
            '07S-2629-D1'  => '07S-2629-D1 (7mo Semestre · Diurno 1)',
            '08S-2629-D1'  => '08S-2629-D1 (8vo Semestre · Diurno 1)',
            '09S-2629-D1'  => '09S-2629-D1 (9no Semestre · Pasantia / TEG)',
        ],
        'Materias Electivas' => [
            '07S-ET-D1'    => '07S-ET-D1 (7mo Sem · Electivas Tecnicas)',
            '07S-ENT-D1'   => '07S-ENT-D1 (7mo Sem · Electivas No Tecnicas)',
            '08S-ET-D1'    => '08S-ET-D1 (8vo Sem · Electivas Tecnicas)',
            '08S-ENT-D1'   => '08S-ENT-D1 (8vo Sem · Electivas No Tecnicas)',
        ]
    ];

    $yaListadas = [];
    foreach ($seccionesCatalogOpciones as $grp) {
        foreach ($grp as $k => $v) $yaListadas[$k] = true;
    }
    $otrasDb = [];
    foreach ($seccionesRegistradas as $sdb) {
        if (!empty($sdb) && !isset($yaListadas[$sdb])) {
            $otrasDb[$sdb] = "Seccion $sdb";
        }
    }
    if (!empty($otrasDb)) {
        $seccionesCatalogOpciones['Otras Secciones en Sistema'] = $otrasDb;
    }
}
?>

<div class="container py-4 mt-3">
    <!-- cartel verde de exito si alguna operacion salio bien -->
    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm mb-3">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <!-- cartel rojo de error si algo fallo -->
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm mb-3">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- cabecera con el titulo y el boton para regresar al panel principal -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h2 class="fw-bold mb-1" style="color:#003366;"><i class="fas fa-chalkboard-teacher me-2"></i>Gestion de Docentes y Asignaturas</h2>
            <p class="text-muted small mb-0">Carrera: <strong>Ingenieria de Sistemas</strong> · Registro y asignacion docente por Coordinacion Academica.</p>
        </div>
        <a href="index.php?page=dashboard" class="btn btn-outline-secondary rounded-pill px-4 fw-bold">
            <i class="fas fa-arrow-left me-1"></i>Volver al Panel
        </a>
    </div>

    <!-- seccion 1: formulario para registrar a un nuevo profesor en el sistema -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 fw-bold text-dark">
                <i class="fas fa-chalkboard-teacher me-2 text-primary"></i>Registrar Profesor (Docente de Materia)
            </h5>
            <span class="badge rounded-pill bg-light text-secondary border px-3 py-1 fw-bold">
                Control de Estudios
            </span>
        </div>
        <div class="card-body p-4">
            <form action="index.php?action=register_teacher_coord" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="rol_docente" value="Profesor">

                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Nombre</label>
                        <input type="text" name="nombre" class="form-control" placeholder="Ej: Carlos" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Apellido</label>
                        <input type="text" name="apellido" class="form-control" placeholder="Ej: Mendoza" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Cedula de Identidad</label>
                        <div class="input-group">
                            <select name="tipo_documento" class="form-select flex-shrink-0" style="max-width: 65px; font-weight: bold;">
                                <option value="V" selected>V</option>
                                <option value="E">E</option>
                            </select>
                            <input type="text" name="cedula" class="form-control" placeholder="Numero de cedula (solo numeros)" pattern="[0-9]+" minlength="6" maxlength="9" required>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Correo Institucional</label>
                        <input type="email" name="correo" class="form-control" placeholder="profesor@unefa.edu.ve" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Clave Inicial</label>
                        <input type="text" name="clave" class="form-control" value="12345678" required>
                    </div>
                </div>

                <!-- aqui se pueden marcar las materias que va a dictar el profesor desde el primer momento -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-bold mb-0">Materias Asignadas (Una o mas)</label>
                        <span class="badge bg-secondary-subtle text-secondary rounded-pill" id="count-sel-reg" style="font-size:0.75rem;">0 seleccionadas</span>
                    </div>
                    <div class="border rounded-3 p-3 bg-light shadow-sm" style="max-height: 220px; overflow-y: auto;">
                        <input type="text" class="form-control form-control-sm mb-2" id="buscar-mat-reg" placeholder="Buscar materia por nombre o codigo..." onkeyup="filtrarMateriasReg()">
                        <div id="lista-materias-reg" class="row g-2">
                            <?php foreach ($materiasLista as $mat): 
                                $semTxt = ($mat['semestre'] !== null) ? (($mat['semestre'] === 0) ? 'CINU' : "Sem {$mat['semestre']}") : 'Electiva';
                            ?>
                                <div class="col-md-6 col-12 item-materia-reg" data-text="<?= htmlspecialchars(strtolower($mat['codigo_materia'] . ' ' . $mat['nombre_materia'])) ?>">
                                    <div class="form-check tarjeta-seleccion-materia">
                                        <input class="form-check-input chk-mat-reg" type="checkbox" name="materias[]" value="<?= (int)$mat['id'] ?>" id="mat_reg_<?= (int)$mat['id'] ?>" onchange="actualizarContadorReg()">
                                        <label class="form-check-label small d-flex justify-content-between align-items-center" for="mat_reg_<?= (int)$mat['id'] ?>">
                                            <span class="text-truncate pe-2">
                                                <strong class="text-dark">[<?= htmlspecialchars($mat['codigo_materia']) ?>]</strong> <?= htmlspecialchars($mat['nombre_materia']) ?>
                                            </span>
                                            <span class="badge bg-light text-muted border rounded-pill flex-shrink-0" style="font-size:0.65rem;"><?= $semTxt ?></span>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <small class="text-muted d-block mt-1.5" style="font-size:0.75rem;">
                        <i class="fas fa-info-circle me-1 text-primary"></i>Puede marcar de antemano las asignaturas oficiales que impartira este docente.
                    </small>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm" style="background:#003366;border-color:#003366;">
                        <i class="fas fa-plus-circle me-1.5"></i>Registrar Docente con QR y Asignaturas
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php
    // contamos cuantos profesores estan activos y cuantos estan inactivos para poner el numero en los botones
    $totalDocentesActivos = 0;
    $totalDocentesInactivos = 0;
    foreach ($profesoresTitulares as $pt) {
        if (($pt['estado'] ?? '') === 'Aprobado') {
            $totalDocentesActivos++;
        } else {
            $totalDocentesInactivos++;
        }
    }
    ?>
    <!-- seccion 2: tabla donde aparecen todos los profesores registrados con sus materias y el boton de activar o desactivar -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="fas fa-user-tie me-2 text-primary"></i>Profesores Registrados y Asignaturas a su Cargo
                </h5>
                <small class="text-muted">El Coordinador puede asignar materias o desactivar profesores. Los datos se preservan en el sistema sin eliminarse.</small>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- estos dos botones sirven para ver solo los profesores activos o solo los inactivos -->
                <div class="btn-group btn-group-sm p-1 rounded-pill bg-light border shadow-xs" role="group">
                    <button type="button" class="btn btn-sm rounded-pill px-3 fw-bold active text-white" id="filtro-docentes-activos" onclick="filtrarEstadoDocentes('activos')" style="background:#003366;">
                        <i class="fas fa-user-check me-1"></i>Activos (<?= $totalDocentesActivos ?>)
                    </button>
                    <button type="button" class="btn btn-sm rounded-pill px-3 fw-bold text-muted" id="filtro-docentes-inactivos" onclick="filtrarEstadoDocentes('inactivos')">
                        <i class="fas fa-user-slash me-1"></i>Inactivos (<?= $totalDocentesInactivos ?>)
                    </button>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Profesor</th>
                        <th>Cedula</th>
                        <th>Contacto</th>
                        <th style="min-width: 260px;">Materias Asignadas</th>
                        <th class="text-center">Estado</th>
                        <th class="text-end pe-4" style="min-width: 190px;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbody-docentes-lista">
                    <?php if (empty($profesoresTitulares)): ?>
                        <tr id="row-docentes-empty-base">
                            <td colspan="6" class="text-center py-4 text-muted">
                                No hay profesores registrados en el sistema.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($profesoresTitulares as $pt): 
                            $pid = (int)$pt['id'];
                            $matsDoc = $materiasPorDocente[$pid] ?? [];
                            $idsMatsDoc = array_map('intval', array_column($matsDoc, 'id_materia'));
                            $nombreCompleto = trim($pt['nombre'] . ' ' . $pt['apellido']);
                            $esActivo = (($pt['estado'] ?? '') === 'Aprobado');
                        ?>
                            <!-- cada fila representa a un profesor. si esta inactivo se oculta por defecto mediante javascript -->
                            <tr class="fila-docente <?= ($esActivo ? 'docente-activo' : 'docente-inactivo') ?>" data-estado="<?= ($esActivo ? 'activo' : 'inactivo') ?>" style="<?= (!$esActivo ? 'background:#fafbfc;' : '') ?>">
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white shadow-sm" style="width:36px;height:36px;background:<?= $esActivo ? 'linear-gradient(135deg,#003366,#005c99)' : '#94a3b8' ?>;font-size:.75rem;flex-shrink:0;">
                                            <?= mb_strtoupper(mb_substr($pt['nombre'],0,1).mb_substr($pt['apellido'],0,1), 'UTF-8') ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold <?= $esActivo ? 'text-dark' : 'text-muted text-decoration-line-through' ?>"><?= htmlspecialchars($nombreCompleto) ?></div>
                                            <div class="d-flex align-items-center gap-1 mt-0.5">
                                                <span class="badge bg-secondary-subtle text-secondary rounded-pill" style="font-size:0.62rem;">Docente Titular</span>
                                                <?php if (!$esActivo): ?>
                                                    <span class="badge rounded-pill fw-bold" style="background:#fee2e2;color:#991b1b;font-size:0.62rem;">Oculto</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border fw-bold">
                                        <?= htmlspecialchars(($pt['tipo_documento'] ?? 'V') . '-' . $pt['cedula']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($pt['correo']) ?></div>
                                    <?php if (!empty($pt['telefono'])): ?>
                                        <small class="text-muted"><i class="fas fa-phone me-1"></i><?= htmlspecialchars($pt['telefono']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (empty($matsDoc)): ?>
                                        <span class="text-muted small fst-italic">
                                            <i class="fas fa-exclamation-circle text-warning me-1"></i>Sin materias asignadas
                                        </span>
                                    <?php else: ?>
                                        <div class="d-flex flex-wrap gap-1" style="max-width:420px;">
                                            <?php foreach ($matsDoc as $md): 
                                                $esEt = (strpos($md['codigo_materia'], 'ET-') === 0);
                                                $esEnt = (strpos($md['codigo_materia'], 'ENT-') === 0);
                                                $badgeStyle = $esEt ? 'background:#e0e7ff; color:#1e40af;' : ($esEnt ? 'background:#fef3c7; color:#92400e;' : 'background:#f1f5f9; color:#334155;');
                                            ?>
                                                <span class="badge rounded-pill border px-2 py-1" style="<?= $badgeStyle ?> font-size:0.70rem;" title="<?= htmlspecialchars($md['nombre_materia']) ?> (<?= (int)$md['uc'] ?> UC)">
                                                    <strong><?= htmlspecialchars($md['codigo_materia']) ?></strong>: <?= htmlspecialchars($md['nombre_materia']) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($esActivo): ?>
                                        <span class="badge rounded-pill fw-bold px-2.5 py-1" style="background:#dcfce7;color:#166534;font-size:0.72rem;">
                                            <i class="fas fa-check-circle me-1"></i>Activo
                                        </span>
                                    <?php else: ?>
                                        <span class="badge rounded-pill fw-bold px-2.5 py-1" style="background:#fee2e2;color:#991b1b;font-size:0.72rem;">
                                            <i class="fas fa-user-slash me-1"></i>Inactivo
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex gap-1 justify-content-end align-items-center flex-wrap">
                                        <!-- boton para abrir la ventanita modal de asignar materias -->
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-primary rounded-pill px-2.5 py-1 fw-bold"
                                                style="font-size:0.75rem;"
                                                onclick='abrirModalMaterias(<?= $pid ?>, <?= json_encode($nombreCompleto) ?>, <?= json_encode($idsMatsDoc) ?>)'
                                                title="Asignar o editar materias">
                                            <i class="fas fa-book me-1"></i>Materias
                                        </button>
                                        
                                        <!-- boton para desactivar o reactivar al profesor sin borrar nada de la base de datos -->
                                        <button type="button"
                                                class="btn btn-sm rounded-pill px-2.5 py-1 fw-bold <?= $esActivo ? 'btn-outline-warning text-dark' : 'btn-outline-success text-success' ?>"
                                                style="font-size:0.75rem;"
                                                onclick="toggleDocenteStatus(<?= $pid ?>, '<?= $esActivo ? 'desactivar' : 'activar' ?>', '<?= htmlspecialchars($nombreCompleto, ENT_QUOTES) ?>')"
                                                title="<?= $esActivo ? 'Desactivar y ocultar profesor del sistema' : 'Reactivar profesor en el sistema' ?>">
                                            <i class="fas <?= $esActivo ? 'fa-user-slash text-danger' : 'fa-user-check text-success' ?> me-1"></i><?= $esActivo ? 'Desactivar' : 'Activar' ?>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr id="row-docentes-empty-filter" style="display:none;">
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="fas fa-info-circle me-1 text-primary"></i><span id="texto-filtro-vacio">No hay profesores en este estado.</span>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- seccion 3: formulario para inscribir a un alumno aprobado en una seccion de clase -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom">
            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-user-graduate me-2 text-info"></i>Asignar Estudiante Aprobado a Seccion de Asistencia</h5>
        </div>
        <div class="card-body p-4">
            <form action="index.php?action=enroll_student_section" method="POST" class="row g-3 align-items-end">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="col-md-5">
                    <label class="form-label small fw-bold">Estudiante Aprobado</label>
                    <select name="id_estudiante" class="form-select" required>
                        <?php foreach ($estudiantesActivos as $est): ?>
                            <option value="<?= (int)$est['id'] ?>">
                                <?= htmlspecialchars($est['nombre'] . ' ' . $est['apellido']) ?> (<?= htmlspecialchars(($est['tipo_documento'] ?? 'V') . '-' . $est['cedula']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-bold">Seccion / Horario</label>
                    <select name="id_seccion" class="form-select" required>
                        <?php foreach ($seccionesLista as $sec): ?>
                            <option value="<?= (int)$sec['id'] ?>">
                                <?= htmlspecialchars($sec['nombre_seccion']) ?> - <?= htmlspecialchars($sec['nombre_materia']) ?> (<?= htmlspecialchars($sec['dia_semana']) ?> <?= substr($sec['hora_inicio'], 0, 5) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary rounded-pill w-100 fw-bold" style="background:#003366;">
                        <i class="fas fa-user-plus me-1"></i>Asignar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- seccion 4: tabla y matriz visual con los horarios de clase de toda la semana -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-calendar-alt me-2 text-primary"></i>Cronograma Semanal y Horarios</h5>
                <small class="text-muted">Gestion de horarios por bloques academicos de 45 minutos (UNEFA)</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary rounded-pill px-3 py-2"><?= count($seccionesLista) ?> clases activas</span>
                <ul class="nav nav-pills bg-light p-1 rounded-pill" id="cronogramaViewTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active rounded-pill px-3 py-1 fw-bold small" id="view-matrix-tab" data-bs-toggle="pill" data-bs-target="#view-matrix" type="button" role="tab">
                            <i class="fas fa-th me-1"></i>Cronograma Visual
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill px-3 py-1 fw-bold small text-secondary" id="view-table-tab" data-bs-toggle="pill" data-bs-target="#view-table" type="button" role="tab">
                            <i class="fas fa-list me-1"></i>Lista Resumen
                        </button>
                    </li>
                </ul>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="tab-content" id="cronogramaViewContent">
                <!-- pestana 1: la matriz visual con los dias de lunes a sabado -->
                <div class="tab-pane fade show active p-0" id="view-matrix" role="tabpanel">
                    <?php
                    $seccionesMatriz   = $seccionesLista;
                    $esEditable        = true;
                    $rolVista          = 'Coordinador';
                    $sinBordeCard      = true;
                    $ocultarTituloCard = true;
                    require 'app/views/coordinator/partials/horario_matrix.php';
                    ?>
                </div>

                <!-- pestana 2: tabla simple tipo lista con todos los horarios -->
                <div class="tab-pane fade" id="view-table" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Seccion</th>
                                    <th>Materia</th>
                                    <th>Profesor</th>
                                    <th>Dia y Horario</th>
                                    <th>Aula</th>
                                    <th class="text-center">Estudiantes</th>
                                    <th class="text-end pe-4">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($seccionesLista)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            <i class="fas fa-calendar-times fa-2x mb-2 d-block opacity-50"></i>
                                            No hay secciones ni horarios registrados.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($seccionesLista as $sec): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold text-primary"><?= htmlspecialchars($sec['nombre_seccion']) ?></td>
                                            <td>
                                                <span class="fw-bold"><?= htmlspecialchars($sec['nombre_materia']) ?></span>
                                                <small class="text-muted d-block"><?= htmlspecialchars($sec['codigo_materia']) ?></small>
                                            </td>
                                            <td><?= htmlspecialchars($sec['titular_nombre']) ?></td>
                                            <td>
                                                <span class="badge bg-info text-dark rounded-pill px-3">
                                                    <?= htmlspecialchars($sec['dia_semana']) ?> · <?= substr($sec['hora_inicio'], 0, 5) ?> - <?= substr($sec['hora_fin'], 0, 5) ?>
                                                </span>
                                                <?php
                                                $tol = (int)($sec['tolerancia_minutos'] ?? 15);
                                                $hi = strtotime('2000-01-01 ' . $sec['hora_inicio']);
                                                $hlim = date('h:i A', $hi + ($tol * 60));
                                                ?>
                                                <small class="d-block text-muted mt-1" title="Chance limite de llegada">
                                                    <i class="fas fa-hourglass-half text-warning me-1"></i>Chance: Hasta <?= $hlim ?> (+<?= $tol ?>m)
                                                </small>
                                            </td>
                                            <td><?= htmlspecialchars($sec['aula']) ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-success rounded-pill px-3"><?= (int)$sec['total_estudiantes'] ?></span>
                                            </td>
                                            <td class="text-end pe-4">
                                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2 me-1" 
                                                        onclick='abrirModalEditarHorario(<?= json_encode($sec, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Modificar Horario">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="index.php?page=asistencias&section_id=<?= (int)$sec['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
                                                    <i class="fas fa-clipboard-check me-1"></i>Asistencias
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ventanita modal flotante que se abre para asignar materias al profesor -->
    <div class="modal fade" id="modalAsignarMaterias" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <form action="index.php?action=assign_teacher_subjects" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="id_docente" id="modal-docente-id" value="">
                    
                    <div class="modal-header border-0 p-4 text-white" style="background: linear-gradient(135deg, #003366, #005c99);">
                        <div class="d-flex align-items-center gap-3">
                            <div class="p-2 rounded-3" style="background:rgba(255,255,255,0.2);">
                                <i class="fas fa-book-reader text-white fa-lg"></i>
                            </div>
                            <div>
                                <h5 class="modal-title fw-bold m-0 text-white">Asignar Materias al Profesor</h5>
                                <small class="text-white opacity-75" id="modal-docente-nombre">Profesor seleccionado</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body p-4" style="background:#f8fafc;">
                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                            <div>
                                <span class="fw-bold small text-dark">Selecciona una o mas materias del pensum:</span>
                                <div class="text-muted" style="font-size:0.75rem;">Puedes marcar todas las materias que este docente este autorizado a dictar.</div>
                            </div>
                            <div class="d-flex gap-2 align-items-center">
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0.5 px-2" style="font-size:0.72rem;" onclick="marcarTodasMateriasModal(true)">Marcar todas</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0.5 px-2" style="font-size:0.72rem;" onclick="marcarTodasMateriasModal(false)">Desmarcar todas</button>
                                <span class="badge rounded-pill px-2.5 py-1 fw-bold" id="modal-contador-mats" style="background:#003366; color:#ffffff; font-size:0.75rem;">0 seleccionadas</span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <input type="text" class="form-control form-control-sm rounded-pill px-3" id="modal-buscar-materia" placeholder="Escribe para buscar por nombre o codigo..." onkeyup="filtrarMateriasModal()">
                        </div>

                        <div class="border rounded-4 bg-white p-3 shadow-sm" style="max-height: 340px; overflow-y: auto;">
                            <div class="row g-2" id="modal-lista-materias">
                                <?php foreach ($materiasLista as $mat): 
                                    $mid = (int)$mat['id'];
                                    $semTxt = ($mat['semestre'] !== null) ? (($mat['semestre'] === 0) ? 'CINU' : "Sem {$mat['semestre']}") : 'Electiva';
                                    $esEt = (strpos($mat['codigo_materia'], 'ET-') === 0);
                                    $esEnt = (strpos($mat['codigo_materia'], 'ENT-') === 0);
                                ?>
                                    <div class="col-md-6 col-12 item-materia-modal" data-text="<?= htmlspecialchars(strtolower($mat['codigo_materia'] . ' ' . $mat['nombre_materia'])) ?>">
                                        <div class="form-check tarjeta-seleccion-materia h-100">
                                            <input class="form-check-input chk-mat-modal" type="checkbox" name="materias[]" value="<?= $mid ?>" id="chk_modal_mat_<?= $mid ?>" onchange="actualizarContadorModal()">
                                            <label class="form-check-label small" for="chk_modal_mat_<?= $mid ?>">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="badge rounded-pill fw-bold" style="background:#e0e7ff; color:#1e40af; font-size:0.68rem;"><?= htmlspecialchars($mat['codigo_materia']) ?></span>
                                                    <span class="badge bg-secondary-subtle text-secondary rounded-pill" style="font-size:0.65rem;"><?= $semTxt ?> · <?= (int)$mat['uc'] ?> UC</span>
                                                </div>
                                                <div class="fw-semibold text-dark mt-1 text-truncate" style="font-size:0.80rem; line-height:1.2;"><?= htmlspecialchars($mat['nombre_materia']) ?></div>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer border-0 p-3 bg-white d-flex justify-content-between">
                        <button type="button" class="btn btn-sm btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-sm rounded-pill px-4 fw-bold text-white shadow-sm" style="background:#003366;">
                            <i class="fas fa-save me-1"></i>Guardar Asignacion de Materias
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- aqui incluimos el editor para agregar o modificar horarios semanales -->
<?php require 'app/views/coordinator/partials/modal_horario_editor.php'; ?>

<script>
// esta funcion abre la ventanita modal y le marca con un gancho las materias que ya tiene asignadas el profesor
function abrirModalMaterias(docenteId, docenteNombre, materiasIds) {
    document.getElementById('modal-docente-id').value = docenteId;
    document.getElementById('modal-docente-nombre').textContent = 'Prof. ' + docenteNombre;
    
    const chks = document.querySelectorAll('.chk-mat-modal');
    chks.forEach(chk => {
        const val = parseInt(chk.value);
        chk.checked = materiasIds.includes(val);
    });
    
    document.getElementById('modal-buscar-materia').value = '';
    filtrarMateriasModal();
    actualizarContadorModal();
    const modal = new bootstrap.Modal(document.getElementById('modalAsignarMaterias'));
    modal.show();
}

// esta funcion cuenta cuantas materias estan marcadas en la ventanita modal y actualiza el texto de conteo
function actualizarContadorModal() {
    const checks = document.querySelectorAll('.chk-mat-modal');
    let total = 0;
    checks.forEach(chk => {
        if (chk.checked) total++;
        const card = chk.closest('.tarjeta-seleccion-materia');
        if (card) {
            card.classList.toggle('seleccionada', chk.checked);
        }
    });
    document.getElementById('modal-contador-mats').textContent = total + ' seleccionada(s)';
}

// esta funcion marca o desmarca todas las casillas de materias a la vez con un solo clic
function marcarTodasMateriasModal(marcar) {
    document.querySelectorAll('.chk-mat-modal').forEach(chk => {
        const parent = chk.closest('.item-materia-modal');
        if (parent && parent.style.display !== 'none') {
            chk.checked = marcar;
        }
    });
    actualizarContadorModal();
}

// esta funcion filtra y oculta las materias en la ventanita segun lo que la persona escriba en la busqueda
function filtrarMateriasModal() {
    const q = (document.getElementById('modal-buscar-materia').value || '').toLowerCase().trim();
    document.querySelectorAll('.item-materia-modal').forEach(item => {
        const text = item.getAttribute('data-text') || '';
        if (q === '' || text.includes(q)) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
}

// esta funcion cuenta cuantas casillas de materias estan marcadas en el formulario de registro de profesor nuevo
function actualizarContadorReg() {
    const checks = document.querySelectorAll('.chk-mat-reg');
    let total = 0;
    checks.forEach(chk => {
        if (chk.checked) total++;
        const card = chk.closest('.tarjeta-seleccion-materia');
        if (card) {
            card.classList.toggle('seleccionada', chk.checked);
        }
    });
    document.getElementById('count-sel-reg').textContent = total + ' seleccionada(s)';
}

// esta funcion filtra las materias mientras escribes en el formulario de registrar profesor nuevo
function filtrarMateriasReg() {
    const q = (document.getElementById('buscar-mat-reg').value || '').toLowerCase().trim();
    document.querySelectorAll('.item-materia-reg').forEach(item => {
        const text = item.getAttribute('data-text') || '';
        if (q === '' || text.includes(q)) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
}

// este filtro sirve para mostrar solo a los profesores activos o solo a los inactivos
// ningun profesor se borra de la base de datos, solo se oculta de la vista para mantener el orden
function filtrarEstadoDocentes(modo) {
    const filas = document.querySelectorAll('.fila-docente');
    const btnActivos = document.getElementById('filtro-docentes-activos');
    const btnInactivos = document.getElementById('filtro-docentes-inactivos');

    if (btnActivos) {
        if (modo === 'activos') {
            btnActivos.className = 'btn btn-sm rounded-pill px-3 fw-bold active text-white';
            btnActivos.style.background = '#003366';
        } else {
            btnActivos.className = 'btn btn-sm rounded-pill px-3 fw-bold text-muted';
            btnActivos.style.background = 'transparent';
        }
    }
    if (btnInactivos) {
        if (modo === 'inactivos') {
            btnInactivos.className = 'btn btn-sm rounded-pill px-3 fw-bold active text-white';
            btnInactivos.style.background = '#003366';
        } else {
            btnInactivos.className = 'btn btn-sm rounded-pill px-3 fw-bold text-muted';
            btnInactivos.style.background = 'transparent';
        }
    }

    let visibles = 0;
    filas.forEach(f => {
        const estado = f.getAttribute('data-estado');
        if (modo === 'activos') {
            if (estado === 'activo') {
                f.style.display = '';
                visibles++;
            } else {
                f.style.display = 'none';
            }
        } else if (modo === 'inactivos') {
            if (estado === 'inactivo') {
                f.style.display = '';
                visibles++;
            } else {
                f.style.display = 'none';
            }
        }
    });

    const emptyRow = document.getElementById('row-docentes-empty-filter');
    const emptyText = document.getElementById('texto-filtro-vacio');
    if (emptyRow) {
        emptyRow.style.display = (filas.length > 0 && visibles === 0) ? '' : 'none';
        if (emptyText) {
            emptyText.textContent = (modo === 'inactivos')
                ? 'No hay profesores inactivos en el sistema.'
                : 'No hay profesores activos en el sistema.';
        }
    }
}

// esta funcion le pide confirmacion al usuario y luego cambia el estado del profesor (activar o desactivar)
async function toggleDocenteStatus(id, accion, nombre) {
    const confirmMsg = (accion === 'desactivar')
        ? `Desea desactivar y ocultar al profesor ${nombre}? Perdera el acceso para iniciar sesion.`
        : `Desea reactivar al profesor ${nombre}? Recuperara el acceso al sistema.`;
    
    if (typeof confirmar === 'function') {
        const ok = await confirmar(confirmMsg);
        if (!ok) return;
    } else {
        if (!confirm(confirmMsg)) return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'index.php?action=toggle_teacher_status_coord';
    
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = '<?= $_SESSION['csrf_token'] ?>';
    form.appendChild(csrfInput);

    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'id_docente';
    idInput.value = id;
    form.appendChild(idInput);

    document.body.appendChild(form);
    form.submit();
}

// cuando la pagina termina de cargar mostramos solo a los profesores activos por defecto
document.addEventListener('DOMContentLoaded', function() {
    filtrarEstadoDocentes('activos');
    actualizarContadorReg();
});
</script>
