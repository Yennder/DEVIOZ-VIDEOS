<?php
/** V5.2 - Reporte de evidencias e intentos practicos. */
require_once __DIR__.'/../../../config/sesion.php';
verificarAdmin();
require_once __DIR__.'/../../../models/WatchBuild.php';
require_once __DIR__.'/../../../includes/watchbuild_i18n.php';
$modelo = new WatchBuild();
$idReto = max(0,(int)($_GET['id_reto'] ?? 0));
$estado=(string)($_GET['estado'] ?? '');
if (!in_array($estado, ['','enviada','correcciones','aprobada','no_aprobada'],true)) $estado='';
$error='';
try {
    if (!$modelo->instalado()) throw new RuntimeException('Migración no instalada');
    $retos = $modelo->retosAdmin();
    $entregas = $modelo->entregasAdmin($idReto,$estado);
} catch (Throwable $e) {
    error_log('WatchBuild admin entregas: '.$e->getMessage());
    $retos=$entregas=[]; $error='No se pudo consultar las entregas. Comprueba la migración V5.2.';
}
?>
<!DOCTYPE html><html lang="<?php echo deviozIdiomaActual()==='pt'?'pt-BR':wbE(deviozIdiomaActual()); ?>"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Entregas Watch & Build - DEVIOZ</title><link rel="stylesheet" href="../../../assets/css/admin.css"><link rel="stylesheet" href="../../../assets/css/watchbuild.css?v=5.2"></head>
<body><?php include '../../includes/sidebar.php'; ?><div class="admin-main"><?php include '../../includes/navbar.php'; ?><main class="admin-content learning-admin-page wb-admin">
<header class="gestion-header"><div><span class="learning-admin-kicker">WATCH & BUILD · V5.2</span><h1><?php echo wbH('Entregas y revisiones'); ?></h1><p class="dashboard-subtitle"><?php echo wbH('El administrador revisa cada entrega.'); ?></p></div><a class="wb-btn wb-btn-outline" href="index.php">← <?php echo wbH('Volver a retos'); ?></a></header>
<?php if ($error): ?><div class="wb-alert wb-alert-error"><?php echo wbE($error); ?></div><?php endif; ?>
<form class="wb-filters" method="get" action="entregas.php">
<label><?php echo wbH('Reto'); ?><select name="id_reto"><option value="0"><?php echo wbH('Todos'); ?></option>
<?php foreach ($retos as $r): ?><option value="<?php echo (int)$r['id_reto']; ?>" <?php echo $idReto===(int)$r['id_reto']?'selected':''; ?> data-i18n-ignore><?php echo wbE($r['titulo']); ?></option><?php endforeach; ?></select></label>
<label><?php echo wbH('Estado'); ?><select name="estado"><option value=""><?php echo wbH('Todos los estados'); ?></option>
<?php foreach (['enviada','correcciones','aprobada','no_aprobada'] as $v): ?><option value="<?php echo $v; ?>" <?php echo $estado===$v?'selected':''; ?>><?php echo wbE(wbEstado($v)); ?></option><?php endforeach; ?></select></label>
<button class="wb-btn"><?php echo wbH('Filtrar'); ?></button><a class="wb-btn wb-btn-outline" href="entregas.php"><?php echo wbH('Limpiar'); ?></a></form>
<div class="wb-table-wrap"><table class="wb-table"><thead><tr><th><?php echo wbH('Trabajador'); ?></th><th><?php echo wbH('Reto'); ?></th><th><?php echo wbH('Intento'); ?></th><th><?php echo wbH('Fecha de entrega'); ?></th><th><?php echo wbH('Nota'); ?></th><th><?php echo wbH('Estado'); ?></th><th><?php echo wbH('Acciones'); ?></th></tr></thead><tbody>
<?php if (!$entregas): ?><tr><td colspan="7"><?php echo wbH('Todavía no hay entregas.'); ?></td></tr><?php endif; ?>
<?php foreach ($entregas as $e): ?><tr>
<td><strong data-i18n-ignore><?php echo wbE($e['usuario_nombre']); ?></strong><small data-i18n-ignore><?php echo wbE($e['email']); ?></small></td>
<td data-i18n-ignore><?php echo wbE($e['reto_titulo']); ?><?php if($e['skill_nombre']): ?><small><?php echo wbE($e['skill_nombre']); ?></small><?php endif; ?></td>
<td><?php echo (int)$e['numero_intento']; ?></td><td><?php echo wbE(wbFecha($e['fecha_entrega'])); ?></td>
<td><?php echo $e['nota']!==null?(float)$e['nota'].'/100':'—'; ?></td>
<td><span class="wb-status wb-status-<?php echo wbE($e['estado']); ?>"><?php echo wbE(wbEstado($e['estado'])); ?></span></td>
<td><a class="wb-action-link" href="revisar.php?id=<?php echo (int)$e['id_entrega']; ?>"><?php echo wbH('Ver detalle'); ?> →</a></td>
</tr><?php endforeach; ?>
</tbody></table></div></main></div></body></html>
