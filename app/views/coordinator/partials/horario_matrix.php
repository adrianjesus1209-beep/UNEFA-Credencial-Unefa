<?php
// MATRIZ DE CRONOGRAMA SEMANAL (UNEFA - HORARIOS EN BLOQUES DE 45 MINUTOS)
// Variables esperadas:
// $seccionesMatriz: array de secciones con [id, id_materia, nombre_materia, codigo_materia, nombre_seccion, dia_semana, hora_inicio, hora_fin, aula, titular_nombre, tolerancia_minutos, semestre]
// $esEditable: bool (si es true, permite hacer clic para modificar horario)
// $rolVista: string ('Coordinador', 'Profesor', 'Estudiante')
// $tituloMatriz: string opcional
// $sinBordeCard: bool opcional (evita doble borde de card si ya está dentro de una)
// $ocultarTituloCard: bool opcional (evita duplicar encabezado si el padre ya tiene uno)

if (!isset($seccionesMatriz)) {
    $seccionesMatriz = [];
}
if (!isset($esEditable)) {
    $esEditable = false;
}
if (!isset($rolVista)) {
    $rolVista = 'Coordinador';
}
if (!isset($tituloMatriz)) {
    $tituloMatriz = 'Cronograma Semanal de Clases';
}

// Bloques de 45 minutos estándar UNEFA (Diurno 07:45 - 13:00 y Vespertino hasta 17:30)
$bloquesHorarios = [
    ['ini' => '07:00', 'fin' => '07:45', 'label' => '07:00 - 07:45', 'vespertino' => false],
    ['ini' => '07:45', 'fin' => '08:30', 'label' => '07:45 - 08:30', 'vespertino' => false],
    ['ini' => '08:30', 'fin' => '09:15', 'label' => '08:30 - 09:15', 'vespertino' => false],
    ['ini' => '09:15', 'fin' => '10:00', 'label' => '09:15 - 10:00', 'vespertino' => false],
    ['ini' => '10:00', 'fin' => '10:45', 'label' => '10:00 - 10:45', 'vespertino' => false],
    ['ini' => '10:45', 'fin' => '11:30', 'label' => '10:45 - 11:30', 'vespertino' => false],
    ['ini' => '11:30', 'fin' => '12:15', 'label' => '11:30 - 12:15', 'vespertino' => false],
    ['ini' => '12:15', 'fin' => '13:00', 'label' => '12:15 - 13:00', 'vespertino' => false],
    ['ini' => '13:00', 'fin' => '13:45', 'label' => '13:00 - 13:45', 'vespertino' => true],
    ['ini' => '13:45', 'fin' => '14:30', 'label' => '13:45 - 14:30', 'vespertino' => true],
    ['ini' => '14:30', 'fin' => '15:15', 'label' => '14:30 - 15:15', 'vespertino' => true],
    ['ini' => '15:15', 'fin' => '16:00', 'label' => '15:15 - 16:00', 'vespertino' => true],
    ['ini' => '16:00', 'fin' => '16:45', 'label' => '16:00 - 16:45', 'vespertino' => true],
    ['ini' => '16:45', 'fin' => '17:30', 'label' => '16:45 - 17:30', 'vespertino' => true],
];

// Cabeceras de días con diseño sobrio y profesional
$diasSemanales = [
    'Lunes'     => ['nombre' => 'Lunes',     'bg' => '#f8fafc', 'color' => '#1e293b'],
    'Martes'    => ['nombre' => 'Martes',    'bg' => '#f8fafc', 'color' => '#1e293b'],
    'Miercoles' => ['nombre' => 'Miércoles', 'bg' => '#f8fafc', 'color' => '#1e293b'],
    'Jueves'    => ['nombre' => 'Jueves',    'bg' => '#f8fafc', 'color' => '#1e293b'],
    'Viernes'   => ['nombre' => 'Viernes',   'bg' => '#f8fafc', 'color' => '#1e293b'],
    'Sabado'    => ['nombre' => 'Sábado',    'bg' => '#f8fafc', 'color' => '#1e293b'],
];

// Paleta armónica de colores (fondo suave, texto contrastante y acento lateral)
$paletaMaterias = [
    ['border' => '#2563eb', 'bg' => '#eff6ff', 'text' => '#1e40af', 'badge_bg' => '#dbeafe', 'badge_color' => '#1d4ed8'],
    ['border' => '#7c3aed', 'bg' => '#f5f3ff', 'text' => '#5b21b6', 'badge_bg' => '#ede9fe', 'badge_color' => '#6d28d9'],
    ['border' => '#059669', 'bg' => '#f0fdf4', 'text' => '#065f46', 'badge_bg' => '#dcfce7', 'badge_color' => '#047857'],
    ['border' => '#d97706', 'bg' => '#fffbeb', 'text' => '#92400e', 'badge_bg' => '#fef3c7', 'badge_color' => '#b45309'],
    ['border' => '#0891b2', 'bg' => '#ecfeff', 'text' => '#155e75', 'badge_bg' => '#cffafe', 'badge_color' => '#0e7490'],
    ['border' => '#e11d48', 'bg' => '#fff1f2', 'text' => '#9f1239', 'badge_bg' => '#ffe4e6', 'badge_color' => '#be123c'],
    ['border' => '#4f46e5', 'bg' => '#eef2ff', 'text' => '#3730a3', 'badge_bg' => '#e0e7ff', 'badge_color' => '#4338ca'],
    ['border' => '#0284c7', 'bg' => '#f0f9ff', 'text' => '#075985', 'badge_bg' => '#e0f2fe', 'badge_color' => '#0369a1'],
    ['border' => '#65a30d', 'bg' => '#f7fee7', 'text' => '#3f6212', 'badge_bg' => '#ecfccb', 'badge_color' => '#4d7c0f'],
    ['border' => '#c026d3', 'bg' => '#fdf4ff', 'text' => '#86198f', 'badge_bg' => '#fae8ff', 'badge_color' => '#a21caf'],
];

// Asignar color consistente por materia
$colorMap = [];
$cIdx = 0;
foreach ($seccionesMatriz as $s) {
    $mKey = $s['id_materia'] ?? ($s['codigo_materia'] ?? $s['nombre_materia']);
    if (!isset($colorMap[$mKey])) {
        $colorMap[$mKey] = $paletaMaterias[$cIdx % count($paletaMaterias)];
        $cIdx++;
    }
}

// Extraer secciones únicas para el selector
$seccionesUnicas = [];
foreach ($seccionesMatriz as $s) {
    $secNom = trim($s['nombre_seccion'] ?? '');
    if ($secNom !== '') {
        if (!isset($seccionesUnicas[$secNom])) {
            $semVal = $s['semestre'] ?? null;
            $semTxt = ($semVal !== null && $semVal !== '') ? ($semVal == 0 ? 'CINU' : "Sem {$semVal}") : 'Electiva';
            
            $weight = 100;
            if ($secNom === 'CINU' || strpos($secNom, 'CINU') === 0) {
                $weight = 0;
            } elseif (preg_match('/^0?([0-9]+)S/', $secNom, $mNum)) {
                $weight = intval($mNum[1]) * 10;
            }
            if (strpos($secNom, 'ET') !== false) $weight += 4;
            if (strpos($secNom, 'ENT') !== false) $weight += 5;

            $seccionesUnicas[$secNom] = [
                'nombre'   => $secNom,
                'semestre' => $semTxt,
                'weight'   => $weight,
                'total'    => 0
            ];
        }
        $seccionesUnicas[$secNom]['total']++;
    }
}

uasort($seccionesUnicas, function($a, $b) {
    if ($a['weight'] !== $b['weight']) return $a['weight'] <=> $b['weight'];
    return strcmp($a['nombre'], $b['nombre']);
});

// Sección predeterminada
$seccionDefecto = '__TODAS__';
if (isset($seccionesUnicas['01S-2629-D1'])) {
    $seccionDefecto = '01S-2629-D1';
} elseif (count($seccionesUnicas) === 1) {
    $seccionDefecto = array_key_first($seccionesUnicas);
} elseif (!empty($seccionesUnicas)) {
    $seccionDefecto = array_key_first($seccionesUnicas);
}

// Normalizador
if (!function_exists('normalizarDiaClave')) {
    function normalizarDiaClave($d) {
        $d = mb_strtolower(trim($d), 'UTF-8');
        if (strpos($d, 'lun') !== false) return 'Lunes';
        if (strpos($d, 'mar') !== false) return 'Martes';
        if (strpos($d, 'mie') !== false || strpos($d, 'mié') !== false) return 'Miercoles';
        if (strpos($d, 'jue') !== false) return 'Jueves';
        if (strpos($d, 'vie') !== false) return 'Viernes';
        if (strpos($d, 'sab') !== false || strpos($d, 'sáb') !== false) return 'Sabado';
        return ucfirst($d);
    }
}
?>

<style>
/* ============================================================ */
/* TABLA DE CRONOGRAMA SEMANAL UNEFA - DISEÑO COMPACTO Y NÍTIDO */
/* ============================================================ */
.unefa-timetable-wrapper {
    overflow-x: auto;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #ffffff;
}

.unefa-timetable-table {
    border-collapse: collapse;
    width: 100%;
    table-layout: fixed;
    background: #ffffff;
    font-size: 0.68rem;
}

.unefa-timetable-table th, 
.unefa-timetable-table td {
    border: 1px solid #e2e8f0;
    padding: 0;
    text-align: center;
    vertical-align: middle;
}

/* Cabeceras de días */
.unefa-timetable-header-col {
    background: #f8fafc;
    color: #475569;
    font-weight: 700;
    font-size: 0.64rem;
    padding: 5px 3px;
    letter-spacing: 0.3px;
    text-transform: uppercase;
    width: 80px;
    border: 1px solid #e2e8f0;
}

.unefa-timetable-header-day {
    background: #f8fafc;
    color: #1e293b;
    font-weight: 700;
    font-size: 0.68rem;
    padding: 5px 4px;
    letter-spacing: 0.2px;
    width: calc((100% - 80px) / 6);
    border: 1px solid #e2e8f0;
    border-bottom: 2px solid #cbd5e1;
}

.unefa-timetable-table tbody tr {
    height: 30px;
}

.unefa-timetable-slot-label {
    background: #f8fafc;
    font-weight: 700;
    font-size: 0.58rem;
    color: #475569;
    padding: 2px 4px;
    white-space: nowrap;
    line-height: 1.15;
    user-select: none;
    border-right: 2px solid #e2e8f0;
    text-align: center;
}

/* Celda individual compacta y rítmica */
.unefa-timetable-cell {
    height: 30px;
    min-height: 30px;
    position: relative;
    background: #ffffff;
    transition: background 0.12s ease;
}

.unefa-timetable-cell.is-empty {
    cursor: <?= $esEditable ? 'pointer' : 'default' ?>;
}

.unefa-timetable-cell.is-empty:hover {
    background: <?= $esEditable ? '#f0fdf4' : '#ffffff' ?>;
}

/* SELECCIÓN MANUAL INTERACTIVA ESTILO SICEU */
.unefa-timetable-cell.is-selection-start {
    background: #bfdbfe !important;
    border: 2px solid #1d4ed8 !important;
    position: relative;
    z-index: 5;
}

.unefa-timetable-cell.is-selection-start::before {
    content: '1. Inicio';
    position: absolute;
    top: 1px;
    left: 3px;
    font-size: 0.54rem;
    font-weight: 800;
    color: #1e40af;
    background: rgba(255, 255, 255, 0.9);
    padding: 1px 4px;
    border-radius: 3px;
    z-index: 6;
}

.unefa-timetable-cell.is-selection-range {
    background: #dbeafe !important;
    border: 2px dashed #2563eb !important;
    position: relative;
    z-index: 4;
}

.unefa-timetable-cell.is-selection-range::before {
    content: 'Seleccionado';
    position: absolute;
    top: 1px;
    left: 3px;
    font-size: 0.52rem;
    font-weight: 700;
    color: #1d4ed8;
    background: rgba(255, 255, 255, 0.9);
    padding: 1px 3px;
    border-radius: 3px;
    z-index: 5;
}

/* Tarjeta de Materia: Diseño armónico compacto */
.unefa-timetable-block {
    height: calc(100% - 4px);
    width: calc(100% - 4px);
    margin: 2px;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    align-items: stretch;
    padding: 3px 6px;
    border-radius: 6px;
    font-weight: 600;
    line-height: 1.15;
    cursor: <?= $esEditable ? 'pointer' : 'default' ?>;
    border: 1px solid #e2e8f0;
    border-left-width: 3px !important;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    transition: transform 0.12s ease, box-shadow 0.12s ease;
    user-select: none;
    overflow: hidden;
    box-sizing: border-box;
    text-align: left;
}

.unefa-timetable-block:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(0,0,0,0.08);
}

.unefa-timetable-block .subject-title {
    font-weight: 800;
    font-size: 0.74rem;
    text-transform: uppercase;
    letter-spacing: 0.2px;
    line-height: 1.2;
    margin-bottom: 2px;
    text-align: center;
    width: 100%;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.unefa-timetable-block .subject-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 3px;
    margin-top: 2px;
}

.unefa-timetable-block .subject-teacher {
    font-size: 0.58rem;
    font-weight: 600;
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    text-align: center;
    width: 100%;
}

/* Mini chip cuando hay múltiples secciones en una celda consolidada */
.unefa-chip-mini {
    display: block;
    border-radius: 4px;
    padding: 2px 4px;
    margin: 1px 0;
    font-size: 0.56rem;
    line-height: 1.15;
    font-weight: 600;
    background: #ffffff;
    color: #1e293b;
    border: 1px solid #e2e8f0;
    border-left: 3px solid;
    text-overflow: ellipsis;
    overflow: hidden;
    white-space: nowrap;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    cursor: <?= $esEditable ? 'pointer' : 'default' ?>;
}

@media print {
    .d-print-none, .btn, .card-header, nav, footer, .welcome-banner { display: none !important; }
    .unefa-timetable-wrapper { max-height: none !important; overflow: visible !important; border: 1px solid #000 !important; }
    .unefa-timetable-table { border: 1px solid #000 !important; width: 100% !important; font-size: 7pt !important; }
    .unefa-timetable-table th, .unefa-timetable-table td { border: 1px solid #000 !important; }
    .unefa-timetable-block { -webkit-print-color-adjust: exact; print-color-adjust: exact; box-shadow: none !important; }
}
</style>

<div id="card-cronograma-matrix" class="<?= empty($sinBordeCard) ? 'card border shadow-sm rounded-4 overflow-hidden mb-3' : 'unefa-cronograma-wrapper mb-3' ?>" style="border-color:#e2e8f0 !important;">
    <?php if (empty($ocultarTituloCard)): ?>
        <div class="card-header bg-white py-3 px-3.5 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-2.5">
                <span class="rounded-circle d-flex align-items-center justify-content-center text-white shadow-xs" style="width:34px;height:34px;background:#003366;font-size:0.85rem;">
                    <i class="fas fa-calendar-alt"></i>
                </span>
                <div>
                    <h6 class="fw-bold mb-0 text-dark" style="font-size:0.98rem; letter-spacing:-0.2px;"><?= htmlspecialchars($tituloMatriz) ?></h6>
                    <small class="text-muted" style="font-size:0.75rem;">Matriz oficial en bloques de 45 min · Lunes a Sábado (07:00 a 17:30)</small>
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2 d-print-none">
                <button type="button" class="btn btn-sm btn-outline-dark rounded-pill px-3 py-1.5 fw-bold shadow-xs" onclick="window.print()" style="font-size:0.76rem;">
                    <i class="fas fa-print me-1.5"></i>Imprimir Horario
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Barra de Filtros y Acciones Organizada y Limpia -->
    <div class="px-3 py-2.5 bg-light border-bottom d-print-none">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2.5">
            <!-- 1. Izquierda: Filtro de Sección -->
            <div class="d-flex align-items-center gap-2 flex-grow-1" style="max-width: 480px;">
                <div class="input-group input-group-sm rounded-pill overflow-hidden border bg-white shadow-xs w-100">
                    <span class="input-group-text bg-white border-0 text-primary px-2.5">
                        <i class="fas fa-layer-group"></i>
                    </span>
                    <select id="filtro-seccion-matriz" class="form-select form-select-sm border-0 fw-bold text-dark bg-white" onchange="renderizarMatrizHorario()" style="font-size:0.78rem;">
                        <?php if (count($seccionesUnicas) > 1): ?>
                            <option value="__TODAS__" <?= $seccionDefecto === '__TODAS__' ? 'selected' : '' ?>>Todas las Secciones (Vista Consolidada)</option>
                        <?php endif; ?>
                        <?php 
                        $gruposSelect = [
                            'Ciclo Básico (1ro a 4to Semestre y CINU)' => [],
                            'Ciclo Profesional (5to a 9no Semestre)' => [],
                            'Materias Electivas' => []
                        ];
                        foreach ($seccionesUnicas as $secKey => $secVal) {
                            if (strpos($secKey, 'ET') !== false || strpos($secKey, 'ENT') !== false) {
                                $gruposSelect['Materias Electivas'][$secKey] = $secVal;
                            } elseif ($secVal['weight'] >= 50) {
                                $gruposSelect['Ciclo Profesional (5to a 9no Semestre)'][$secKey] = $secVal;
                            } else {
                                $gruposSelect['Ciclo Básico (1ro a 4to Semestre y CINU)'][$secKey] = $secVal;
                            }
                        }
                        foreach ($gruposSelect as $grpNom => $itemsGrp):
                            if (empty($itemsGrp)) continue;
                        ?>
                            <optgroup label="<?= htmlspecialchars($grpNom) ?>">
                                <?php foreach ($itemsGrp as $secKey => $secVal): ?>
                                    <option value="<?= htmlspecialchars($secKey) ?>" <?= $seccionDefecto === $secKey ? 'selected' : '' ?>>
                                        Sección: <?= htmlspecialchars($secKey) ?><?= !empty($secVal['semestre']) ? " ({$secVal['semestre']})" : '' ?> · <?= $secVal['total'] ?> clases
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- 2. Derecha: Buscador y Contador de Clases -->
            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                <div class="input-group input-group-sm rounded-pill overflow-hidden border bg-white shadow-xs" style="width: 185px;">
                    <span class="input-group-text bg-white border-0 px-2 text-muted"><i class="fas fa-search"></i></span>
                    <input type="text" id="filtro-texto-matriz" class="form-control form-control-sm border-0 bg-white" placeholder="Buscar materia..." onkeyup="renderizarMatrizHorario()" style="font-size:0.75rem;">
                </div>

                <span class="badge bg-white text-dark border px-2.5 py-1.5 rounded-pill shadow-xs fw-bold" id="badge-contador-matriz" style="font-size:0.72rem;">
                    <i class="fas fa-book-open me-1 text-primary"></i><span id="contador-secciones-txt"><?= count($seccionesMatriz) ?></span> clases
                </span>
            </div>
        </div>
    </div>

    <!-- Banner interactivo de selección manual de bloques (Estilo SICEU) -->
    <div id="banner-seleccion-horario" class="alert alert-primary py-2 px-3 mx-3 mt-2 mb-1 rounded-3 d-none align-items-center justify-content-between shadow-xs border-primary-subtle" style="background:#eff6ff; font-size:0.75rem;">
        <div class="d-flex align-items-center gap-2">
            <span class="spinner-grow spinner-grow-sm text-primary" role="status" style="width:0.75rem;height:0.75rem;"></span>
            <span id="txt-banner-seleccion">Paso 1: Bloque de inicio fijado. Haz clic en la casilla de abajo donde termina la clase.</span>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-xs btn-outline-danger rounded-pill px-2.5 py-0.5 fw-bold" onclick="cancelarSeleccionManual()">
                <i class="fas fa-times me-1"></i>Cancelar
            </button>
        </div>
    </div>

    <!-- Tabla Matriz Estilo Cronograma Semanal Oficial UNEFA -->
    <div class="unefa-timetable-wrapper p-0" id="wrapper-tabla-horario">
        <table class="unefa-timetable-table" id="tabla-cronograma-semanal">
            <thead>
                <tr>
                    <th class="unefa-timetable-header-col">Hora</th>
                    <?php foreach ($diasSemanales as $diaClave => $dInfo): ?>
                        <th class="unefa-timetable-header-day col-dia-<?= $diaClave ?>" id="th-dia-<?= $diaClave ?>">
                            <?= htmlspecialchars($dInfo['nombre']) ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody id="tbody-cronograma-matriz">
                <!-- Se renderiza dinámicamente vía JavaScript -->
            </tbody>
        </table>
    </div>

    <!-- Pie Informativo Compacto -->
    <div class="bg-white py-2 px-3 border-top text-muted d-flex flex-wrap justify-content-between align-items-center" style="font-size:0.68rem;">
        <span><i class="fas fa-info-circle me-1 text-primary"></i>Selección manual de bloques: haz clic en el bloque inicial y luego en el bloque final para asignar la materia.</span>
        <?php if ($esEditable): ?>
            <span class="text-primary fw-bold"><i class="fas fa-mouse-pointer me-1"></i>Clic en cualquier clase registrada para modificar o eliminar.</span>
        <?php else: ?>
            <span><i class="fas fa-check me-1 text-success"></i>Horario académico oficial sincronizado.</span>
        <?php endif; ?>
    </div>
</div>

<script>
// Datos globales inyectados desde PHP
window.__seccionesMatrizData = <?= json_encode($seccionesMatriz, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?> || [];
window.__esEditableMatriz    = <?= $esEditable ? 'true' : 'false' ?>;
window.__colorMapMatriz      = <?= json_encode($colorMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?> || {};

window.__bloquesHorarios = [
    { ini: '07:00', fin: '07:45', label: '07:00 - 07:45', vespertino: false },
    { ini: '07:45', fin: '08:30', label: '07:45 - 08:30', vespertino: false },
    { ini: '08:30', fin: '09:15', label: '08:30 - 09:15', vespertino: false },
    { ini: '09:15', fin: '10:00', label: '09:15 - 10:00', vespertino: false },
    { ini: '10:00', fin: '10:45', label: '10:00 - 10:45', vespertino: false },
    { ini: '10:45', fin: '11:30', label: '10:45 - 11:30', vespertino: false },
    { ini: '11:30', fin: '12:15', label: '11:30 - 12:15', vespertino: false },
    { ini: '12:15', fin: '13:00', label: '12:15 - 13:00', vespertino: false },
    { ini: '13:00', fin: '13:45', label: '13:00 - 13:45', vespertino: true },
    { ini: '13:45', fin: '14:30', label: '13:45 - 14:30', vespertino: true },
    { ini: '14:30', fin: '15:15', label: '14:30 - 15:15', vespertino: true },
    { ini: '15:15', fin: '16:00', label: '15:15 - 16:00', vespertino: true },
    { ini: '16:00', fin: '16:45', label: '16:00 - 16:45', vespertino: true },
    { ini: '16:45', fin: '17:30', label: '16:45 - 17:30', vespertino: true }
];

window.__diasSemanales = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'];
window.__bloquesVisiblesActuales = [];
window.__seleccionManual = null; // Estado de la selección manual estilo SICEU

function horaAMinutosJS(horaStr) {
    if (!horaStr) return 0;
    const p = horaStr.split(':');
    return (parseInt(p[0], 10) * 60) + parseInt(p[1] || 0, 10);
}

function normalizarDiaJS(d) {
    if (!d) return '';
    d = d.toLowerCase().trim();
    if (d.includes('lun')) return 'Lunes';
    if (d.includes('mar')) return 'Martes';
    if (d.includes('mie') || d.includes('mié')) return 'Miercoles';
    if (d.includes('jue')) return 'Jueves';
    if (d.includes('vie')) return 'Viernes';
    if (d.includes('sab') || d.includes('sáb')) return 'Sabado';
    return d.charAt(0).toUpperCase() + d.slice(1);
}

// ============================================================
// SELECCIÓN MANUAL DE BLOQUES ESTILO SICEU (CLIC INICIO -> CLIC FIN)
// ============================================================
function onCeldaClick(dia, slotIndex) {
    if (!window.__esEditableMatriz) return;

    if (!window.__seleccionManual) {
        // PASO 1: Iniciar selección manual en esta celda
        window.__seleccionManual = {
            dia: dia,
            slotInicio: slotIndex,
            slotFin: slotIndex,
            slotHover: slotIndex
        };
        actualizarHighlightSeleccion();
        mostrarBannerSeleccion(dia, slotIndex);
    } else if (window.__seleccionManual.dia === dia) {
        // PASO 2: Clic en la casilla donde termina (desplazando hacia abajo)
        const iniSlot = Math.min(window.__seleccionManual.slotInicio, slotIndex);
        const finSlot = Math.max(window.__seleccionManual.slotInicio, slotIndex);

        const horaIni = window.__bloquesVisiblesActuales[iniSlot].ini;
        const horaFin = window.__bloquesVisiblesActuales[finSlot].fin;

        cancelarSeleccionManual(false);

        // Abrir modal para escoger la materia directamente con esas horas
        if (typeof abrirModalAsignacionManual === 'function') {
            abrirModalAsignacionManual(dia, horaIni, horaFin);
        } else {
            abrirModalSlotVacio(dia, horaIni, horaFin);
        }
    } else {
        // Clic en otro día: reiniciar selección en ese nuevo día
        window.__seleccionManual = {
            dia: dia,
            slotInicio: slotIndex,
            slotFin: slotIndex,
            slotHover: slotIndex
        };
        actualizarHighlightSeleccion();
        mostrarBannerSeleccion(dia, slotIndex);
    }
}

function onCeldaHover(dia, slotIndex) {
    if (!window.__seleccionManual || window.__seleccionManual.dia !== dia) return;
    window.__seleccionManual.slotHover = slotIndex;
    actualizarHighlightSeleccion();
}

function actualizarHighlightSeleccion() {
    document.querySelectorAll('.unefa-timetable-cell').forEach(td => {
        td.classList.remove('is-selection-start', 'is-selection-range');
    });

    if (!window.__seleccionManual) return;

    const dia = window.__seleccionManual.dia;
    const ini = window.__seleccionManual.slotInicio;
    const hover = (window.__seleccionManual.slotHover !== undefined) ? window.__seleccionManual.slotHover : ini;
    const minS = Math.min(ini, hover);
    const maxS = Math.max(ini, hover);

    for (let s = minS; s <= maxS; s++) {
        const td = document.querySelector(`.cell-slot-${s}.col-dia-${dia}`);
        if (td && td.classList.contains('is-empty')) {
            if (s === ini) {
                td.classList.add('is-selection-start');
            } else {
                td.classList.add('is-selection-range');
            }
        }
    }
}

function mostrarBannerSeleccion(dia, slotIndex) {
    const banner = document.getElementById('banner-seleccion-horario');
    const txt = document.getElementById('txt-banner-seleccion');
    const bInfo = window.__bloquesVisiblesActuales[slotIndex];
    if (banner && txt && bInfo) {
        txt.innerHTML = `<strong>1. Bloque de inicio fijado:</strong> ${dia} a las <strong>${bInfo.ini}</strong>. Ahora <strong>haz clic en la casilla de abajo</strong> donde termina la clase para escoger la materia.`;
        banner.classList.remove('d-none');
        banner.classList.add('d-flex');
    }
}

function cancelarSeleccionManual(ocultarBanner = true) {
    window.__seleccionManual = null;
    actualizarHighlightSeleccion();
    if (ocultarBanner) {
        const banner = document.getElementById('banner-seleccion-horario');
        if (banner) {
            banner.classList.remove('d-flex');
            banner.classList.add('d-none');
        }
    }
}

function renderizarMatrizHorario() {
    cancelarSeleccionManual(true);

    const secSel = document.getElementById('filtro-seccion-matriz')?.value || '__TODAS__';
    const q      = (document.getElementById('filtro-texto-matriz')?.value || '').toLowerCase().trim();

    // Filtrar secciones activas
    const seccionesFiltradas = window.__seccionesMatrizData.filter(s => {
        if (!s.hora_inicio || !s.hora_fin) return false;
        if (secSel !== '__TODAS__' && s.nombre_seccion !== secSel) return false;
        if (q !== '') {
            const mat  = (s.nombre_materia || '').toLowerCase();
            const sec  = (s.nombre_seccion || '').toLowerCase();
            const aula = (s.aula || '').toLowerCase();
            const doc  = (s.titular_nombre || '').toLowerCase();
            const cod  = (s.codigo_materia || '').toLowerCase();
            if (!mat.includes(q) && !sec.includes(q) && !aula.includes(q) && !doc.includes(q) && !cod.includes(q)) {
                return false;
            }
        }
        return true;
    });

    const cntEl = document.getElementById('contador-secciones-txt');
    if (cntEl) cntEl.textContent = seccionesFiltradas.length;

    // Matriz completa oficial siempre visible desde 07:00 hasta 17:30
    const bloquesVisibles = window.__bloquesHorarios.map((b, idx) => ({ ...b, originalIndex: idx }));

    window.__bloquesVisiblesActuales = bloquesVisibles;
    const dias = window.__diasSemanales;
    const numBloques = bloquesVisibles.length;

    // Grid: matriz de celdas
    const grid = {};
    dias.forEach(d => {
        grid[d] = [];
        for (let b = 0; b < numBloques; b++) {
            grid[d][b] = { type: 'empty', items: [] };
        }
    });

    // Mapear cada sección a sus bloques con umbral de 20 minutos
    seccionesFiltradas.forEach(s => {
        const dia = normalizarDiaJS(s.dia_semana);
        if (!grid[dia]) return;

        const iniMin = horaAMinutosJS(s.hora_inicio);
        const finMin = horaAMinutosJS(s.hora_fin);

        for (let b = 0; b < numBloques; b++) {
            const bIni = horaAMinutosJS(bloquesVisibles[b].ini);
            const bFin = horaAMinutosJS(bloquesVisibles[b].fin);
            const overlap = Math.min(finMin, bFin) - Math.max(iniMin, bIni);
            if (overlap >= 20) {
                grid[dia][b].items.push(s);
            }
        }
    });

    // Calcular rowspan cuando es una sola sección por celda
    const esModoSeccion = (secSel !== '__TODAS__');
    const skipGrid = {};
    dias.forEach(d => {
        skipGrid[d] = new Array(numBloques).fill(false);
    });

    if (esModoSeccion) {
        dias.forEach(d => {
            for (let b = 0; b < numBloques; b++) {
                if (skipGrid[d][b]) continue;
                const cell = grid[d][b];
                if (cell.items.length === 1) {
                    const sec = cell.items[0];
                    let span = 1;
                    for (let nextB = b + 1; nextB < numBloques; nextB++) {
                        const nextCell = grid[d][nextB];
                        if (nextCell.items.length === 1 && nextCell.items[0].id === sec.id) {
                            span++;
                            skipGrid[d][nextB] = true;
                        } else {
                            break;
                        }
                    }
                    cell.type = 'start';
                    cell.span = span;
                }
            }
        });
    }

    // Renderizar HTML del tbody
    let tbodyHtml = '';
    for (let b = 0; b < numBloques; b++) {
        const bInfo = bloquesVisibles[b];

        tbodyHtml += `<tr class="fila-bloque" data-slot="${b}">`;
        tbodyHtml += `<td class="unefa-timetable-slot-label">${bInfo.label}</td>`;

        dias.forEach(d => {
            if (skipGrid[d][b]) {
                return;
            }

            const cell = grid[d][b];

            if (cell.type === 'start' && cell.span > 1) {
                // Celda que abarca múltiples bloques (rowspan)
                const s = cell.items[0];
                const mKey = s.id_materia || s.codigo_materia || s.nombre_materia;
                const col = window.__colorMapMatriz[mKey] || { border: '#2563eb', bg: '#eff6ff', text: '#1e40af', badge_bg: '#dbeafe', badge_color: '#1d4ed8' };
                const secJson = JSON.stringify(s).replace(/"/g, '&quot;');
                const clickAttr = window.__esEditableMatriz ? `onclick="abrirModalEditarHorario(${secJson})"` : '';
                const tol = s.tolerancia_minutos !== undefined ? s.tolerancia_minutos : 15;

                tbodyHtml += `
                    <td class="unefa-timetable-cell cell-slot-${b} col-dia-${d}" rowspan="${cell.span}">
                        <div class="unefa-timetable-block" ${clickAttr} style="background:${col.bg || '#eff6ff'} !important; border-color:${col.border}40 !important; border-left-color:${col.border} !important;" title="${s.nombre_materia} (${s.nombre_seccion}) - ${s.hora_inicio.substring(0,5)} a ${s.hora_fin.substring(0,5)} · Tol: ${tol}m · Aula: ${s.aula || 'S/A'}">
                            <div class="subject-title" style="color:${col.text || '#0f172a'}; font-size:0.75rem;">${s.nombre_materia}</div>
                            <div class="subject-meta">
                                <span class="badge rounded-pill" style="background:${col.badge_bg || '#fff'}; color:${col.badge_color || '#333'}; font-size:0.55rem; padding: 1px 4px; font-weight:700;"><i class="fas fa-door-open me-0.5"></i>${s.aula || 'Aula 01'}</span>
                                ${s.nombre_seccion ? `<span class="badge rounded-pill" style="background:${col.badge_bg || '#fff'}; color:${col.badge_color || '#333'}; font-size:0.55rem; padding: 1px 4px; font-weight:700;"><i class="fas fa-layer-group me-0.5"></i>${s.nombre_seccion}</span>` : ''}
                                ${window.__esEditableMatriz ? '<i class="fas fa-pen ms-auto opacity-75" style="font-size:0.50rem; color:' + (col.text || '#333') + ';"></i>' : ''}
                            </div>
                            ${s.titular_nombre ? `<div class="subject-teacher" style="color:${col.text || '#475569'};"><i class="fas fa-chalkboard-teacher me-1 opacity-75"></i>${s.titular_nombre}</div>` : ''}
                        </div>
                    </td>
                `;
            } else if (cell.items.length === 1) {
                // Bloque único (45 min)
                const s = cell.items[0];
                const mKey = s.id_materia || s.codigo_materia || s.nombre_materia;
                const col = window.__colorMapMatriz[mKey] || { border: '#2563eb', bg: '#eff6ff', text: '#1e40af', badge_bg: '#dbeafe', badge_color: '#1d4ed8' };
                const secJson = JSON.stringify(s).replace(/"/g, '&quot;');
                const clickAttr = window.__esEditableMatriz ? `onclick="abrirModalEditarHorario(${secJson})"` : '';

                tbodyHtml += `
                    <td class="unefa-timetable-cell cell-slot-${b} col-dia-${d}">
                        <div class="unefa-timetable-block" ${clickAttr} style="background:${col.bg || '#eff6ff'} !important; border-color:${col.border}40 !important; border-left-color:${col.border} !important;" title="${s.nombre_materia} (${s.nombre_seccion}) - ${s.hora_inicio.substring(0,5)} a ${s.hora_fin.substring(0,5)}">
                            <div class="subject-title text-truncate" style="color:${col.text || '#0f172a'}; font-size:0.72rem;">${s.nombre_materia}</div>
                            <div class="subject-meta" style="font-size:0.52rem;">
                                <span class="badge rounded-pill" style="background:${col.badge_bg || '#fff'}; color:${col.badge_color || '#333'}; font-size:0.52rem; padding: 1px 3px;"><i class="fas fa-door-open me-0.5"></i>${s.aula || 'Aula 01'}</span>
                                ${s.nombre_seccion ? `<span class="badge rounded-pill" style="background:${col.badge_bg || '#fff'}; color:${col.badge_color || '#333'}; font-size:0.52rem; padding: 1px 3px;">${s.nombre_seccion}</span>` : ''}
                            </div>
                        </div>
                    </td>
                `;
            } else if (cell.items.length > 1) {
                // Múltiples secciones (vista consolidada "Todas")
                tbodyHtml += `<td class="unefa-timetable-cell cell-slot-${b} col-dia-${d} p-1" style="background:#f8fafc; vertical-align:top;">`;
                cell.items.forEach(s => {
                    const mKey = s.id_materia || s.codigo_materia || s.nombre_materia;
                    const col = window.__colorMapMatriz[mKey] || { border: '#64748b', bg: '#f1f5f9', text: '#334155' };
                    const secJson = JSON.stringify(s).replace(/"/g, '&quot;');
                    const clickAttr = window.__esEditableMatriz ? `onclick="abrirModalEditarHorario(${secJson})"` : '';

                    tbodyHtml += `
                        <div class="unefa-chip-mini" style="border-left-color:${col.border}; background:${col.bg || '#fff'}; color:${col.text || '#1e293b'};" ${clickAttr} title="${s.nombre_materia} (${s.nombre_seccion}) · ${s.aula || 'S/A'}">
                            <strong>${s.nombre_seccion}</strong>: ${s.nombre_materia} <small class="text-muted">(${s.aula || 'S/A'})</small>
                        </div>
                    `;
                });
                tbodyHtml += `</td>`;
            } else {
                // Celda vacía con soporte de selección manual interactiva (Clic inicio -> Clic fin)
                const clickAction = window.__esEditableMatriz ? `onclick="onCeldaClick('${d}', ${b})" onmouseenter="onCeldaHover('${d}', ${b})"` : '';
                tbodyHtml += `<td class="unefa-timetable-cell cell-slot-${b} col-dia-${d} is-empty" data-dia="${d}" data-slot="${b}" ${clickAction}></td>`;
            }
        });

        tbodyHtml += `</tr>`;
    }

    const tbodyEl = document.getElementById('tbody-cronograma-matriz');
    if (tbodyEl) {
        tbodyEl.innerHTML = tbodyHtml;
    }
}

// Ejecutar primer renderizado al cargar
document.addEventListener('DOMContentLoaded', function() {
    renderizarMatrizHorario();
});

if (document.readyState === 'interactive' || document.readyState === 'complete') {
    renderizarMatrizHorario();
}
</script>
