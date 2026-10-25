<?php
// aqui empieza el pie de pagina que se muestra en la parte inferior de todas las pantallas del sitio.
// contiene los enlaces a las redes sociales oficiales, el aviso de derechos de autor y la ventanita modal de confirmacion.
?>
    <footer class="pie-pagina-moderno text-center">
        <div class="container py-3">
            <div class="mb-2">
                <img src="<?php echo URLROOT; ?>/assets/img/logos/logo_unefa.png" class="rounded-circle mb-1.5 logo-footer" style="width:44px;height:44px;object-fit:contain;">
                <h6 class="fw-bold text-white mb-0.5" style="font-size:0.92rem;">Universidad Nacional Experimental Politecnica <br class="d-none d-md-block">de la Fuerza Armada Nacional Bolivariana</h6>
                <p class="text-white-50 small mb-0" style="font-size:0.75rem;">RIF: G-20006297-5</p>
            </div>
            
            <!-- enlaces a las redes sociales oficiales de la unefa -->
            <div class="d-flex justify-content-center gap-2 mb-2 flex-wrap">
                <a href="https://x.com/Unefa_VEN?t=FhK2uslLRmCrIa9sjQIEEA&s=09" target="_blank" class="footer-social-link" title="X">
                    <img src="<?php echo URLROOT; ?>/assets/img/redes/X-Twitter.webp" alt="X">
                </a>
                <a href="https://www.instagram.com/unefa_ve?igsh=MXJvcjFkMXJ5Z3NzMg%3D%3D" target="_blank" class="footer-social-link" title="Instagram">
                    <img src="<?php echo URLROOT; ?>/assets/img/redes/Instagram.webp" alt="Instagram">
                </a>
                <a href="https://www.facebook.com/share/1BKuAut1dg/" target="_blank" class="footer-social-link" title="Facebook">
                    <img src="<?php echo URLROOT; ?>/assets/img/redes/Facebook.webp" alt="Facebook">
                </a>
                <a href="https://www.youtube.com/channel/UCU1YFZgV-ENQkfHRspsK9nA" target="_blank" class="footer-social-link" title="YouTube">
                    <img src="<?php echo URLROOT; ?>/assets/img/redes/Youtube.webp" alt="YouTube">
                </a>
                <a href="https://www.tiktok.com/@unefa_ve?_t=8iwcWCLFEAA&_r=1" target="_blank" class="footer-social-link" title="TikTok">
                    <img src="<?php echo URLROOT; ?>/assets/img/redes/Tiktok.webp" alt="TikTok">
                </a>
            </div>
            
            <hr class="border-light opacity-10 my-2 w-50 mx-auto">
            <p class="small text-white-50 mb-0 px-3" style="font-size:0.75rem;">&copy; 2026 UNEFA. Excelencia Educativa Abierta al Pueblo.</p>
        </div>
    </footer>

    <!-- contenedor invisible donde aparecen los cartelitos flotantes verdes o rojos de notificacion -->
    <div id="toast-container-unefa" class="toast-container-unefa"></div>

    <!-- ventanita modal flotante que se usa cuando el sistema le pregunta al usuario si esta seguro de continuar -->
    <div class="modal fade" id="modalConfirmar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" style="max-width:400px;margin:0 auto;">
                <div class="text-center p-4" style="background:linear-gradient(135deg,#003366,#0056b3);">
                    <div class="text-white mb-2">
                        <i class="fas fa-question-circle fa-4x"></i>
                    </div>
                    <h5 class="fw-bold text-white mb-0" id="confirmar-titulo">Confirmar</h5>
                </div>
                <div class="px-4 py-4 text-center">
                    <p class="mb-0 fs-6" id="confirmar-mensaje"></p>
                </div>
                <div class="px-4 pb-4 d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold" id="confirmar-cancelar" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn rounded-pill px-4 fw-bold text-white shadow-sm" id="confirmar-aceptar" style="background:#003366;min-width:120px;">Aceptar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- scripts basicos de bootstrap y funciones principales de javascript -->
    <script src="<?php echo URLROOT; ?>/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URLROOT; ?>/js/main.js"></script>

    <script>
    // cuando la pagina termina de cargar, si hay algun aviso pendiente en la sesion lo mostramos en pantalla
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($_SESSION['error']) && !empty($_SESSION['error'])): ?>
        notificar(<?php echo json_encode($_SESSION['error']); ?>, 'error');
        <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['success']) && !empty($_SESSION['success'])): ?>
        notificar(<?php echo json_encode($_SESSION['success']); ?>, 'success');
        <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['success_extra']) && !empty($_SESSION['success_extra'])): ?>
        notificar(<?php echo json_encode($_SESSION['success_extra']); ?>, 'info');
        <?php unset($_SESSION['success_extra']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['flash_aprobado'])): ?>
        notificar('Registro Aprobado!', 'success');
        <?php unset($_SESSION['flash_aprobado']); ?>
        <?php endif; ?>
    });
    </script>

</body>
</html>
