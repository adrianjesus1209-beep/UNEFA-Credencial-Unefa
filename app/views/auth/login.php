<?php
// este archivo es la pantalla de inicio de sesion
// primero la persona toca su rol (director, profesor, alumno, etc) y luego escribe su correo y clave
?>
<div class="container pt-5 pb-4">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">
            <div class="bg-white p-4 rounded-4 shadow-premium border-light">
                <div class="text-center mb-3">
                    <img src="<?php echo URLROOT; ?>/assets/img/logos/logo_unefa.png" alt="Logo UNEFA" class="rounded-circle mb-2" style="width: 64px; height: 64px; object-fit: contain; border: 2px solid #003366;">
                    <h2 class="fw-bold h5 mb-1">SISTEMA UNIFICADO DE ASISTENCIAS Y CARNETS</h2>
                    <p class="text-muted small mb-0">Ingenieria de Sistemas - Selecciona tu rol institucional</p>
                </div>

                <?php if (!empty($_SESSION['error'])): ?>
                    <div class="alert alert-danger py-2 px-3 small rounded-3 mb-3">
                        <i class="fas fa-exclamation-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($_SESSION['success'])): ?>
                    <div class="alert alert-success py-2 px-3 small rounded-3 mb-3">
                        <i class="fas fa-check-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <div id="paso-rol-login">
                    <p class="fw-bold text-center mb-3" style="color: #003366; font-size:0.85rem;">Estructura Jerarquica de Acceso</p>
                    <div class="row g-2 mb-2">
                        <div class="col-6 col-md-4">
                            <div class="tarjeta-rol-login border rounded-4 p-2 text-center h-100"
                                onclick="seleccionarRolLogin('Super Administrador', this)"
                                style="cursor: pointer; transition: all 0.2s;">
                                <div style="font-size: 1.35rem; color: #1e293b;"><i class="fas fa-server"></i></div>
                                <div class="fw-bold mt-1" style="font-size: 0.72rem; color: #1e293b;">1. Super Administrador</div>
                                <small class="text-muted d-block" style="font-size: 0.62rem;">Soporte TI / Auditoria</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="tarjeta-rol-login border rounded-4 p-2 text-center h-100"
                                onclick="seleccionarRolLogin('Director', this)"
                                style="cursor: pointer; transition: all 0.2s;">
                                <div style="font-size: 1.35rem; color: #b91c1c;"><i class="fas fa-landmark"></i></div>
                                <div class="fw-bold mt-1" style="font-size: 0.72rem; color: #b91c1c;">2. Director</div>
                                <small class="text-muted d-block" style="font-size: 0.62rem;">Rector / Institucion</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="tarjeta-rol-login border rounded-4 p-2 text-center h-100"
                                onclick="seleccionarRolLogin('Coordinador Academico', this)"
                                style="cursor: pointer; transition: all 0.2s;">
                                <div style="font-size: 1.35rem; color: #d97706;"><i class="fas fa-user-shield"></i></div>
                                <div class="fw-bold mt-1" style="font-size: 0.72rem; color: #d97706;">3. Coordinador Academico</div>
                                <small class="text-muted d-block" style="font-size: 0.62rem;">Control de Estudios</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="tarjeta-rol-login border rounded-4 p-2 text-center h-100"
                                onclick="seleccionarRolLogin('Profesor', this)"
                                style="cursor: pointer; transition: all 0.2s;">
                                <div style="font-size: 1.35rem; color: #7b1fa2;"><i class="fas fa-chalkboard-teacher"></i></div>
                                <div class="fw-bold mt-1" style="font-size: 0.72rem; color: #7b1fa2;">4. Profesor</div>
                                <small class="text-muted d-block" style="font-size: 0.62rem;">Docente Titular</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="tarjeta-rol-login border rounded-4 p-2 text-center h-100"
                                onclick="seleccionarRolLogin('Estudiante', this)"
                                style="cursor: pointer; transition: all 0.2s;">
                                <div style="font-size: 1.35rem; color: #003366;"><i class="fas fa-user-graduate"></i></div>
                                <div class="fw-bold mt-1" style="font-size: 0.72rem; color: #003366;">5. Estudiante</div>
                                <small class="text-muted d-block" style="font-size: 0.62rem;">Alumno Regular</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="tarjeta-rol-login border rounded-4 p-2 text-center h-100"
                                onclick="seleccionarRolLogin('Aspirante', this)"
                                style="cursor: pointer; transition: all 0.2s;">
                                <div style="font-size: 1.35rem; color: #0d9488;"><i class="fas fa-file-upload"></i></div>
                                <div class="fw-bold mt-1" style="font-size: 0.72rem; color: #0d9488;">6. Aspirante</div>
                                <small class="text-muted d-block" style="font-size: 0.62rem;">Postulante / Pre-inscrito</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="paso-form-login" style="display:none;">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-3 p-2 rounded-3" id="login-rol-badge" style="border: 1px solid rgba(0,0,0,0.08); transition: all 0.3s ease;">
                        <div class="d-flex align-items-center gap-2">
                            <span id="login-rol-icono" style="font-size: 1.3rem;"></span>
                            <div>
                                <div class="fw-bold small" id="login-rol-texto"></div>
                                <a href="#" onclick="cambiarRolLogin(); return false;" class="text-decoration-none hover-link" style="font-size: 11px; color: #64748b; font-weight: 600;">
                                    <i class="fas fa-sync-alt me-1"></i>Cambiar rol
                                </a>
                            </div>
                        </div>
                    </div>

                    <form action="index.php?action=login" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="rol" id="campo-rol-login" value="">
                        <div class="mb-2">
                            <label class="form-label small fw-bold text-secondary">Usuario o Correo Institucional</label>
                            <div class="input-group-modern">
                                <i class="fas fa-user icon-prefix"></i>
                                <input type="text" name="correo" id="input-usuario-login" class="form-control-modern" placeholder="Correo electronico o usuario" required autocomplete="username" style="padding:0.6rem 0.75rem 0.6rem 2.2rem;">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">Contrasena</label>
                            <div class="position-relative input-group-modern">
                                <i class="fas fa-lock icon-prefix"></i>
                                <input type="password" name="clave" id="clave-login" class="form-control-modern pe-5" placeholder="••••••••" required minlength="6" autocomplete="current-password" style="padding:0.6rem 2.5rem 0.6rem 2.2rem;">
                                <button type="button" class="btn-eye-float" onclick="togglePass('clave-login', this)" tabindex="-1">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="text-end mb-3">
                            <a href="index.php?page=reset_password" class="text-decoration-none small fw-bold" style="color: #003366;">
                                Olvidaste tu contrasena?
                            </a>
                        </div>

                        <button type="submit" class="btn btn-modern w-100 py-2 fw-bold shadow-sm text-white" id="btn-ingresar">
                            Ingresar al Sistema
                        </button>
                    </form>
                </div>

                <div class="text-center mt-3 pt-2 border-top" id="registro-link">
                    <div class="small text-muted mb-1">Deseas postularte como Aspirante a Ingenieria de Sistemas?</div>
                    <a href="index.php?page=register" class="btn btn-outline-primary btn-sm px-4 rounded-pill fw-bold" style="color: #003366; border-color: #003366;">
                        REGISTRARSE COMO ASPIRANTE
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
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

var configuracionRoles = {
    'Super Administrador':   { color: '#1e293b', bg: '#f1f5f9', icon: '<i class="fas fa-server"></i>', label: '1. Super Administrador (Soporte TI)' },
    'Director':              { color: '#b91c1c', bg: '#fef2f2', icon: '<i class="fas fa-landmark"></i>', label: '2. Director (Rector / Institucion)' },
    'Coordinador Academico': { color: '#d97706', bg: '#fffbeb', icon: '<i class="fas fa-user-shield"></i>', label: '3. Coordinador Academico (Control de Estudios)' },
    'Profesor':              { color: '#7b1fa2', bg: '#f3e5f5', icon: '<i class="fas fa-chalkboard-teacher"></i>', label: '4. Profesor (Docente Titular)' },
    'Estudiante':            { color: '#003366', bg: '#e8eeff', icon: '<i class="fas fa-user-graduate"></i>', label: '5. Estudiante (Alumno Regular)' },
    'Aspirante':             { color: '#0d9488', bg: '#f0fdfa', icon: '<i class="fas fa-file-upload"></i>', label: '6. Aspirante (Postulante / Pre-inscrito)' }
};

// esta funcion prepara los colores del boton y abre el formulario cuando alguien toca una tarjeta de rol
function seleccionarRolLogin(rol, elemento) {
    var cfg = configuracionRoles[rol];
    document.getElementById('campo-rol-login').value = rol;
    document.getElementById('input-usuario-login').value = '';
    document.getElementById('clave-login').value = '';
    document.getElementById('login-rol-icono').innerHTML = cfg.icon;
    document.getElementById('login-rol-icono').style.color = cfg.color;
    document.getElementById('login-rol-texto').textContent = cfg.label;
    document.getElementById('login-rol-texto').style.color = cfg.color;
    document.getElementById('login-rol-badge').style.background = cfg.bg;
    document.getElementById('login-rol-badge').style.borderColor = cfg.color;
    document.getElementById('btn-ingresar').style.background = cfg.color;

    document.querySelectorAll('.tarjeta-rol-login').forEach(function(c) { c.classList.remove('seleccionada'); });
    if (elemento) elemento.classList.add('seleccionada');

    document.getElementById('registro-link').style.display = (rol === 'Aspirante' || rol === 'Estudiante') ? 'block' : 'none';

    document.getElementById('paso-rol-login').style.display = 'none';
    document.getElementById('paso-form-login').style.display = 'block';

    setTimeout(function() {
        var inputUser = document.getElementById('input-usuario-login');
        if (inputUser) inputUser.focus();
    }, 50);
}

// esta funcion regresa a la lista de tarjetas de rol si la persona se equivoco y quiere elegir otro
function cambiarRolLogin() {
    document.getElementById('paso-form-login').style.display = 'none';
    document.getElementById('paso-rol-login').style.display = 'block';
    document.getElementById('registro-link').style.display = 'block';
}
</script>
