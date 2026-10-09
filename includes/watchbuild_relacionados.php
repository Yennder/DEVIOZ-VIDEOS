<?php
/** V5.2 - Reutilizable en detalle de video y curso. Espera $wbVideoId/$wbCursoId. */
if (usuarioAutenticado()) {
    $wbRelacionados = [];
    try {
        require_once __DIR__.'/../models/WatchBuild.php';
        $wbRelatedModel = new WatchBuild();
        if ($wbRelatedModel->instalado()) {
            $wbRelacionados = $wbRelatedModel->relacionados((int)($wbVideoId ?? 0),(int)($wbCursoId ?? 0));
        }
    } catch (Throwable $e) { error_log('WatchBuild relacionados: '.$e->getMessage()); }
    if ($wbRelacionados) {
        require_once __DIR__.'/watchbuild_i18n.php';
?>
<section class="wb-panel wb-related">
  <div class="wb-related-heading"><div><span class="wb-eyebrow">WATCH &amp; BUILD · V5.2</span><h2><?php echo wbH('Aprende haciendo'); ?></h2>
    <p><?php echo wbH('Resuelve proyectos reales y demuestra lo que aprendiste.'); ?></p></div><a class="wb-btn wb-btn-outline" href="retos.php"><?php echo wbH('Ver retos'); ?> →</a></div>
  <div class="wb-related-list">
    <?php foreach ($wbRelacionados as $r): ?><a href="reto.php?id=<?php echo (int)$r['id_reto']; ?>">
      <strong data-i18n-ignore><?php echo wbE($r['titulo']); ?></strong>
      <span><?php echo wbE(wbDificultad($r['dificultad'])); ?> · <?php echo wbH('Fecha límite'); ?>: <?php echo wbE(wbFecha($r['fecha_limite'])); ?> →</span>
    </a><?php endforeach; ?>
  </div>
</section>
<?php
    }
}
