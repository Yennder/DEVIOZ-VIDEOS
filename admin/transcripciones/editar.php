<?php

require_once '../../config/sesion.php';
verificarAdmin();
require_once '../../controllers/TranscripcionController.php';

$idVideo = filter_input(INPUT_GET, 'id_video', FILTER_VALIDATE_INT);
if (!$idVideo) {
    header('Location: listar.php');
    exit;
}

$controller = new TranscripcionController();
$transcripcion = $controller->obtenerPorVideo((int)$idVideo);
if (!$transcripcion) {
    header('Location: listar.php?error=' . urlencode('La transcripcion no existe.'));
    exit;
}

$mensaje = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        verificarCsrf($_POST['csrf_token'] ?? null);
        $textos = is_array($_POST['texto'] ?? null) ? $_POST['texto'] : [];
        $controller->guardarCorrecciones((int)$idVideo, $textos);
        $mensaje = 'Correcciones guardadas y subtitulos VTT regenerados.';
        $transcripcion = $controller->obtenerPorVideo((int)$idVideo);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$segmentos = $controller->obtenerSegmentos((int)$idVideo);

function tiempoAdmin(float $segundos): string
{
    $segundos = max(0, (int)round($segundos));
    $h = intdiv($segundos, 3600);
    $r = $segundos % 3600;
    $m = intdiv($r, 60);
    $s = $r % 60;
    return $h > 0 ? sprintf('%02d:%02d:%02d', $h, $m, $s) : sprintf('%02d:%02d', $m, $s);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Editar transcripcion - DEVIOZ VIDEOS</title>
<link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="admin-main">
<?php include '../includes/navbar.php'; ?>
<section class="admin-content">
    <div class="gestion-header">
        <div>
            <h1>Editar transcripcion</h1>
            <p><?php echo htmlspecialchars($transcripcion['titulo']); ?></p>
        </div>
        <div class="acciones-contenedor">
            <a class="btn-limpiar" href="listar.php">Volver</a>
            <a class="btn" href="descargar_vtt.php?id_video=<?php echo (int)$idVideo; ?>">Descargar VTT</a>
        </div>
    </div>

    <?php if ($mensaje): ?><div class="transcription-admin-alert is-success"><?php echo htmlspecialchars($mensaje); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="transcription-admin-alert is-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="transcription-edit-meta">
        <span>Estado: <strong><?php echo htmlspecialchars($transcripcion['estado']); ?></strong></span>
        <span>Idioma: <strong><?php echo htmlspecialchars($transcripcion['idioma']); ?></strong></span>
        <span>Modelo: <strong><?php echo htmlspecialchars($transcripcion['modelo']); ?></strong></span>
        <span>Segmentos: <strong><?php echo count($segmentos); ?></strong></span>
    </div>

    <form method="POST" class="transcription-edit-form">
        <?php echo csrfInput(); ?>
        <div class="transcription-segment-editor-list">
        <?php foreach ($segmentos as $seg): ?>
            <article class="transcription-segment-editor">
                <div class="transcription-segment-time"><?php echo tiempoAdmin((float)$seg['inicio_segundos']); ?> - <?php echo tiempoAdmin((float)$seg['fin_segundos']); ?></div>
                <textarea name="texto[<?php echo (int)$seg['id_segmento']; ?>]" rows="3" maxlength="5000"><?php echo htmlspecialchars($seg['texto']); ?></textarea>
            </article>
        <?php endforeach; ?>
        </div>
        <?php if ($segmentos): ?>
        <div class="transcription-save-bar">
            <span>Al guardar se actualiza tambien el archivo de subtitulos.</span>
            <button type="submit" class="btn">Guardar correcciones</button>
        </div>
        <?php endif; ?>
    </form>
</section>
</div>
</body>
</html>
