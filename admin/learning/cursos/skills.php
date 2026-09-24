<?php
require_once '../../../config/sesion.php'; verificarAdmin();
require_once '../../../controllers/LearningController.php';
require_once '../../../controllers/SkillController.php';
require_once '../../../includes/learning_helpers.php';

$learning = new LearningController();
$skillsController = new SkillController();
$idCurso = (int)($_GET['id'] ?? $_POST['id_curso'] ?? 0);
$curso = $learning->buscarCurso($idCurso);
if (!$curso) { header('Location: listar.php'); exit; }

$error = '';
$analisis = null;
$accion = (string)($_POST['accion'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrfPost();
    try {
        if ($accion === 'analizar_ia') {
            $analisis = $skillsController->analizarCursoIA($idCurso);
        } elseif ($accion === 'aplicar_ia') {
            $skillsController->guardarSugerenciasIA(
                $idCurso,
                (array)($_POST['sugerencias'] ?? []),
                (string)($_POST['provider'] ?? ''),
                (string)($_POST['model'] ?? '')
            );
            header('Location: skills.php?id=' . $idCurso . '&ok=ia');
            exit;
        } else {
            $skillsController->guardarSkillsCurso(
                $idCurso,
                $_POST['skills'] ?? [],
                $_POST['peso'] ?? [],
                $_POST['nivel'] ?? []
            );
            header('Location: skills.php?id=' . $idCurso . '&ok=manual');
            exit;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$skills = $skillsController->skillsCurso($idCurso);
$contextoIA = $skillsController->contextoCursoIA($idCurso);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error !== '' && $accion === '') {
    $seleccionadas = array_fill_keys(array_map('intval', $_POST['skills'] ?? []), true);
    foreach ($skills as &$s) {
        $id = (int)$s['id_skill'];
        $s['asignada'] = isset($seleccionadas[$id]) ? 1 : 0;
        $s['peso'] = $_POST['peso'][$id] ?? $s['peso'];
        $s['nivel_objetivo'] = $_POST['nivel'][$id] ?? $s['nivel_objetivo'];
    }
    unset($s);
}

$totalLecciones = (int)($contextoIA['total_lecciones'] ?? 0);
$transcritas = (int)($contextoIA['lecciones_transcritas'] ?? 0);
$sinTranscribir = (int)($contextoIA['lecciones_sin_transcribir'] ?? 0);
$cobertura = $totalLecciones > 0 ? (int)round(($transcritas / $totalLecciones) * 100) : 0;
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Skills del curso - Learning Lab</title><link rel="stylesheet" href="../../../assets/css/admin.css"></head><body>
<?php include '../../includes/sidebar.php'; ?><div class="admin-main"><?php include '../../includes/navbar.php'; ?><section class="admin-content learning-admin-page">
<div class="gestion-header"><div><span class="learning-admin-kicker">Competencias del curso</span><h1><?php echo learningH($curso['titulo']); ?></h1><p class="dashboard-subtitle">Puedes asignar las skills manualmente o pedir a DEVIOZ AI que las detecte a partir de las transcripciones del curso.</p></div><div class="learning-actions"><a class="btn-secundario" href="editar.php?id=<?php echo $idCurso; ?>">Editar curso</a><a class="btn-limpiar" href="listar.php">Volver</a></div></div>

<?php if(($_GET['ok'] ?? '') === 'manual'): ?><div class="mensaje-exito">Skills del curso actualizadas manualmente.</div><?php endif; ?>
<?php if(($_GET['ok'] ?? '') === 'ia'): ?><div class="mensaje-exito">Sugerencias de DEVIOZ AI aplicadas correctamente. Puedes ajustarlas manualmente cuando quieras.</div><?php endif; ?>
<?php if($error): ?><div class="mensaje-error"><?php echo learningH($error); ?></div><?php endif; ?>

<section class="skill-ai-panel">
    <div class="skill-ai-head">
        <div>
            <span class="skill-ai-kicker">✨ Detección automática</span>
            <h2>Detectar skills con DEVIOZ AI</h2>
            <p>Analiza las transcripciones de las lecciones y propone competencias, pesos y nivel objetivo. La IA no publica nada por sí sola: tú revisas y apruebas el resultado.</p>
        </div>
        <form method="POST" class="skill-ai-action-form">
            <?php echo csrfInput(); ?>
            <input type="hidden" name="id_curso" value="<?php echo $idCurso; ?>">
            <input type="hidden" name="accion" value="analizar_ia">
            <button class="btn-crear" type="submit" <?php echo $transcritas===0?'disabled':''; ?>>✨ Analizar transcripciones</button>
        </form>
    </div>
    <div class="skill-ai-coverage">
        <div><span>Lecciones</span><strong><?php echo $totalLecciones; ?></strong></div>
        <div><span>Transcritas</span><strong><?php echo $transcritas; ?></strong></div>
        <div><span>Pendientes</span><strong><?php echo $sinTranscribir; ?></strong></div>
        <div class="skill-ai-coverage-bar"><span>Cobertura para análisis</span><div><i style="width:<?php echo max(0,min(100,$cobertura)); ?>%"></i></div><strong><?php echo $cobertura; ?>%</strong></div>
    </div>
    <?php if($totalLecciones===0): ?><div class="skill-ai-note is-warning">Este curso todavía no tiene lecciones. Agrega videos antes de detectar competencias.</div>
    <?php elseif($transcritas===0): ?><div class="skill-ai-note is-warning">Ninguna lección está transcrita. Completa al menos una transcripción para usar la detección automática.</div>
    <?php elseif($sinTranscribir>0): ?><div class="skill-ai-note">La IA analizará las <?php echo $transcritas; ?> lecciones disponibles. Para un resultado más completo, puedes transcribir las <?php echo $sinTranscribir; ?> pendientes.</div>
    <?php else: ?><div class="skill-ai-note is-success">Todas las lecciones están transcritas. El curso está listo para un análisis completo.</div><?php endif; ?>
</section>

<?php if($analisis): ?>
<section class="skill-ai-results">
    <div class="skill-ai-results-head">
        <div><span class="learning-admin-kicker">Propuesta de IA</span><h2>Skills detectadas</h2><p>Revisa la propuesta antes de guardarla. Puedes cambiar pesos, niveles o desmarcar cualquier skill.</p></div>
        <div class="skill-ai-provider"><span>Respuesta generada con</span><strong><?php echo learningH(strtoupper((string)$analisis['provider'])); ?></strong><small><?php echo learningH((string)$analisis['model']); ?></small></div>
    </div>
    <?php if(!empty($analisis['observaciones'])): ?><div class="skill-ai-observation"><?php echo learningH($analisis['observaciones']); ?></div><?php endif; ?>
    <form method="POST" class="skill-ai-suggestions" id="skillAiSuggestionsForm">
        <?php echo csrfInput(); ?>
        <input type="hidden" name="id_curso" value="<?php echo $idCurso; ?>">
        <input type="hidden" name="accion" value="aplicar_ia">
        <input type="hidden" name="provider" value="<?php echo learningH((string)$analisis['provider']); ?>">
        <input type="hidden" name="model" value="<?php echo learningH((string)$analisis['model']); ?>">
        <div class="skill-ai-suggestion-grid">
        <?php foreach($analisis['skills'] as $i=>$s): ?>
            <article class="skill-ai-suggestion is-selected" data-ai-suggestion>
                <div class="skill-ai-suggestion-top">
                    <label class="skill-ai-select"><input type="checkbox" name="sugerencias[<?php echo $i; ?>][seleccionada]" value="1" checked data-ai-check><span class="skill-icon"><?php echo learningH($s['icono']); ?></span><span><strong><?php echo learningH($s['nombre']); ?></strong><small><?php echo learningH($s['categoria']); ?></small></span></label>
                    <span class="learning-badge <?php echo !empty($s['es_nueva'])?'is-warning':'is-success'; ?>"><?php echo !empty($s['es_nueva'])?'Nueva sugerida':'Catálogo'; ?></span>
                </div>
                <input type="hidden" name="sugerencias[<?php echo $i; ?>][id_skill]" value="<?php echo (int)$s['id_skill']; ?>">
                <input type="hidden" name="sugerencias[<?php echo $i; ?>][nombre]" value="<?php echo learningH($s['nombre']); ?>">
                <input type="hidden" name="sugerencias[<?php echo $i; ?>][categoria]" value="<?php echo learningH($s['categoria']); ?>">
                <input type="hidden" name="sugerencias[<?php echo $i; ?>][descripcion]" value="<?php echo learningH($s['descripcion']); ?>">
                <input type="hidden" name="sugerencias[<?php echo $i; ?>][icono]" value="<?php echo learningH($s['icono']); ?>">
                <input type="hidden" name="sugerencias[<?php echo $i; ?>][confianza]" value="<?php echo learningH($s['confianza']); ?>">
                <input type="hidden" name="sugerencias[<?php echo $i; ?>][justificacion]" value="<?php echo learningH($s['justificacion']); ?>">
                <div class="skill-ai-confidence"><span>Confianza IA</span><div><i style="width:<?php echo max(0,min(100,(float)$s['confianza'])); ?>%"></i></div><strong><?php echo (float)$s['confianza']; ?>%</strong></div>
                <p class="skill-ai-reason"><?php echo learningH($s['justificacion'] ?: 'La IA identificó esta competencia a partir del contenido transcrito.'); ?></p>
                <div class="skill-course-fields">
                    <label>Peso (%)<input type="number" name="sugerencias[<?php echo $i; ?>][peso]" min="0.01" max="100" step="0.01" value="<?php echo learningH($s['peso']); ?>" data-ai-weight></label>
                    <label>Nivel objetivo<select name="sugerencias[<?php echo $i; ?>][nivel_objetivo]"><?php foreach(['basico'=>'Básico','intermedio'=>'Intermedio','avanzado'=>'Avanzado'] as $k=>$v): ?><option value="<?php echo $k; ?>" <?php echo $s['nivel_objetivo']===$k?'selected':''; ?>><?php echo $v; ?></option><?php endforeach; ?></select></label>
                </div>
                <?php if(!empty($s['es_nueva'])): ?><small class="skill-ai-new-help">Si apruebas esta sugerencia, también se creará automáticamente en el catálogo de Skills.</small><?php endif; ?>
            </article>
        <?php endforeach; ?>
        </div>
        <div class="skill-ai-apply-bar"><div><span>Total seleccionado</span><strong id="skillAiTotal">100%</strong><small>Debe sumar exactamente 100% antes de aplicar.</small></div><button class="btn-crear" type="submit">✓ Aprobar y aplicar sugerencias</button></div>
    </form>
</section>
<?php endif; ?>

<div class="skill-manual-divider"><span>Asignación manual</span><p>También puedes crear y ajustar las competencias directamente. Guardar manualmente reemplaza la distribución actual.</p></div>

<?php if(!$skills): ?><div class="admin-empty-state">No hay skills creadas todavía. Puedes <a href="../skills/crear.php">crear una manualmente</a> o usar la detección automática de arriba.</div><?php else: ?>
<form method="POST" class="skill-course-form"><?php echo csrfInput(); ?><input type="hidden" name="id_curso" value="<?php echo $idCurso; ?>">
<div class="skill-course-toolbar"><div><strong>Distribución de competencias</strong><small>Marca solo las competencias que este curso realmente desarrolla.</small></div><div class="skill-weight-total" id="skillWeightTotal"><span>Total</span><b>0%</b></div></div>
<div class="skill-course-grid">
<?php foreach($skills as $s): $id=(int)$s['id_skill']; $asignada=!empty($s['asignada']); ?>
<article class="skill-course-card <?php echo $asignada?'is-selected':''; ?>" data-skill-card>
<label class="skill-course-main"><input type="checkbox" name="skills[]" value="<?php echo $id; ?>" <?php echo $asignada?'checked':''; ?> data-skill-check><span class="skill-icon"><?php echo learningH($s['icono']); ?></span><span><strong><?php echo learningH($s['nombre']); ?></strong><small><?php echo learningH($s['categoria']); ?> · <?php echo learningH($s['codigo']); ?></small></span></label>
<div class="skill-course-fields"><label>Peso (%)<input type="number" name="peso[<?php echo $id; ?>]" min="0" max="100" step="0.01" value="<?php echo $asignada?learningH($s['peso']):''; ?>" data-skill-weight <?php echo $asignada?'':'disabled'; ?>></label><label>Nivel objetivo<select name="nivel[<?php echo $id; ?>]" data-skill-level <?php echo $asignada?'':'disabled'; ?>><?php foreach(['basico'=>'Básico','intermedio'=>'Intermedio','avanzado'=>'Avanzado'] as $k=>$v): ?><option value="<?php echo $k; ?>" <?php echo ($s['nivel_objetivo']??'basico')===$k?'selected':''; ?>><?php echo $v; ?></option><?php endforeach; ?></select></label></div>
<?php if($asignada && ($s['origen']??'')==='ia'): ?><div class="skill-ai-origin"><span>✨ Detectada por IA</span><?php if($s['confianza_ia']!==null): ?><b><?php echo (float)$s['confianza_ia']; ?>% confianza</b><?php endif; ?><?php if(!empty($s['justificacion_ia'])): ?><small><?php echo learningH($s['justificacion_ia']); ?></small><?php endif; ?></div><?php endif; ?>
<?php if(empty($s['estado'])): ?><span class="learning-badge is-muted skill-inactive-badge">Inactiva</span><?php endif; ?>
</article>
<?php endforeach; ?>
</div>
<div class="skill-course-footer"><p>Ejemplo: Docker 70% + Linux 20% + DevOps 10% = 100%.</p><button class="btn-crear" type="submit">Guardar skills manualmente</button></div>
</form>
<?php endif; ?>
</section></div>
<script>
(function(){
  const cards=[...document.querySelectorAll('[data-skill-card]')];
  const totalBox=document.getElementById('skillWeightTotal');
  function refreshManual(){
    if(!totalBox) return;
    let total=0;
    cards.forEach(card=>{
      const check=card.querySelector('[data-skill-check]');
      const weight=card.querySelector('[data-skill-weight]');
      const level=card.querySelector('[data-skill-level]');
      const on=check.checked;
      card.classList.toggle('is-selected',on);
      weight.disabled=!on;
      level.disabled=!on;
      if(on) total+=parseFloat(weight.value||0)||0;
    });
    total=Math.round(total*100)/100;
    totalBox.querySelector('b').textContent=total+'%';
    totalBox.classList.toggle('is-valid',Math.abs(total-100)<0.01);
    totalBox.classList.toggle('is-warning',total>0&&Math.abs(total-100)>=0.01);
  }
  cards.forEach(card=>{
    card.querySelector('[data-skill-check]').addEventListener('change',()=>{
      const weight=card.querySelector('[data-skill-weight]');
      if(card.querySelector('[data-skill-check]').checked && !weight.value) weight.value='100';
      refreshManual();
    });
    card.querySelector('[data-skill-weight]').addEventListener('input',refreshManual);
  });
  refreshManual();

  const aiCards=[...document.querySelectorAll('[data-ai-suggestion]')];
  const aiTotal=document.getElementById('skillAiTotal');
  function refreshAI(){
    if(!aiTotal) return;
    let total=0;
    aiCards.forEach(card=>{
      const check=card.querySelector('[data-ai-check]');
      const weight=card.querySelector('[data-ai-weight]');
      const on=check.checked;
      card.classList.toggle('is-selected',on);
      weight.disabled=!on;
      if(on) total+=parseFloat(weight.value||0)||0;
    });
    total=Math.round(total*100)/100;
    aiTotal.textContent=total+'%';
    aiTotal.parentElement.classList.toggle('is-valid',Math.abs(total-100)<0.01);
    aiTotal.parentElement.classList.toggle('is-warning',Math.abs(total-100)>=0.01);
  }
  aiCards.forEach(card=>{
    card.querySelector('[data-ai-check]').addEventListener('change',refreshAI);
    card.querySelector('[data-ai-weight]').addEventListener('input',refreshAI);
  });
  refreshAI();
})();
</script>
</body></html>
