<?php
require_once '../../../config/sesion.php';
verificarAdmin();
require_once '../../../controllers/SkillController.php';
require_once '../../../includes/learning_helpers.php';

$skillsController = new SkillController();
$idUsuario = (int)($_GET['id_usuario'] ?? 0);
$perfil = $skillsController->perfilUsuarioAdmin($idUsuario);
if (!$perfil) { header('Location: reporte.php'); exit; }
$usuario = $perfil['usuario'];
$skills = $perfil['skills'];
$resumen = $perfil['resumen'];
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Skills de <?php echo learningH($usuario['nombre']); ?></title><link rel="stylesheet" href="../../../assets/css/admin.css"></head><body>
<?php include '../../includes/sidebar.php'; ?><div class="admin-main"><?php include '../../includes/navbar.php'; ?>
<section class="admin-content learning-admin-page skill-worker-page">
<div class="gestion-header"><div><span class="learning-admin-kicker">Perfil de competencias</span><h1><?php echo learningH($usuario['nombre']); ?></h1><p class="dashboard-subtitle"><?php echo learningH($usuario['email']); ?> · evidencia generada a partir de cursos, progreso y evaluaciones.</p></div><a class="btn-secundario" href="reporte.php">Volver al reporte</a></div>

<div class="skill-report-summary skill-worker-summary">
<article><span>Skills detectadas</span><strong><?php echo (int)$resumen['total_skills']; ?></strong><small>Competencias relacionadas con sus cursos.</small></article>
<article><span>Promedio plataforma</span><strong><?php echo round((float)$resumen['promedio'],1); ?>%</strong><small>Evidencia académica automática.</small></article>
<article><span>Evaluadas por supervisor</span><strong><?php echo (int)($resumen['evaluadas_supervisor'] ?? 0); ?></strong><small>Competencias con validación humana.</small></article>
<article><span>Skill destacada</span><strong class="skill-worker-highlight"><?php echo learningH($resumen['destacada_nombre'] ?: '—'); ?></strong><small><?php echo round((float)$resumen['destacada_porcentaje'],1); ?>% según TechFlix.</small></article>
</div>

<?php if(!$skills): ?><div class="tabla-vacia skill-empty-report">Este trabajador todavía no tiene Skills asociadas a sus cursos.</div><?php else: ?>
<div class="skill-worker-grid">
<?php foreach($skills as $s): $m=$s['evaluacion_supervisor'] ?? null; ?>
<article class="skill-worker-card">
    <div class="skill-worker-head"><span class="skill-worker-icon"><?php echo learningH($s['icono']); ?></span><div><span class="skill-ai-kicker"><?php echo learningH($s['categoria']); ?></span><h2><?php echo learningH($s['nombre']); ?></h2><small>Objetivo: <?php echo learningH($s['nivel_objetivo_texto']); ?></small></div><a class="btn-filtrar" href="evaluar.php?id_usuario=<?php echo $idUsuario; ?>&id_skill=<?php echo (int)$s['id_skill']; ?>">Evaluar</a></div>
    <div class="skill-worker-scores">
        <div><span>Resultado TechFlix</span><strong><?php echo round((float)$s['porcentaje'],1); ?>%</strong><small><?php echo learningH($s['nivel_texto']); ?></small></div>
        <div class="is-supervisor"><span>Supervisor</span><?php if($m): ?><strong><?php echo round((float)$m['puntaje'],1); ?>%</strong><small><?php echo ucfirst(learningH($m['nivel'])); ?> · <?php echo learningH($m['evaluador_nombre']); ?></small><?php else: ?><strong>—</strong><small>Sin evaluación manual</small><?php endif; ?></div>
    </div>
    <div class="skill-worker-evidence"><span>Evidencia de aprendizaje</span>
    <?php foreach($s['cursos'] as $c): ?><div><strong><?php echo learningH($c['curso']); ?></strong><small>Progreso <?php echo round((float)$c['progreso'],1); ?>%<?php if($c['tiene_evaluacion']): ?> · Nota <?php echo round((float)$c['mejor_nota'],1); ?>%<?php endif; ?> · Relevancia <?php echo round((float)$c['peso'],1); ?>%</small></div><?php endforeach; ?>
    </div>
    <?php if($m): ?><div class="skill-worker-supervisor-note"><strong>Última observación</strong><p><?php echo nl2br(learningH($m['comentario'])); ?></p><small><?php echo date('d/m/Y H:i', strtotime($m['fecha_evaluacion'])); ?></small></div><?php endif; ?>
</article>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section></div></body></html>
