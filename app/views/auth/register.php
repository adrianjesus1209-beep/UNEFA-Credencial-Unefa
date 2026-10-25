<?php
// este archivo es el formulario para que un bachiller cree su cuenta de aspirante
// revisa si las inscripciones estan abiertas y pide sus datos personales con dos preguntas de seguridad
$conn_view = $conexion ?? (new Database())->getConnection();
$stmtPeriodoReg = $conn_view->query("SELECT estado, nombre FROM periodo_academico ORDER BY FIELD(estado, 'Activo', 'Planificado', 'Finalizado') LIMIT 1");
$periodoReg = $stmtPeriodoReg->fetch(PDO::FETCH_ASSOC);
$registroPermitido = $periodoReg && $periodoReg['estado'] === 'Activo';
?>
<div class="container pt-5 pb-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="bg-white p-3 rounded-4 shadow-premium border-light">
                <div class="text-center mb-3">
                    <img src="<?php echo URLROOT; ?>/assets/img/logos/logo_unefa.png" alt="Logo UNEFA" class="rounded-circle mb-2" style="width: 64px; height: 64px; object-fit: contain; border: 2px solid #003366;">
                    <h2 class="fw-bold h5">REGISTRO DE ASPIRANTE</h2>
                    <p class="text-muted small mb-0">Postulate en Ingenieria de Sistemas y sube tus documentos para revision del Coordinador Academico.</p>
                </div>

                <div id="paso-form">
                    <form action="index.php?action=register" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="rol" value="Aspirante">

                        <fieldset <?= !$registroPermitido ? 'disabled style="opacity:0.6;pointer-events:none;"' : '' ?>>
                        <div class="row g-2">
                            <div class="col-md-6 mb-1">
                                <label class="form-label small fw-bold text-secondary">Primer Nombre</label>
                                <div class="input-group-modern">
                                    <i class="fas fa-user icon-prefix"></i>
                                    <input type="text" name="nombre" class="form-control-modern" style="padding:0.6rem 0.75rem 0.6rem 2.2rem;" oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '')" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="form-label small fw-bold text-secondary">Segundo Nombre</label>
                                <div class="input-group-modern">
                                    <i class="fas fa-user icon-prefix"></i>
                                    <input type="text" name="segundo_nombre" class="form-control-modern" style="padding:0.6rem 0.75rem 0.6rem 2.2rem;" oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '')">
                                </div>
                            </div>
                        </div>

                        <div class="row g-2">
                            <div class="col-md-6 mb-1">
                                <label class="form-label small fw-bold text-secondary">Primer Apellido</label>
                                <div class="input-group-modern">
                                    <i class="fas fa-user-tag icon-prefix"></i>
                                    <input type="text" name="apellido" class="form-control-modern" style="padding:0.6rem 0.75rem 0.6rem 2.2rem;" oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '')" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="form-label small fw-bold text-secondary">Segundo Apellido</label>
                                <div class="input-group-modern">
                                    <i class="fas fa-user-tag icon-prefix"></i>
                                    <input type="text" name="segundo_apellido" class="form-control-modern" style="padding:0.6rem 0.75rem 0.6rem 2.2rem;" oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '')" required>
                                </div>
                            </div>
                        </div>

                        <div class="row g-2 mt-0">
                            <div class="col-md-6 mb-1">
                                <label class="form-label small fw-bold text-secondary">Telefono</label>
                                <div class="input-group-modern">
                                    <i class="fas fa-phone icon-prefix"></i>
                                    <input type="tel" name="telefono" class="form-control-modern" style="padding:0.6rem 0.75rem 0.6rem 2.2rem;" minlength="10" maxlength="11" pattern="[0-9]+" oninput="this.value = this.value.replace(/[^0-9]/g, '')" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="form-label small fw-bold text-secondary">Direccion Completa</label>
                                <div class="input-group-modern">
                                    <i class="fas fa-map-marker-alt icon-prefix"></i>
                                    <input type="text" name="direccion" class="form-control-modern" style="padding:0.6rem 0.75rem 0.6rem 2.2rem;" required>
                                </div>
                            </div>
                        </div>

                        <?php
                        $carreras = $conn_view->query("SELECT id, nombre_carrera FROM carrera")->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        <div id="contenedor-carrera" class="mb-2 mt-0">
                            <label class="form-label small fw-bold text-primary"><i class="fas fa-graduation-cap me-1"></i> Carrera a cursar</label>
                            <div class="input-group-modern">
                                <i class="fas fa-university icon-prefix"></i>
                                <select name="id_carrera" id="id_carrera" class="form-control-modern" style="padding:0.6rem 0.75rem 0.6rem 2.2rem;">
                                    <?php foreach ($carreras as $carrera): ?>
                                        <option value="<?php echo $carrera['id']; ?>"><?php echo $carrera['nombre_carrera']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-2 mt-0">
                            <label class="form-label small fw-bold text-secondary">Documento de Identidad</label>
                            <div class="d-flex w-100">
                                <select name="tipo_documento" class="form-control-modern border-end-0" style="width: 65px; max-width: 65px; flex-shrink: 0; border-radius: 12px 0 0 12px; padding: 0.6rem 0.4rem 0.6rem 0.6rem; font-weight: bold; cursor: pointer; text-align: center;">
                                    <option value="V">V</option>
                                    <option value="E">E</option>
                                    <option value="P">P</option>
                                    <option value="M">M</option>
                                </select>
                                <div class="input-group-modern flex-grow-1">
                                    <i class="fas fa-id-card icon-prefix"></i>
                                    <input type="text" name="cedula" class="form-control-modern" minlength="6" maxlength="9" pattern="[0-9]+" oninput="this.value = this.value.replace(/[^0-9]/g, '')" required style="padding:0.6rem 0.75rem 0.6rem 2.2rem; border-radius: 0 12px 12px 0;">
                                </div>
                            </div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-bold text-secondary">Correo Institucional</label>
                            <div class="input-group-modern">
                                <i class="fas fa-envelope icon-prefix"></i>
                                <input type="email" name="correo" class="form-control-modern" style="padding:0.6rem 0.75rem 0.6rem 2.2rem;" required>
                            </div>
                        </div>

                        <div class="bg-light p-2 rounded-4 mb-3 border border-info border-opacity-10">
                            <h6 class="fw-bold mb-2 small text-primary"><i class="fas fa-shield-alt me-2"></i>Seguridad de la Cuenta</h6>
                            <?php
                            $u_model_questions = new Usuario($conn_view);
                            $preguntas = $u_model_questions->obtenerPreguntasSeguridad();
                            ?>
                            <div class="mb-2">
                                <label class="fw-bold text-muted" style="font-size:0.7rem;">Pregunta de Seguridad 1</label>
                                <select name="id_pregunta_1" id="id_pregunta_1" class="form-control-modern mb-1 select-pregunta" required style="padding:0.5rem 0.6rem 0.5rem 0.8rem;">
                                    <option value="" selected disabled>Seleccione una pregunta...</option>
                                    <?php foreach ($preguntas as $p): ?>
                                        <option value="<?php echo $p['id']; ?>"><?php echo $p['texto_pregunta']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="input-group-modern">
                                    <i class="fas fa-key icon-prefix"></i>
                                    <input type="text" name="respuesta_1" class="form-control-modern" placeholder="Respuesta 1" required oninput="this.value = this.value.toUpperCase()" style="padding:0.5rem 0.6rem 0.5rem 2rem;">
                                </div>
                            </div>
                            <div class="mb-0">
                                <label class="fw-bold text-muted" style="font-size:0.7rem;">Pregunta de Seguridad 2</label>
                                <select name="id_pregunta_2" id="id_pregunta_2" class="form-control-modern mb-1 select-pregunta" required style="padding:0.5rem 0.6rem 0.5rem 0.8rem;">
                                    <option value="" selected disabled>Seleccione una pregunta...</option>
                                    <?php foreach ($preguntas as $p): ?>
                                        <option value="<?php echo $p['id']; ?>"><?php echo $p['texto_pregunta']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="input-group-modern">
                                    <i class="fas fa-key icon-prefix"></i>
                                    <input type="text" name="respuesta_2" class="form-control-modern" placeholder="Respuesta 2" required oninput="this.value = this.value.toUpperCase()" style="padding:0.5rem 0.6rem 0.5rem 2rem;">
                                </div>
                            </div>
                        </div>

                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const s1 = document.getElementById('id_pregunta_1');
                            const s2 = document.getElementById('id_pregunta_2');

                            function updateOptions() {
                                const v1 = s1.value;
                                const v2 = s2.value;

                                Array.from(s2.options).forEach(opt => {
                                    if (opt.value === v1 && v1 !== "") {
                                        opt.disabled = true;
                                        opt.style.display = 'none';
                                    } else {
                                        opt.disabled = false;
                                        opt.style.display = 'block';
                                    }
                                });

                                Array.from(s1.options).forEach(opt => {
                                    if (opt.value === v2 && v2 !== "") {
                                        opt.disabled = true;
                                        opt.style.display = 'none';
                                    } else {
                                        opt.disabled = false;
                                        opt.style.display = 'block';
                                    }
                                });
                            }

                            s1.addEventListener('change', updateOptions);
                            s2.addEventListener('change', updateOptions);
                        });
                        </script>

                        <div class="mb-2">
                            <label class="form-label small fw-bold text-secondary">Contrasena</label>
                            <div class="position-relative input-group-modern">
                                <i class="fas fa-lock icon-prefix"></i>
                                <input type="password" name="clave" id="clave-reg" class="form-control-modern pe-5" placeholder="Crea una clave segura" required style="padding:0.6rem 2.5rem 0.6rem 2.2rem;">
                                <button type="button" class="btn-eye-float" onclick="togglePass('clave-reg', this)" tabindex="-1">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        </fieldset>
                        <button type="submit" class="btn btn-modern w-100 py-2 fw-bold shadow-sm" id="btn-registrar" <?= !$registroPermitido ? 'disabled style="background:#9ca3af;cursor:not-allowed;"' : '' ?>>
                            <?= $registroPermitido ? 'Crear Mi Cuenta' : 'Registros cerrados' ?>
                        </button>
                    </form>
                </div>

                <div class="text-center mt-2">
                    <a href="index.php?page=login" class="text-decoration-none small" style="color: #64748b;">
                        ¿Ya tienes cuenta? <span style="color:#003366; font-weight:600;">Inicia Sesion</span>
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
// esta funcion muestra u oculta la clave al tocar el ojito
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
</script>

