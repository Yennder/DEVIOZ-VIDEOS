<?php

require_once __DIR__ . '/../../config/sesion.php';
verificarAdmin();
require_once __DIR__ . '/../../controllers/DescargaController.php';

$controller = new DescargaController();
$mensaje = '';
$error = '';

if (!$controller->tablasDisponibles()) {
    $error = 'Falta importar database/migracion_v4_2_1_descargas.sql.';
} elseif (!$controller->solicitudesDisponibles()) {
    $error = 'Falta importar database/migracion_v4_2_1_solicitudes.sql.';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $error === '') {
    verificarCsrfPost();
    $accion = (string)($_POST['accion'] ?? '');
    try {
        if ($accion === 'estado_codigo') {
            $idCodigo = filter_input(INPUT_POST, 'id_codigo', FILTER_VALIDATE_INT);
            $estadoNuevo = (string)($_POST['estado_nuevo'] ?? '');
            if (!$idCodigo || !$controller->cambiarEstado((int)$idCodigo, $estadoNuevo)) {
                throw new RuntimeException('No se pudo actualizar el codigo.');
            }
            $mensaje = $estadoNuevo === 'revocado' ? 'Codigo revocado.' : 'Codigo reactivado.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$buscarSolicitud = trim((string)($_GET['buscar_solicitud'] ?? ''));
$estadoSolicitud = trim((string)($_GET['estado_solicitud'] ?? ''));
$solicitudes = $controller->solicitudesDisponibles() ? $controller->listarSolicitudes($buscarSolicitud, $estadoSolicitud) : [];
$pendientes = $controller->solicitudesDisponibles() ? $controller->contarSolicitudesPendientes() : 0;

$buscar = trim((string)($_GET['buscar'] ?? ''));
$estado = trim((string)($_GET['estado'] ?? ''));
$codigos = $controller->tablasDisponibles() ? $controller->listar($buscar, $estado) : [];
$historial = $controller->tablasDisponibles() ? $controller->historial(100) : [];

function descargaEstadoClase(string $estado): string
{
    return 'download-status ' . (in_array($estado, ['disponible','revocado','vencido','agotado'], true) ? $estado : 'vencido');
}
function solicitudEstadoClase(string $estado): string
{
    return 'download-request-status ' . (in_array($estado, ['pendiente','aprobada','rechazada','vencida','revocada','descargada'], true) ? $estado : 'pendiente');
}
function solicitudEstadoLabel(string $estado): string
{
    return ['pendiente'=>'Pendiente','aprobada'=>'Aprobada','rechazada'=>'Rechazada','vencida'=>'Vencida','revocada'=>'Revocada','descargada'=>'Descargada'][$estado] ?? ucfirst($estado);
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Solicitudes de descarga - DEVIOZ VIDEOS</title>
<link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="admin-main">
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<section class="admin-content">
<div class="gestion-header">
  <div>
    <span class="section-kicker">CONTROL DE CONTENIDO</span>
    <h1>Solicitudes de descarga</h1>
    <p>Los usuarios solicitan acceso; el administrador revisa y aprueba o rechaza cada solicitud.</p>
  </div>
  <div class="download-request-summary"><strong><?php echo (int)$pendientes; ?></strong><span>Pendientes</span></div>
</div>

<?php if ($mensaje): ?><div class="alerta exito"><?php echo htmlspecialchars($mensaje); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alerta error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<?php if ($controller->solicitudesDisponibles()): ?>
<form method="get" class="filtros-admin download-request-filters">
<div class="filtro-busqueda"><label>Buscar solicitud</label><input type="text" name="buscar_solicitud" value="<?php echo htmlspecialchars($buscarSolicitud); ?>" placeholder="Usuario, correo o video..."></div>
<div><label>Estado</label><select name="estado_solicitud"><option value="">Todos</option><?php foreach (['pendiente'=>'Pendiente','aprobada'=>'Aprobada','rechazada'=>'Rechazada'] as $k=>$label): ?><option value="<?php echo $k; ?>" <?php echo $estadoSolicitud===$k?'selected':''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></div>
<button class="btn" type="submit">Filtrar</button><a class="btn-secundario" href="listar.php">Limpiar</a>
</form>

<div class="admin-table-wrapper"><table class="admin-table download-admin-table"><thead><tr><th>Usuario</th><th>Video</th><th>Solicitud</th><th>Estado</th><th>Revisado por</th><th>Accion</th></tr></thead><tbody>
<?php if (!$solicitudes): ?><tr><td colspan="6">No hay solicitudes registradas.</td></tr><?php endif; ?>
<?php foreach ($solicitudes as $s): ?><tr class="<?php echo $s['estado']==='pendiente'?'download-request-pending-row':''; ?>">
<td><strong><?php echo htmlspecialchars($s['usuario']); ?></strong><small><?php echo htmlspecialchars($s['email']); ?></small></td>
<td><?php echo htmlspecialchars($s['video']); ?></td>
<td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string)$s['fecha_solicitud']))); ?><?php if (!empty($s['motivo'])): ?><small><?php echo htmlspecialchars($s['motivo']); ?></small><?php endif; ?></td>
<?php $estadoVista=(string)($s['estado_mostrado'] ?? $s['estado']); ?><td><span class="<?php echo solicitudEstadoClase($estadoVista); ?>"><?php echo htmlspecialchars(solicitudEstadoLabel($estadoVista)); ?></span></td>
<td><?php echo htmlspecialchars($s['administrador'] ?: '—'); ?></td>
<td><a class="btn-editar" href="solicitud.php?id=<?php echo (int)$s['id_solicitud']; ?>"><?php echo $s['estado']==='pendiente'?'Revisar':'Ver'; ?></a></td>
</tr><?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>

<div class="gestion-header download-history-header"><div><h2>Codigos generados</h2><p>Autorizaciones creadas al aprobar solicitudes de descarga.</p></div></div>
<form method="get" class="filtros-admin">
<div class="filtro-busqueda"><label>Buscar codigo</label><input type="text" name="buscar" value="<?php echo htmlspecialchars($buscar); ?>" placeholder="Usuario, video o codigo..."></div>
<div><label>Estado</label><select name="estado"><option value="">Todos</option><?php foreach (['disponible'=>'Disponible','agotado'=>'Agotado','vencido'=>'Vencido','revocado'=>'Revocado'] as $k=>$label): ?><option value="<?php echo $k; ?>" <?php echo $estado===$k?'selected':''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></div>
<button class="btn" type="submit">Filtrar</button><a class="btn-secundario" href="listar.php">Limpiar</a>
</form>

<div class="admin-table-wrapper"><table class="admin-table download-admin-table"><thead><tr><th>Codigo</th><th>Usuario</th><th>Video</th><th>Uso</th><th>Vence</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
<?php if (!$codigos): ?><tr><td colspan="7">No hay codigos registrados.</td></tr><?php endif; ?>
<?php foreach ($codigos as $c): ?><tr>
<td><code><?php echo htmlspecialchars($c['codigo_mascara']); ?></code></td>
<td><strong><?php echo htmlspecialchars($c['usuario']); ?></strong><small><?php echo htmlspecialchars($c['email']); ?></small></td>
<td><?php echo htmlspecialchars($c['video']); ?></td>
<td><?php echo (int)$c['usos']; ?> / <?php echo (int)$c['max_usos']; ?></td>
<td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string)$c['expira_en']))); ?></td>
<td><span class="<?php echo descargaEstadoClase((string)$c['estado_calculado']); ?>"><?php echo htmlspecialchars(ucfirst((string)$c['estado_calculado'])); ?></span></td>
<td><?php if (($c['estado'] ?? '') === 'activo'): ?><form method="post" class="inline-form"><?php echo csrfInput(); ?><input type="hidden" name="accion" value="estado_codigo"><input type="hidden" name="id_codigo" value="<?php echo (int)$c['id_codigo']; ?>"><input type="hidden" name="estado_nuevo" value="revocado"><button class="btn-eliminar" type="submit">Revocar</button></form><?php else: ?><form method="post" class="inline-form"><?php echo csrfInput(); ?><input type="hidden" name="accion" value="estado_codigo"><input type="hidden" name="id_codigo" value="<?php echo (int)$c['id_codigo']; ?>"><input type="hidden" name="estado_nuevo" value="activo"><button class="btn-editar" type="submit">Reactivar</button></form><?php endif; ?></td>
</tr><?php endforeach; ?>
</tbody></table></div>

<div class="gestion-header download-history-header"><div><h2>Historial de descargas</h2><p>Ultimas descargas autorizadas registradas por el sistema.</p></div></div>
<div class="admin-table-wrapper"><table class="admin-table download-admin-table"><thead><tr><th>Fecha</th><th>Usuario</th><th>Video</th><th>Codigo</th><th>IP</th></tr></thead><tbody>
<?php if (!$historial): ?><tr><td colspan="5">Todavia no se registraron descargas.</td></tr><?php endif; ?>
<?php foreach ($historial as $h): ?><tr><td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string)$h['fecha_descarga']))); ?></td><td><strong><?php echo htmlspecialchars($h['usuario']); ?></strong><small><?php echo htmlspecialchars($h['email']); ?></small></td><td><?php echo htmlspecialchars($h['video']); ?></td><td><code><?php echo htmlspecialchars($h['codigo_mascara']); ?></code></td><td><?php echo htmlspecialchars((string)$h['ip']); ?></td></tr><?php endforeach; ?>
</tbody></table></div>
</section></div>
</body></html>
