<?php
/** V5.2 - Revision individual del intento y retroalimentacion. */
require_once __DIR__.'/../../../config/sesion.php';
verificarAdmin();
require_once __DIR__.'/../../../models/WatchBuild.php';
require_once __DIR__.'/../../../includes/watchbuild_i18n.php';
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: 0;
if (!$id) { http_response_code(404); exit('Entrega no encontrada.'); }
$modelo = new WatchBuild();
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET')==='POST') {
    verificarCsrfPost();
    try {
        $modelo->revisar($id,(int)$_SESSION['id_usuario'],(string)($_POST['decision'] ?? ''),(string)($_POST['nota'] ?? ''),(string)($_POST['comentario_admin'] ?? ''));
        header('Location: revisar.php?id='.$id.'&ok=1', true, 303); exit;
    } catch (InvalidArgumentException $e) { $error=$e->getMessage(); }
    catch (Throwable $e) { error_log('WatchBuild revision: '.$e->getMessage()); $error='No se pudo guardar la revisión.'; }
}
try { $e=$modelo->entrega($id); }
catch (Throwable $ex) { $e=null; }
if (!$e) { http_response_code(404); exit('Entrega no encontrada.'); }
?>
<!DOCTYPE html><html lang="<?php echo deviozIdiomaActual()==='pt'?'pt-BR':wbE(deviozIdiomaActual()); ?>"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Revisión Watch & Build - DEVIOZ</title><link rel="stylesheet" href="../../../assets/css/admin.css"><link rel="stylesheet" href="../../../assets/css/watchbuild.css?v=5.2"></head>
<body><?php include '../../includes/sidebar.php'; ?><div class="admin-main"><?php include '../../includes/navbar.php'; ?><main class="admin-content learning-admin-page wb-admin">
<header class="gestion-header"><div><span class="learning-admin-kicker">WATCH & BUILD · V5.2</span><h1><?php echo wbH('Revisar entrega'); ?> #<?php echo (int)$e['id_entrega']; ?></h1>
<p class="dashboard-subtitle"><?php echo wbH('Trabajador'); ?>: <span data-i18n-ignore><?php echo wbE($e['usuario_nombre']); ?></span> · <?php echo wbH('Intento'); ?> <?php echo (int)$e['numero_intento']; ?></p>
</div><a class="wb-btn wb-btn-outline" href="entregas.php?id_reto=<?php echo (int)$e['id_reto']; ?>">← <?php echo wbH('Volver a entregas'); ?></a></header>
<?php if ($error): ?><div class="wb-alert wb-alert-error" role="alert"><?php echo wbE($error); ?></div><?php endif; ?>
<?php if(isset($_GET['ok'])): ?><div class="wb-alert"><?php echo wbH('Revisión registrada.'); ?></div><?php endif; ?>
<div class="wb-detail-grid"><section class="wb-panel"><span class="wb-eyebrow"><?php echo wbH('Reto'); ?></span><h2 data-i18n-ignore><?php echo wbE($e['reto_titulo']); ?></h2><div class="wb-tags"><span class="wb-status wb-status-<?php echo wbE($e['estado']); ?>"><?php echo wbE(wbEstado($e['estado'])); ?></span><span class="wb-pill"><?php echo wbH('Fecha de entrega'); ?>: <?php echo wbE(wbFecha($e['fecha_entrega'])); ?></span></div>
<h3><?php echo wbH('Describe tu trabajo'); ?></h3><div class="wb-prose" data-i18n-ignore><?php echo nl2br(wbE($e['descripcion'])); ?></div>
<div class="wb-links"><?php if($e['enlace']): ?><a href="<?php echo wbE($e['enlace']); ?>" target="_blank" rel="noopener noreferrer nofollow"><?php echo wbH('Abrir enlace'); ?> ↗</a><?php endif; ?>
<?php if($e['archivo_guardado']): ?><a href="../../../public/descargar_entrega.php?id=<?php echo (int)$e['id_entrega']; ?>"><?php echo wbH('Descargar evidencia'); ?> ↓</a><?php endif; ?></div>
<?php if($e['fecha_revision']): ?><div class="wb-feedback"><strong><?php echo wbH('Puntuación obtenida'); ?>: <?php echo (float)$e['nota']; ?>/100</strong><small><?php echo wbE(wbFecha($e['fecha_revision'])); ?></small><div data-i18n-ignore class="wb-prose"><?php echo nl2br(wbE($e['comentario_admin'] ?? '')); ?></div></div><?php endif; ?>
</section>
<aside class="wb-side-column"><section class="wb-panel"><h2><?php echo wbH('Evaluar entrega'); ?></h2>
<?php if ($e['estado'] !== 'enviada'): ?><div class="wb-empty"><?php echo wbH('Revisión registrada.'); ?></div>
<?php else: ?>
<form class="wb-form" method="post" action="revisar.php?id=<?php echo $id; ?>">
<?php echo csrfInput(); ?>
<label><?php echo wbH('Nota'); ?> (0–100) <small><?php echo wbH('Nota mínima'); ?>: <?php echo (float)$e['nota_minima']; ?>/100</small><input type="number" name="nota" value="<?php echo wbE($_POST['nota'] ?? ''); ?>" required min="0" max="100" step="0.01"></label>
<label><?php echo wbH('Decisión'); ?><select name="decision" required>
<?php foreach(['aprobada'=>'Aprobar','correcciones'=>'Solicitar correcciones','no_aprobada'=>'No aprobar'] as $status=>$label): ?><option value="<?php echo $status; ?>" <?php echo ($_POST['decision'] ?? '')===$status?'selected':''; ?>><?php echo wbH($label); ?></option><?php endforeach; ?></select></label>
<label><?php echo wbH('Retroalimentación'); ?><textarea name="comentario_admin" maxlength="6000" rows="6" placeholder="<?php echo wbH('Explica qué salió bien o qué debe mejorarse'); ?>"><?php echo wbE($_POST['comentario_admin'] ?? ''); ?></textarea></label>
<button class="wb-btn" type="submit"><?php echo wbH('Guardar revisión'); ?></button>
</form><?php endif; ?>
</section></aside></div>
</main></div></body></html>
