<?php
require_once '../../config/sesion.php';
verificarAdmin();
if(($_SERVER['REQUEST_METHOD'] ?? '')!=='POST'){header('Location:index.php');exit;}
verificarCsrf($_POST['csrf_token'] ?? null);
session_write_close();
require_once '../../includes/KnowledgeIndexManager.php';
$accion=trim((string)($_POST['accion'] ?? ''));
if(!in_array($accion,['indexar','reconstruir','detener'],true)){header('Location:index.php?error='.urlencode('Acción inválida.'));exit;}
try{
    $manager=new KnowledgeIndexManager();
    if($accion==='detener'){$r=$manager->stop();}
    else{$r=$manager->start($accion==='reconstruir');}
    header('Location:index.php?msg='.urlencode((string)($r['message'] ?? 'Acción completada.')));
}catch(Throwable $e){
    error_log('TECHFLIX V4.1 RAG: '.$e->getMessage());
    header('Location:index.php?error='.urlencode($e->getMessage()));
}
exit;
