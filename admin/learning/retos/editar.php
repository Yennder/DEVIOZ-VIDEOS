<?php
/** V5.2 - Alta y edición de retos prácticos. */
require_once __DIR__.'/../../../config/sesion.php';
verificarAdmin();
require_once __DIR__.'/../../../models/WatchBuild.php';
require_once __DIR__.'/../../../includes/watchbuild_i18n.php';
$modelo = new WatchBuild();
$id = max(0, (int)($_GET['id'] ?? 0));
$error = '';
try {
    $actual = $id ? $modelo->reto($id) : null;
    if ($id && !$actual) { http_response_code(404); exit('El reto no existe.'); }
    $opciones = $modelo->opciones();
} catch (Throwable $e) {
    error_log('WatchBuild formulario reto: '.$e->getMessage());
    $error = 'No se pudo cargar el formulario. Comprueba la migración V5.2.';
    $actual = null; $opciones=['cursos'=>[],'videos'=>[],'skills'=>[]];
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verificarCsrfPost();
    try {
        $id = $modelo->guardarReto($_POST, (int)$_SESSION['id_usuario'], $id);
        header('Location: index.php?ok=1', true, 303); exit;
    } catch (InvalidArgumentException $e) { $error=$e->getMessage(); }
    catch (Throwable $e) { error_log('WatchBuild guardar reto: '.$e->getMessage()); $error='No se pudo guardar el reto. Comprueba la conexión y la migración.'; }
}
$datos = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') ? $_POST : ($actual ?? []);
$valor = static fn(string $nombre, string $default = ''): string => (string)($datos[$nombre] ?? $default);
?>
<!DOCTYPE html><html lang="<?php echo deviozIdiomaActual()==='pt'?'pt-BR':wbE(deviozIdiomaActual()); ?>"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Watch & Build - DEVIOZ</title><link rel="stylesheet" href="../../../assets/css/admin.css"><link rel="stylesheet" href="../../../assets/css/watchbuild.css?v=5.2"></head>
<body><?php include '../../includes/sidebar.php'; ?><div class="admin-main"><?php include '../../includes/navbar.php'; ?><main class="admin-content learning-admin-page wb-admin">
<header class="gestion-header"><div><span class="learning-admin-kicker">WATCH & BUILD · V5.2</span><h1><?php echo wbH($id?'Editar reto':'Nuevo reto'); ?></h1>
<p class="dashboard-subtitle"><?php echo wbH('Elige al menos un video, curso o Skill.'); ?></p></div><a class="wb-btn wb-btn-outline" href="index.php">← <?php echo wbH('Volver a retos'); ?></a></header>
<?php if ($error): ?><div class="wb-alert wb-alert-error" role="alert"><?php echo wbE($error); ?></div><?php endif; ?>
<?php if ($actual && (int)$actual['total_entregas']): ?><div class="wb-alert"><?php echo wbH('No se puede editar criterios ni vínculos después de recibir entregas.'); ?></div><?php endif; ?>
<form method="post" class="wb-admin-form wb-panel">
<?php echo csrfInput(); ?>
<div class="wb-form-grid">
<label class="wb-wide"><?php echo wbH('Título'); ?> * <input name="titulo" required maxlength="180" minlength="6" value="<?php echo wbE($valor('titulo')); ?>" placeholder="<?php echo wbH('Título del reto'); ?>"></label>
<label class="wb-wide"><?php echo wbH('Descripción'); ?> * <textarea name="descripcion" rows="3" maxlength="3000" required placeholder="<?php echo wbH('Qué se espera aprender y construir'); ?>"><?php echo wbE($valor('descripcion')); ?></textarea></label>
<label class="wb-wide"><?php echo wbH('Instrucciones'); ?> * <textarea name="instrucciones" rows="7" maxlength="18000" required placeholder="<?php echo wbH('Pasos, requisitos y resultado esperado'); ?>"><?php echo wbE($valor('instrucciones')); ?></textarea></label>
<label class="wb-wide"><?php echo wbH('Criterios de evaluación'); ?> * <textarea name="criterios" rows="5" maxlength="6000" required placeholder="<?php echo wbH('Cómo se calificará la evidencia'); ?>"><?php echo wbE($valor('criterios')); ?></textarea></label>
<label><?php echo wbH('Curso'); ?> <select name="id_curso"><option value=""><?php echo wbH('Sin relación'); ?></option>
<?php foreach ($opciones['cursos'] as $c): ?><option value="<?php echo (int)$c['id_curso']; ?>" <?php echo (int)$valor('id_curso')===(int)$c['id_curso']?'selected':''; ?> data-i18n-ignore><?php echo wbE($c['titulo']); ?></option><?php endforeach; ?></select></label>
<label><?php echo wbH('Video'); ?> <select name="id_video"><option value=""><?php echo wbH('Sin relación'); ?></option>
<?php foreach ($opciones['videos'] as $v): ?><option value="<?php echo (int)$v['id_video']; ?>" <?php echo (int)$valor('id_video')===(int)$v['id_video']?'selected':''; ?> data-i18n-ignore><?php echo wbE($v['titulo']); ?></option><?php endforeach; ?></select></label>
<label><?php echo wbH('Skill'); ?> <select name="id_skill"><option value=""><?php echo wbH('Sin relación'); ?></option>
<?php foreach ($opciones['skills'] as $s): ?><option value="<?php echo (int)$s['id_skill']; ?>" <?php echo (int)$valor('id_skill')===(int)$s['id_skill']?'selected':''; ?> data-i18n-ignore><?php echo wbE($s['icono'].' '.$s['nombre']); ?></option><?php endforeach; ?></select></label>
<label><?php echo wbH('Dificultad'); ?><select name="dificultad">
<?php foreach (['basica','intermedia','avanzada'] as $nivel): ?><option value="<?php echo $nivel; ?>" <?php echo $valor('dificultad','intermedia')===$nivel?'selected':''; ?>><?php echo wbE(wbDificultad($nivel)); ?></option><?php endforeach; ?></select></label>
<label><?php echo wbH('Nota mínima'); ?> (0–100)<input type="number" name="nota_minima" min="0" max="100" step="0.01" required value="<?php echo wbE($valor('nota_minima','70')); ?>"></label>
<label><?php echo wbH('Fecha límite'); ?> (<?php echo wbH('Opcional'); ?>)<input type="datetime-local" name="fecha_limite" value="<?php echo wbE(str_replace(' ','T',substr($valor('fecha_limite'),0,16))); ?>"></label>
<label><?php echo wbH('Estado'); ?><select name="estado">
<?php foreach (['borrador','publicado','archivado'] as $estado): ?><option value="<?php echo $estado; ?>" <?php echo $valor('estado','borrador')===$estado?'selected':''; ?>><?php echo wbE(wbEstado($estado)); ?></option><?php endforeach; ?></select></label>
</div><div class="wb-form-actions"><button class="wb-btn" type="submit"><?php echo wbH($id?'Guardar reto':'Crear reto'); ?></button><a class="wb-btn wb-btn-outline" href="index.php"><?php echo wbH('Volver a retos'); ?></a></div>
</form></main></div></body></html>
