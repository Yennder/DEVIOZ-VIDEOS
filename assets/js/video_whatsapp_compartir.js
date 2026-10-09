/* DEVIOZ VIDEOS V4.5.5 - Compartir un video despues de contestar su cuestionario.
 * Solo crea un enlace oficial de WhatsApp (Click to Chat). No envia mensajes,
 * no registra telefonos y no presupone que el mensaje fue enviado.
 */
(function () {
    'use strict';

    const modal = document.getElementById('vqWhatsappDialog');
    if (!modal) return;

    const formulario = document.getElementById('vqWhatsappForm');
    const comentario = document.getElementById('vqWhatsappComentario');
    const pais = document.getElementById('vqWhatsappPais');
    const numero = document.getElementById('vqWhatsappNumero');
    const enlace = document.getElementById('vqWhatsappEnlace');
    const enlaceAviso = document.getElementById('vqWhatsappEnlaceAviso');
    const vistaPrevia = document.getElementById('vqWhatsappVistaPrevia');
    const errorBox = document.getElementById('vqWhatsappError');
    const estado = document.getElementById('vqWhatsappEstado');
    const enviar = document.getElementById('vqWhatsappEnviar');
    const despues = document.getElementById('vqWhatsappDespues');
    const abrirOtraVez = document.getElementById('vqWhatsappAbrir');

    if (!formulario || !comentario || !pais || !numero || !enlace || !vistaPrevia || !enviar) return;

    const idVideo = modal.dataset.videoId;
    const titulo = modal.dataset.videoTitulo || 'este video de DEVIOZ VIDEOS';
    const ubicacion = new URL(window.location.href);
    const esLocal = !['http:', 'https:'].includes(ubicacion.protocol) || /^(localhost|127(?:\.\d{1,3}){3}|\[::1\])$/i.test(ubicacion.hostname);

    if (!esLocal) {
        enlace.value = new URL('detalle.php?id=' + encodeURIComponent(idVideo), ubicacion.href).href;
        if (enlaceAviso) enlaceAviso.textContent = 'Este enlace se incluirá en el mensaje para que puedan abrir el video.';
    } else if (enlaceAviso) {
        enlaceAviso.textContent = 'Estás usando localhost. No incluiremos un enlace local inaccesible para el destinatario; puedes escribir aquí una URL pública cuando la tengas.';
    }

    function mensajeWhatsapp() {
        const lineas = [
            'Hola, quiero compartirte un video de DEVIOZ VIDEOS:',
            titulo,
            '',
            'Lo que me pareció interesante:',
            comentario.value.trim() || '(Escribe aquí tu comentario)',
        ];
        if (enlace.value.trim()) lineas.push('', 'Ver video: ' + enlace.value.trim());
        return lineas.join('\n');
    }

    function actualizarPrevia() {
        vistaPrevia.textContent = mensajeWhatsapp();
        if (!errorBox.hidden) ocultarError();
    }

    function mostrarError(texto, campo) {
        errorBox.hidden = false;
        errorBox.textContent = texto;
        if (campo) campo.focus();
    }

    function ocultarError() {
        errorBox.hidden = true;
        errorBox.textContent = '';
    }

    function cerrar() {
        if (typeof modal.close === 'function' && modal.open) {
            modal.close();
        } else {
            modal.removeAttribute('open');
            modal.classList.remove('vq-wa-abierto');
        }
    }

    function abrir() {
        if (modal.open) return;
        try {
            if (typeof modal.showModal === 'function') modal.showModal();
            else {
                modal.setAttribute('open', '');
                modal.classList.add('vq-wa-abierto');
            }
            comentario.focus();
        } catch (e) {
            // Un fallo del modal no debe impedir ver la nota o navegar.
        }
    }

    function validar() {
        const texto = comentario.value.trim();
        if (texto.length < 3 || texto.length > 600) {
            mostrarError('Escribe un comentario sobre lo que aprendiste (mínimo 3 caracteres).', comentario);
            return null;
        }
        const prefijo = pais.value;
        if (!/^\d{1,4}$/.test(prefijo)) {
            mostrarError('Selecciona un prefijo de país válido.', pais);
            return null;
        }
        const raw = numero.value.trim();
        if (!/^[0-9 .()\-]+$/.test(raw)) {
            mostrarError('Introduce solo el número nacional; el prefijo se agrega automáticamente.', numero);
            return null;
        }
        const nacional = raw.replace(/\D/g, '');
        const completo = prefijo + nacional;
        if (!nacional || nacional.startsWith('0') || nacional.length < 6 || completo.length < 8 || completo.length > 15) {
            mostrarError('Revisa el número: debe estar completo, sin cero inicial y sin el prefijo internacional.', numero);
            return null;
        }
        if (prefijo === '51' && !/^9\d{8}$/.test(nacional)) {
            mostrarError('Para Perú introduce un celular de 9 dígitos que empiece con 9.', numero);
            return null;
        }
        let url = '';
        if (enlace.value.trim()) {
            try {
                const parsed = new URL(enlace.value.trim());
                if (!['https:', 'http:'].includes(parsed.protocol)) throw new Error('Protocolo no permitido');
                if (/^(localhost|127(?:\.\d{1,3}){3}|\[::1\])$/i.test(parsed.hostname)) {
                    mostrarError('Un enlace localhost no será accesible al destinatario. Bórralo o usa una URL pública.', enlace);
                    return null;
                }
                if (parsed.href.length > 2000) throw new Error('Enlace demasiado largo');
                url = parsed.href;
            } catch (e) {
                mostrarError('Introduce una URL pública válida o deja el enlace vacío.', enlace);
                return null;
            }
        }
        return { telefono: completo, link: url };
    }

    // Enlace nativo: el navegador abre WhatsApp desde la accion directa del usuario.
    enviar.addEventListener('click', function (event) {
        ocultarError();
        const valores = validar();
        if (!valores) {
            event.preventDefault();
            enviar.setAttribute('href', '#');
            return;
        }
        if (valores.link) enlace.value = valores.link;
        const mensaje = mensajeWhatsapp();
        const destino = 'https://wa.me/' + valores.telefono + '?text=' + encodeURIComponent(mensaje);
        enviar.setAttribute('href', destino);
        if (estado) {
            estado.hidden = false;
            estado.textContent = 'WhatsApp debería abrirse en otra pestaña. Completa el envío allí: DEVIOZ no puede verificar si enviaste el mensaje.';
        }
    });

    comentario.addEventListener('input', actualizarPrevia);
    enlace.addEventListener('input', actualizarPrevia);
    pais.addEventListener('change', function () {
        if (pais.value === '51') numero.placeholder = '987 654 321';
        else numero.placeholder = 'Número nacional sin prefijo';
        ocultarError();
    });
    numero.addEventListener('input', ocultarError);
    if (despues) despues.addEventListener('click', cerrar);
    if (abrirOtraVez) abrirOtraVez.addEventListener('click', abrir);
    actualizarPrevia();
    if (modal.dataset.abrirAuto === '1') {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', abrir, { once: true });
        else abrir();
    }
})();
