<?php
// este archivo sirve para recuperar la clave en 3 pasos sencillos
// primero pide el correo, luego las respuestas secretas y al final la clave nueva
$step = $_SESSION['reset_step'] ?? '1';
$preguntas = $_SESSION['reset_questions'] ?? [];
?>
<div class="container pt-0 pb-4">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="bg-white p-4 rounded-4 shadow-premium border-light">
                <div class="text-center mb-4">
                    <div class="mb-3 d-inline-block p-3 rounded-circle" style="background: rgba(0,51,102,0.05); color: #003366;">
                        <i class="fas fa-user-lock fa-2x"></i>
                    </div>
                    <h2 class="fw-bold h4">RECUPERAR ACCESO</h2>
                    <p class="text-muted small">
                        <?php 
                        if ($step == '1') echo "Identifica tu cuenta institucional";
                        elseif ($step == '2') echo "Valida tu identidad con tus preguntas";
                        elseif ($step == '3') echo "Crea una nueva contrasena segura";
                        ?>
                    </p>
                </div>

                <form action="index.php?action=reset_password" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="step" value="<?php echo $step; ?>">

                    <?php if ($step == '1'): ?>
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-secondary">Correo Institucional</label>
                            <div class="input-group-modern">
                                <i class="fas fa-envelope icon-prefix"></i>
                                <input type="email" name="correo" class="form-control-modern" placeholder="usuario@unefa.edu.ve" required autofocus>
                            </div>
                            <div class="form-text x-small mt-2 text-muted">
                                Te pediremos tus preguntas de seguridad en el siguiente paso.
                            </div>
                        </div>

                    <?php elseif ($step == '2'): ?>
                        <div class="bg-light p-3 rounded-4 mb-4 border border-info border-opacity-10">
                            <?php foreach ($preguntas as $idx => $p): ?>
                                <div class="mb-3 <?php echo ($idx < count($preguntas)-1) ? 'pb-3 border-bottom' : ''; ?>">
                                    <label class="form-label x-small fw-bold text-primary d-block mb-2">Pregunta <?php echo ($idx + 1); ?></label>
                                    <p class="small fw-bold text-dark mb-2"><?php echo $p['texto_pregunta']; ?></p>
                                    <div class="input-group-modern">
                                        <i class="fas fa-key icon-prefix"></i>
                                        <input type="text" name="respuestas[<?php echo $p['id']; ?>]" class="form-control-modern" placeholder="Escribe tu respuesta aqui" required oninput="this.value = this.value.toUpperCase()">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    <?php elseif ($step == '3'): ?>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">Nueva Contrasena</label>
                            <div class="position-relative input-group-modern">
                                <i class="fas fa-lock icon-prefix"></i>
                                <input type="password" name="clave" id="nueva-clave" class="form-control-modern pe-5" placeholder="Minimo 6 caracteres" required autofocus>
                                <button type="button" class="btn-eye-float" onclick="togglePass('nueva-clave', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-secondary">Confirmar Contrasena</label>
                            <div class="position-relative input-group-modern">
                                <i class="fas fa-check-double icon-prefix"></i>
                                <input type="password" name="confirm_clave" id="confirm-clave" class="form-control-modern pe-5" placeholder="Repite la contrasena" required>
                                <button type="button" class="btn-eye-float" onclick="togglePass('confirm-clave', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-modern w-100 py-3 fw-bold shadow-sm" style="background: #003366;">
                        <?php 
                        if ($step == '1') echo "Continuar";
                        elseif ($step == '2') echo "Validar Respuestas";
                        elseif ($step == '3') echo "Cambiar Contrasena";
                        ?>
                    </button>
                    
                    <?php if ($step != '1'): ?>
                        <div class="text-center mt-3">
                            <a href="index.php?page=reset_password&cancel=1" class="text-decoration-none small text-muted">
                                Cancelar y volver
                            </a>
                        </div>
                    <?php endif; ?>
                </form>

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
</script>
<?php
if (isset($_GET['cancel'])) {
    unset($_SESSION['reset_user_id'], $_SESSION['reset_step'], $_SESSION['reset_questions']);
    header('Location: index.php?page=reset_password');
    exit;
}
?>