(function(){
    'use strict';
    const list = document.getElementById('chapterEditorList');
    const add = document.getElementById('btnAddChapter');
    if(!list || !add) return;

    const escapeAttr = (value) => String(value ?? '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    const formatTime = (seconds) => {
        seconds = Math.max(0, Math.round(Number(seconds || 0)));
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        return h > 0
            ? [h,m,s].map(v=>String(v).padStart(2,'0')).join(':')
            : [m,s].map(v=>String(v).padStart(2,'0')).join(':');
    };
    const parseTime = (value) => {
        const text = String(value ?? '').trim();
        if(/^\d+(?:\.\d{1,3})?$/.test(text)) return Number(text);
        const parts = text.split(':');
        if(parts.length === 2 && /^\d+$/.test(parts[0]) && /^[0-5]\d$/.test(parts[1])) {
            return Number(parts[0]) * 60 + Number(parts[1]);
        }
        if(parts.length === 3 && /^\d+$/.test(parts[0]) && /^[0-5]\d$/.test(parts[1]) && /^[0-5]\d$/.test(parts[2])) {
            return Number(parts[0]) * 3600 + Number(parts[1]) * 60 + Number(parts[2]);
        }
        return NaN;
    };
    const renumber = () => {
        list.querySelectorAll('[data-chapter-row]').forEach((row,index)=>{
            const number = row.querySelector('[data-chapter-number]');
            if(number) number.textContent = String(index+1);
        });
    };
    const bindRemove = (root=document) => {
        root.querySelectorAll('[data-remove-chapter]').forEach(btn=>{
            if(btn.dataset.bound === '1') return;
            btn.dataset.bound = '1';
            btn.addEventListener('click',()=>{
                const rows = list.querySelectorAll('[data-chapter-row]');
                if(rows.length <= 1){
                    alert('El borrador debe conservar al menos un capítulo.');
                    return;
                }
                btn.closest('[data-chapter-row]')?.remove();
                renumber();
            });
        });
    };

    add.addEventListener('click',()=>{
        const rows = Array.from(list.querySelectorAll('[data-chapter-row]'));
        const lastStart = rows.length ? parseTime(rows[rows.length-1].querySelector('input[name="inicio[]"]')?.value) : 0;
        const duration = Number(list.dataset.duration || 0);
        const remaining = Math.max(0, duration - lastStart);
        const suggested = duration > 0 ? lastStart + Math.min(60, Math.max(0, Math.floor(remaining / 2))) : lastStart + 60;
        const row = document.createElement('article');
        row.className = 'ai-chapter-editor-row';
        row.setAttribute('data-chapter-row','');
        row.innerHTML = `
            <div class="ai-chapter-editor-number" data-chapter-number>${rows.length+1}</div>
            <div class="ai-chapter-editor-fields">
                <div class="ai-chapter-editor-grid">
                    <label><span>Inicio</span><input type="text" name="inicio[]" value="${escapeAttr(formatTime(suggested))}" placeholder="00:00" required></label>
                    <label class="is-wide"><span>Título</span><input type="text" name="titulo[]" maxlength="180" value="" required></label>
                </div>
                <label><span>Resumen de la escena</span><textarea name="resumen[]" rows="2" maxlength="2000"></textarea></label>
                <label><span>Conceptos <small>(separados por coma)</small></span><input type="text" name="conceptos[]" maxlength="600" value=""></label>
            </div>
            <button type="button" class="ai-chapter-remove" data-remove-chapter aria-label="Eliminar capítulo">×</button>`;
        list.appendChild(row);
        bindRemove(row);
        row.querySelector('input[name="titulo[]"]')?.focus();
    });

    const form = document.getElementById('chapterDraftForm');
    if(form) {
        form.addEventListener('input', (event) => {
            if(event.target.matches('input[name="inicio[]"]')) event.target.setCustomValidity('');
        });
        form.addEventListener('submit', (event) => {
            const inputs = Array.from(list.querySelectorAll('input[name="inicio[]"]'));
            const duration = Number(list.dataset.duration || 0);
            const used = new Set();
            for(const input of inputs) {
                input.setCustomValidity('');
                const seconds = parseTime(input.value);
                let error = '';
                if(!Number.isFinite(seconds) || seconds < 0) {
                    error = 'Usa MM:SS o HH:MM:SS, por ejemplo 02:35.';
                } else if(duration > 0 && seconds >= duration) {
                    error = 'El inicio debe ser anterior a la duracion del video.';
                } else if(used.has(seconds.toFixed(3))) {
                    error = 'Dos capitulos no pueden empezar en el mismo momento.';
                }
                if(error) {
                    event.preventDefault();
                    input.setCustomValidity(error);
                    input.reportValidity();
                    input.focus();
                    return;
                }
                used.add(seconds.toFixed(3));
            }
        });
    }

    bindRemove();
})();
