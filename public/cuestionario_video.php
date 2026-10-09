<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../models/CuestionarioVideo.php';
require_once __DIR__ . '/../controllers/VideoController.php';

$idVideo = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
if ($idVideo < 1) { header('Location: index.php'); exit; }
$regresar = 'detalle.php?id=' . $idVideo;
if (!usuarioAutenticado()) {
    header('Location: ../views/login.php?redirect=' . urlencode('/DEVIOZ-VIDEOS/public/cuestionario_video.php?id=' . $idVideo));
    exit;
}
$h = static fn($s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$model = new CuestionarioVideo();
$disponible = $model->instalado();
$video = $model->video($idVideo);
if (!$video || $video['estado'] !== 'publicado') { header('Location: index.php'); exit; }
$version = $disponible ? $model->version($idVideo, 'publicado') : null;
$usuario = (int)$_SESSION['id_usuario'];
$intento = null;
$error = '';
if ($disponible && isset($_GET['intento'])) {
    $idIntento = filter_var($_GET['intento'], FILTER_VALIDATE_INT) ?: 0;
    $intento = $idIntento > 0 ? $model->intento($idIntento, $usuario) : null;
    if ($intento && (int)$intento['id_video'] !== $idVideo) $intento = null;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verificarCsrfPost();
    try {
        if (!$version) throw new InvalidArgumentException('Este video todavia no tiene un cuestionario publicado.');
        $respuestas = $_POST['respuesta'] ?? null;
        if (!is_array($respuestas)) throw new InvalidArgumentException('Responde las cinco preguntas.');
        $resultado = $model->registrarIntento($idVideo, $usuario, $respuestas);
        // V4.5.5: mostrar la invitacion a compartir solo despues de un intento real.
        // La sesion guarda una invitacion efimera; no almacena numeros ni mensajes.
        $_SESSION['devioz_whatsapp_compartir_flash'] = [
            'id_intento' => (int)$resultado['id_intento'],
            'id_video' => $idVideo,
            'id_usuario' => $usuario,
            'creado' => time(),
        ];
        header('Location: cuestionario_video.php?id=' . $idVideo . '&intento=' . (int)$resultado['id_intento']);
        exit;
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('DEVIOZ V4.5.4 - Respuesta cuestionario: ' . $e->getMessage());
        $error = 'No fue posible registrar tu intento. Vuelve a intentarlo.';
    }
}
// El flash se consume una sola vez tras la redireccion de una evaluacion.
$abrirCompartirWhatsApp = false;
$invitacionWhatsApp = $_SESSION['devioz_whatsapp_compartir_flash'] ?? null;
if (is_array($invitacionWhatsApp)) {
    unset($_SESSION['devioz_whatsapp_compartir_flash']);
    $abrirCompartirWhatsApp = $intento !== null
        && (int)($invitacionWhatsApp['id_usuario'] ?? 0) === $usuario
        && (int)($invitacionWhatsApp['id_video'] ?? 0) === $idVideo
        && (int)($invitacionWhatsApp['id_intento'] ?? 0) === (int)$intento['id_intento']
        && (int)($invitacionWhatsApp['creado'] ?? 0) >= time() - 600;
}
$preguntas = $version && !$intento ? $model->preguntas((int)$version['id_cuestionario'], false) : [];
$resumen = $version ? $model->resumenUsuario($idVideo, $usuario) : ['intentos' => 0, 'mejor_puntaje' => 0];
$categorias = (new VideoController())->listarCategorias();
?>
<?php include __DIR__ . '/../includes/public_header.php'; ?>
<?php include __DIR__ . '/../includes/public_navbar.php'; ?>
<div class="layout">
<?php include __DIR__ . '/../includes/public_sidebar.php'; ?>
<main class="public-content vq-public-page">
    <nav class="vq-report-toplinks"><a class="vq-back" href="<?php echo $h($regresar); ?>">&larr; Volver al video</a><a class="vq-back" href="mis_cuestionarios.php">📋 Mis cuestionarios y notas</a></nav>
    <section class="vq-public-hero">
        <span class="vq-eyebrow">DEVIOZ LEARNING / V4.5.4</span>
        <h1>Comprueba lo que aprendiste</h1>
        <p class="vq-video-name"><?php echo $h($video['titulo']); ?></p>
        <div class="vq-public-meta"><span>5 preguntas</span><span>20 puntos cada una</span><span>100 puntos posibles</span></div>
        <p>Responde segun el contenido del video. Tu puntaje se guarda en tu historial de intentos y no afecta las evaluaciones de Learning Lab.</p>
    </section>
    <?php if (!$disponible || !$version): ?>
        <section class="vq-public-card vq-empty"><h2>Cuestionario en preparacion</h2><p>El administrador todavia no ha publicado las cinco preguntas para este video.</p><a class="vq-primary" href="<?php echo $h($regresar); ?>">Volver al video</a></section>
    <?php else: ?>
        <?php if ($resumen['intentos']): ?>
        <div class="vq-history-strip"><span>Intentos registrados: <strong><?php echo $resumen['intentos']; ?></strong></span><span>Tu mejor nota: <strong><?php echo $resumen['mejor_puntaje']; ?>/100</strong></span></div>
        <?php endif; ?>
        <?php if ($intento): ?>
            <section class="vq-public-card vq-result" aria-live="polite">
                <span class="vq-eyebrow">Intento <?php echo (int)$intento['numero_intento']; ?> registrado</span>
                <div class="vq-result-score"><?php echo (int)$intento['puntaje']; ?><small>/100</small></div>
                <h2><?php echo (int)$intento['aciertos'] === 5 ? 'Excelente resultado' : 'Sigue aprendiendo'; ?></h2>
                <p>Acertaste <strong><?php echo (int)$intento['aciertos']; ?> de 5 preguntas</strong>. Puedes repasar el video y realizar otro intento para mejorar tu puntaje.</p>
                <p class="vq-result-note">Para que la evaluacion siga siendo util, no mostramos las respuestas correctas despues de cada intento.</p>
                <div class="vq-result-actions"><a class="vq-primary" href="cuestionario_video.php?id=<?php echo $idVideo; ?>">Intentar de nuevo</a><a class="vq-secondary" href="<?php echo $h($regresar); ?>">Volver al video</a><a class="vq-secondary" href="mis_cuestionarios.php">Ver mis resultados</a><button class="vq-secondary" type="button" id="vqWhatsappAbrir">Compartir por WhatsApp ↗</button></div>
            </section>
            <?php include __DIR__ . '/../includes/video_whatsapp_compartir.php'; ?>
        <?php else: ?>
            <?php if ($error): ?><div class="vq-alert is-error" role="alert"><?php echo $h($error); ?></div><?php endif; ?>
            <?php if (count($preguntas) !== 5): ?>
                <div class="vq-alert is-error">Este cuestionario no esta listo. Solicita una revision administrativa.</div>
            <?php else: ?>
            <form class="vq-public-form" method="POST" action="cuestionario_video.php?id=<?php echo $idVideo; ?>" onsubmit="return confirm('Enviar las cinco respuestas y registrar este intento?');">
                <?php echo csrfInput(); ?><input type="hidden" name="id" value="<?php echo $idVideo; ?>">
                <?php foreach ($preguntas as $i => $p): ?>
                    <fieldset class="vq-public-card vq-public-question">
                        <legend><span class="vq-number"><?php echo sprintf('%02d', $i + 1); ?></span><span><?php echo $h($p['pregunta']); ?></span><small>20 pts</small></legend>
                        <div class="vq-answer-grid">
                            <?php foreach ($p['opciones'] as $j => $op): ?>
                                <label class="vq-answer"><input type="radio" name="respuesta[<?php echo (int)$p['id_pregunta']; ?>]" value="<?php echo (int)$op['id_opcion']; ?>" required <?php echo isset($_POST['respuesta'][(int)$p['id_pregunta']]) && (int)$_POST['respuesta'][(int)$p['id_pregunta']] === (int)$op['id_opcion'] ? 'checked' : ''; ?>><span class="vq-letter"><?php echo chr(65 + $j); ?></span><span><?php echo $h($op['texto']); ?></span></label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                <?php endforeach; ?>
                <div class="vq-submit-bar"><span>Revisa tus respuestas antes de enviar. Se guardara tu nota final.</span><button class="vq-primary" type="submit">Finalizar 5 preguntas</button></div>
            </form>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</main></div>
<script src="../assets/js/video_whatsapp_compartir.js?v=4.5.5.2" defer></script>
<?php include __DIR__ . '/../includes/public_footer.php'; ?>
