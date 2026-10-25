<?php
// este archivo es la pantalla de auditoria y seguridad
// solo puede entrar el super administrador para revisar quien hizo cada accion en el sistema y descargar reportes pdf
if (!isset($_SESSION['rol_usuario']) || $_SESSION['rol_usuario'] !== 'Super Administrador') {
    header('Location: index.php?page=dashboard');
    exit;
}

require_once 'app/helpers/AuditHelper.php';
// filtramos los registros segun la categoria elegida o mostramos todos
$filtroCat = $_GET['cat'] ?? 'todos';
$registros = AuditHelper::obtenerPorCategoria($conexion, $filtroCat, 100);
?>
<style>
.modal-pdf iframe { width: 100%; height: 100%; border: none; }
.modal-pdf .modal-body { padding: 0; background: #f0f0f0; }
.modal-pdf .modal-content { border-radius: 16px; overflow: hidden; }
</style>

<div class="container py-5 mt-3">
    <!-- Encabezado de Nivel 6 -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <span class="badge bg-danger rounded-pill px-3 py-1 mb-1">NIVEL 6 · SUPER ADMINISTRADOR (TI / AUDITORIA)</span>
            <h1 class="fw-bold mb-0" style="color: #003366;">
                <i class="fas fa-clipboard-check me-2"></i> Auditoria y Trazabilidad del Sistema
            </h1>
            <p class="text-muted small mb-0">Generacion clasificada de reportes tecnicos oficiales en formato PDF y exportacion legal en archivo .LOG.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?action=export_audit_log&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-dark rounded-pill px-4 fw-bold">
                <i class="fas fa-file-download me-1"></i> Generar Archivo .LOG
            </a>
            <a href="index.php?page=dashboard" class="btn btn-outline-secondary rounded-pill px-4">
                <i class="fas fa-arrow-left me-1"></i> Volver al Panel
            </a>
        </div>
    </div>

    <!-- TARJETAS DE REPORTES TECNICOS CLASIFICADOS (PDF) -->
    <div class="row g-4 mb-5">
        <!-- Categoria 1: Historial de Aspirantes Validados / Rechazados -->
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-2" style="border-top: 4px solid #ea580c !important;">
                <div class="card-body text-center p-4 d-flex flex-column">
                    <div class="display-5 mb-3 text-warning">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <span class="badge bg-warning text-dark rounded-pill px-3 py-1 mb-2 align-self-center small fw-bold">CATEGORIA 1</span>
                    <h5 class="fw-bold text-dark">Aspirantes y Recaudos</h5>
                    <p class="text-muted small flex-grow-1">Historial cronologico de aspirantes aprobados para ingreso a Estudiante y expedientes rechazados con motivos.</p>
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-warning text-dark rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#modalPDF" data-url="index.php?action=generate_pdf&tipo=aspirantes&preview=1&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" data-title="Auditoria de Aspirantes y Recaudos">
                            <i class="fas fa-eye me-1"></i> Vista Previa
                        </button>
                        <a href="index.php?action=generate_pdf&tipo=aspirantes&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-warning rounded-pill fw-bold text-dark">
                            <i class="fas fa-file-pdf me-1"></i> Exportar PDF
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Categoria 2: Registro de Marcaje de Asistencias (QR vs Manual) -->
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-2" style="border-top: 4px solid #16a34a !important;">
                <div class="card-body text-center p-4 d-flex flex-column">
                    <div class="display-5 mb-3 text-success">
                        <i class="fas fa-qrcode"></i>
                    </div>
                    <span class="badge bg-success text-white rounded-pill px-3 py-1 mb-2 align-self-center small fw-bold">CATEGORIA 2</span>
                    <h5 class="fw-bold text-dark">Marcajes de Asistencia</h5>
                    <p class="text-muted small flex-grow-1">Auditoria de pase de lista por materia, identificando metodo usado (Escaner QR vs Toggle Manual), hora y docente.</p>
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-success rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#modalPDF" data-url="index.php?action=generate_pdf&tipo=asistencias&preview=1&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" data-title="Auditoria de Marcaje de Asistencias (QR y Manual)">
                            <i class="fas fa-eye me-1"></i> Vista Previa
                        </button>
                        <a href="index.php?action=generate_pdf&tipo=asistencias&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-success rounded-pill fw-bold">
                            <i class="fas fa-file-pdf me-1"></i> Exportar PDF
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Categoria 3: Cuentas Docentes y Administrativas -->
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-2" style="border-top: 4px solid #003366 !important;">
                <div class="card-body text-center p-4 d-flex flex-column">
                    <div class="display-5 mb-3" style="color:#003366;">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <span class="badge bg-primary rounded-pill px-3 py-1 mb-2 align-self-center small fw-bold" style="background:#003366 !important;">CATEGORIA 3</span>
                    <h5 class="fw-bold text-dark">Cuentas Institucionales</h5>
                    <p class="text-muted small flex-grow-1">Registro de creacion, edicion de datos, asignacion de coordinadores y cambios de estado de personal institucional.</p>
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#modalPDF" data-url="index.php?action=generate_pdf&tipo=cuentas&preview=1&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" data-title="Auditoria de Cuentas Institucionales">
                            <i class="fas fa-eye me-1"></i> Vista Previa
                        </button>
                        <a href="index.php?action=generate_pdf&tipo=cuentas&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-primary rounded-pill fw-bold" style="background:#003366;border-color:#003366;">
                            <i class="fas fa-file-pdf me-1"></i> Exportar PDF
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL DE PREVISUALIZACION PDF -->
    <div class="modal fade modal-pdf" id="modalPDF" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white py-2">
                    <h6 class="modal-title fw-bold" id="modalPDFTitle">
                        <i class="fas fa-file-pdf text-danger me-2"></i> Reporte
                    </h6>
                    <div class="d-flex gap-2">
                        <a id="modalPDFDownload" href="#" class="btn btn-sm btn-danger rounded-pill px-3 fw-bold">
                            <i class="fas fa-download me-1"></i> Descargar
                        </a>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body p-0" style="max-height: 80vh;height:60vh;">
                    <iframe id="modalPDFIframe" src="" style="width:100%;height:100%;border:none;" title="Previsualizacion PDF"></iframe>
                </div>
                <div class="modal-footer bg-light py-2 justify-content-center">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Si no visualizas el PDF, <a id="modalPDFDirect" href="#" target="_blank" class="text-decoration-none fw-bold">Abrelo en una nueva pestana</a>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLA DE LOGS DE AUDITORIA CLASIFICADOS -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fas fa-list-alt me-2 text-primary"></i> Trazabilidad de Eventos Registrados en Bitacora
                </h5>
                <small class="text-muted">Filtrado clasificado de eventos del sistema.</small>
            </div>
            <!-- Botones de Filtro por Categoria -->
            <div class="btn-group btn-group-sm rounded-pill p-1 bg-light border" role="group">
                <a href="index.php?page=auditoria&cat=todos" class="btn btn-sm rounded-pill fw-bold <?= $filtroCat === 'todos' ? 'btn-dark text-white shadow-sm' : 'btn-light text-dark' ?>">
                    Todos
                </a>
                <a href="index.php?page=auditoria&cat=aspirantes" class="btn btn-sm rounded-pill fw-bold <?= $filtroCat === 'aspirantes' ? 'btn-warning text-dark shadow-sm' : 'btn-light text-warning' ?>">
                    Aspirantes
                </a>
                <a href="index.php?page=auditoria&cat=asistencias" class="btn btn-sm rounded-pill fw-bold <?= $filtroCat === 'asistencias' ? 'btn-success text-white shadow-sm' : 'btn-light text-success' ?>">
                    Asistencias
                </a>
                <a href="index.php?page=auditoria&cat=cuentas" class="btn btn-sm rounded-pill fw-bold <?= $filtroCat === 'cuentas' ? 'btn-primary text-white shadow-sm' : 'btn-light text-primary' ?>" style="<?= $filtroCat === 'cuentas' ? 'background:#003366;border-color:#003366;' : '' ?>">
                    Cuentas
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($registros)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-3x mb-3 d-block opacity-25"></i>
                    <p class="mb-0">No hay registros de actividad para la categoria seleccionada.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle small mb-0" style="table-layout:fixed;">
                        <colgroup>
                            <col style="min-width:130px;width:150px;">
                            <col style="min-width:110px;width:130px;">
                            <col style="min-width:170px;width:200px;">
                            <col style="min-width:140px;width:170px;">
                            <col>
                            <col style="min-width:100px;width:120px;">
                        </colgroup>
                        <thead class="table-dark">
                            <tr>
                                <th class="ps-3 py-3">Fecha / Hora</th>
                                <th>Rol Operador</th>
                                <th>Usuario / Correo</th>
                                <th>Accion</th>
                                <th>Detalle del Evento</th>
                                <th class="pe-3">Direccion IP</th>
                            </tr>
                        </thead>
                        <tbody id="bitacoraBody">
                            <?php foreach ($registros as $r): ?>
                                <tr>
                                    <td class="ps-3 fw-semibold text-muted" style="white-space:nowrap;">
                                        <?= date('d/m/Y H:i:s', strtotime($r['fecha_hora'])); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($r['rol'] ?? 'Sistema'); ?></span>
                                    </td>
                                    <td style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($r['correo'] ?? 'Sistema Central'); ?>">
                                        <?= htmlspecialchars($r['correo'] ?? 'Sistema Central'); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-dark"><?= htmlspecialchars($r['accion']); ?></span>
                                    </td>
                                    <td style="white-space:normal;word-break:break-word;">
                                        <?= htmlspecialchars($r['detalle'] ?? '-'); ?>
                                    </td>
                                    <td class="pe-3">
                                        <code><?= htmlspecialchars($r['direccion_ip']); ?></code>
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

<script>
// cuando se abre la ventanita modal de vista previa cargamos el reporte pdf dentro del marco
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('modalPDF');
    if (modal) {
        modal.addEventListener('show.bs.modal', function(event) {
            var btn = event.relatedTarget;
            var url = btn.getAttribute('data-url');
            var title = btn.getAttribute('data-title');
            document.getElementById('modalPDFTitle').innerHTML = '<i class="fas fa-file-pdf text-danger me-2"></i> ' + title;
            document.getElementById('modalPDFIframe').src = url;
            document.getElementById('modalPDFDownload').href = url.replace('&preview=1', '');
            document.getElementById('modalPDFDirect').href = url.replace('&preview=1', '');
        });
        // cuando se cierra limpiamos el marco para que no consuma memoria
        modal.addEventListener('hidden.bs.modal', function() {
            document.getElementById('modalPDFIframe').src = '';
        });
    }
});
</script>
