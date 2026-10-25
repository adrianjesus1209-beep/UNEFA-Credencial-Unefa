<?php
// MODALES PARA CREAR, MODIFICAR Y ELIMINAR HORARIOS DIRECTAMENTE DESDE LA MATRIZ SEMANAL
// Variables opcionales: $materiasLista, $profesoresTitulares
if (!isset($materiasLista) || empty($materiasLista)) {
    $stmtM = $conexion->query("SELECT id, codigo_materia, nombre_materia, semestre, uc FROM materia ORDER BY semestre IS NULL, semestre ASC, nombre_materia ASC");
    $materiasLista = $stmtM ? $stmtM->fetchAll(PDO::FETCH_ASSOC) : [];
}
if (!isset($profesoresTitulares) || empty($profesoresTitulares)) {
    $stmtP = $conexion->query("SELECT u.id, p.nombre, p.apellido, p.cedula FROM usuario u JOIN perfil p ON u.id_perfil = p.id WHERE u.rol IN ('Profesor','Docente') AND u.estado = 'Aprobado' ORDER BY p.apellido, p.nombre");
    $profesoresTitulares = $stmtP ? $stmtP->fetchAll(PDO::FETCH_ASSOC) : [];
}

// Catálogo de secciones UNEFA estándar y registradas en BD (solo activas)
if (!isset($seccionesCatalogOpciones)) {
    $seccionesRegistradas = [];
    if (isset($conexion)) {
        $stmtSecDb = $conexion->query("SELECT DISTINCT nombre_seccion FROM seccion WHERE estado = 'Activa' AND nombre_seccion IS NOT NULL AND nombre_seccion != '' ORDER BY nombre_seccion ASC");
        if ($stmtSecDb) {
            $seccionesRegistradas = $stmtSecDb->fetchAll(PDO::FETCH_COLUMN);
        }
    }

    $seccionesCatalogOpciones = [
        'Ciclo Básico (1ro a 4to Semestre y CINU)' => [
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
            '09S-2629-D1'  => '09S-2629-D1 (9no Semestre · Pasantía / TEG)',
        ],
        'Materias Electivas' => [
            '07S-ET-D1'    => '07S-ET-D1 (7mo Sem · Electivas Técnicas)',
            '07S-ENT-D1'   => '07S-ENT-D1 (7mo Sem · Electivas No Técnicas)',
            '08S-ET-D1'    => '08S-ET-D1 (8vo Sem · Electivas Técnicas)',
            '08S-ENT-D1'   => '08S-ENT-D1 (8vo Sem · Electivas No Técnicas)',
        ]
    ];

    $yaListadas = [];
    foreach ($seccionesCatalogOpciones as $grp) {
        foreach ($grp as $k => $v) $yaListadas[$k] = true;
    }
    $otrasDb = [];
    foreach ($seccionesRegistradas as $sdb) {
        if (!empty($sdb) && !isset($yaListadas[$sdb])) {
            $otrasDb[$sdb] = "Sección $sdb";
        }
    }
    if (!empty($otrasDb)) {
        $seccionesCatalogOpciones['Otras Secciones en Sistema'] = $otrasDb;
    }
}

$currentReturnUrl = htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'index.php?page=gestion_docentes');
?>

<!-- Modal 1: Modificar Horario Existente -->
<div class="modal fade" id="modalModificarHorarioMatriz" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header text-white border-0 py-3" style="background:#003366;">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-clock fa-lg"></i>
                    <h5 class="modal-title fw-bold mb-0">Modificar Horario de Clase</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form action="index.php?action=update_teacher_schedule" method="POST" id="form-edit-horario">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="return_url" value="<?= $currentReturnUrl ?>">
                <input type="hidden" name="id_seccion" id="edit-matriz-id-seccion" value="">

                <div class="modal-body p-4">
                    <!-- Cabecera informativa de la materia -->
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <span class="badge bg-primary rounded-pill mb-1" id="edit-matriz-badge-seccion">Sección</span>
                        <h6 class="fw-bold mb-0 text-dark" id="edit-matriz-nombre-materia">Nombre de Materia</h6>
                        <small class="text-muted" id="edit-matriz-profesor-actual">Profesor Asignado</small>
                    </div>

                    <div class="row g-3 mb-3">
                        <?php if (!function_exists('tieneRolCoordinacion') || tieneRolCoordinacion()): ?>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Código de Sección</label>
                            <select name="nombre_seccion" id="edit-matriz-nombre-seccion" class="form-select" onchange="verificarSeccionCustomModal(this, 'edit-matriz-custom-seccion')">
                                <option value="" disabled>-- Seleccionar Sección --</option>
                                <?php foreach ($seccionesCatalogOpciones as $grupo => $secs): ?>
                                    <optgroup label="<?= htmlspecialchars($grupo) ?>">
                                        <?php foreach ($secs as $val => $lbl): ?>
                                            <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($lbl) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                                <optgroup label="Personalizada">
                                    <option value="__OTRA__">+ Otra sección personalizada...</option>
                                </optgroup>
                            </select>
                            <input type="text" name="nombre_seccion_custom" id="edit-matriz-custom-seccion" class="form-control mt-1" placeholder="Escriba código de sección" style="display:none;">
                        </div>
                        <?php endif; ?>

                        <div class="col-12">
                            <label class="form-label small fw-bold">Día de la Semana</label>
                            <select name="dia_semana" id="edit-matriz-dia" class="form-select" required>
                                <option value="Lunes">Lunes</option>
                                <option value="Martes">Martes</option>
                                <option value="Miercoles">Miércoles</option>
                                <option value="Jueves">Jueves</option>
                                <option value="Viernes">Viernes</option>
                                <option value="Sabado">Sábado</option>
                            </select>
                        </div>

                        <?php if (tieneRolCoordinacion() && !empty($profesoresTitulares)): ?>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Profesor a Cargo</label>
                            <select name="id_docente" id="edit-matriz-id-docente" class="form-select">
                                <?php foreach ($profesoresTitulares as $pt): ?>
                                    <option value="<?= (int)$pt['id'] ?>">
                                        <?= htmlspecialchars($pt['nombre'] . ' ' . $pt['apellido']) ?> (C.I. <?= htmlspecialchars($pt['cedula']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="col-6">
                            <label class="form-label small fw-bold">Hora Entrada (Inicio)</label>
                            <input type="time" name="hora_inicio" id="edit-matriz-hora-inicio" class="form-control" required oninput="calcularPreviewModalEdit()">
                        </div>

                        <div class="col-6">
                            <label class="form-label small fw-bold">Hora Salida (Culminación)</label>
                            <input type="time" name="hora_fin" id="edit-matriz-hora-fin" class="form-control" required>
                        </div>

                        <div class="col-6">
                            <label class="form-label small fw-bold">Tolerancia (Chance Límite)</label>
                            <div class="input-group">
                                <input type="number" name="tolerancia_minutos" id="edit-matriz-tolerancia" class="form-control" min="0" max="120" value="15" required oninput="calcularPreviewModalEdit()">
                                <span class="input-group-text small">min</span>
                            </div>
                        </div>

                        <div class="col-6">
                            <label class="form-label small fw-bold">Aula / Laboratorio</label>
                            <input type="text" name="aula" id="edit-matriz-aula" class="form-control" placeholder="Ej: Aula 01" required>
                        </div>
                    </div>

                    <!-- Resumen en vivo de llegada para el estudiante -->
                    <div class="p-3 rounded-3 border bg-warning-subtle text-dark small" style="border-color:#fde047 !important;">
                        <div class="fw-bold mb-1"><i class="fas fa-user-clock me-1 text-warning"></i>Rango de Llegada para el Estudiante:</div>
                        <div id="edit-matriz-preview-rango">La clase empieza a las --:--. Chance límite hasta las --:-- (+15m).</div>
                    </div>
                </div>

                <div class="modal-footer bg-light border-0 py-3 d-flex justify-content-between align-items-center">
                    <?php if (tieneRolCoordinacion()): ?>
                        <button type="button" class="btn btn-danger text-white rounded-pill px-3 py-1.5 fw-bold btn-sm shadow-xs" onclick="confirmarDesactivarSeccion()">
                            <i class="fas fa-trash-alt me-1"></i>Eliminar Clase
                        </button>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-3 py-1.5 fw-bold btn-sm" data-bs-dismiss="modal">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 py-1.5 fw-bold btn-sm shadow-sm" style="background:#003366;border-color:#003366;">
                            <i class="fas fa-save me-1"></i>Guardar Cambios
                        </button>
                    </div>
                </div>
            </form>

            <?php if (tieneRolCoordinacion()): ?>
                <form id="form-delete-seccion" action="index.php?action=delete_section_schedule" method="POST" style="display:none;">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="return_url" value="<?= $currentReturnUrl ?>">
                    <input type="hidden" name="id_seccion" id="delete-matriz-id-seccion" value="">
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal 2: Crear Nueva Asignacion de Horario (Coordinador) -->
<div class="modal fade" id="modalCrearHorarioMatriz" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header text-white border-0 py-3" style="background:#003366;">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-calendar-plus fa-lg"></i>
                    <h5 class="modal-title fw-bold mb-0">Agregar Clase al Cronograma Semanal</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form action="index.php?action=create_section_schedule" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8 col-12">
                            <label class="form-label small fw-bold">Materia (Ingeniería de Sistemas)</label>
                            <select name="id_materia" class="form-select" required>
                                <?php foreach ($materiasLista as $mat): ?>
                                    <option value="<?= (int)$mat['id'] ?>">
                                        [<?= htmlspecialchars($mat['codigo_materia']) ?>] <?= htmlspecialchars($mat['nombre_materia']) ?> (Sem: <?= $mat['semestre'] ?? 'Electiva' ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4 col-12">
                            <label class="form-label small fw-bold">Código de Sección</label>
                            <select name="nombre_seccion" id="create-modal-nombre-seccion" class="form-select" onchange="verificarSeccionCustomModal(this, 'create-modal-custom-seccion')" required>
                                <option value="" disabled selected>-- Elegir Sección --</option>
                                <?php foreach ($seccionesCatalogOpciones as $grupo => $secs): ?>
                                    <optgroup label="<?= htmlspecialchars($grupo) ?>">
                                        <?php foreach ($secs as $val => $lbl): ?>
                                            <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($lbl) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                                <optgroup label="Personalizada">
                                    <option value="__OTRA__">+ Otra sección personalizada...</option>
                                </optgroup>
                            </select>
                            <input type="text" name="nombre_seccion_custom" id="create-modal-custom-seccion" class="form-control mt-1" placeholder="Ej: 09S-2629-D1" style="display:none;">
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold">Profesor Titular</label>
                            <select name="id_docente" class="form-select" required>
                                <?php foreach ($profesoresTitulares as $pt): ?>
                                    <option value="<?= (int)$pt['id'] ?>">
                                        <?= htmlspecialchars($pt['nombre'] . ' ' . $pt['apellido']) ?> (C.I. <?= htmlspecialchars($pt['cedula']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 col-12">
                            <label class="form-label small fw-bold">Día de la Semana</label>
                            <select name="dia_semana" id="create-matriz-dia" class="form-select" required>
                                <option value="Lunes">Lunes</option>
                                <option value="Martes">Martes</option>
                                <option value="Miercoles">Miércoles</option>
                                <option value="Jueves">Jueves</option>
                                <option value="Viernes">Viernes</option>
                                <option value="Sabado">Sábado</option>
                            </select>
                        </div>

                        <div class="col-md-6 col-12">
                            <label class="form-label small fw-bold">Aula / Laboratorio</label>
                            <input type="text" name="aula" class="form-control" value="Aula 01" required>
                        </div>

                        <div class="col-md-4 col-6">
                            <label class="form-label small fw-bold">Hora Entrada</label>
                            <input type="time" name="hora_inicio" id="create-matriz-hora-inicio" class="form-control" value="07:45" required>
                        </div>

                        <div class="col-md-4 col-6">
                            <label class="form-label small fw-bold">Hora Salida</label>
                            <input type="time" name="hora_fin" id="create-matriz-hora-fin" class="form-control" value="10:00" required>
                        </div>

                        <div class="col-md-4 col-12">
                            <label class="form-label small fw-bold">Tolerancia</label>
                            <div class="input-group">
                                <input type="number" name="tolerancia_minutos" class="form-control" value="15" min="0" max="120" required>
                                <span class="input-group-text small">min</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light border-0 py-3">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-3 fw-bold btn-sm" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold btn-sm shadow-sm" style="background:#003366;border-color:#003366;">
                        <i class="fas fa-plus-circle me-1"></i>Registrar en Horario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function verificarSeccionCustomModal(selectEl, customInputId) {
    const inputCustom = document.getElementById(customInputId);
    if (!inputCustom) return;
    if (selectEl.value === '__OTRA__') {
        inputCustom.style.display = 'block';
        inputCustom.required = true;
        inputCustom.focus();
    } else {
        inputCustom.style.display = 'none';
        inputCustom.required = false;
        inputCustom.value = '';
    }
}

function abrirModalEditarHorario(sec) {
    if (!sec) return;
    document.getElementById('edit-matriz-id-seccion').value = sec.id || '';
    document.getElementById('edit-matriz-badge-seccion').textContent = sec.nombre_seccion || 'Sección';
    document.getElementById('edit-matriz-nombre-materia').textContent = (sec.codigo_materia ? '[' + sec.codigo_materia + '] ' : '') + (sec.nombre_materia || '');
    document.getElementById('edit-matriz-profesor-actual').textContent = 'Docente: ' + (sec.titular_nombre || 'Docente asignado');
    
    // Sincronizar sección en select
    const selSec = document.getElementById('edit-matriz-nombre-seccion');
    const customSec = document.getElementById('edit-matriz-custom-seccion');
    if (selSec && sec.nombre_seccion) {
        let found = false;
        for (let i = 0; i < selSec.options.length; i++) {
            if (selSec.options[i].value === sec.nombre_seccion) {
                selSec.selectedIndex = i;
                found = true;
                break;
            }
        }
        if (!found) {
            selSec.value = '__OTRA__';
            if (customSec) {
                customSec.style.display = 'block';
                customSec.value = sec.nombre_seccion;
            }
        } else {
            if (customSec) {
                customSec.style.display = 'none';
                customSec.value = '';
            }
        }
    }

    // Normalizar día
    const diaVal = sec.dia_semana || 'Lunes';
    const selDia = document.getElementById('edit-matriz-dia');
    if (selDia) {
        for (let i = 0; i < selDia.options.length; i++) {
            if (selDia.options[i].value.toLowerCase().includes(diaVal.toLowerCase().substring(0, 3))) {
                selDia.selectedIndex = i;
                break;
            }
        }
    }

    const selDoc = document.getElementById('edit-matriz-id-docente');
    if (selDoc && sec.id_docente) {
        selDoc.value = sec.id_docente;
    }

    const hi = (sec.hora_inicio || '07:45').substring(0, 5);
    const hf = (sec.hora_fin || '10:00').substring(0, 5);
    document.getElementById('edit-matriz-hora-inicio').value = hi;
    document.getElementById('edit-matriz-hora-fin').value = hf;
    document.getElementById('edit-matriz-tolerancia').value = sec.tolerancia_minutos !== undefined ? sec.tolerancia_minutos : 15;
    document.getElementById('edit-matriz-aula').value = sec.aula || 'Aula 01';

    const delId = document.getElementById('delete-matriz-id-seccion');
    if (delId) delId.value = sec.id || '';

    calcularPreviewModalEdit();

    const m = new bootstrap.Modal(document.getElementById('modalModificarHorarioMatriz'));
    m.show();
}

function abrirModalNuevoHorario() {
    // Si hay una sección seleccionada en la matriz, sincronizarla con el select
    const secFiltro = document.getElementById('filtro-seccion-matriz')?.value;
    const selSec = document.getElementById('create-modal-nombre-seccion');
    const customSec = document.getElementById('create-modal-custom-seccion');
    if (selSec && secFiltro && secFiltro !== '__TODAS__') {
        let found = false;
        for (let i = 0; i < selSec.options.length; i++) {
            if (selSec.options[i].value === secFiltro) {
                selSec.selectedIndex = i;
                found = true;
                break;
            }
        }
        if (!found) {
            selSec.value = '__OTRA__';
            if (customSec) {
                customSec.style.display = 'block';
                customSec.value = secFiltro;
            }
        } else {
            if (customSec) {
                customSec.style.display = 'none';
                customSec.value = '';
            }
        }
    }

    const m = new bootstrap.Modal(document.getElementById('modalCrearHorarioMatriz'));
    m.show();
}

function abrirModalAsignacionManual(dia, horaIni, horaFin) {
    const selDia = document.getElementById('create-matriz-dia');
    if (selDia && dia) {
        for (let i = 0; i < selDia.options.length; i++) {
            if (selDia.options[i].value.toLowerCase().includes(dia.toLowerCase().substring(0, 3))) {
                selDia.selectedIndex = i;
                break;
            }
        }
    }
    const hi = document.getElementById('create-matriz-hora-inicio');
    if (hi && horaIni) hi.value = horaIni;
    const hf = document.getElementById('create-matriz-hora-fin');
    if (hf && horaFin) hf.value = horaFin;

    // Sincronizar sección seleccionada de la matriz
    const secFiltro = document.getElementById('filtro-seccion-matriz')?.value;
    const selSec = document.getElementById('create-modal-nombre-seccion');
    const customSec = document.getElementById('create-modal-custom-seccion');
    if (selSec && secFiltro && secFiltro !== '__TODAS__') {
        let found = false;
        for (let i = 0; i < selSec.options.length; i++) {
            if (selSec.options[i].value === secFiltro) {
                selSec.selectedIndex = i;
                found = true;
                break;
            }
        }
        if (!found) {
            selSec.value = '__OTRA__';
            if (customSec) {
                customSec.style.display = 'block';
                customSec.value = secFiltro;
            }
        } else {
            if (customSec) {
                customSec.style.display = 'none';
                customSec.value = '';
            }
        }
    }

    const m = new bootstrap.Modal(document.getElementById('modalCrearHorarioMatriz'));
    m.show();
}

function abrirModalSlotVacio(dia, horaIni, horaFin) {
    abrirModalAsignacionManual(dia, horaIni, horaFin);
}

function calcularPreviewModalEdit() {
    const hi = document.getElementById('edit-matriz-hora-inicio')?.value || '07:45';
    const tol = parseInt(document.getElementById('edit-matriz-tolerancia')?.value || '15', 10);
    const prevEl = document.getElementById('edit-matriz-preview-rango');
    if (!prevEl) return;

    const parts = hi.split(':');
    const d = new Date(2000, 0, 1, parseInt(parts[0], 10), parseInt(parts[1], 10));
    const dLim = new Date(d.getTime() + tol * 60000);

    const format12 = (dt) => {
        let h = dt.getHours();
        const m = dt.getMinutes();
        const ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        return (h < 10 ? '0' + h : h) + ':' + (m < 10 ? '0' + m : m) + ' ' + ampm;
    };

    prevEl.innerHTML = `La clase empieza a las <strong>${format12(d)}</strong>. Chance límite para marcar a tiempo hasta las <strong>${format12(dLim)}</strong> (+${tol} min).`;
}

function confirmarDesactivarSeccion() {
    const matNom = document.getElementById('edit-matriz-nombre-materia')?.textContent || 'esta materia';
    if (confirm(`¿Está seguro de que desea eliminar ${matNom} del horario semanal?`)) {
        document.getElementById('form-delete-seccion')?.submit();
    }
}
</script>
