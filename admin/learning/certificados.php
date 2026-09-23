<?php
require_once '../../config/sesion.php';
verificarAdmin();
require_once '../../controllers/CertificadoController.php';
require_once '../../includes/learning_helpers.php';
$controller=new CertificadoController();
$mensaje='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verificarCsrfPost();
    $accion=$_POST['accion']??'';
    $id=(int)($_POST['id_certificado']??0);
    if($accion==='anular'){
        $controller->cambiarEstado($id,'anulado',$_POST['motivo']??'Anulado por el administrador.');
        $mensaje='Certificado anulado.';
    } elseif($accion==='reactivar'){
        $controller->cambiarEstado($id,'valido');
        $mensaje='Certificado reactivado.';
    }
}
$buscar=trim((string)($_GET['buscar']??''));
$estado=(string)($_GET['estado']??'');
$rows=$controller->listarAdmin($buscar,$estado);
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Certificados - Learning Lab</title><link rel="stylesheet" href="../../assets/css/admin.css"></head><body>
<?php include '../includes/sidebar.php'; ?><div class="admin-main"><?php include '../includes/navbar.php'; ?>
<section class="admin-content learning-admin-page">
<div class="gestion-header"><div><span class="learning-admin-kicker">Learning Lab</span><h1>Certificados emitidos</h1><p class="dashboard-subtitle">Consulta, valida o anula los certificados obtenidos por los participantes.</p></div></div>
<?php if($mensaje): ?><div class="mensaje-exito"><?php echo learningH($mensaje); ?></div><?php endif; ?>
<form method="GET" class="filtros-admin learning-filter certificate-admin-filter"><div><label>Buscar</label><input name="buscar" value="<?php echo learningH($buscar); ?>" placeholder="Participante, curso o código"></div><div><label>Estado</label><select name="estado"><option value="">Todos</option><option value="valido" <?php echo $estado==='valido'?'selected':''; ?>>Válidos</option><option value="anulado" <?php echo $estado==='anulado'?'selected':''; ?>>Anulados</option></select></div><div class="filtros-botones"><button class="btn-filtrar">Filtrar</button><a class="btn-limpiar" href="certificados.php">Limpiar</a></div></form>
<div class="admin-table-wrapper"><table class="admin-table"><thead><tr><th>Participante</th><th>Curso / capacitación</th><th>Finalización</th><th>Nota</th><th>Código</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="7" class="tabla-vacia">No hay certificados para este filtro.</td></tr><?php else: foreach($rows as $r): ?>
<tr><td><strong><?php echo learningH($r['nombre_participante']); ?></strong></td><td><strong><?php echo learningH($r['curso_titulo']); ?></strong><small class="learning-table-sub"><?php echo learningH($r['capacitacion_nombre']); ?></small></td><td><?php echo date('d/m/Y',strtotime($r['fecha_finalizacion'])); ?></td><td><?php echo round((float)$r['nota_final'],1); ?>%</td><td><code class="certificate-admin-code"><?php echo learningH($r['codigo']); ?></code></td><td><span class="certificate-admin-status <?php echo $r['estado']==='valido'?'is-valid':'is-revoked'; ?>"><?php echo $r['estado']==='valido'?'Válido':'Anulado'; ?></span></td><td><div class="acciones-tabla certificate-admin-actions"><a class="btn-editar" target="_blank" href="../../public/verificar_certificado.php?codigo=<?php echo urlencode($r['codigo']); ?>">Verificar</a><?php if($r['estado']==='valido'): ?><a class="btn-editar" href="../../public/certificado_pdf.php?codigo=<?php echo urlencode($r['codigo']); ?>">PDF</a><?php endif; ?><?php if($r['estado']==='valido'): ?><form method="POST" onsubmit="return confirm('¿Anular este certificado?');"><?php echo csrfInput(); ?><input type="hidden" name="accion" value="anular"><input type="hidden" name="id_certificado" value="<?php echo (int)$r['id_certificado']; ?>"><input type="hidden" name="motivo" value="Anulado por el administrador."><button type="submit" class="btn-eliminar">Anular</button></form><?php else: ?><form method="POST"><?php echo csrfInput(); ?><input type="hidden" name="accion" value="reactivar"><input type="hidden" name="id_certificado" value="<?php echo (int)$r['id_certificado']; ?>"><button type="submit" class="btn-filtrar">Reactivar</button></form><?php endif; ?></div></td></tr>
<?php endforeach; endif; ?></tbody></table></div>
</section></div></body></html>
