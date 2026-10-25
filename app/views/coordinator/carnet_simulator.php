<?php
// este archivo es el simulador de vencimiento de carnets
// sirve para que coordinacion pueda probar como se ve un carnet cuando ya paso su fecha limite de validez (13 meses)
if (!isset($_SESSION['rol_usuario']) || !in_array($_SESSION['rol_usuario'], ['Coordinador Academico', 'Coordinador', 'Super Administrador', 'Director'])) {
    header('Location: index.php?page=login');
    exit;
}

// filtramos si estamos viendo alumnos o profesores
$tipo = $_GET['tipo'] ?? 'estudiante';
$es_docente = ($tipo === 'docente');
$rol_filter = $es_docente ? 'Profesor' : 'Estudiante';
$label_p    = $es_docente ? 'Profesores' : 'Estudiantes';
$icono      = $es_docente ? 'fa-chalkboard-teacher' : 'fa-user-graduate';

$busqueda = trim($_GET['q'] ?? '');
$usuarios = [];
$where_rol = $es_docente ? "u.rol IN ('Profesor', 'Docente', 'Coordinador Academico', 'Director', 'Super Administrador')" : "u.rol = 'Estudiante'";
// si escribieron algo en el buscador traemos solo las coincidencias
if ($busqueda !== '') {
    $like = "%{$busqueda}%";
    $stmt = $conexion->prepare(
        "SELECT u.id, u.correo, u.rol, u.estado, p.nombre, p.segundo_nombre, p.apellido, p.segundo_apellido, p.cedula, p.tipo_documento, p.foto_perfil, p.fecha_carnetizacion
        FROM usuario u
        JOIN perfil p ON u.id_perfil = p.id
        WHERE $where_rol
        AND (p.cedula LIKE :q OR p.nombre LIKE :q OR p.segundo_nombre LIKE :q OR p.apellido LIKE :q OR p.segundo_apellido LIKE :q OR u.correo LIKE :q)
        ORDER BY p.apellido, p.nombre
        LIMIT 20"
    );
    $stmt->execute([':q' => $like]);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // si no escribieron nada traemos los primeros 100 de la lista
    $stmt = $conexion->prepare(
        "SELECT u.id, u.correo, u.rol, u.estado, p.nombre, p.segundo_nombre, p.apellido, p.segundo_apellido, p.cedula, p.tipo_documento, p.foto_perfil, p.fecha_carnetizacion
        FROM usuario u
        JOIN perfil p ON u.id_perfil = p.id
        WHERE $where_rol
        ORDER BY p.apellido, p.nombre
        LIMIT 100"
    );
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="container py-5 mt-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="p-3 rounded-3 d-flex align-items-center justify-content-center" style="background:linear-gradient(135deg,#003366,#005c99);width:52px;height:52px;">
                <i class="fas fa-sync-alt text-white fa-lg"></i>
            </div>
            <div>
                <h1 class="fw-bold mb-0" style="color:#003366;font-size:1.65rem;">Gestión de Carnets</h1>
                <p class="text-muted mb-0 small">Simular vencimientos y estado de carnets de todos los usuarios</p>
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
                    <a href="index.php?page=gestion_carnets&tipo=estudiante<?= $busqueda ? '&q='.urlencode($busqueda) : '' ?>"
                    class="px-3 py-2 text-decoration-none small fw-bold rounded-pill transition-all"
                    style="background:<?= $es_docente ? 'transparent' : '#fff' ?>;color:<?= $es_docente ? '#64748b' : '#003366' ?>;box-shadow:<?= $es_docente ? 'none' : '0 1px 3px rgba(0,0,0,0.12)' ?>;">
                        <i class="fas fa-user-graduate me-1"></i>Estudiante
                    </a>
                    <a href="index.php?page=gestion_carnets&tipo=docente<?= $busqueda ? '&q='.urlencode($busqueda) : '' ?>"
                    class="px-3 py-2 text-decoration-none small fw-bold rounded-pill transition-all"
                    style="background:<?= $es_docente ? '#fff' : 'transparent' ?>;color:<?= $es_docente ? '#003366' : '#64748b' ?>;box-shadow:<?= $es_docente ? '0 1px 3px rgba(0,0,0,0.12)' : 'none' ?>;">
                        <i class="fas fa-chalkboard-teacher me-1"></i>Docente
                    </a>
                </div>
                <form method="GET" action="index.php" class="d-flex gap-2 flex-grow-1" style="min-width:260px;flex-wrap:wrap;">
                    <input type="hidden" name="page" value="gestion_carnets">
                    <input type="hidden" name="tipo" value="<?= $tipo ?>">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 rounded-start-pill" style="border-color:#d1d9e6;">
                            <i class="fas fa-search text-muted" style="font-size:.9rem;"></i>
                        </span>
                        <input type="text" name="q" value="<?= htmlspecialchars($busqueda) ?>"
                            class="form-control border-start-0 rounded-end-pill"
                            style="border-color:#d1d9e6;font-size:.95rem;padding:.45rem 1rem;outline:none;box-shadow:none;"
                            placeholder="Cédula, nombre o correo..." maxlength="50">
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
                            <th>Cédula</th>
                            <th>Estado</th>
                            <th>Carnet</th>
                            <th>Vencimiento</th>
                            <th class="text-end pe-4">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuarios)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fas fa-user-slash fa-3x mb-3 opacity-25"></i>
                                <p>No se encontraron <?= strtolower($label_p) ?>.</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($usuarios as $u):
                            $nombre_completo = trim("{$u['nombre']} {$u['segundo_nombre']} {$u['apellido']} {$u['segundo_apellido']}");
                            $tiene_foto = !empty($u['foto_perfil']);
                            $fecha_c = $u['fecha_carnetizacion'] ? new DateTime($u['fecha_carnetizacion']) : null;
                            $ahora = new DateTime();

                            $estado_carnet = '';
                            $color_carnet = '';
                            $vencimiento = '';
                            $color_venc = '';
                            if (!$tiene_foto) {
                                $estado_carnet = 'Sin foto';
                                $color_carnet = '#fef3c7';
                                $color_text = '#92400e';
                            } elseif ($fecha_c) {
                                $validez = clone $fecha_c; $validez->modify('+13 months');
                                if ($ahora > $validez) {
                                    $estado_carnet = 'Vencido';
                                    $color_carnet = '#fee2e2';
                                    $color_text = '#991b1b';
                                    $dias_atras = $validez->diff($ahora)->days;
                                    $m = floor($dias_atras / 30);
                                    $d = $dias_atras % 30;
                                    $vencimiento = 'Vencido hace ' . $m . 'm ' . $d . 'd';
                                    $color_venc = '#dc2626';
                                } else {
                                    $estado_carnet = 'Vigente';
                                    $color_carnet = '#dcfce7';
                                    $color_text = '#166534';
                                    $total_dias = $ahora->diff($validez)->days;
                                    $m = floor($total_dias / 30);
                                    $d = $total_dias % 30;
                                    $vencimiento = 'Vence en ' . $m . 'm ' . $d . 'd';
                                    $color_venc = '#16a34a';
                                }
                            } else {
                                $estado_carnet = 'Vigente';
                                $color_carnet = '#dcfce7';
                                $color_text = '#166534';
                            }
                        ?>
                        <tr>
                            <td class="ps-4 py-3">
                                <div class="d-flex align-items-center gap-3">
                                    <?php if ($tiene_foto): ?>
                                        <img src="public/uploads/profiles/<?= htmlspecialchars($u['foto_perfil']) ?>" class="rounded-circle" style="width:36px;height:36px;object-fit:cover;border:2px solid #e2e8f0;">
                                    <?php else: ?>
                                        <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white" style="width:36px;height:36px;background:linear-gradient(135deg,#003366,#005c99);font-size:.7rem;">
                                            <?= mb_strtoupper(mb_substr($u['nombre'],0,1).mb_substr($u['apellido'],0,1), 'UTF-8') ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="fw-bold" style="color:#1e293b;font-size:.85rem;"><?= htmlspecialchars($nombre_completo) ?></div>
                                        <div class="small" style="color:#94a3b8;"><?= htmlspecialchars($u['correo']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge bg-light text-muted border fw-normal"><?= htmlspecialchars(($u['tipo_documento'] ?? 'V').'-'.$u['cedula']) ?></span></td>
                            <td>
                                <span class="badge rounded-pill fw-semibold" style="background:<?= $u['estado'] === 'Aprobado' ? '#dcfce7' : ($u['estado'] === 'Pendiente' ? '#fef3c7' : '#fee2e2') ?>;color:<?= $u['estado'] === 'Aprobado' ? '#166534' : ($u['estado'] === 'Pendiente' ? '#92400e' : '#991b1b') ?>;">
                                    <?= $u['estado'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge rounded-pill fw-semibold" style="background:<?= $color_carnet ?>;color:<?= $color_text ?>;">
                                    <?php if ($tiene_foto): ?>
                                        <i class="fas fa-check me-1"></i>
                                    <?php else: ?>
                                        <i class="fas fa-camera me-1"></i>
                                    <?php endif; ?>
                                    <?= $estado_carnet ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($vencimiento): ?>
                                    <span class="small fw-bold" style="color:<?= $color_venc ?>;"><?= $vencimiento ?></span>
                                <?php else: ?>
                                    <span class="small text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-flex gap-2 justify-content-end align-items-center flex-wrap">
                                    <a href="index.php?page=mi_carnet&id_usuario=<?= $u['id'] ?>" class="btn btn-sm rounded-pill px-3 fw-semibold" style="background:#0891b2;color:#fff;border:none;font-size:.75rem;">
                                        <i class="fas fa-id-card me-1"></i>Ver Carnet
                                    </a>
                                    <a href="index.php?page=mi_qr&id_usuario=<?= $u['id'] ?>" class="btn btn-sm btn-outline-dark rounded-pill px-3 fw-semibold" style="font-size:.75rem;">
                                        <i class="fas fa-qrcode me-1"></i>QR
                                    </a>
                                    <a href="index.php?page=carnetizacion&tipo=<?= $tipo ?>&est=<?= $u['id'] ?>" class="btn btn-sm rounded-pill px-3 fw-semibold" style="background:#003366;color:#fff;border:none;font-size:.75rem;">
                                        <i class="fas fa-camera me-1"></i>Carnetizar
                                    </a>
                                    <select id="sim-<?= $u['id'] ?>" onchange="simularDesdeSelect(<?= $u['id'] ?>, this)" class="form-select form-select-sm rounded-pill fw-semibold" style="width:auto;font-size:.7rem;background:#f8fafc;border:1px solid #d1d9e6;padding:.25rem .75rem;max-width:140px;">
                                        <option value="">Simular</option>
                                        <option value="total">Vencimiento validez (13 meses)</option>
                                    </select>
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
</div>

<script>
// esta funcion envia la peticion al servidor para cambiar la fecha del carnet y simular vencimiento
async function simularDesdeSelect(id, select) {
    const type = select.value;
    if (!type) {
        select.value = '';
        return;
    }
    const labels = {
        'total': 'Vencimiento de validez (13 meses)'
    };
    // preguntamos si esta seguro antes de aplicar el cambio
    if (!await confirmar(`Deseas aplicar ${labels[type]} a este usuario?`)) {
        select.value = '';
        return;
    }

    select.disabled = true;

    try {
        const res = await fetch('index.php?action=simulate_expiry', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ est_id: id, type: type, csrf_token: '<?= $_SESSION['csrf_token'] ?>' })
        });
        const data = await res.json();
        if (data.success) {
            notificar('Simulacion aplicada correctamente.', 'success');
            setTimeout(() => location.reload(), 1200);
        } else {
            notificar(data.message, 'error');
            select.disabled = false;
            select.value = '';
        }
    } catch(e) {
        notificar('Error: ' + e.message, 'error');
        select.disabled = false;
        select.value = '';
    }
}
</script>
