/* V4.5.1.2: dismiss the existing mobile navigation without touching public.js. */
(function () {
    'use strict';
    function start() {
        var button = document.getElementById('publicMenuToggle');
        var sidebar = document.getElementById('publicSidebar');
        var nav = document.getElementById('publicNavLinks');
        if (!button || !sidebar) return;
        var backdrop = document.createElement('div');
        backdrop.className = 'public-menu-backdrop';
        backdrop.setAttribute('aria-hidden', 'true');
        document.body.appendChild(backdrop);

        function closeMenu() {
            document.body.classList.remove('public-menu-open');
            sidebar.classList.remove('is-mobile-open');
            if (nav) nav.classList.remove('is-mobile-open');
            button.setAttribute('aria-expanded', 'false');
        }
        backdrop.addEventListener('click', closeMenu);
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && document.body.classList.contains('public-menu-open')) {
                closeMenu();
                button.focus();
            }
        });
        sidebar.addEventListener('click', function (event) {
            if (event.target.closest('a')) closeMenu();
        });
        if (nav) {
            nav.addEventListener('click', function (event) {
                if (event.target.closest('a')) closeMenu();
            });
        }
        var media = window.matchMedia('(min-width: 1201px)');
        function onBreakpointChange() { closeMenu(); }
        if (media.addEventListener) media.addEventListener('change', onBreakpointChange);
        else if (media.addListener) media.addListener(onBreakpointChange);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once: true});
    else start();
})();
