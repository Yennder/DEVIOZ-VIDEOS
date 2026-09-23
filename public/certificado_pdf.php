<?php
require_once '../config/sesion.php';
verificarSesion();
require_once '../controllers/CertificadoController.php';
require_once '../includes/certificado_pdf.php';

$codigo=trim((string)($_GET['codigo']??''));
$controller=new CertificadoController();
$certificado=$controller->obtenerPorCodigo($codigo);
if(!$certificado){ http_response_code(404); exit('Certificado no encontrado.'); }
if(!esAdmin() && (int)$certificado['id_usuario'] !== (int)$_SESSION['id_usuario']){ http_response_code(403); exit('No autorizado.'); }
if($certificado['estado']!=='valido'){ http_response_code(410); exit('Este certificado fue anulado y ya no puede descargarse.'); }

$proto=deviozEsHttps()?'https':'http';
$host=$_SERVER['HTTP_HOST']??'localhost';
$url=$proto.'://'.$host.'/DEVIOZ-VIDEOS/public/verificar_certificado.php?codigo='.rawurlencode($certificado['codigo']);
$pdf=generarCertificadoPdf($certificado,$url);
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="certificado-'.preg_replace('/[^A-Za-z0-9_-]/','',$certificado['codigo']).'.pdf"');
header('Content-Length: '.strlen($pdf));
echo $pdf;
