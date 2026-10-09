<?php
/** DEVIOZ V5.2 - Administracion de retos practicos. */
require_once __DIR__.'/../../../config/sesion.php';
verificarAdmin();
require_once __DIR__.'/../../../models/WatchBuild.php';
require_once __DIR__.'/../../../includes/watchbuild_i18n.php';
$modelo = new WatchBuild();
$buscar = trim((string)($_GET['buscar'] ?? ''));
if (strlen($buscar) > 200) $buscar = substr($buscar,0,200);
$estado = (string)($_GET['estado'] ?? '');
if (!in_array($estado, ['','borrador','publicado','archivado'],true)) $estado = '';
$error = '';
try {
    if (!$modelo->instalado()) throw new RuntimeException('Importa la migración V5.2 antes de abrir Watch & Build.');
    $retos = $modelo->retosAdmin($buscar, $estado);
} catch (Throwable $e) {
    error_log('WatchBuild admin retos: '.$e->getMessage());
    $retos = [];
    $error = 'No se pudo cargar Watch & Build. Comprueba la migración V5.2.';
}
?>
<!DOCTYPE html><html lang="<?php echo deviozIdiomaActual()==='pt'?'pt-BR':wbE(deviozIdiomaActual()); ?>"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Watch & Build - DEVIOZ</title>
<link rel="stylesheet" href="../../../assets/css/admin.css"><link rel="stylesheet" href="../../../assets/css/watchbuild.css?v=5.2"></head>
<body><?php include '../../includes/sidebar.php'; ?><div class="admin-main"><?php include '../../includes/navbar.php'; ?>
<main class="admin-content learning-admin-page wb-admin">
<header class="gestion-header"><div><span class="learning-admin-kicker">TECHFLIX LEARNING LAB · V5.2</span><h1><?php echo wbH('Watch & Build'); ?></h1>
<p class="dashboard-subtitle"><?php echo wbH('Crea retos vinculados a un video, un curso o una Skill. El administrador revisa cada entrega.'); ?></p></div>
<a class="wb-btn" href="editar.php"><?php echo wbH('Nuevo reto'); ?> +</a></header>
<?php if ($error): ?><div class="wb-alert wb-alert-error" role="alert"><?php echo wbE($error); ?></div><?php endif; ?>
<?php if (isset($_GET['ok'])): ?><div class="wb-alert" role="status"><?php echo wbH('Reto guardado correctamente.'); ?></div><?php endif; ?>
<div class="wb-quick-links"><a class="wb-btn wb-btn-outline" href="entregas.php"><?php echo wbH('Entregas y revisiones'); ?> →</a><a class="wb-btn wb-btn-outline" href="../../../public/retos.php"><?php echo wbH('Ver retos'); ?> ↗</a></div>
<form class="wb-filters" method="get"><label><?php echo wbH('Buscar'); ?><input name="buscar" value="<?php echo wbE($buscar); ?>" maxlength="100" placeholder="<?php echo wbH('Título o descripción'); ?>"></label>
<label><?php echo wbH('Estado'); ?><select name="estado"><option value=""><?php echo wbH('Todos los estados'); ?></option>
<?php foreach (['borrador','publicado','archivado'] as $valor): ?><option value="<?php echo $valor; ?>" <?php echo $estado === $valor?'selected':''; ?>><?php echo wbE(wbEstado($valor)); ?></option><?php endforeach; ?>
</select></label><button class="wb-btn" type="submit"><?php echo wbH('Filtrar'); ?></button><a href="index.php" class="wb-btn wb-btn-outline"><?php echo wbH('Limpiar'); ?></a></form>
<div class="wb-table-wrap"><table class="wb-table"><thead><tr><th><?php echo wbH('Reto'); ?></th><th><?php echo wbH('Skill'); ?> / <?php echo wbH('Curso'); ?></th><th><?php echo wbH('Fecha límite'); ?></th><th><?php echo wbH('Participantes'); ?></th><th><?php echo wbH('Estado'); ?></th><th><?php echo wbH('Acciones'); ?></th></tr></thead><tbody>
<?php if (!$retos): ?><tr><td colspan="6"><?php echo wbH('No hay retos para estos filtros.'); ?></td></tr><?php endif; ?>
<?php foreach ($retos as $r): ?><tr>
<td><strong data-i18n-ignore><?php echo wbE($r['titulo']); ?></strong><small><?php echo wbE(wbDificultad($r['dificultad'])); ?> · <?php echo wbH('Nota mínima'); ?> <?php echo (float)$r['nota_minima']; ?>/100</small></td>
<td><span data-i18n-ignore><?php echo wbE($r['skill_nombre'] ?? '—'); ?></span><small data-i18n-ignore><?php echo wbE($r['curso_titulo'] ?? ''); ?></small></td>
<td><?php echo wbE(wbFecha($r['fecha_limite'])); ?></td><td><?php echo (int)$r['participantes']; ?><small><?php echo (int)$r['por_revisar']; ?> <?php echo wbH('Por revisar'); ?></small></td>
<td><span class="wb-status wb-status-<?php echo wbE($r['estado']); ?>"><?php echo wbE(wbEstado($r['estado'])); ?></span></td>
<td><div class="wb-actions"><a href="editar.php?id=<?php echo (int)$r['id_reto']; ?>"><?php echo wbH('Editar reto'); ?></a><a href="entregas.php?id_reto=<?php echo (int)$r['id_reto']; ?>"><?php echo wbH('Ver entregas'); ?></a></div></td>
</tr><?php endforeach; ?>
</tbody></table></div>
</main></div></body></html>
