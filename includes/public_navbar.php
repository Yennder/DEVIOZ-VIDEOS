<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../controllers/ConfiguracionController.php';
require_once __DIR__ . '/../controllers/NotificacionController.php';

$configController = new ConfiguracionController();
$logoSitio = $configController->obtener('logo_sitio');
$logoRutaServidor = $logoSitio ? (__DIR__ . '/../uploads/config/' . basename($logoSitio)) : '';
$logoDisponible = $logoSitio && is_file($logoRutaServidor);

$logueado = usuarioAutenticado();
$nombreUsuario = $_SESSION['nombre'] ?? '';
$rolUsuario = $_SESSION['rol'] ?? '';
$paginaActual = basename($_SERVER['PHP_SELF'] ?? 'index.php');

$notificacionesNavbar = [];
$totalNotificacionesNoLeidas = 0;
if ($logueado) {
    try {
        $notificacionController = new NotificacionController();
        $notificacionController->sincronizarUsuario((int)$_SESSION['id_usuario']);
        $notificacionesNavbar = $notificacionController->noLeidas((int)$_SESSION['id_usuario'], 5);
        $totalNotificacionesNoLeidas = $notificacionController->contarNoLeidas((int)$_SESSION['id_usuario']);
    } catch (Throwable $e) {
        $notificacionesNavbar = [];
        $totalNotificacionesNoLeidas = 0;
    }
}
?>

<nav class="public-navbar" aria-label="Navegación principal">
    <div class="public-navbar-left">
        <button type="button" class="public-menu-toggle" id="publicMenuToggle" aria-label="Abrir navegación" aria-expanded="false">☰</button>

        <a href="index.php" class="public-logo" aria-label="DEVIOZ VIDEOS - Inicio">
            <?php if($logoDisponible): ?>
                <img src="/DEVIOZ-VIDEOS/uploads/config/<?php echo htmlspecialchars(basename($logoSitio)); ?>" alt="DEVIOZ VIDEOS">
            <?php else: ?>
                <span class="brand-symbol">DV</span>
                <span class="brand-copy"><strong>DEVIOZ</strong><small>VIDEOS</small></span>
            <?php endif; ?>
        </a>

        <div class="public-nav-links" id="publicNavLinks">
            <a href="index.php" class="<?php echo $paginaActual === 'index.php' ? 'is-active' : ''; ?>">Inicio</a>
            <a href="series.php" class="<?php echo in_array($paginaActual, ['series.php','detalle_serie.php'], true) ? 'is-active' : ''; ?>">Series</a>
            <?php if($logueado): ?>
                <a href="aprendizaje.php" class="<?php echo in_array($paginaActual, ['aprendizaje.php','curso.php','evaluacion.php','logros.php','progreso.php','certificados.php'], true) ? 'is-active' : ''; ?>">Aprendizaje</a>
                <a href="favoritos.php" class="<?php echo $paginaActual === 'favoritos.php' ? 'is-active' : ''; ?>">Favoritos</a>
                <a href="historial.php" class="<?php echo $paginaActual === 'historial.php' ? 'is-active' : ''; ?>">Historial</a>
                <a href="playlists.php" class="<?php echo $paginaActual === 'playlists.php' ? 'is-active' : ''; ?>">Playlists</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="search-box">
        <form action="index.php" method="GET" role="search">
            <span class="search-icon" aria-hidden="true">⌕</span>
            <input type="search" name="buscar" value="<?php echo htmlspecialchars($_GET['buscar'] ?? ''); ?>" placeholder="Buscar videos, temas o categorías..." aria-label="Buscar videos">
            <button type="submit" aria-label="Buscar">Buscar</button>
        </form>
    </div>

    <div class="public-navbar-actions">
        <div class="theme-control">
            <span class="theme-icon" id="themeIcon" aria-hidden="true">☀️</span>
            <label class="theme-switch" title="Cambiar tema">
                <input type="checkbox" id="themeSwitch" aria-label="Cambiar modo claro u oscuro">
                <span class="theme-slider"></span>
            </label>
        </div>

        <?php if($logueado): ?>
        <div class="notification-center" id="notificationCenter">
            <button type="button" class="notification-bell" id="notificationBell" aria-label="Notificaciones" aria-expanded="false">
                <span aria-hidden="true">🔔</span>
                <?php if($totalNotificacionesNoLeidas > 0): ?>
                    <b><?php echo $totalNotificacionesNoLeidas > 99 ? '99+' : (int)$totalNotificacionesNoLeidas; ?></b>
                <?php endif; ?>
            </button>
            <div class="notification-dropdown" id="notificationDropdown" hidden>
                <div class="notification-dropdown-head">
                    <div><strong>Notificaciones</strong><small><?php echo (int)$totalNotificacionesNoLeidas; ?> sin leer</small></div>
                    <a href="notificaciones.php">Ver todas</a>
                </div>
                <div class="notification-dropdown-list">
                    <?php if(empty($notificacionesNavbar)): ?>
                        <div class="notification-dropdown-empty">
                            <span>✓</span>
                            <p>Estás al día.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($notificacionesNavbar as $notif): ?>
                        <form method="POST" action="/DEVIOZ-VIDEOS/public/notificaciones.php" class="notification-dropdown-item">
                            <?php echo csrfInput(); ?>
                            <input type="hidden" name="accion" value="leer">
                            <input type="hidden" name="id_notificacion" value="<?php echo (int)$notif['id_notificacion']; ?>">
                            <input type="hidden" name="destino" value="<?php echo htmlspecialchars($notif['url'] ?: '/DEVIOZ-VIDEOS/public/notificaciones.php'); ?>">
                            <button type="submit">
                                <span class="notification-dropdown-icon"><?php echo htmlspecialchars($notif['icono'] ?: '🔔'); ?></span>
                                <span><strong><?php echo htmlspecialchars($notif['titulo']); ?></strong><small><?php echo htmlspecialchars($notif['mensaje']); ?></small><em><?php echo htmlspecialchars(date('d/m H:i', strtotime($notif['fecha_creacion']))); ?></em></span>
                            </button>
                        </form>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php if($totalNotificacionesNoLeidas > 0): ?>
                <form method="POST" action="/DEVIOZ-VIDEOS/public/notificaciones.php" class="notification-dropdown-footer">
                    <?php echo csrfInput(); ?>
                    <input type="hidden" name="accion" value="leer_todas">
                    <button type="submit">Marcar todas como leídas</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="public-user-area">
            <?php if(!$logueado): ?>
                <a href="../views/login.php" class="public-login-link">Iniciar sesión</a>
            <?php else: ?>
                <a href="../views/perfil.php" class="public-user-chip" title="Mi perfil">
                    <span class="user-avatar"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($nombreUsuario, 0, 1))); ?></span>
                    <span class="user-label"><?php echo htmlspecialchars($nombreUsuario); ?></span>
                </a>
                <?php if($rolUsuario === 'admin'): ?>
                    <a href="../admin/dashboard.php" class="public-admin-link">Panel</a>
                <?php endif; ?>
                <a href="../logout.php" class="public-logout-link" title="Cerrar sesión">Salir</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
