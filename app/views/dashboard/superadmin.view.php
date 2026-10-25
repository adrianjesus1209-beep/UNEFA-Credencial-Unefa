<?php
// este archivo es el panel de control del super administrador
// muestra el resumen total del sistema: cuentas creadas, bitacora de auditoria y accesos
if (Usuario::normalizarRol($_SESSION['rol_usuario'] ?? '') !== 'Super Administrador') {
    header('Location: index.php?page=dashboard');
    exit;
}

$totalUsuarios = (int)$conexion->query("SELECT COUNT(*) FROM usuario")->fetchColumn();
$totalQRs      = (int)$conexion->query("SELECT COUNT(*) FROM credencial_qr WHERE estado = 'Activo'")->fetchColumn();
$totalBitacora = (int)$conexion->query("SELECT COUNT(*) FROM bitacora")->fetchColumn();
$totalAsist    = (int)$conexion->query("SELECT COUNT(*) FROM asistencia")->fetchColumn();

$usuariosLista = $conexion->query(
    "SELECT u.id, u.nombre_usuario, u.correo, u.rol, u.estado, p.cedula, p.tipo_documento, p.nombre, p.apellido, q.qr_token
     FROM usuario u
     JOIN perfil p ON u.id_perfil = p.id
     LEFT JOIN credencial_qr q ON q.id_usuario = u.id
     ORDER BY FIELD(u.rol, 'Super Administrador', 'Director', 'Coordinador Academico', 'Profesor', 'Estudiante', 'Aspirante'), u.id ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$ultimosLogs = $conexion->query(
    "SELECT b.*, u.correo, u.rol, u.nombre_usuario
     FROM bitacora b
     LEFT JOIN usuario u ON b.id_usuario = u.id
     ORDER BY b.fecha_hora DESC LIMIT 15"
)->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container py-4 mt-3">
    <div class="p-4 mb-4 rounded-4 bg-white shadow-sm d-flex flex-column flex-md-row align-items-center justify-content-between gap-3" style="border-left: 6px solid #1e293b !important;">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-4 p-3 text-white d-flex align-items-center justify-content-center" style="background: #1e293b; width: 68px; height: 68px;">
                <i class="fas fa-server fa-2x"></i>
            </div>
            <div>
                <span class="badge bg-dark rounded-pill px-3 py-1 mb-1">NIVEL 6 · SUPER ADMINISTRADOR (SOPORTE TECNICO / TI / AUDITORIA)</span>
                <h2 class="fw-bold mb-0 text-dark">Panel de Super Administrador</h2>
                <p class="text-muted small mb-0">Control exclusivo de Auditoria, Archivos de Registro, Seguridad y Roles del Sistema.</p>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="index.php?page=auditoria" class="btn btn-dark rounded-pill px-4 fw-bold shadow-sm">
                <i class="fas fa-file-shield me-2"></i>Auditoria y Archivos de Registro
            </a>
            <a href="index.php?page=mi_perfil" class="btn btn-outline-secondary rounded-pill px-3 fw-bold">
                <i class="fas fa-user me-1"></i>Mi Perfil
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="text-muted small fw-bold">USUARIOS REGISTRADOS</div>
                <div class="display-6 fw-bold text-dark my-1"><?= $totalUsuarios ?></div>
                <small class="text-success"><i class="fas fa-sitemap me-1"></i>6 Roles Jerarquicos</small>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="text-muted small fw-bold">CREDENCIALES QR ACTIVAS</div>
                <div class="display-6 fw-bold text-primary my-1"><?= $totalQRs ?></div>
                <small class="text-muted"><i class="fas fa-qrcode me-1"></i>Tokens criptograficos</small>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="text-muted small fw-bold">EVENTOS DE AUDITORIA</div>
                <div class="display-6 fw-bold text-warning my-1"><?= $totalBitacora ?></div>
                <small class="text-muted"><i class="fas fa-Lock me-1"></i>Acceso exclusivo TI</small>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="text-muted small fw-bold">ASISTENCIAS REGISTRADAS</div>
                <div class="display-6 fw-bold text-success my-1"><?= $totalAsist ?></div>
                <small class="text-muted"><i class="fas fa-check-double me-1"></i>Ingenieria de Sistemas</small>
            </div>
        </div>
    </div>

    <!-- Acceso Directo Exclusivo a Generacion de Archivos de Auditoria -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
        <div class="card-header bg-dark text-white py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="mb-0 fw-bold"><i class="fas fa-clipboard-list me-2"></i>Modulo Exclusivo de Auditoria y Generacion de Archivos de Registro</h5>
                <small class="opacity-75">Unicamente el Super Administrador posee privilegios para visualizar y exportar la bitacora del sistema.</small>
            </div>
            <div class="d-flex gap-2">
                <a href="index.php?action=export_audit_log&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-sm btn-warning rounded-pill px-3 fw-bold text-dark">
                    <i class="fas fa-file-alt me-1"></i>Descargar Log (.TXT/.LOG)
                </a>
                <a href="index.php?action=generate_pdf&tipo=bitacora&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-sm btn-danger rounded-pill px-3 fw-bold">
                    <i class="fas fa-file-pdf me-1"></i>Descargar Auditoria PDF
                </a>
                <a href="index.php?page=auditoria" class="btn btn-sm btn-outline-light rounded-pill px-3 fw-bold">
                    <i class="fas fa-expand me-1"></i>Ver Panel Completo
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Fecha / Hora</th>
                            <th>Usuario / Rol</th>
                            <th>Accion</th>
                            <th>Detalle Tecnico</th>
                            <th class="pe-4">Direccion IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ultimosLogs as $log): ?>
                            <tr>
                                <td class="ps-4 fw-bold">#<?= (int)$log['id'] ?></td>
                                <td><?= date('d/m/Y H:i:s', strtotime($log['fecha_hora'])) ?></td>
                                <td>
                                    <span class="fw-bold"><?= htmlspecialchars($log['nombre_usuario'] ?? $log['correo'] ?? 'Sistema') ?></span>
                                    <span class="badge bg-secondary ms-1"><?= htmlspecialchars($log['rol'] ?? 'TI') ?></span>
                                </td>
                                <td><span class="badge bg-info text-dark"><?= htmlspecialchars($log['accion']) ?></span></td>
                                <td><?= htmlspecialchars($log['detalle']) ?></td>
                                <td class="pe-4"><code><?= htmlspecialchars($log['direccion_ip']) ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Directorio de Cuentas por Rol Jerarquico -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-users-cog me-2 text-primary"></i>Usuarios y Credenciales del Sistema (Clave Estandar: 12345678)</h5>
            <span class="badge bg-primary rounded-pill"><?= count($usuariosLista) ?> cuentas</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Usuario / Login</th>
                        <th>Nombre Completo</th>
                        <th>Cedula</th>
                        <th>Correo</th>
                        <th>Rol Jerarquico</th>
                        <th>Estado</th>
                        <th class="pe-4">Credencial QR</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuariosLista as $u): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-primary"><?= htmlspecialchars($u['nombre_usuario']) ?></td>
                            <td><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?></td>
                            <td><?= htmlspecialchars(($u['tipo_documento'] ?? 'V') . '-' . $u['cedula']) ?></td>
                            <td><?= htmlspecialchars($u['correo']) ?></td>
                            <td><span class="badge bg-dark"><?= htmlspecialchars($u['rol']) ?></span></td>
                            <td>
                                <span class="badge <?= $u['estado'] === 'Aprobado' ? 'bg-success' : ($u['estado'] === 'Pendiente' ? 'bg-warning text-dark' : 'bg-danger') ?>">
                                    <?= htmlspecialchars($u['estado']) ?>
                                </span>
                            </td>
                            <td class="pe-4">
                                <?php if (!empty($u['qr_token'])): ?>
                                    <code class="text-success"><?= htmlspecialchars($u['qr_token']) ?></code>
                                <?php else: ?>
                                    <span class="text-muted">Pendiente aprobacion</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
