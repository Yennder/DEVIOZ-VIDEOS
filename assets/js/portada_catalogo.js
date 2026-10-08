/* DEVIOZ VIDEOS - PATCH V4.5.1: portada audiovisual y catálogos compactos. */
(function () {
    'use strict';

    function iniciarPortada() {
        const portada = document.querySelector('.platform-hero--video');
        if (!portada) return;

        const video = portada.querySelector('.platform-hero-video');
        if (!video) return;

        const movimientoReducido = window.matchMedia
            ? window.matchMedia('(prefers-reduced-motion: reduce)')
            : { matches: false };
        let visible = true;

        function actualizarReproduccion() {
            if (movimientoReducido.matches || document.hidden || !visible || portada.classList.contains('is-video-unavailable')) {
                video.pause();
                return;
            }
            const intento = video.play();
            if (intento && typeof intento.catch === 'function') {
                intento.catch(function () {
                    // Si el navegador bloquea autoplay, permanece el fondo alternativo.
                });
            }
        }

        function mostrarFotograma() {
            portada.classList.add('has-video-frame');
        }

        function mostrarFondoAlternativo() {
            portada.classList.add('is-video-unavailable');
            portada.classList.remove('has-video-frame');
            video.pause();
        }

        video.addEventListener('loadeddata', mostrarFotograma);
        video.addEventListener('error', mostrarFondoAlternativo);
        if (video.readyState >= 2) mostrarFotograma();
        if (video.error) mostrarFondoAlternativo();

        document.addEventListener('visibilitychange', actualizarReproduccion);
        if (typeof movimientoReducido.addEventListener === 'function') {
            movimientoReducido.addEventListener('change', actualizarReproduccion);
        } else if (typeof movimientoReducido.addListener === 'function') {
            movimientoReducido.addListener(actualizarReproduccion);
        }

        if ('IntersectionObserver' in window) {
            const observador = new IntersectionObserver(function (entradas) {
                visible = entradas[0].isIntersecting;
                actualizarReproduccion();
            }, { threshold: 0.05 });
            observador.observe(portada);
        } else {
            actualizarReproduccion();
        }
    }

    function columnasReales(contenedor) {
        const estilos = window.getComputedStyle(contenedor);
        if (estilos.display !== 'grid') return 1;
        const pistas = estilos.gridTemplateColumns.trim();
        if (!pistas || pistas === 'none') return 1;
        // gridTemplateColumns computado devuelve los anchos reales de las columnas.
        return Math.max(1, pistas.split(/\s+/).length);
    }

    function iniciarCatalogos() {
        const selectores = [
            '.public-content .grid-videos',
            '.public-content .smart-entity-grid',
            '.public-content .playlist-video-list'
        ];
        const contenedores = Array.from(document.querySelectorAll(selectores.join(', ')));
        if (!contenedores.length) return;

        const actualizar = [];
        contenedores.forEach(function (contenedor, indice) {
            const elementos = Array.from(contenedor.children);
            if (!elementos.length) return;

            let expandido = false;
            if (!contenedor.id) contenedor.id = 'catalogo-v451-' + (indice + 1);
            contenedor.classList.add('catalogo-grid-managed');

            const controles = document.createElement('div');
            controles.className = 'catalogo-toggle-wrap';
            controles.hidden = true;

            const boton = document.createElement('button');
            boton.className = 'catalogo-toggle';
            boton.type = 'button';
            boton.setAttribute('aria-controls', contenedor.id);
            boton.setAttribute('aria-expanded', 'false');

            const etiqueta = document.createElement('span');
            const icono = document.createElement('span');
            icono.className = 'catalogo-toggle-icon';
            icono.setAttribute('aria-hidden', 'true');
            boton.append(etiqueta, icono);
            controles.appendChild(boton);
            contenedor.insertAdjacentElement('afterend', controles);

            const seccion = contenedor.closest('section');
            const tituloSeccion = seccion ? seccion.querySelector('h1, h2') : null;
            const descripcion = tituloSeccion ? tituloSeccion.textContent.trim() : 'contenido';

            function renderizar() {
                // No ocultar nada si el contenedor está temporalmente fuera del layout.
                if (contenedor.clientWidth === 0) return;
                const limite = columnasReales(contenedor) * 2;
                const tieneResto = elementos.length > limite;
                if (!tieneResto) expandido = false;

                elementos.forEach(function (elemento, posicion) {
                    elemento.hidden = tieneResto && !expandido && posicion >= limite;
                });

                controles.hidden = !tieneResto;
                boton.setAttribute('aria-expanded', String(expandido));
                etiqueta.textContent = expandido ? 'Ver menos' : 'Ver más';
                icono.textContent = expandido ? '⌃' : '⌄';
                boton.setAttribute('aria-label',
                    (expandido ? 'Ver menos en ' : 'Ver más en ') + descripcion);
            }

            boton.addEventListener('click', function () {
                expandido = !expandido;
                renderizar();
                if (!expandido) {
                    // Mantener visible el botón si al contraer queda por encima del scroll.
                    boton.scrollIntoView({ block: 'nearest', behavior: 'auto' });
                }
            });

            actualizar.push(renderizar);
            renderizar();
        });

        if (!actualizar.length) return;
        let pendiente = false;
        function actualizarEnSiguienteFrame() {
            if (pendiente) return;
            pendiente = true;
            window.requestAnimationFrame(function () {
                pendiente = false;
                actualizar.forEach(function (renderizar) { renderizar(); });
            });
        }

        window.addEventListener('resize', actualizarEnSiguienteFrame, { passive: true });
        if ('ResizeObserver' in window) {
            const principal = document.querySelector('.public-content');
            if (principal) {
                let anchoPrevio = principal.clientWidth;
                const observador = new ResizeObserver(function () {
                    const anchoActual = principal.clientWidth;
                    if (anchoActual !== anchoPrevio) {
                        anchoPrevio = anchoActual;
                        actualizarEnSiguienteFrame();
                    }
                });
                observador.observe(principal);
            }
        }
    }

    function iniciar() {
        iniciarPortada();
        iniciarCatalogos();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar, { once: true });
    } else {
        iniciar();
    }
})();
