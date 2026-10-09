<?php
require_once __DIR__ . '/../../config/sesion.php';
verificarAdmin();
require_once __DIR__ . '/../../models/CuestionarioVideo.php';
require_once __DIR__ . '/../../services/CuestionarioVideoIAService.php';

$idVideo = filter_var($_GET['id_video'] ?? $_POST['id_video'] ?? null, FILTER_VALIDATE_INT) ?: 0;
if ($idVideo <= 0) { header('Location: listar.php'); exit; }
$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$modelo = new CuestionarioVideo();
$instalado = $modelo->instalado();
$video = $modelo->video($idVideo);
if (!$video) { header('Location: listar.php'); exit; }
$error = '';
$entradaFallida = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verificarCsrfPost();
    $accion = (string)($_POST['accion'] ?? '');
    try {
        if (!$instalado) throw new RuntimeException('Primero importa la migracion SQL V4.5.4.');
        $admin = (int)$_SESSION['id_usuario'];
        if ($accion === 'generar') {
            (new CuestionarioVideoIAService())->generar($idVideo, (string)$video['titulo'], $admin);
            $mensaje = 'DEVIOZ AI genero un borrador de cinco preguntas. Revisalo antes de publicarlo.';
        } elseif ($accion === 'guardar') {
            $textos = $_POST['pregunta'] ?? [];
            $alternativas = $_POST['opcion'] ?? [];
            $correctas = $_POST['correcta'] ?? [];
            $explicaciones = $_POST['explicacion'] ?? [];
            if (!is_array($textos) || count($textos) !== 5 || !is_array($alternativas) || !is_array($correctas) || !is_array($explicaciones)) {
                throw new InvalidArgumentException('Completa el formulario de las cinco preguntas.');
            }
            $filas = [];
            for ($i = 0; $i < 5; $i++) {
                $filas[] = [
                    'pregunta' => $textos[$i] ?? '',
                    'explicacion' => $explicaciones[$i] ?? '',
                    'opciones' => $alternativas[$i] ?? [],
                    'correcta' => $correctas[$i] ?? null,
                ];
            }
            $entradaFallida = $filas;
            $anterior = $modelo->version($idVideo, 'borrador');
            $esIA = $anterior && $anterior['origen'] === 'ia';
            $modelo->guardarBorrador($idVideo, $filas, $admin, $esIA ? 'ia' : 'manual', $esIA ? [
                'fuente_hash' => $anterior['fuente_hash'],
                'proveedor' => $anterior['proveedor'],
                'modelo' => $anterior['modelo'],
            ] : []);
            $mensaje = 'Borrador guardado. Revisa las cinco respuestas correctas y publica cuando este listo.';
        } elseif ($accion === 'publicar') {
            $borrador = $modelo->version($idVideo, 'borrador');
            if (!$borrador) throw new InvalidArgumentException('Primero guarda un borrador de cinco preguntas.');
            $hash = $borrador['origen'] === 'ia' ? (new CuestionarioVideoIAService())->fuente($idVideo, false)['hash'] : null;
            $modelo->publicar($idVideo, (int)$borrador['id_cuestionario'], $admin, $hash);
            $mensaje = 'Cuestionario publicado. Los usuarios ya pueden responder las cinco preguntas.';
        } elseif ($accion === 'descartar') {
            $modelo->descartarBorrador($idVideo);
            $mensaje = 'Borrador descartado. La version publicada sigue disponible.';
        } else {
            throw new InvalidArgumentException('Accion no valida.');
        }
        header('Location: cuestionario.php?id_video=' . $idVideo . '&msg=' . rawurlencode($mensaje));
        exit;
    } catch (Throwable $e) {
        error_log('DEVIOZ V4.5.4 - Admin cuestionario: ' . $e->getMessage());
        $error = $e instanceof InvalidArgumentException || $e instanceof RuntimeException
            ? $e->getMessage() : 'No fue posible guardar las preguntas. Verifica MySQL y vuelve a intentarlo.';
    }
}
$borrador = $instalado ? $modelo->version($idVideo, 'borrador') : null;
$publicado = $instalado ? $modelo->version($idVideo, 'publicado') : null;
$preguntas = $borrador ? $modelo->preguntas((int)$borrador['id_cuestionario']) : [];
$editor = [];
if ($entradaFallida !== null) {
    $editor = $entradaFallida;
} elseif ($preguntas) {
    foreach ($preguntas as $p) {
        $correcta = 0;
        foreach ($p['opciones'] as $k => $o) if (!empty($o['es_correcta'])) $correcta = $k;
        $editor[] = ['pregunta' => $p['pregunta'], 'explicacion' => $p['explicacion'], 'opciones' => array_column($p['opciones'], 'texto'), 'correcta' => $correcta];
    }
}
while (count($editor) < 5) $editor[] = ['pregunta' => '', 'explicacion' => '', 'opciones' => ['', '', '', ''], 'correcta' => 0];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cuestionario del video - DEVIOZ VIDEOS</title>
<link rel="stylesheet" href="../../assets/css/admin.css">
<link rel="stylesheet" href="../../assets/css/cuestionarios.css?v=4.5.4">
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="admin-main">
<?php include '../includes/navbar.php'; ?>
<section class="admin-content vq-admin">
    <div class="gestion-header">
        <div><span class="vq-eyebrow">DEVIOZ AI / V4.5.4</span><h1>Cuestionario del video</h1><p><?php echo $h($video['titulo']); ?></p></div>
        <div class="acciones-contenedor"><a class="btn-limpiar" href="listar.php">Volver a videos</a><a class="btn-editar" href="resultados_cuestionarios.php?id_video=<?php echo $idVideo; ?>">Ver notas de este video</a><a class="btn-editar" target="_blank" rel="noopener" href="../../public/detalle.php?id=<?php echo $idVideo; ?>">Ver video</a></div>
    </div>
    <?php if (!empty($_GET['msg'])): ?><div class="vq-alert is-success" role="status"><?php echo $h($_GET['msg']); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="vq-alert is-error" role="alert"><?php echo $h($error); ?></div><?php endif; ?>
    <?php if (!$instalado): ?>
        <div class="vq-alert is-error">Faltan tablas. Importa primero <code>MIGRACION_V4.5.4_CUESTIONARIOS_VIDEO.sql</code> en phpMyAdmin.</div>
    <?php else: ?>
    <div class="vq-overview">
        <div><span>Preguntas por video</span><strong>5</strong><small>20 puntos cada una</small></div>
        <div><span>Estado publico</span><strong><?php echo $publicado ? 'Publicado' : 'Sin publicar'; ?></strong><small><?php echo $publicado ? 'Version #' . (int)$publicado['id_cuestionario'] : 'Pendiente de revision'; ?></small></div>
        <div><span>Editor</span><strong><?php echo $borrador ? 'Borrador' : 'Nuevo'; ?></strong><small><?php echo $borrador ? ($borrador['origen'] === 'ia' ? 'Generado con IA' : 'Creado manualmente') : '5 preguntas editables'; ?></small></div>
    </div>
    <section class="vq-admin-card vq-ai-card">
        <div><span class="vq-eyebrow">Generacion asistida</span><h2>Crear preguntas con DEVIOZ AI</h2>
        <p>Usa la transcripcion del video y genera cinco preguntas con cuatro opciones. Revisa los resultados antes de publicarlos. Si el video no tiene transcripcion, puedes usar el editor manual.</p></div>
        <form method="POST" onsubmit="return confirm('Se reemplazara el borrador actual, si existe. La version publicada no cambiara. Continuar?');">
            <?php echo csrfInput(); ?><input type="hidden" name="id_video" value="<?php echo $idVideo; ?>">
            <button type="submit" name="accion" value="generar" class="btn">Generar 5 preguntas con IA</button>
        </form>
    </section>
    <section class="vq-admin-card">
        <div class="vq-editor-header"><div><span class="vq-eyebrow">Editor y revision</span><h2><?php echo $borrador ? 'Editar borrador' : 'Crear cuestionario manual'; ?></h2><p>Elige una unica respuesta correcta por pregunta. Los cambios no afectan al publico hasta publicarlos.</p></div><span class="vq-score-pill">Total 100 puntos</span></div>
        <form method="POST" id="vqEditor" class="vq-editor-form">
        <?php echo csrfInput(); ?><input type="hidden" name="id_video" value="<?php echo $idVideo; ?>">
        <?php foreach ($editor as $i => $p): ?>
        <article class="vq-question-editor">
            <div class="vq-editor-row"><span class="vq-number"><?php echo sprintf('%02d', $i + 1); ?></span><label>Pregunta <?php echo $i + 1; ?><textarea name="pregunta[<?php echo $i; ?>]" maxlength="800" rows="2" required placeholder="Escribe una pregunta sobre este video"><?php echo $h($p['pregunta']); ?></textarea></label><span class="vq-point-label">20 pts</span></div>
            <div class="vq-choice-grid">
                <?php for ($j = 0; $j < 4; $j++): ?>
                <label class="vq-choice-editor"><input type="radio" name="correcta[<?php echo $i; ?>]" value="<?php echo $j; ?>" <?php echo (int)$p['correcta'] === $j ? 'checked' : ''; ?> required aria-label="Respuesta correcta <?php echo $j + 1; ?> para pregunta <?php echo $i + 1; ?>"><span class="vq-letter"><?php echo chr(65 + $j); ?></span><input type="text" name="opcion[<?php echo $i; ?>][<?php echo $j; ?>]" maxlength="500" value="<?php echo $h($p['opciones'][$j] ?? ''); ?>" placeholder="Alternativa <?php echo $j + 1; ?>" required></label>
                <?php endfor; ?>
            </div>
            <label class="vq-explanation">Explicacion para revision administrativa (opcional)<textarea name="explicacion[<?php echo $i; ?>]" maxlength="2000" rows="2"><?php echo $h($p['explicacion']); ?></textarea></label>
        </article>
        <?php endforeach; ?>
        <div class="vq-editor-actions"><button class="btn" type="submit" name="accion" value="guardar">Guardar borrador</button><small>Guardar no publica las preguntas ni modifica intentos anteriores.</small></div>
        </form>
    </section>
    <?php if ($borrador): ?>
    <section class="vq-admin-card vq-publish-card">
        <div><span class="vq-eyebrow">Revision humana obligatoria</span><h2>Publicar las cinco preguntas revisadas</h2>
        <p>Si hay una version publica, quedara archivada. Las puntuaciones anteriores se conservan. Guarda primero cualquier cambio del editor.</p>
        <?php if ($borrador['origen'] === 'ia'): ?><small>Fuente IA: <?php echo $h($borrador['proveedor'] ?: 'Proveedor no informado'); ?>. Si la transcripcion cambio, sera necesario regenerar.</small><?php endif; ?></div>
        <div class="vq-publish-actions">
            <form method="POST" onsubmit="return confirm('Publicar estas cinco preguntas para todos los usuarios?');"><?php echo csrfInput(); ?><input type="hidden" name="id_video" value="<?php echo $idVideo; ?>"><button type="submit" name="accion" value="publicar" class="btn">Publicar cuestionario</button></form>
            <form method="POST" onsubmit="return confirm('Descartar el borrador actual? La version publicada se conservara.');"><?php echo csrfInput(); ?><input type="hidden" name="id_video" value="<?php echo $idVideo; ?>"><button type="submit" name="accion" value="descartar" class="btn-limpiar">Descartar borrador</button></form>
        </div>
    </section>
    <?php endif; ?>
    <?php if ($publicado): ?>
    <section class="vq-admin-card"><span class="vq-eyebrow">Version publicada</span><h2>Preguntas disponibles para los usuarios</h2><ol class="vq-published-list">
        <?php foreach ($modelo->preguntas((int)$publicado['id_cuestionario']) as $p): ?><li><?php echo $h($p['pregunta']); ?></li><?php endforeach; ?>
    </ol></section>
    <?php endif; ?>
    <?php endif; ?>
</section></div>
<script src="../../assets/js/admin.js"></script>
</body></html>
