<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../controllers/ConfiguracionController.php';

$configController = new ConfiguracionController();
$logoSitio = $configController->obtener('logo_sitio');
$logoRutaServidor = $logoSitio ? (__DIR__ . '/../uploads/config/' . basename($logoSitio)) : '';
$logoDisponible = $logoSitio && is_file($logoRutaServidor);

$logueado = usuarioAutenticado();
$nombreUsuario = $_SESSION['nombre'] ?? '';
$rolUsuario = $_SESSION['rol'] ?? '';
$paginaActual = basename($_SERVER['PHP_SELF'] ?? 'index.php');
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
                <a href="aprendizaje.php" class="<?php echo in_array($paginaActual, ['aprendizaje.php','curso.php','evaluacion.php','logros.php','progreso.php'], true) ? 'is-active' : ''; ?>">Aprendizaje</a>
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
