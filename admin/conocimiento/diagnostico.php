<?php
require_once '../../config/sesion.php';
verificarAdmin();
require_once '../../includes/KnowledgeIndexManager.php';
$manager=new KnowledgeIndexManager();
$diag=$manager->diagnostics();
$d=is_array($diag['details'] ?? null)?$diag['details']:[];
$s=$diag['status'] ?? [];
$log=$manager->tailLog(35);
function ragOk($v):bool{return $v===true||$v===1||$v==='1'||$v==='OK'||$v==='ok';}
$checks=[
 ['Python', (bool)($diag['python_ready']??false), $d['python_version']??'No detectado'],
 ['PyMySQL', ragOk($d['pymysql']??false), $d['pymysql_version']??'Librería'],
 ['NumPy', ragOk($d['numpy']??false), $d['numpy_version']??'Vectores'],
 ['Sentence Transformers', ragOk($d['sentence_transformers']??false), $d['sentence_transformers_version']??'Embeddings'],
 ['MySQL', ragOk($d['mysql']??false), 'Conexión a devioz_videos'],
 ['Migración V4.1', ragOk($d['migration_v41']??false), 'Tablas rag_documentos y rag_chunks'],
];
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Diagnóstico RAG - DEVIOZ VIDEOS</title><link rel="stylesheet" href="../../assets/css/admin.css"></head><body>
<?php include '../includes/sidebar.php'; ?><div class="admin-main"><?php include '../includes/navbar.php'; ?><section class="admin-content knowledge-admin-page">
<div class="gestion-header"><div><span class="learning-admin-kicker">TECHFLIX V4.1</span><h1>Diagnóstico RAG local</h1><p class="dashboard-subtitle">Comprueba el entorno que genera embeddings y construye el índice semántico.</p></div><div class="knowledge-header-actions"><a href="index.php" class="btn-secundario">Volver</a><a href="diagnostico.php" class="btn-editar">Actualizar</a></div></div>
<?php if(!empty($diag['error'])):?><div class="transcription-admin-alert is-error"><?php echo htmlspecialchars((string)$diag['error']); ?></div><?php endif; ?>
<div class="worker-diagnostic-grid"><?php foreach($checks as [$label,$ok,$detail]): ?><article class="worker-diagnostic-card <?php echo $ok?'is-ok':'is-warning'; ?>"><div class="worker-diagnostic-icon"><?php echo $ok?'✓':'!'; ?></div><div><strong><?php echo htmlspecialchars($label); ?></strong><span><?php echo htmlspecialchars((string)$detail); ?></span></div></article><?php endforeach; ?></div>
<div class="knowledge-diagnostic-summary"><article><span>Modelo</span><strong><?php echo htmlspecialchars((string)($d['model_name']??$manager->modelName())); ?></strong></article><article><span>Transcripciones</span><strong><?php echo (int)($d['transcripciones_completadas']??0); ?></strong></article><article><span>Videos indexados</span><strong><?php echo (int)($d['documentos_indexados']??0); ?></strong></article><article><span>Chunks</span><strong><?php echo (int)($d['chunks']??0); ?></strong></article></div>
<section class="knowledge-install-card"><span class="learning-admin-kicker">INSTALACIÓN LOCAL</span><h2>Si aparece alguna dependencia pendiente</h2><p>Ejecuta una sola vez:</p><code>C:\xampp\htdocs\DEVIOZ-VIDEOS\python\conocimiento\INSTALAR_RAG_LOCAL.bat</code><p>La primera indexación descargará el modelo de embeddings. Después, el índice y las búsquedas pueden reutilizarlo desde la caché local.</p></section>
<div class="worker-log-card"><div class="worker-log-title"><strong>Últimos mensajes del indexador</strong><span><?php echo !empty($s['active'])?'Activo':'Detenido'; ?></span></div><pre><?php echo htmlspecialchars($log!==''?$log:'Aún no hay registros de indexación.'); ?></pre></div>
</section></div></body></html>
