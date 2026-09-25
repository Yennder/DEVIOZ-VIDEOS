<?php
require_once '../../../config/sesion.php';
verificarAdmin();
require_once '../../../controllers/SkillController.php';
require_once '../../../includes/learning_helpers.php';

$skillsController = new SkillController();
$idUsuario = (int)($_GET['id_usuario'] ?? $_POST['id_usuario'] ?? 0);
$idSkill = (int)($_GET['id_skill'] ?? $_POST['id_skill'] ?? 0);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrfPost();
    try {
        $skillsController->guardarEvaluacionManual($idUsuario, $idSkill, (int)$_SESSION['id_usuario'], $_POST);
        header('Location: evaluar.php?id_usuario=' . $idUsuario . '&id_skill=' . $idSkill . '&ok=1');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$datos = $skillsController->datosEvaluacionSkillAdmin($idUsuario, $idSkill);
if (!$datos) { header('Location: reporte.php'); exit; }
$usuario = $datos['usuario'];
$skill = $datos['skill'];
$historial = $datos['historial'];
$ultima = $historial[0] ?? null;
$puntajeForm = $_POST['puntaje'] ?? ($ultima['puntaje'] ?? round((float)$skill['porcentaje'], 1));
$nivelForm = $_POST['nivel'] ?? ($ultima['nivel'] ?? ((float)$puntajeForm >= 80 ? 'avanzado' : ((float)$puntajeForm >= 50 ? 'intermedio' : 'basico')));
$comentarioForm = $_POST['comentario'] ?? '';
$nivelManualTexto = ['basico'=>'Básico','intermedio'=>'Intermedio','avanzado'=>'Avanzado'];
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Evaluar Skill - Learning Lab</title><link rel="stylesheet" href="../../../assets/css/admin.css"></head><body>
<?php include '../../includes/sidebar.php'; ?><div class="admin-main"><?php include '../../includes/navbar.php'; ?>
<section class="admin-content learning-admin-page skill-evaluate-page">
<div class="gestion-header"><div><span class="learning-admin-kicker">Validación del supervisor</span><h1>Evaluar <?php echo learningH($skill['nombre']); ?></h1><p class="dashboard-subtitle"><?php echo learningH($usuario['nombre']); ?> · <?php echo learningH($usuario['email']); ?></p></div><a class="btn-secundario" href="trabajador.php?id_usuario=<?php echo $idUsuario; ?>">Volver al trabajador</a></div>
<?php if(isset($_GET['ok'])): ?><div class="mensaje-exito">Evaluación registrada. El historial anterior se conserva.</div><?php endif; ?>
<?php if($error): ?><div class="mensaje-error"><?php echo learningH($error); ?></div><?php endif; ?>

<div class="skill-evaluate-layout">
<div>
    <div class="skill-evaluate-context">
        <div class="skill-worker-icon"><?php echo learningH($skill['icono']); ?></div>
        <div><span>Resultado automático de TechFlix</span><strong><?php echo round((float)$skill['porcentaje'],1); ?>%</strong><small><?php echo learningH($skill['nivel_texto']); ?> · objetivo <?php echo learningH($skill['nivel_objetivo_texto']); ?></small></div>
    </div>
    <form method="POST" class="admin-form skill-supervisor-form">
        <?php echo csrfInput(); ?><input type="hidden" name="id_usuario" value="<?php echo $idUsuario; ?>"><input type="hidden" name="id_skill" value="<?php echo $idSkill; ?>">
        <div><label>Puntaje del supervisor (0 - 100) *</label><input type="number" min="0" max="100" step="0.1" name="puntaje" required value="<?php echo learningH($puntajeForm); ?>"></div>
        <div><label>Nivel validado *</label><select name="nivel" required><option value="basico" <?php echo $nivelForm==='basico'?'selected':''; ?>>Básico</option><option value="intermedio" <?php echo $nivelForm==='intermedio'?'selected':''; ?>>Intermedio</option><option value="avanzado" <?php echo $nivelForm==='avanzado'?'selected':''; ?>>Avanzado</option></select></div>
        <div class="learning-field-span"><label>Comentario / evidencia observada *</label><textarea name="comentario" maxlength="1000" rows="6" required placeholder="Ej. Demuestra manejo adecuado de imágenes, contenedores y DockerHub durante la práctica."><?php echo learningH($comentarioForm); ?></textarea></div>
        <div class="learning-field-span skill-evaluate-note">La evaluación del supervisor se guarda como una evidencia independiente. No reemplaza el resultado automático de TechFlix y las evaluaciones anteriores permanecen en el historial.</div>
        <div class="learning-field-span"><button class="btn-crear">Guardar evaluación</button></div>
    </form>
</div>

<div class="skill-evidence-panel">
    <span class="learning-admin-kicker">Evidencia disponible</span><h2>¿De dónde sale el <?php echo round((float)$skill['porcentaje'],1); ?>%?</h2>
    <?php if(empty($skill['cursos'])): ?><p class="dashboard-subtitle">Todavía no existe evidencia académica de esta skill.</p><?php else: foreach($skill['cursos'] as $c): ?>
    <article><strong><?php echo learningH($c['curso']); ?></strong><small><?php echo learningH($c['capacitacion']); ?></small><div><span>Progreso <b><?php echo round((float)$c['progreso'],1); ?>%</b></span><?php if($c['tiene_evaluacion']): ?><span>Nota <b><?php echo round((float)$c['mejor_nota'],1); ?>%</b></span><?php endif; ?><span>Relevancia <b><?php echo round((float)$c['peso'],1); ?>%</b></span><span>Puntaje curso <b><?php echo round((float)$c['puntaje_curso'],1); ?>%</b></span></div></article>
    <?php endforeach; endif; ?>
</div>
</div>

<div class="skill-history-block">
<h2>Historial de evaluaciones del supervisor</h2><p class="dashboard-subtitle">Cada evaluación queda registrada para poder revisar la evolución del trabajador.</p>
<div class="admin-table-wrapper"><table class="admin-table"><thead><tr><th>Fecha</th><th>Evaluador</th><th>Puntaje</th><th>Nivel</th><th>Comentario</th></tr></thead><tbody>
<?php if(!$historial): ?><tr><td colspan="5" class="tabla-vacia">Todavía no hay evaluaciones manuales para esta skill.</td></tr><?php else: foreach($historial as $h): ?><tr><td><?php echo date('d/m/Y H:i',strtotime($h['fecha_evaluacion'])); ?></td><td><?php echo learningH($h['evaluador_nombre']); ?></td><td><strong><?php echo round((float)$h['puntaje'],1); ?>%</strong></td><td><?php echo learningH($nivelManualTexto[$h['nivel']] ?? ucfirst((string)$h['nivel'])); ?></td><td class="skill-history-comment"><?php echo learningH($h['comentario']); ?></td></tr><?php endforeach; endif; ?>
</tbody></table></div>
</div>
</section></div></body></html>
