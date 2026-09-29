<?php
require_once '../config/sesion.php';
require_once '../controllers/BusquedaController.php';

$busqueda = new BusquedaController();
$q = trim((string)($_GET['q'] ?? $_GET['buscar'] ?? ''));
$tipoRaw = (string)($_GET['tipo'] ?? 'todos');
$tipo = in_array($tipoRaw, ['todos','videos','series','cursos','contenido'], true) ? $tipoRaw : 'todos';
$scopeRaw = (string)($_GET['scope'] ?? 'global');
$scope = in_array($scopeRaw, ['global','curso','serie','video'], true) ? $scopeRaw : 'global';
$scopeId = isset($_GET['scope_id']) && ctype_digit((string)$_GET['scope_id']) ? (int)$_GET['scope_id'] : 0;
if ($scope !== 'global') {
    $altKey = 'scope_id_' . $scope;
    if (isset($_GET[$altKey]) && ctype_digit((string)$_GET[$altKey])) $scopeId = (int)$_GET[$altKey];
    if ($scopeId <= 0) $scope = 'global';
}

$videos = [];
$series = [];
$cursos = [];

if ($q !== '') {
    if (in_array($tipo, ['todos','videos'], true)) $videos = $busqueda->videos($q, 12);
    if (in_array($tipo, ['todos','series'], true)) $series = $busqueda->series($q, 8);
    if (usuarioAutenticado() && in_array($tipo, ['todos','cursos'], true)) $cursos = $busqueda->cursos($q, 8);

}

$cursosScope = usuarioAutenticado() ? $busqueda->cursosParaScope() : [];
$seriesScope = $busqueda->seriesParaScope();
$videosScope = $busqueda->videosParaScope();
$baseTotal = count($videos)+count($series)+count($cursos);
$total = $baseTotal;

function bH($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function bTime($seconds): string {
    $s=max(0,(int)round((float)$seconds)); $h=intdiv($s,3600); $m=intdiv($s%3600,60); $r=$s%60;
    return $h>0?sprintf('%02d:%02d:%02d',$h,$m,$r):sprintf('%02d:%02d',$m,$r);
}
function bUrl(array $changes=[]): string {
    $base=$_GET; foreach($changes as $k=>$v){ if($v===null||$v==='') unset($base[$k]); else $base[$k]=$v; }
    return 'buscar.php?'.http_build_query($base);
}
?>
<?php include '../includes/public_header.php'; ?>
<?php include '../includes/public_navbar.php'; ?>
<div class="layout">
<?php include '../includes/public_sidebar.php'; ?>
<main class="public-content smart-search-page">
    <section class="smart-search-hero">
        <div>
            <span class="section-kicker">Búsqueda inteligente</span>
            <h1>Encuentra contenido por título o por significado.</h1>
            <p>Busca videos, series, cursos y momentos exactos dentro de las transcripciones indexadas.</p>
        </div>
        <?php if($q!==''): ?><div class="smart-search-count"><strong id="smartSearchCountValue" data-base-count="<?php echo (int)$baseTotal; ?>"><?php echo (int)$baseTotal; ?></strong><span>resultados encontrados</span></div><?php endif; ?>
    </section>

    <section class="smart-search-panel">
        <form method="GET" class="smart-search-main-form">
            <div class="smart-search-input-wrap"><span>⌕</span><input type="search" name="q" value="<?php echo bH($q); ?>" placeholder="Ej. ¿Dónde explican Docker Hub?" autofocus><button type="submit">Buscar</button></div>
            <input type="hidden" name="tipo" value="<?php echo bH($tipo); ?>">
            <input type="hidden" name="scope" value="<?php echo bH($scope); ?>">
            <?php if($scopeId>0): ?><input type="hidden" name="scope_id" value="<?php echo (int)$scopeId; ?>"><?php endif; ?>
        </form>
        <?php if($q!==''): ?>
        <div class="smart-search-tabs" aria-label="Tipos de resultado">
            <?php foreach(['todos'=>'Todo','videos'=>'Videos','series'=>'Series','cursos'=>'Cursos','contenido'=>'Dentro del contenido'] as $key=>$label): ?>
                <?php if($key==='cursos' && !usuarioAutenticado()) continue; ?>
                <a class="<?php echo $tipo===$key?'is-active':''; ?>" href="<?php echo bH(bUrl(['tipo'=>$key])); ?>"><?php echo bH($label); ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <?php if($q===''): ?>
        <section class="smart-search-empty-intro">
            <div>🧠</div><h2>Prueba una búsqueda por concepto</h2>
            <p>No necesitas escribir exactamente las palabras del video. TechFlix puede relacionar conceptos por significado.</p>
            <div class="smart-search-examples"><a href="buscar.php?q=%C2%BFQu%C3%A9+es+Docker%3F">¿Qué es Docker?</a><a href="buscar.php?q=%C2%BFC%C3%B3mo+puedo+aislar+aplicaciones%3F">¿Cómo puedo aislar aplicaciones?</a><a href="buscar.php?q=Docker+Hub">Docker Hub</a></div>
        </section>
    <?php else: ?>

        <?php if(in_array($tipo,['todos','contenido'],true)): ?>
        <section class="content-section smart-semantic-section">
            <div class="section-heading-row smart-search-heading"><div><span class="section-kicker">Índice semántico</span><h2>Coincidencias dentro de los videos</h2><p>Fragmentos recuperados desde las transcripciones según su relación con tu búsqueda.</p></div></div>
            <form method="GET" class="smart-scope-form" id="smartScopeForm">
                <input type="hidden" name="q" value="<?php echo bH($q); ?>"><input type="hidden" name="tipo" value="<?php echo bH($tipo); ?>">
                <label>Buscar dentro de<select name="scope" id="smartSearchScope"><option value="global" <?php echo $scope==='global'?'selected':''; ?>>Todo TechFlix</option><?php if(usuarioAutenticado()): ?><option value="curso" <?php echo $scope==='curso'?'selected':''; ?>>Un curso</option><?php endif; ?><option value="serie" <?php echo $scope==='serie'?'selected':''; ?>>Una serie</option><option value="video" <?php echo $scope==='video'?'selected':''; ?>>Un video</option></select></label>
                <label class="smart-scope-target <?php echo $scope==='curso'?'is-visible':''; ?>" data-scope-target="curso">Curso<select name="scope_id_curso"><option value="">Seleccionar curso</option><?php foreach($cursosScope as $c): ?><option value="<?php echo (int)$c['id_curso']; ?>" <?php echo $scope==='curso'&&$scopeId===(int)$c['id_curso']?'selected':''; ?>><?php echo bH($c['titulo']); ?></option><?php endforeach; ?></select></label>
                <label class="smart-scope-target <?php echo $scope==='serie'?'is-visible':''; ?>" data-scope-target="serie">Serie<select name="scope_id_serie"><option value="">Seleccionar serie</option><?php foreach($seriesScope as $s): ?><option value="<?php echo (int)$s['id_serie']; ?>" <?php echo $scope==='serie'&&$scopeId===(int)$s['id_serie']?'selected':''; ?>><?php echo bH($s['titulo']); ?></option><?php endforeach; ?></select></label>
                <label class="smart-scope-target <?php echo $scope==='video'?'is-visible':''; ?>" data-scope-target="video">Video<select name="scope_id_video"><option value="">Seleccionar video</option><?php foreach($videosScope as $v): ?><option value="<?php echo (int)$v['id_video']; ?>" <?php echo $scope==='video'&&$scopeId===(int)$v['id_video']?'selected':''; ?>><?php echo bH($v['titulo']); ?></option><?php endforeach; ?></select></label>
                <input type="hidden" name="scope_id" id="smartScopeId" value="<?php echo (int)$scopeId; ?>">
                <button type="submit">Aplicar</button>
            </form>
            <div id="smartSemanticResults" class="smart-semantic-results"
                 data-query="<?php echo bH($q); ?>"
                 data-scope="<?php echo bH($scope); ?>"
                 data-scope-id="<?php echo (int)$scopeId; ?>">
                <div class="smart-semantic-loading" role="status" aria-live="polite">
                    <span class="smart-semantic-spinner" aria-hidden="true"></span>
                    <div><strong>Buscando por significado...</strong><small>Los demás resultados ya están disponibles mientras el motor semántico trabaja.</small></div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php if(in_array($tipo,['todos','videos'],true) && !empty($videos)): ?>
        <section class="content-section"><div class="section-heading-row"><div><span class="section-kicker">Videos</span><h2>Coincidencias por información del contenido</h2></div><a class="btn-secondary-modern" href="<?php echo bH(bUrl(['tipo'=>'videos'])); ?>">Ver solo videos</a></div><div class="grid-videos"><?php foreach($videos as $cardVideo): include '../includes/video_card.php'; endforeach; ?></div></section>
        <?php endif; ?>

        <?php if(in_array($tipo,['todos','series'],true) && !empty($series)): ?>
        <section class="content-section"><div class="section-heading-row"><div><span class="section-kicker">Series</span><h2>Series relacionadas</h2></div></div><div class="smart-entity-grid"><?php foreach($series as $s): ?><article class="smart-entity-card"><span class="smart-entity-icon">▣</span><div><small>SERIE</small><h3><?php echo bH($s['titulo']); ?></h3><p><?php echo bH(mb_strimwidth((string)($s['descripcion']??''),0,180,'…')); ?></p><div class="smart-entity-meta"><?php echo (int)$s['total_temporadas']; ?> temporadas · <?php echo (int)$s['total_capitulos']; ?> capítulos</div><a href="detalle_serie.php?id=<?php echo (int)$s['id_serie']; ?>">Ver serie →</a></div></article><?php endforeach; ?></div></section>
        <?php endif; ?>

        <?php if(usuarioAutenticado() && in_array($tipo,['todos','cursos'],true) && !empty($cursos)): ?>
        <section class="content-section" id="catalogo-cursos"><div class="section-heading-row"><div><span class="section-kicker">Learning Lab</span><h2>Cursos relacionados</h2></div></div><div class="smart-entity-grid"><?php foreach($cursos as $c): ?><article class="smart-entity-card"><span class="smart-entity-icon">🎓</span><div><small>CURSO · <?php echo bH(strtoupper((string)$c['nivel'])); ?></small><h3><?php echo bH($c['titulo']); ?></h3><p><?php echo bH(mb_strimwidth((string)($c['descripcion']??''),0,180,'…')); ?></p><div class="smart-entity-meta"><?php echo (int)$c['total_lecciones']; ?> lecciones · <?php echo (int)$c['duracion_estimada_minutos']; ?> min aprox.</div><a href="aprendizaje.php">Ir a Aprendizaje →</a></div></article><?php endforeach; ?></div></section>
        <?php endif; ?>

        <?php if(in_array($tipo,['todos','contenido'],true)): ?>
            <div class="empty-state" id="smartSearchGlobalEmpty" hidden><span class="empty-icon">⌕</span><h3>No encontramos resultados</h3><p>Prueba con otra palabra, una pregunta más general o cambia el ámbito de búsqueda.</p></div>
        <?php elseif($total===0): ?>
            <div class="empty-state"><span class="empty-icon">⌕</span><h3>No encontramos resultados</h3><p>Prueba con otra palabra o cambia el tipo de resultado.</p></div>
        <?php endif; ?>
    <?php endif; ?>
</main></div>
<?php include '../includes/public_footer.php'; ?>
