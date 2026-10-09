/* DEVIOZ V4.5.4.1 - Invitacion de evaluacion al finalizar un video publico. */
(function () {
    'use strict';
    const player = document.getElementById('deviozVideoPlayer');
    const callout = document.getElementById('videoQuizCallout');
    const modal = document.getElementById('videoQuizModal');
    if (!player || !callout || !modal) return;

    const mensaje = document.getElementById('vqCalloutMessage');
    const botonEmpezar = document.getElementById('vqQuizModalStart');
    const botonDespues = document.getElementById('vqQuizModalLater');

    function cerrarModal() {
        if (typeof modal.close === 'function' && modal.open) {
            modal.close();
        } else {
            modal.removeAttribute('open');
            modal.classList.remove('vq-fallback-open');
        }
    }

    function mostrarModal() {
        if (modal.open) return;
        try {
            if (typeof modal.showModal === 'function') {
                modal.showModal();
            } else {
                modal.setAttribute('open', '');
                modal.classList.add('vq-fallback-open');
            }
            if (botonEmpezar) botonEmpezar.focus();
        } catch (error) {
            // El dialogo no debe interrumpir ni bloquear la reproduccion si falla.
            callout.classList.add('is-ready');
        }
    }

    player.addEventListener('ended', function () {
        callout.classList.add('is-ready');
        if (mensaje) mensaje.textContent = 'Ya terminaste el video. Puedes comenzar la evaluación de cinco preguntas o responder después.';
        if (document.fullscreenElement && typeof document.exitFullscreen === 'function') {
            // En pantalla completa, salir primero para que el dialogo no quede oculto.
            document.exitFullscreen().then(mostrarModal).catch(mostrarModal);
        } else {
            mostrarModal();
        }
    });

    if (botonDespues) botonDespues.addEventListener('click', cerrarModal);
    modal.addEventListener('click', function (event) {
        // Cerrar solamente al pulsar el fondo externo, no el contenido interno.
        if (event.target === modal) cerrarModal();
    });
})();
