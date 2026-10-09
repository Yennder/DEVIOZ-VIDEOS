<?php

require_once "../config/sesion.php";
require_once "../controllers/TemporadaController.php";
require_once "../controllers/VideoController.php";
require_once "../controllers/InteraccionController.php";
require_once "../controllers/TranscripcionController.php";
require_once "../includes/video_security.php";
require_once "../controllers/DescargaController.php";
require_once "../controllers/CapituloIAController.php";
require_once "../controllers/ValoracionController.php";
require_once "../models/CuestionarioVideo.php";

$videoController = new VideoController();
$temporadaController = new TemporadaController();
$interaccionController = new InteraccionController();


$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if(!$id)
{
    $idTemporada = filter_input(INPUT_GET, "id_temporada", FILTER_VALIDATE_INT);

    if($idTemporada)
    {
        $primerCapitulo = $videoController->primerCapituloTemporada($idTemporada);
        if($primerCapitulo)
        {
            $id = (int)$primerCapitulo["id_video"];
        }
    }
}

if(!$id)
{
    header("Location:index.php");
    exit;
}

$video = $videoController->buscarDetalle($id);


if(!$video)
{

    header("Location:index.php");
    exit;

}

// V4.2.1 - URL temporal de reproduccion. Los MP4 ya no se exponen directamente.
$videoStreamExpira = time() + 7200;
$videoStreamToken = deviozVideoStreamToken((int)$id, $videoStreamExpira);

// V4.2.1 - Solicitud de descarga no bloqueante.
$descargaSolicitudActual = null;
if (usuarioAutenticado())
{
    try
    {
        $descargaController = new DescargaController();
        if ($descargaController->solicitudesDisponibles())
        {
            $descargaSolicitudActual = $descargaController->solicitudActualUsuario((int)$_SESSION["id_usuario"], (int)$id);
        }
    }
    catch (Throwable $e)
    {
        error_log("TECHFLIX V4.2.1 - No se pudo consultar solicitud de descarga: " . $e->getMessage());
    }
}


$comentariosVideo = $interaccionController->comentariosVideo($id);
$comentariosRaiz = [];
$respuestasComentarios = [];
foreach($comentariosVideo as $comentarioVideo)
{
    $idPadreComentario = (int)($comentarioVideo["id_comentario_padre"] ?? 0);
    if($idPadreComentario > 0)
    {
        $respuestasComentarios[$idPadreComentario][] = $comentarioVideo;
    }
    else
    {
        $comentariosRaiz[] = $comentarioVideo;
    }
}
$comentariosRaiz = array_reverse($comentariosRaiz);

// TRANSCRIPCION LOCAL / SUBTITULOS / CONTEXTO IA
$transcripcionVideo = null;
$transcripcionSegmentos = [];
$transcripcionVttDisponible = false;
try
{
    $transcripcionController = new TranscripcionController();
    if($transcripcionController->tablasDisponibles())
    {
        $transcripcionVideo = $transcripcionController->obtenerPorVideo((int)$id);
        if($transcripcionVideo && ($transcripcionVideo["estado"] ?? "") === "completada")
        {
            $transcripcionSegmentos = $transcripcionController->obtenerSegmentos((int)$id);
            $vttNombre = basename((string)($transcripcionVideo["vtt_archivo"] ?? ""));
            $transcripcionVttDisponible = $vttNombre !== "" && is_file(__DIR__ . "/../uploads/subtitulos/" . $vttNombre);
            if(!$transcripcionVttDisponible && !empty($transcripcionSegmentos))
            {
                try
                {
                    $vttNombre = $transcripcionController->escribirVtt((int)$id);
                    $transcripcionVideo["vtt_archivo"] = $vttNombre;
                    $transcripcionVttDisponible = is_file(__DIR__ . "/../uploads/subtitulos/" . basename($vttNombre));
                }
                catch(Throwable $vttError)
                {
                    error_log("TECHFLIX V3 - No se pudo regenerar VTT: " . $vttError->getMessage());
                }
            }
        }
    }
}
catch(Throwable $e)
{
    error_log("TECHFLIX V3 - No se pudo cargar transcripcion: " . $e->getMessage());
}

// V4.4.3 - Capitulos inteligentes publicados. Solo se muestran versiones
// revisadas y publicadas por un administrador.
$capitulosInteligentes = [];
try
{
    $capituloIAController = new CapituloIAController();
    if($capituloIAController->tablasDisponibles())
    {
        $capitulosInteligentes = $capituloIAController->publicadosVideo((int)$id);
    }
}
catch(Throwable $e)
{
    error_log("TECHFLIX V4.4.3 - No se pudieron cargar capitulos inteligentes: " . $e->getMessage());
}

// V4.4.4 - Estudio voluntario por escenas, separado del avance académico.
$escenasEstado = [];
$escenasEstudioDisponible = false;
if (!empty($capitulosInteligentes) && usuarioAutenticado()) {
    try {
        require_once __DIR__ . '/../models/EscenaAprendizaje.php';
        $escenasModelo = new EscenaAprendizaje();
        $escenasEstudioDisponible = $escenasModelo->instalado();
        if ($escenasEstudioDisponible) {
            $escenasEstado = $escenasModelo->estadoVideo((int)$_SESSION['id_usuario'], (int)$id);
        }
    } catch (Throwable $e) {
        error_log('DEVIOZ V4.4.4 - No se pudo cargar el avance por escenas: ' . $e->getMessage());
    }
}

// CONTEXTO DE LEARNING LAB (no altera la reproducción normal)
$learningAsignacion = filter_input(INPUT_GET, "learning_asignacion", FILTER_VALIDATE_INT);
$learningContext = null;
$learningLecciones = [];
$learningCurrentLesson = null;
$learningNextLesson = null;
$learningLessonLocked = [];
if($learningAsignacion && usuarioAutenticado())
{
    require_once "../controllers/LearningController.php";
    $learningController = new LearningController();
    $learningContext = $learningController->detalleAsignacion((int)$learningAsignacion, (int)$_SESSION["id_usuario"]);

    if($learningContext)
    {
        $learningLecciones = $learningController->leccionesAsignacion((int)$learningAsignacion, (int)$_SESSION["id_usuario"]);
        $learningPrevObligatoriaCompleta = true;

        foreach($learningLecciones as $learningIndex => $learningLesson)
        {
            $learningLessonComplete = (($learningLesson["progreso_estado"] ?? "pendiente") === "completada");
            $learningLessonIsLocked = !empty($learningContext["orden_secuencial"])
                && !$learningPrevObligatoriaCompleta
                && !$learningLessonComplete;

            $learningLessonLocked[(int)$learningLesson["id_leccion"]] = $learningLessonIsLocked;

            if((int)$learningLesson["id_video"] === (int)$id)
            {
                $learningCurrentLesson = $learningLesson;
                if(isset($learningLecciones[$learningIndex + 1]))
                {
                    // Se conserva para autoplay. Cuando el video actual termine,
                    // su progreso ya habrá sido guardado y la siguiente lección
                    // quedará desbloqueada al cargarla.
                    $learningNextLesson = $learningLecciones[$learningIndex + 1];
                }
            }

            if(!empty($learningLesson["obligatoria"]) && !$learningLessonComplete)
            {
                $learningPrevObligatoriaCompleta = false;
            }
        }

        // Un parámetro de capacitación no debe convertir videos ajenos al curso
        // en parte del Learning Lab.
        if(!$learningCurrentLesson)
        {
            $learningContext = null;
            $learningLecciones = [];
            $learningNextLesson = null;
            $learningLessonLocked = [];
        }
        elseif(!empty($learningContext["orden_secuencial"])
            && !empty($learningLessonLocked[(int)$learningCurrentLesson["id_leccion"]]))
        {
            // Protección del lado servidor: aunque el usuario escriba manualmente
            // la URL de una lección futura, no puede saltarse el orden del curso.
            header("Location: curso.php?asignacion=" . (int)$learningAsignacion . "&leccion_bloqueada=1");
            exit;
        }
    }
}

// INTERACCIONES DE USUARIO. En una capacitacion el consumo del video se registra
// como progreso academico y no incrementa las vistas/historial/progreso publico.
$esVistaLearning = (bool)($learningContext && $learningCurrentLesson);
if(!$esVistaLearning)
{
    $videoController->aumentarVista($id);
    $video["vistas"] = (int)$video["vistas"] + 1;
}

$estadoInteraccion = $interaccionController->estadoVideo(
    usuarioAutenticado() ? (int)$_SESSION["id_usuario"] : 0,
    $id
);
$valoracionActual = (new ValoracionController())->resumenVideo(
    (int)$id, usuarioAutenticado() ? (int)$_SESSION["id_usuario"] : 0
);
// V4.5.4: cuestionarios publicos independientes de los examenes Learning Lab.
$cuestionarioPublico = null;
$cuestionarioResumen = null;
try {
    $modeloQuizVideo = new CuestionarioVideo();
    if ($modeloQuizVideo->instalado()) {
        $cuestionarioPublico = $modeloQuizVideo->version((int)$id, 'publicado');
        if ($cuestionarioPublico && usuarioAutenticado()) {
            $cuestionarioResumen = $modeloQuizVideo->resumenUsuario((int)$id, (int)$_SESSION['id_usuario']);
        }
    }
} catch (Throwable $e) {
    error_log('DEVIOZ V4.5.4 - Cuestionario publico: ' . $e->getMessage());
}
$progresoVideo = ["posicion_segundos" => 0, "duracion_segundos" => 0, "porcentaje" => 0];
$playlistsUsuario = [];

if(usuarioAutenticado())
{
    $idUsuarioActual = (int)$_SESSION["id_usuario"];
    if($esVistaLearning)
    {
        $progresoVideo = [
            "posicion_segundos" => (int)($learningCurrentLesson["posicion_segundos"] ?? 0),
            "duracion_segundos" => (int)($learningCurrentLesson["duracion_segundos"] ?? 0),
            "porcentaje" => (float)($learningCurrentLesson["porcentaje"] ?? 0),
        ];
    }
    else
    {
        $interaccionController->registrarHistorial($idUsuarioActual, $id);
        $progresoVideo = $interaccionController->obtenerProgreso($idUsuarioActual, $id);
    }
    $playlistsUsuario = $interaccionController->playlistsUsuario($idUsuarioActual);
}


// =========================================
// DATOS GENERALES
// =========================================

$relacionados = [];

$capitulos = [];

$temporadas = [];

$anterior = null;

$siguiente = null;

$siguienteRelacionado = null;

$textoAnterior = "";

$textoSiguiente = "";



// =========================================
// LOGICA SERIES
// =========================================

if($video["tipo_contenido"] == "serie")
{

    $temporadas = $temporadaController->listarPublicas(
        $video["id_serie"]
    );


    $capitulos = $videoController->capitulosRelacionados(
        $video["id_temporada"]
    );



    foreach($capitulos as $index => $cap)
    {

        if($cap["id_video"] == $id)
        {


            // =========================================
            // CAPITULO ANTERIOR
            // =========================================

            if(isset($capitulos[$index - 1]))
            {

                $anterior = $capitulos[$index - 1];


                $textoAnterior =
                    "⬅ Capítulo "
                    .
                    $anterior["numero_capitulo"];

            }
            else
            {

                $tempAnterior =
                    $temporadaController->anteriorTemporada(
                        $video["id_serie"],
                        $video["numero_temporada"]
                    );


                if($tempAnterior)
                {

                    $ultimo =
                        $videoController->ultimoCapituloTemporada(
                            $tempAnterior["id_temporada"]
                        );


                    if($ultimo)
                    {

                        $anterior = $ultimo;


                        $textoAnterior =
                            "⬅ Temporada "
                            .
                            $tempAnterior["numero_temporada"]
                            .
                            " - Capítulo "
                            .
                            $ultimo["numero_capitulo"];

                    }

                }

            }



            // =========================================
            // CAPITULO SIGUIENTE
            // =========================================

            if(isset($capitulos[$index + 1]))
            {

                $siguiente = $capitulos[$index + 1];


                $textoSiguiente =
                    "Capítulo "
                    .
                    $siguiente["numero_capitulo"]
                    .
                    " ➡";

            }
            else
            {

                $tempSiguiente =
                    $temporadaController->siguienteTemporada(
                        $video["id_serie"],
                        $video["numero_temporada"]
                    );


                if($tempSiguiente)
                {

                    $primero =
                        $videoController->primerCapituloTemporada(
                            $tempSiguiente["id_temporada"]
                        );


                    if($primero)
                    {

                        $siguiente = $primero;


                        $textoSiguiente =
                            "Temporada "
                            .
                            $tempSiguiente["numero_temporada"]
                            .
                            " - Capítulo "
                            .
                            $primero["numero_capitulo"]
                            .
                            " ➡";

                    }

                }

            }


        }

    }

}
else
{

    // =========================================
    // VIDEOS INDEPENDIENTES
    // =========================================

    $relacionados =
        $videoController->relacionados(
            $video["id_categoria"],
            $id
        );


    // =========================================
    // SIGUIENTE VIDEO INDEPENDIENTE
    // =========================================

    $siguienteRelacionado =
        $videoController->siguienteVideoIndependiente(
            $video["id_categoria"],
            $id
        );

    // El siguiente video real se muestra primero en la lista lateral para que
    // la interfaz coincida con la secuencia usada por la reproduccion automatica.
    if($siguienteRelacionado && !empty($relacionados))
    {
        usort($relacionados, function($a, $b) use ($siguienteRelacionado) {
            $idSiguiente = (int)$siguienteRelacionado["id_video"];
            if((int)$a["id_video"] === $idSiguiente) return -1;
            if((int)$b["id_video"] === $idSiguiente) return 1;
            return strtotime((string)$b["fecha_publicacion"]) <=> strtotime((string)$a["fecha_publicacion"]);
        });
    }

}

?>


<?php include "../includes/public_header.php"; ?>

<?php include "../includes/public_navbar.php"; ?>



<main class="video-detail">


<section class="video-layout">



<!-- =========================================
     CONTENIDO PRINCIPAL
========================================= -->

<div class="main-video">

<?php if($learningContext): ?>
<div class="learning-video-context">
    <div>
        <span>TECHFLIX LEARNING LAB</span>
        <strong><?php echo htmlspecialchars($learningContext["capacitacion"]); ?></strong>
        <small><?php echo (float)$learningContext["progreso"]["porcentaje"]; ?>% completado · fecha límite <?php echo htmlspecialchars($learningContext["fecha_limite"]); ?></small>
        <?php if(($learningCurrentLesson["progreso_estado"] ?? "") !== "completada"): ?>
        <small class="learning-watch-lock">🔒 Avance protegido: puedes retroceder, pero no adelantar partes que aun no has visto.</small>
        <?php else: ?>
        <small class="learning-watch-lock is-complete">✓ Leccion completada: puedes desplazarte libremente por el video.</small>
        <?php endif; ?>
    </div>
    <a href="curso.php?asignacion=<?php echo (int)$learningAsignacion; ?>">Volver al curso →</a>
</div>
<?php endif; ?>

<?php if($video["tipo_contenido"] == "serie"): ?>


<a
href="series.php"
class="btn-volver-series"
>

← Volver a Series

</a>


<?php endif; ?>



<!-- =========================================
     TITULO
========================================= -->

<h1 class="video-title">


<?php if($video["tipo_contenido"] == "serie"): ?>


🎬 <?php echo htmlspecialchars($video["serie"]); ?>


<p class="info-serie">

Temporada
<?php echo (int)$video["numero_temporada"]; ?>

-

Capítulo
<?php echo (int)$video["numero_capitulo"]; ?>

</p>


<?php else: ?>


<?php echo htmlspecialchars($video["titulo"]); ?>


<?php endif; ?>


</h1>



<!-- =========================================
     REPRODUCTOR
========================================= -->

<div
class="player-box"
id="deviozPlayerBox"
>


    <video
    id="deviozVideoPlayer"
    controls
    controlsList="nodownload nofullscreen noremoteplayback"
    preload="metadata"
    >


        <source
        src="video_stream.php?id=<?php echo (int)$id; ?>&amp;exp=<?php echo (int)$videoStreamExpira; ?>&amp;token=<?php echo htmlspecialchars($videoStreamToken); ?>"
        type="video/mp4"
        >

        <?php if($transcripcionVttDisponible): ?>
        <track
        kind="subtitles"
        srclang="es"
        label="Espanol"
        src="../uploads/subtitulos/<?php echo htmlspecialchars(basename((string)$transcripcionVideo["vtt_archivo"])); ?>"
        >
        <?php endif; ?>

        Tu navegador no soporta video HTML5.


    </video>



    <button
    type="button"
    id="btnDeviozFullscreen"
    class="btn-devioz-fullscreen"
    title="Pantalla completa"
    aria-label="Pantalla completa"
    >

        ⛶

    </button>



    <?php if(
        $video["tipo_contenido"] === "serie"
        &&
        $siguiente
    ): ?>



    <!-- =========================================
         AVISO PEQUEÑO SIGUIENTE EPISODIO
    ========================================== -->

    <div
    class="next-episode-mini"
    id="nextEpisodeMini"
    >


        <div class="next-mini-info">


            <span>
                Siguiente episodio
            </span>


            <strong>

                <?php echo htmlspecialchars($textoSiguiente); ?>

            </strong>


        </div>


        <button
        type="button"
        id="btnNextEpisodeMini"
        >

            Reproducir →

        </button>


    </div>



    <!-- =========================================
         OVERLAY FINAL
    ========================================== -->

    <div
    class="next-episode-overlay"
    id="nextEpisodeOverlay"
    >


        <div class="next-overlay-content">


            <span class="next-overlay-label">

                SIGUIENTE EPISODIO

            </span>


            <h2>

                <?php echo htmlspecialchars($siguiente["titulo"]); ?>

            </h2>


            <p>

                Continuando en

                <strong id="nextCountdown">

                    10

                </strong>

                segundos

            </p>



            <div class="next-overlay-progress">


                <div
                class="next-overlay-progress-bar"
                id="nextProgressBar"
                ></div>


            </div>



            <div class="next-overlay-actions">


                <button
                type="button"
                class="btn-next-now"
                id="btnNextNow"
                >

                    ▶ Reproducir ahora

                </button>


                <button
                type="button"
                class="btn-next-cancel"
                id="btnNextCancel"
                >

                    Cancelar

                </button>


            </div>


        </div>


    </div>


    <?php endif; ?>


</div>



<!-- =========================================
     NAVEGACION CAPITULOS
========================================= -->

<?php if($video["tipo_contenido"] == "serie"): ?>


<div class="navegacion-capitulos">


<?php if($anterior): ?>


<a
href="detalle.php?id=<?php echo (int)$anterior["id_video"]; ?>"
>

<?php echo htmlspecialchars($textoAnterior); ?>

</a>


<?php else: ?>


<div></div>


<?php endif; ?>



<?php if($siguiente): ?>


<a
href="detalle.php?id=<?php echo (int)$siguiente["id_video"]; ?>"
>

<?php echo htmlspecialchars($textoSiguiente); ?>

</a>


<?php endif; ?>


</div>


<?php endif; ?>



<!-- =========================================
     INFORMACION
========================================= -->

<div class="video-info-bar">


<span>

👁 <?php echo (int)$video["vistas"]; ?> vistas

</span>


<span>

📁 <?php echo htmlspecialchars($video["categoria"]); ?>

</span>


<span>

📅 <?php echo htmlspecialchars($video["fecha_publicacion"]); ?>

</span>


</div>

<?php include "../includes/video_rating.php"; ?>
<?php include "../includes/video_quiz_callout.php"; ?>

<div class="video-actions-modern">
    <?php if(usuarioAutenticado()): ?>
        <button type="button" class="video-action-btn <?php echo $estadoInteraccion['liked'] ? 'is-active' : ''; ?>" id="btnLikeVideo">
            <span>♡</span><strong id="likeLabel"><?php echo $estadoInteraccion['liked'] ? 'Te gusta' : 'Me gusta'; ?></strong><small id="likeCount"><?php echo (int)$estadoInteraccion['total_likes']; ?></small>
        </button>
        <button type="button" class="video-action-btn <?php echo $estadoInteraccion['favorito'] ? 'is-active' : ''; ?>" id="btnFavoriteVideo">
            <span>♡</span><strong>Favoritos</strong><small id="favoriteLabel"><?php echo $estadoInteraccion['favorito'] ? 'Guardado' : 'Agregar'; ?></small>
        </button>
    <?php else: ?>
        <a class="video-action-btn" href="../views/login.php?redirect=<?php echo urlencode('/DEVIOZ-VIDEOS/public/detalle.php?id=' . $id); ?>"><span>♡</span><strong>Me gusta</strong><small><?php echo (int)$estadoInteraccion['total_likes']; ?></small></a>
        <a class="video-action-btn" href="../views/login.php?redirect=<?php echo urlencode('/DEVIOZ-VIDEOS/public/detalle.php?id=' . $id); ?>"><span>♡</span><strong>Favoritos</strong><small>Agregar</small></a>
    <?php endif; ?>

    <button type="button" class="video-action-btn" id="btnShareVideo"><span>↗</span><strong>Compartir</strong></button>

    <?php if(usuarioAutenticado()): ?>
        <button type="button" class="video-action-btn" id="btnOpenDownloadCode"><span>⇩</span><strong>Descargar</strong><small><?php echo $descargaSolicitudActual && $descargaSolicitudActual['estado']==='aprobada' && ($descargaSolicitudActual['estado_codigo'] ?? '')==='disponible' ? 'Autorizada' : ($descargaSolicitudActual && $descargaSolicitudActual['estado']==='pendiente' ? 'Solicitud pendiente' : 'Solicitar acceso'); ?></small></button>
    <?php else: ?>
        <a class="video-action-btn" href="../views/login.php?redirect=<?php echo urlencode('/DEVIOZ-VIDEOS/public/detalle.php?id=' . $id); ?>"><span>⇩</span><strong>Descargar</strong><small>Inicia sesion</small></a>
    <?php endif; ?>

    <?php if(usuarioAutenticado()): ?>
        <div class="playlist-add-control">
            <select id="playlistSelect" aria-label="Seleccionar playlist">
                <option value="">Añadir a playlist…</option>
                <?php foreach($playlistsUsuario as $playlist): ?>
                    <option value="<?php echo (int)$playlist['id_playlist']; ?>"><?php echo htmlspecialchars($playlist['nombre']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" id="btnAddPlaylist" class="video-action-btn compact"><span>＋</span><strong>Agregar</strong></button>
            <button type="button" id="btnTogglePlayerPlaylistCreate" class="video-action-btn compact"><span>☷</span><strong>Nueva playlist</strong></button>
        </div>
        <div class="player-playlist-create" id="playerPlaylistCreate" hidden>
            <label for="playerPlaylistName">Nombre de la nueva playlist</label>
            <div>
                <input type="text" id="playerPlaylistName" maxlength="100" placeholder="Ej. Docker y DevOps" autocomplete="off">
                <button type="button" id="btnCreatePlayerPlaylist" class="btn-primary-modern">Crear y guardar video</button>
                <button type="button" id="btnCancelPlayerPlaylistCreate" class="btn-secondary-modern">Cancelar</button>
            </div>
            <small>La playlist se crea y este video se agrega automáticamente.</small>
        </div>
    <?php endif; ?>
</div>

<?php if(usuarioAutenticado()): ?>
<aside class="download-access-drawer" id="downloadAccessDrawer" aria-labelledby="downloadAccessTitle" aria-hidden="true">
    <div class="download-access-drawer-head">
        <div><span class="section-kicker">DESCARGA PROTEGIDA</span><h2 id="downloadAccessTitle">Solicitar descarga</h2></div>
        <button type="button" class="download-access-close" id="btnCloseDownloadCode" aria-label="Cerrar">×</button>
    </div>
    <div class="download-access-drawer-body">
        <p>La solicitud no bloquea el reproductor. Puedes seguir navegando y revisar el estado desde <strong>Mis solicitudes</strong>.</p>

        <?php if($descargaSolicitudActual && ($descargaSolicitudActual['estado_mostrado'] ?? '')==='pendiente'): ?>
            <div class="download-request-state pending"><strong>Solicitud pendiente</strong><span>El administrador aun debe revisarla.</span></div>
            <a class="btn-secondary-modern" href="solicitudes_descarga.php">Ver mis solicitudes</a>
        <?php elseif($descargaSolicitudActual && ($descargaSolicitudActual['estado_mostrado'] ?? '')==='aprobada' && !empty($descargaSolicitudActual['codigo_visible'])): ?>
            <div class="download-request-state approved"><strong>Descarga aprobada</strong><span>Tu codigo ya esta disponible y tambien fue enviado a tus notificaciones.</span></div>
            <div class="download-visible-code compact">
                <code><?php echo htmlspecialchars($descargaSolicitudActual['codigo_visible']); ?></code>
                <button type="button" class="btn-copy-download-code" data-copy-code="<?php echo htmlspecialchars($descargaSolicitudActual['codigo_visible'], ENT_QUOTES); ?>">Copiar codigo</button>
            </div>
            <form method="post" action="descargar_video.php" target="_blank" id="downloadCodeForm">
                <?php echo csrfInput(); ?>
                <input type="hidden" name="id_video" value="<?php echo (int)$id; ?>">
                <input type="hidden" name="codigo_descarga" value="<?php echo htmlspecialchars($descargaSolicitudActual['codigo_visible'], ENT_QUOTES); ?>">
                <button type="submit" class="btn-primary-modern">Descargar ahora</button>
            </form>
            <a class="download-conversation-link" href="solicitudes_descarga.php">Ver mis solicitudes</a>
        <?php else: ?>
            <?php if($descargaSolicitudActual && ($descargaSolicitudActual['estado_mostrado'] ?? '')==='rechazada'): ?>
                <div class="download-request-state rejected"><strong>Solicitud anterior rechazada</strong><span><?php echo !empty($descargaSolicitudActual['motivo_rechazo']) ? htmlspecialchars($descargaSolicitudActual['motivo_rechazo']) : 'Puedes enviar una nueva solicitud.'; ?></span></div>
                <a class="download-conversation-link" href="solicitudes_descarga.php">Ver mis solicitudes</a>
            <?php elseif($descargaSolicitudActual && in_array(($descargaSolicitudActual['estado_mostrado'] ?? ''), ['vencida','revocada','descargada'], true)): ?>
                <div class="download-request-state rejected"><strong>Autorizacion no disponible</strong><span>El codigo anterior vencio, fue revocado o ya fue utilizado. Puedes solicitar uno nuevo.</span></div>
                <a class="download-conversation-link" href="solicitudes_descarga.php">Ver mis solicitudes</a>
            <?php endif; ?>
            <form method="post" action="solicitud_descarga.php" class="download-request-create-form">
                <?php echo csrfInput(); ?>
                <input type="hidden" name="accion" value="crear">
                <input type="hidden" name="id_video" value="<?php echo (int)$id; ?>">
                <label for="downloadRequestReason">Motivo opcional</label>
                <textarea id="downloadRequestReason" name="motivo" rows="3" maxlength="500" placeholder="Ej. Necesito revisarlo sin conexion."></textarea>
                <button type="submit" class="btn-primary-modern">Solicitar codigo</button>
            </form>
            <small>Cuando el administrador apruebe, recibiras una notificacion con el codigo.</small>
        <?php endif; ?>
    </div>
</aside>
<?php endif; ?>

<div class="interaction-toast" id="interactionToast" role="status" aria-live="polite"></div>


<!-- =========================================
     DESCRIPCION
========================================= -->

<div class="description-box">


<h2>
Descripción
</h2>


<p>

<?php echo nl2br(
    htmlspecialchars(
        $video["descripcion"] ?? ""
    )
); ?>

</p>


</div>

<?php $wbVideoId = (int)$id; include __DIR__ . "/../includes/watchbuild_relacionados.php"; ?>

<?php if(usuarioAutenticado()): ?>
<section class="ai-summary-card" data-ai-summary data-summary-type="video" data-summary-id="<?php echo (int)$id; ?>" data-summary-csrf="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>" data-summary-can-refresh="<?php echo esAdmin() ? '1' : '0'; ?>">
    <div class="ai-summary-heading">
        <div>
            <span class="section-kicker">DEVIOZ AI · V4.4.1</span>
            <h2>Resumen inteligente</h2>
            <p>Generado a partir de la transcripción del video y guardado para reutilizarlo.</p>
        </div>
        <div class="ai-summary-actions">
            <span class="ai-summary-badge" data-summary-status>Comprobando…</span>
            <button type="button" class="btn-secondary-modern" data-summary-generate hidden>✨ Generar resumen</button>
        </div>
    </div>
    <div class="ai-summary-body" data-summary-body>
        <div class="ai-summary-loading"><span></span><p>Revisando si ya existe un resumen…</p></div>
    </div>
</section>
<?php endif; ?>

<?php if(!empty($capitulosInteligentes)): ?>
<section class="smart-chapters-card" id="capitulosInteligentes" data-smart-chapters>
    <div class="smart-chapters-heading">
        <div>
            <span class="section-kicker">DEVIOZ AI · V4.4.3</span>
            <h2>Capítulos inteligentes · Navegación por temas</h2>
            <p>Navega por los cambios de tema detectados en la transcripción. Estos capítulos fueron revisados y publicados por un administrador.</p>
        </div>
        <div class="smart-chapters-badges">
            <span class="smart-chapters-badge"><?php echo count($capitulosInteligentes); ?> escenas</span>
            <span class="smart-chapters-badge">✓ Revisado</span>
        </div>
    </div>

    <div class="scene-study" data-scene-study
         data-study-video="<?php echo (int)$id; ?>"
         data-study-authenticated="<?php echo usuarioAutenticado() ? '1' : '0'; ?>"
         data-study-ready="<?php echo $escenasEstudioDisponible ? '1' : '0'; ?>"
         data-study-csrf="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="scene-study-intro">
            <div>
                <span class="section-kicker">DEVIOZ LEARNING · V4.4.4</span>
                <h3>Aprender por escenas</h3>
                <p>Estudia un fragmento a la vez, escribe una reflexión y conserva tus escenas repasadas. Esta actividad no modifica tus notas ni el progreso de Learning Lab.</p>
                <div class="scene-study-progress-row" aria-live="polite">
                    <strong data-scene-study-total>0 / <?php echo count($capitulosInteligentes); ?> repasadas</strong>
                    <span class="scene-study-progress-bar"><i data-scene-study-progress></i></span>
                </div>
            </div>
            <button type="button" class="scene-study-primary" data-scene-study-open aria-expanded="false" aria-controls="sceneStudyPanel">Estudiar por escenas</button>
        </div>
        <div class="scene-study-panel" id="sceneStudyPanel" data-scene-study-panel hidden>
            <div class="scene-study-panel-top">
                <span class="scene-study-counter" data-scene-study-counter>Escena 1</span>
                <button type="button" class="scene-study-close" data-scene-study-close aria-label="Cerrar modo de estudio">✕</button>
            </div>
            <h3 data-scene-study-title data-i18n-ignore></h3>
            <p class="scene-study-recap" data-scene-study-summary data-i18n-ignore></p>
            <div class="scene-study-concepts" data-scene-study-concepts data-i18n-ignore></div>
            <p class="scene-study-question">💡 ¿Cuál fue la idea más importante de esta escena y cómo la aplicarías?</p>
            <label class="scene-study-label" for="sceneStudyNote">Tu reflexión sobre esta escena</label>
            <textarea id="sceneStudyNote" data-scene-study-note rows="3" maxlength="1200" placeholder="Escribe lo que aprendiste, con tus propias palabras..."></textarea>
            <p class="scene-study-helper">Para marcarla como repasada, escribe al menos 12 caracteres. Esto es una autoevaluación, no una nota.</p>
            <p class="scene-study-status" data-scene-study-status role="status" aria-live="polite"></p>
            <div class="scene-study-controls">
                <button type="button" class="scene-study-secondary" data-scene-study-prev>← Anterior</button>
                <button type="button" class="scene-study-primary" data-scene-study-play>▶ Reproducir fragmento</button>
                <button type="button" class="scene-study-secondary" data-scene-study-save>Guardar apunte</button>
                <button type="button" class="scene-study-primary" data-scene-study-complete>✓ Marcar como repasada</button>
                <button type="button" class="scene-study-secondary" data-scene-study-next>Siguiente →</button>
            </div>
            <?php if(!usuarioAutenticado()): ?>
                <p class="scene-study-login">Puedes explorar las escenas, pero para guardar tus reflexiones necesitas <a href="../views/login.php">iniciar sesión</a>.</p>
            <?php elseif(!$escenasEstudioDisponible): ?>
                <p class="scene-study-login">El guardado todavía no está disponible. Importa la migración V4.4.4 desde phpMyAdmin.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="smart-chapter-current" data-current-chapter hidden>
        <span>Escena actual</span>
        <strong data-current-chapter-title></strong>
    </div>
    <div class="smart-chapter-notice" data-smart-chapter-notice hidden></div>

    <div class="smart-chapters-list">
        <?php foreach($capitulosInteligentes as $capituloIndex => $capitulo): ?>
        <?php
            $capInicio = max(0, (float)($capitulo["inicio_segundos"] ?? 0));
            $capFin = max($capInicio, (float)($capitulo["fin_segundos"] ?? $capInicio));
            $capInicioEntero = (int)round($capInicio);
            $capFinEntero = (int)round($capFin);
            $capFmt = static function(int $total): string {
                $h = intdiv($total, 3600);
                $resto = $total % 3600;
                $m = intdiv($resto, 60);
                $seg = $resto % 60;
                return $h > 0 ? sprintf("%02d:%02d:%02d", $h, $m, $seg) : sprintf("%02d:%02d", $m, $seg);
            };
        ?>
        <article
            class="smart-chapter-item"
            data-smart-chapter
            data-chapter-start="<?php echo htmlspecialchars((string)$capInicio); ?>"
            data-chapter-end="<?php echo htmlspecialchars((string)$capFin); ?>"
            data-chapter-title="<?php echo htmlspecialchars((string)$capitulo["titulo"], ENT_QUOTES, "UTF-8"); ?>"
            data-chapter-id="<?php echo (int)($capitulo['id_capitulo'] ?? 0); ?>"
            data-chapter-reviewed="<?php echo !empty($escenasEstado[(int)($capitulo['id_capitulo'] ?? 0)]['completada']) ? '1' : '0'; ?>"
            data-chapter-note="<?php echo htmlspecialchars((string)($escenasEstado[(int)($capitulo['id_capitulo'] ?? 0)]['nota'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
        >
            <div class="smart-chapter-index"><?php echo str_pad((string)($capituloIndex + 1), 2, "0", STR_PAD_LEFT); ?></div>
            <div class="smart-chapter-main">
                <div class="smart-chapter-top">
                    <h3><?php echo htmlspecialchars((string)$capitulo["titulo"]); ?></h3>
                    <span class="smart-chapter-time"><?php echo htmlspecialchars($capFmt($capInicioEntero)); ?> - <?php echo htmlspecialchars($capFmt($capFinEntero)); ?></span>
                </div>
                <?php if(trim((string)($capitulo["resumen"] ?? "")) !== ""): ?>
                <p class="smart-chapter-summary"><?php echo htmlspecialchars((string)$capitulo["resumen"]); ?></p>
                <?php endif; ?>
                <?php if(!empty($capitulo["conceptos"]) && is_array($capitulo["conceptos"])): ?>
                <div class="smart-chapter-tags">
                    <?php foreach($capitulo["conceptos"] as $concepto): ?>
                    <span class="smart-chapter-tag"><?php echo htmlspecialchars((string)$concepto); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="smart-chapter-actions">
                    <button type="button" class="smart-chapter-seek" data-chapter-seek>▶ Ir a esta escena</button>
                    <button type="button" class="scene-study-select" data-scene-study-select>Estudiar</button>
                    <span class="scene-study-reviewed" data-chapter-reviewed-badge <?php echo !empty($escenasEstado[(int)($capitulo['id_capitulo'] ?? 0)]['completada']) ? '' : 'hidden'; ?>>✓ Repasada</span>
                    <span class="smart-chapter-progress"><i data-chapter-progress></i></span>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if($transcripcionVideo && ($transcripcionVideo["estado"] ?? "") === "completada" && !empty($transcripcionSegmentos)): ?>
<section class="video-transcript-card" id="transcripcionVideo">
    <div class="video-transcript-heading">
        <div>
            <span class="section-kicker">Contenido accesible</span>
            <h2>Transcripcion del video</h2>
            <p>Selecciona un fragmento para saltar directamente a ese momento.</p>
        </div>
        <div class="video-transcript-tools">
            <span><?php echo count($transcripcionSegmentos); ?> segmentos</span>
            <?php if($transcripcionVttDisponible): ?><span>Subtitulos CC disponibles</span><?php endif; ?>
        </div>
    </div>

    <div class="video-transcript-search">
        <input type="search" id="transcriptSearch" placeholder="Buscar dentro de la transcripcion..." autocomplete="off">
        <button type="button" id="transcriptToggle">Mostrar transcripcion</button>
    </div>

    <div class="video-transcript-list" id="transcriptList" hidden>
        <?php foreach($transcripcionSegmentos as $segmento): ?>
        <?php
            $inicioSegmento = (float)$segmento["inicio_segundos"];
            $inicioEntero = max(0, (int)round($inicioSegmento));
            $horaSegmento = intdiv($inicioEntero, 3600);
            $restoSegmento = $inicioEntero % 3600;
            $minutoSegmento = intdiv($restoSegmento, 60);
            $segundoSegmento = $restoSegmento % 60;
            $tiempoSegmento = $horaSegmento > 0
                ? sprintf("%02d:%02d:%02d", $horaSegmento, $minutoSegmento, $segundoSegmento)
                : sprintf("%02d:%02d", $minutoSegmento, $segundoSegmento);
        ?>
        <button
        type="button"
        class="video-transcript-segment"
        data-transcript-start="<?php echo htmlspecialchars((string)$inicioSegmento); ?>"
        data-transcript-text="<?php echo htmlspecialchars(function_exists("mb_strtolower") ? mb_strtolower((string)$segmento["texto"], "UTF-8") : strtolower((string)$segmento["texto"])); ?>"
        >
            <time><?php echo htmlspecialchars($tiempoSegmento); ?></time>
            <span><?php echo htmlspecialchars($segmento["texto"]); ?></span>
        </button>
        <?php endforeach; ?>
        <div class="video-transcript-empty" id="transcriptEmpty" hidden>No se encontraron coincidencias en esta transcripcion.</div>
    </div>
</section>
<?php elseif($transcripcionVideo && ($transcripcionVideo["estado"] ?? "") === "procesando"): ?>
<div class="video-transcript-pending">La transcripcion de este video se esta procesando localmente.</div>
<?php endif; ?>

<section class="comments-section" id="comentarios">
    <div class="comments-heading">
        <div><span class="section-kicker">Comunidad</span><h2>Comentarios</h2></div>
        <span class="comment-count"><?php echo count($comentariosVideo); ?></span>
    </div>

    <?php if(usuarioAutenticado()): ?>
        <form class="comment-form" id="commentForm">
            <textarea id="commentInput" maxlength="1000" rows="3" placeholder="Comparte una idea o comentario respetuoso…" required></textarea>
            <div><small>Máximo 1000 caracteres.</small><button type="submit" class="btn-primary-modern">Publicar comentario</button></div>
        </form>
    <?php else: ?>
        <div class="comments-login-hint">Inicia sesión para participar en la conversación. <a href="../views/login.php?redirect=<?php echo urlencode('/DEVIOZ-VIDEOS/public/detalle.php?id=' . $id); ?>">Iniciar sesión</a></div>
    <?php endif; ?>

    <div class="comments-list">
        <?php if(empty($comentariosRaiz)): ?>
            <div class="empty-state empty-state-small"><span class="empty-icon">◌</span><h3>Aún no hay comentarios</h3><p>Sé la primera persona en comentar este contenido.</p></div>
        <?php else: ?>
            <?php foreach($comentariosRaiz as $comentario): ?>
                <?php
                $nombreComentario = (string)$comentario['nombre'];
                $inicialComentario = function_exists('mb_substr') && function_exists('mb_strtoupper')
                    ? mb_strtoupper(mb_substr($nombreComentario, 0, 1, 'UTF-8'), 'UTF-8')
                    : strtoupper(substr($nombreComentario, 0, 1));
                $idComentarioActual = (int)$comentario['id_comentario'];
                $respuestasActuales = $respuestasComentarios[$idComentarioActual] ?? [];
                ?>
                <article class="comment-thread" data-comment-thread="<?php echo $idComentarioActual; ?>">
                    <div class="comment-card" data-comment-id="<?php echo $idComentarioActual; ?>">
                        <div class="comment-avatar"><?php echo htmlspecialchars($inicialComentario); ?></div>
                        <div class="comment-body">
                            <div class="comment-meta"><strong><?php echo htmlspecialchars($comentario['nombre']); ?></strong><span><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($comentario['fecha_creacion']))); ?></span></div>
                            <p class="comment-text"><?php echo nl2br(htmlspecialchars($comentario['contenido'])); ?></p>
                            <div class="comment-actions">
                                <?php if(usuarioAutenticado()): ?><button type="button" data-reply-comment="<?php echo $idComentarioActual; ?>">Responder</button><?php endif; ?>
                                <?php if(usuarioAutenticado() && (int)$comentario['id_usuario'] === (int)$_SESSION['id_usuario']): ?><button type="button" data-edit-comment>Editar</button><?php endif; ?>
                                <?php if(usuarioAutenticado() && ((int)$comentario['id_usuario'] === (int)$_SESSION['id_usuario'] || esAdmin())): ?><button type="button" data-delete-comment>Eliminar</button><?php endif; ?>
                                <?php if(!empty($respuestasActuales)): ?><span class="comment-reply-count"><?php echo count($respuestasActuales); ?> respuesta<?php echo count($respuestasActuales) === 1 ? '' : 's'; ?></span><?php endif; ?>
                            </div>

                            <?php if(usuarioAutenticado()): ?>
                            <form class="comment-reply-form" data-reply-form="<?php echo $idComentarioActual; ?>" hidden>
                                <textarea maxlength="1000" rows="2" placeholder="Responder a <?php echo htmlspecialchars($comentario['nombre']); ?>…" required></textarea>
                                <div>
                                    <button type="submit" class="btn-primary-modern">Responder</button>
                                    <button type="button" class="btn-secondary-modern" data-cancel-reply>Cancelar</button>
                                </div>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if(!empty($respuestasActuales)): ?>
                    <div class="comment-replies">
                        <?php foreach($respuestasActuales as $respuesta): ?>
                            <?php
                            $nombreRespuesta = (string)$respuesta['nombre'];
                            $inicialRespuesta = function_exists('mb_substr') && function_exists('mb_strtoupper')
                                ? mb_strtoupper(mb_substr($nombreRespuesta, 0, 1, 'UTF-8'), 'UTF-8')
                                : strtoupper(substr($nombreRespuesta, 0, 1));
                            ?>
                            <div class="comment-card comment-reply-card" data-comment-id="<?php echo (int)$respuesta['id_comentario']; ?>">
                                <div class="comment-avatar"><?php echo htmlspecialchars($inicialRespuesta); ?></div>
                                <div class="comment-body">
                                    <div class="comment-meta">
                                        <strong><?php echo htmlspecialchars($respuesta['nombre']); ?> <small class="comment-reply-badge">Respuesta</small></strong>
                                        <span><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($respuesta['fecha_creacion']))); ?></span>
                                    </div>
                                    <p class="comment-text"><?php echo nl2br(htmlspecialchars($respuesta['contenido'])); ?></p>
                                    <?php if(usuarioAutenticado() && ((int)$respuesta['id_usuario'] === (int)$_SESSION['id_usuario'] || esAdmin())): ?>
                                        <div class="comment-actions">
                                            <?php if((int)$respuesta['id_usuario'] === (int)$_SESSION['id_usuario']): ?><button type="button" data-edit-comment>Editar</button><?php endif; ?>
                                            <button type="button" data-delete-comment>Eliminar</button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>


</div>



<!-- =========================================
     SIDEBAR
========================================= -->

<aside class="related-video">


<?php if($learningContext && $learningCurrentLesson): ?>

<h2>Lecciones del curso</h2>

<?php if($learningNextLesson): ?>
<div class="sidebar-autoplay-control">
    <div class="sidebar-autoplay-text">
        <strong>Reproducción automática</strong>
        <span>Continuar con la siguiente lección</span>
    </div>
    <label class="autoplay-switch">
        <input type="checkbox" id="autoplaySwitch">
        <span class="autoplay-slider"></span>
    </label>
</div>
<?php endif; ?>

<div class="learning-player-lessons">
<?php foreach($learningLecciones as $lessonIndex => $lesson): ?>
<?php
    $lessonLocked = !empty($learningLessonLocked[(int)$lesson["id_leccion"]]);
    $lessonActual = ((int)$lesson["id_video"] === (int)$id);
    $lessonComplete = (($lesson["progreso_estado"] ?? "pendiente") === "completada");
?>
<?php if($lessonLocked): ?>
<div class="related-card learning-player-lesson-locked" aria-disabled="true" title="Completa la lección anterior para continuar">
    <div>
        <h3>🔒 <?php echo str_pad((string)($lessonIndex + 1), 2, "0", STR_PAD_LEFT); ?> - <?php echo htmlspecialchars($lesson["titulo"]); ?></h3>
        <p><?php echo round((float)$lesson["porcentaje"]); ?>% visto · <?php echo !empty($lesson["obligatoria"]) ? "Obligatoria" : "Opcional"; ?> · Bloqueada</p>
    </div>
</div>
<?php else: ?>
<a
href="detalle.php?id=<?php echo (int)$lesson["id_video"]; ?>&learning_asignacion=<?php echo (int)$learningAsignacion; ?>"
class="related-card <?php echo $lessonActual ? "actual-capitulo" : ""; ?>"
>
    <div>
        <h3><?php echo $lessonComplete ? "✓" : str_pad((string)($lessonIndex + 1), 2, "0", STR_PAD_LEFT); ?> - <?php echo htmlspecialchars($lesson["titulo"]); ?></h3>
        <p><?php echo round((float)$lesson["porcentaje"]); ?>% visto · <?php echo !empty($lesson["obligatoria"]) ? "Obligatoria" : "Opcional"; ?></p>
    </div>
</a>
<?php endif; ?>
<?php endforeach; ?>
</div>

<?php elseif($video["tipo_contenido"] == "serie"): ?>


<h2>
Episodios
</h2>


<?php if($siguiente): ?>


<div class="sidebar-autoplay-control">


    <div class="sidebar-autoplay-text">


        <strong>
            Reproducción automática
        </strong>


        <span>
            Reproducir siguiente episodio
        </span>


    </div>


    <label class="autoplay-switch">


        <input
        type="checkbox"
        id="autoplaySwitch"
        >


        <span class="autoplay-slider"></span>


    </label>


</div>


<?php endif; ?>



<select
class="selector-temporada"
id="selectorTemporada"
>


<?php foreach($temporadas as $temp): ?>


<option
value="<?php echo (int)$temp["id_temporada"]; ?>"
<?php
echo
$temp["id_temporada"] == $video["id_temporada"]
?
"selected"
:
"";
?>
>

Temporada <?php echo (int)$temp["numero_temporada"]; ?>

</option>


<?php endforeach; ?>


</select>



<?php foreach($capitulos as $cap): ?>


<a
href="detalle.php?id=<?php echo (int)$cap["id_video"]; ?>"
class="related-card <?php echo $cap["id_video"] == $id ? "actual-capitulo" : ""; ?>"
>


<div>


<h3>

<?php
echo str_pad(
    $cap["numero_capitulo"],
    2,
    "0",
    STR_PAD_LEFT
);
?>

- Capítulo

<?php echo (int)$cap["numero_capitulo"]; ?>

</h3>


<p>

<?php echo htmlspecialchars($cap["titulo"]); ?>

</p>


</div>


</a>


<?php endforeach; ?>



<?php else: ?>


<h2>
Relacionados
</h2>


<?php if($siguienteRelacionado): ?>


<div class="sidebar-autoplay-control">


    <div class="sidebar-autoplay-text">


        <strong>
            Reproducción automática
        </strong>


        <span>
            Continuar con el siguiente video de la categoría
        </span>


    </div>


    <label class="autoplay-switch">


        <input
        type="checkbox"
        id="autoplaySwitch"
        >


        <span class="autoplay-slider"></span>


    </label>


</div>


<?php endif; ?>



<?php foreach($relacionados as $rel): ?>


<a
href="detalle.php?id=<?php echo (int)$rel["id_video"]; ?>"
class="related-card <?php echo ($siguienteRelacionado && (int)$rel["id_video"] === (int)$siguienteRelacionado["id_video"]) ? "next-related-card" : ""; ?>"
>


<img
src="../uploads/thumbnails/<?php echo htmlspecialchars($rel["miniatura"]); ?>"
alt="<?php echo htmlspecialchars($rel["titulo"]); ?>"
>


<div>

<?php if($siguienteRelacionado && (int)$rel["id_video"] === (int)$siguienteRelacionado["id_video"]): ?>
<span class="next-related-label">Siguiente</span>
<?php endif; ?>

<h3>

<?php echo htmlspecialchars($rel["titulo"]); ?>

</h3>


<p>
Ver video →
</p>


</div>


</a>


<?php endforeach; ?>


<?php endif; ?>


</aside>


</section>


</main>



<!-- =========================================
     CONFIGURACION PARA JAVASCRIPT
========================================= -->

<?php if($learningContext && $learningCurrentLesson): ?>

<script>
window.DEVIOZ_PLAYER = {
    esSerie: false,
    esLearning: true,
    siguienteRelacionado:
    <?php if($learningNextLesson): ?>
    {
        id: <?php echo (int)$learningNextLesson["id_video"]; ?>,
        titulo: <?php echo json_encode($learningNextLesson["titulo"], JSON_UNESCAPED_UNICODE); ?>,
        url: <?php echo json_encode("detalle.php?id=" . (int)$learningNextLesson["id_video"] . "&learning_asignacion=" . (int)$learningAsignacion, JSON_UNESCAPED_SLASHES); ?>
    }
    <?php else: ?>
    null
    <?php endif; ?>,
    finalUrl: <?php echo json_encode("curso.php?asignacion=" . (int)$learningAsignacion . "&fin=1", JSON_UNESCAPED_SLASHES); ?>
};
</script>

<?php elseif($video["tipo_contenido"] === "serie"): ?>


<script>

window.DEVIOZ_PLAYER = {

    esSerie: true,

    videoActual: {

        id:
        <?php echo (int)$video["id_video"]; ?>,

        capitulo:
        <?php echo (int)$video["numero_capitulo"]; ?>,

        temporada:
        <?php echo (int)$video["numero_temporada"]; ?>

    },


    siguiente:

    <?php if($siguiente): ?>

    {

        id:
        <?php echo (int)$siguiente["id_video"]; ?>,

        capitulo:
        <?php echo (int)$siguiente["numero_capitulo"]; ?>,

        titulo:
        <?php
        echo json_encode(
            $siguiente["titulo"],
            JSON_UNESCAPED_UNICODE
        );
        ?>,

        url:
        <?php
        echo json_encode(
            "detalle.php?id="
            .
            $siguiente["id_video"]
        );
        ?>

    }

    <?php else: ?>

    null

    <?php endif; ?>

};

</script>


<?php else: ?>


<script>

window.DEVIOZ_PLAYER = {

    esSerie: false,

    videoActual: { id: <?php echo (int)$video["id_video"]; ?> },

    siguiente: null,

    siguienteRelacionado:

    <?php if($siguienteRelacionado): ?>

    {

        id:
        <?php echo (int)$siguienteRelacionado["id_video"]; ?>,

        titulo:
        <?php
        echo json_encode(
            $siguienteRelacionado["titulo"],
            JSON_UNESCAPED_UNICODE
        );
        ?>,

        url:
        <?php
        echo json_encode(
            "detalle.php?id="
            .
            $siguienteRelacionado["id_video"]
        );
        ?>

    }

    <?php else: ?>

    null

    <?php endif; ?>

};

</script>


<?php endif; ?>


<script>
window.DEVIOZ_AI_CONTEXT = {
    mode: "general",
    videoId: <?php echo (int)$id; ?>,
    videoTitle: <?php echo json_encode((string)$video["titulo"], JSON_UNESCAPED_UNICODE); ?>,
    courseId: <?php echo $learningContext ? (int)($learningContext["id_curso"] ?? 0) : 0; ?>,
    courseTitle: <?php echo json_encode($learningContext ? (string)($learningContext["curso"] ?? "") : "", JSON_UNESCAPED_UNICODE); ?>,
    transcriptAvailable: <?php echo ($transcripcionVideo && ($transcripcionVideo["estado"] ?? "") === "completada" && !empty($transcripcionSegmentos)) ? "true" : "false"; ?>
};
</script>
<script>
// Si este video publico tiene cuestionario, priorizar el dialogo al terminar
// incluso cuando el usuario ya haya realizado intentos. No altera Learning Lab.
if (window.DEVIOZ_PLAYER) {
    window.DEVIOZ_PLAYER.cuestionarioPendiente = <?php echo $cuestionarioPublico && !$esVistaLearning ? 'true' : 'false'; ?>;
}
window.DEVIOZ_INTERACTIONS = <?php echo json_encode([
    'endpoint' => '../api/interacciones.php',
    'csrfToken' => csrfToken(),
    'videoId' => (int)$id,
    'authenticated' => usuarioAutenticado(),
    'progressSeconds' => (int)($progresoVideo['posicion_segundos'] ?? 0),
    'progressPercent' => (float)($progresoVideo['porcentaje'] ?? 0),
    'learningAssignment' => $learningContext ? (int)$learningAsignacion : 0,
    'learningMode' => $esVistaLearning,
    'learningLessonCompleted' => $esVistaLearning && (($learningCurrentLesson['progreso_estado'] ?? '') === 'completada'),
    'learningMaxSeconds' => $esVistaLearning ? (int)($learningCurrentLesson['max_posicion_segundos'] ?? 0) : 0,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>

<?php
$deviozAiVideoId = (int)$id;
$deviozAiVideoTitle = (string)$video["titulo"];
$deviozAiVideoContextAvailable = (bool)($transcripcionVideo && ($transcripcionVideo["estado"] ?? "") === "completada" && !empty($transcripcionSegmentos));
$deviozAiCourseId = $learningContext ? (int)($learningContext["id_curso"] ?? 0) : 0;
$deviozAiCourseTitle = $learningContext ? (string)($learningContext["curso"] ?? "") : "";
$deviozAiCourseContextAvailable = $deviozAiCourseId > 0;
?>
<script src="../assets/js/capitulos.js"></script>
<script src="../assets/js/escenas_aprendizaje.js?v=4.4.4" defer></script>
<script src="../assets/js/valoraciones.js?v=4.5.3" defer></script>
<script src="../assets/js/cuestionario_video.js?v=4.5.4.1" defer></script>
<?php include "../includes/public_footer.php"; ?>