<?php
require_once '../config/sesion.php';
require_once '../controllers/SerieController.php';
require_once '../controllers/TemporadaController.php';
require_once '../controllers/VideoController.php';

$serieController = new SerieController();
$temporadaController = new TemporadaController();
$videoController = new VideoController();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if(!$id){
    header('Location:series.php');
    exit;
}

$serie = $serieController->detallePublico($id);
if(!$serie){
    header('Location:series.php');
    exit;
}

$temporadas = $temporadaController->listarPublicas($id);
$categorias = $videoController->listarCategorias();

$temporadasConCapitulos = [];
$totalCapitulosSerie = 0;
$primerCapituloSerie = null;
foreach($temporadas as $tempSerie) {
    $capsSerie = $videoController->capitulosTemporada((int)$tempSerie['id_temporada']);
    $temporadasConCapitulos[(int)$tempSerie['id_temporada']] = $capsSerie;
    $totalCapitulosSerie += count($capsSerie);
    if($primerCapituloSerie === null && !empty($capsSerie)) {
        $primerCapituloSerie = $capsSerie[0];
    }
}

$portada = !empty($serie['imagen_portada']) ? basename((string)$serie['imagen_portada']) : '';
$portadaPath = $portada !== '' ? (__DIR__ . '/../uploads/series/' . $portada) : '';
?>
<?php include '../includes/public_header.php'; ?>
<?php include '../includes/public_navbar.php'; ?>

<div class="layout">
<?php include '../includes/public_sidebar.php'; ?>
<main class="public-content">
    <a href="series.php" class="btn-volver-series">← Volver a Series</a>

    <section class="serie-detalle">
        <div class="serie-header-netflix">
            <div class="serie-portada-box">
                <?php if($portadaPath !== '' && is_file($portadaPath)): ?>
                    <img src="../uploads/series/<?php echo htmlspecialchars($portada); ?>" class="serie-portada" alt="Portada de <?php echo htmlspecialchars($serie['titulo']); ?>">
                <?php else: ?>
                    <div class="serie-portada serie-portada-placeholder"><strong>DV</strong><span>SERIE</span></div>
                <?php endif; ?>
            </div>

            <div class="serie-data">
                <span class="section-kicker">Serie</span>
                <h1><?php echo htmlspecialchars($serie['titulo']); ?></h1>
                <p><?php echo nl2br(htmlspecialchars((string)($serie['descripcion'] ?? ''))); ?></p>
                <div class="serie-catalog-actions">
                    <span class="serie-catalog-meta"><?php echo count($temporadas); ?> temporada<?php echo count($temporadas) === 1 ? '' : 's'; ?> · <?php echo $totalCapitulosSerie; ?> capítulo<?php echo $totalCapitulosSerie === 1 ? '' : 's'; ?></span>
                    <?php if($primerCapituloSerie): ?>
                        <a class="btn-primary-modern" href="detalle.php?id=<?php echo (int)$primerCapituloSerie['id_video']; ?>">▶ Reproducir desde el inicio</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <h2 class="titulo-temporadas">Temporadas</h2>

        <?php if(empty($temporadas)): ?>
            <div class="empty-state compact-empty">
                <span class="empty-icon">▤</span>
                <h3>Esta serie todavía no tiene temporadas publicadas</h3>
                <p>Vuelve más adelante para ver nuevo contenido.</p>
            </div>
        <?php else: ?>
            <?php foreach($temporadas as $temp): ?>
                <?php $capitulos = $temporadasConCapitulos[(int)$temp['id_temporada']] ?? []; ?>
                <section class="temporada-netflix">
                    <div class="season-heading-row">
                        <div>
                            <span class="section-kicker">Temporada <?php echo (int)$temp['numero_temporada']; ?></span>
                            <h3><?php echo htmlspecialchars($temp['titulo'] ?: ('Temporada ' . (int)$temp['numero_temporada'])); ?></h3>
                        </div>
                        <span class="season-count"><?php echo count($capitulos); ?> capítulo<?php echo count($capitulos) === 1 ? '' : 's'; ?></span>
                    </div>

                    <?php if(empty($capitulos)): ?>
                        <p class="season-empty">Aún no hay capítulos publicados en esta temporada.</p>
                    <?php else: ?>
                        <div class="capitulos-grid">
                            <?php foreach($capitulos as $cap): ?>
                                <a href="detalle.php?id=<?php echo (int)$cap['id_video']; ?>" class="capitulo-netflix">
                                    <span class="numero-capitulo"><?php echo str_pad((string)(int)$cap['numero_capitulo'], 2, '0', STR_PAD_LEFT); ?></span>
                                    <div>
                                        <strong>Capítulo <?php echo (int)$cap['numero_capitulo']; ?></strong>
                                        <p><?php echo htmlspecialchars($cap['titulo']); ?></p>
                                    </div>
                                    <span class="episode-play">▶</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>
</div>

<?php include '../includes/public_footer.php'; ?>
