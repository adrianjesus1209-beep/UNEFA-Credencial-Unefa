<?php
// este archivo muestra el cronograma y calendario de actividades de la universidad
// aqui se anotan las fechas importantes como inicio de clases, examenes y entrega de notas
if (!isset($_SESSION['id_usuario'])) {
    header('Location: index.php?page=login');
    exit;
}

// buscamos el ano que se esta consultando o usamos el ano actual
$ultimo_anio = $conexion->query("SELECT MAX(anio) FROM cronograma_evento")->fetchColumn();
$anio = intval($_GET['anio'] ?? ($ultimo_anio ?: date('Y')));

$periodos_db = $conexion->query("SELECT id, nombre, estado FROM periodo_academico ORDER BY FIELD(estado, 'Activo','Planificado','Finalizado'), fecha_inicio DESC")->fetchAll(PDO::FETCH_ASSOC);

$eventos = $conexion->prepare("SELECT * FROM cronograma_evento WHERE anio = :anio ORDER BY FIELD(categoria,'CINU','Pregrado','Extensión','Postgrado','PIV'), periodo, fecha_inicio");
$eventos->execute([':anio' => $anio]);
$eventos = $eventos->fetchAll(PDO::FETCH_ASSOC);

$categorias = ['CINU', 'Pregrado', 'Extensión', 'Postgrado', 'PIV'];
$cat_desc = ['CINU'=>'Aspirante, nivelacion obligatoria','Pregrado'=>'Estudiante regular activo','Extensión'=>'Externo, formacion complementaria','Postgrado'=>'Graduado, especializacion superior','PIV'=>'Aspirante, ingreso verano'];
$colores = ['CINU'=>'#0891b2','Pregrado'=>'#7c3aed','Extensión'=>'#e67e22','Postgrado'=>'#c2410c','PIV'=>'#16a34a'];
$periodos_sugeridos = [
    "1-{$anio} Semestre","2-{$anio} Semestre",
    "01-{$anio} Termino","02-{$anio} Termino","03-{$anio} Termino",
    "Periodo 1-{$anio}","Periodo 2-{$anio}","{$anio}"
];
$actividades_sugeridas = [
    'Carga SOA SICEU','Inscripciones','Inicio y fin Actividades academicas',
    'Carga de Notas','Carnetizacion','Variacion','Reparacion',
    'Entrega SOA','Promocion','Pre-inscripciones',
    'Entrega SDA Pasantias','Carga y Entrega de Notas'
];

$archivo_subido = null;
$ruta_archivo = $_SERVER['DOCUMENT_ROOT'] . '/PROYECTO SICEU/UNEFA/public/assets/documentos/cronogramas/';
foreach (['pdf', 'xls', 'xlsx', 'jpg', 'png'] as $ext) {
    $f = "calendario_academico_{$anio}.{$ext}";
    if (file_exists($ruta_archivo . $f)) {
        $archivo_subido = 'public/assets/documentos/cronogramas/' . $f;
        break;
    }
}
?>
<div class="container py-5 mt-5">
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h1 class="display-6 fw-bold mb-1" style="color:var(--coord-primary);"><i class="fas fa-list me-3"></i>Cronograma academico</h1>
            <p class="text-muted mb-0">Gestion manual de eventos del cronograma.</p>
        </div>
        <div class="col-auto d-flex gap-2">
            <a href="index.php?page=gestion_periodos" class="btn btn-sm rounded-pill px-3 fw-bold" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;">
                <i class="fas fa-calendar-alt me-2"></i>Periodos
            </a>
            <a href="index.php?page=<?= in_array($_SESSION['rol_usuario'], ['Coordinador Academico', 'Coordinador', 'Super Administrador', 'Director']) ? 'approve_registration' : 'dashboard' ?>" class="btn btn-sm rounded-pill px-3 fw-bold" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;">
                <i class="fas fa-arrow-left me-2"></i>Volver
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold small text-muted">Ano</label>
                    <form method="GET" action="index.php" class="d-flex gap-2">
                        <input type="hidden" name="page" value="cronograma">
                        <select name="anio" class="form-select" onchange="this.form.submit()">
                            <?php for ($a = 2026; $a <= date('Y') + 5; $a++): ?>
                                <option value="<?= $a ?>" <?= $a === $anio ? 'selected' : '' ?>><?= $a ?></option>
                            <?php endfor; ?>
                        </select>
                        <noscript><button type="submit" class="btn btn-outline-secondary rounded-pill px-3">Ir</button></noscript>
                    </form>
                </div>
                <?php if (in_array($_SESSION['rol_usuario'], ['Coordinador Academico', 'Coordinador', 'Super Administrador', 'Director'])): ?>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold small text-muted">Copiar desde ano anterior</label>
                    <a href="index.php?action=copy_cronograma_anio&origen=<?= $anio - 1 ?>&destino=<?= $anio ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                    class="btn btn-outline-success rounded-pill px-3 fw-bold w-100 text-nowrap"
                    onclick="event.preventDefault(); (async ()=>{ if(await confirmar('¿Copiar eventos de <?= $anio - 1 ?> a <?= $anio ?>?')) window.location.href=this.href; })()">
                        <i class="fas fa-copy me-1"></i>Copiar de <?= $anio - 1 ?>
                    </a>
                </div>
                <div class="col-12 col-md-4 text-end">
                    <button type="button" class="btn rounded-pill px-4 fw-bold shadow-sm w-100" style="background:var(--coord-primary);color:#fff;" data-bs-toggle="modal" data-bs-target="#modalAddEvento">
                        <i class="fas fa-plus me-2"></i>Agregar Evento
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
        <div class="card-header border-0 p-3 d-flex align-items-center gap-2" style="background:#f8f9fa;">
            <i class="fas fa-list me-1 text-muted"></i>
            <h5 class="fw-bold mb-0 flex-grow-1">Eventos — <?= $anio ?></h5>
            <span class="badge bg-dark rounded-pill px-3"><?= count($eventos) ?> registros</span>
            <div class="input-group input-group-sm" style="max-width:200px;">
                <span class="input-group-text bg-white border-0"><i class="fas fa-search text-muted"></i></span>
                <input type="text" id="filtroTabla" class="form-control border-0 bg-light" placeholder="Filtrar..." onkeyup="filtrarEventos()">
            </div>
        </div>
        <div class="d-flex flex-wrap gap-3 mb-3 px-1">
            <?php foreach ($categorias as $cat): ?>
                <span class="d-inline-flex align-items-center gap-2 small fw-medium text-muted">
                    <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:<?= $colores[$cat] ?>;"></span>
                    <?= $cat ?>
                </span>
            <?php endforeach; ?>
        </div>
        <?php if (empty($eventos)): ?>
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3 opacity-25"></i>
                <p class="text-muted fw-bold">No hay eventos para <?= $anio ?>.</p>
                <?php if (in_array($_SESSION['rol_usuario'], ['Coordinador Academico', 'Coordinador', 'Super Administrador', 'Director'])): ?>
                <button class="btn rounded-pill px-4 fw-bold shadow-sm" style="background:var(--coord-primary);color:#fff;" data-bs-toggle="modal" data-bs-target="#modalAddEvento">
                    <i class="fas fa-plus me-2"></i>Crear primer evento
                </button>
                <?php endif; ?>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tablaEventos">
                <thead class="bg-light">
                    <tr class="text-muted small fw-bold">
                        <th class="ps-4 py-3" style="width:3%;"></th>
                        <th style="width:25%;">ACTIVIDAD</th>
                        <th style="width:10%;">CATEGORIA</th>
                        <th style="width:14%;">PERIODO</th>
                        <th style="width:12%;">INICIO</th>
                        <th style="width:12%;">FIN</th>
                        <th style="width:6%;">DIAS</th>
                        <th class="text-end pe-4" style="width:14%;">ACCION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($eventos as $e):
                        $color = $colores[$e['categoria']] ?? '#6b7280';
                        $inicio = new DateTime($e['fecha_inicio']);
                        $fin = new DateTime($e['fecha_fin']);
                        $dias = $inicio->diff($fin)->days + 1;
                    ?>
                        <tr class="evento-row">
                            <td class="ps-4 py-3">
                                <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= $color ?>;"></span>
                            </td>
                            <td>
                                <span class="fw-bold small"><?= htmlspecialchars($e['descripcion']) ?></span>
                            </td>
                            <td>
                                <span class="badge rounded-pill px-3 py-2 fw-medium" style="background:<?= $color ?>15;color:<?= $color ?>;">
                                    <?= htmlspecialchars($e['categoria']) ?>
                                </span>
                                <div class="small text-muted" style="font-size:.6rem;"><?= htmlspecialchars($cat_desc[$e['categoria']] ?? '') ?></div>
                            </td>
                            <td><span class="small fw-medium text-muted"><?= htmlspecialchars($e['periodo']) ?></span></td>
                            <td><span class="badge bg-light text-dark border fw-medium px-3 py-2 rounded-pill small"><?= $inicio->format('d/m/Y') ?></span></td>
                            <td><span class="badge bg-light text-dark border fw-medium px-3 py-2 rounded-pill small"><?= $fin->format('d/m/Y') ?></span></td>
                            <td><span class="badge rounded-pill px-3 py-2 fw-bold" style="background:<?= $color ?>15;color:<?= $color ?>;"><?= $dias ?></span></td>
                            <td class="text-end pe-4">
                                <?php if (in_array($_SESSION['rol_usuario'], ['Coordinador Academico', 'Coordinador', 'Super Administrador', 'Director'])): ?>
                                <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 me-1 fw-medium" title="Editar"
                                        data-bs-toggle="modal" data-bs-target="#modalEditEvento"
                                        data-id="<?= $e['id'] ?>"
                                        data-descripcion="<?= htmlspecialchars($e['descripcion'], ENT_QUOTES) ?>"
                                        data-categoria="<?= $e['categoria'] ?>"
                                        data-periodo="<?= htmlspecialchars($e['periodo'], ENT_QUOTES) ?>"
                                        data-fecha_inicio="<?= $e['fecha_inicio'] ?>"
                                        data-fecha_fin="<?= $e['fecha_fin'] ?>"
                                        data-id_periodo="<?= $e['id_periodo'] ?? '' ?>"
                >
                                    <i class="fas fa-edit me-1"></i>Editar
                                </button>
                                <a href="index.php?action=delete_crono_evento&id=<?= $e['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                class="btn btn-sm btn-outline-danger rounded-pill px-3"
                                onclick="event.preventDefault(); (async ()=>{ if(await confirmar('¿Eliminar «<?= htmlspecialchars($e['descripcion'], ENT_QUOTES) ?>»?')) window.location.href=this.href; })()" title="Eliminar">
                                    <i class="fas fa-trash me-1"></i>
                                </a>
                                <?php else: ?>
                                <span class="small text-muted fw-medium">Solo lectura</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header border-0 p-4 d-flex align-items-center" style="background:#f8f9fa;">
            <i class="fas fa-file-upload me-2 text-muted"></i>
            <h5 class="fw-bold mb-0 flex-grow-1">Calendario Oficial — <?= $anio ?></h5>
            <?php if ($archivo_subido): ?>
                <a href="<?= $archivo_subido ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
                    <i class="fas fa-eye me-1"></i>Ver archivo
                </a>
            <?php endif; ?>
        </div>
        <div class="card-body p-4">
            <?php if (in_array($_SESSION['rol_usuario'], ['Coordinador Academico', 'Coordinador', 'Super Administrador', 'Director'])): ?>
            <form method="POST" action="index.php?action=upload_cronograma_file" enctype="multipart/form-data" class="row g-3 align-items-end">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">Ano</label>
                    <input type="number" name="anio_file" class="form-control" value="<?= $anio ?>" readonly>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-bold small text-muted">Archivo (PDF, Excel, imagen)</label>
                    <input type="file" name="cronograma_file" class="form-control" accept=".pdf,.xls,.xlsx,.jpg,.png" required>
                </div>
                <div class="col-12 col-md-4">
                    <button type="submit" class="btn rounded-pill px-4 fw-bold w-100 shadow-sm" style="background:var(--coord-primary);color:#fff;">
                        <i class="fas fa-upload me-2"></i>Subir Archivo
                    </button>
                </div>
            </form>
            <?php endif; ?>
            <?php if ($archivo_subido): ?>
                <div class="mt-3 p-3 rounded-3 bg-light d-flex align-items-center gap-3">
                    <i class="fas fa-file text-primary fa-lg"></i>
                    <span class="fw-bold">calendario_academico_<?= $anio ?>.<?= pathinfo($archivo_subido, PATHINFO_EXTENSION) ?></span>
                    <span class="badge bg-success rounded-pill ms-auto">Archivo cargado</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (in_array($_SESSION['rol_usuario'], ['Coordinador Academico', 'Coordinador', 'Super Administrador', 'Director'])): ?>
<div class="modal fade" id="modalAddEvento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 p-4" style="background:var(--coord-primary);color:#fff;">
                <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle me-2"></i>Nuevo Evento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?action=add_crono_evento">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="anio" value="<?= $anio ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Categoria</label>
                            <select name="categoria" class="form-select" required>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?= $cat ?>"><?= $cat ?> — <?= htmlspecialchars($cat_desc[$cat]) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label fw-bold small text-muted">Vincular a Periodo (opcional)</label>
                        <select name="id_periodo" class="form-select">
                            <option value="">ninguno</option>
                            <?php foreach ($periodos_db as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?> (<?= $p['estado'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mt-3">
                        <label class="form-label fw-bold small text-muted">Periodo (texto)</label>
                        <select name="periodo_select" id="periodo_select" class="form-select" onchange="togglePeriodoInput(this)">
                            <option value="">Seleccione...</option>
                            <?php foreach ($periodos_sugeridos as $p): ?>
                                <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
                            <?php endforeach; ?>
                            <option value="__otro__">Escribir otro...</option>
                        </select>
                        <input type="text" name="periodo" id="periodo_input" class="form-control" placeholder="Escriba el periodo..." style="display:none;">
                    </div>
                    <div class="mt-3">
                        <label class="form-label fw-bold small text-muted">Actividad</label>
                        <select name="descripcion_select" id="descripcion_select" class="form-select" onchange="toggleDescripcionInput(this)">
                            <option value="">Seleccione...</option>
                            <?php foreach ($actividades_sugeridas as $a): ?>
                                <option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option>
                            <?php endforeach; ?>
                            <option value="__otro__">Escribir otra...</option>
                        </select>
                        <input type="text" name="descripcion" id="descripcion_input" class="form-control" placeholder="Escriba la actividad..." style="display:none;">
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Inicio</label>
                            <input type="date" name="fecha_inicio" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Fin</label>
                            <input type="date" name="fecha_fin" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn rounded-pill px-4 fw-bold shadow-sm" style="background:var(--coord-primary);color:#fff;"><i class="fas fa-save me-2"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditEvento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 p-4" style="background:var(--coord-primary);color:#fff;">
                <h5 class="modal-title fw-bold"><i class="fas fa-edit me-2"></i>Editar Evento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?action=edit_crono_evento">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="id" id="edit_id" value="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Categoria</label>
                            <select name="categoria" id="edit_categoria" class="form-select" required>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?= $cat ?>"><?= $cat ?> — <?= htmlspecialchars($cat_desc[$cat]) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label fw-bold small text-muted">Vincular a Periodo (opcional)</label>
                        <select name="id_periodo" id="edit_id_periodo" class="form-select">
                            <option value="">ninguno</option>
                            <?php foreach ($periodos_db as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?> (<?= $p['estado'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mt-3">
                        <label class="form-label fw-bold small text-muted">Periodo (texto)</label>
                        <select name="edit_periodo_select" id="edit_periodo_select" class="form-select" onchange="toggleEditPeriodo(this)">
                            <option value="">Seleccione...</option>
                            <?php foreach ($periodos_sugeridos as $p): ?>
                                <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
                            <?php endforeach; ?>
                            <option value="__otro__">Escribir otro...</option>
                        </select>
                        <input type="text" name="periodo" id="edit_periodo" class="form-control" placeholder="Escriba el periodo..." style="display:none;">
                    </div>
                    <div class="mt-3">
                        <label class="form-label fw-bold small text-muted">Actividad</label>
                        <select name="edit_descripcion_select" id="edit_descripcion_select" class="form-select" onchange="toggleEditDescripcion(this)">
                            <option value="">Seleccione...</option>
                            <?php foreach ($actividades_sugeridas as $a): ?>
                                <option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option>
                            <?php endforeach; ?>
                            <option value="__otro__">Escribir otra...</option>
                        </select>
                        <input type="text" name="descripcion" id="edit_descripcion" class="form-control" placeholder="Escriba la actividad..." style="display:none;">
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Inicio</label>
                            <input type="date" name="fecha_inicio" id="edit_fecha_inicio" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Fin</label>
                            <input type="date" name="fecha_fin" id="edit_fecha_fin" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn rounded-pill px-4 fw-bold shadow-sm" style="background:var(--coord-primary);color:#fff;"><i class="fas fa-save me-2"></i>Actualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
<?php if (in_array($_SESSION['rol_usuario'], ['Coordinador Academico', 'Coordinador', 'Super Administrador', 'Director'])): ?>
function setEditSelect(selectId, inputId, value) {
    var select = document.getElementById(selectId);
    var input = document.getElementById(inputId);
    var opts = select.options;
    var found = false;
    for (var i = 0; i < opts.length; i++) {
        if (opts[i].value === value) {
            select.value = value;
            found = true;
            break;
        }
    }
    if (found) {
        select.style.display = 'block';
        input.style.display = 'none';
        input.required = false;
    } else if (value) {
        select.value = '__otro__';
        select.style.display = 'none';
        input.style.display = 'block';
        input.value = value;
        input.required = true;
    } else {
        select.style.display = 'block';
        select.value = '';
        input.style.display = 'none';
        input.required = false;
    }
}

document.getElementById('modalEditEvento')?.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    document.getElementById('edit_id').value = button.dataset.id;
    setEditSelect('edit_periodo_select', 'edit_periodo', button.dataset.periodo);
    setEditSelect('edit_descripcion_select', 'edit_descripcion', button.dataset.descripcion);
    document.getElementById('edit_categoria').value = button.dataset.categoria;
    document.getElementById('edit_fecha_inicio').value = button.dataset.fecha_inicio;
    document.getElementById('edit_fecha_fin').value = button.dataset.fecha_fin;
    document.getElementById('edit_id_periodo').value = button.dataset.id_periodo || '';
});

function togglePeriodoInput(select) {
    var input = document.getElementById('periodo_input');
    if (select.value === '__otro__') {
        select.style.display = 'none';
        input.style.display = 'block';
        input.focus();
        input.required = true;
    } else if (select.value) {
        input.style.display = 'none';
        input.required = false;
    }
}

function toggleDescripcionInput(select) {
    var input = document.getElementById('descripcion_input');
    if (select.value === '__otro__') {
        select.style.display = 'none';
        input.style.display = 'block';
        input.focus();
        input.required = true;
    } else if (select.value) {
        input.style.display = 'none';
        input.required = false;
    }
}

function toggleEditPeriodo(select) {
    var input = document.getElementById('edit_periodo');
    if (select.value === '__otro__') {
        select.style.display = 'none';
        input.style.display = 'block';
        input.focus();
        input.required = true;
    } else if (select.value) {
        input.style.display = 'none';
        input.required = false;
    }
}

function toggleEditDescripcion(select) {
    var input = document.getElementById('edit_descripcion');
    if (select.value === '__otro__') {
        select.style.display = 'none';
        input.style.display = 'block';
        input.focus();
        input.required = true;
    } else if (select.value) {
        input.style.display = 'none';
        input.required = false;
    }
}

document.getElementById('modalEditEvento')?.addEventListener('hidden.bs.modal', function () {
    var ps = document.getElementById('edit_periodo_select');
    var pi = document.getElementById('edit_periodo');
    var ds = document.getElementById('edit_descripcion_select');
    var di = document.getElementById('edit_descripcion');
    ps.style.display = 'block'; ps.value = '';
    pi.style.display = 'none'; pi.value = ''; pi.required = false;
    ds.style.display = 'block'; ds.value = '';
    di.style.display = 'none'; di.value = ''; di.required = false;
});

document.getElementById('modalAddEvento')?.addEventListener('hidden.bs.modal', function () {
    var ps = document.getElementById('periodo_select');
    var pi = document.getElementById('periodo_input');
    var ds = document.getElementById('descripcion_select');
    var di = document.getElementById('descripcion_input');
    ps.style.display = 'block'; ps.value = '';
    pi.style.display = 'none'; pi.value = ''; pi.required = false;
    ds.style.display = 'block'; ds.value = '';
    di.style.display = 'none'; di.value = ''; di.required = false;
});
<?php endif; ?>

// esta funcion filtra la tabla de actividades en vivo segun lo que la persona escriba en la cajita de buscar
function filtrarEventos() {
    var q = document.getElementById('filtroTabla').value.toLowerCase();
    document.querySelectorAll('.evento-row').forEach(function(r) {
        r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>

