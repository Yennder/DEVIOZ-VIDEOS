<?php
require_once __DIR__ . '/../../config/sesion.php';
verificarAdmin();
require_once __DIR__ . '/../../controllers/DescargaController.php';

$controller = new DescargaController();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id_solicitud', FILTER_VALIDATE_INT);
if (!$id) { header('Location:listar.php'); exit; }
$mensaje = '';
$error = '';
$codigoGenerado = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verificarCsrfPost();
    $accion = (string)($_POST['accion'] ?? '');
    try {
        if ($accion === 'aprobar') {
            $horas = filter_input(INPUT_POST, 'horas_validez', FILTER_VALIDATE_INT) ?: 24;
            $usos = filter_input(INPUT_POST, 'max_usos', FILTER_VALIDATE_INT) ?: 1;
            $resultado = $controller->aprobarSolicitud((int)$id, (int)$_SESSION['id_usuario'], (int)$horas, (int)$usos);
            $codigoGenerado = (string)$resultado['codigo'];
            $mensaje = 'Solicitud aprobada. El usuario recibio una notificacion con el codigo.';
        } elseif ($accion === 'rechazar') {
            $motivo = trim((string)($_POST['motivo_rechazo'] ?? ''));
            $controller->rechazarSolicitud((int)$id, (int)$_SESSION['id_usuario'], $motivo);
            $mensaje = 'Solicitud rechazada y usuario notificado.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$solicitud = $controller->obtenerSolicitud((int)$id);
if (!$solicitud) { header('Location:listar.php'); exit; }
$estado = (string)($solicitud['estado_mostrado'] ?? $solicitud['estado']);
$labels = ['pendiente'=>'Pendiente','aprobada'=>'Aprobada','rechazada'=>'Rechazada','vencida'=>'Vencida','revocada'=>'Revocada','descargada'=>'Descargada'];
?>
<!doctype html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Solicitud de descarga</title><link rel="stylesheet" href="../../assets/css/admin.css"></head><body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="admin-main"><?php include __DIR__ . '/../includes/navbar.php'; ?><section class="admin-content">
<div class="gestion-header"><div><a class="back-link" href="listar.php">← Volver a descargas</a><h1>Solicitud #<?php echo (int)$solicitud['id_solicitud']; ?></h1><p><?php echo htmlspecialchars($solicitud['usuario']); ?> · <?php echo htmlspecialchars($solicitud['video']); ?></p></div><span class="download-request-status <?php echo htmlspecialchars($estado); ?>"><?php echo htmlspecialchars($labels[$estado] ?? ucfirst($estado)); ?></span></div>
<?php if ($mensaje): ?><div class="alerta exito"><?php echo htmlspecialchars($mensaje); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alerta error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if ($codigoGenerado): ?><div class="download-code-created"><span>CODIGO GENERADO Y NOTIFICADO AL USUARIO</span><strong><?php echo htmlspecialchars($codigoGenerado); ?></strong><button type="button" class="admin-copy-code" data-copy-code="<?php echo htmlspecialchars($codigoGenerado, ENT_QUOTES); ?>">Copiar codigo</button></div><?php endif; ?>

<div class="download-request-layout download-request-layout-single">
<div class="admin-card download-request-detail">
<h2>Detalle de la solicitud</h2>
<dl>
<div><dt>Usuario</dt><dd><?php echo htmlspecialchars($solicitud['usuario'].' - '.$solicitud['email']); ?></dd></div>
<div><dt>Video</dt><dd><?php echo htmlspecialchars($solicitud['video']); ?></dd></div>
<div><dt>Solicitado</dt><dd><?php echo htmlspecialchars(date('d/m/Y H:i',strtotime((string)$solicitud['fecha_solicitud']))); ?></dd></div>
<?php if(!empty($solicitud['motivo'])): ?><div><dt>Motivo</dt><dd><?php echo htmlspecialchars($solicitud['motivo']); ?></dd></div><?php endif; ?>
</dl>

<?php if ($solicitud['estado']==='pendiente'): ?>
<div class="download-review-grid">
<form method="post" class="download-approve-form"><?php echo csrfInput(); ?><input type="hidden" name="accion" value="aprobar"><input type="hidden" name="id_solicitud" value="<?php echo (int)$id; ?>"><h3>Aprobar</h3><label>Validez<select name="horas_validez"><option value="1">1 hora</option><option value="6">6 horas</option><option value="24" selected>24 horas</option><option value="72">3 dias</option><option value="168">7 dias</option></select></label><label>Descargas<select name="max_usos"><option value="1" selected>1 descarga</option><option value="2">2 descargas</option><option value="3">3 descargas</option><option value="5">5 descargas</option></select></label><button class="btn" type="submit">Aprobar y generar codigo</button></form>
<form method="post" class="download-reject-form"><?php echo csrfInput(); ?><input type="hidden" name="accion" value="rechazar"><input type="hidden" name="id_solicitud" value="<?php echo (int)$id; ?>"><h3>Rechazar</h3><label>Motivo<textarea name="motivo_rechazo" rows="5" placeholder="Indica el motivo para el usuario."></textarea></label><button class="btn-eliminar" type="submit">Rechazar solicitud</button></form>
</div>
<?php else: ?>
<div class="download-reviewed-box">
<strong>Revisada por:</strong> <?php echo htmlspecialchars($solicitud['administrador'] ?: 'Administrador'); ?><?php if($solicitud['fecha_revision']): ?> · <?php echo htmlspecialchars(date('d/m/Y H:i',strtotime((string)$solicitud['fecha_revision']))); ?><?php endif; ?>
<?php if(!empty($solicitud['codigo_visible'])): ?><br><strong>Codigo:</strong> <code><?php echo htmlspecialchars($solicitud['codigo_visible']); ?></code> <button type="button" class="admin-copy-code" data-copy-code="<?php echo htmlspecialchars($solicitud['codigo_visible'], ENT_QUOTES); ?>">Copiar</button><?php elseif($solicitud['codigo_mascara']): ?><br><strong>Codigo:</strong> <code><?php echo htmlspecialchars($solicitud['codigo_mascara']); ?></code><?php endif; ?>
<?php if(!empty($solicitud['expira_en'])): ?><br><strong>Vence:</strong> <?php echo htmlspecialchars(date('d/m/Y H:i',strtotime((string)$solicitud['expira_en']))); ?><?php endif; ?>
<?php if($solicitud['estado']==='rechazada' && !empty($solicitud['motivo_rechazo'])): ?><br><strong>Motivo del rechazo:</strong> <?php echo htmlspecialchars($solicitud['motivo_rechazo']); ?><?php endif; ?>
</div>
<?php endif; ?>
</div>
</div>
</section></div>
<script>
document.addEventListener('click', function(e){
  const b=e.target.closest('[data-copy-code]'); if(!b) return;
  const code=b.getAttribute('data-copy-code')||'';
  if(navigator.clipboard){navigator.clipboard.writeText(code).then(()=>{const t=b.textContent;b.textContent='Copiado';setTimeout(()=>b.textContent=t,1400);});}
});
</script>
</body></html>
