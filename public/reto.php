<?php
/** Watch & Build V5.2 - Reto, entrega de evidencia e historial del participante. */
require_once __DIR__ . '/../config/sesion.php';
verificarSesion();
require_once __DIR__ . '/../models/WatchBuild.php';
require_once __DIR__ . '/../controllers/VideoController.php';
require_once __DIR__ . '/../includes/watchbuild_i18n.php';
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: 0;
if (!$id) { http_response_code(404); exit('Reto no encontrado.'); }
$categorias = (new VideoController())->listarCategorias();
$modelo = new WatchBuild();
$error = '';
try {
    $reto = $modelo->reto($id);
} catch (Throwable $e) {
    error_log('WatchBuild detalle: '.$e->getMessage());
    $reto = null;
    $error = 'No se pudo cargar el reto. Comprueba la migración V5.2.';
}
if ($reto && !esAdmin() && $reto['estado'] !== 'publicado' && !$modelo->ultimaEntrega($id, (int)$_SESSION['id_usuario'])) {
    $reto = null;
}
if (!$reto) { http_response_code(404); exit($error ?: 'Reto no encontrado.'); }
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verificarCsrfPost();
    try {
        $modelo->enviar($id, (int)$_SESSION['id_usuario'], (string)($_POST['descripcion'] ?? ''), (string)($_POST['enlace'] ?? ''), $_FILES['evidencia'] ?? null);
        header('Location: reto.php?id='.$id.'&ok=1', true, 303);
        exit;
    } catch (InvalidArgumentException | RuntimeException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('WatchBuild enviar entrega: ' . $e->getMessage());
        $error = 'No se pudo guardar tu entrega. Inténtalo nuevamente.';
    }
}
try { $historial = $modelo->historial($id, (int)$_SESSION['id_usuario']); }
catch (Throwable $e) { $historial = []; $error = 'No se pudo cargar el historial de entregas.'; }
$ultima = $historial[0] ?? null;
$puedeEnviar = $modelo->puedeEnviar($reto, $ultima);
$vencido = $reto['fecha_limite'] && strtotime((string)$reto['fecha_limite']) < time();
?>
<?php include __DIR__.'/../includes/public_header.php'; ?>
<?php include __DIR__.'/../includes/public_navbar.php'; ?>
<div class="layout"><?php include __DIR__.'/../includes/public_sidebar.php'; ?>
<main class="public-content wb-page">
<a class="wb-back" href="retos.php">← <?php echo wbH('Volver a retos'); ?></a>
<section class="wb-hero wb-hero-detail"><div><span class="wb-eyebrow">WATCH & BUILD · V5.2</span>
<h1 data-i18n-ignore><?php echo wbE($reto['titulo']); ?></h1><p data-i18n-ignore><?php echo wbE($reto['descripcion']); ?></p>
<div class="wb-tags"><span class="wb-pill"><?php echo wbE(wbDificultad($reto['dificultad'])); ?></span><span class="wb-pill">🎯 <?php echo wbH('Nota mínima'); ?>: <?php echo (float)$reto['nota_minima']; ?>/100</span><span class="wb-pill">📅 <?php echo wbE(wbFecha($reto['fecha_limite'])); ?></span></div>
</div></section>
<?php if (isset($_GET['ok'])): ?><div class="wb-alert" role="status"><?php echo wbH('Entrega enviada correctamente.'); ?></div><?php endif; ?>
<?php if ($error): ?><div class="wb-alert wb-alert-error" role="alert"><?php echo wbE($error); ?></div><?php endif; ?>
<div class="wb-detail-grid">
<div class="wb-main-column">
<section class="wb-panel"><h2><?php echo wbH('Instrucciones'); ?></h2><div class="wb-prose" data-i18n-ignore><?php echo nl2br(wbE($reto['instrucciones'])); ?></div></section>
<section class="wb-panel"><h2><?php echo wbH('Criterios de evaluación'); ?></h2><div class="wb-prose" data-i18n-ignore><?php echo nl2br(wbE($reto['criterios'])); ?></div></section>
<section class="wb-panel"><h2><?php echo wbH('Historial de entregas'); ?> (<?php echo count($historial); ?>)</h2>
<?php if (!$historial): ?><div class="wb-empty"><?php echo wbH('Todavía no hay entregas.'); ?></div><?php endif; ?>
<?php foreach ($historial as $e): ?>
<article class="wb-attempt"><div class="wb-attempt-head"><strong><?php echo wbH('Intento'); ?> <?php echo (int)$e['numero_intento']; ?></strong><span class="wb-status wb-status-<?php echo wbE($e['estado']); ?>"><?php echo wbE(wbEstado($e['estado'])); ?></span></div>
<small><?php echo wbH('Fecha de entrega'); ?>: <?php echo wbE(wbFecha($e['fecha_entrega'])); ?></small>
<?php if ($e['nota'] !== null): ?><div class="wb-score"><?php echo wbH('Puntuación obtenida'); ?>: <strong><?php echo (float)$e['nota']; ?>/100</strong></div><?php endif; ?>
<div class="wb-prose" data-i18n-ignore><?php echo nl2br(wbE($e['descripcion'])); ?></div>
<div class="wb-links">
<?php if ($e['enlace']): ?><a href="<?php echo wbE($e['enlace']); ?>" target="_blank" rel="noopener noreferrer nofollow"><?php echo wbH('Abrir enlace'); ?> ↗</a><?php endif; ?>
<?php if ($e['archivo_guardado']): ?><a href="descargar_entrega.php?id=<?php echo (int)$e['id_entrega']; ?>"><?php echo wbH('Descargar evidencia'); ?> ↓</a><?php endif; ?>
</div>
<?php if ($e['comentario_admin']): ?><div class="wb-feedback"><strong><?php echo wbH('Observaciones del revisor'); ?></strong><div class="wb-prose" data-i18n-ignore><?php echo nl2br(wbE($e['comentario_admin'])); ?></div></div><?php endif; ?>
</article>
<?php endforeach; ?>
</section>
</div>
<aside class="wb-side-column">
<section class="wb-panel"><h2><?php echo wbH('Vínculos'); ?></h2>
<div class="wb-meta">
<?php if ($reto['id_video'] && $reto['video_titulo']): ?><a href="detalle.php?id=<?php echo (int)$reto['id_video']; ?>">🎬 <?php echo wbH('Video'); ?>: <span data-i18n-ignore><?php echo wbE($reto['video_titulo']); ?></span></a><?php endif; ?>
<?php if ($reto['id_curso'] && $reto['curso_titulo']): ?><a href="aprendizaje.php">📘 <?php echo wbH('Curso'); ?>: <span data-i18n-ignore><?php echo wbE($reto['curso_titulo']); ?></span></a><?php endif; ?>
<?php if ($reto['id_skill'] && $reto['skill_nombre']): ?><a href="skills.php">🧩 <?php echo wbH('Skill'); ?>: <span data-i18n-ignore><?php echo wbE($reto['skill_nombre']); ?></span></a><?php endif; ?>
</div></section>
<section class="wb-panel" id="enviar-evidencia"><h2><?php echo wbH('Tu evidencia'); ?></h2>
<?php if ($puedeEnviar): ?>
<p class="wb-muted"><?php echo wbH('Puedes subir PDF, JPG, PNG, WEBP o ZIP (máximo 10 MB).'); ?></p>
<form method="post" enctype="multipart/form-data" action="reto.php?id=<?php echo $id; ?>#enviar-evidencia" class="wb-form">
<?php echo csrfInput(); ?>
<label><?php echo wbH('Describe tu trabajo'); ?> *<textarea name="descripcion" minlength="20" maxlength="8000" rows="6" required placeholder="<?php echo wbH('Explica cómo lo desarrollaste, qué lograste y cómo comprobarlo.'); ?>"><?php echo wbE($_POST['descripcion'] ?? ''); ?></textarea></label>
<label><?php echo wbH('Enlace de evidencia'); ?> (<?php echo wbH('Opcional'); ?>)<input type="url" name="enlace" maxlength="1024" placeholder="https://github.com/..." value="<?php echo wbE($_POST['enlace'] ?? ''); ?>"></label>
<label><?php echo wbH('Adjuntar archivo'); ?> (<?php echo wbH('Opcional'); ?>)<input type="file" name="evidencia" accept=".pdf,.jpg,.jpeg,.png,.webp,.zip" aria-describedby="wb-evidence-note"></label>
<small id="wb-evidence-note"><?php echo wbH('Puedes subir PDF, JPG, PNG, WEBP o ZIP (máximo 10 MB).'); ?></small>
<button class="wb-btn" type="submit"><?php echo wbH('Enviar entrega'); ?> →</button>
</form>
<?php elseif ($vencido): ?><div class="wb-empty"><?php echo wbH('El plazo de entrega terminó.'); ?></div>
<?php elseif ($ultima && $ultima['estado']==='aprobada'): ?><div class="wb-alert"><?php echo wbH('Reto aprobado. Tu evidencia queda en el historial.'); ?></div>
<?php elseif ($ultima && $ultima['estado']==='enviada'): ?><div class="wb-empty"><?php echo wbH('Ya entregaste este reto. Espera la revisión administrativa.'); ?></div>
<?php else: ?><div class="wb-empty"><?php echo wbH('Este reto no está disponible para nuevas entregas.'); ?></div><?php endif; ?>
</section>
</aside>
</div>
</main></div><?php include __DIR__.'/../includes/public_footer.php'; ?>
