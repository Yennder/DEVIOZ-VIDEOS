<?php
// V4.5.3 - Se incluye solo desde public/detalle.php, con $valoracionActual y $id definidos.
$promedioRating = (float)($valoracionActual['promedio'] ?? 0);
$totalRating = (int)($valoracionActual['total'] ?? 0);
$miRating = (int)($valoracionActual['mi_valoracion'] ?? 0);
$puedeValorar = usuarioAutenticado();
?>
<section class="video-rating-panel" id="videoRatingPanel" aria-label="Valoraciones del video"
         <?php if ($puedeValorar): ?>data-video-id="<?php echo (int)$id; ?>"
         data-endpoint="../api/valoraciones.php"
         data-csrf-token="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>"
         data-selected="<?php echo $miRating; ?>"<?php endif; ?>>
    <div class="video-rating-overview">
        <span class="video-rating-symbol" aria-hidden="true">★</span>
        <div class="video-rating-score">
            <strong id="videoRatingAverage"><?php echo number_format($promedioRating, 1, ',', '.'); ?></strong>
            <small>/ 5</small>
        </div>
        <div class="video-rating-caption">
            <strong>Valoración de la comunidad</strong>
            <small><span id="videoRatingTotal"><?php echo number_format($totalRating, 0, ',', '.'); ?></span> valoraciones</small>
        </div>
    </div>
    <div class="video-rating-interaction">
        <?php if ($puedeValorar): ?>
            <span>¿Qué te pareció este video?</span>
            <div class="video-rating-stars" role="group" aria-label="Califica este video de una a cinco estrellas">
                <?php for ($puntosRating = 1; $puntosRating <= 5; $puntosRating++): ?>
                    <button type="button" data-rating-value="<?php echo $puntosRating; ?>"
                            aria-label="<?php echo $puntosRating; ?> <?php echo $puntosRating === 1 ? 'estrella' : 'estrellas'; ?>"
                            aria-pressed="<?php echo $miRating === $puntosRating ? 'true' : 'false'; ?>"
                            class="<?php echo $puntosRating <= $miRating ? 'is-selected' : ''; ?>"
                            title="Calificar con <?php echo $puntosRating; ?> de 5">★</button>
                <?php endfor; ?>
            </div>
            <p class="video-rating-user-status" id="videoRatingUserStatus">
                <?php echo $miRating > 0 ? 'Tu calificación: ' . $miRating . ' de 5. Puedes cambiarla cuando quieras.' : 'Selecciona de 1 a 5 estrellas para valorar.'; ?>
            </p>
        <?php else: ?>
            <a class="video-rating-guest-link" href="../views/login.php?redirect=<?php echo urlencode('/DEVIOZ-VIDEOS/public/detalle.php?id=' . (int)$id); ?>">Inicia sesión para calificar este video →</a>
        <?php endif; ?>
    </div>
    <?php if ($puedeValorar): ?><p class="video-rating-feedback" id="videoRatingFeedback" role="status" aria-live="polite" hidden></p><?php endif; ?>
</section>
