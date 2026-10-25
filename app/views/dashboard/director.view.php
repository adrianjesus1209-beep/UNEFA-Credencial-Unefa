<?php
// este archivo es el panel de control del director
// le permite supervisar las clases, ver al coordinador asignado y nombrar a un nuevo coordinador si hace falta
if (Usuario::normalizarRol($_SESSION['rol_usuario'] ?? '') !== 'Director') {
    header('Location: index.php?page=dashboard');
    exit;
}

$carreraInfo = $conexion->query(
    "SELECT c.id, c.codigo_carrera, c.nombre_carrera, c.id_coordinador,
            u.correo AS coord_correo, u.nombre_usuario AS coord_login,
            p.nombre AS coord_nombre, p.apellido AS coord_apellido, p.cedula AS coord_cedula, p.tipo_documento AS coord_tipo
     FROM carrera c
     LEFT JOIN usuario u ON c.id_coordinador = u.id
     LEFT JOIN perfil p ON u.id_perfil = p.id
     WHERE c.id = 1 LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

// Candidatos elegibles para ser Coordinador Academico de Ingenieria de Sistemas
$candidatosCoord = $conexion->query(
    "SELECT u.id, u.rol, u.correo, p.nombre, p.apellido, p.cedula, p.tipo_documento
     FROM usuario u
     JOIN perfil p ON u.id_perfil = p.id
     WHERE u.rol IN ('Coordinador Academico', 'Profesor') AND u.estado = 'Aprobado'
     ORDER BY FIELD(u.rol, 'Coordinador Academico', 'Profesor'), p.apellido ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$seccionesResumen = $conexion->query(
    "SELECT s.id, s.nombre_seccion, s.dia_semana, s.hora_inicio, s.hora_fin, s.aula,
            m.nombre_materia, m.codigo_materia,
            CONCAT(pd.nombre, ' ', pd.apellido) AS profesor_titular,
            (SELECT COUNT(*) FROM inscripcion_seccion ins WHERE ins.id_seccion = s.id AND ins.estado = 'Inscrito') AS total_inscritos
     FROM seccion s
     JOIN materia m ON s.id_materia = m.id
     JOIN usuario ud ON s.id_docente = ud.id
     JOIN perfil pd ON ud.id_perfil = pd.id
     ORDER BY s.nombre_seccion ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$totalEstudiantes = (int)$conexion->query("SELECT COUNT(*) FROM usuario WHERE rol = 'Estudiante' AND estado = 'Aprobado'")->fetchColumn();
$totalAspirantes  = (int)$conexion->query("SELECT COUNT(*) FROM usuario WHERE rol = 'Aspirante'")->fetchColumn();
$totalProfesores  = (int)$conexion->query("SELECT COUNT(*) FROM usuario WHERE rol = 'Profesor' AND estado = 'Aprobado'")->fetchColumn();
?>

<div class="container py-4 mt-3">
    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm mb-3">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm mb-3">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="p-4 mb-4 rounded-4 bg-white shadow-sm d-flex flex-column flex-md-row align-items-center justify-content-between gap-3" style="border-left: 6px solid #b91c1c !important;">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-4 p-3 text-white d-flex align-items-center justify-content-center" style="background: #b91c1c; width: 68px; height: 68px;">
                <i class="fas fa-landmark fa-2x"></i>
            </div>
            <div>
                <span class="badge bg-danger rounded-pill px-3 py-1 mb-1">NIVEL 5 · DIRECTOR (SUPERVISION EJECUTIVA)</span>
                <h2 class="fw-bold mb-0 text-dark">Despacho de Direccion y Rectorado</h2>
                <p class="text-muted small mb-0">Supervision Institucional, Estadisticas Globales de Asistencia y Alta de Personal Clave (Ingenieria de Sistemas).</p>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="index.php?page=mi_carnet" class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm">
                <i class="fas fa-id-card me-2"></i>Mi Carnet Digital
            </a>
            <a href="index.php?page=mi_qr" class="btn btn-outline-danger rounded-pill px-4 fw-bold">
                <i class="fas fa-qrcode me-2"></i>Mi Codigo QR
            </a>
            <a href="index.php?page=mi_perfil" class="btn btn-outline-secondary rounded-pill px-3 fw-bold">
                <i class="fas fa-user me-1"></i>Mi Perfil
            </a>
        </div>
    </div>

    <!-- Tarjetas de Resumen Institucional -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="text-muted small fw-bold">CARRERA ACTIVA</div>
                <div class="h5 fw-bold text-primary my-2"><?= htmlspecialchars($carreraInfo['nombre_carrera'] ?? 'Ingenieria de Sistemas') ?></div>
                <small class="text-muted">Codigo: <strong><?= htmlspecialchars($carreraInfo['codigo_carrera'] ?? 'SYS') ?></strong></small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="text-muted small fw-bold">COORDINADOR ASIGNADO</div>
                <div class="h6 fw-bold text-dark my-2">
                    <?= !empty($carreraInfo['coord_nombre']) ? htmlspecialchars($carreraInfo['coord_nombre'] . ' ' . $carreraInfo['coord_apellido']) : 'Sin asignar' ?>
                </div>
                <small class="text-success"><i class="fas fa-user-shield me-1"></i><?= htmlspecialchars(($carreraInfo['coord_tipo'] ?? 'V') . '-' . ($carreraInfo['coord_cedula'] ?? '')) ?></small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="text-muted small fw-bold">CUERPO DOCENTE</div>
                <div class="display-6 fw-bold text-dark my-1"><?= $totalProfesores ?></div>
                <small class="text-muted">Profesores Activos</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="text-muted small fw-bold">MATRICULA Y ASPIRANTES</div>
                <div class="display-6 fw-bold text-success my-1"><?= $totalEstudiantes ?></div>
                <small class="text-warning fw-bold"><?= $totalAspirantes ?> aspirante(s) en revision</small>
            </div>
        </div>
    </div>

    <!-- Asignacion de Coordinador Academico para Ingenieria de Sistemas -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-user-check me-2 text-danger"></i>Asignar Coordinador Academico de la Carrera</h5>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted small mb-3">
                        Como <strong>Director</strong>, usted designa al <strong>Coordinador Academico</strong> responsable de la carrera de <strong>Ingenieria de Sistemas</strong>.
                    </p>
                    <form action="index.php?action=assign_coordinator" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Carrera</label>
                            <input type="text" class="form-control bg-light fw-bold" value="Ingenieria de Sistemas (SYS)" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Seleccionar Coordinador Academico</label>
                            <select name="id_coordinador" class="form-select" required>
                                <?php foreach ($candidatosCoord as $cand): ?>
                                    <option value="<?= (int)$cand['id'] ?>" <?= ((int)($carreraInfo['id_coordinador'] ?? 0) === (int)$cand['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cand['nombre'] . ' ' . $cand['apellido']) ?> (<?= htmlspecialchars(($cand['tipo_documento'] ?? 'V') . '-' . $cand['cedula']) ?> · <?= htmlspecialchars($cand['rol']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold w-100">
                            <i class="fas fa-save me-2"></i>Guardar Designacion de Coordinador
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-user-plus me-2 text-primary"></i>Registrar Nuevo Coordinador Academico</h5>
                </div>
                <div class="card-body p-4">
                    <form action="index.php?action=register_coordinator_by_director" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold">Nombre</label>
                                <input type="text" name="nombre" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Apellido</label>
                                <input type="text" name="apellido" class="form-control form-control-sm" required>
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold">Cedula (Solo numeros)</label>
                                <input type="text" name="cedula" class="form-control form-control-sm" pattern="[0-9]+" minlength="6" maxlength="9" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Telefono</label>
                                <input type="text" name="telefono" class="form-control form-control-sm" value="04120000000" required>
                            </div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold">Correo Institucional</label>
                                <input type="email" name="correo" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Clave Inicial</label>
                                <input type="text" name="clave" class="form-control form-control-sm" value="12345678" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold w-100" style="background:#003366;border-color:#003366;">
                            <i class="fas fa-user-shield me-2"></i>Crear y Asignar Coordinador
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Supervision de Horarios y Secciones Registradas por el Coordinador -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-calendar-alt me-2 text-primary"></i>Horarios y Secciones Activas - Ingenieria de Sistemas</h5>
            <span class="badge bg-primary rounded-pill"><?= count($seccionesResumen) ?> secciones</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Seccion</th>
                        <th>Materia</th>
                        <th>Profesor</th>
                        <th>Horario / Aula</th>
                        <th class="text-center pe-4">Estudiantes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($seccionesResumen)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No hay secciones registradas actualmente.</td></tr>
                    <?php else: ?>
                        <?php foreach ($seccionesResumen as $sec): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-primary"><?= htmlspecialchars($sec['nombre_seccion']) ?></td>
                                <td>
                                    <span class="fw-bold"><?= htmlspecialchars($sec['nombre_materia']) ?></span>
                                    <small class="text-muted d-block"><?= htmlspecialchars($sec['codigo_materia']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($sec['profesor_titular']) ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?= htmlspecialchars($sec['dia_semana']) ?> · <?= substr($sec['hora_inicio'], 0, 5) ?> a <?= substr($sec['hora_fin'], 0, 5) ?>
                                    </span>
                                    <small class="text-muted d-block"><?= htmlspecialchars($sec['aula']) ?></small>
                                </td>
                                <td class="text-center pe-4">
                                    <span class="badge bg-success rounded-pill px-3"><?= (int)$sec['total_inscritos'] ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
