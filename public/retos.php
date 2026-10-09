<?php
/** Watch & Build V5.2 - Catalogo de retos para colaboradores. */
require_once __DIR__ . '/../config/sesion.php';
verificarSesion();
require_once __DIR__ . '/../models/WatchBuild.php';
require_once __DIR__ . '/../controllers/VideoController.php';
require_once __DIR__ . '/../includes/watchbuild_i18n.php';
$categorias = (new VideoController())->listarCategorias();
$buscar = trim((string)($_GET['buscar'] ?? ''));
if (strlen($buscar) > 200) $buscar = substr($buscar, 0, 200);
$skillId = max(0, (int)($_GET['skill'] ?? 0));
$retos = $skills = [];
$error = '';
try {
    $modelo = new WatchBuild();
    if (!$modelo->instalado()) throw new RuntimeException('Importa la migración V5.2 desde phpMyAdmin antes de utilizar Watch & Build.');
    $skills = $modelo->opciones()['skills'];
    $retos = $modelo->retosPublicos((int)$_SESSION['id_usuario'], $buscar, $skillId);
} catch (Throwable $e) {
    error_log('WatchBuild catalogo: ' . $e->getMessage());
    $error = 'No se pudo cargar Watch & Build. Comprueba la migración V5.2.';
}
?>
<?php include __DIR__.'/../includes/public_header.php'; ?>
<?php include __DIR__.'/../includes/public_navbar.php'; ?>
<div class="layout"><?php include __DIR__.'/../includes/public_sidebar.php'; ?>
<main class="public-content wb-page">
  <section class="wb-hero">
    <div><span class="wb-eyebrow">DEVIOZ LEARNING · V5.2</span><h1><?php echo wbH('Watch & Build'); ?></h1>
      <p><?php echo wbH('Resuelve proyectos reales y demuestra lo que aprendiste.'); ?></p>
      <small><?php echo wbH('Los retos se publican para todos los usuarios registrados. No modifican las notas de Learning Lab.'); ?></small>
    </div><span class="wb-hero-mark" aria-hidden="true">⌘</span>
  </section>
  <?php if ($error): ?><div class="wb-alert wb-alert-error" role="alert"><?php echo wbE($error); ?></div><?php else: ?>
  <form class="wb-filters" method="get" action="retos.php">
    <label><?php echo wbH('Buscar'); ?><input name="buscar" maxlength="100" value="<?php echo wbE($buscar); ?>" placeholder="<?php echo wbH('Título o descripción'); ?>"></label>
    <label><?php echo wbH('Skill'); ?><select name="skill"><option value="0"><?php echo wbH('Todas las skills'); ?></option>
      <?php foreach ($skills as $s): ?><option value="<?php echo (int)$s['id_skill']; ?>" <?php echo $skillId === (int)$s['id_skill'] ? 'selected' : ''; ?>><?php echo wbE($s['nombre']); ?></option><?php endforeach; ?>
    </select></label>
    <button class="wb-btn" type="submit"><?php echo wbH('Filtrar'); ?></button><a class="wb-btn wb-btn-outline" href="retos.php"><?php echo wbH('Limpiar'); ?></a>
  </form>
  <?php if (!$retos): ?><div class="wb-empty"><?php echo wbH('No hay retos para estos filtros.'); ?></div><?php else: ?>
  <div class="wb-grid">
  <?php foreach ($retos as $r):
    $vencido = $r['fecha_limite'] && strtotime((string)$r['fecha_limite']) < time();
    $estado = (string)($r['mi_estado'] ?? '');
  ?>
  <article class="wb-card">
    <div class="wb-card-top"><span class="wb-pill">● <?php echo wbE(wbDificultad((string)$r['dificultad'])); ?></span>
      <?php if ($estado): ?><span class="wb-status wb-status-<?php echo wbE($estado); ?>"><?php echo wbE(wbEstado($estado)); ?></span><?php elseif($vencido): ?><span class="wb-status wb-status-archivado"><?php echo wbH('El plazo de entrega terminó.'); ?></span><?php else: ?><span class="wb-status"><?php echo wbH('Sin entregas'); ?></span><?php endif; ?>
    </div>
    <h2 data-i18n-ignore><?php echo wbE($r['titulo']); ?></h2><p data-i18n-ignore><?php echo wbE($r['descripcion']); ?></p>
    <div class="wb-meta"><span>📅 <?php echo wbE(wbFecha($r['fecha_limite'])); ?></span><span>🎯 <?php echo wbH('Nota mínima'); ?>: <?php echo (float)$r['nota_minima']; ?>/100</span>
    <?php if ($r['skill_nombre']): ?><span>🧩 <span data-i18n-ignore><?php echo wbE($r['skill_nombre']); ?></span></span><?php endif; ?>
    <?php if ($r['curso_titulo']): ?><span>📘 <span data-i18n-ignore><?php echo wbE($r['curso_titulo']); ?></span></span><?php endif; ?>
    </div>
    <a class="wb-btn" href="reto.php?id=<?php echo (int)$r['id_reto']; ?>"><?php echo wbH('Ver detalle'); ?> →</a>
  </article>
  <?php endforeach; ?>
  </div>
  <?php endif; ?><?php endif; ?>
</main></div><?php include __DIR__.'/../includes/public_footer.php'; ?>
