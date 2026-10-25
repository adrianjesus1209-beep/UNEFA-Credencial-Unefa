/**
 * este archivo contiene funciones utiles que se usan en todo el sistema
 * por ejemplo mostrar avisos flotantes, ver contrasenas o confirmar antes de borrar
 */

// esta funcion sirve para mostrar u ocultar la contrasena al presionar el icono del ojo
function togglePass(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    // si estaba oculta con puntitos la mostramos en texto normal
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    // si estaba visible la volvemos a ocultar con puntitos
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

/* ===== avisos flotantes tipo mensaje en la esquina ===== */
// esta funcion saca una tarjetita de aviso en la esquina que dura 5 segundos y se quita sola
function notificar(mensaje, tipo) {
    tipo = tipo || 'info';
    const contenedor = document.getElementById('toast-container-unefa');
    if (!contenedor) return;

    // elegimos el icono segun si es mensaje de exito, error, advertencia o informacion
    const iconos = {
        success: 'fa-check-circle',
        error: 'fa-times-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };

    // creamos el cuadrito en la pantalla
    const toast = document.createElement('div');
    toast.className = 'toast-unefa toast-' + tipo;
    toast.innerHTML =
        '<i class="fas ' + (iconos[tipo] || iconos.info) + ' toast-icon"></i>' +
        '<div class="toast-body">' + mensaje + '</div>' +
        '<button class="toast-close" onclick="cerrarToast(this.parentElement)"><i class="fas fa-times"></i></button>';

    contenedor.appendChild(toast);

    // esperamos 5 segundos y lo desaparecemos suavemente
    setTimeout(function() {
        cerrarToast(toast);
    }, 5000);
}

// esta funcion desvanece el aviso flotante y lo borra de la memoria
function cerrarToast(toast) {
    if (!toast || toast.classList.contains('toast-hiding')) return;
    toast.classList.add('toast-hiding');
    setTimeout(function() {
        if (toast.parentElement) toast.remove();
    }, 300);
}

/* ===== ventana para preguntar si esta seguro antes de hacer algo importante ===== */
function confirmar(mensaje, titulo) {
    return new Promise(function(resolve) {
        var modalEl = document.getElementById('modalConfirmar');
        // si no existe la ventanita modal continuamos directamente
        if (!modalEl) {
            resolve(true);
            return;
        }
        var tituloEl = document.getElementById('confirmar-titulo');
        var mensajeEl = document.getElementById('confirmar-mensaje');
        var btnAceptar = document.getElementById('confirmar-aceptar');
        var btnCancelar = document.getElementById('confirmar-cancelar');

        if (tituloEl) tituloEl.textContent = titulo || 'Confirmar';
        if (mensajeEl) mensajeEl.innerHTML = mensaje;

        // quitamos los escuchadores para que no se queden pegados al reutilizar la ventana
        function limpiar() {
            btnAceptar.removeEventListener('click', onAceptar);
            btnCancelar.removeEventListener('click', onCancelar);
            modalEl.removeEventListener('hidden.bs.modal', onCancelar);
        }

        // si presiono el boton azul de aceptar decimos que si
        function onAceptar() {
            limpiar();
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            resolve(true);
        }

        // si presiono cancelar o cerro la ventana decimos que no
        function onCancelar() {
            limpiar();
            resolve(false);
        }

        btnAceptar.addEventListener('click', onAceptar);
        btnCancelar.addEventListener('click', onCancelar);
        modalEl.addEventListener('hidden.bs.modal', onCancelar);

        var modal = new bootstrap.Modal(modalEl, { backdrop: false });
        modal.show();
    });
}

// cuando la pagina termina de cargar preparamos los videos y animaciones
document.addEventListener('DOMContentLoaded', function() {
    // pausa los videos que no se estan viendo cuando el usuario cambia de pestana
    document.querySelectorAll('input[name="playlist"]').forEach(radio => {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.slot-video video').forEach(vid => {
                vid.pause();
            });
            const activeVideo = document.querySelector('.slot-video:not([style*="display: none"]) video, .slot-video[style*="display: block"] video');
            if (activeVideo) activeVideo.load();
        });
    });

    // hace que las cajitas aparezcan suavemente cuando el usuario baja con la ruedita del raton
    const revealElements = document.querySelectorAll('.reveal, .reveal-left, .reveal-right');
    if (revealElements.length > 0) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });
        revealElements.forEach(el => observer.observe(el));
    }

    // pone las ventanas modales al frente de todo para que ningun menu las tape
    document.addEventListener('show.bs.modal', function(e) {
        if (e.target && e.target.parentElement !== document.body) {
            document.body.appendChild(e.target);
        }
    });
});
