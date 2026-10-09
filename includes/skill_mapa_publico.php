<?php
/** Expects $mapaPersonal from public/skills.php. */
$metas = $mapaPersonal['filas'] ?? [];
$mapaResumen = $mapaPersonal['resumen'] ?? [];
$estadoMap = ['sin_evidencia' => 'Sin evidencia académica', 'brecha' => 'Brecha de evidencia', 'alcanzado' => 'Meta de evidencia alcanzada'];
?>
<section class="content-section v51-public-map" id="mapa-skills">
  <div class="section-heading-row"><div>
    <span class="section-kicker">V5.1 · TECHFLIX LEARNING LAB</span>
    <h2><?php echo mapaH('Mapa personal de competencias'); ?></h2>
    <p><?php echo mapaH('Visualiza tus objetivos y la evidencia académica pendiente para cada competencia.'); ?></p>
  </div></div>
  <div class="v51-stats v51-stats--public">
    <article><span><?php echo mapaH('Objetivos identificados'); ?></span><strong><?php echo (int)($mapaResumen['total'] ?? 0); ?></strong></article>
    <article><span><?php echo mapaH('Objetivos alcanzados'); ?></span><strong><?php echo (int)($mapaResumen['alcanzadas'] ?? 0); ?></strong></article>
    <article><span><?php echo mapaH('Con brecha'); ?></span><strong><?php echo (int)($mapaResumen['con_brecha'] ?? 0); ?></strong></article>
    <article><span><?php echo mapaH('Sin evidencia'); ?></span><strong><?php echo (int)($mapaResumen['sin_evidencia'] ?? 0); ?></strong></article>
    <article><span><?php echo mapaH('Alta prioridad'); ?></span><strong><?php echo (int)($mapaResumen['prioridad_alta'] ?? 0); ?></strong></article>
  </div>
  <p class="v51-disclaimer"><?php echo mapaH('Los porcentajes reflejan evidencia de aprendizaje, no certifican la competencia profesional.'); ?></p>
  <?php if (!$metas): ?>
  <div class="v51-empty"><?php echo mapaH('Todavía no tienes metas de Skills: se mostrarán al asignarte cursos con competencias o cuando administración configure un objetivo.'); ?></div>
  <?php else: ?>
  <div class="v51-map-grid">
  <?php foreach ($metas as $meta): ?>
    <article class="v51-map-card">
      <header><span class="v51-editor-icon"><?php echo learningH($meta['icono']); ?></span><div><small><?php echo learningH($meta['categoria']); ?></small><h3><?php echo learningH($meta['nombre']); ?></h3></div>
      <span class="v51-status v51-status--<?php echo learningH($meta['estado']); ?>"><?php echo mapaH($estadoMap[$meta['estado']]); ?></span></header>
      <div class="v51-score-row"><span><?php echo mapaH('Evidencia actual'); ?> <strong><?php echo (float)$meta['actual']; ?>%</strong></span>
      <span><?php echo mapaH('Meta'); ?> <strong><?php echo (float)$meta['objetivo']; ?>%</strong></span></div>
      <div class="v51-bar" aria-label="<?php echo mapaH('Evidencia actual'); ?> <?php echo (float)$meta['actual']; ?>%, <?php echo mapaH('Meta'); ?> <?php echo (float)$meta['objetivo']; ?>%">
        <i class="v51-goal" style="width:<?php echo (float)$meta['objetivo']; ?>%"></i>
        <i class="v51-now" style="width:<?php echo (float)$meta['actual']; ?>%"></i>
      </div>
      <p class="v51-gap-line"><span><?php echo mapaH('Brecha'); ?>: <strong><?php echo (float)$meta['brecha']; ?> pp</strong></span>
      <span><?php echo mapaH('Prioridad'); ?>: <strong><?php echo mapaH(ucfirst($meta['prioridad'])); ?></strong></span></p>
      <small class="v51-source"><?php echo mapaH($meta['fuente_objetivo'] === 'administrador' ? 'Configurado por administración' : 'Derivado de curso'); ?> · <?php echo mapaH($meta['nivel_objetivo_texto']); ?></small>
      <?php if ($meta['observacion_objetivo'] !== ''): ?><p class="v51-goal-note"><?php echo nl2br(learningH($meta['observacion_objetivo'])); ?></p><?php endif; ?>
      <details class="v51-course-details"><summary><?php echo mapaH('Cursos relacionados'); ?> (<?php echo count($meta['cursos_vinculados']); ?>)</summary>
        <small><?php echo mapaH('Cursos vinculados publicados (su acceso requiere asignación).'); ?></small>
        <?php if (!$meta['cursos_vinculados']): ?><p><?php echo mapaH('No hay cursos publicados asociados a esta skill.'); ?></p><?php endif; ?>
        <?php foreach ($meta['cursos_vinculados'] as $curso): ?><div><strong><?php echo learningH($curso['titulo']); ?></strong><small><?php echo mapaH('Relevancia en curso'); ?>: <?php echo (float)$curso['peso']; ?>%</small></div><?php endforeach; ?>
        <a href="aprendizaje.php"><?php echo mapaH('Ver mi aprendizaje'); ?> →</a>
      </details>
    </article>
  <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
