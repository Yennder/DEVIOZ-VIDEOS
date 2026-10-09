/* DEVIOZ V4.5.6 - Local interface translation without sending data to third parties.
 * Videos, transcript text, AI responses, and input values are not translated.
 */
(function () {
    'use strict';
    const raw = String(window.DEVIOZ_LANG || 'es');
    const lang = (raw === 'en' || raw === 'pt') ? raw : 'es';
    const dictionary = window.DEVIOZ_I18N_STRINGS && window.DEVIOZ_I18N_STRINGS[lang] || {};
    document.documentElement.lang = lang === 'pt' ? 'pt-BR' : lang;
    const normalize = value => String(value).trim().replace(/\s+/g, ' ');
    function translate(value) {
        if (lang === 'es' || typeof value !== 'string') return value;
        const text = normalize(value);
        if (!text || text.length > 450) return value;
        if (Object.prototype.hasOwnProperty.call(dictionary, text)) {
            const parts = /^(\s*)([\s\S]*?)(\s*)$/.exec(value);
            return parts ? parts[1] + dictionary[text] + parts[3] : dictionary[text];
        }
        // Small number-dependent interface labels; not user-authored content.
        let m = /^(\d+) (sin leer|vistas|valoraciones|preguntas|intentos)$/.exec(text);
        if (m) {
            const terms = {
                'sin leer': ['unread', 'não lidas'],
                vistas: ['views', 'visualizações'],
                valoraciones: ['ratings', 'avaliações'],
                preguntas: ['questions', 'perguntas'],
                intentos: ['attempts', 'tentativas']
            };
            return m[1] + ' ' + terms[m[2]][lang === 'en' ? 0 : 1];
        }
        m = /^Intento (\d+) registrado$/.exec(text);
        if (m) return lang === 'en' ? `Attempt ${m[1]} recorded` : `Tentativa ${m[1]} registrada`;
        return value;
    }
    window.deviozTraducir = translate;
    window.DEVIOZ_I18N = Object.freeze({ lang, t: translate });
    if (lang === 'es') return;

    const skipSelector = [
        'script', 'style', 'noscript', 'textarea', 'code', 'pre',
        '[contenteditable="true"]', '[data-i18n-ignore]',
        '.devioz-ai-message', '.ai-message', '.ai-message-content', '.ai-chat-message',
        '.comment-body', '.comment-content', '.transcription-text',
        '.transcript-segment', '.transcript-content', '.message-text',
        '.vq-video-name', '.video-info h3', '.video-card-description',
        '.smart-entity-card h3', '.learning-course-title'
    ].join(',');
    const skipElement = element => element && (element.closest(skipSelector) !== null);
    function translateText(node) {
        const parent = node.parentElement;
        if (!parent || skipElement(parent)) return;
        const source = node.nodeValue;
        if (!source || source.length > 600) return;
        const result = translate(source);
        if (result !== source) node.nodeValue = result;
    }
    function translateAttributes(el) {
        if (skipElement(el)) return;
        for (const name of ['title', 'placeholder', 'aria-label', 'alt']) {
            if (!el.hasAttribute(name)) continue;
            const current = el.getAttribute(name);
            const value = translate(current);
            if (value !== current) el.setAttribute(name, value);
        }
        if (el.matches('input[type="button"], input[type="submit"], input[type="reset"]')) {
            const source = el.value;
            const value = translate(source);
            if (value !== source) el.value = value;
        }
    }
    function translateTree(root) {
        if (!root) return;
        if (root.nodeType === Node.TEXT_NODE) return translateText(root);
        if (root.nodeType !== Node.ELEMENT_NODE && root.nodeType !== Node.DOCUMENT_NODE) return;
        if (root.nodeType === Node.ELEMENT_NODE && skipElement(root)) return;
        if (root.nodeType === Node.ELEMENT_NODE) translateAttributes(root);
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_TEXT);
        let item;
        while ((item = walker.nextNode())) {
            if (item.nodeType === Node.TEXT_NODE) translateText(item);
            else translateAttributes(item);
        }
    }
    function initialize() {
        document.title = translate(document.title);
        translateTree(document.body);
        // New notifications, search results and modals added by existing scripts.
        const observer = new MutationObserver(records => {
            for (const r of records) {
                if (r.type === 'characterData') translateText(r.target);
                if (r.type === 'childList') for (const node of r.addedNodes) translateTree(node);
                if (r.type === 'attributes' && r.target.nodeType === Node.ELEMENT_NODE) translateAttributes(r.target);
            }
        });
        observer.observe(document.body, { childList: true, subtree: true, characterData: true });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
    else initialize();
})();
