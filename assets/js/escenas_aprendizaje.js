/* DEVIOZ V4.4.4: fragment-by-fragment learning. Never changes official course progress. */
(function () {
    'use strict';
    const root = document.querySelector('[data-scene-study]');
    const section = document.querySelector('[data-smart-chapters]');
    const video = document.getElementById('deviozVideoPlayer');
    if (!root || !section || !video) return;

    const cards = Array.from(section.querySelectorAll('[data-smart-chapter]'));
    if (!cards.length) return;

    const find = selector => root.querySelector(selector);
    const panel = find('[data-scene-study-panel]');
    const openButton = find('[data-scene-study-open]');
    const closeButton = find('[data-scene-study-close]');
    const status = find('[data-scene-study-status]');
    const noteInput = find('[data-scene-study-note]');
    const counter = find('[data-scene-study-counter]');
    const title = find('[data-scene-study-title]');
    const summary = find('[data-scene-study-summary]');
    const concepts = find('[data-scene-study-concepts]');
    const progressText = find('[data-scene-study-total]');
    const progressBar = find('[data-scene-study-progress]');
    const prevButton = find('[data-scene-study-prev]');
    const nextButton = find('[data-scene-study-next]');
    const playButton = find('[data-scene-study-play]');
    const saveButton = find('[data-scene-study-save]');
    const completeButton = find('[data-scene-study-complete]');
    const authenticated = root.dataset.studyAuthenticated === '1';
    const ready = root.dataset.studyReady === '1';
    const videoId = Number(root.dataset.studyVideo || 0);
    const csrfToken = String(root.dataset.studyCsrf || '');
    const interactions = window.DEVIOZ_INTERACTIONS || {};
    const learningMode = Boolean(interactions.learningMode && !interactions.learningLessonCompleted);
    let maxWatched = Math.max(0, Number(interactions.learningMaxSeconds || 0));
    let selected = -1;
    let watching = false;
    let busy = false;
    const tr = value => value === 'de' ? (window.DEVIOZ_LANG === 'en' ? 'of' : 'de') : (window.DEVIOZ_I18N?.t?.(value) || value);
    const data = cards.map(card => ({
        card,
        id: Number(card.dataset.chapterId || 0),
        start: Number(card.dataset.chapterStart || 0),
        end: Number(card.dataset.chapterEnd || 0),
        title: card.dataset.chapterTitle || '',
        summary: card.querySelector('.smart-chapter-summary')?.textContent?.trim() || '',
        concepts: Array.from(card.querySelectorAll('.smart-chapter-tag')).map(node => node.textContent.trim()),
        reviewed: card.dataset.chapterReviewed === '1',
        savedNote: card.dataset.chapterNote || '',
        draft: card.dataset.chapterNote || '',
    }));

    function show(message, error = false) {
        if (!status) return;
        status.textContent = tr(message);
        status.classList.toggle('is-error', error);
    }

    function refreshProgress() {
        const done = data.filter(item => item.reviewed).length;
        const total = data.length;
        if (progressText) {
            const label = tr('repasadas');
            progressText.textContent = `${done} / ${total} ${label}`;
        }
        if (progressBar) progressBar.style.width = `${total ? done / total * 100 : 0}%`;
        data.forEach(item => {
            item.card.classList.toggle('is-reviewed', item.reviewed);
            const badge = item.card.querySelector('[data-chapter-reviewed-badge]');
            if (badge) badge.hidden = !item.reviewed;
        });
    }

    function render() {
        if (selected < 0 || !data[selected]) return;
        const item = data[selected];
        data.forEach((entry, index) => entry.card.classList.toggle('is-studying', index === selected));
        counter.textContent = `${tr('Escena')} ${selected + 1} ${tr('de')} ${data.length}`;
        title.textContent = item.title;
        summary.textContent = item.summary || tr('Esta escena no tiene un resumen publicado.');
        concepts.replaceChildren();
        item.concepts.forEach(concept => {
            const pill = document.createElement('span');
            pill.textContent = concept;
            concepts.appendChild(pill);
        });
        noteInput.value = item.draft;
        prevButton.disabled = selected === 0;
        nextButton.disabled = selected >= data.length - 1;
        completeButton.textContent = item.reviewed ? tr('✓ Escena repasada') : tr('✓ Marcar como repasada');
        completeButton.disabled = busy || !authenticated || !ready;
        saveButton.disabled = busy || !authenticated || !ready;
        show(item.reviewed ? 'Esta escena ya está marcada como repasada.' : 'Reproduce el fragmento y escribe una idea para tu reflexión.');
    }

    function select(index) {
        if (busy || index < 0 || index >= data.length) return;
        if (selected >= 0) data[selected].draft = noteInput.value;
        if (watching) video.pause();
        selected = index;
        watching = false;
        panel.hidden = false;
        openButton.setAttribute('aria-expanded', 'true');
        openButton.textContent = tr('Ocultar estudio');
        render();
    }

    function open(index = null) {
        if (index === null) {
            index = data.findIndex(entry => !entry.reviewed);
            if (index < 0) index = 0;
        }
        select(index);
        panel.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'nearest' });
    }

    openButton?.addEventListener('click', () => {
        if (panel.hidden) open();
        else close();
    });
    function close() {
        watching = false;
        panel.hidden = true;
        openButton.setAttribute('aria-expanded', 'false');
        openButton.textContent = tr('Estudiar por escenas');
        data.forEach(entry => entry.card.classList.remove('is-studying'));
    }
    closeButton?.addEventListener('click', close);

    cards.forEach((card, index) => {
        card.querySelector('[data-scene-study-select]')?.addEventListener('click', () => open(index));
    });
    noteInput?.addEventListener('input', () => {
        if (selected >= 0) data[selected].draft = noteInput.value;
    });
    prevButton?.addEventListener('click', () => select(selected - 1));
    nextButton?.addEventListener('click', () => select(selected + 1));

    function canJump(time) {
        if (!learningMode) return true;
        if (time <= maxWatched + 2.5) return true;
        show('Esta escena aún está bloqueada por el avance obligatorio de la capacitación.', true);
        return false;
    }

    playButton?.addEventListener('click', () => {
        if (selected < 0) return;
        const scene = data[selected];
        if (!canJump(scene.start)) return;
        // Use the established chapter handler to respect Learning Lab's seek restrictions.
        scene.card.querySelector('[data-chapter-seek]')?.click();
        watching = true;
        show('Reproduciendo solo este fragmento. Al terminar podrás escribir tu reflexión.');
    });

    video.addEventListener('timeupdate', () => {
        const now = Number(video.currentTime || 0);
        // Do not invent academic progress: this is only used to avoid offering
        // scene jumps that Learning Lab has not already unlocked.
        if (learningMode && !video.seeking && now <= maxWatched + 3 && !video.paused) {
            maxWatched = Math.max(maxWatched, now);
        }
        if (!watching || selected < 0) return;
        const scene = data[selected];
        const lastMoment = Math.max(scene.start, scene.end - 0.35);
        if (now >= lastMoment && scene.end > scene.start) {
            watching = false;
            video.pause();
            show('Fragmento terminado. Escribe una reflexión y guarda tu avance.');
            if (!panel.hidden) panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    });
    video.addEventListener('ended', () => { if (watching) watching = false; });

    async function save(reviewed) {
        if (busy || selected < 0) return;
        if (reviewed && !canJump(data[selected].start)) return;
        if (!authenticated || !ready) {
            show('Inicia sesión y verifica la migración para guardar escenas.', true);
            return;
        }
        const item = data[selected];
        const note = noteInput.value.trim();
        if (Array.from(note).length > 1200) {
            show('La reflexión puede tener como máximo 1200 caracteres.', true);
            return;
        }
        if (reviewed && Array.from(note).length < 12) {
            show('Escribe al menos 12 caracteres sobre lo que aprendiste.', true);
            noteInput.focus();
            return;
        }
        busy = true;
        saveButton.disabled = true;
        completeButton.disabled = true;
        show('Guardando tu reflexión...');
        try {
            const response = await fetch('../api/escenas_aprendizaje.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    id_video: videoId,
                    id_capitulo: item.id,
                    nota: note,
                    completada: Boolean(reviewed)
                })
            });
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.mensaje || tr('No fue posible guardar la escena.'));
            item.reviewed = Boolean(result.completada);
            item.savedNote = String(result.nota || '');
            item.draft = item.savedNote;
            noteInput.value = item.savedNote;
            item.card.dataset.chapterReviewed = item.reviewed ? '1' : '0';
            item.card.dataset.chapterNote = item.savedNote;
            refreshProgress();
            show('Tu avance se guardó correctamente.');
        } catch (error) {
            show(error?.message || 'No fue posible guardar la escena.', true);
        } finally {
            busy = false;
            saveButton.disabled = !authenticated || !ready;
            completeButton.disabled = !authenticated || !ready;
            completeButton.textContent = item.reviewed ? tr('✓ Escena repasada') : tr('✓ Marcar como repasada');
        }
    }
    saveButton?.addEventListener('click', () => save(false));
    completeButton?.addEventListener('click', () => save(true));

    refreshProgress();
    const query = new URLSearchParams(window.location.search);
    if (query.get('estudiar') === '1') {
        const requested = Number(query.get('escena') || 0);
        const idx = requested > 0 ? data.findIndex(item => item.id === requested) : -1;
        open(idx >= 0 ? idx : null);
    }
})();
