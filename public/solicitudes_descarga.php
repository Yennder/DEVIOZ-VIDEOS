<?php
require_once __DIR__ . '/../config/sesion.php';
verificarSesion();
require_once __DIR__ . '/../controllers/DescargaController.php';
require_once __DIR__ . '/../controllers/VideoController.php';

$controller = new DescargaController();
$videoController = new VideoController();
$categorias = $videoController->listarCategorias();
$solicitudes = $controller->listarSolicitudesUsuario((int)$_SESSION['id_usuario'], 120);

function solicitudEstadoPublico(array $s): string
{
    return (string)($s['estado_mostrado'] ?? $s['estado'] ?? 'pendiente');
}
function solicitudEstadoLabel(string $estado): string
{
    return [
        'pendiente' => 'Pendiente',
        'aprobada' => 'Aprobada',
        'rechazada' => 'Rechazada',
        'vencida' => 'Vencida',
        'revocada' => 'Revocada',
        'descargada' => 'Descargada',
    ][$estado] ?? ucfirst($estado);
}
?>
<?php include __DIR__ . '/../includes/public_header.php'; ?>
<?php include __DIR__ . '/../includes/public_navbar.php'; ?>
<div class="layout">
<?php include __DIR__ . '/../includes/public_sidebar.php'; ?>
<main class="public-content learning-public-page download-requests-page">
    <section class="page-hero-compact download-requests-hero">
        <div>
            <span class="section-kicker">DESCARGAS PROTEGIDAS</span>
            <h1>Mis solicitudes</h1>
            <p>Consulta el estado de tus solicitudes, copia codigos aprobados y vuelve al video solicitado.</p>
        </div>
    </section>

    <section class="content-section content-section-first">
        <?php if (empty($solicitudes)): ?>
            <div class="empty-state">
                <span class="empty-icon">🔐</span>
                <h3>Todavia no tienes solicitudes</h3>
                <p>Solicita una descarga desde el reproductor de cualquier video disponible.</p>
                <a class="btn-primary-modern" href="index.php">Explorar videos</a>
            </div>
        <?php else: ?>
            <div class="download-user-request-list">
            <?php foreach ($solicitudes as $s): $estado = solicitudEstadoPublico($s); ?>
                <article class="download-user-request-card <?php echo htmlspecialchars($estado); ?>">
                    <div class="download-user-request-main">
                        <div class="download-user-request-icon">🔐</div>
                        <div>
                            <div class="download-user-request-topline">
                                <h2><?php echo htmlspecialchars($s['video']); ?></h2>
                                <span class="download-user-status <?php echo htmlspecialchars($estado); ?>"><?php echo htmlspecialchars(solicitudEstadoLabel($estado)); ?></span>
                            </div>
                            <p>Solicitada el <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string)$s['fecha_solicitud']))); ?></p>
                            <?php if (!empty($s['motivo'])): ?><small>Motivo: <?php echo htmlspecialchars($s['motivo']); ?></small><?php endif; ?>
                            <?php if ($estado === 'rechazada' && !empty($s['motivo_rechazo'])): ?><div class="download-request-reason">Motivo del rechazo: <?php echo htmlspecialchars($s['motivo_rechazo']); ?></div><?php endif; ?>
                            <?php if ($estado === 'aprobada' && !empty($s['expira_en'])): ?><small>Valida hasta <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string)$s['expira_en']))); ?> · Usos <?php echo (int)$s['usos']; ?>/<?php echo (int)$s['max_usos']; ?></small><?php endif; ?>
                        </div>
                    </div>

                    <div class="download-user-request-actions">
                        <?php if ($estado === 'aprobada' && !empty($s['codigo_visible'])): ?>
                            <div class="download-visible-code">
                                <code><?php echo htmlspecialchars($s['codigo_visible']); ?></code>
                                <button type="button" class="btn-copy-download-code" data-copy-code="<?php echo htmlspecialchars($s['codigo_visible'], ENT_QUOTES); ?>">Copiar codigo</button>
                            </div>
                            <form method="post" action="descargar_video.php" target="_blank">
                                <?php echo csrfInput(); ?>
                                <input type="hidden" name="id_video" value="<?php echo (int)$s['id_video']; ?>">
                                <input type="hidden" name="codigo_descarga" value="<?php echo htmlspecialchars($s['codigo_visible'], ENT_QUOTES); ?>">
                                <button type="submit" class="btn-primary-modern">Descargar ahora</button>
                            </form>
                        <?php endif; ?>
                        <a class="btn-secondary-modern" href="detalle.php?id=<?php echo (int)$s['id_video']; ?>">Ir al video</a>
                        <a class="download-request-detail-link" href="solicitud_descarga.php?id=<?php echo (int)$s['id_solicitud']; ?>">Ver detalle</a>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
</div>
<?php include __DIR__ . '/../includes/public_footer.php'; ?>
