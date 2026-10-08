(function(){
    'use strict';
    const section = document.querySelector('[data-smart-chapters]');
    const player = document.getElementById('deviozVideoPlayer');
    if(!section || !player) return;

    const items = Array.from(section.querySelectorAll('[data-smart-chapter]'));
    const currentBox = section.querySelector('[data-current-chapter]');
    const currentTitle = section.querySelector('[data-current-chapter-title]');
    const notice = section.querySelector('[data-smart-chapter-notice]');
    let lastActive = -1;
    const learningConfig = window.DEVIOZ_INTERACTIONS || {};
    // Learning progress grows while the user watches. A static max from page load
    // would keep newly watched scenes locked until refreshing the page.
    let maxWatchedNow = Math.max(0, Number(learningConfig.learningMaxSeconds || 0));

    const showNotice = (message) => {
        if(!notice) return;
        notice.textContent = message;
        notice.hidden = false;
        window.clearTimeout(showNotice.timer);
        showNotice.timer = window.setTimeout(()=>{ notice.hidden = true; }, 4500);
    };

    const learningAllows = (target) => {
        if(!learningConfig.learningMode || learningConfig.learningLessonCompleted) return true;
        if(target <= maxWatchedNow + 2.5) return true;
        showNotice('Esta escena todavía está bloqueada por el avance secuencial de la capacitación. Continúa viendo el video para desbloquearla.');
        return false;
    };

    const setActive = (time) => {
        let active = -1;
        items.forEach((item,index)=>{
            const start = Number(item.dataset.chapterStart || 0);
            const end = Number(item.dataset.chapterEnd || Number.POSITIVE_INFINITY);
            const isActive = time >= start && (time < end || index === items.length-1);
            item.classList.toggle('is-active', isActive);
            const progress = item.querySelector('[data-chapter-progress]');
            if(progress){
                const span = Math.max(.001, end-start);
                const pct = isActive ? Math.max(0,Math.min(100,((time-start)/span)*100)) : (time >= end ? 100 : 0);
                progress.style.width = pct + '%';
            }
            if(isActive) active = index;
        });
        if(active !== lastActive){
            lastActive = active;
            if(active >= 0){
                if(currentBox) currentBox.hidden = false;
                if(currentTitle) currentTitle.textContent = items[active].dataset.chapterTitle || 'Escena actual';
            }else if(currentBox){
                currentBox.hidden = true;
            }
        }
    };

    items.forEach(item=>{
        const btn = item.querySelector('[data-chapter-seek]');
        btn?.addEventListener('click',()=>{
            const target = Math.max(0,Number(item.dataset.chapterStart || 0));
            if(!learningAllows(target)) return;
            try{ player.currentTime = target; }catch(e){}
            player.scrollIntoView({behavior:'smooth',block:'center'});
            const playPromise = player.play();
            if(playPromise && typeof playPromise.catch === 'function') playPromise.catch(()=>{});
            setActive(target);
        });
    });

    player.addEventListener('timeupdate',()=>{
        const current = Math.max(0, Number(player.currentTime || 0));
        if(learningConfig.learningMode && !learningConfig.learningLessonCompleted && !player.seeking) {
            maxWatchedNow = Math.max(maxWatchedNow, current);
        }
        setActive(current);
    });
    player.addEventListener('loadedmetadata',()=>setActive(Number(player.currentTime || 0)));
    setActive(Number(player.currentTime || 0));
})();
