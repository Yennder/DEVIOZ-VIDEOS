/* DEVIOZ VIDEOS V4.5.5 - Compartir un video despues de contestar su cuestionario.
 * Solo crea un enlace oficial de WhatsApp (Click to Chat). No envia mensajes,
 * no registra telefonos y no presupone que el mensaje fue enviado.
 */
(function () {
    'use strict';

    // V4.5.6: el mensaje que recibirá el contacto usa el idioma elegido,
    // sin traducir el comentario ni el título que escribió su autor.
    const idioma = ['es', 'en', 'pt'].includes(window.DEVIOZ_LANG) ? window.DEVIOZ_LANG : 'es';
    const textosIdioma = {
        es: {
            intro: '¡Hola! Te recomiendo un video de *DEVIOZ VIDEOS*.',
            interes: '*Esto fue lo que más me interesó:*',
            link: '*Mira el video aquí:*',
            cierre: '_¡Aprendamos y compartamos conocimiento!_',
            comentario: '(Aquí aparecerá tu comentario)'
        },
        en: {
            intro: 'Hi! I recommend a video from *DEVIOZ VIDEOS*.',
            interes: '*What I found most interesting:*',
            link: '*Watch the video here:*',
            cierre: '_Let’s learn and share knowledge!_',
            comentario: '(Your comment will appear here)'
        },
        pt: {
            intro: 'Olá! Recomendo um vídeo do *DEVIOZ VIDEOS*.',
            interes: '*O que achei mais interessante:*',
            link: '*Assista ao vídeo aqui:*',
            cierre: '_Vamos aprender e compartilhar conhecimento!_',
            comentario: '(Seu comentário aparecerá aqui)'
        }
    }[idioma];
    const t = text => typeof window.deviozTraducir === 'function' ? window.deviozTraducir(text) : text;

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
    const enlacePublicoConfigurado = modal.dataset.videoEnlacePublico || '';
    const ubicacion = new URL(window.location.href);
    const esLocal = !['http:', 'https:'].includes(ubicacion.protocol) || /^(localhost|127(?:\.\d{1,3}){3}|\[::1\])$/i.test(ubicacion.hostname);

    if (enlacePublicoConfigurado) {
        enlace.value = enlacePublicoConfigurado;
        if (enlaceAviso) enlaceAviso.textContent = t('Enlace generado automáticamente para este video desde la configuración del administrador.');
    } else if (!esLocal) {
        enlace.value = new URL('detalle.php?id=' + encodeURIComponent(idVideo), ubicacion.href).href;
        if (enlaceAviso) enlaceAviso.textContent = t('El enlace directo a este video se incluirá en tu mensaje.');
    } else if (enlaceAviso) {
        enlaceAviso.textContent = t('DEVIOZ está en localhost: para incluir un enlace que funcione fuera de tu PC, configura el dominio público en Administración → Configuración o pega aquí un enlace público del video.');
    }

    // Emojis Unicode estandar compatibles con WhatsApp. Usar puntos de codigo
    // evita que una respuesta JS con charset incorrecto altere los simbolos.
    const simbolos = {
        saludo: String.fromCodePoint(0x1F44B),   // mano saludando
        video: String.fromCodePoint(0x1F3AC),    // claqueta
        idea: String.fromCodePoint(0x1F4A1),     // bombilla
        enlace: String.fromCodePoint(0x1F517),   // eslabon
        cierre: String.fromCodePoint(0x2728),    // destellos
    };

    function mensajeWhatsapp() {
        const comentarioUsuario = comentario.value.trim() || textosIdioma.comentario;
        const nombreVideo = titulo.replace(/\*/g, '').trim();
        const lineas = [
            simbolos.saludo + ' ' + textosIdioma.intro,
            '',
            simbolos.video + ' *' + nombreVideo + '*',
            '',
            simbolos.idea + ' ' + textosIdioma.interes,
            comentarioUsuario,
        ];
        if (enlace.value.trim()) {
            lineas.push('', simbolos.enlace + ' ' + textosIdioma.link, enlace.value.trim());
        }
        lineas.push('', simbolos.cierre + ' ' + textosIdioma.cierre);
        return lineas.join('\n');
    }

    // Previsualizacion sin innerHTML: permite negrita y cursiva sin riesgo de inyectar HTML.
    function actualizarPrevia() {
        const mensaje = mensajeWhatsapp();
        const fragmento = document.createDocumentFragment();
        mensaje.split(/(\*[^*\n]+\*|_[^_\n]+_)/g).forEach(function (parte) {
            if (!parte) return;
            const esNegrita = parte.startsWith('*') && parte.endsWith('*') && parte.length > 2;
            const esCursiva = parte.startsWith('_') && parte.endsWith('_') && parte.length > 2;
            if (esNegrita || esCursiva) {
                const elemento = document.createElement(esNegrita ? 'strong' : 'em');
                elemento.textContent = parte.slice(1, -1);
                fragmento.appendChild(elemento);
            } else {
                fragmento.appendChild(document.createTextNode(parte));
            }
        });
        vistaPrevia.replaceChildren(fragmento);
        if (!errorBox.hidden) ocultarError();
    }

    function mostrarError(texto, campo) {
        errorBox.hidden = false;
        errorBox.textContent = t(texto);
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
                const host = parsed.hostname.toLowerCase();
                const esPrivada = /^(?:localhost|127\.|10\.|0\.|192\.168\.|169\.254\.|172\.(?:1[6-9]|2\d|3[01])\.|\[::1\]$)/i.test(host)
                    || /\.(?:localhost|local|lan|internal|test|invalid)$/.test(host);
                if (esPrivada) {
                    mostrarError('El enlace local o privado no será accesible al destinatario. Usa una URL pública.', enlace);
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
        // V4.5.5.2: evitar wa.me, cuya redireccion puede sustituir emojis por U+FFFD.
        // El endpoint directo preserva la cadena UTF-8 codificada una sola vez.
        const destino = 'https://api.whatsapp.com/send?phone=' + valores.telefono
            + '&text=' + encodeURIComponent(mensaje);
        enviar.setAttribute('href', destino);
        if (estado) {
            estado.hidden = false;
            estado.textContent = 'Se abrirá WhatsApp con el mensaje preparado. Pulsa Enviar dentro de WhatsApp para completar el envío.';
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
