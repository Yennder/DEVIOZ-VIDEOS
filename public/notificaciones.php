<?php
require_once '../config/sesion.php';
verificarSesion();
require_once '../controllers/NotificacionController.php';
require_once '../controllers/VideoController.php';

$notificacionesController = new NotificacionController();
$videoController = new VideoController();
$idUsuario = (int)$_SESSION['id_usuario'];
$categorias = $videoController->listarCategorias();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrfPost();
    $accion = (string)($_POST['accion'] ?? '');
    if ($accion === 'leer') {
        $id = (int)($_POST['id_notificacion'] ?? 0);
        $notificacionesController->marcarLeida($id, $idUsuario);
        $destino = (string)($_POST['destino'] ?? '');
        if (rutaDeviozInternaValida($destino)) {
            header('Location: ' . $destino);
            exit;
        }
    } elseif ($accion === 'leer_todas') {
        $notificacionesController->marcarTodas($idUsuario);
    } elseif ($accion === 'eliminar') {
        $notificacionesController->eliminar((int)($_POST['id_notificacion'] ?? 0), $idUsuario);
    }
    header('Location: notificaciones.php');
    exit;
}

$notificacionesController->sincronizarUsuario($idUsuario);
$notificaciones = $notificacionesController->listar($idUsuario, 80);
$noLeidas = $notificacionesController->contarNoLeidas($idUsuario);

function notifFechaHumana(string $fecha): string
{
    $ts = strtotime($fecha);
    if (!$ts) return $fecha;
    $hoy = date('Y-m-d');
    if (date('Y-m-d', $ts) === $hoy) return 'Hoy ' . date('H:i', $ts);
    if (date('Y-m-d', $ts) === date('Y-m-d', strtotime('-1 day'))) return 'Ayer ' . date('H:i', $ts);
    return date('d/m/Y H:i', $ts);
}
?>
<?php include '../includes/public_header.php'; ?>
<?php include '../includes/public_navbar.php'; ?>
<div class="layout">
    <?php include '../includes/public_sidebar.php'; ?>
    <main class="public-content learning-public-page notifications-page">
        <section class="page-hero-compact notifications-hero">
            <div>
                <span class="section-kicker">Centro de avisos</span>
                <h1>Notificaciones</h1>
                <p>Capacitaciones, evaluaciones, logros y actividad importante de tu cuenta.</p>
            </div>
            <div class="notifications-hero-actions">
                <span class="notification-total-badge"><?php echo $noLeidas; ?> sin leer</span>
                <?php if($noLeidas > 0): ?>
                <form method="POST">
                    <?php echo csrfInput(); ?>
                    <input type="hidden" name="accion" value="leer_todas">
                    <button class="btn-secondary-modern" type="submit">Marcar todas como leídas</button>
                </form>
                <?php endif; ?>
            </div>
        </section>

        <section class="content-section content-section-first">
            <?php if(empty($notificaciones)): ?>
                <div class="empty-state">
                    <span class="empty-icon">🔔</span>
                    <h3>No tienes notificaciones todavía</h3>
                    <p>Cuando te asignen una capacitación o tengas una evaluación disponible, aparecerá aquí.</p>
                </div>
            <?php else: ?>
                <div class="notifications-list">
                <?php foreach($notificaciones as $n): ?>
                    <article class="notification-card <?php echo empty($n['leida']) ? 'is-unread' : 'is-read'; ?>">
                        <div class="notification-card-icon" aria-hidden="true"><?php echo htmlspecialchars($n['icono'] ?: '🔔'); ?></div>
                        <div class="notification-card-body">
                            <div class="notification-card-title-row">
                                <h2><?php echo htmlspecialchars($n['titulo']); ?></h2>
                                <?php if(empty($n['leida'])): ?><span class="notification-new-dot">Nueva</span><?php endif; ?>
                            </div>
                            <p><?php echo htmlspecialchars($n['mensaje']); ?></p>
                            <small><?php echo htmlspecialchars(notifFechaHumana($n['fecha_creacion'])); ?></small>
                        </div>
                        <div class="notification-card-actions">
                            <?php if(!empty($n['url'])): ?>
                            <form method="POST">
                                <?php echo csrfInput(); ?>
                                <input type="hidden" name="accion" value="leer">
                                <input type="hidden" name="id_notificacion" value="<?php echo (int)$n['id_notificacion']; ?>">
                                <input type="hidden" name="destino" value="<?php echo htmlspecialchars($n['url']); ?>">
                                <button class="btn-primary-modern" type="submit"><?php echo empty($n['leida']) ? 'Ver' : 'Abrir'; ?></button>
                            </form>
                            <?php elseif(empty($n['leida'])): ?>
                            <form method="POST">
                                <?php echo csrfInput(); ?>
                                <input type="hidden" name="accion" value="leer">
                                <input type="hidden" name="id_notificacion" value="<?php echo (int)$n['id_notificacion']; ?>">
                                <button class="btn-secondary-modern" type="submit">Marcar leída</button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" onsubmit="return confirm('¿Eliminar esta notificación?');">
                                <?php echo csrfInput(); ?>
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id_notificacion" value="<?php echo (int)$n['id_notificacion']; ?>">
                                <button class="notification-delete" type="submit" title="Eliminar">×</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
<?php include '../includes/public_footer.php'; ?>
