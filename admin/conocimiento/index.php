<?php
require_once '../../config/sesion.php';
verificarAdmin();
require_once '../../controllers/KnowledgeController.php';
require_once '../../includes/KnowledgeIndexManager.php';

$controller = new KnowledgeController();
$manager = new KnowledgeIndexManager();
$tablas = $controller->tablasDisponibles();
$resumen = $tablas ? $controller->resumen() : ['transcritos'=>0,'indexados'=>0,'pendientes'=>0,'errores'=>0,'chunks'=>0];
$status = $manager->status();

$buscar = trim((string)($_GET['buscar'] ?? ''));
$estado = trim((string)($_GET['estado'] ?? ''));
if (!in_array($estado, ['', 'pendiente', 'indexando', 'indexado', 'error'], true)) $estado='';
$documentos = $tablas ? $controller->listarDocumentos($buscar, $estado) : [];

$q = trim((string)($_GET['q'] ?? ''));
$scope = trim((string)($_GET['scope'] ?? 'global'));
$scopeId = (int)($_GET['scope_id'] ?? 0);
if (!in_array($scope, ['global','video','curso'], true)) $scope='global';
$resultados = [];
$searchError = '';
if ($q !== '' && $tablas) {
    if ($scope !== 'global' && $scopeId <= 0) {
        $searchError = 'Selecciona el curso o video en el que deseas buscar.';
    } else {
        try {
            $resultados = $manager->search($q, 6, $scope, $scopeId);
        } catch (Throwable $e) {
            $searchError = $e->getMessage();
        }
    }
}
$cursos = $controller->cursosPublicados();
$videosIndexados = $controller->videosIndexados();

function kbH($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function kbTime($seconds): string {
    $s=max(0,(int)round((float)$seconds));$h=intdiv($s,3600);$s-=$h*3600;$m=intdiv($s,60);$s-=$m*60;
    return $h>0?sprintf('%02d:%02d:%02d',$h,$m,$s):sprintf('%02d:%02d',$m,$s);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Base de conocimiento - DEVIOZ VIDEOS</title>
<link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="admin-main">
<?php include '../includes/navbar.php'; ?>
<section class="admin-content knowledge-admin-page">
    <div class="gestion-header">
        <div>
            <span class="learning-admin-kicker">TECHFLIX V4.1</span>
            <h1>Base de conocimiento semántica</h1>
            <p class="dashboard-subtitle">Convierte las transcripciones existentes en fragmentos semánticos reutilizables por DEVIOZ AI y el buscador inteligente.</p>
        </div>
        <div class="knowledge-header-actions">
            <a href="diagnostico.php" class="btn-secundario">Diagnóstico RAG</a>
        </div>
    </div>

    <?php if(isset($_GET['msg'])): ?><div class="transcription-admin-alert is-success"><?php echo kbH($_GET['msg']); ?></div><?php endif; ?>
    <?php if(isset($_GET['error'])): ?><div class="transcription-admin-alert is-error"><?php echo kbH($_GET['error']); ?></div><?php endif; ?>

    <?php if(!$tablas): ?>
        <div class="transcription-admin-alert is-warning">Primero importa <code>database/migracion_v4_1_rag.sql</code>. Tus transcripciones actuales no se eliminarán.</div>
    <?php endif; ?>

    <article class="knowledge-engine-card <?php echo !empty($status['active'])?'is-online':'is-offline'; ?>">
        <div class="knowledge-engine-main">
            <div class="knowledge-engine-status"><span class="knowledge-live-dot"></span><strong><?php echo !empty($status['active'])?'Indexador activo':'Indexador detenido'; ?></strong><span>LOCAL</span></div>
            <h3>Índice semántico local</h3>
            <p><?php echo kbH($status['message'] ?: 'Listo para indexar contenido nuevo o modificado.'); ?></p>
            <?php if(!empty($status['current_title'])): ?><small>Procesando: <?php echo kbH($status['current_title']); ?></small><?php endif; ?>
        </div>
        <div class="knowledge-engine-meta">
            <span>Modelo <strong><?php echo kbH($status['model'] ?: $manager->modelName()); ?></strong></span>
            <span>Proceso <strong><?php echo (int)$status['processed']; ?> / <?php echo (int)$status['total']; ?></strong></span>
            <span>Chunks sesión <strong><?php echo (int)$status['chunks']; ?></strong></span>
            <span>PID <strong><?php echo $status['pid'] ? (int)$status['pid'] : '—'; ?></strong></span>
        </div>
        <div class="knowledge-engine-buttons">
            <form method="POST" action="accion.php"><?php echo csrfInput(); ?><input type="hidden" name="accion" value="indexar"><button class="btn-editar" <?php echo !$tablas?'disabled':''; ?>>Indexar pendientes</button></form>
            <form method="POST" action="accion.php" onsubmit="return confirm('¿Reconstruir todo el índice semántico?');"><?php echo csrfInput(); ?><input type="hidden" name="accion" value="reconstruir"><button class="btn-secundario" <?php echo !$tablas?'disabled':''; ?>>Reconstruir índice</button></form>
            <?php if(!empty($status['active'])): ?><form method="POST" action="accion.php"><?php echo csrfInput(); ?><input type="hidden" name="accion" value="detener"><button class="btn-eliminar">Detener</button></form><?php endif; ?>
        </div>
    </article>

    <div class="knowledge-summary-grid">
        <article><span>Transcritos</span><strong><?php echo (int)$resumen['transcritos']; ?></strong><small>Videos con transcripción completada.</small></article>
        <article><span>Indexados</span><strong><?php echo (int)$resumen['indexados']; ?></strong><small>Listos para búsqueda semántica.</small></article>
        <article><span>Pendientes</span><strong><?php echo (int)$resumen['pendientes']; ?></strong><small>Nuevos o modificados.</small></article>
        <article><span>Chunks</span><strong><?php echo (int)$resumen['chunks']; ?></strong><small>Fragmentos de conocimiento.</small></article>
        <article><span>Con error</span><strong><?php echo (int)$resumen['errores']; ?></strong><small>Requieren revisión.</small></article>
    </div>

    <section class="knowledge-search-card">
        <div class="knowledge-search-head">
            <div><span class="learning-admin-kicker">PRUEBA SEMÁNTICA</span><h2>Buscar dentro del contenido</h2><p>Esta búsqueda todavía no responde con IA: solo verifica que el índice encuentre fragmentos relevantes por significado.</p></div>
        </div>
        <form method="GET" class="knowledge-search-form">
            <input type="text" name="q" value="<?php echo kbH($q); ?>" placeholder="Ej. ¿Dónde explican para qué sirve un contenedor?" maxlength="500">
            <select name="scope" id="knowledgeScope">
                <option value="global" <?php echo $scope==='global'?'selected':''; ?>>Todo TechFlix</option>
                <option value="curso" <?php echo $scope==='curso'?'selected':''; ?>>Un curso</option>
                <option value="video" <?php echo $scope==='video'?'selected':''; ?>>Un video</option>
            </select>
            <select name="scope_id" id="knowledgeScopeId" data-current="<?php echo (int)$scopeId; ?>">
                <option value="0">Seleccionar...</option>
                <?php if($scope==='curso'): foreach($cursos as $c): ?><option value="<?php echo (int)$c['id_curso']; ?>" <?php echo $scopeId===(int)$c['id_curso']?'selected':''; ?>><?php echo kbH($c['titulo']); ?></option><?php endforeach; endif; ?>
                <?php if($scope==='video'): foreach($videosIndexados as $v): ?><option value="<?php echo (int)$v['id_video']; ?>" <?php echo $scopeId===(int)$v['id_video']?'selected':''; ?>><?php echo kbH($v['titulo']); ?></option><?php endforeach; endif; ?>
            </select>
            <button class="btn-filtrar">Buscar</button>
        </form>
        <?php if($searchError): ?><div class="transcription-admin-alert is-error"><?php echo kbH($searchError); ?></div><?php endif; ?>
        <?php if($q!=='' && !$searchError): ?>
            <div class="knowledge-results">
            <?php if(!$resultados): ?><div class="tabla-vacia">No se encontraron fragmentos indexados para esta consulta.</div><?php else: foreach($resultados as $r): ?>
                <article class="knowledge-result-card">
                    <div class="knowledge-result-top"><div><strong><?php echo kbH($r['titulo'] ?? 'Video'); ?></strong><span><?php echo kbH($r['categoria'] ?? ''); ?><?php echo !empty($r['serie'])?' · '.kbH($r['serie']):''; ?></span></div><b><?php echo kbH($r['score_percent'] ?? 0); ?>%</b></div>
                    <p><?php echo kbH($r['texto'] ?? ''); ?></p>
                    <div class="knowledge-result-footer"><span><?php echo kbH($r['inicio'] ?? kbTime($r['inicio_segundos'] ?? 0)); ?> - <?php echo kbH($r['fin'] ?? kbTime($r['fin_segundos'] ?? 0)); ?></span><a href="/DEVIOZ-VIDEOS/public/detalle.php?id=<?php echo (int)($r['id_video'] ?? 0); ?>#t=<?php echo (int)round((float)($r['inicio_segundos'] ?? 0)); ?>">Abrir video</a></div>
                </article>
            <?php endforeach; endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="gestion-header knowledge-list-header"><div><span class="learning-admin-kicker">CONTENIDO INDEXABLE</span><h2>Estado por video</h2></div></div>
    <form method="GET" class="filtros-admin knowledge-filter">
        <div><label>Buscar</label><input name="buscar" value="<?php echo kbH($buscar); ?>" placeholder="Título, categoría o serie"></div>
        <div><label>Estado</label><select name="estado"><option value="">Todos</option><option value="pendiente" <?php echo $estado==='pendiente'?'selected':''; ?>>Pendiente</option><option value="indexando" <?php echo $estado==='indexando'?'selected':''; ?>>Indexando</option><option value="indexado" <?php echo $estado==='indexado'?'selected':''; ?>>Indexado</option><option value="error" <?php echo $estado==='error'?'selected':''; ?>>Error</option></select></div>
        <div class="filtros-botones"><button class="btn-filtrar">Filtrar</button><a class="btn-limpiar" href="index.php">Limpiar</a></div>
    </form>

    <div class="admin-table-wrapper">
        <table class="admin-table knowledge-table">
            <thead><tr><th>Video</th><th>Transcripción</th><th>Índice</th><th>Chunks</th><th>Modelo</th><th>Última indexación</th></tr></thead>
            <tbody>
            <?php if(!$documentos): ?><tr><td colspan="6" class="tabla-vacia">No hay transcripciones completadas para estos filtros.</td></tr><?php else: foreach($documentos as $d): ?>
                <tr>
                    <td><strong><?php echo kbH($d['titulo']); ?></strong><small class="learning-table-sub"><?php echo kbH($d['categoria']); ?><?php echo !empty($d['serie'])?' · '.kbH($d['serie']):''; ?></small></td>
                    <td><strong><?php echo (int)$d['segmentos']; ?> segmentos</strong><small class="learning-table-sub"><?php echo kbH($d['transcripcion_actualizada']); ?></small></td>
                    <td><span class="knowledge-state is-<?php echo kbH($d['indice_estado']); ?>"><?php echo kbH(ucfirst($d['indice_estado'])); ?></span><?php if(!empty($d['mensaje_error'])):?><small class="knowledge-error"><?php echo kbH($d['mensaje_error']); ?></small><?php endif; ?></td>
                    <td><?php echo (int)($d['total_chunks'] ?? 0); ?></td>
                    <td><small><?php echo kbH($d['modelo_embedding'] ?: '—'); ?></small></td>
                    <td><?php echo $d['fecha_indexado']?kbH($d['fecha_indexado']):'—'; ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
</div>
<script>
(function(){
 const scope=document.getElementById('knowledgeScope');
 const target=document.getElementById('knowledgeScopeId');
 if(!scope||!target)return;
 const cursos=<?php echo json_encode(array_map(static fn($c)=>['id'=>(int)$c['id_curso'],'titulo'=>$c['titulo']],$cursos),JSON_UNESCAPED_UNICODE); ?>;
 const videos=<?php echo json_encode(array_map(static fn($v)=>['id'=>(int)$v['id_video'],'titulo'=>$v['titulo']],$videosIndexados),JSON_UNESCAPED_UNICODE); ?>;
 const render=()=>{
   const current=parseInt(target.dataset.current||'0',10);
   const source=scope.value==='curso'?cursos:(scope.value==='video'?videos:[]);
   target.innerHTML='<option value="0">'+(scope.value==='global'?'No aplica':'Seleccionar...')+'</option>';
   source.forEach(item=>{const o=document.createElement('option');o.value=item.id;o.textContent=item.titulo;if(item.id===current)o.selected=true;target.appendChild(o);});
   target.disabled=scope.value==='global';
 };
 scope.addEventListener('change',()=>{target.dataset.current='0';render();});render();
})();
</script>
</body>
</html>
