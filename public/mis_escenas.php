<?php
/** DEVIOZ V4.4.4 - Biblioteca personal de escenas repasadas. */
require_once __DIR__ . '/../config/sesion.php';
verificarSesion();
require_once __DIR__ . '/../models/EscenaAprendizaje.php';
require_once __DIR__ . '/../controllers/VideoController.php';

$h = static fn($texto): string => htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
$idiomaEscenas = deviozIdiomaActual();
$textoDe = $idiomaEscenas === 'en' ? 'of' : 'de';
$textoRepasadas = $idiomaEscenas === 'en' ? 'scenes reviewed' : ($idiomaEscenas === 'pt' ? 'cenas revisadas' : 'escenas repasadas');
$textoUltima = $idiomaEscenas === 'en' ? 'Last activity:' : ($idiomaEscenas === 'pt' ? 'Última atividade:' : 'Última actividad:');
$categorias = (new VideoController())->listarCategorias();
$items = [];
$error = false;
try {
    $escenasModelo = new EscenaAprendizaje();
    if (!$escenasModelo->instalado()) {
        $error = true;
    } else {
        $items = $escenasModelo->resumenUsuario((int)$_SESSION['id_usuario']);
    }
} catch (Throwable $e) {
    error_log('DEVIOZ V4.4.4 - Mis escenas: ' . $e->getMessage());
    $error = true;
}
?>
<?php include __DIR__ . '/../includes/public_header.php'; ?>
<?php include __DIR__ . '/../includes/public_navbar.php'; ?>
<div class="layout">
<?php include __DIR__ . '/../includes/public_sidebar.php'; ?>
<main class="public-content">
    <div class="scene-library">
        <section class="scene-library-hero">
            <span class="section-kicker">DEVIOZ LEARNING · V4.4.4</span>
            <h1>Mis escenas</h1>
            <p>Aquí encontrarás los videos en los que escribiste reflexiones o repasaste capítulos. Puedes retomar el estudio cuando quieras. Esta actividad no modifica tus evaluaciones de Learning Lab.</p>
        </section>
        <?php if ($error): ?>
            <div class="scene-library-empty" role="alert">El seguimiento por escenas aún no está disponible. Comprueba que importaste la migración V4.4.4.</div>
        <?php elseif (!$items): ?>
            <div class="scene-library-empty">Todavía no has guardado reflexiones por escenas. Abre un video con capítulos inteligentes publicados y selecciona <strong>Estudiar por escenas</strong>. <a href="index.php">Explorar videos →</a></div>
        <?php else: ?>
            <div class="scene-library-list">
                <?php foreach ($items as $fila): ?>
                <?php
                    $total = max(0, (int)$fila['total']);
                    $repasadas = max(0, min($total, (int)$fila['repasadas']));
                    $pct = $total > 0 ? (int)round(100 * $repasadas / $total) : 0;
                ?>
                <article class="scene-library-item">
                    <span class="section-kicker"><?php echo $repasadas === $total && $total > 0 ? '✓ Repaso completo' : 'Repaso en progreso'; ?></span>
                    <h2 data-i18n-ignore><?php echo $h($fila['titulo']); ?></h2>
                    <div class="scene-library-meta"><?php echo $repasadas; ?> <?php echo $h($textoDe); ?> <?php echo $total; ?> <?php echo $h($textoRepasadas); ?> · <?php echo $pct; ?>%</div>
                    <div class="scene-library-meter" role="progressbar" aria-label="Escenas repasadas" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo $pct; ?>"><i style="width:<?php echo $pct; ?>%"></i></div>
                    <?php if (!empty($fila['ultima_actividad'])): ?>
                        <small class="scene-library-meta"><?php echo $h($textoUltima); ?> <?php echo $h(date('d/m/Y H:i', strtotime((string)$fila['ultima_actividad']))); ?></small>
                    <?php endif; ?>
                    <div class="scene-library-actions">
                        <a class="scene-study-primary" href="detalle.php?id=<?php echo (int)$fila['id_video']; ?>&amp;estudiar=1#capitulosInteligentes">Continuar estudiando →</a>
                        <a class="scene-study-secondary" href="detalle.php?id=<?php echo (int)$fila['id_video']; ?>">Ver video</a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>
</div>
<?php include __DIR__ . '/../includes/public_footer.php'; ?>
