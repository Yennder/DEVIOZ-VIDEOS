<?php
require_once '../../../config/sesion.php'; verificarAdmin();
require_once '../../../controllers/SkillController.php';
require_once '../../../includes/learning_helpers.php';

$skills = new SkillController();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrfPost();
    try {
        $skills->guardar($_POST);
        header('Location: listar.php?msg=' . urlencode('Skill creada correctamente.'));
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Nueva skill - Learning Lab</title><link rel="stylesheet" href="../../../assets/css/admin.css"></head><body>
<?php include '../../includes/sidebar.php'; ?><div class="admin-main"><?php include '../../includes/navbar.php'; ?><section class="admin-content learning-admin-page">
<div class="gestion-header"><div><span class="learning-admin-kicker">Competencias</span><h1>Nueva skill</h1><p class="dashboard-subtitle">Crea una competencia que luego podrás asociar a uno o varios cursos.</p></div><a class="btn-secundario" href="listar.php">Volver</a></div>
<?php if($error): ?><div class="mensaje-error"><?php echo learningH($error); ?></div><?php endif; ?>
<form method="POST" class="admin-form learning-form-grid"><?php echo csrfInput(); ?>
<div><label>Nombre *</label><input name="nombre" maxlength="120" required value="<?php echo learningH($_POST['nombre']??''); ?>" placeholder="Ej. Docker"></div>
<div><label>Código</label><input name="codigo" maxlength="60" value="<?php echo learningH($_POST['codigo']??''); ?>" placeholder="Se genera automáticamente"></div>
<div><label>Categoría *</label><input name="categoria" maxlength="80" required value="<?php echo learningH($_POST['categoria']??''); ?>" placeholder="Ej. DevOps"></div>
<div><label>Icono</label><input name="icono" maxlength="20" value="<?php echo learningH($_POST['icono']??'🧩'); ?>" placeholder="🧩"></div>
<div class="learning-field-span"><label>Descripción</label><textarea name="descripcion" rows="4" maxlength="500" placeholder="Qué conocimiento o capacidad representa esta skill"><?php echo learningH($_POST['descripcion']??''); ?></textarea></div>
<div class="learning-check-field"><label><input type="checkbox" name="estado" value="1" <?php echo !isset($_POST['estado'])||!empty($_POST['estado'])?'checked':''; ?>> Skill activa</label></div>
<div class="learning-field-span"><button class="btn-crear">Crear skill</button></div>
</form>
</section></div></body></html>
