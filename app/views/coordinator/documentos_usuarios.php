<?php
// este archivo sirve para que coordinacion pueda ver la lista de todos los usuarios
// aqui se puede desactivar o reincorporar a estudiantes y profesores sin borrar ningun dato
if (!isset($_SESSION['rol_usuario']) || !in_array($_SESSION['rol_usuario'], ['Coordinador Academico', 'Coordinador', 'Super Administrador', 'Director'])) {
    header('Location: index.php?page=login');
    exit;
}

// filtramos si queremos ver la lista de estudiantes o la lista de docentes
$tipo = $_GET['tipo'] ?? 'estudiante';
$es_docente = ($tipo === 'docente');
$rol_filter = $es_docente ? 'Profesor' : 'Estudiante';
$label_p    = $es_docente ? 'Profesores' : 'Estudiantes';
$accion_desactivar = $es_docente ? 'Desactivar' : 'Retirar';

$busqueda = trim($_GET['q'] ?? '');
$pagina_actual = max(1, intval($_GET['p'] ?? 1));
$por_pagina = 25;
$offset = ($pagina_actual - 1) * $por_pagina;

$where_rol = $es_docente ? "u.rol IN ('Profesor', 'Docente')" : "u.rol IN ('Estudiante', 'Aspirante')";
$stmt_total = $conexion->prepare("SELECT COUNT(*) FROM usuario u JOIN perfil p ON u.id_perfil = p.id WHERE $where_rol" . ($busqueda !== '' ? " AND (p.cedula LIKE :q OR p.nombre LIKE :q OR p.segundo_nombre LIKE :q OR p.apellido LIKE :q OR p.segundo_apellido LIKE :q OR u.correo LIKE :q)" : ""));
$params_total = [];
if ($busqueda !== '') {
    $like = "%{$busqueda}%";
    $params_total[':q'] = $like;
}
$stmt_total->execute($params_total);
$total_registros = (int)$stmt_total->fetchColumn();
$total_paginas = max(1, ceil($total_registros / $por_pagina));

$usuarios = [];
if ($total_registros > 0) {
    $sql = "SELECT u.id, u.correo, u.estado, u.rol, p.id as id_perfil, p.nombre, p.segundo_nombre, p.apellido, p.segundo_apellido, p.cedula, p.tipo_documento, p.telefono, p.direccion
            FROM usuario u
            JOIN perfil p ON u.id_perfil = p.id
            WHERE $where_rol";
    $params = [];
    if ($busqueda !== '') {
        $like = "%{$busqueda}%";
        $sql .= " AND (p.cedula LIKE :q OR p.nombre LIKE :q OR p.segundo_nombre LIKE :q OR p.apellido LIKE :q OR p.segundo_apellido LIKE :q OR u.correo LIKE :q)";
        $params[':q'] = $like;
    }
    $sql .= " ORDER BY p.apellido, p.nombre LIMIT $por_pagina OFFSET $offset";
    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$seccionesPorDocente = [];
$materiasPorDocente = [];
if ($es_docente && !empty($usuarios)) {
    $ids = array_column($usuarios, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmtSec = $conexion->prepare(
        "SELECT s.id_docente, m.codigo_materia, m.nombre_materia, s.nombre_seccion
        FROM seccion s
        JOIN materia m ON s.id_materia = m.id
        WHERE s.id_docente IN ($placeholders)
        ORDER BY m.nombre_materia"
    );
    $stmtSec->execute($ids);
    foreach ($stmtSec->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $seccionesPorDocente[$row['id_docente']][] = $row;
    }

    $stmtDM = $conexion->prepare(
        "SELECT dm.id_docente, m.codigo_materia, m.nombre_materia
         FROM docente_materia dm
         JOIN materia m ON dm.id_materia = m.id
         WHERE dm.id_docente IN ($placeholders)
         ORDER BY m.nombre_materia"
    );
    $stmtDM->execute($ids);
    foreach ($stmtDM->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $materiasPorDocente[$row['id_docente']][] = $row;
    }
}

$docentesDisponibles = [];
if ($es_docente) {
    $stmtD = $conexion->query(
        "SELECT u.id, p.nombre, p.apellido, p.cedula, p.tipo_documento
        FROM usuario u JOIN perfil p ON u.id_perfil = p.id
        WHERE u.rol IN ('Profesor', 'Docente') AND u.estado = 'Aprobado'
        ORDER BY p.apellido, p.nombre"
    );
    $docentesDisponibles = $stmtD->fetchAll(PDO::FETCH_ASSOC);
}
?>
<div class="container py-5 mt-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="p-3 rounded-3 d-flex align-items-center justify-content-center" style="background:linear-gradient(135deg,#003366,#005c99);width:52px;height:52px;">
                <i class="fas fa-user-cog text-white fa-lg"></i>
            </div>
            <div>
                <h1 class="fw-bold mb-0" style="color:#003366;font-size:1.65rem;">Gestion de Usuarios</h1>
                <p class="text-muted mb-0 small">Activar o desactivar cuentas de usuarios del sistema</p>
            </div>
        </div>
        <a href="index.php?page=dashboard" class="btn btn-sm rounded-pill px-3 fw-bold" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;">
            <i class="fas fa-arrow-left me-2"></i>Volver
        </a>
    </div>

    <div class="card border-0 rounded-4 mb-4 overflow-hidden" style="box-shadow:0 1px 3px rgba(0,0,0,0.06);">
        <div class="p-3" style="background:#f8fafc;">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div class="d-flex rounded-pill overflow-hidden" style="background:#e2e8f0;padding:3px;flex-shrink:0;">
                    <a href="index.php?page=documentos_usuarios&tipo=estudiante<?= $busqueda ? '&q='.urlencode($busqueda) : '' ?>"
                    class="px-3 py-2 text-decoration-none small fw-bold rounded-pill transition-all"
                    style="background:<?= $es_docente ? 'transparent' : '#fff' ?>;color:<?= $es_docente ? '#64748b' : '#003366' ?>;box-shadow:<?= $es_docente ? 'none' : '0 1px 3px rgba(0,0,0,0.12)' ?>;">
                        <i class="fas fa-user-graduate me-1"></i>Estudiante
                    </a>
                    <a href="index.php?page=documentos_usuarios&tipo=docente<?= $busqueda ? '&q='.urlencode($busqueda) : '' ?>"
                    class="px-3 py-2 text-decoration-none small fw-bold rounded-pill transition-all"
                    style="background:<?= $es_docente ? '#fff' : 'transparent' ?>;color:<?= $es_docente ? '#003366' : '#64748b' ?>;box-shadow:<?= $es_docente ? '0 1px 3px rgba(0,0,0,0.12)' : 'none' ?>;">
                        <i class="fas fa-chalkboard-teacher me-1"></i>Docente
                    </a>
                </div>
                <form method="GET" action="index.php" class="d-flex gap-2 flex-grow-1" style="min-width:260px;flex-wrap:wrap;">
                    <input type="hidden" name="page" value="documentos_usuarios">
                    <input type="hidden" name="tipo" value="<?= $tipo ?>">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 rounded-start-pill" style="border-color:#d1d9e6;">
                            <i class="fas fa-search text-muted" style="font-size:.9rem;"></i>
                        </span>
                        <input type="text" name="q" value="<?= htmlspecialchars($busqueda) ?>"
                            class="form-control border-start-0 rounded-end-pill"
                            style="border-color:#d1d9e6;font-size:.95rem;padding:.45rem 1rem;outline:none;box-shadow:none;"
                            placeholder="Cedula, nombre o correo..." maxlength="50">
                    </div>
                    <button type="submit" class="btn fw-bold rounded-pill px-4" style="background:#003366;color:#fff;border:none;font-size:.9rem;">Buscar</button>
                </form>
            </div>
        </div>
    </div>

    <div class="card border-0 rounded-4 overflow-hidden" style="box-shadow:0 1px 3px rgba(0,0,0,0.06);">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="small text-uppercase" style="color:#64748b;background:#f8fafc;">
                            <th class="ps-4 py-3"><?= $label_p ?></th>
                            <th>Cedula</th>
                            <th>Estado</th>
                            <th class="text-end pe-4">Accion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuarios)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted">
                                <i class="fas fa-user-slash fa-3x mb-3 opacity-25"></i>
                                <p>No se encontraron <?= strtolower($label_p) ?>.</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($usuarios as $u):
                            $nombre_completo = trim("{$u['nombre']} {$u['segundo_nombre']} {$u['apellido']} {$u['segundo_apellido']}");
                            $activo = ($u['estado'] === 'Aprobado');
                        ?>
                        <tr>
                            <td class="ps-4 py-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white" style="width:36px;height:36px;background:linear-gradient(135deg,#003366,#005c99);font-size:.7rem;flex-shrink:0;">
                                        <?= mb_strtoupper(mb_substr($u['nombre'],0,1).mb_substr($u['apellido'],0,1), 'UTF-8') ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold" style="color:#1e293b;font-size:.85rem;"><?= htmlspecialchars($nombre_completo) ?></div>
                                        <div class="small" style="color:#94a3b8;"><?= htmlspecialchars($u['correo']) ?></div>
                                        <?php if ($es_docente): 
                                            $cntM = count($materiasPorDocente[$u['id']] ?? []);
                                        ?>
                                            <div class="mt-1">
                                                <a href="index.php?page=gestion_docentes" class="badge rounded-pill text-decoration-none" style="background:#e0e7ff; color:#1e40af; font-size:0.68rem;" title="Gestionar materias en modulo docente">
                                                    <i class="fas fa-book me-1"></i><?= $cntM ?> materia(s) asignada(s)
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge bg-light text-muted border fw-normal"><?= htmlspecialchars(($u['tipo_documento'] ?? 'V').'-'.$u['cedula']) ?></span></td>
                            <td>
                                <span class="badge rounded-pill fw-semibold status-badge" id="badge-<?= $u['id'] ?>" style="background:<?= $activo ? '#dcfce7' : '#fee2e2' ?>;color:<?= $activo ? '#166534' : '#991b1b' ?>;">
                                    <?= $activo ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm rounded-pill px-3 fw-semibold me-1 edit-btn"
                                    data-id="<?= $u['id'] ?>"
                                    data-id-perfil="<?= $u['id_perfil'] ?>"
                                    data-nombre="<?= htmlspecialchars($u['nombre']) ?>"
                                    data-segundo_nombre="<?= htmlspecialchars($u['segundo_nombre'] ?? '') ?>"
                                    data-apellido="<?= htmlspecialchars($u['apellido']) ?>"
                                    data-segundo_apellido="<?= htmlspecialchars($u['segundo_apellido'] ?? '') ?>"
                                    data-cedula="<?= htmlspecialchars($u['cedula']) ?>"
                                    data-tipo_documento="<?= htmlspecialchars($u['tipo_documento'] ?? 'V') ?>"
                                    data-telefono="<?= htmlspecialchars($u['telefono'] ?? '') ?>"
                                    data-direccion="<?= htmlspecialchars($u['direccion'] ?? '') ?>"
                                    data-correo="<?= htmlspecialchars($u['correo']) ?>"
                                    style="background:#e8f0fe;color:#003366;border:none;font-size:.75rem;">
                                    <i class="fas fa-edit me-1"></i>Editar
                                </button>
                                <button class="btn btn-sm rounded-pill px-3 fw-semibold toggle-btn"
                                    data-id="<?= $u['id'] ?>"
                                    data-activo="<?= $activo ? '1' : '0' ?>"
                                    data-docente="<?= htmlspecialchars($nombre_completo) ?>"
                                    style="background:<?= $activo ? '#fee2e2' : '#dcfce7' ?>;color:<?= $activo ? '#991b1b' : '#166534' ?>;border:none;font-size:.75rem;">
                                    <i class="fas <?= $activo ? 'fa-user-slash' : 'fa-user-check' ?> me-1"></i>
                                    <?= $activo ? $accion_desactivar : 'Reincorporar' ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($total_paginas > 1): ?>
    <nav class="mt-4" aria-label="Paginacion">
        <ul class="pagination justify-content-center">
            <li class="page-item <?= $pagina_actual <= 1 ? 'disabled' : '' ?>">
                <a class="page-link rounded-start-pill" href="index.php?page=documentos_usuarios&tipo=<?= $tipo ?>&p=<?= $pagina_actual - 1 ?><?= $busqueda ? '&q='.urlencode($busqueda) : '' ?>">Anterior</a>
            </li>
            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
            <li class="page-item <?= $i === $pagina_actual ? 'active' : '' ?>">
                <a class="page-link" href="index.php?page=documentos_usuarios&tipo=<?= $tipo ?>&p=<?= $i ?><?= $busqueda ? '&q='.urlencode($busqueda) : '' ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
            <li class="page-item <?= $pagina_actual >= $total_paginas ? 'disabled' : '' ?>">
                <a class="page-link rounded-end-pill" href="index.php?page=documentos_usuarios&tipo=<?= $tipo ?>&p=<?= $pagina_actual + 1 ?><?= $busqueda ? '&q='.urlencode($busqueda) : '' ?>">Siguiente</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 p-4" style="background: linear-gradient(135deg, #003366, #00509e);">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-2 rounded-3" style="background:rgba(255,255,255,0.2);">
                        <i class="fas fa-user-edit text-white fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold m-0 text-white">Editar Perfil</h5>
                        <small class="text-white opacity-75">Actualice los datos del usuario seleccionado.</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" style="background:#f5f8ff;">
                <form id="editForm">
                    <input type="hidden" name="id_perfil" id="edit-id-perfil">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="row g-3">
                        <div class="col-md-6 mb-1">
                            <label class="form-label small fw-bold" style="color:#003366;">Cedula</label>
                            <div class="reg-input-group">
                                <i class="fas fa-id-card reg-icon"></i>
                                <input type="text" class="reg-input" name="cedula" id="edit-cedula" required maxlength="20">
                            </div>
                        </div>
                        <div class="col-md-3 mb-1">
                            <label class="form-label small fw-bold" style="color:#003366;">Tipo Doc.</label>
                            <select class="form-control" name="tipo_documento" id="edit-tipo_documento" style="height:48px;border-radius:12px;border-color:#d1d9e6;">
                                <option value="V">V</option>
                                <option value="E">E</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-1">
                            <label class="form-label small fw-bold" style="color:#003366;">Correo</label>
                            <input type="email" class="form-control" id="edit-correo" readonly style="height:48px;border-radius:12px;border-color:#d1d9e6;background:#f0f4f8;">
                        </div>
                        <div class="col-md-6 mb-1">
                            <label class="form-label small fw-bold" style="color:#003366;">Nombre</label>
                            <div class="reg-input-group">
                                <i class="fas fa-user reg-icon"></i>
                                <input type="text" class="reg-input" name="nombre" id="edit-nombre" required maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-6 mb-1">
                            <label class="form-label small fw-bold" style="color:#003366;">Segundo Nombre</label>
                            <div class="reg-input-group">
                                <i class="fas fa-user reg-icon"></i>
                                <input type="text" class="reg-input" name="segundo_nombre" id="edit-segundo_nombre" maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-6 mb-1">
                            <label class="form-label small fw-bold" style="color:#003366;">Apellido</label>
                            <div class="reg-input-group">
                                <i class="fas fa-user-tag reg-icon"></i>
                                <input type="text" class="reg-input" name="apellido" id="edit-apellido" required maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-6 mb-1">
                            <label class="form-label small fw-bold" style="color:#003366;">Segundo Apellido</label>
                            <div class="reg-input-group">
                                <i class="fas fa-user-tag reg-icon"></i>
                                <input type="text" class="reg-input" name="segundo_apellido" id="edit-segundo_apellido" maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-6 mb-1">
                            <label class="form-label small fw-bold" style="color:#003366;">Telefono</label>
                            <div class="reg-input-group">
                                <i class="fas fa-phone reg-icon"></i>
                                <input type="text" class="reg-input" name="telefono" id="edit-telefono" maxlength="20">
                            </div>
                        </div>
                        <div class="col-md-6 mb-1">
                            <label class="form-label small fw-bold" style="color:#003366;">Direccion</label>
                            <div class="reg-input-group">
                                <i class="fas fa-map-marker-alt reg-icon"></i>
                                <input type="text" class="reg-input" name="direccion" id="edit-direccion" maxlength="255">
                            </div>
                        </div>
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn fw-bold py-3 rounded-pill text-white" style="background:linear-gradient(135deg,#003366,#00509e);border:none;font-size:1rem;letter-spacing:0.5px;transition:all 0.3s;" id="edit-submit">
                            <i class="fas fa-save me-2"></i>Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDespedirDocente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 p-4" style="background: linear-gradient(135deg, #b71c1c, #e53935);">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-2 rounded-3" style="background:rgba(255,255,255,0.2);">
                        <i class="fas fa-user-slash text-white fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold m-0 text-white">Despedir Docente</h5>
                        <small class="text-white opacity-75" id="despedir-info-docente"></small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" style="background:#fff5f5;">
                <div class="alert alert-warning rounded-3 border-0 py-2 mb-3 d-flex align-items-center gap-2" id="despedir-warning" style="background:#fff3e0;color:#bf360c;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <small class="fw-semibold">Este docente tiene materias asignadas con estudiantes inscritos y notas registradas.</small>
                </div>

                <div class="card border-0 rounded-4 mb-3 overflow-hidden" style="box-shadow:0 1px 4px rgba(0,0,0,0.06);">
                    <div class="card-header py-2 px-3 small fw-bold" style="background:#f8f9fa;color:#37474f;border-bottom:1px solid #eee;">
                        <i class="fas fa-book me-2"></i>Materias asignadas actualmente
                    </div>
                    <div class="card-body p-3" id="despedir-lista-secciones">
                    </div>
                </div>

                <hr class="my-3">

                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:40px;height:40px;background:#e8f5e9;flex-shrink:0;">
                        <i class="fas fa-exchange-alt" style="color:#2e7d32;"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0" style="color:#1b5e20;">Reasignar a otro docente</h6>
                        <small class="text-muted">Todas las materias pasaran al docente seleccionado con sus notas e inscripciones intactas.</small>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-8">
                        <select id="despedir-select-docente" class="form-control" style="border-color:#d1d9e6;">
                            <option value="">-- Seleccione un docente activo --</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <button class="btn w-100 rounded-pill fw-bold text-white" id="btn-despedir-reasignar" style="background:#2e7d32;border:none;padding:0.6rem;">
                            <i class="fas fa-exchange-alt me-2"></i>Reasignar y Despedir
                        </button>
                    </div>
                </div>

                <div class="text-center mt-4 pt-3 border-top d-flex justify-content-center gap-3" style="border-color:#e0d6c8 !important;">
                    <button type="button" class="btn btn-outline-danger rounded-pill px-4" id="btn-despedir-solo">
                        <i class="fas fa-user-slash me-2"></i>Solo Despedir
                    </button>
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const docentesDisponiblesDespedir = <?= json_encode($docentesDisponibles) ?>;
const seccionesDocentesMap = <?= json_encode($seccionesPorDocente) ?>;

document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('edit-id-perfil').value = this.dataset.idPerfil;
        document.getElementById('edit-cedula').value = this.dataset.cedula;
        document.getElementById('edit-tipo_documento').value = this.dataset.tipoDocumento;
        document.getElementById('edit-correo').value = this.dataset.correo;
        document.getElementById('edit-nombre').value = this.dataset.nombre;
        document.getElementById('edit-segundo_nombre').value = this.dataset.segundoNombre;
        document.getElementById('edit-apellido').value = this.dataset.apellido;
        document.getElementById('edit-segundo_apellido').value = this.dataset.segundoApellido;
        document.getElementById('edit-telefono').value = this.dataset.telefono;
        document.getElementById('edit-direccion').value = this.dataset.direccion;
        const modal = new bootstrap.Modal(document.getElementById('editModal'));
        modal.show();
    });
});

document.getElementById('editForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('edit-submit');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';
    const formData = new FormData(this);
    try {
        const res = await fetch('index.php?action=update_student_profile&ajax=1', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            notificar('Perfil actualizado correctamente.', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            notificar(data.message || 'Error al actualizar.', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save me-2"></i>Guardar Cambios';
        }
    } catch(e) {
        notificar('Error: ' + e.message, 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save me-2"></i>Guardar Cambios';
    }
});

let docenteDespedirId = null;

document.querySelectorAll('.toggle-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        const id = this.dataset.id;
        const activo = this.dataset.activo === '1';
        const esDocente = <?= $es_docente ? 'true' : 'false' ?>;
        const actionLabel = activo ? '<?= $accion_desactivar ?>' : 'Reincorporar';

        if (esDocente && activo) {
            docenteDespedirId = parseInt(id);
            const nombre = this.dataset.docente;
            const secciones = seccionesDocentesMap[id] || [];

            document.getElementById('despedir-info-docente').textContent = nombre;

            const warning = document.getElementById('despedir-warning');
            const lista = document.getElementById('despedir-lista-secciones');
            if (secciones.length === 0) {
                warning.style.display = 'none';
                lista.innerHTML = '<div class="text-muted small text-center py-3"><i class="fas fa-info-circle me-2"></i>Este docente no tiene materias asignadas.</div>';
            } else {
                warning.style.display = 'flex';
                let html = '<div class="d-flex flex-wrap gap-2">';
                secciones.forEach(s => {
                    html += '<span class="badge border fw-normal small d-inline-flex align-items-center gap-1 px-3 py-2" style="background:#f0f7ff;color:#003366;border-color:#cce0ff !important;font-size:.8rem;">'
                        + '<i class="fas fa-book"></i> ' + escapeHtml(s.codigo_materia) + ' - ' + escapeHtml(s.nombre_materia)
                        + ' <span class="badge bg-light text-muted border fw-normal" style="font-size:.65rem;">' + escapeHtml(s.nombre_seccion) + '</span>'
                        + '</span>';
                });
                html += '</div>';
                html += '<div class="mt-2 small text-muted"><i class="fas fa-info-circle me-1"></i>' + secciones.length + ' materia(s) asignada(s)</div>';
                lista.innerHTML = html;
            }

            const select = document.getElementById('despedir-select-docente');
            select.innerHTML = '<option value="">-- Seleccione un docente activo --</option>';
            docentesDisponiblesDespedir.forEach(d => {
                if (d.id !== docenteDespedirId) {
                    const opt = document.createElement('option');
                    opt.value = d.id;
                    opt.textContent = d.nombre + ' ' + d.apellido + ' (' + d.tipo_documento + '-' + d.cedula + ')';
                    select.appendChild(opt);
                }
            });
            select.value = '';

            const modal = new bootstrap.Modal(document.getElementById('modalDespedirDocente'));
            modal.show();
            return;
        }

        const msg = activo
            ? '¿Esta seguro de <?= strtolower($accion_desactivar) ?> a este usuario? Perdera el acceso al sistema.'
            : '¿Reincorporar a este usuario? Recuperara el acceso al sistema.';

        if (!await confirmar(msg)) return;

        toggleUserStatus(id, this);
    });
});

// esta funcion cambia el estado de un usuario entre activo e inactivo enviando la orden al servidor
function toggleUserStatus(id, btn) {
    btn.disabled = true;
    fetch('index.php?action=toggle_user_status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, csrf_token: '<?= $_SESSION['csrf_token'] ?>' })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            notificar('Operacion exitosa.', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            notificar(data.message, 'error');
            btn.disabled = false;
        }
    })
    .catch(e => {
        notificar('Error: ' + e.message, 'error');
        btn.disabled = false;
    });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

document.getElementById('btn-despedir-reasignar').addEventListener('click', function() {
    const select = document.getElementById('despedir-select-docente');
    const reassign_to = parseInt(select.value);
    if (!reassign_to || reassign_to <= 0) {
        notificar('Debe seleccionar un docente para reasignar.', 'warning');
        return;
    }
    if (reassign_to === docenteDespedirId) {
        notificar('No puede reasignarse a si mismo.', 'warning');
        return;
    }
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';
    const btn = this;
    fetch('index.php?action=toggle_user_status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id: docenteDespedirId,
            reassign_to: reassign_to,
            csrf_token: '<?= $_SESSION['csrf_token'] ?>'
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalDespedirDocente'));
            if (modal) modal.hide();
            notificar('Docente despedido y materias reasignadas correctamente.', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            notificar(data.message, 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-exchange-alt me-2"></i>Reasignar y Despedir';
        }
    })
    .catch(e => {
        notificar('Error: ' + e.message, 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-exchange-alt me-2"></i>Reasignar y Despedir';
    });
});

document.getElementById('btn-despedir-solo').addEventListener('click', function() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('modalDespedirDocente'));
    if (modal) modal.hide();
    toggleUserStatus(docenteDespedirId, document.querySelector('.toggle-btn[data-id="' + docenteDespedirId + '"]'));
});
</script>

