<?php
// este archivo es la planilla imprimible para entregar en control de estudios
// muestra los datos personales del bachiller, la lista de requisitos y el espacio para firmar
$id_usuario = $_SESSION['id_usuario_temp'] ?? $_SESSION['id_usuario'] ?? null;
if (!$id_usuario) {
    echo "Error: No se pudo encontrar la informacion del estudiante.";
    exit;
}

$consulta = "SELECT p.*, u.correo, u.rol, u.estado, c.nombre_carrera 
            FROM usuario u 
            JOIN perfil p ON u.id_perfil = p.id 
            LEFT JOIN carrera c ON p.id_carrera = c.id
            WHERE u.id = :id LIMIT 1";
$sentencia = $conexion->prepare($consulta);
$sentencia->execute([':id' => $id_usuario]);
$datos = $sentencia->fetch(PDO::FETCH_ASSOC);

if (!$datos) {
    echo "Error: No se encontro el perfil del estudiante.";
    exit;
}

$periodo = $conexion->query("SELECT nombre FROM periodo_academico WHERE estado = 'Activo' LIMIT 1")->fetchColumn();
if (!$periodo) {
    $periodo = date('Y') . '-I';
}

$stmtDocs = $conexion->prepare("SELECT nombre_archivo, tipo, estado FROM registro_documentos WHERE id_usuario = :id");
$stmtDocs->execute([':id' => $id_usuario]);
$documentos = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);

$nombre_completo = trim("{$datos['nombre']} {$datos['segundo_nombre']} {$datos['apellido']} {$datos['segundo_apellido']}");
$cedula_completa = ($datos['tipo_documento'] ?? 'V') . '-' . $datos['cedula'];
$titulo_planilla = ($datos['estado'] === 'Aprobado') ? 'FICHA ESTUDIANTIL' : 'PLANILLA DE PREINSCRIPCION';
$foto_ruta = !empty($datos['foto_perfil']) && $datos['foto_perfil'] !== 'default.svg'
    ? URLROOT . '/uploads/profiles/' . $datos['foto_perfil']
    : null;
 ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo $titulo_planilla; ?> - UNEFA</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #333; margin: 0; line-height: 1.4; }
        .only-screen { display: block; }
        .cintilla { width: 100%; display: block; max-height: 61px; object-fit: cover; object-position: top; }
        .content { padding: 25px 40px; }
        .membrete { display: flex; align-items: center; gap: 20px; margin-bottom: 20px; }
        .membrete-logo { width: 80px; height: 80px; flex-shrink: 0; }
        .membrete-texto { flex-grow: 1; text-align: center; }
        .membrete-texto .pais { margin: 2px 0; font-size: 0.72rem; color: #003366; font-weight: normal; }
        .membrete-texto .organismo { margin: 2px 0; font-size: 0.78rem; color: #003366; font-weight: normal; }
        .membrete-texto .universidad { margin: 2px 0; font-size: 0.95rem; color: #003366; font-weight: bold; }
        .membrete-texto .nucleo { margin: 2px 0; font-size: 0.85rem; color: #003366; font-weight: bold; letter-spacing: 1px; }
        .membrete-foto { width: 95px; height: 115px; border: 1px solid #ccc; flex-shrink: 0; display: flex; align-items: center; justify-content: center; background: #f9f9f9; overflow: hidden; }
        .membrete-foto img { width: 100%; height: 100%; object-fit: cover; }
        .membrete-foto span { font-size: 0.55rem; color: #bbb; text-transform: uppercase; }
        .title-bar { text-align: center; padding: 8px 0; margin-bottom: 25px; border-bottom: 2px solid #003366; }
        .title-bar h2 { margin: 0; font-size: 1.1rem; font-weight: bold; letter-spacing: 1px; color: #003366; }
        .section { border: 1px solid #ddd; border-radius: 8px; overflow: hidden; margin-bottom: 22px; }
        .section-header { background: #f0f4f8; padding: 10px 16px; font-weight: bold; color: #003366; font-size: 0.87rem; text-transform: uppercase; border-bottom: 1px solid #ddd; letter-spacing: 0.3px; }
        .section-body { padding: 16px 20px; }
        .data-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 20px; }
        .data-grid .full { grid-column: span 2; }
        .data-row { display: flex; border-bottom: 1px solid #e5e7eb; padding: 6px 0; }
        .data-row .label { font-weight: bold; font-size: 0.8rem; color: #555; flex-shrink: 0; }
        .data-row .label::after { content: ": "; }
        .data-row .value { font-size: 0.9rem; color: #000; }
        .doc-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .doc-table th { text-align: left; font-size: 0.75rem; color: #666; border-bottom: 1px solid #d1d5db; padding: 6px 8px; }
        .doc-table td { padding: 6px 8px; border-bottom: 1px solid #f3f4f6; }
        .doc-status { display: inline-block; padding: 1px 8px; border-radius: 10px; font-size: 0.7rem; font-weight: bold; }
        .doc-status.aprobado { background: #dcfce7; color: #16a34a; }
        .doc-status.pendiente { background: #fef9c3; color: #ca8a04; }
        .doc-status.rechazado { background: #fee2e2; color: #dc2626; }
        .signature-row { display: flex; justify-content: space-around; margin-top: 50px; gap: 40px; }
        .signature-box { text-align: center; min-width: 220px; }
        .signature-box .line { border-top: 1px solid #000; padding-top: 8px; margin-bottom: 4px; }
        .signature-box .label { font-size: 0.75rem; color: #555; }
        .btn-print { display: block; margin: 40px auto 0; background: #003366; color: #fff; border: none; padding: 12px 32px; border-radius: 50px; cursor: pointer; font-weight: bold; font-size: 1rem; box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
        .btn-print:hover { background: #002244; }
        .footer { background: #003366; color: rgba(255,255,255,0.9); text-align: center; padding: 1.5rem 0; font-size: 0.8rem; margin-top: 40px; }
        .footer .logo-footer { width: 64px; height: 64px; object-fit: contain; margin-bottom: 10px; }
        .footer h4 { color: #fff !important; margin: 0 0 5px; font-size: 0.95rem; }
        .footer .social-links { display: flex; justify-content: center; gap: 12px; margin: 10px 0 15px; flex-wrap: wrap; }
        .footer .social-links img { width: 36px; height: 36px; border-radius: 50%; transition: opacity 0.2s; }
        .footer .social-links img:hover { opacity: 0.7; }
        .footer .sep { border: 0; border-top: 1px solid rgba(255,255,255,0.2); width: 75%; margin: 15px auto; }
        .footer .emision { margin: 5px 0 0; }
        .footer .emision small { opacity: 0.75; }
        .footer .copy { margin: 10px 0 0; font-size: 0.7rem; opacity: 0.6; }
        @media print {
            @page { margin: 0; }
            .only-screen { display: none; }
            .btn-print { display: none; }
            .footer { display: none; }

            .content { padding: 13px 35px; }
            .membrete { margin-bottom: 10px; gap: 13px; }
            .membrete-logo { width: 69px; height: 69px; }
            .membrete-foto { width: 84px; height: 101px; }
            .membrete-texto .pais { font-size: 0.76rem; margin: 0; }
            .membrete-texto .organismo { font-size: 0.84rem; margin: 0; }
            .membrete-texto .universidad { font-size: 1.01rem; margin: 0; }
            .membrete-texto .nucleo { font-size: 0.91rem; margin: 0; }
            .title-bar { margin-bottom: 15px; padding: 4px 0; }
            .title-bar h2 { font-size: 1.21rem; }
            .section { margin-bottom: 13px; }
            .section-body { padding: 10px 18px; }
            .section-header { padding: 7px 18px; font-size: 0.96rem; }
            .data-row { padding: 3px 0; }
            .data-row .label { font-size: 0.89rem; }
            .data-row .value { font-size: 0.96rem; }
            .data-grid { gap: 2px 15px; }
            .signature-row { margin-top: 61px; gap: 25px; }
            .signature-box .line { padding-top: 4px; }
            .doc-table { font-size: 0.91rem; }
            .doc-table th, .doc-table td { padding: 3px 8px; }
        }
    </style>
</head>
<body>

<img src="<?php echo URLROOT; ?>/assets/img/banners/cintilla.jpg" alt="" class="cintilla only-screen">

<div class="content">

<div class="membrete">
    <img src="<?php echo URLROOT; ?>/assets/img/logos/logo_unefa.png" alt="UNEFA" class="membrete-logo">
    <div class="membrete-texto">
        <p class="pais">REPÚBLICA BOLIVARIANA DE VENEZUELA</p>
        <p class="organismo">MINISTERIO DEL PODER POPULAR PARA LA DEFENSA</p>
        <p class="universidad">Universidad Nacional Experimental Politécnica de la Fuerza Armada Nacional</p>
        <p class="nucleo">NUCLEO GUACARA</p>
    </div>
    <div class="membrete-foto">
        <?php if ($foto_ruta): ?>
            <img src="<?php echo $foto_ruta; ?>" alt="Foto">
        <?php else: ?>
            <span>FOTO</span>
        <?php endif; ?>
    </div>
</div>

<div class="title-bar">
    <h2><?php echo $titulo_planilla; ?></h2>
</div>

<div class="section">
    <div class="section-header">Datos Personales</div>
    <div class="section-body">
        <div class="data-grid">
            <div class="data-row"><span class="label">Apellidos</span><span class="value"><?php echo htmlspecialchars(trim($datos['apellido'] . ' ' . $datos['segundo_apellido'])); ?></span></div>
            <div class="data-row"><span class="label">Nombres</span><span class="value"><?php echo htmlspecialchars(trim($datos['nombre'] . ' ' . $datos['segundo_nombre'])); ?></span></div>
            <div class="data-row"><span class="label">Cedula de Identidad</span><span class="value"><?php echo htmlspecialchars($cedula_completa); ?></span></div>
            <div class="data-row"><span class="label">Telefono</span><span class="value"><?php echo htmlspecialchars($datos['telefono'] ?? ''); ?></span></div>
            <div class="data-row full"><span class="label">Correo Electronico</span><span class="value"><?php echo htmlspecialchars($datos['correo']); ?></span></div>
            <div class="data-row full"><span class="label">Direccion</span><span class="value"><?php echo htmlspecialchars($datos['direccion'] ?? ''); ?></span></div>
        </div>
    </div>
</div>

<div class="section">
    <div class="section-header">Informacion academica</div>
    <div class="section-body">
        <div class="data-grid">
            <div class="data-row full"><span class="label">Carrera a Cursar</span><span class="value"><?php echo htmlspecialchars($datos['nombre_carrera'] ?? 'Ingenieria de Sistemas'); ?></span></div>
            <div class="data-row"><span class="label">Periodo academico</span><span class="value"><?php echo htmlspecialchars($periodo); ?></span></div>
            <div class="data-row"><span class="label">Semestre Actual</span><span class="value"><?php echo labelSemestre($datos['semestre_actual'] ?? 0); ?></span></div>
            <div class="data-row"><span class="label">Modalidad</span><span class="value">PREGRADO</span></div>
        </div>
    </div>
</div>

<div class="section">
    <div class="section-header">Documentacion Consignada (Digital)</div>
    <div class="section-body">
        <?php if (!empty($documentos)): ?>
            <table class="doc-table">
                <tr><th>Estado</th><th>Documento</th><th style="text-align:right;">Estatus</th></tr>
                <?php foreach ($documentos as $doc):
                    $estado_clase = match($doc['estado']) {
                        'Aprobado' => 'aprobado',
                        'Rechazado' => 'rechazado',
                        default => 'pendiente'
                    };
                ?>
                <tr>
                    <td><?php echo $doc['estado'] === 'Aprobado' ? '[OK]' : ($doc['estado'] === 'Rechazado' ? '[X]' : '-'); ?></td>
                    <td><?php echo htmlspecialchars($doc['nombre_archivo']); ?></td>
                    <td style="text-align:right;"><span class="doc-status <?php echo $estado_clase; ?>"><?php echo htmlspecialchars($doc['estado']); ?></span></td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p style="text-align:center;color:#999;font-size:0.85rem;"><em>No se han consignado documentos digitales.</em></p>
        <?php endif; ?>
    </div>
</div>

<div class="signature-row">
    <div class="signature-box"><div class="line"></div><span class="label">Firma del Estudiante</span></div>
    <div class="signature-box"><div class="line"></div><span class="label">Sello de Control de Estudios</span></div>
</div>

<button class="btn-print only-screen" onclick="window.print()" style="display:block;margin:40px auto 0;">Imprimir Planilla (Descargar PDF)</button>

</div>

<div class="footer">
    <img src="<?php echo URLROOT; ?>/assets/img/logos/logo_unefa.png" alt="UNEFA" class="logo-footer">
    <h4>Universidad Nacional Experimental Politécnica de la Fuerza Armada Nacional Bolivariana</h4>
    <p>RIF: G-20006297-5</p>
    <div class="social-links">
        <a href="https://x.com/Unefa_VEN?t=FhK2uslLRmCrIa9sjQIEEA&s=09" target="_blank"><img src="<?php echo URLROOT; ?>/assets/img/redes/X-Twitter.webp" alt="X"></a>
        <a href="https://www.instagram.com/unefa_ve?igsh=MXJvcjFkMXJ5Z3NzMg%3D%3D" target="_blank"><img src="<?php echo URLROOT; ?>/assets/img/redes/Instagram.webp" alt="Instagram"></a>
        <a href="https://www.facebook.com/share/1BKuAut1dg/" target="_blank"><img src="<?php echo URLROOT; ?>/assets/img/redes/Facebook.webp" alt="Facebook"></a>
        <a href="https://www.youtube.com/channel/UCU1YFZgV-ENQkfHRspsK9nA" target="_blank"><img src="<?php echo URLROOT; ?>/assets/img/redes/Youtube.webp" alt="YouTube"></a>
        <a href="https://www.tiktok.com/@unefa_ve?_t=8iwcWCLFEAA&_r=1" target="_blank"><img src="<?php echo URLROOT; ?>/assets/img/redes/Tiktok.webp" alt="TikTok"></a>
    </div>
    <hr class="sep">
    <p class="emision">Este documento es una preinscripcion generada por el sistema SICEU-UNEFA.<br><small>Fecha de emision: <?php echo date('d/m/Y h:i A'); ?></small></p>
    <p class="copy">&copy; <?php echo date('Y'); ?> UNEFA. Excelencia Educativa Abierta al Pueblo.</p>
</div>
</body>
</html>

