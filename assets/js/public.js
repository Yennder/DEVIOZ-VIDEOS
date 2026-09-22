// =================================
// DEVIOZ VIDEOS PUBLIC JS
// =================================


document.addEventListener(
"DOMContentLoaded",
function()
{


    // =================================
    // BUSCADOR - ENTER
    // =================================

    const buscador = document.querySelector(
        ".search-box input"
    );


    if(buscador)
    {

        buscador.addEventListener(
            "keypress",
            function(e)
            {

                if(e.key === "Enter")
                {

                    this.form.submit();

                }

            }
        );

    }




    // =================================
    // SELECTOR DE TEMPORADA
    // =================================

    const selector = document.getElementById(
        "selectorTemporada"
    );


    if(selector)
    {

        selector.addEventListener(
            "change",
            function()
            {

                const temporada = this.value;


                window.location.href =
                    "detalle.php?id_temporada="
                    + temporada;

            }
        );

    }




    // =================================
    // ELEMENTOS DEL PLAYER
    // =================================

    const config =
        window.DEVIOZ_PLAYER;


    const video =
        document.getElementById(
            "deviozVideoPlayer"
        );


    const playerBox =
        document.getElementById(
            "deviozPlayerBox"
        );


    const botonFullscreen =
        document.getElementById(
            "btnDeviozFullscreen"
        );


    const autoplaySwitch =
        document.getElementById(
            "autoplaySwitch"
        );




    // =================================
    // SI NO EXISTE VIDEO
    // =================================

    if(!video)
    {

        return;

    }




    // =================================
    // AUTOPLAY GUARDADO
    // =================================

    let autoplayActivo =
        localStorage.getItem(
            "devioz_autoplay"
        ) !== "off";


    if(autoplaySwitch)
    {

        autoplaySwitch.checked =
            autoplayActivo;


        autoplaySwitch.addEventListener(
            "change",
            function()
            {

                autoplayActivo =
                    this.checked;


                localStorage.setItem(
                    "devioz_autoplay",
                    autoplayActivo
                        ? "on"
                        : "off"
                );

            }
        );

    }




    // =================================
    // AUTOREPRODUCIR AL LLEGAR
    // DEL CAPITULO ANTERIOR
    // =================================

    const parametros =
        new URLSearchParams(
            window.location.search
        );


    if(
        autoplayActivo
        &&
        parametros.get("autoplay") === "1"
    )
    {

        video.play().catch(
            function(error)
            {

                console.log(
                    "El navegador bloqueó el autoplay con sonido.",
                    error
                );

            }
        );

    }




    // =================================
    // FULLSCREEN DEVIOZ
    // =================================

    async function alternarFullscreen()
    {

        if(!playerBox)
        {

            return;

        }


        try
        {

            const fullscreenActual =
                document.fullscreenElement
                ||
                document.webkitFullscreenElement;


            // ENTRAR FULLSCREEN

            if(!fullscreenActual)
            {

                if(playerBox.requestFullscreen)
                {

                    await playerBox.requestFullscreen();

                }
                else if(
                    playerBox.webkitRequestFullscreen
                )
                {

                    playerBox.webkitRequestFullscreen();

                }

            }


            // SALIR FULLSCREEN

            else
            {

                if(document.exitFullscreen)
                {

                    await document.exitFullscreen();

                }
                else if(
                    document.webkitExitFullscreen
                )
                {

                    document.webkitExitFullscreen();

                }

            }

        }
        catch(error)
        {

            console.error(
                "No se pudo cambiar el modo fullscreen:",
                error
            );

        }

    }




    // =================================
    // BOTON FULLSCREEN DEVIOZ
    // =================================

    if(botonFullscreen)
    {

        botonFullscreen.addEventListener(
            "click",
            function(e)
            {

                e.preventDefault();

                e.stopPropagation();


                alternarFullscreen();

            }
        );

    }




    // =================================
    // ESTADO FULLSCREEN
    // =================================

    function actualizarFullscreen()
    {

        const fullscreenActual =
            document.fullscreenElement
            ||
            document.webkitFullscreenElement;


        if(
            fullscreenActual === playerBox
        )
        {

            playerBox.classList.add(
                "fullscreen-activo"
            );


            if(botonFullscreen)
            {

                botonFullscreen.innerHTML =
                    "✕";


                botonFullscreen.title =
                    "Salir de pantalla completa";

            }

        }
        else
        {

            playerBox.classList.remove(
                "fullscreen-activo"
            );


            if(botonFullscreen)
            {

                botonFullscreen.innerHTML =
                    "⛶";


                botonFullscreen.title =
                    "Pantalla completa";

            }

        }

    }


    document.addEventListener(
        "fullscreenchange",
        actualizarFullscreen
    );


    document.addEventListener(
        "webkitfullscreenchange",
        actualizarFullscreen
    );




    // =================================
    // PLAY / PAUSE
    // =================================

    function alternarPlayPause()
    {

        if(video.ended)
        {

            video.currentTime = 0;

        }


        if(video.paused)
        {

            video.play().catch(
                function(error)
                {

                    console.log(
                        "No se pudo reproducir:",
                        error
                    );

                }
            );

        }
        else
        {

            video.pause();

        }

    }




    // =================================
    // CLICK SIMPLE / DOBLE CLICK
    // =================================

    let temporizadorClickVideo =
        null;


    video.addEventListener(
        "click",
        function(e)
        {

            const rect =
                video.getBoundingClientRect();


            const distanciaInferior =
                rect.bottom
                -
                e.clientY;


            // Dejamos libre la zona
            // de controles nativos

            if(distanciaInferior <= 60)
            {

                return;

            }


            e.preventDefault();


            if(document.activeElement === video)
            {

                video.blur();

            }


            if(temporizadorClickVideo)
            {

                clearTimeout(
                    temporizadorClickVideo
                );

            }


            temporizadorClickVideo =
                setTimeout(
                    function()
                    {

                        alternarPlayPause();


                        temporizadorClickVideo =
                            null;

                    },
                    220
                );

        }
    );




    // =================================
    // DOBLE CLICK = FULLSCREEN
    // =================================

    video.addEventListener(
        "dblclick",
        function(e)
        {

            const rect =
                video.getBoundingClientRect();


            const distanciaInferior =
                rect.bottom
                -
                e.clientY;


            if(distanciaInferior <= 60)
            {

                return;

            }


            e.preventDefault();

            e.stopPropagation();


            if(temporizadorClickVideo)
            {

                clearTimeout(
                    temporizadorClickVideo
                );


                temporizadorClickVideo =
                    null;

            }


            if(document.activeElement === video)
            {

                video.blur();

            }


            alternarFullscreen();

        }
    );




    // =================================
    // ATAJOS DE TECLADO
    // =================================

    document.addEventListener(
        "keydown",
        function(e)
        {


            if(e.repeat)
            {

                return;

            }


            const elemento =
                document.activeElement;


            if(
                elemento
                &&
                (
                    elemento.tagName === "INPUT"
                    ||
                    elemento.tagName === "TEXTAREA"
                    ||
                    elemento.tagName === "SELECT"
                    ||
                    elemento.isContentEditable
                )
            )
            {

                return;

            }


            // ESPACIO

            if(e.code === "Space")
            {

                e.preventDefault();

                e.stopPropagation();

                e.stopImmediatePropagation();


                if(document.activeElement === video)
                {

                    video.blur();

                }


                alternarPlayPause();


                return;

            }


            // FLECHA DERECHA

            if(e.code === "ArrowRight")
            {

                e.preventDefault();

                e.stopPropagation();

                e.stopImmediatePropagation();


                let nuevoTiempo =
                    video.currentTime + 5;


                if(
                    video.duration
                    &&
                    isFinite(video.duration)
                )
                {

                    nuevoTiempo =
                        Math.min(
                            video.duration,
                            nuevoTiempo
                        );

                }


                video.currentTime =
                    nuevoTiempo;


                return;

            }


            // FLECHA IZQUIERDA

            if(e.code === "ArrowLeft")
            {

                e.preventDefault();

                e.stopPropagation();

                e.stopImmediatePropagation();


                video.currentTime =
                    Math.max(
                        0,
                        video.currentTime - 5
                    );


                return;

            }


            // MUTE

            if(e.code === "KeyM")
            {

                e.preventDefault();

                e.stopPropagation();

                e.stopImmediatePropagation();


                video.muted =
                    !video.muted;


                return;

            }


            // FULLSCREEN

            if(e.code === "KeyF")
            {

                e.preventDefault();

                e.stopPropagation();

                e.stopImmediatePropagation();


                alternarFullscreen();


                return;

            }

        }
    );




    // =================================
    // AUTOPLAY VIDEOS INDEPENDIENTES
    // =================================

    if(
        config
        &&
        config.esSerie === false
    )
    {

        const siguienteRelacionado =
            config.siguienteRelacionado
            ||
            null;


        video.addEventListener(
            "ended",
            function()
            {

                if(!autoplayActivo)
                {
                    return;
                }

                let url = null;

                if(siguienteRelacionado)
                {
                    const idActual = Number(config && config.videoActual ? config.videoActual.id : 0);
                    const idSiguiente = Number(siguienteRelacionado.id || 0);

                    // Defensa adicional: nunca redirigir al mismo video ni a un ID invalido.
                    if(idSiguiente > 0 && idSiguiente !== idActual)
                    {
                        url = siguienteRelacionado.url;
                        url += url.includes("?") ? "&autoplay=1" : "?autoplay=1";
                    }
                }
                else if(config && config.esLearning && config.finalUrl)
                {
                    // Última lección: regresamos al curso para mostrar el examen.
                    url = config.finalUrl;
                }

                if(!url)
                {
                    return;
                }

                // En Learning damos una fracción de segundo para persistir el 100%
                // del video antes de abrir la siguiente lección.
                const demora = config && config.esLearning ? 550 : 0;
                window.setTimeout(function()
                {
                    window.location.href = url;
                }, demora);

            }
        );


        return;

    }




    // =================================
    // SERIES
    // =================================

    if(
        !config
        ||
        !config.esSerie
        ||
        !config.siguiente
    )
    {

        return;

    }




    const avisoSiguiente =
        document.getElementById(
            "nextEpisodeMini"
        );


    const overlay =
        document.getElementById(
            "nextEpisodeOverlay"
        );


    const botonSiguiente =
        document.getElementById(
            "btnNextEpisodeMini"
        );


    const botonAhora =
        document.getElementById(
            "btnNextNow"
        );


    const botonCancelar =
        document.getElementById(
            "btnNextCancel"
        );


    const contador =
        document.getElementById(
            "nextCountdown"
        );


    const barra =
        document.getElementById(
            "nextProgressBar"
        );




    const TIEMPO_AVISO =
        65;


    const TIEMPO_OVERLAY =
        10;


    let overlayActivo =
        false;


    let cancelado =
        false;


    let intervaloCuenta =
        null;




    function irSiguiente()
    {

        let url =
            config.siguiente.url;


        if(autoplayActivo)
        {

            url +=
                url.includes("?")
                    ? "&autoplay=1"
                    : "?autoplay=1";

        }


        window.location.href =
            url;

    }




    function ocultarAvisos()
    {

        if(avisoSiguiente)
        {

            avisoSiguiente.classList.remove(
                "visible"
            );

        }


        if(overlay)
        {

            overlay.classList.remove(
                "visible"
            );

        }

    }




    function mostrarOverlay()
    {

        if(
            !overlay
            ||
            overlayActivo
            ||
            cancelado
            ||
            !autoplayActivo
        )
        {

            return;

        }


        overlayActivo =
            true;


        if(avisoSiguiente)
        {

            avisoSiguiente.classList.remove(
                "visible"
            );

        }


        overlay.classList.add(
            "visible"
        );


        iniciarCuentaRegresiva();

    }




    function iniciarCuentaRegresiva()
    {

        if(intervaloCuenta)
        {

            clearInterval(
                intervaloCuenta
            );

        }


        intervaloCuenta =
            setInterval(
                function()
                {

                    if(
                        !video.duration
                        ||
                        !isFinite(
                            video.duration
                        )
                    )
                    {

                        return;

                    }


                    let restante =
                        Math.ceil(
                            video.duration
                            -
                            video.currentTime
                        );


                    restante =
                        Math.max(
                            0,
                            restante
                        );


                    if(contador)
                    {

                        contador.textContent =
                            restante;

                    }


                    if(barra)
                    {

                        let porcentaje =
                            (
                                restante
                                /
                                TIEMPO_OVERLAY
                            )
                            *
                            100;


                        porcentaje =
                            Math.max(
                                0,
                                Math.min(
                                    100,
                                    porcentaje
                                )
                            );


                        barra.style.width =
                            porcentaje
                            +
                            "%";

                    }


                    if(restante <= 0)
                    {

                        clearInterval(
                            intervaloCuenta
                        );


                        intervaloCuenta =
                            null;


                        if(
                            autoplayActivo
                            &&
                            !cancelado
                        )
                        {

                            irSiguiente();

                        }

                    }

                },
                250
            );

    }




    video.addEventListener(
        "timeupdate",
        function()
        {

            if(
                !video.duration
                ||
                !isFinite(
                    video.duration
                )
            )
            {

                return;

            }


            const tiempoRestante =
                video.duration
                -
                video.currentTime;


            if(
                overlayActivo
                &&
                tiempoRestante >
                    TIEMPO_OVERLAY
            )
            {

                overlayActivo =
                    false;


                if(intervaloCuenta)
                {

                    clearInterval(
                        intervaloCuenta
                    );


                    intervaloCuenta =
                        null;

                }


                if(overlay)
                {

                    overlay.classList.remove(
                        "visible"
                    );

                }

            }


            if(!autoplayActivo)
            {

                if(overlayActivo)
                {

                    overlayActivo =
                        false;


                    if(intervaloCuenta)
                    {

                        clearInterval(
                            intervaloCuenta
                        );


                        intervaloCuenta =
                            null;

                    }


                    if(overlay)
                    {

                        overlay.classList.remove(
                            "visible"
                        );

                    }

                }


                if(
                    tiempoRestante <=
                        TIEMPO_AVISO
                    &&
                    tiempoRestante > 0
                    &&
                    !cancelado
                )
                {

                    if(avisoSiguiente)
                    {

                        avisoSiguiente.classList.add(
                            "visible"
                        );

                    }

                }
                else
                {

                    if(avisoSiguiente)
                    {

                        avisoSiguiente.classList.remove(
                            "visible"
                        );

                    }

                }


                return;

            }


            if(
                tiempoRestante <=
                    TIEMPO_OVERLAY
                &&
                tiempoRestante > 0
            )
            {

                mostrarOverlay();


                return;

            }


            if(
                tiempoRestante <=
                    TIEMPO_AVISO
                &&
                tiempoRestante >
                    TIEMPO_OVERLAY
                &&
                !overlayActivo
                &&
                !cancelado
            )
            {

                if(avisoSiguiente)
                {

                    avisoSiguiente.classList.add(
                        "visible"
                    );

                }

            }
            else
            {

                if(avisoSiguiente)
                {

                    avisoSiguiente.classList.remove(
                        "visible"
                    );

                }

            }

        }
    );




    video.addEventListener(
        "ended",
        function()
        {

            if(
                autoplayActivo
                &&
                !cancelado
            )
            {

                irSiguiente();

            }

        }
    );




    if(botonSiguiente)
    {

        botonSiguiente.addEventListener(
            "click",
            function(e)
            {

                e.preventDefault();

                e.stopPropagation();


                irSiguiente();

            }
        );

    }




    if(botonAhora)
    {

        botonAhora.addEventListener(
            "click",
            function(e)
            {

                e.preventDefault();

                e.stopPropagation();


                irSiguiente();

            }
        );

    }




    if(botonCancelar)
    {

        botonCancelar.addEventListener(
            "click",
            function(e)
            {

                e.preventDefault();

                e.stopPropagation();


                cancelado =
                    true;


                overlayActivo =
                    false;


                if(intervaloCuenta)
                {

                    clearInterval(
                        intervaloCuenta
                    );


                    intervaloCuenta =
                        null;

                }


                ocultarAvisos();

            }
        );

    }


});




// =========================================
// CAMBIAR TEMPORADA
// FUNCION GLOBAL
// =========================================

function cambiarTemporada(
    idTemporada
)
{

    window.location.href =
        "detalle.php?id_temporada="
        +
        idTemporada;

}




// =========================================
// DEVIOZ - MODO CLARO / OSCURO
// =========================================

document.addEventListener(
"DOMContentLoaded",
function()
{


    const themeSwitch =
        document.getElementById(
            "themeSwitch"
        );


    const themeIcon =
        document.getElementById(
            "themeIcon"
        );


    if(!themeSwitch)
    {

        return;

    }


    const temaGuardado =
        localStorage.getItem(
            "devioz_theme"
        );


    function aplicarTema(tema)
    {

        if(tema === "dark")
        {

            document.body.classList.add(
                "dark-mode"
            );


            themeSwitch.checked = true;


            if(themeIcon)
            {

                themeIcon.textContent = "🌙";

            }

        }
        else
        {

            document.body.classList.remove(
                "dark-mode"
            );


            themeSwitch.checked = false;


            if(themeIcon)
            {

                themeIcon.textContent = "☀️";

            }

        }

    }


    if(temaGuardado === "dark")
    {

        aplicarTema("dark");

    }
    else
    {

        aplicarTema("light");

    }


    themeSwitch.addEventListener(
        "change",
        function()
        {

            if(this.checked)
            {

                aplicarTema("dark");


                localStorage.setItem(
                    "devioz_theme",
                    "dark"
                );

            }
            else
            {

                aplicarTema("light");


                localStorage.setItem(
                    "devioz_theme",
                    "light"
                );

            }

        }
    );


});




// =========================================
// DEVIOZ AI - PANEL MEJORADO
// =========================================

document.addEventListener(
"DOMContentLoaded",
function()
{


    // =========================================
    // ELEMENTOS
    // =========================================

    const boton =
        document.getElementById(
            "deviozAiFloating"
        );


    const panel =
        document.getElementById(
            "deviozAiPanel"
        );


    const cerrar =
        document.getElementById(
            "deviozAiClose"
        );


    const minimizar =
        document.getElementById(
            "deviozAiMinimize"
        );


    const nuevaConversacion =
        document.getElementById(
            "deviozAiNewChat"
        );


    const confirmNuevaConversacion =
        document.getElementById(
            "deviozAiConfirm"
        );


    const confirmarCancelar =
        document.getElementById(
            "deviozAiConfirmCancel"
        );


    const confirmarAceptar =
        document.getElementById(
            "deviozAiConfirmAccept"
        );


    const enviar =
        document.getElementById(
            "deviozAiSend"
        );


    const input =
        document.getElementById(
            "deviozAiInput"
        );


    const mensajes =
        document.getElementById(
            "deviozAiMessages"
        );



    // =========================================
    // VALIDAR PANEL
    // =========================================

    if(
        !boton
        ||
        !panel
    )
    {

        return;

    }



    // =========================================
    // CLAVES LOCALSTORAGE
    // =========================================

    const CLAVE_ESTADO =
        "devioz_ai_panel_state";


    const idUsuario =
        panel.dataset.userId
        ||
        "visitante";


    const CLAVE_CHAT =
        "devioz_ai_chat_"
        +
        idUsuario;



    // =========================================
    // GUARDAR ESTADO PANEL
    // =========================================

    function guardarEstado(
        estado
    )
    {

        localStorage.setItem(
            CLAVE_ESTADO,
            estado
        );

    }



    // =========================================
    // ACTUALIZAR BOTON FLOTANTE
    // =========================================

    function actualizarBotonFlotante()
    {

        const abierto =
            panel.classList.contains(
                "visible"
            );


        boton.classList.toggle(
            "panel-open",
            abierto
        );


        boton.setAttribute(
            "aria-expanded",
            abierto
                ? "true"
                : "false"
        );

    }



    // =========================================
    // ABRIR PANEL
    // =========================================

    function abrirPanel()
    {

        panel.classList.add(
            "visible"
        );


        panel.classList.remove(
            "minimized"
        );


        if(minimizar)
        {

            minimizar.textContent =
                "—";


            minimizar.title =
                "Minimizar";


            minimizar.setAttribute(
                "aria-label",
                "Minimizar DEVIOZ AI"
            );

        }


        actualizarBotonFlotante();


        guardarEstado(
            "open"
        );


        if(input)
        {

            setTimeout(
                function()
                {

                    input.focus();

                },
                180
            );

        }

    }



    // =========================================
    // CERRAR PANEL
    // =========================================

    function cerrarPanel()
    {

        ocultarConfirmacionNuevaConversacion();


        panel.classList.remove(
            "visible",
            "minimized"
        );


        actualizarBotonFlotante();


        guardarEstado(
            "closed"
        );

    }



    // =========================================
    // MINIMIZAR PANEL
    // =========================================

    function minimizarPanel()
    {

        ocultarConfirmacionNuevaConversacion();


        panel.classList.add(
            "visible",
            "minimized"
        );


        if(minimizar)
        {

            minimizar.textContent =
                "□";


            minimizar.title =
                "Restaurar";


            minimizar.setAttribute(
                "aria-label",
                "Restaurar DEVIOZ AI"
            );

        }


        actualizarBotonFlotante();


        guardarEstado(
            "minimized"
        );

    }



    // =========================================
    // RESTAURAR PANEL
    // =========================================

    function restaurarPanel()
    {

        panel.classList.add(
            "visible"
        );


        panel.classList.remove(
            "minimized"
        );


        if(minimizar)
        {

            minimizar.textContent =
                "—";


            minimizar.title =
                "Minimizar";


            minimizar.setAttribute(
                "aria-label",
                "Minimizar DEVIOZ AI"
            );

        }


        actualizarBotonFlotante();


        guardarEstado(
            "open"
        );


        if(input)
        {

            setTimeout(
                function()
                {

                    input.focus();

                },
                180
            );

        }

    }



    // =========================================
    // ALTERNAR PANEL
    // =========================================

    function alternarPanel()
    {

        if(
            panel.classList.contains(
                "visible"
            )
        )
        {

            if(
                panel.classList.contains(
                    "minimized"
                )
            )
            {

                restaurarPanel();

            }
            else
            {

                cerrarPanel();

            }

        }
        else
        {

            abrirPanel();

        }

    }



    // =========================================
    // MOSTRAR MODAL NUEVA CONVERSACION
    // =========================================

    function mostrarConfirmacionNuevaConversacion()
    {

        if(!confirmNuevaConversacion)
        {

            return;

        }


        confirmNuevaConversacion.classList.add(
            "visible"
        );


        confirmNuevaConversacion.setAttribute(
            "aria-hidden",
            "false"
        );

    }



    // =========================================
    // OCULTAR MODAL NUEVA CONVERSACION
    // =========================================

    function ocultarConfirmacionNuevaConversacion()
    {

        if(!confirmNuevaConversacion)
        {

            return;

        }


        confirmNuevaConversacion.classList.remove(
            "visible"
        );


        confirmNuevaConversacion.setAttribute(
            "aria-hidden",
            "true"
        );

    }



    // =========================================
    // RESTAURAR ESTADO GUARDADO
    // =========================================

    const estadoGuardado =
        localStorage.getItem(
            CLAVE_ESTADO
        );


    if(
        estadoGuardado ===
        "open"
    )
    {

        panel.classList.add(
            "visible"
        );

    }
    else if(
        estadoGuardado ===
        "minimized"
    )
    {

        panel.classList.add(
            "visible",
            "minimized"
        );


        if(minimizar)
        {

            minimizar.textContent =
                "□";


            minimizar.title =
                "Restaurar";


            minimizar.setAttribute(
                "aria-label",
                "Restaurar DEVIOZ AI"
            );

        }

    }


    actualizarBotonFlotante();



    // =========================================
    // ABRIR IA DESPUES DEL LOGIN
    // =========================================

    const parametrosAI =
        new URLSearchParams(
            window.location.search
        );


    if(
        parametrosAI.get(
            "open_ai"
        ) === "1"
    )
    {

        abrirPanel();


        parametrosAI.delete(
            "open_ai"
        );


        const nuevaQuery =
            parametrosAI.toString();


        const nuevaUrl =
            window.location.pathname
            +
            (
                nuevaQuery
                ?
                "?"
                +
                nuevaQuery
                :
                ""
            );


        window.history.replaceState(
            {},
            "",
            nuevaUrl
        );

    }



    // =========================================
    // BOTON FLOTANTE
    // =========================================

    boton.addEventListener(
        "click",
        function(e)
        {

            e.preventDefault();


            e.stopPropagation();


            alternarPanel();

        }
    );



    // =========================================
    // CERRAR
    // =========================================

    if(cerrar)
    {

        cerrar.addEventListener(
            "click",
            function(e)
            {

                e.preventDefault();


                e.stopPropagation();


                cerrarPanel();

            }
        );

    }



    // =========================================
    // MINIMIZAR / RESTAURAR
    // =========================================

    if(minimizar)
    {

        minimizar.addEventListener(
            "click",
            function(e)
            {

                e.preventDefault();


                e.stopPropagation();


                if(
                    panel.classList.contains(
                        "minimized"
                    )
                )
                {

                    restaurarPanel();

                }
                else
                {

                    minimizarPanel();

                }

            }
        );

    }



    // =========================================
    // DOBLE CLICK CABECERA MINIMIZADA
    // =========================================

    const cabecera =
        panel.querySelector(
            ".devioz-ai-header"
        );


    if(cabecera)
    {

        cabecera.addEventListener(
            "dblclick",
            function(e)
            {

                if(
                    panel.classList.contains(
                        "minimized"
                    )
                    &&
                    !e.target.closest(
                        "button"
                    )
                )
                {

                    restaurarPanel();

                }

            }
        );

    }



    // =========================================
    // NO CERRAR AL CLICK DENTRO
    // =========================================

    panel.addEventListener(
        "click",
        function(e)
        {

            e.stopPropagation();

        }
    );



    // =========================================
    // CLICK FUERA
    // =========================================

    document.addEventListener(
        "click",
        function(e)
        {

            if(
                !panel.classList.contains(
                    "visible"
                )
            )
            {

                return;

            }


            if(
                panel.contains(
                    e.target
                )
                ||
                boton.contains(
                    e.target
                )
            )
            {

                return;

            }


            const enlace =
                e.target.closest(
                    "a"
                );


            if(enlace)
            {

                return;

            }


            cerrarPanel();

        }
    );



    // =========================================
    // ESCAPE
    // =========================================

    document.addEventListener(
        "keydown",
        function(e)
        {

            if(e.key !== "Escape")
            {

                return;

            }


            // PRIMERO CERRAR MODAL

            if(
                confirmNuevaConversacion
                &&
                confirmNuevaConversacion.classList.contains(
                    "visible"
                )
            )
            {

                ocultarConfirmacionNuevaConversacion();


                return;

            }


            // DESPUES CERRAR PANEL

            if(
                panel.classList.contains(
                    "visible"
                )
            )
            {

                cerrarPanel();

            }

        }
    );



        // =========================================
    // CHAT AJAX + HISTORIAL
    // =========================================

    if(
        enviar
        &&
        input
        &&
        mensajes
    )
    {

        let enviandoMensaje =
            false;


        const contenidoBotonEnviar =
            enviar.innerHTML;



        // =========================================
        // FORMATEAR HORA
        // =========================================

        function formatearHora(fecha)
        {

            const fechaMensaje =
                fecha
                ?
                new Date(fecha)
                :
                new Date();


            return fechaMensaje.toLocaleTimeString(
                "es-PE",
                {
                    hour:
                        "2-digit",

                    minute:
                        "2-digit"
                }
            );

        }



        // =========================================
        // OBTENER HISTORIAL
        // =========================================

        function obtenerHistorial()
        {

            try
            {

                const guardado =
                    localStorage.getItem(
                        CLAVE_CHAT
                    );


                if(!guardado)
                {

                    return [];

                }


                const historial =
                    JSON.parse(
                        guardado
                    );


                return Array.isArray(
                    historial
                )
                    ?
                    historial
                    :
                    [];

            }
            catch(error)
            {

                console.error(
                    "No se pudo leer el historial DEVIOZ AI:",
                    error
                );


                return [];

            }

        }



        // =========================================
        // GUARDAR HISTORIAL
        // =========================================

        function guardarHistorial(
            historial
        )
        {

            try
            {

                localStorage.setItem(
                    CLAVE_CHAT,
                    JSON.stringify(
                        historial
                    )
                );

            }
            catch(error)
            {

                console.error(
                    "No se pudo guardar el historial DEVIOZ AI:",
                    error
                );

            }

        }



        // =========================================
        // GUARDAR MENSAJE
        // =========================================

        function guardarMensajeHistorial(
            texto,
            tipo,
            fecha
        )
        {

            const historial =
                obtenerHistorial();


            historial.push(
                {

                    texto:
                        texto,

                    tipo:
                        tipo,

                    fecha:
                        fecha
                        ||
                        Date.now()

                }
            );


            // =========================================
            // MAXIMO 100 MENSAJES
            // =========================================

            if(
                historial.length >
                100
            )
            {

                historial.splice(
                    0,
                    historial.length - 100
                );

            }


            guardarHistorial(
                historial
            );

        }

        // =========================================
// ESCAPAR HTML
// =========================================

function escaparHTML(texto)
{

    const div =
        document.createElement(
            "div"
        );


    div.textContent =
        texto;


    return div.innerHTML;

}


    // =========================================
// MARKDOWN BASICO + ENLACES DEVIOZ
// =========================================

function renderizarMarkdownBasico(texto)
{

    // =========================================
    // GUARDAR ENLACES INTERNOS TEMPORALMENTE
    // =========================================

    const enlaces =
        [];


    let contenido =
        texto.replace(
            /\[([^\]]+)\]\((\/DEVIOZ-VIDEOS\/public\/[^)\s]+)\)/g,
            function(
                coincidencia,
                etiqueta,
                url
            )
            {

                // =========================================
                // VALIDAR RUTA
                // =========================================

                if(
                    !url.startsWith(
                        "/DEVIOZ-VIDEOS/public/"
                    )
                )
                {

                    return etiqueta;

                }


                // =========================================
                // BLOQUEAR CARACTERES SOSPECHOSOS
                // =========================================

                if(
                    url.includes("<")
                    ||
                    url.includes(">")
                    ||
                    url.includes("\"")
                    ||
                    url.includes("'")
                )
                {

                    return etiqueta;

                }


                const indice =
                    enlaces.length;


                enlaces.push(
                    {

                        etiqueta:
                            etiqueta,

                        url:
                            url

                    }
                );


                return (
                    "DEVIOZ_LINK_"
                    +
                    indice
                    +
                    "_END"
                );

            }
        );



    // =========================================
    // ESCAPAR TODO EL RESTO
    // =========================================

    let html =
        escaparHTML(
            contenido
        );


    // =========================================
    // TIMESTAMPS DEL VIDEO ACTUAL
    // =========================================

    html =
        html.replace(
            /\[((?:\d{1,2}:)?\d{1,2}:\d{2})\]/g,
            '<button type="button" class="ai-timestamp-link" data-ai-time="$1">$1</button>'
        );



    // =========================================
    // NEGRITA
    // =========================================

    html =
        html.replace(
            /\*\*(.*?)\*\*/g,
            "<strong>$1</strong>"
        );



    // =========================================
    // TITULOS
    // =========================================

    html =
        html.replace(
            /^### (.*)$/gm,
            "<h4>$1</h4>"
        );


    html =
        html.replace(
            /^## (.*)$/gm,
            "<h3>$1</h3>"
        );



    // =========================================
    // LISTAS NUMERADAS
    // =========================================

    html =
        html.replace(
            /^(\d+)\.\s+(.*)$/gm,
            '<div class="ai-list-item">'
            +
            '<span>$1.</span>'
            +
            '<div>$2</div>'
            +
            '</div>'
        );



    // =========================================
    // LISTAS CON GUION
    // =========================================

    html =
        html.replace(
            /^-\s+(.*)$/gm,
            '<div class="ai-list-item ai-list-bullet">'
            +
            '<span>•</span>'
            +
            '<div>$1</div>'
            +
            '</div>'
        );



    // =========================================
    // SALTOS DE LINEA
    // =========================================

    html =
        html.replace(
            /\n/g,
            "<br>"
        );



    // =========================================
    // RESTAURAR ENLACES COMO BOTONES
    // =========================================

    enlaces.forEach(
        function(
            enlace,
            indice
        )
        {

            const marcador =
                "DEVIOZ_LINK_"
                +
                indice
                +
                "_END";


            // =========================================
            // ESCAPAR ETIQUETA
            // =========================================

            const etiquetaSegura =
                escaparHTML(
                    enlace.etiqueta
                );


            // =========================================
            // CREAR BOTON
            // =========================================

            const boton =
                '<a '
                +
                'href="'
                +
                enlace.url
                +
                '" '
                +
                'class="ai-content-link">'
                +
                etiquetaSegura
                +
                '</a>';


            html =
                html.replace(
                    marcador,
                    boton
                );

        }
    );



    return html;

}


        // =========================================
        // CREAR MENSAJE
        // =========================================

        function agregarMensaje(
            texto,
            tipo,
            guardar = true,
            fecha = null
        )
        {

            const fechaMensaje =
                fecha
                ||
                Date.now();


            const elemento =
                document.createElement(
                    "div"
                );


            elemento.className =
                "ai-message "
                +
                tipo;



            // =========================================
            // TEXTO
            // =========================================

            const contenido =
                document.createElement(
                    "div"
                );


            contenido.className =
                "ai-message-content";


            if(
    tipo.includes(
        "ai-message-system"
    )
)
{

    contenido.innerHTML =
        renderizarMarkdownBasico(
            texto
        );

}
else
{

    contenido.textContent =
        texto;

}


            elemento.appendChild(
                contenido
            );



            // =========================================
            // HORA
            // =========================================

            const hora =
                document.createElement(
                    "span"
                );


            hora.className =
                "ai-message-time";


            hora.textContent =
                formatearHora(
                    fechaMensaje
                );


            elemento.appendChild(
                hora
            );



            mensajes.appendChild(
                elemento
            );


            mensajes.scrollTop =
                mensajes.scrollHeight;



            if(guardar)
            {

                guardarMensajeHistorial(
                    texto,
                    tipo,
                    fechaMensaje
                );

            }


            return elemento;

        }



        // =========================================
        // MOSTRAR ESCRIBIENDO
        // =========================================

        function mostrarEscribiendo()
        {

            const elemento =
                document.createElement(
                    "div"
                );


            elemento.className =
                "ai-message ai-message-system ai-message-typing";


            elemento.innerHTML = `
                <div class="ai-typing-content">

                    <span class="ai-typing-label">
                        DEVIOZ AI
                    </span>

                    <span class="ai-typing-dots">

                        <span></span>
                        <span></span>
                        <span></span>

                    </span>

                </div>
            `;


            mensajes.appendChild(
                elemento
            );


            mensajes.scrollTop =
                mensajes.scrollHeight;


            return elemento;

        }



        // =========================================
        // MOSTRAR ERROR
        // =========================================

        function mostrarError(
            texto
        )
        {

            const elemento =
                document.createElement(
                    "div"
                );


            elemento.className =
                "ai-message ai-message-error";


            elemento.innerHTML = `
                <div class="ai-error-content">

                    <span class="ai-error-icon">
                        ⚠
                    </span>

                    <div>

                        <strong>
                            No se pudo completar la solicitud
                        </strong>

                        <p></p>

                    </div>

                </div>
            `;


            const parrafo =
                elemento.querySelector(
                    "p"
                );


            if(parrafo)
            {

                parrafo.textContent =
                    texto;

            }


            mensajes.appendChild(
                elemento
            );


            mensajes.scrollTop =
                mensajes.scrollHeight;


            // Los errores NO se guardan
            // en el historial.

            return elemento;

        }



        // =========================================
        // ESTADO DE CARGA
        // =========================================

        function activarCarga()
        {

            enviandoMensaje =
                true;


            enviar.disabled =
                true;


            input.disabled =
                true;


            enviar.classList.add(
                "ai-send-loading"
            );


            enviar.innerHTML = `
                <span class="ai-send-loader"></span>
            `;

        }



        // =========================================
        // QUITAR ESTADO DE CARGA
        // =========================================

        function desactivarCarga()
        {

            enviandoMensaje =
                false;


            enviar.disabled =
                false;


            input.disabled =
                false;


            enviar.classList.remove(
                "ai-send-loading"
            );


            enviar.innerHTML =
                contenidoBotonEnviar;


            input.focus();

        }



        // =========================================
        // CARGAR HISTORIAL
        // =========================================

        function cargarHistorial()
        {

            const historial =
                obtenerHistorial();


            if(
                historial.length ===
                0
            )
            {

                return;

            }


            mensajes.innerHTML =
                "";


            historial.forEach(
                function(item)
                {

                    if(
                        !item
                        ||
                        typeof item.texto !==
                            "string"
                        ||
                        typeof item.tipo !==
                            "string"
                    )
                    {

                        return;

                    }


                    agregarMensaje(
                        item.texto,
                        item.tipo,
                        false,
                        item.fecha
                        ||
                        Date.now()
                    );

                }
            );


            mensajes.scrollTop =
                mensajes.scrollHeight;

        }



        // =========================================
        // EJECUTAR NUEVA CONVERSACION
        // =========================================

        function ejecutarNuevaConversacion()
        {

            localStorage.removeItem(
                CLAVE_CHAT
            );


            mensajes.innerHTML =
                "";


            agregarMensaje(
                "Nueva conversación iniciada. ¿En qué puedo ayudarte?",
                "ai-message-system",
                false
            );


            input.value =
                "";


            ocultarConfirmacionNuevaConversacion();


            input.focus();

        }



        // =========================================
        // ENVIAR MENSAJE
        // =========================================

        async function enviarMensajeAI()
        {

            const texto =
                input.value.trim();



            // =========================================
            // VALIDAR
            // =========================================

            if(!texto)
            {

                return;

            }


            // =========================================
            // EVITAR DOBLE ENVIO
            // =========================================

            if(enviandoMensaje)
            {

                return;

            }



            // =========================================
            // MOSTRAR MENSAJE USUARIO
            // =========================================

            agregarMensaje(
                texto,
                "ai-message-user"
            );


            input.value =
                "";



            // =========================================
            // BLOQUEAR CHAT
            // =========================================

            activarCarga();



            // =========================================
            // MOSTRAR TYPING
            // =========================================

            const escribiendo =
                mostrarEscribiendo();



            try
            {

                // =========================================
                // FETCH
                // =========================================

                const respuesta =
                    await fetch(
                        "/DEVIOZ-VIDEOS/api/devioz_ai.php",
                        {

                            method:
                                "POST",


                            headers:
                            {

                                "Content-Type":
                                    "application/json"

                            },


                            body:
    JSON.stringify(
        {

            mensaje:
                texto,

            contexto_modo:
                (window.DEVIOZ_AI_CONTEXT && window.DEVIOZ_AI_CONTEXT.mode)
                    ? window.DEVIOZ_AI_CONTEXT.mode
                    : "general",

            video_id:
                (window.DEVIOZ_AI_CONTEXT && window.DEVIOZ_AI_CONTEXT.videoId)
                    ? window.DEVIOZ_AI_CONTEXT.videoId
                    : 0,


            historial:
                obtenerHistorial()
                    .slice(-10)
                    .map(
                        function(item)
                        {

                            let rol =
                                "user";


                            if(
                                item.tipo
                                &&
                                item.tipo.includes(
                                    "ai-message-system"
                                )
                            )
                            {

                                rol =
                                    "assistant";

                            }


                            return {

                                role:
                                    rol,

                                content:
                                    item.texto

                            };

                        }
                    )

        }
    )               

                        }
                    );



                // =========================================
                // CONVERTIR JSON
                // =========================================

                let datos =
                    null;


                try
                {

                    datos =
                        await respuesta.json();

                }
                catch(errorJson)
                {

                    throw new Error(
                        "El servidor devolvió una respuesta inválida."
                    );

                }



                // =========================================
                // QUITAR TYPING
                // =========================================

                if(
                    escribiendo
                    &&
                    escribiendo.parentNode
                )
                {

                    escribiendo.remove();

                }



                // =========================================
                // SESION EXPIRADA
                // =========================================

                if(
                    respuesta.status ===
                    401
                )
                {

                    mostrarError(
                        "Tu sesión ha expirado. Inicia sesión nuevamente."
                    );


                    return;

                }



                // =========================================
                // ERROR SERVIDOR
                // =========================================

                if(
                    !respuesta.ok
                    ||
                    !datos
                    ||
                    !datos.ok
                )
                {

                    mostrarError(
                        datos?.mensaje
                        ||
                        "No se pudo obtener una respuesta de DEVIOZ AI."
                    );


                    return;

                }



                // =========================================
                // VALIDAR RESPUESTA
                // =========================================

                if(
                    typeof datos.respuesta !==
                    "string"
                    ||
                    datos.respuesta.trim() ===
                    ""
                )
                {

                    mostrarError(
                        "DEVIOZ AI devolvió una respuesta vacía."
                    );


                    return;

                }



                // =========================================
                // RESPUESTA DEVIOZ AI
                // =========================================

                agregarMensaje(
                    datos.respuesta,
                    "ai-message-system"
                );

            }
            catch(error)
            {

                if(
                    escribiendo
                    &&
                    escribiendo.parentNode
                )
                {

                    escribiendo.remove();

                }


                mostrarError(
                    "No se pudo conectar con DEVIOZ AI. Revisa tu conexión e intenta nuevamente."
                );


                console.error(
                    "Error DEVIOZ AI:",
                    error
                );

            }
            finally
            {

                desactivarCarga();

            }

        }



        // =========================================
        // ENVIAR CON BOTON
        // =========================================

        enviar.addEventListener(
            "click",
            function()
            {

                enviarMensajeAI();

            }
        );



        // =========================================
        // NUEVA CONVERSACION
        // =========================================

        if(nuevaConversacion)
        {

            nuevaConversacion.addEventListener(
                "click",
                function(e)
                {

                    e.preventDefault();


                    e.stopPropagation();


                    if(enviandoMensaje)
                    {

                        return;

                    }


                    mostrarConfirmacionNuevaConversacion();

                }
            );

        }



        // =========================================
        // CANCELAR NUEVA CONVERSACION
        // =========================================

        if(confirmarCancelar)
        {

            confirmarCancelar.addEventListener(
                "click",
                function(e)
                {

                    e.preventDefault();


                    e.stopPropagation();


                    ocultarConfirmacionNuevaConversacion();

                }
            );

        }



        // =========================================
        // CONFIRMAR NUEVA CONVERSACION
        // =========================================

        if(confirmarAceptar)
        {

            confirmarAceptar.addEventListener(
                "click",
                function(e)
                {

                    e.preventDefault();


                    e.stopPropagation();


                    if(enviandoMensaje)
                    {

                        return;

                    }


                    ejecutarNuevaConversacion();

                }
            );

        }



        // =========================================
        // ENTER PARA ENVIAR
        // =========================================

        input.addEventListener(
            "keydown",
            function(e)
            {

                if(
                    e.key ===
                    "Enter"
                    &&
                    !e.shiftKey
                )
                {

                    e.preventDefault();


                    if(enviandoMensaje)
                    {

                        return;

                    }


                    enviarMensajeAI();

                }

            }
        );



        // =========================================
        // RECUPERAR HISTORIAL
        // =========================================

        cargarHistorial();

    }


});
// =========================================================
// DEVIOZ VIDEOS 2026 - NAVEGACION E INTERACCIONES
// =========================================================
document.addEventListener('DOMContentLoaded', function () {
    const menuToggle = document.getElementById('publicMenuToggle');
    const sidebar = document.getElementById('publicSidebar');
    const navLinks = document.getElementById('publicNavLinks');

    if (menuToggle) {
        menuToggle.addEventListener('click', function () {
            const abierto = document.body.classList.toggle('public-menu-open');
            menuToggle.setAttribute('aria-expanded', abierto ? 'true' : 'false');
            if (sidebar) sidebar.classList.toggle('is-mobile-open', abierto);
            if (navLinks) navLinks.classList.toggle('is-mobile-open', abierto);
        });
    }

    // Toda la tarjeta de video abre el detalle, salvo que el usuario pulse
    // sobre un control interactivo interno (enlace, boton, formulario, etc.).
    document.querySelectorAll('.card-video[data-card-url]').forEach(function (card) {
        function openCard() {
            const url = card.getAttribute('data-card-url');
            if (url) window.location.href = url;
        }

        card.addEventListener('click', function (event) {
            if (event.target.closest('a,button,input,select,textarea,label,form')) return;
            openCard();
        });

        card.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' && event.key !== ' ') return;
            if (event.target.closest('a,button,input,select,textarea,label,form')) return;
            event.preventDefault();
            openCard();
        });
    });

    document.querySelectorAll('[data-toggle-create-playlist]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            const panel = document.getElementById('playlistCreatePanel');
            if (!panel) return;
            panel.hidden = !panel.hidden;
            if (!panel.hidden) {
                const input = panel.querySelector('input[name="nombre"]');
                if (input) input.focus();
            }
        });
    });

    const config = window.DEVIOZ_INTERACTIONS;
    if (!config || !config.videoId) return;

    const toast = document.getElementById('interactionToast');
    let toastTimer = null;

    function showToast(mensaje, esError) {
        if (!toast) return;
        toast.textContent = mensaje;
        toast.classList.toggle('is-error', !!esError);
        toast.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
            toast.classList.remove('is-visible');
        }, 2800);
    }

    async function action(accion, extra) {
        if (!config.authenticated) {
            window.location.href = '../views/login.php?redirect=' + encodeURIComponent('/DEVIOZ-VIDEOS/public/detalle.php?id=' + config.videoId);
            return null;
        }

        const payload = Object.assign({
            accion: accion,
            csrf_token: config.csrfToken,
            id_video: Number(config.videoId),
            learning_asignacion: Number(config.learningAssignment || 0)
        }, extra || {});

        const respuesta = await fetch(config.endpoint, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            credentials: 'same-origin',
            keepalive: accion === 'guardar_progreso',
            body: JSON.stringify(payload)
        });

        let data = null;
        try {
            data = await respuesta.json();
        } catch (error) {
            data = {ok: false, mensaje: 'Respuesta inválida del servidor.'};
        }

        if (!respuesta.ok || !data.ok) {
            throw new Error(data.mensaje || 'No se pudo completar la operación.');
        }

        return data;
    }

    const likeButton = document.getElementById('btnLikeVideo');
    if (likeButton) {
        likeButton.addEventListener('click', async function () {
            likeButton.disabled = true;
            try {
                const data = await action('toggle_like');
                if (!data) return;
                likeButton.classList.toggle('is-active', !!data.activo);
                const label = document.getElementById('likeLabel');
                const count = document.getElementById('likeCount');
                if (label) label.textContent = data.activo ? 'Te gusta' : 'Me gusta';
                if (count) count.textContent = String(data.total || 0);
                showToast(data.activo ? 'Like agregado.' : 'Like retirado.');
            } catch (error) {
                showToast(error.message, true);
            } finally {
                likeButton.disabled = false;
            }
        });
    }

    const favoriteButton = document.getElementById('btnFavoriteVideo');
    if (favoriteButton) {
        favoriteButton.addEventListener('click', async function () {
            favoriteButton.disabled = true;
            try {
                const data = await action('toggle_favorito');
                if (!data) return;
                favoriteButton.classList.toggle('is-active', !!data.activo);
                const label = document.getElementById('favoriteLabel');
                if (label) label.textContent = data.activo ? 'Guardado' : 'Agregar';
                showToast(data.activo ? 'Guardado en favoritos.' : 'Quitado de favoritos.');
            } catch (error) {
                showToast(error.message, true);
            } finally {
                favoriteButton.disabled = false;
            }
        });
    }

    const shareButton = document.getElementById('btnShareVideo');
    if (shareButton) {
        shareButton.addEventListener('click', async function () {
            const datos = {title: document.title, text: 'Mira este contenido en DEVIOZ VIDEOS', url: window.location.href};
            try {
                if (navigator.share) {
                    await navigator.share(datos);
                } else if (navigator.clipboard) {
                    await navigator.clipboard.writeText(window.location.href);
                    showToast('Enlace copiado al portapapeles.');
                } else {
                    window.prompt('Copia este enlace:', window.location.href);
                }
            } catch (error) {
                if (error && error.name !== 'AbortError') showToast('No se pudo compartir el enlace.', true);
            }
        });
    }

    const playlistButton = document.getElementById('btnAddPlaylist');
    const playlistSelect = document.getElementById('playlistSelect');
    if (playlistButton && playlistSelect) {
        playlistButton.addEventListener('click', async function () {
            const playlist = Number(playlistSelect.value || 0);
            if (!playlist) {
                showToast('Selecciona una playlist primero.', true);
                return;
            }
            playlistButton.disabled = true;
            try {
                await action('agregar_playlist', {id_playlist: playlist});
                showToast('Video agregado a la playlist.');
            } catch (error) {
                showToast(error.message, true);
            } finally {
                playlistButton.disabled = false;
            }
        });
    }

    const togglePlaylistCreate = document.getElementById('btnTogglePlayerPlaylistCreate');
    const playlistCreatePanel = document.getElementById('playerPlaylistCreate');
    const playlistNameInput = document.getElementById('playerPlaylistName');
    const createPlaylistButton = document.getElementById('btnCreatePlayerPlaylist');
    const cancelPlaylistButton = document.getElementById('btnCancelPlayerPlaylistCreate');

    function setPlayerPlaylistPanel(open) {
        if (!playlistCreatePanel) return;
        playlistCreatePanel.hidden = !open;
        if (open && playlistNameInput) {
            window.setTimeout(function () { playlistNameInput.focus(); }, 30);
        }
    }

    if (togglePlaylistCreate && playlistCreatePanel) {
        togglePlaylistCreate.addEventListener('click', function () {
            setPlayerPlaylistPanel(playlistCreatePanel.hasAttribute('hidden'));
        });
    }

    if (cancelPlaylistButton) {
        cancelPlaylistButton.addEventListener('click', function () {
            setPlayerPlaylistPanel(false);
            if (playlistNameInput) playlistNameInput.value = '';
        });
    }

    if (createPlaylistButton && playlistNameInput && playlistSelect) {
        const createAndSave = async function () {
            const nombre = playlistNameInput.value.trim();
            if (!nombre) {
                showToast('Escribe un nombre para la playlist.', true);
                playlistNameInput.focus();
                return;
            }

            createPlaylistButton.disabled = true;
            try {
                const creada = await action('crear_playlist', {nombre: nombre, descripcion: ''});
                const nuevaId = Number(creada.id_playlist || 0);
                if (!nuevaId) throw new Error('La playlist se creo sin un identificador valido.');

                const option = document.createElement('option');
                option.value = String(nuevaId);
                option.textContent = nombre;
                playlistSelect.appendChild(option);
                playlistSelect.value = String(nuevaId);

                await action('agregar_playlist', {id_playlist: nuevaId});
                playlistNameInput.value = '';
                setPlayerPlaylistPanel(false);
                showToast('Playlist creada y video guardado.');
            } catch (error) {
                showToast(error.message, true);
            } finally {
                createPlaylistButton.disabled = false;
            }
        };

        createPlaylistButton.addEventListener('click', createAndSave);
        playlistNameInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                createAndSave();
            }
        });
    }

    const commentForm = document.getElementById('commentForm');
    if (commentForm) {
        commentForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            const input = document.getElementById('commentInput');
            const contenido = input ? input.value.trim() : '';
            if (!contenido) return;
            const button = commentForm.querySelector('button[type="submit"]');
            if (button) button.disabled = true;
            try {
                await action('agregar_comentario', {contenido: contenido});
                showToast('Comentario publicado.');
                window.setTimeout(function () { window.location.reload(); }, 350);
            } catch (error) {
                showToast(error.message, true);
                if (button) button.disabled = false;
            }
        });
    }

    document.querySelectorAll('[data-reply-comment]').forEach(function (button) {
        button.addEventListener('click', function () {
            const parentId = button.getAttribute('data-reply-comment');
            const form = document.querySelector('[data-reply-form="' + parentId + '"]');
            if (!form) return;
            const willOpen = form.hasAttribute('hidden');
            document.querySelectorAll('[data-reply-form]').forEach(function (other) {
                other.setAttribute('hidden', 'hidden');
            });
            if (willOpen) {
                form.removeAttribute('hidden');
                const textarea = form.querySelector('textarea');
                if (textarea) textarea.focus();
            }
        });
    });

    document.querySelectorAll('[data-cancel-reply]').forEach(function (button) {
        button.addEventListener('click', function () {
            const form = button.closest('[data-reply-form]');
            if (!form) return;
            form.setAttribute('hidden', 'hidden');
            const textarea = form.querySelector('textarea');
            if (textarea) textarea.value = '';
        });
    });

    document.querySelectorAll('[data-reply-form]').forEach(function (form) {
        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            const parentId = Number(form.getAttribute('data-reply-form') || 0);
            const textarea = form.querySelector('textarea');
            const contenido = textarea ? textarea.value.trim() : '';
            if (!parentId || !contenido) return;
            const submit = form.querySelector('button[type="submit"]');
            if (submit) submit.disabled = true;
            try {
                await action('responder_comentario', {
                    id_comentario_padre: parentId,
                    contenido: contenido
                });
                showToast('Respuesta publicada.');
                window.setTimeout(function () { window.location.reload(); }, 300);
            } catch (error) {
                showToast(error.message, true);
                if (submit) submit.disabled = false;
            }
        });
    });

    document.querySelectorAll('[data-edit-comment]').forEach(function (button) {
        button.addEventListener('click', async function () {
            const card = button.closest('[data-comment-id]');
            const text = card ? card.querySelector('.comment-text') : null;
            if (!card || !text) return;
            const nuevo = window.prompt('Editar comentario:', text.innerText.trim());
            if (nuevo === null || !nuevo.trim()) return;
            try {
                await action('editar_comentario', {id_comentario: Number(card.dataset.commentId), contenido: nuevo.trim()});
                showToast('Comentario actualizado.');
                window.setTimeout(function () { window.location.reload(); }, 300);
            } catch (error) {
                showToast(error.message, true);
            }
        });
    });

    document.querySelectorAll('[data-delete-comment]').forEach(function (button) {
        button.addEventListener('click', async function () {
            const card = button.closest('[data-comment-id]');
            if (!card || !window.confirm('¿Eliminar este comentario?')) return;
            try {
                await action('eliminar_comentario', {id_comentario: Number(card.dataset.commentId)});
                const thread = card.closest('.comment-thread');
                const esRespuesta = card.classList.contains('comment-reply-card');

                if (!esRespuesta && thread) {
                    // La BD elimina tambien las respuestas del comentario raiz.
                    thread.remove();
                } else {
                    card.remove();
                    if (thread) {
                        const restantes = thread.querySelectorAll('.comment-reply-card').length;
                        const badge = thread.querySelector('.comment-reply-count');
                        if (badge) {
                            if (restantes > 0) {
                                badge.textContent = restantes + (restantes === 1 ? ' respuesta' : ' respuestas');
                            } else {
                                badge.remove();
                            }
                        }
                    }
                }
                showToast('Comentario eliminado.');
            } catch (error) {
                showToast(error.message, true);
            }
        });
    });

    // Historial + continuar viendo. Se guarda de forma moderada para no saturar la BD.
    const video = document.getElementById('deviozVideoPlayer');
    if (video && config.authenticated) {
        let ultimoGuardado = 0;
        let restaurado = false;

        function guardarProgreso(forzar) {
            if (!Number.isFinite(video.duration) || video.duration <= 0) return;
            const posicion = Math.floor(video.currentTime || 0);
            if (!forzar && Math.abs(posicion - ultimoGuardado) < 12) return;
            ultimoGuardado = posicion;

            action('guardar_progreso', {
                posicion: posicion,
                duracion: Math.floor(video.duration)
            }).catch(function () {});
        }

        video.addEventListener('loadedmetadata', function () {
            if (restaurado) return;
            restaurado = true;
            const punto = Number(config.progressSeconds || 0);
            const porcentaje = Number(config.progressPercent || 0);
            if (punto >= 5 && porcentaje < 95 && punto < video.duration - 8) {
                video.currentTime = punto;
                showToast('Continuamos desde ' + Math.floor(punto / 60) + ':' + String(punto % 60).padStart(2, '0') + '.');
            }
        });

        video.addEventListener('timeupdate', function () { guardarProgreso(false); });
        video.addEventListener('pause', function () { guardarProgreso(true); });
        video.addEventListener('ended', function () { guardarProgreso(true); });
    }
});


// =========================================
// TECHFLIX V3 - TRANSCRIPCION + CONTEXTO AI
// =========================================
document.addEventListener("DOMContentLoaded", function () {
    const context = window.DEVIOZ_AI_CONTEXT || null;
    const contextButtons = document.querySelectorAll("[data-ai-context-mode]");
    const aiInput = document.getElementById("deviozAiInput");

    contextButtons.forEach(function (button) {
        button.addEventListener("click", function () {
            const mode = button.getAttribute("data-ai-context-mode") || "general";
            if (context) {
                context.mode = mode;
            }
            contextButtons.forEach(function (item) {
                item.classList.toggle("is-active", item === button);
            });
            if (aiInput) {
                aiInput.placeholder = mode === "video"
                    ? "Pregunta sobre lo dicho en este video..."
                    : "Escribe una pregunta...";
                aiInput.focus();
            }
        });
    });

    const video = document.getElementById("deviozVideoPlayer");
    const transcriptList = document.getElementById("transcriptList");
    const transcriptToggle = document.getElementById("transcriptToggle");
    const transcriptSearch = document.getElementById("transcriptSearch");
    const transcriptEmpty = document.getElementById("transcriptEmpty");
    const transcriptSegments = transcriptList
        ? Array.from(transcriptList.querySelectorAll("[data-transcript-start]"))
        : [];

    function jumpTo(seconds) {
        if (!video || !Number.isFinite(seconds)) return;
        video.currentTime = Math.max(0, seconds);
        video.play().catch(function () {});
        const playerBox = document.getElementById("deviozPlayerBox");
        if (playerBox) {
            playerBox.scrollIntoView({behavior: "smooth", block: "center"});
        }
    }

    transcriptSegments.forEach(function (segment) {
        segment.addEventListener("click", function () {
            jumpTo(Number(segment.getAttribute("data-transcript-start") || 0));
        });
    });

    if (transcriptToggle && transcriptList) {
        transcriptToggle.addEventListener("click", function () {
            const willOpen = transcriptList.hasAttribute("hidden");
            if (willOpen) {
                transcriptList.removeAttribute("hidden");
                transcriptToggle.textContent = "Ocultar transcripcion";
            } else {
                transcriptList.setAttribute("hidden", "hidden");
                transcriptToggle.textContent = "Mostrar transcripcion";
            }
        });
    }

    if (transcriptSearch && transcriptSegments.length) {
        transcriptSearch.addEventListener("input", function () {
            const query = transcriptSearch.value.trim().toLocaleLowerCase("es");
            let visibles = 0;
            transcriptSegments.forEach(function (segment) {
                const text = (segment.getAttribute("data-transcript-text") || "").toLocaleLowerCase("es");
                const visible = !query || text.includes(query);
                segment.hidden = !visible;
                if (visible) visibles++;
            });
            if (transcriptEmpty) {
                transcriptEmpty.hidden = visibles !== 0;
            }
        });
    }

    if (video && transcriptSegments.length) {
        let lastActive = null;
        video.addEventListener("timeupdate", function () {
            const current = Number(video.currentTime || 0);
            let active = null;
            for (let i = transcriptSegments.length - 1; i >= 0; i--) {
                const start = Number(transcriptSegments[i].getAttribute("data-transcript-start") || 0);
                if (current >= start) {
                    active = transcriptSegments[i];
                    break;
                }
            }
            if (active !== lastActive) {
                if (lastActive) lastActive.classList.remove("is-current");
                if (active) active.classList.add("is-current");
                lastActive = active;
            }
        });
    }

    const aiMessages = document.getElementById("deviozAiMessages");
    if (aiMessages) {
        aiMessages.addEventListener("click", function (event) {
            const target = event.target.closest(".ai-timestamp-link");
            if (!target || !video) return;
            const value = target.getAttribute("data-ai-time") || "";
            const parts = value.split(":").map(Number);
            let seconds = 0;
            if (parts.length === 2) {
                seconds = parts[0] * 60 + parts[1];
            } else if (parts.length === 3) {
                seconds = parts[0] * 3600 + parts[1] * 60 + parts[2];
            }
            jumpTo(seconds);
        });
    }
});
