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
        const parts = String(value || '').trim().split(':').map(Number);
        if(parts.some(Number.isNaN)) return 0;
        if(parts.length === 2) return Math.max(0, parts[0]*60 + parts[1]);
        if(parts.length === 3) return Math.max(0, parts[0]*3600 + parts[1]*60 + parts[2]);
        return Math.max(0, Number(value || 0));
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
        const suggested = duration > 0 ? Math.min(duration, lastStart + 60) : lastStart + 60;
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

    bindRemove();
})();
