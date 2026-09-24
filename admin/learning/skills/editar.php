<?php
require_once '../../../config/sesion.php'; verificarAdmin();
require_once '../../../controllers/SkillController.php';
require_once '../../../includes/learning_helpers.php';

$skills = new SkillController();
$id = (int)($_GET['id'] ?? $_POST['id_skill'] ?? 0);
$skill = $skills->buscar($id);
if (!$skill) { header('Location: listar.php'); exit; }
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrfPost();
    try {
        $_POST['id_skill'] = $id;
        $skills->guardar($_POST);
        header('Location: editar.php?id=' . $id . '&ok=1');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $skill = array_merge($skill, $_POST);
        $skill['id_skill'] = $id;
        $skill['estado'] = !empty($_POST['estado']) ? 1 : 0;
    }
}
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Editar skill - Learning Lab</title><link rel="stylesheet" href="../../../assets/css/admin.css"></head><body>
<?php include '../../includes/sidebar.php'; ?><div class="admin-main"><?php include '../../includes/navbar.php'; ?><section class="admin-content learning-admin-page">
<div class="gestion-header"><div><span class="learning-admin-kicker">Competencias</span><h1>Editar skill</h1><p class="dashboard-subtitle"><?php echo learningH($skill['nombre']); ?></p></div><a class="btn-secundario" href="listar.php">Volver</a></div>
<?php if(isset($_GET['ok'])): ?><div class="mensaje-exito">Skill actualizada correctamente.</div><?php endif; ?>
<?php if($error): ?><div class="mensaje-error"><?php echo learningH($error); ?></div><?php endif; ?>
<form method="POST" class="admin-form learning-form-grid"><?php echo csrfInput(); ?><input type="hidden" name="id_skill" value="<?php echo $id; ?>">
<div><label>Nombre *</label><input name="nombre" maxlength="120" required value="<?php echo learningH($skill['nombre']); ?>"></div>
<div><label>Código</label><input name="codigo" maxlength="60" value="<?php echo learningH($skill['codigo']); ?>"></div>
<div><label>Categoría *</label><input name="categoria" maxlength="80" required value="<?php echo learningH($skill['categoria']); ?>"></div>
<div><label>Icono</label><input name="icono" maxlength="20" value="<?php echo learningH($skill['icono']); ?>"></div>
<div class="learning-field-span"><label>Descripción</label><textarea name="descripcion" rows="4" maxlength="500"><?php echo learningH($skill['descripcion']); ?></textarea></div>
<div class="learning-check-field"><label><input type="checkbox" name="estado" value="1" <?php echo !empty($skill['estado'])?'checked':''; ?>> Skill activa</label></div>
<div class="learning-field-span"><button class="btn-crear">Guardar cambios</button></div>
</form>
</section></div></body></html>
