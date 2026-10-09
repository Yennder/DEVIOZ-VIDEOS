<?php
$cardVideo = $cardVideo ?? [];
$idCard = (int)($cardVideo['id_video'] ?? 0);
$tituloCard = (string)($cardVideo['titulo'] ?? 'Video');
$miniaturaCard = basename((string)($cardVideo['miniatura'] ?? ''));
$miniaturaPath = $miniaturaCard !== '' ? (__DIR__ . '/../uploads/thumbnails/' . $miniaturaCard) : '';
$tieneMiniatura = $miniaturaPath !== '' && is_file($miniaturaPath);
$porcentajeCard = isset($cardVideo['porcentaje']) ? max(0, min(100, (float)$cardVideo['porcentaje'])) : null;
$promedioCard = (float)($cardVideo['valoracion_promedio'] ?? 0);
$totalCard = (int)($cardVideo['valoracion_total'] ?? 0);
?>
<article class="card-video card-video-clickable" data-card-url="detalle.php?id=<?php echo $idCard; ?>" tabindex="0">
    <a class="thumbnail" href="detalle.php?id=<?php echo $idCard; ?>" aria-label="Ver <?php echo htmlspecialchars($tituloCard); ?>">
        <?php if($tieneMiniatura): ?>
            <img src="../uploads/thumbnails/<?php echo htmlspecialchars($miniaturaCard); ?>" alt="<?php echo htmlspecialchars($tituloCard); ?>" loading="lazy">
        <?php else: ?>
            <span class="thumbnail-placeholder"><span>DV</span><small>VIDEO</small></span>
        <?php endif; ?>
        <span class="card-play" aria-hidden="true">▶</span>
        <?php if(($cardVideo['tipo_contenido'] ?? '') === 'serie'): ?>
            <span class="content-badge">Serie</span>
        <?php endif; ?>
        <?php if($porcentajeCard !== null && $porcentajeCard > 0): ?>
            <span class="card-progress"><span style="width:<?php echo $porcentajeCard; ?>%"></span></span>
        <?php endif; ?>
    </a>
    <div class="video-info">
        <div class="video-card-topline">
            <span class="categoria"><?php echo htmlspecialchars($cardVideo['categoria'] ?? 'General'); ?></span>
            <span class="views"><?php echo number_format((int)($cardVideo['vistas'] ?? 0)); ?> vistas</span>
        </div>
        <h3><a href="detalle.php?id=<?php echo $idCard; ?>"><?php echo htmlspecialchars($tituloCard); ?></a></h3>
        <div class="video-card-meta">
            <span>♡ <?php echo number_format((int)($cardVideo['likes'] ?? 0)); ?></span>
            <?php if($totalCard > 0): ?>
                <span class="card-rating" title="<?php echo $totalCard; ?> valoraciones" aria-label="Promedio de <?php echo number_format($promedioCard, 1, ',', '.'); ?> estrellas de 5, <?php echo $totalCard; ?> valoraciones">★ <?php echo number_format($promedioCard, 1, ',', '.'); ?> (<?php echo $totalCard; ?>)</span>
            <?php else: ?>
                <span class="card-rating is-empty" title="Aún no hay valoraciones">☆ Sin votos</span>
            <?php endif; ?>
            <?php if(!empty($cardVideo['fecha_publicacion'])): ?>
                <span><?php echo htmlspecialchars(date('d/m/Y', strtotime($cardVideo['fecha_publicacion']))); ?></span>
            <?php endif; ?>
            <?php if($porcentajeCard !== null && $porcentajeCard > 0): ?>
                <span><?php echo (int)$porcentajeCard; ?>% visto</span>
            <?php endif; ?>
        </div>
    </div>
</article>
