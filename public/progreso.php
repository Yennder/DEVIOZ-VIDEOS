<?php
require_once '../config/sesion.php';
verificarSesion();
require_once '../controllers/LearningController.php';
require_once '../controllers/SkillController.php';
require_once '../controllers/VideoController.php';
require_once '../includes/learning_helpers.php';

$learning = new LearningController();
$skillController = new SkillController();
$videoController = new VideoController();
$categorias = $videoController->listarCategorias();
$id = (int)$_SESSION['id_usuario'];
$r = $learning->resumenUsuario($id);
$asignaciones = $learning->misAsignaciones($id);
$perfilSkills = $skillController->perfilUsuario($id);
$skillsResumen = $perfilSkills['resumen'] ?? [];
$skillsPreview = array_slice($perfilSkills['skills'] ?? [], 0, 4);
?>
<?php include '../includes/public_header.php'; ?>
<?php include '../includes/public_navbar.php'; ?>
<div class="layout">
<?php include '../includes/public_sidebar.php'; ?>
<main class="public-content learning-public-page">
    <section class="page-hero-compact">
        <span class="section-kicker">Tech Profile</span>
        <h1>Mi progreso</h1>
        <p>Resumen de cumplimiento, evaluaciones y formación tecnológica.</p>
    </section>

    <div class="learning-summary-grid learning-summary-grid-six">
        <article><span>🎓</span><strong><?php echo $r['asignadas']; ?></strong><small>Asignadas</small></article>
        <article><span>✅</span><strong><?php echo $r['completadas']; ?></strong><small>Completadas</small></article>
        <article><span>▶</span><strong><?php echo $r['en_progreso']; ?></strong><small>En progreso</small></article>
        <article><span>📝</span><strong><?php echo $r['intentos']; ?></strong><small>Evaluaciones</small></article>
        <article><span>📈</span><strong><?php echo $r['promedio']; ?>%</strong><small>Promedio</small></article>
        <article><span>🏅</span><strong><?php echo $r['logros']; ?></strong><small>Logros</small></article>
    </div>

    <section class="content-section skill-progress-preview-section">
        <div class="section-heading-row">
            <div>
                <span class="section-kicker">Competencias</span>
                <h2>Skills que estoy desarrollando</h2>
                <p>Estimación basada en el avance de tus cursos y resultados de evaluación.</p>
            </div>
            <a class="btn-secondary-modern" href="skills.php">Ver perfil tecnológico</a>
        </div>
        <?php if(empty($skillsPreview)): ?>
            <div class="empty-state empty-state-small"><h3>Aún no hay skills calculadas</h3><p>Se mostrarán cuando tus cursos tengan competencias asociadas.</p></div>
        <?php else: ?>
            <div class="skill-progress-preview-grid">
                <?php foreach($skillsPreview as $skill): ?>
                    <a href="skills.php#mis-skills" class="skill-progress-preview-card">
                        <div class="skill-progress-preview-head"><span><?php echo learningH($skill['icono']); ?></span><div><strong><?php echo learningH($skill['nombre']); ?></strong><small><?php echo learningH($skill['nivel_texto']); ?></small></div><b><?php echo (float)$skill['porcentaje']; ?>%</b></div>
                        <div class="skill-profile-progress"><i style="width:<?php echo min(100,max(0,(float)$skill['porcentaje'])); ?>%"></i></div>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="skill-progress-preview-footer">
                <span><?php echo (int)($skillsResumen['skills_en_desarrollo'] ?? 0); ?> skills con avance</span>
                <span>Promedio: <b><?php echo (float)($skillsResumen['promedio'] ?? 0); ?>%</b></span>
            </div>
        <?php endif; ?>
    </section>

    <section class="content-section">
        <div class="section-heading-row"><div><span class="section-kicker">Historial de formación</span><h2>Mis cursos asignados</h2></div></div>
        <?php if(!$asignaciones): ?>
            <div class="empty-state">No tienes actividad de aprendizaje todavía.</div>
        <?php else: ?>
            <div class="learning-progress-history">
                <?php foreach($asignaciones as $a): ?>
                    <a href="curso.php?asignacion=<?php echo (int)$a['id_asignacion']; ?>" class="learning-progress-history-row">
                        <div><strong><?php echo learningH($a['curso']); ?></strong><small><?php echo learningH($a['capacitacion']); ?> · límite <?php echo learningH($a['fecha_limite']); ?></small></div>
                        <div class="learning-progress-inline"><span><i style="width:<?php echo min(100,(float)$a['porcentaje']); ?>%"></i></span><b><?php echo (float)$a['porcentaje']; ?>%</b></div>
                        <span class="learning-badge <?php echo learningEstadoClase($a['estado']); ?>"><?php echo learningEstadoTexto($a['estado']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
</div>
<?php include '../includes/public_footer.php'; ?>
