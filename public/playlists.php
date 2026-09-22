<?php
require_once '../config/sesion.php';
verificarSesion();
require_once '../controllers/VideoController.php';
require_once '../controllers/InteraccionController.php';

$videoController = new VideoController();
$interaccionController = new InteraccionController();
$idUsuario = (int)$_SESSION['id_usuario'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verificarCsrfPost();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $interaccionController->crearPlaylist(
            $idUsuario,
            trim((string)($_POST['nombre'] ?? '')),
            trim((string)($_POST['descripcion'] ?? ''))
        );
    } elseif ($accion === 'eliminar') {
        $interaccionController->eliminarPlaylist($idUsuario, (int)($_POST['id_playlist'] ?? 0));
    } elseif ($accion === 'quitar_video') {
        $interaccionController->quitarVideoPlaylist(
            $idUsuario,
            (int)($_POST['id_playlist'] ?? 0),
            (int)($_POST['id_video'] ?? 0)
        );
    }

    $redirect = 'playlists.php';
    if (!empty($_POST['id_playlist']) && $accion !== 'eliminar') {
        $redirect .= '?playlist=' . (int)$_POST['id_playlist'];
    }
    header('Location: ' . $redirect);
    exit;
}

$categorias = $videoController->listarCategorias();
$playlists = $interaccionController->playlistsUsuario($idUsuario);
$idSeleccionada = isset($_GET['playlist']) ? (int)$_GET['playlist'] : 0;
if (!$idSeleccionada && !empty($playlists)) {
    $idSeleccionada = (int)$playlists[0]['id_playlist'];
}
$videosPlaylist = $idSeleccionada ? $interaccionController->videosPlaylist($idUsuario, $idSeleccionada) : [];
$playlistSeleccionada = null;
foreach ($playlists as $p) {
    if ((int)$p['id_playlist'] === $idSeleccionada) { $playlistSeleccionada = $p; break; }
}
?>
<?php include '../includes/public_header.php'; ?>
<?php include '../includes/public_navbar.php'; ?>
<div class="layout">
<?php include '../includes/public_sidebar.php'; ?>
<main class="public-content">
    <section class="page-hero-compact playlist-page-header">
        <div><span class="section-kicker">Organiza tu contenido</span><h1>Mis playlists</h1><p>Crea listas para cursos, pendientes, estudio o cualquier colección personal.</p></div>
        <button class="btn-primary-modern" type="button" data-toggle-create-playlist>+ Nueva playlist</button>
    </section>

    <section class="playlist-create-panel" id="playlistCreatePanel" hidden>
        <form method="POST" class="modern-inline-form">
            <?php echo csrfInput(); ?>
            <input type="hidden" name="accion" value="crear">
            <div><label for="playlistNombre">Nombre</label><input id="playlistNombre" type="text" name="nombre" maxlength="100" placeholder="Ej. Para estudiar" required></div>
            <div><label for="playlistDescripcion">Descripción</label><input id="playlistDescripcion" type="text" name="descripcion" maxlength="255" placeholder="Opcional"></div>
            <button type="submit" class="btn-primary-modern">Crear</button>
        </form>
    </section>

    <?php if(empty($playlists)): ?>
        <section class="content-section content-section-first"><div class="empty-state"><span class="empty-icon">☷</span><h3>Crea tu primera playlist</h3><p>Después podrás agregar videos desde la página de reproducción.</p><button type="button" class="btn-primary-modern" data-toggle-create-playlist>Crear playlist</button></div></section>
    <?php else: ?>
        <section class="playlist-workspace">
            <aside class="playlist-list">
                <div class="playlist-list-title"><span>Tus listas</span><small><?php echo count($playlists); ?></small></div>
                <?php foreach($playlists as $playlist): ?>
                    <a href="playlists.php?playlist=<?php echo (int)$playlist['id_playlist']; ?>" class="playlist-item <?php echo (int)$playlist['id_playlist'] === $idSeleccionada ? 'is-active' : ''; ?>">
                        <span class="playlist-item-icon">☷</span>
                        <span><strong><?php echo htmlspecialchars($playlist['nombre']); ?></strong><small><?php echo (int)$playlist['total_videos']; ?> videos</small></span>
                    </a>
                <?php endforeach; ?>
            </aside>

            <div class="playlist-content">
                <?php if($playlistSeleccionada): ?>
                    <div class="section-heading-row playlist-selected-heading">
                        <div><span class="section-kicker">Playlist</span><h2><?php echo htmlspecialchars($playlistSeleccionada['nombre']); ?></h2><p><?php echo htmlspecialchars($playlistSeleccionada['descripcion'] ?: 'Colección personal de DEVIOZ VIDEOS.'); ?></p></div>
                        <form method="POST" onsubmit="return confirm('¿Eliminar esta playlist?');">
                            <?php echo csrfInput(); ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id_playlist" value="<?php echo $idSeleccionada; ?>"><button class="btn-danger-soft" type="submit">Eliminar playlist</button>
                        </form>
                    </div>

                    <?php if(empty($videosPlaylist)): ?>
                        <div class="empty-state empty-state-small"><span class="empty-icon">＋</span><h3>Esta playlist está vacía</h3><p>Abre un video y selecciona esta playlist para agregarlo.</p><a href="index.php" class="btn-secondary-modern">Explorar videos</a></div>
                    <?php else: ?>
                        <div class="playlist-video-list">
                        <?php foreach($videosPlaylist as $item): ?>
                            <div class="playlist-video-row">
                                <a href="detalle.php?id=<?php echo (int)$item['id_video']; ?>" class="playlist-video-main">
                                    <span class="playlist-video-play">▶</span><span><strong><?php echo htmlspecialchars($item['titulo']); ?></strong><small><?php echo htmlspecialchars($item['categoria']); ?> · <?php echo number_format((int)$item['vistas']); ?> vistas</small></span>
                                </a>
                                <form method="POST">
                                    <?php echo csrfInput(); ?><input type="hidden" name="accion" value="quitar_video"><input type="hidden" name="id_playlist" value="<?php echo $idSeleccionada; ?>"><input type="hidden" name="id_video" value="<?php echo (int)$item['id_video']; ?>"><button class="icon-action-button" type="submit" title="Quitar de la playlist">×</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</main>
</div>
<?php include '../includes/public_footer.php'; ?>
