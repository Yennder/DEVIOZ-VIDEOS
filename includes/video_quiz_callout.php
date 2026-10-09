<?php
// V4.5.4.1 - Invitacion normal y popup al finalizar (no aparece en Learning Lab).
if (!empty($cuestionarioPublico) && empty($esVistaLearning)):
    $intentosVQ = (int)($cuestionarioResumen['intentos'] ?? 0);
    $mejorVQ = (int)($cuestionarioResumen['mejor_puntaje'] ?? 0);
    $destinoVQ = usuarioAutenticado()
        ? 'cuestionario_video.php?id=' . (int)$id
        : '../views/login.php?redirect=' . urlencode('/DEVIOZ-VIDEOS/public/cuestionario_video.php?id=' . (int)$id);
    $nombreVQ = htmlspecialchars((string)($video['titulo'] ?? 'este video'), ENT_QUOTES, 'UTF-8');
?>
<section class="vq-callout" id="videoQuizCallout" aria-label="Cuestionario del video">
    <div class="vq-callout-icon" aria-hidden="true">?</div>
    <div class="vq-callout-copy">
        <span class="vq-eyebrow">Reto de conocimiento</span>
        <h2>5 preguntas sobre este video</h2>
        <p id="vqCalloutMessage"><?php echo $intentosVQ ? '✓ Ya respondiste este video (' . $intentosVQ . ' intentos, mejor nota ' . $mejorVQ . '/100). Puedes repetir la evaluación.' : 'Cuando termines el video, comprueba lo que aprendiste.'; ?></p>
        <small>Cada respuesta correcta vale 20 puntos. No afecta Learning Lab.</small>
        <?php if (usuarioAutenticado()): ?><p class="vq-callout-history"><a href="mis_cuestionarios.php">Ver todos mis cuestionarios y notas →</a></p><?php endif; ?>
    </div>
    <div class="vq-callout-action"><a class="vq-primary" href="<?php echo htmlspecialchars($destinoVQ, ENT_QUOTES, 'UTF-8'); ?>"><?php echo usuarioAutenticado() ? ($intentosVQ ? 'Repetir evaluación →' : 'Responder preguntas →') : 'Iniciar sesión para responder'; ?></a></div>
</section>
<dialog class="vq-quiz-modal" id="videoQuizModal" aria-labelledby="vqQuizModalTitle" aria-describedby="vqQuizModalDescription">
    <div class="vq-quiz-modal-inner">
        <span class="vq-quiz-modal-icon" aria-hidden="true">📝</span>
        <span class="vq-eyebrow">¡Terminaste el video!</span>
        <h2 id="vqQuizModalTitle"><?php echo $intentosVQ ? '¿Quieres repetir la evaluación?' : '¿Listo para comenzar la evaluación?'; ?></h2>
        <p id="vqQuizModalDescription">Son <strong>5 preguntas</strong> sobre <strong><?php echo $nombreVQ; ?></strong>, con <strong>100 puntos</strong> posibles. <?php echo $intentosVQ ? 'Tu mejor nota hasta ahora es ' . $mejorVQ . '/100.' : 'Podrás comprobar cuánto aprendiste.'; ?></p>
        <div class="vq-quiz-modal-actions">
            <a id="vqQuizModalStart" class="vq-primary" href="<?php echo htmlspecialchars($destinoVQ, ENT_QUOTES, 'UTF-8'); ?>"><?php echo usuarioAutenticado() ? ($intentosVQ ? 'Empezar otro intento' : 'Empezar evaluación') : 'Iniciar sesión y evaluar'; ?></a>
            <button id="vqQuizModalLater" class="vq-secondary" type="button">Ahora no</button>
        </div>
        <small>Puedes responder después desde «Mis cuestionarios».</small>
    </div>
</dialog>
<?php endif; ?>
