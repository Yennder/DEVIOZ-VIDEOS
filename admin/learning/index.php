<?php
require_once '../../config/sesion.php';
verificarAdmin();
require_once '../../controllers/LearningController.php';
require_once '../../includes/learning_helpers.php';
$learning=new LearningController();
$stats=$learning->statsAdmin();
$seguimiento=array_slice($learning->seguimientoAdmin(),0,8);
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Learning Lab - DEVIOZ</title><link rel="stylesheet" href="../../assets/css/admin.css"></head><body>
<?php include '../includes/sidebar.php'; ?><div class="admin-main"><?php include '../includes/navbar.php'; ?>
<section class="admin-content learning-admin-page">
<div class="dashboard-header"><div><span class="learning-admin-kicker">TechFlix Learning Lab</span><h1>Aprendizaje y capacitación</h1><p class="dashboard-subtitle">Gestiona cursos, asignaciones, evaluaciones, progreso y logros desde un solo lugar.</p></div><a class="btn-crear" href="cursos/crear.php">+ Crear curso</a></div>
<div class="stats-container learning-stats">
<?php $cards=[['📘','Cursos',$stats['cursos'],'Activos en la plataforma'],['🎓','Capacitaciones',$stats['capacitaciones'],'Actualmente activas'],['👥','Asignaciones',$stats['asignaciones'],'Usuarios asignados'],['✅','Completadas',$stats['completadas'],'Procesos finalizados'],['📝','Evaluaciones',$stats['evaluaciones'],'Evaluaciones publicadas'],['🏅','Logros',$stats['logros'],'Insignias obtenidas']]; foreach($cards as $c): ?>
<div class="stat-card"><div class="stat-icon"><?php echo $c[0]; ?></div><h3><?php echo learningH($c[1]); ?></h3><p><?php echo (int)$c[2]; ?></p><span class="stat-description"><?php echo learningH($c[3]); ?></span></div>
<?php endforeach; ?>
</div>
<div class="learning-admin-grid">
<section class="dashboard-section"><div class="dashboard-section-header"><div><span class="learning-admin-kicker">Flujo recomendado</span><h2>Gestiona el aprendizaje</h2></div></div>
<div class="learning-quick-grid">
<a href="cursos/listar.php" class="learning-quick-card"><span>01</span><strong>Cursos</strong><small>Crea el contenido y organiza lecciones.</small></a>
<a href="capacitaciones/listar.php" class="learning-quick-card"><span>02</span><strong>Capacitaciones</strong><small>Define fechas y asigna usuarios.</small></a>
<a href="evaluaciones/listar.php" class="learning-quick-card"><span>03</span><strong>Evaluaciones</strong><small>Configura examen, nota mínima e intentos.</small></a>
<a href="seguimiento.php" class="learning-quick-card"><span>04</span><strong>Seguimiento</strong><small>Revisa progreso, vencimientos y notas.</small></a>
</div></section>
<section class="dashboard-section"><div class="dashboard-section-header"><div><span class="learning-admin-kicker">Control</span><h2>Asignaciones recientes</h2></div><a class="dashboard-link" href="seguimiento.php">Ver todo</a></div>
<?php if(!$seguimiento): ?><div class="admin-empty-state">Todavía no hay usuarios asignados a capacitaciones.</div><?php else: ?><div class="learning-mini-list">
<?php foreach($seguimiento as $r): ?><div class="learning-mini-row"><div><strong><?php echo learningH($r['nombre']); ?></strong><small><?php echo learningH($r['curso']); ?> · vence <?php echo learningH($r['fecha_limite']); ?></small></div><div class="learning-mini-progress"><span style="width:<?php echo min(100,(float)$r['porcentaje']); ?>%"></span></div><b><?php echo (float)$r['porcentaje']; ?>%</b></div><?php endforeach; ?>
</div><?php endif; ?></section>
</div>
</section></div></body></html>
