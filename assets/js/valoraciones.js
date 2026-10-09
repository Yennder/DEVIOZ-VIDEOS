/* DEVIOZ VIDEOS V4.5.3 - Una calificacion por video/usuario. */
(function () {
    'use strict';

    const panel = document.getElementById('videoRatingPanel');
    if (!panel) return;

    const stars = Array.from(panel.querySelectorAll('[data-rating-value]'));
    if (!stars.length) return; // Los visitantes sin sesion solo ven el promedio.

    let selected = Number(panel.dataset.selected || 0);
    let hover = 0;
    let saving = false;
    const average = document.getElementById('videoRatingAverage');
    const total = document.getElementById('videoRatingTotal');
    const userStatus = document.getElementById('videoRatingUserStatus');
    const feedback = document.getElementById('videoRatingFeedback');
    const formatNumber = new Intl.NumberFormat('es-ES', { minimumFractionDigits: 1, maximumFractionDigits: 1 });

    function paint() {
        const shown = hover || selected;
        stars.forEach(function (button) {
            const rating = Number(button.dataset.ratingValue);
            button.classList.toggle('is-selected', rating <= shown);
            button.setAttribute('aria-pressed', selected === rating ? 'true' : 'false');
            button.disabled = saving;
        });
    }

    function message(text, error) {
        if (!feedback) return;
        feedback.hidden = !text;
        feedback.textContent = text;
        feedback.classList.toggle('is-error', !!error);
    }

    async function save(value) {
        if (saving) return;
        saving = true;
        hover = 0;
        paint();
        message('Guardando tu calificación…', false);

        try {
            const response = await fetch(panel.dataset.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                body: JSON.stringify({
                    id_video: Number(panel.dataset.videoId),
                    csrf_token: panel.dataset.csrfToken,
                    estrellas: value
                })
            });
            let data;
            try { data = await response.json(); }
            catch (_) { data = {ok: false, mensaje: 'Respuesta inválida del servidor.'}; }
            if (!response.ok || !data.ok) {
                throw new Error(data.mensaje || 'No se pudo guardar tu calificación.');
            }

            selected = Number(data.mi_valoracion);
            if (average) average.textContent = formatNumber.format(Number(data.promedio || 0));
            if (total) total.textContent = Number(data.total || 0).toLocaleString('es-ES');
            if (userStatus) userStatus.textContent = 'Tu calificación: ' + selected + ' de 5. Puedes cambiarla cuando quieras.';
            message('¡Tu calificación se guardó correctamente!', false);
        } catch (error) {
            message(error.message || 'No se pudo guardar tu calificación.', true);
        } finally {
            saving = false;
            paint();
        }
    }

    stars.forEach(function (button) {
        const value = Number(button.dataset.ratingValue);
        button.addEventListener('mouseenter', function () { if (!saving) { hover = value; paint(); } });
        button.addEventListener('focus', function () { if (!saving) { hover = value; paint(); } });
        button.addEventListener('blur', function () { hover = 0; paint(); });
        button.addEventListener('click', function () { save(value); });
    });
    const group = panel.querySelector('.video-rating-stars');
    if (group) group.addEventListener('mouseleave', function () { hover = 0; paint(); });
    paint();
})();
