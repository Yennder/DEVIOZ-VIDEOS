<?php
require_once '../../../config/sesion.php'; verificarAdmin();
require_once '../../../controllers/SkillController.php';
require_once '../../../includes/learning_helpers.php';

$skillsController = new SkillController();
$buscar = trim((string)($_GET['buscar'] ?? ''));
$categoria = trim((string)($_GET['categoria'] ?? ''));
$estado = (string)($_GET['estado'] ?? '');
$skills = $skillsController->listar($buscar, $categoria, $estado);
$categorias = $skillsController->categorias();
$mensaje = trim((string)($_GET['msg'] ?? ''));
$error = trim((string)($_GET['error'] ?? ''));
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Skills - Learning Lab</title><link rel="stylesheet" href="../../../assets/css/admin.css"></head><body>
<?php include '../../includes/sidebar.php'; ?><div class="admin-main"><?php include '../../includes/navbar.php'; ?><section class="admin-content learning-admin-page">
<div class="gestion-header"><div><span class="learning-admin-kicker">Competencias</span><h1>Skills</h1><p class="dashboard-subtitle">Define las competencias tecnológicas que podrán relacionarse con los cursos.</p></div><a href="crear.php" class="btn-crear">+ Nueva skill</a></div>
<?php if($mensaje): ?><div class="mensaje-exito"><?php echo learningH($mensaje); ?></div><?php endif; ?>
<?php if($error): ?><div class="mensaje-error"><?php echo learningH($error); ?></div><?php endif; ?>
<form method="GET" class="filtros-admin learning-filter skill-filter"><div><label>Buscar</label><input name="buscar" value="<?php echo learningH($buscar); ?>" placeholder="Nombre, código o descripción"></div><div><label>Categoría</label><select name="categoria"><option value="">Todas</option><?php foreach($categorias as $cat): ?><option value="<?php echo learningH($cat); ?>" <?php echo $categoria===$cat?'selected':''; ?>><?php echo learningH($cat); ?></option><?php endforeach; ?></select></div><div><label>Estado</label><select name="estado"><option value="">Todos</option><option value="1" <?php echo $estado==='1'?'selected':''; ?>>Activas</option><option value="0" <?php echo $estado==='0'?'selected':''; ?>>Inactivas</option></select></div><div class="filtros-botones"><button class="btn-filtrar">Filtrar</button><a class="btn-limpiar" href="listar.php">Limpiar</a></div></form>
<div class="admin-table-wrapper"><table class="admin-table learning-action-table skill-admin-table"><thead><tr><th>Skill</th><th>Categoría</th><th>Código</th><th>Cursos</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
<?php if(!$skills): ?><tr><td colspan="6" class="tabla-vacia">Aún no hay skills configuradas.</td></tr><?php else: foreach($skills as $s): ?><tr><td><div class="skill-name-cell"><span class="skill-icon"><?php echo learningH($s['icono']); ?></span><div><strong><?php echo learningH($s['nombre']); ?></strong><small class="learning-table-sub"><?php echo learningH($s['descripcion'] ?: 'Sin descripción'); ?></small></div></div></td><td><?php echo learningH($s['categoria']); ?></td><td><code class="skill-code"><?php echo learningH($s['codigo']); ?></code></td><td><?php echo (int)$s['cursos_asociados']; ?></td><td><span class="learning-badge <?php echo $s['estado']?'is-success':'is-muted'; ?>"><?php echo $s['estado']?'Activa':'Inactiva'; ?></span></td><td><div class="learning-actions"><a class="btn-editar" href="editar.php?id=<?php echo (int)$s['id_skill']; ?>">Editar</a><form method="POST" action="eliminar.php" class="inline-delete-form" onsubmit="return confirm('¿Eliminar esta skill? Solo será posible si no está asociada a ningún curso.');"><?php echo csrfInput(); ?><input type="hidden" name="id" value="<?php echo (int)$s['id_skill']; ?>"><button class="btn-eliminar">Eliminar</button></form></div></td></tr><?php endforeach; endif; ?>
</tbody></table></div>
</section></div></body></html>
