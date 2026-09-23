<?php
require_once '../config/sesion.php';
require_once '../controllers/CertificadoController.php';
require_once '../controllers/VideoController.php';
require_once '../includes/learning_helpers.php';

$controller=new CertificadoController();
$videoController=new VideoController();
$categorias=$videoController->listarCategorias();
$codigo=trim((string)($_GET['codigo']??$_POST['codigo']??''));
$certificado=$codigo!==''?$controller->obtenerPorCodigo($codigo):null;
$consultado=$codigo!=='';
?>
<?php include '../includes/public_header.php'; ?>
<?php include '../includes/public_navbar.php'; ?>
<div class="layout">
<?php include '../includes/public_sidebar.php'; ?>
<main class="public-content learning-public-page">
<section class="page-hero-compact certificate-verify-hero"><span class="section-kicker">Validación pública</span><h1>Verificar certificado</h1><p>Ingresa el código para comprobar si un certificado fue emitido por DEVIOZ.</p></section>
<section class="content-section content-section-first">
<form method="GET" class="certificate-verify-form"><label for="codigo">Código del certificado</label><div><input id="codigo" name="codigo" value="<?php echo learningH($codigo); ?>" placeholder="DEVIOZ-2026-000001-XXXXXXXX" required><button class="btn-primary-modern" type="submit">Verificar</button></div></form>
<?php if($consultado): ?>
    <?php if(!$certificado): ?>
        <div class="certificate-verification-result is-invalid"><strong>Certificado no encontrado</strong><p>Revisa el código ingresado e inténtalo nuevamente.</p></div>
    <?php elseif($certificado['estado']!=='valido'): ?>
        <div class="certificate-verification-result is-invalid"><strong>Certificado anulado</strong><p>Este código existe, pero el certificado ya no se encuentra vigente.</p><?php if($certificado['motivo_anulacion']): ?><small><?php echo learningH($certificado['motivo_anulacion']); ?></small><?php endif; ?></div>
    <?php else: ?>
        <div class="certificate-verification-result is-valid"><span>✓</span><div><strong>Certificado válido</strong><h2><?php echo learningH($certificado['nombre_participante']); ?></h2><p>Completó <b><?php echo learningH($certificado['curso_titulo']); ?></b> mediante <?php echo learningH($certificado['capacitacion_nombre']); ?>.</p><div class="certificate-verify-data"><span>Nota <b><?php echo round((float)$certificado['nota_final'],1); ?>%</b></span><span>Finalización <b><?php echo date('d/m/Y',strtotime($certificado['fecha_finalizacion'])); ?></b></span><span>Código <b><?php echo learningH($certificado['codigo']); ?></b></span></div></div></div>
    <?php endif; ?>
<?php endif; ?>
</section>
</main></div>
<?php include '../includes/public_footer.php'; ?>
