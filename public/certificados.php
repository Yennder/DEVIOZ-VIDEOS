<?php
require_once '../config/sesion.php';
verificarSesion();
require_once '../controllers/CertificadoController.php';
require_once '../controllers/VideoController.php';
require_once '../includes/learning_helpers.php';

$certificadosController=new CertificadoController();
$videoController=new VideoController();
$categorias=$videoController->listarCategorias();
$certificados=$certificadosController->listarUsuario((int)$_SESSION['id_usuario']);
$codigoResaltado=trim((string)($_GET['codigo']??''));
?>
<?php include '../includes/public_header.php'; ?>
<?php include '../includes/public_navbar.php'; ?>
<div class="layout">
<?php include '../includes/public_sidebar.php'; ?>
<main class="public-content learning-public-page">
<section class="page-hero-compact certificate-hero">
    <span class="section-kicker">Reconocimiento</span>
    <h1>Mis certificados</h1>
    <p>Descarga y verifica los certificados obtenidos al completar tus capacitaciones.</p>
    <div class="learning-course-hero-meta"><span>🎓 <?php echo count($certificados); ?> certificados</span><a href="verificar_certificado.php" class="btn-secondary-modern">Verificar código</a></div>
</section>
<section class="content-section content-section-first">
<?php if(!$certificados): ?>
<div class="empty-state"><span class="empty-icon">🎓</span><h3>Aún no tienes certificados</h3><p>Completa una capacitación y aprueba su evaluación para obtener el primero.</p></div>
<?php else: ?>
<div class="certificate-grid">
<?php foreach($certificados as $c): $resaltado=$codigoResaltado!=='' && hash_equals((string)$c['codigo'],$codigoResaltado); ?>
<article class="certificate-card <?php echo $resaltado?'is-highlighted':''; ?> <?php echo $c['estado']==='anulado'?'is-revoked':''; ?>">
    <div class="certificate-card-icon">🎓</div>
    <div class="certificate-card-body">
        <span class="certificate-status <?php echo $c['estado']==='valido'?'is-valid':'is-revoked'; ?>"><?php echo $c['estado']==='valido'?'Certificado válido':'Certificado anulado'; ?></span>
        <h3><?php echo learningH($c['curso_titulo']); ?></h3>
        <p><?php echo learningH($c['capacitacion_nombre']); ?></p>
        <div class="certificate-meta"><span>Nota <strong><?php echo round((float)$c['nota_final'],1); ?>%</strong></span><span>Finalizado <strong><?php echo date('d/m/Y',strtotime($c['fecha_finalizacion'])); ?></strong></span></div>
        <code><?php echo learningH($c['codigo']); ?></code>
        <?php if($c['estado']==='anulado' && $c['motivo_anulacion']): ?><small class="certificate-revoked-reason"><?php echo learningH($c['motivo_anulacion']); ?></small><?php endif; ?>
    </div>
    <div class="certificate-actions">
        <?php if($c['estado']==='valido'): ?><a class="btn-primary-modern" href="certificado_pdf.php?codigo=<?php echo urlencode($c['codigo']); ?>">Descargar PDF</a><?php endif; ?>
        <a class="btn-secondary-modern" href="verificar_certificado.php?codigo=<?php echo urlencode($c['codigo']); ?>">Verificar</a>
    </div>
</article>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section>
</main></div>
<?php include '../includes/public_footer.php'; ?>
