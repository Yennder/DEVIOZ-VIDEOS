<?php
require_once '../config/sesion.php';
verificarSesion();
require_once '../controllers/SkillController.php';
require_once '../controllers/VideoController.php';
require_once '../includes/learning_helpers.php';

$skillController = new SkillController();
$videoController = new VideoController();
$categorias = $videoController->listarCategorias();
$idUsuario = (int)$_SESSION['id_usuario'];
$perfil = $skillController->perfilUsuario($idUsuario);
$skills = $perfil['skills'] ?? [];
$resumen = $perfil['resumen'] ?? [];
?>
<?php include '../includes/public_header.php'; ?>
<?php include '../includes/public_navbar.php'; ?>
<div class="layout">
<?php include '../includes/public_sidebar.php'; ?>
<main class="public-content learning-public-page skill-profile-page">
    <section class="learning-hero skill-profile-hero">
        <div>
            <span class="section-kicker">Tech Profile</span>
            <h1>Mi perfil tecnológico</h1>
            <p>Visualiza las competencias que estás desarrollando a partir de tus cursos, progreso y evaluaciones.</p>
            <div class="learning-hero-actions">
                <a class="btn-primary-modern" href="#mis-skills">Ver mis skills</a>
                <a class="btn-secondary-modern" href="aprendizaje.php">Mi aprendizaje</a>
            </div>
        </div>
        <div class="learning-hero-score skill-profile-score">
            <span>Promedio de desarrollo</span>
            <strong><?php echo (float)($resumen['promedio'] ?? 0); ?>%</strong>
            <small><?php echo (int)($resumen['skills_en_desarrollo'] ?? 0); ?> de <?php echo (int)($resumen['total_skills'] ?? 0); ?> skills con avance</small>
        </div>
    </section>

    <div class="learning-summary-grid skill-summary-grid">
        <article><span>🧩</span><strong><?php echo (int)($resumen['total_skills'] ?? 0); ?></strong><small>Skills relacionadas</small></article>
        <article><span>📈</span><strong><?php echo (float)($resumen['promedio'] ?? 0); ?>%</strong><small>Promedio de desarrollo</small></article>
        <article><span>📚</span><strong><?php echo (int)($resumen['cursos_con_skills'] ?? 0); ?></strong><small>Cursos con competencias</small></article>
        <article><span>⭐</span><strong class="skill-summary-name"><?php echo learningH($resumen['destacada_nombre'] ?: 'Aún sin datos'); ?></strong><small><?php echo !empty($resumen['destacada_nombre']) ? (float)$resumen['destacada_porcentaje'].'% destacada' : 'Completa una formación'; ?></small></article>
    </div>

    <section class="content-section" id="mis-skills">
        <div class="section-heading-row">
            <div>
                <span class="section-kicker">Competencias en desarrollo</span>
                <h2>Mis skills</h2>
                <p>El porcentaje representa evidencia de aprendizaje obtenida en los cursos que tienes asignados.</p>
            </div>
        </div>

        <?php if(empty($skills)): ?>
            <div class="empty-state skill-empty-state">
                <span class="empty-icon">🧩</span>
                <h3>Aún no tienes skills calculadas</h3>
                <p>Las competencias aparecerán cuando tus cursos tengan skills asociadas y cuentes con una capacitación asignada.</p>
                <a class="btn-primary-modern" href="aprendizaje.php">Ir a mi aprendizaje</a>
            </div>
        <?php else: ?>
            <div class="skill-profile-grid">
                <?php foreach($skills as $skill): ?>
                <article class="skill-profile-card skill-level-<?php echo learningH($skill['nivel']); ?>">
                    <div class="skill-profile-card-head">
                        <span class="skill-profile-icon"><?php echo learningH($skill['icono']); ?></span>
                        <div>
                            <span class="skill-profile-category"><?php echo learningH($skill['categoria']); ?></span>
                            <h3><?php echo learningH($skill['nombre']); ?></h3>
                        </div>
                        <span class="skill-profile-level"><?php echo learningH($skill['nivel_texto']); ?></span>
                    </div>
                    <?php if(!empty($skill['descripcion'])): ?><p class="skill-profile-description"><?php echo learningH($skill['descripcion']); ?></p><?php endif; ?>
                    <div class="skill-profile-progress-row"><span>Desarrollo estimado</span><strong><?php echo (float)$skill['porcentaje']; ?>%</strong></div>
                    <div class="skill-profile-progress"><i style="width:<?php echo min(100,max(0,(float)$skill['porcentaje'])); ?>%"></i></div>
                    <div class="skill-profile-meta">
                        <span><b><?php echo (int)$skill['cursos_asociados']; ?></b> curso<?php echo (int)$skill['cursos_asociados']===1?'':'s'; ?></span>
                        <span><b><?php echo (int)$skill['cursos_completados']; ?></b> completado<?php echo (int)$skill['cursos_completados']===1?'':'s'; ?></span>
                        <span>Objetivo: <b><?php echo learningH($skill['nivel_objetivo_texto']); ?></b></span>
                    </div>
                    <?php if(!empty($skill['cursos'])): ?>
                    <details class="skill-evidence-details">
                        <summary>Ver evidencia de aprendizaje</summary>
                        <div class="skill-evidence-list">
                            <?php foreach($skill['cursos'] as $curso): ?>
                            <div class="skill-evidence-item">
                                <div><strong><?php echo learningH($curso['curso']); ?></strong><small><?php echo learningH($curso['capacitacion']); ?></small></div>
                                <div class="skill-evidence-values">
                                    <span>Curso <b><?php echo (float)$curso['puntaje_curso']; ?>%</b></span>
                                    <span>Progreso <b><?php echo (float)$curso['progreso']; ?>%</b></span>
                                    <?php if($curso['tiene_evaluacion']): ?><span>Mejor nota <b><?php echo (float)$curso['mejor_nota']; ?>%</b></span><?php endif; ?>
                                    <span>Relevancia <b><?php echo (float)$curso['peso']; ?>%</b></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </details>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="content-section skill-method-section">
        <div class="section-heading-row"><div><span class="section-kicker">Cálculo transparente</span><h2>¿Cómo se obtiene mi porcentaje?</h2></div></div>
        <div class="skill-method-grid">
            <article><span>▶</span><div><strong>40% Progreso</strong><p>Avance real de las lecciones obligatorias del curso.</p></div></article>
            <article><span>📝</span><div><strong>40% Evaluación</strong><p>Se utiliza tu mejor nota cuando el curso tiene una evaluación publicada.</p></div></article>
            <article><span>✅</span><div><strong>20% Finalización</strong><p>Se reconoce cuando completas satisfactoriamente la capacitación.</p></div></article>
        </div>
        <p class="skill-method-note">Si un curso no tiene examen, el progreso representa el 80% y la finalización el 20%. Cuando una skill aparece en varios cursos, TechFlix combina la evidencia según la relevancia que esa competencia tiene en cada curso.</p>
    </section>
</main>
</div>
<?php include '../includes/public_footer.php'; ?>
