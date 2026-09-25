<?php
require_once '../../../config/sesion.php';
verificarAdmin();
require_once '../../../controllers/SkillController.php';
require_once '../../../includes/learning_helpers.php';

$skillsController = new SkillController();
$buscar = trim((string)($_GET['buscar'] ?? ''));
$idSkill = (int)($_GET['skill'] ?? 0);
$nivel = trim((string)($_GET['nivel'] ?? ''));
$evaluado = trim((string)($_GET['evaluado'] ?? ''));
if (!in_array($nivel, ['', 'pendiente', 'desarrollo', 'basico', 'intermedio', 'avanzado'], true)) $nivel = '';
if (!in_array($evaluado, ['', 'si', 'no'], true)) $evaluado = '';

$reporte = $skillsController->reporteSkillsAdmin($buscar, $idSkill, $nivel, $evaluado);
$filas = $reporte['filas'];
$resumen = $reporte['resumen'];
$catalogo = $skillsController->listar('', '', '1');
$nivelManualTexto = ['basico'=>'Básico','intermedio'=>'Intermedio','avanzado'=>'Avanzado'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reporte de Skills - Learning Lab</title>
<link rel="stylesheet" href="../../../assets/css/admin.css">
</head>
<body>
<?php include '../../includes/sidebar.php'; ?>
<div class="admin-main">
<?php include '../../includes/navbar.php'; ?>
<section class="admin-content learning-admin-page skill-report-page">
    <div class="gestion-header">
        <div>
            <span class="learning-admin-kicker">Competencias del equipo</span>
            <h1>Reporte de Skills</h1>
            <p class="dashboard-subtitle">Compara el desarrollo calculado por TechFlix con la validación realizada por un supervisor.</p>
        </div>
        <a class="btn-secundario" href="listar.php">Catálogo de Skills</a>
    </div>

    <div class="skill-report-summary">
        <article><span>Trabajadores con evidencia</span><strong><?php echo (int)$resumen['trabajadores']; ?></strong><small>Con al menos una skill asociada a su aprendizaje.</small></article>
        <article><span>Skills presentes</span><strong><?php echo (int)$resumen['skills']; ?></strong><small>Competencias visibles en el reporte actual.</small></article>
        <article><span>Promedio plataforma</span><strong><?php echo round((float)$resumen['promedio_automatico'], 1); ?>%</strong><small>Promedio de evidencia académica calculada.</small></article>
        <article><span>Evaluaciones supervisor</span><strong><?php echo (int)$resumen['evaluaciones_supervisor']; ?></strong><small>Skills con una validación manual vigente.</small></article>
    </div>

    <form method="GET" class="filtros-admin learning-filter skill-report-filter">
        <div><label>Trabajador</label><input name="buscar" value="<?php echo learningH($buscar); ?>" placeholder="Nombre o correo"></div>
        <div><label>Skill</label><select name="skill"><option value="0">Todas</option><?php foreach($catalogo as $s): ?><option value="<?php echo (int)$s['id_skill']; ?>" <?php echo $idSkill===(int)$s['id_skill']?'selected':''; ?>><?php echo learningH($s['nombre']); ?></option><?php endforeach; ?></select></div>
        <div><label>Nivel plataforma</label><select name="nivel"><option value="">Todos</option><option value="pendiente" <?php echo $nivel==='pendiente'?'selected':''; ?>>Pendiente</option><option value="desarrollo" <?php echo $nivel==='desarrollo'?'selected':''; ?>>En desarrollo</option><option value="basico" <?php echo $nivel==='basico'?'selected':''; ?>>Básico</option><option value="intermedio" <?php echo $nivel==='intermedio'?'selected':''; ?>>Intermedio</option><option value="avanzado" <?php echo $nivel==='avanzado'?'selected':''; ?>>Avanzado</option></select></div>
        <div><label>Supervisor</label><select name="evaluado"><option value="">Todas</option><option value="si" <?php echo $evaluado==='si'?'selected':''; ?>>Evaluadas</option><option value="no" <?php echo $evaluado==='no'?'selected':''; ?>>Sin evaluar</option></select></div>
        <div class="filtros-botones"><button class="btn-filtrar">Filtrar</button><a class="btn-limpiar" href="reporte.php">Limpiar</a></div>
    </form>

    <div class="admin-table-wrapper">
        <table class="admin-table skill-report-table">
            <thead><tr><th>Trabajador</th><th>Skill</th><th>TechFlix</th><th>Supervisor</th><th>Evidencia</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php if(!$filas): ?>
                <tr><td colspan="6" class="tabla-vacia">No hay competencias para estos filtros.</td></tr>
            <?php else: foreach($filas as $r): $m=$r['manual']; ?>
                <tr>
                    <td><strong><?php echo learningH($r['nombre']); ?></strong><small class="learning-table-sub"><?php echo learningH($r['email']); ?></small></td>
                    <td><div class="skill-report-name"><span><?php echo learningH($r['skill_icono']); ?></span><div><strong><?php echo learningH($r['skill_nombre']); ?></strong><small><?php echo learningH($r['skill_categoria']); ?> · objetivo <?php echo learningH($r['nivel_objetivo_texto']); ?></small></div></div></td>
                    <td><div class="skill-report-score"><strong><?php echo round((float)$r['automatico'],1); ?>%</strong><span class="skill-level-badge is-<?php echo learningH($r['nivel_automatico']); ?>"><?php echo learningH($r['nivel_automatico_texto']); ?></span></div></td>
                    <td><?php if($m): ?><div class="skill-report-score is-supervisor"><strong><?php echo round((float)$m['puntaje'],1); ?>%</strong><span><?php echo learningH($nivelManualTexto[$m['nivel']] ?? ucfirst((string)$m['nivel'])); ?></span><small><?php echo learningH($m['evaluador_nombre']); ?></small></div><?php else: ?><span class="skill-report-pending">Sin evaluar</span><?php endif; ?></td>
                    <td><strong><?php echo (int)$r['cursos_completados']; ?> / <?php echo (int)$r['cursos_asociados']; ?></strong><small class="learning-table-sub">cursos completados</small></td>
                    <td><div class="learning-actions skill-report-actions"><a class="btn-editar" href="trabajador.php?id_usuario=<?php echo (int)$r['id_usuario']; ?>">Ver perfil</a><a class="btn-filtrar" href="evaluar.php?id_usuario=<?php echo (int)$r['id_usuario']; ?>&id_skill=<?php echo (int)$r['id_skill']; ?>">Evaluar</a></div></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
</div>
</body>
</html>
