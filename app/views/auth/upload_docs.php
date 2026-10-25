<?php
// este archivo es el casillero digital donde el aspirante sube sus 8 recaudos (partida, titulo, cedula, etc)
// aqui puede ver en tiempo real que papeles le aprobaron y cuales tiene que volver a subir si fueron rechazados
$id_usuario = $_SESSION['id_usuario_temp'] ?? $_SESSION['id_usuario'] ?? null;
if (!$id_usuario) {
    header('Location: index.php?page=login');
    exit;
}

$db = new Database();
$conexion = $db->getConnection();
require_once 'app/models/Usuario.php';

// TRANSICION DE ESTADO AUTOMATICA: Si el usuario ya fue aprobado o tiene rol Estudiante, redirigir a Nivel 2
$stmtU = $conexion->prepare("SELECT rol, estado FROM usuario WHERE id = :id LIMIT 1");
$stmtU->execute([':id' => $id_usuario]);
$userRow = $stmtU->fetch(PDO::FETCH_ASSOC);
if ($userRow && ($userRow['rol'] === 'Estudiante' || $userRow['estado'] === 'Aprobado')) {
    $_SESSION['rol_usuario'] = 'Estudiante';
    $_SESSION['id_usuario'] = $id_usuario;
    header('Location: index.php?page=dashboard');
    exit;
}

$stmt = $conexion->prepare("SELECT nombre_archivo, tipo, estado, observaciones FROM registro_documentos WHERE id_usuario = :id");
$stmt->execute([':id' => $id_usuario]);
$docs_subidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$requisitos = [
    'Cedula de Identidad (Laminada y Copia)' => 'cedula',
    'Cedula de Identidad de los Padres' => 'cedula_padres',
    'Partida de Nacimiento' => 'partida_nacimiento',
    'Titulo de Bachiller' => 'titulo_bachiller',
    'Notas Certificadas' => 'notas_certificadas',
    'Constancia OPSU (RUSNIEU)' => 'opsu',
    'Carnet de Inscripcion Militar' => 'inscripcion_militar',
    'Carta de Residencia/Buena Conducta' => 'residencia'
];

$totalRequisitos = count($requisitos);
$aprobadosCount = 0;
$rechazadosCount = 0;
$subidosCount = 0;

foreach ($requisitos as $label => $id) {
    foreach ($docs_subidos as $d) {
        if (stripos($d['nombre_archivo'], $label) !== false || stripos($d['nombre_archivo'], $id) !== false) {
            $subidosCount++;
            if ($d['estado'] === 'Aprobado') $aprobadosCount++;
            if ($d['estado'] === 'Rechazado') $rechazadosCount++;
            break;
        }
    }
}

// TRANSICION AUTOMATICA: Al validarse y aprobarse la totalidad de los documentos
if ($aprobadosCount >= $totalRequisitos && $totalRequisitos > 0) {
    $modeloUsuario = new Usuario($conexion);
    $modeloUsuario->aprobarAspiranteAEstudiante($id_usuario);
    $_SESSION['rol_usuario'] = 'Estudiante';
    $_SESSION['id_usuario'] = $id_usuario;
    header('Location: index.php?page=dashboard');
    exit;
}

// Calculo de Estado General de la Solicitud
if ($rechazadosCount > 0) {
    $estadoGeneral = 'Documentacion Rechazada';
    $badgeClase = 'bg-danger text-white';
    $estadoMensaje = 'Uno o mas documentos fueron rechazados por el Coordinador Academico. Reemplaza unicamente los archivos indicados. El archivo anterior sera eliminado permanentemente del almacenamiento al resubirlo.';
} elseif ($subidosCount >= $totalRequisitos) {
    $estadoGeneral = 'En Revision';
    $badgeClase = 'bg-warning text-dark';
    $estadoMensaje = 'Tu expediente completo esta en revision por el Coordinador Academico (Control de Estudios). En cuanto se aprueben todos los recaudos, accederas de inmediato al panel de Estudiante con tu Codigo QR activo.';
} else {
    $estadoGeneral = 'Pendiente por Carga';
    $badgeClase = 'bg-secondary text-white';
    $estadoMensaje = 'Carga los documentos requeridos para formalizar tu expediente de admision en la carrera de Ingenieria de Sistemas.';
}

function tipoColor($tipo) {
    switch ($tipo) {
        case 'PDF': return ['bg'=>'#fee2e2','text'=>'#dc2626','icon'=>'fa-file-pdf'];
        case 'Word': return ['bg'=>'#dbeafe','text'=>'#2563eb','icon'=>'fa-file-word'];
        case 'Imagen': return ['bg'=>'#dcfce7','text'=>'#16a34a','icon'=>'fa-file-image'];
        default: return ['bg'=>'#f1f5f9','text'=>'#475569','icon'=>'fa-file'];
    }
}
?>

<div class="container py-5 mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <!-- Banner General de Estado de la Solicitud -->
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4" style="background:#ffffff; border-left: 6px solid #003366 !important;">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge <?= $badgeClase ?> rounded-pill px-3 py-2 fw-bold text-uppercase" style="font-size:0.75rem;">
                                <?= htmlspecialchars($estadoGeneral) ?>
                            </span>
                            <span class="text-muted small fw-semibold">Nivel 1: Aspirante (Admision)</span>
                        </div>
                        <h4 class="fw-bold mb-1" style="color:#003366;">Expediente de Recaudos Digitales</h4>
                        <p class="small text-muted mb-0"><?= htmlspecialchars($estadoMensaje) ?></p>
                    </div>
                    <div class="text-md-end flex-shrink-0">
                        <span class="badge bg-light text-dark border px-3 py-2 rounded-pill fw-bold">
                            Progreso: <?= $subidosCount ?> / <?= $totalRequisitos ?> recaudos
                        </span>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-lg rounded-4 overflow-hidden" style="background: #ffffff;">
                <div class="row g-0">
                    <div class="col-md-4 text-white p-4 p-md-5 d-flex flex-column justify-content-between" style="background: linear-gradient(135deg, #003366 0%, #001f3f 100%) !important;">
                        <div>
                            <span class="badge bg-warning text-dark rounded-pill px-3 py-1 mb-3 fw-bold">ASPIRANTE / POSTULANTE</span>
                            <h3 class="fw-bold mb-3">Control de Recaudos</h3>
                            <div class="progress mb-4" style="height: 10px; background: rgba(255,255,255,0.2);">
                                <div id="mainProgressBar" class="progress-bar bg-success" role="progressbar" style="width: 0%;"></div>
                            </div>
                            <p class="small opacity-75">Sube cada documento requerido para la carrera de <strong>Ingenieria de Sistemas</strong>.</p>
                            
                            <div class="p-3 rounded-3 mt-3 small" style="background: rgba(255,255,255,0.12);">
                                <i class="fas fa-shield-alt me-1 text-warning"></i> <strong>Regla de Reemplazo:</strong> Si un documento resulta rechazado, la plataforma habilita unicamente el boton de reemplazo para ese recaudo, eliminando de forma definitiva el archivo anterior del almacenamiento.
                            </div>
                        </div>

                        <div class="mt-4 d-grid gap-2">
                            <a href="index.php?page=planilla" target="_blank" class="btn btn-outline-light w-100 rounded-pill fw-bold">
                                <i class="fas fa-file-pdf me-2"></i> Ver Planilla
                            </a>
                            <a href="index.php?action=logout" class="btn btn-danger w-100 rounded-pill fw-bold">
                                <i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesion
                            </a>
                        </div>
                    </div>

                    <div class="col-md-8 p-4 p-md-5">
                        <h4 class="fw-bold text-dark mb-4">Casillas Individuales de Recaudos</h4>
                        
                        <div class="checklist">
                            <?php foreach ($requisitos as $label => $id): 
                                $doc_data = null;
                                foreach($docs_subidos as $d) {
                                    if (stripos($d['nombre_archivo'], $label) !== false || stripos($d['nombre_archivo'], $id) !== false) {
                                        $doc_data = $d;
                                        break;
                                    }
                                }
                                $is_uploaded = ($doc_data !== null);
                                $is_approved = ($is_uploaded && $doc_data['estado'] == 'Aprobado');
                                $is_rejected = ($is_uploaded && $doc_data['estado'] == 'Rechazado');
                            ?>
                                <div class="requirement-item d-flex align-items-center justify-content-between p-3 mb-3 border rounded-3 <?php 
                                    echo $is_approved ? 'bg-light-success' : ($is_rejected ? 'bg-light-danger' : ($is_uploaded ? 'bg-light-info' : 'bg-white')); 
                                ?>" data-requisito="<?php echo $label; ?>" data-id="<?php echo $id; ?>">
                                    <div class="d-flex align-items-center">
                                        <div class="status-icon me-3 <?php 
                                            echo $is_approved ? 'text-success' : ($is_rejected ? 'text-danger' : ($is_uploaded ? 'text-info' : 'text-muted')); 
                                        ?>">
                                            <i class="fas <?php 
                                                echo $is_approved ? 'fa-check-circle' : ($is_rejected ? 'fa-times-circle' : ($is_uploaded ? 'fa-clock' : 'fa-circle-notch')); 
                                            ?>"></i>
                                        </div>
                                        <div>
                                            <h6 class="m-0 fw-bold <?php 
                                                echo $is_approved ? 'text-success' : ($is_rejected ? 'text-danger' : ($is_uploaded ? 'text-info' : 'text-dark')); 
                                            ?>"><?php echo $label; ?></h6>
                                            <small class="text-muted d-block"><?php 
                                                if ($is_approved) echo 'Documento Aprobado';
                                                elseif ($is_rejected) echo 'Documento Rechazado';
                                                elseif ($is_uploaded) echo 'En revision por el Coordinador Academico';
                                                else echo 'Pendiente por subir';
                                            ?></small>
                                            <?php
                                                $tc = tipoColor($doc_data['tipo'] ?? '');
                                                $icono = $tc['icon'];
                                            ?>
                                            <?php if ($is_uploaded): ?>
                                                <small class="text-muted d-block mt-1" style="font-size:0.7rem;line-height:1.2;">
                                                    <i class="fas <?= $icono ?> me-1"></i><?= htmlspecialchars($doc_data['nombre_archivo']) ?>
                                                    <span class="badge rounded-pill px-2 py-0 ms-1 fw-bold" style="font-size:0.6rem;background:<?= $tc['bg'] ?>;color:<?= $tc['text'] ?>;"><?= $doc_data['tipo'] ?></span>
                                                </small>
                                            <?php endif; ?>
                                            <?php if ($is_rejected && !empty($doc_data['observaciones'])): ?>
                                                <div class="alert alert-danger py-1 px-2 mt-2 mb-0 small border-0" style="font-size: 0.75rem;">
                                                    <i class="fas fa-exclamation-triangle me-1"></i> <strong>Motivo de Rechazo:</strong> <?php echo htmlspecialchars($doc_data['observaciones']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="action-zone">
                                        <?php if ($is_approved): ?>
                                            <span class="badge bg-success rounded-pill px-3 py-2"><i class="fas fa-check me-1"></i> Aprobado</span>
                                        <?php elseif ($is_rejected): ?>
                                            <button class="btn btn-sm btn-danger rounded-pill px-3 fw-bold" onclick="triggerUpload('<?php echo $label; ?>')" title="Reemplazar archivo rechazado">
                                                <i class="fas fa-sync-alt me-1"></i> Reemplazar
                                            </button>
                                        <?php elseif (!$is_uploaded): ?>
                                            <button class="btn btn-sm btn-primary rounded-pill px-3 fw-bold" onclick="triggerUpload('<?php echo $label; ?>')">
                                                <i class="fas fa-upload me-1"></i> Subir
                                            </button>
                                        <?php else: ?>
                                            <span class="badge bg-info text-white rounded-pill px-3 py-2"><i class="fas fa-clock me-1"></i> En Revision</span>
                                        <?php endif; ?>
                                    </div>
                                    <input type="file" class="file-input-hidden" style="display:none" onchange="handleFileSelected(this, '<?php echo $label; ?>')" accept="image/*,.pdf,.doc,.docx">
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mt-4 pt-3 border-top d-flex justify-content-between align-items-center">
                            <span class="text-muted small"><i class="fas fa-university me-1"></i> Control de Estudios - UNEFA</span>
                            <?php if ($subidosCount >= $totalRequisitos): ?>
                                <span class="badge bg-success text-white rounded-pill px-3 py-2">
                                    <i class="fas fa-check-double me-1"></i> Todos los recaudos cargados
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .requirement-item { transition: all 0.3s ease; border: 1px solid #e2e8f0; }
    .requirement-item:hover { transform: translateX(5px); border-color: #003366; }
    .bg-light-success { background-color: #f0fff4; border-color: #c6f6d5 !important; }
    .bg-light-danger { background-color: #fff5f5; border-color: #feb2b2 !important; }
    .bg-light-info { background-color: #ebf8ff; border-color: #bee3f8 !important; }
    .text-success { color: #2f855a !important; }
    .text-danger { color: #c53030 !important; }
    .text-info { color: #2b6cb0 !important; }
    .status-icon { font-size: 1.5rem; }
</style>

<script>
function tipoBadge(tipo) {
    var map = {
        'PDF': {bg:'#fee2e2', text:'#dc2626', icon:'fa-file-pdf'},
        'Word': {bg:'#dbeafe', text:'#2563eb', icon:'fa-file-word'},
        'Imagen': {bg:'#dcfce7', text:'#16a34a', icon:'fa-file-image'}
    };
    return map[tipo] || {bg:'#f1f5f9', text:'#475569', icon:'fa-file'};
}

function triggerUpload(requisito) {
    const items = document.querySelectorAll('.requirement-item');
    items.forEach(item => {
        if (item.getAttribute('data-requisito') === requisito) {
            item.querySelector('.file-input-hidden').click();
        }
    });
}

function handleFileSelected(input, requisito) {
    if (input.files && input.files[0]) {
        uploadFile(input.files[0], requisito);
    }
}

// esta funcion envia el archivo elegido al servidor con ajax y recarga la pagina al terminar
async function uploadFile(file, requisito) {
    const item = Array.from(document.querySelectorAll('.requirement-item')).find(el => el.getAttribute('data-requisito') === requisito);
    if (!item) return;

    const actionZone = item.querySelector('.action-zone');
    const oldAction = actionZone ? actionZone.innerHTML : '';
    if (actionZone) {
        actionZone.innerHTML = '<div class="spinner-border spinner-border-sm text-primary" role="status"></div>';
    }

    const reqId = item.getAttribute('data-id') || '';
    const formData = new FormData();
    formData.append('archivo', file);
    formData.append('requisito', requisito);
    formData.append('requisito_id', reqId);
    formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');

    try {
        const response = await fetch('index.php?action=subir_individual', {
            method: 'POST',
            body: formData
        });
        const res = await response.json();
        if (res.success) {
            location.reload();
        } else {
            alert('Error al subir: ' + (res.message || 'Error desconocido'));
            if (actionZone) actionZone.innerHTML = oldAction;
        }
    } catch (err) {
        alert('Error de conexion al subir documento.');
        if (actionZone) actionZone.innerHTML = oldAction;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const total = <?= $totalRequisitos ?>;
    const subidos = <?= $subidosCount ?>;
    const pct = total > 0 ? Math.round((subidos / total) * 100) : 0;
    const pBar = document.getElementById('mainProgressBar');
    if (pBar) {
        pBar.style.width = pct + '%';
        pBar.setAttribute('aria-valuenow', pct);
    }
});
</script>
