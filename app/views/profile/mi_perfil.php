<?php
// esta pagina sirve para ver y editar la informacion de la cuenta propia
// aqui cualquier usuario puede cambiar su numero de telefono o su contrasena
$modeloUsuario = new Usuario($conexion);
$perfil = $modeloUsuario->obtenerPerfilCompleto($_SESSION['id_usuario']);
$hasPhoto = !empty($perfil['foto_perfil']) && $perfil['foto_perfil'] !== 'default.svg' && file_exists('public/uploads/profiles/' . $perfil['foto_perfil']);
$photoUrl = $hasPhoto ? URLROOT . '/uploads/profiles/' . rawurlencode($perfil['foto_perfil']) : '';
$esEstudiante = (Usuario::normalizarRol($_SESSION['rol_usuario'] ?? '') === 'Estudiante');
?>
<div class="container py-5 mt-3">
    <!-- encabezado principal con titulo y boton para volver al panel -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="fw-bold" style="color:#003366;">
            <i class="fas fa-user me-2"></i> Mi Perfil
        </h1>
        <a href="index.php?page=dashboard" class="btn btn-sm rounded-pill px-3 fw-bold" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;transition:all .2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
            <i class="fas fa-arrow-left me-2"></i>Volver
        </a>
    </div>

    <!-- mensajitos de exito o error si intento guardar cambios -->
    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm mb-4">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm mb-4">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- columna izquierda con la foto redonda y el resumen institucional -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body text-center p-4">
                    <!-- marco circular donde se muestra la foto oficial o silueta -->
                    <div class="mb-3 position-relative d-inline-block" id="profileContainer">
                        <?php if ($hasPhoto): ?>
                            <img src="<?= $photoUrl ?>" alt="Foto de perfil"
                                class="rounded-circle border shadow-sm"
                                style="width:140px;height:140px;object-fit:cover;border:3px solid #e2e8f0 !important;"
                                id="profilePreview">
                        <?php else: ?>
                            <div class="rounded-circle border shadow-sm d-flex align-items-center justify-content-center bg-light"
                                style="width:140px;height:140px;border:3px solid #e2e8f0 !important;">
                                <i class="fas fa-user fa-4x text-secondary opacity-50"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <h5 class="fw-bold mb-1"><?= htmlspecialchars($_SESSION['nombre_completo']) ?></h5>
                    <span class="badge bg-primary rounded-pill px-3 py-2 mb-3" style="background:#003366 !important;">
                        <?= htmlspecialchars($perfil['rol'] ?? '') ?>
                    </span>
                    <hr>
                    <!-- lista de datos basicos de identidad -->
                    <div class="text-start small">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Cedula:</span>
                            <span class="fw-semibold"><?= htmlspecialchars($perfil['tipo_documento'] . ' ' . $perfil['cedula']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Correo:</span>
                            <span class="fw-semibold"><?= htmlspecialchars($perfil['correo']) ?></span>
                        </div>
                        <?php if (!empty($perfil['nombre_carrera'])): ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Carrera:</span>
                            <span class="fw-semibold"><?= htmlspecialchars($perfil['nombre_carrera']) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Estado:</span>
                            <span class="fw-semibold"><?= htmlspecialchars($perfil['estado'] ?? 'Activo') ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- columna derecha con el formulario para editar datos y clave -->
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0"><i class="fas fa-edit me-2 text-secondary"></i> Editar informacion</h5>
                </div>
                <div class="card-body p-4 pt-2">
                    <!-- aviso para los estudiantes de que su nombre y cedula no se pueden alterar -->
                    <?php if ($esEstudiante): ?>
                        <div class="alert alert-info rounded-4 border-0 shadow-sm mb-4">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="fas fa-shield-alt text-primary"></i>
                                <span class="fw-bold text-dark">Campos de Identidad Institucional Bloqueados</span>
                            </div>
                            <p class="small text-muted mb-0">
                                Como estudiante regular, tus datos de identidad (nombre, cedula y carrera) estan validados por Control de Estudios y se mantienen en solo lectura. Puedes editar tu <strong>Numero de Telefono</strong> y tu <strong>Contrasena</strong>.
                            </p>
                        </div>
                    <?php endif; ?>

                    <form id="formPerfil" method="POST" enctype="multipart/form-data" action="index.php?action=update_own_profile">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">
                                    Nombre <?= $esEstudiante ? '<i class="fas fa-lock text-muted ms-1" title="Bloqueado"></i>' : '*' ?>
                                </label>
                                <input type="text" name="nombre" class="form-control rounded-3" <?= $esEstudiante ? 'readonly style="background:#f8fafc;cursor:not-allowed;"' : 'required' ?>
                                    value="<?= htmlspecialchars($perfil['nombre']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">
                                    Segundo nombre <?= $esEstudiante ? '<i class="fas fa-lock text-muted ms-1" title="Bloqueado"></i>' : '' ?>
                                </label>
                                <input type="text" name="segundo_nombre" class="form-control rounded-3" <?= $esEstudiante ? 'readonly style="background:#f8fafc;cursor:not-allowed;"' : '' ?>
                                    value="<?= htmlspecialchars($perfil['segundo_nombre'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">
                                    Apellido <?= $esEstudiante ? '<i class="fas fa-lock text-muted ms-1" title="Bloqueado"></i>' : '*' ?>
                                </label>
                                <input type="text" name="apellido" class="form-control rounded-3" <?= $esEstudiante ? 'readonly style="background:#f8fafc;cursor:not-allowed;"' : 'required' ?>
                                    value="<?= htmlspecialchars($perfil['apellido']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">
                                    Segundo apellido <?= $esEstudiante ? '<i class="fas fa-lock text-muted ms-1" title="Bloqueado"></i>' : '' ?>
                                </label>
                                <input type="text" name="segundo_apellido" class="form-control rounded-3" <?= $esEstudiante ? 'readonly style="background:#f8fafc;cursor:not-allowed;"' : '' ?>
                                    value="<?= htmlspecialchars($perfil['segundo_apellido'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Telefono de contacto *</label>
                                <input type="text" name="telefono" class="form-control rounded-3" required
                                    value="<?= htmlspecialchars($perfil['telefono'] ?? '') ?>">
                            </div>
                            <?php if (!$esEstudiante): ?>
                            <!-- los profesores si pueden cambiar su foto y direccion desde aqui -->
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Foto de perfil</label>
                                <input type="file" name="foto_perfil" class="form-control rounded-3" accept="image/*"
                                    onchange="previewFoto(this)">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Direccion</label>
                                <textarea name="direccion" class="form-control rounded-3" rows="2"><?= htmlspecialchars($perfil['direccion'] ?? '') ?></textarea>
                            </div>
                            <?php endif; ?>
                        </div>

                        <hr class="my-4">
                        <!-- seccion para cambiar la contrasena si el usuario lo desea -->
                        <h6 class="fw-bold">Cambiar contrasena</h6>
                        <p class="text-muted small">Deja en blanco si no deseas cambiar tu contrasena.</p>
                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-semibold">Contrasena actual</label>
                                <div class="position-relative">
                                    <input type="password" name="pass_old" id="pass_old" class="form-control rounded-3 pe-5" autocomplete="current-password">
                                    <button type="button" class="btn-eye-float" onclick="togglePass('pass_old', this)" tabindex="-1">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-semibold">Nueva contrasena</label>
                                <div class="position-relative">
                                    <input type="password" name="pass_new" id="pass_new" class="form-control rounded-3 pe-5" autocomplete="new-password">
                                    <button type="button" class="btn-eye-float" onclick="togglePass('pass_new', this)" tabindex="-1">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-semibold">Confirmar contrasena</label>
                                <div class="position-relative">
                                    <input type="password" name="pass_confirm" id="pass_confirm" class="form-control rounded-3 pe-5" autocomplete="new-password">
                                    <button type="button" class="btn-eye-float" onclick="togglePass('pass_confirm', this)" tabindex="-1">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold" style="background:#003366;border-color:#003366;">
                                <i class="fas fa-save me-2"></i> Guardar cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// esta funcion sirve para que al hacer click en el ojito se muestren u oculten las letras de la clave
function togglePass(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

// esta funcion muestra la foto en el circulo inmediatamente cuando el profesor elige una imagen
function previewFoto(input) {
    if (input.files && input.files[0]) {
        var url = window.URL.createObjectURL(input.files[0]);
        document.getElementById('profileContainer').innerHTML =
            '<img src="' + url + '" class="rounded-circle border shadow-sm" style="width:140px;height:140px;object-fit:cover;border:3px solid #e2e8f0 !important;" id="profilePreview">';
    }
}
</script>

