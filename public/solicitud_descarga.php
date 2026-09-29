<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../controllers/DescargaController.php';
require_once __DIR__ . '/../controllers/VideoController.php';
verificarSesion();

$controller = new DescargaController();
$videoController = new VideoController();
$categorias = $videoController->listarCategorias();
$error = '';
$mensaje = '';
$idSolicitud = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id_solicitud', FILTER_VALIDATE_INT);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verificarCsrfPost();
    $accion = (string)($_POST['accion'] ?? '');
    try {
        if ($accion === 'crear') {
            $idVideo = filter_input(INPUT_POST, 'id_video', FILTER_VALIDATE_INT);
            $motivo = trim((string)($_POST['motivo'] ?? ''));
            if (!$idVideo) throw new RuntimeException('No se pudo identificar el video.');
            $idSolicitud = $controller->solicitar((int)$_SESSION['id_usuario'], (int)$idVideo, $motivo);
            header('Location: solicitud_descarga.php?id=' . (int)$idSolicitud . '&creada=1');
            exit;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if (!$idSolicitud) { header('Location: solicitudes_descarga.php'); exit; }
$solicitud = $controller->obtenerSolicitud((int)$idSolicitud);
if (!$solicitud || (int)$solicitud['id_usuario'] !== (int)$_SESSION['id_usuario']) {
    http_response_code(403);
    exit('No tienes acceso a esta solicitud.');
}
if (isset($_GET['creada'])) $mensaje = 'Solicitud enviada. Puedes seguir usando TechFlix mientras el administrador la revisa.';
$estado = (string)($solicitud['estado_mostrado'] ?? $solicitud['estado']);
$labels = ['pendiente'=>'Pendiente','aprobada'=>'Aprobada','rechazada'=>'Rechazada','vencida'=>'Vencida','revocada'=>'Revocada','descargada'=>'Descargada'];
?>
<?php include __DIR__ . '/../includes/public_header.php'; ?>
<?php include __DIR__ . '/../includes/public_navbar.php'; ?>
<div class="layout">
<?php include __DIR__ . '/../includes/public_sidebar.php'; ?>
<main class="public-content learning-public-page download-request-public-page">
<section class="page-hero-compact download-request-public-hero">
    <div><span class="section-kicker">DESCARGA PROTEGIDA</span><h1>Solicitud de descarga</h1><p><?php echo htmlspecialchars($solicitud['video']); ?></p></div>
    <span class="download-user-status <?php echo htmlspecialchars($estado); ?>"><?php echo htmlspecialchars($labels[$estado] ?? ucfirst($estado)); ?></span>
</section>
<?php if($mensaje): ?><div class="interaction-toast is-visible download-inline-notice"><?php echo htmlspecialchars($mensaje); ?></div><?php endif; ?>
<?php if($error): ?><div class="download-inline-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<section class="content-section content-section-first">
    <div class="download-request-summary-card">
        <div>
            <span class="section-kicker">ESTADO</span>
            <h2><?php echo htmlspecialchars($labels[$estado] ?? ucfirst($estado)); ?></h2>
            <p>Solicitada: <?php echo htmlspecialchars(date('d/m/Y H:i',strtotime((string)$solicitud['fecha_solicitud']))); ?></p>
            <?php if(!empty($solicitud['motivo'])): ?><p><strong>Motivo:</strong> <?php echo htmlspecialchars($solicitud['motivo']); ?></p><?php endif; ?>
            <?php if($estado==='pendiente'): ?><p>El administrador aun debe revisar tu solicitud. Recibiras una notificacion cuando cambie de estado.</p><?php endif; ?>
            <?php if($estado==='rechazada'): ?><p>La solicitud fue rechazada.<?php if(!empty($solicitud['motivo_rechazo'])): ?> Motivo: <?php echo htmlspecialchars($solicitud['motivo_rechazo']); ?><?php endif; ?></p><?php endif; ?>
            <?php if(in_array($estado,['vencida','revocada','descargada'],true)): ?><p>La autorizacion ya no esta disponible. Puedes volver al video y solicitar una nueva si la necesitas.</p><?php endif; ?>
        </div>

        <?php if($estado==='aprobada' && !empty($solicitud['codigo_visible'])): ?>
        <div class="download-approved-panel">
            <span>Codigo autorizado</span>
            <div class="download-visible-code">
                <code><?php echo htmlspecialchars($solicitud['codigo_visible']); ?></code>
                <button type="button" class="btn-copy-download-code" data-copy-code="<?php echo htmlspecialchars($solicitud['codigo_visible'], ENT_QUOTES); ?>">Copiar codigo</button>
            </div>
            <?php if(!empty($solicitud['expira_en'])): ?><small>Valido hasta <?php echo htmlspecialchars(date('d/m/Y H:i',strtotime((string)$solicitud['expira_en']))); ?> · Usos <?php echo (int)$solicitud['usos']; ?>/<?php echo (int)$solicitud['max_usos']; ?></small><?php endif; ?>
            <form method="post" action="descargar_video.php" target="_blank">
                <?php echo csrfInput(); ?>
                <input type="hidden" name="id_video" value="<?php echo (int)$solicitud['id_video']; ?>">
                <input type="hidden" name="codigo_descarga" value="<?php echo htmlspecialchars($solicitud['codigo_visible'], ENT_QUOTES); ?>">
                <button type="submit" class="btn-primary-modern">Descargar ahora</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <div class="download-request-page-actions">
        <a class="btn-primary-modern" href="detalle.php?id=<?php echo (int)$solicitud['id_video']; ?>">Ir al video</a>
        <a class="btn-secondary-modern" href="solicitudes_descarga.php">Todas mis solicitudes</a>
    </div>
</section>
</main>
</div>
<?php include __DIR__ . '/../includes/public_footer.php'; ?>
