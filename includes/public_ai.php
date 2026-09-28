<?php

if(session_status() == PHP_SESSION_NONE)
{
    session_start();
}


$aiDesbloqueada =
    isset($_SESSION["id_usuario"]);


$nombreUsuario =
    $_SESSION["nombre"] ?? "";


$idUsuario =
    $_SESSION["id_usuario"] ?? 0;

// V4.2: detectar si existe una base semantica utilizable.
$deviozAiRagAvailable = false;
if($aiDesbloqueada)
{
    try
    {
        require_once __DIR__ . "/../models/KnowledgeBase.php";
        $knowledgeBaseAi = new KnowledgeBase();
        $resumenKnowledgeAi = $knowledgeBaseAi->resumen();
        $deviozAiRagAvailable = ((int)($resumenKnowledgeAi["chunks"] ?? 0)) > 0;
    }
    catch(Throwable $e)
    {
        $deviozAiRagAvailable = false;
    }
}

$deviozAiVideoContextAvailable = !empty($deviozAiVideoContextAvailable);
$deviozAiCourseContextAvailable = !empty($deviozAiCourseContextAvailable) && $deviozAiRagAvailable;
$deviozAiCourseId = isset($deviozAiCourseId) ? (int)$deviozAiCourseId : 0;
$deviozAiCourseTitle = isset($deviozAiCourseTitle) ? (string)$deviozAiCourseTitle : "";
$deviozAiVideoId = isset($deviozAiVideoId) ? (int)$deviozAiVideoId : 0;
$deviozAiVideoTitle = isset($deviozAiVideoTitle) ? (string)$deviozAiVideoTitle : "";
$deviozAiShowContextSwitcher = $deviozAiVideoContextAvailable || $deviozAiCourseContextAvailable || $deviozAiRagAvailable;

?>



<script>
window.DEVIOZ_AI_CONTEXT = window.DEVIOZ_AI_CONTEXT || {};
window.DEVIOZ_AI_CONTEXT.mode = window.DEVIOZ_AI_CONTEXT.mode || "general";
window.DEVIOZ_AI_CONTEXT.videoId = window.DEVIOZ_AI_CONTEXT.videoId || <?php echo (int)$deviozAiVideoId; ?>;
window.DEVIOZ_AI_CONTEXT.videoTitle = window.DEVIOZ_AI_CONTEXT.videoTitle || <?php echo json_encode($deviozAiVideoTitle, JSON_UNESCAPED_UNICODE); ?>;
window.DEVIOZ_AI_CONTEXT.courseId = <?php echo (int)$deviozAiCourseId; ?> || window.DEVIOZ_AI_CONTEXT.courseId || 0;
window.DEVIOZ_AI_CONTEXT.courseTitle = <?php echo json_encode($deviozAiCourseTitle, JSON_UNESCAPED_UNICODE); ?> || window.DEVIOZ_AI_CONTEXT.courseTitle || "";
window.DEVIOZ_AI_CONTEXT.ragAvailable = <?php echo $deviozAiRagAvailable ? "true" : "false"; ?>;
</script>


<!-- =========================================
     BOTON FLOTANTE DEVIOZ AI
========================================= -->

<button
type="button"
class="devioz-ai-floating"
id="deviozAiFloating"
aria-label="Abrir DEVIOZ AI"
aria-controls="deviozAiPanel"
aria-expanded="false"
>

    ✨

    <span>
        DEVIOZ AI
    </span>

</button>



<!-- =========================================
     PANEL DEVIOZ AI
========================================= -->

<div
class="devioz-ai-panel"
id="deviozAiPanel"
role="dialog"
aria-label="DEVIOZ AI"
data-user-id="<?php echo (int)$idUsuario; ?>"
>


    <!-- =========================================
         CABECERA
    ========================================== -->

    <div class="devioz-ai-header">


        <div>


            <strong>
                ✨ DEVIOZ AI
            </strong>


            <span class="devioz-ai-status">


                <span class="devioz-ai-status-dot"></span>


                Asistente inteligente


            </span>


        </div>



        <div class="devioz-ai-header-actions">


            <!-- =========================================
                 NUEVA CONVERSACION
            ========================================== -->

            <?php if($aiDesbloqueada): ?>


                <button
                type="button"
                id="deviozAiNewChat"
                class="devioz-ai-new-chat"
                title="Nueva conversación"
                aria-label="Nueva conversación"
                >

                    ↻

                </button>


            <?php endif; ?>



            <!-- =========================================
                 MINIMIZAR
            ========================================== -->

            <button
            type="button"
            id="deviozAiMinimize"
            class="devioz-ai-minimize"
            title="Minimizar"
            aria-label="Minimizar DEVIOZ AI"
            >

                —

            </button>



            <!-- =========================================
                 CERRAR
            ========================================== -->

            <button
            type="button"
            id="deviozAiClose"
            class="devioz-ai-close"
            title="Cerrar"
            aria-label="Cerrar DEVIOZ AI"
            >

                ×

            </button>


        </div>


    </div>



    <!-- =========================================
         USUARIO NO AUTENTICADO
    ========================================== -->

    <?php if(!$aiDesbloqueada): ?>


        <div class="devioz-ai-locked">


            <div class="ai-lock-icon">

                🔒

            </div>


            <h3>

                DEVIOZ AI está bloqueado

            </h3>


            <p>

                Inicia sesión para conversar con el asistente
                inteligente de DEVIOZ VIDEOS.

            </p>



            <?php


            $rutaActual =
                $_SERVER["REQUEST_URI"]
                ??
                "/DEVIOZ-VIDEOS/public/index.php";


            $separador =
                str_contains(
                    $rutaActual,
                    "?"
                )
                ? "&"
                : "?";


            $rutaRetorno =
                $rutaActual
                .
                $separador
                .
                "open_ai=1";


            $urlLogin =
                "../views/login.php?redirect="
                .
                urlencode(
                    $rutaRetorno
                );


            ?>



            <a
            href="<?php echo htmlspecialchars($urlLogin); ?>"
            class="btn-ai-login"
            >

                Iniciar sesión

            </a>


        </div>



    <!-- =========================================
         USUARIO AUTENTICADO
    ========================================== -->

    <?php else: ?>


        <div class="devioz-ai-chat">



            <!-- =========================================
                 BIENVENIDA
            ========================================== -->

            <div class="devioz-ai-welcome">


                <strong>

                    Hola
                    <?php echo htmlspecialchars($nombreUsuario); ?>
                    👋

                </strong>


                <p>

                    Pregúntame sobre tecnología, programación,
                    videos, series y contenido disponible en DEVIOZ.

                </p>


            </div>

            <?php if($deviozAiShowContextSwitcher): ?>
            <div class="devioz-ai-context-switcher" id="deviozAiContextSwitcher">
                <span>Contexto de respuesta</span>
                <div>
                    <button type="button" class="is-active" data-ai-context-mode="general">General</button>
                    <?php if($deviozAiVideoContextAvailable): ?>
                    <button type="button" data-ai-context-mode="video">Este video</button>
                    <?php endif; ?>
                    <?php if($deviozAiCourseContextAvailable): ?>
                    <button type="button" data-ai-context-mode="curso">Este curso</button>
                    <?php endif; ?>
                    <?php if($deviozAiRagAvailable): ?>
                    <button type="button" data-ai-context-mode="techflix">TechFlix</button>
                    <?php endif; ?>
                </div>
                <small id="deviozAiContextHelp">General responde con el asistente y el catalogo de la plataforma.</small>
            </div>
            <?php endif; ?>



            <!-- =========================================
                 MENSAJES
            ========================================== -->

            <div
            class="devioz-ai-messages"
            id="deviozAiMessages"
            >


                <div class="ai-message ai-message-system">

                    Listo para ayudarte. La respuesta se procesa con los proveedores de IA configurados en el servidor.

                </div>


            </div>



            <!-- =========================================
                 INPUT
            ========================================== -->

            <div class="devioz-ai-input-area">


                <input
                type="text"
                id="deviozAiInput"
                placeholder="Escribe una pregunta..."
                autocomplete="off"
                >


                <button
                type="button"
                id="deviozAiSend"
                aria-label="Enviar mensaje"
                >

                    ➤

                </button>


            </div>


        </div>



        <!-- =========================================
             MODAL NUEVA CONVERSACION
        ========================================== -->

        <div
        class="devioz-ai-confirm"
        id="deviozAiConfirm"
        aria-hidden="true"
        >


            <div class="devioz-ai-confirm-box">


                <div class="devioz-ai-confirm-icon">

                    ↻

                </div>


                <h3>

                    Nueva conversación

                </h3>


                <p>

                    Se eliminará el historial actual de esta conversación.

                </p>


                <div class="devioz-ai-confirm-actions">


                    <button
                    type="button"
                    id="deviozAiConfirmCancel"
                    class="devioz-ai-confirm-cancel"
                    >

                        Cancelar

                    </button>


                    <button
                    type="button"
                    id="deviozAiConfirmAccept"
                    class="devioz-ai-confirm-accept"
                    >

                        Nueva conversación

                    </button>


                </div>


            </div>


        </div>


    <?php endif; ?>


</div>