<?php
// este archivo es el panel de aprobacion de inscripciones
// aqui el coordinador revisa los papeles que subieron los bachilleres
// si todos los papeles estan bien los aprueba para que pasen a ser estudiantes oficiales
if (!isset($_SESSION['rol_usuario']) || !in_array($_SESSION['rol_usuario'], ['Coordinador Academico', 'Coordinador', 'Super Administrador', 'Director'])) {
    header('Location: index.php?page=login');
    exit;
}
$modeloUsuario = new Usuario($conexion);
// traemos la lista de personas que estan esperando que revisen sus papeles
$pendientes = $modeloUsuario->obtenerUsuariosPendientes();

// traemos la lista de profesores para consultas
$stmtDocentes = $conexion->prepare(
    "SELECT u.id, u.correo, u.estado, p.nombre, p.segundo_nombre, p.apellido, p.segundo_apellido, p.cedula, p.tipo_documento, p.foto_perfil
    FROM usuario u
    JOIN perfil p ON u.id_perfil = p.id
    WHERE u.rol IN ('Profesor', 'Docente')
    ORDER BY p.apellido, p.nombre"
);
$stmtDocentes->execute();
$docentes = $stmtDocentes->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container py-5 mt-5">
    <div class="row mb-5 align-items-center animate__animated animate__fadeIn">
        <div class="col-md-7">
            <h1 class="display-5 fw-bold mb-1" style="color: var(--coord-primary);"><i class="fas fa-university me-3"></i>Gestion de Inscripcion</h1>
            <p class="text-muted mb-0" style="font-size: 1.05rem;">Validacion y aprobacion de documentos estudiantiles.</p>
        </div>
        <div class="col-md-5 mt-3 mt-md-0 d-flex align-items-center justify-content-md-end gap-2">
            <a href="index.php?page=dashboard" class="btn btn-sm rounded-pill px-3 fw-bold" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;transition:all .2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
                <i class="fas fa-arrow-left me-2"></i>Volver
            </a>
            <a href="index.php?page=approve_registration"
                class="d-flex align-items-center gap-2 rounded-pill px-3 py-2 border text-decoration-none btn-hover"
                style="background: rgba(0,51,102,0.05); border-color: rgba(0,51,102,0.15) !important; transition:all .2s;">
                <span class="fw-bold fs-5" style="color: var(--coord-primary);"><?php echo count($pendientes); ?></span>
                <span class="text-muted small fw-semibold" style="line-height: 1.2; font-size: 0.75rem;">Pendientes<br>por Revisar</span>
            </a>
            <div class="dropdown">
                <button class="btn rounded-3 d-flex align-items-center justify-content-center" type="button"
                        data-bs-toggle="dropdown" aria-expanded="false"
                        style="width:44px;height:44px;background:var(--coord-primary);color:#fff;border:none;font-size:1.2rem;">
                    <i class="fas fa-bars"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end border-0 shadow rounded-4 p-2" style="min-width:220px;">
                    <li><a class="dropdown-item rounded-3 py-2 px-3 fw-bold small" style="background:#f5f0ff;" href="index.php?page=gestion_docentes"><i class="fas fa-chalkboard-teacher me-2" style="color:#5c6bc0;"></i>Registrar Docente</a></li>
                    <li><a class="dropdown-item rounded-3 py-2 px-3 fw-bold small" style="background:#fff8f0;" href="index.php?page=approve_registration"><i class="fas fa-user-check me-2" style="color:#e67e22;"></i>Aprobar registros de estudiantes</a></li>
                    <li><a class="dropdown-item rounded-3 py-2 px-3 fw-bold small" style="background:#f5f3ff;" href="index.php?page=documentos_usuarios"><i class="fas fa-user-cog me-2" style="color:#7c3aed;"></i>Gestion de Usuarios</a></li>
                    <li><a class="dropdown-item rounded-3 py-2 px-3 fw-bold small" style="background:#f0f7ff;" href="index.php?page=carnetizacion"><i class="fas fa-camera me-2" style="color:#1976d2;"></i>Carnetizacion</a></li>
                    <li><a class="dropdown-item rounded-3 py-2 px-3 fw-bold small" style="background:#f0fdfa;" href="index.php?page=gestion_carnets"><i class="fas fa-sync-alt me-2" style="color:#0891b2;"></i>Gestion de Carnets</a></li>
                    <li><a class="dropdown-item rounded-3 py-2 px-3 fw-bold small" style="background:#fff7ed;" href="index.php?page=gestion_periodos"><i class="fas fa-calendar-alt me-2" style="color:#c2410c;"></i>Periodos academicos</a></li>
                    <li><a class="dropdown-item rounded-3 py-2 px-3 fw-bold small" style="background:#f0fdf4;" href="index.php?page=cronograma"><i class="fas fa-calendar-week me-2" style="color:#16a34a;"></i>Cronograma academico</a></li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li><a class="dropdown-item rounded-3 py-2 px-3 fw-bold small" style="background:#f8f9fa;" href="index.php?page=mi_perfil"><i class="fas fa-user me-2" style="color:#6b7280;"></i>Mi Perfil</a></li>
                </ul>
            </div>
        </div>

        </div>
    </div>

    <div class="container py-4">
    <?php
    $stmtPeriodo = $conexion->query("SELECT nombre FROM periodo_academico WHERE estado = 'Activo' LIMIT 1");
    $periodoActivo = $stmtPeriodo->fetchColumn();
    ?>
    <?php if ($periodoActivo): ?>
        <div class="d-flex align-items-center gap-2 mb-4">
            <span class="badge rounded-pill px-3 py-2" style="background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;">
                <i class="fas fa-calendar-check me-1"></i>Periodo activo: <strong><?= htmlspecialchars($periodoActivo) ?></strong>
            </span>
        </div>
    <?php endif; ?>
    <div class="card border-0 coord-card overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr class="text-muted small fw-bold">
                        <th class="ps-4 py-4">ESTUDIANTE</th>
                        <th>CEDULA</th>
                        <th>ESTADO DE CARGA</th>
                        <th class="text-end pe-4">ARCHIVOS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pendientes)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-5">
                                <i class="fas fa-check-double fa-3x text-success mb-3 opacity-25"></i>
                                <p class="text-muted fw-bold">No hay documentos pendientes por revisar.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pendientes as $p): 
                            $docs = $modeloUsuario->obtenerDocumentosUsuario($p['id']);
                            $total_docs = count($docs);
                            $aprobados = count(array_filter($docs, function($d) { return $d['estado'] == 'Aprobado'; }));
                        ?>
                            <tr>
                                <td class="ps-4 py-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-circle">
                                            <i class="fas fa-user text-secondary"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark h6 mb-0"><?php echo htmlspecialchars($p['nombre'] . ' ' . $p['apellido']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($p['correo']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark border fw-medium px-3 py-2 rounded-pill"><?php echo htmlspecialchars($p['cedula']); ?></span></td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="progress flex-grow-1" style="height: 8px; min-width:60px; max-width:120px; background: #e2e8f0; border-radius: 10px;">
                                            <div class="progress-bar progress-bar-custom rounded-pill" style="width: <?php echo ($total_docs > 0) ? ($aprobados/$total_docs)*100 : 0; ?>%"></div>
                                        </div>
                                        <span class="small fw-bold text-primary"><?php echo $aprobados; ?>/<?php echo $total_docs; ?></span>
                                    </div>
                                </td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-review btn-sm rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalFiles_<?php echo $p['id']; ?>">
                                        <i class="fas fa-eye me-2"></i> Revisar Archivos
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

<?php if (!empty($pendientes)): ?>
    <?php foreach ($pendientes as $p): 
        $docs = $modeloUsuario->obtenerDocumentosUsuario($p['id']);
        $total_docs = count($docs);
        $aprobados = count(array_filter($docs, function($d) { return $d['estado'] == 'Aprobado'; }));
    ?>
        <div class="modal fade" id="modalFiles_<?php echo $p['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="modal-header modal-hdr-custom text-white p-4 border-0">
                        <div class="d-flex align-items-center gap-3">
                            <div class="p-2 bg-white rounded-3">
                                <i class="fas fa-user-check text-dark fa-lg"></i>
                            </div>
                            <div>
                                <h5 class="modal-title fw-bold m-0"><?php echo htmlspecialchars($p['nombre'] . ' ' . $p['apellido']); ?></h5>
                                <small class="opacity-75">Revision de Documentacion Obligatoria</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0 bg-light">
                        <ul class="nav nav-tabs nav-fill bg-white border-bottom-0 pt-3 px-3" id="modalTabs_<?php echo $p['id']; ?>" role="tablist">
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link active fw-bold border-0" data-bs-toggle="tab" data-bs-target="#docs-<?php echo $p['id']; ?>" type="button" role="tab">
                                                        <i class="fas fa-file-alt me-2"></i>Documentos
                                                    </button>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link fw-bold border-0" data-bs-toggle="tab" data-bs-target="#info-<?php echo $p['id']; ?>" type="button" role="tab">
                                    <i class="fas fa-user-edit me-2"></i>Informacion Personal
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="modalTabsContent_<?php echo $p['id']; ?>">
                            <div class="tab-pane fade show active p-4" id="docs-<?php echo $p['id']; ?>" role="tabpanel">
                                <div class="mb-4 d-flex justify-content-between align-items-center">
                                    <h6 class="text-uppercase small fw-bold text-muted mb-0" style="letter-spacing: 1px;">Documentos Entregados</h6>
                                    <span class="badge bg-dark rounded-pill px-3"><?php echo $total_docs; ?> Archivos</span>
                                </div>
                                
                                <div class="document-list gap-3 d-flex flex-column">
                                    <?php foreach ($docs as $d): 
                                        $ext = pathinfo($d['ruta'], PATHINFO_EXTENSION);
                                        $icon = 'fa-file-alt';
                                        $icon_color = '#64748b';
                                        if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                                            $icon = 'fa-file-image';
                                            $icon_color = '#ec4899';
                                        } elseif (strtolower($ext) == 'pdf') {
                                            $icon = 'fa-file-pdf';
                                            $icon_color = '#ef4444';
                                        } elseif (in_array(strtolower($ext), ['doc', 'docx'])) {
                                            $icon = 'fa-file-word';
                                            $icon_color = '#3b82f6';
                                        }
                                    ?>
                                        <div class="doc-item bg-white p-3 rounded-4 border shadow-sm d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="doc-icon-box" style="background: <?php echo $icon_color; ?>15; color: <?php echo $icon_color; ?>;">
                                                    <i class="fas <?php echo $icon; ?> fa-lg"></i>
                                                </div>
                                                <div>
                                                    <a href="javascript:void(0)" onclick="previewFile('<?php echo htmlspecialchars($d['ruta']); ?>', '<?php echo htmlspecialchars($d['nombre_archivo']); ?>')" class="fw-bold text-dark text-decoration-none d-block h6 mb-1">
                                                        <?php echo htmlspecialchars($d['nombre_archivo']); ?>
                                                    </a>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="small text-muted text-uppercase fw-bold" style="font-size: 0.6rem;">Tipo: <?php echo strtoupper($ext); ?></span>
                                                        <span class="status-dot <?php echo $d['estado'] == 'Aprobado' ? 'bg-success' : ($d['estado'] == 'Rechazado' ? 'bg-danger' : 'bg-warning'); ?>"></span>
                                                        <span class="small fw-bold <?php echo $d['estado'] == 'Aprobado' ? 'text-success' : ($d['estado'] == 'Rechazado' ? 'text-danger' : 'text-warning'); ?>">
                                                            <?php echo $d['estado']; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <button onclick="changeDocStatus(this, <?php echo $d['id']; ?>, 'Aprobado', <?php echo $p['id']; ?>)" 
                                                class="btn btn-icon btn-outline-success <?php echo $d['estado'] == 'Aprobado' ? 'active' : ''; ?>" 
                                                title="Aprobar">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button onclick="changeDocStatus(this, <?php echo $d['id']; ?>, 'Rechazado', <?php echo $p['id']; ?>)" 
                                                class="btn btn-icon btn-outline-danger <?php echo $d['estado'] == 'Rechazado' ? 'active' : ''; ?>" 
                                                title="Rechazar">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="mt-4 alert alert-info rounded-4 border-0 small d-flex align-items-center">
                                    <i class="fas fa-info-circle me-3 fa-lg"></i>
                                    <div>Haga clic en el nombre del archivo para verlo directamente aqui. Verifique cada uno antes de la aprobacion final.</div>
                                </div>
                            </div>

                            <div class="tab-pane fade p-4" id="info-<?php echo $p['id']; ?>" role="tabpanel">
                                <form onsubmit="return saveStudentProfile(this, <?php echo $p['id']; ?>, <?php echo $p['id_perfil']; ?>)">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-secondary">Cedula</label>
                                            <input type="text" name="cedula" class="form-control" value="<?php echo htmlspecialchars($p['cedula']); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-secondary">Telefono</label>
                                            <input type="tel" name="telefono" class="form-control" value="<?php echo htmlspecialchars($p['telefono'] ?? ''); ?>">
                                        </div>
                                    </div>

                                    <div class="row g-3 mt-2">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-secondary">Primer Nombre</label>
                                            <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($p['nombre']); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-secondary">Segundo Nombre</label>
                                            <input type="text" name="segundo_nombre" class="form-control" value="<?php echo htmlspecialchars($p['segundo_nombre'] ?? ''); ?>">
                                        </div>
                                    </div>

                                    <div class="row g-3 mt-2">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-secondary">Primer Apellido</label>
                                            <input type="text" name="apellido" class="form-control" value="<?php echo htmlspecialchars($p['apellido']); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-secondary">Segundo Apellido</label>
                                            <input type="text" name="segundo_apellido" class="form-control" value="<?php echo htmlspecialchars($p['segundo_apellido'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <label class="form-label small fw-bold text-secondary">Direccion</label>
                                        <input type="text" name="direccion" class="form-control" value="<?php echo htmlspecialchars($p['direccion'] ?? ''); ?>">
                                    </div>

                                    <div class="mt-4 text-end">
                                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" id="btnSaveProfile_<?php echo $p['id']; ?>">
                                            <span class="spinner-border spinner-border-sm d-none me-1" role="status"></span>
                                            Guardar Cambios
                                        </button>
                                        <span class="small text-success fw-bold ms-2 d-none" id="savedMsg_<?php echo $p['id']; ?>">
                                            <i class="fas fa-check-circle"></i> Guardado
                                        </span>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-white border-0 p-4 justify-content-between">
                        <a href="index.php?page=approve_registration&reject_id=<?php echo $p['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-link text-danger text-decoration-none fw-bold" onclick="event.preventDefault(); (async ()=>{ if(await confirmar('¿Rechazar definitivamente este registro?')) window.location.href=this.href; })()">
                            <i class="fas fa-trash-alt me-2"></i> Rechazar Todo
                        </a>
                        <a href="index.php?page=approve_registration&approve_id=<?php echo $p['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-success rounded-pill px-5 fw-bold shadow-lg">
                            <i class="fas fa-check-double me-2"></i> Aprobar Registro Completo
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="modal fade" id="modalRejectReason" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-danger text-white p-3 border-0">
                <h5 class="modal-title fw-bold m-0"><i class="fas fa-exclamation-circle me-2"></i>Motivo de Rechazo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-3">Seleccione el motivo por el cual el documento no es valido:</p>
                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase" style="font-size: 0.65rem; color: #64748b;">Motivo Frecuente</label>
                    <select id="rejectSelect" class="form-select border-0 bg-light rounded-3 shadow-sm">
                        <option value="">-- Seleccione un motivo --</option>
                        <option value="Imagen poco legible o borrosa">Imagen poco legible o borrosa</option>
                        <option value="Documento vencido">Documento vencido</option>
                        <option value="No corresponde al requisito solicitado">No corresponde al requisito solicitado</option>
                        <option value="Faltan paginas o informacion visible">Faltan paginas o informacion visible</option>
                        <option value="Formato de archivo no permitido">Formato de archivo no permitido</option>
                        <option value="OTRO">Otro (especificar abajo)...</option>
                    </select>
                </div>
                <div class="mb-0">
                    <label class="form-label fw-bold small text-uppercase" style="font-size: 0.65rem; color: #64748b;">Detalles Adicionales</label>
                    <textarea id="rejectText" class="form-control border-0 bg-light rounded-3 shadow-sm" rows="3" placeholder="Escriba aqui los detalles para el estudiante..."></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 p-3 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" id="confirmRejectBtn" class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm">
                    <i class="fas fa-save me-2"></i>Siguiente
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalPreview" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-dark border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="modal-title text-white fw-bold" id="previewTitle">Nombre del Archivo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="previewBody">
            </div>
        </div>
    </div>
</div>

<script>
function changeDocStatus(btn, docId, status, userId) {
    const parent = btn.closest('.doc-item');
    const badge = parent.querySelector('.small.fw-bold');
    const dot = parent.querySelector('.status-dot');
    
    if (status === 'Rechazado') {
        const rejectModal = new bootstrap.Modal(document.getElementById('modalRejectReason'));
        const confirmBtn = document.getElementById('confirmRejectBtn');
        const select = document.getElementById('rejectSelect');
        const textArea = document.getElementById('rejectText');
        
        select.value = "";
        textArea.value = "";
        
        rejectModal.show();
        
        confirmBtn.onclick = () => {
            let observations = select.value === "OTRO" ? textArea.value : select.value;
            if (select.value === "OTRO" && observations.trim() === '') {
                notificar('Debe especificar un motivo si selecciona "Otro".', 'warning');
                return;
            }
            if (select.value === "" && textArea.value.trim() !== "") {
                observations = textArea.value;
            }
            
            if (!observations || observations.trim() === '') {
                notificar('Por favor seleccione o escriba un motivo.', 'warning');
                return;
            }
            
            rejectModal.hide();
            sendDocStatus(btn, docId, status, userId, observations);
        };
        return;
    }

    sendDocStatus(btn, docId, status, userId, '');
}

function sendDocStatus(btn, docId, status, userId, observations) {
    const parent = btn.closest('.doc-item');
    const badge = parent.querySelector('.small.fw-bold');
    const dot = parent.querySelector('.status-dot');
    const buttons = parent.querySelectorAll('button');
    
    buttons.forEach(b => b.disabled = true);

    const csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
    fetch(`index.php?action=approve_document&id=${docId}&status=${status}&obs=${encodeURIComponent(observations)}&ajax=1&csrf_token=${csrfToken}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                badge.innerText = status;
                badge.className = `small fw-bold text-${status === 'Aprobado' ? 'success' : 'danger'}`;
                dot.className = `status-dot bg-${status === 'Aprobado' ? 'success' : 'danger'}`;
                
                buttons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                updateMainProgress(userId);
            }
        })
        .catch(err => notificar('Error al actualizar el estado', 'error'))
        .finally(() => {
            buttons.forEach(b => b.disabled = false);
        });
}

// esta funcion actualiza la barra verde de progreso que indica cuantos papeles estan listos (por ejemplo 4 de 4)
function updateMainProgress(userId) {
    const modal = document.getElementById('modalFiles_' + userId);
    if (!modal) return;
    const docItems = modal.querySelectorAll('.doc-item');
    const total = docItems.length;
    const approved = modal.querySelectorAll('.doc-item .btn-outline-success.active').length;
    const percent = total > 0 ? Math.round((approved / total) * 100) : 0;

    const btn = document.querySelector('button[data-bs-target="#modalFiles_' + userId + '"]');
    if (btn) {
        var row = btn.closest('tr');
        if (row) {
            var bar = row.querySelector('.progress-bar');
            var label = row.querySelector('.small.fw-bold.text-primary');
            if (bar) bar.style.width = percent + '%';
            if (label) label.textContent = approved + '/' + total;
        }
    }
}

// esta funcion guarda correcciones de nombres o cedula del aspirante sin recargar la pagina
function saveStudentProfile(form, userId, idPerfil) {
    const btn = document.getElementById('btnSaveProfile_' + userId);
    const spinner = btn.querySelector('.spinner-border');
    const savedMsg = document.getElementById('savedMsg_' + userId);
    const formData = new FormData(form);
    formData.set('id_perfil', idPerfil);

    spinner.classList.remove('d-none');
    btn.disabled = true;
    savedMsg.classList.add('d-none');

    fetch('index.php?action=update_student_profile&ajax=1', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            savedMsg.classList.remove('d-none');
            setTimeout(() => savedMsg.classList.add('d-none'), 3000);
        } else {
            notificar(data.message || 'Error al guardar', 'error');
        }
    })
    .catch(() => notificar('Error de conexion', 'error'))
    .finally(() => {
        spinner.classList.add('d-none');
        btn.disabled = false;
    });
    return false;
}

// esta funcion abre la ventana modal para mirar el documento en grande (sea foto o archivo pdf)
function previewFile(url, name) {
    const previewBody = document.getElementById('previewBody');
    const previewTitle = document.getElementById('previewTitle');
    const cleanUrl = encodeURI(url);
    const extension = url.split('.').pop().toLowerCase();
    
    previewTitle.innerText = name;
    previewBody.innerHTML = '<div class="text-white text-center p-5"><i class="fas fa-spinner fa-spin fa-3x mb-3"></i><p>Cargando previsualizacion...</p></div>';
    
    const previewModalEl = document.getElementById('modalPreview');
    const bootstrapModal = bootstrap.Modal.getOrCreateInstance(previewModalEl);
    bootstrapModal.show();

    setTimeout(() => {
        // si es imagen la mostramos con etiqueta img
        if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension)) {
            previewBody.innerHTML = `<img src="${cleanUrl}" style="max-width: 100%; max-height: 80vh; object-fit: contain;">`;
        // si es pdf lo mostramos dentro de un visor iframe
        } else if (extension === 'pdf') {
            previewBody.innerHTML = `<iframe src="${cleanUrl}" style="width: 100%; height: 80vh; border: none;"></iframe>`;
        // si es word damos boton de descarga porque el navegador no lo abre directo
        } else if (['doc', 'docx'].includes(extension)) {
            previewBody.innerHTML = `
                <div class="text-white text-center p-5">
                    <i class="fas fa-file-word fa-4x mb-3 text-primary"></i>
                    <h4>Archivo de Word</h4>
                    <p>Los archivos de Word no se pueden previsualizar directamente en el navegador por seguridad.</p>
                    <a href="${cleanUrl}" class="btn btn-primary rounded-pill px-4 mt-3" download>
                        <i class="fas fa-download me-2"></i> Descargar para revisar
                    </a>
                </div>`;
        } else {
            previewBody.innerHTML = `
                <div class="text-white text-center p-5">
                    <i class="fas fa-file fa-4x mb-3 text-muted"></i>
                    <h4>Archivo no soportado</h4>
                    <p>Haga clic en descargar para ver el archivo.</p>
                    <a href="${cleanUrl}" class="btn btn-light rounded-pill px-4 mt-3" download>
                        <i class="fas fa-download me-2"></i> Descargar
                    </a>
                </div>`;
        }
    }, 300);
}
</script>

