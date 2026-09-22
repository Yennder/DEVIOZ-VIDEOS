<?php
require_once '../../config/sesion.php';
verificarAdmin();
require_once '../../controllers/InteraccionController.php';

$controller = new InteraccionController();
$buscar = trim((string)($_GET['buscar'] ?? ''));
$comentarios = $controller->comentariosAdmin($buscar);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Comentarios - DEVIOZ VIDEOS</title>
<link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="admin-main">
<?php include '../includes/navbar.php'; ?>
<section class="admin-content">
    <div class="gestion-header">
        <div><h1>Moderación de comentarios</h1><p>Revisa y elimina comentarios cuando sea necesario.</p></div>
    </div>

    <form method="GET" class="filtros-admin modern-admin-filter">
        <input type="search" name="buscar" value="<?php echo htmlspecialchars($buscar); ?>" placeholder="Buscar comentario, usuario o video...">
        <button class="btn-crear" type="submit">Buscar</button>
        <?php if($buscar !== ''): ?><a href="listar.php" class="btn-secundario">Limpiar</a><?php endif; ?>
    </form>

    <?php if(empty($comentarios)): ?>
        <div class="admin-empty-state"><span>◌</span><h3>No hay comentarios para mostrar</h3><p>La actividad de la comunidad aparecerá aquí.</p></div>
    <?php else: ?>
    <div class="admin-table-wrapper">
        <table class="admin-table">
            <thead><tr><th>Usuario</th><th>Video</th><th>Comentario</th><th>Fecha</th><th>Acción</th></tr></thead>
            <tbody>
            <?php foreach($comentarios as $comentario): ?>
                <tr>
                    <td><?php echo htmlspecialchars($comentario['usuario']); ?></td>
                    <td><?php echo htmlspecialchars($comentario['video']); ?></td>
                    <td class="comment-admin-cell">
                        <?php if(!empty($comentario['id_comentario_padre'])): ?><small style="display:block;margin-bottom:4px;font-weight:800;opacity:.65;">↳ Respuesta al comentario #<?php echo (int)$comentario['id_comentario_padre']; ?></small><?php endif; ?>
                        <?php echo htmlspecialchars($comentario['contenido']); ?>
                    </td>
                    <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($comentario['fecha_creacion']))); ?></td>
                    <td>
                        <form method="POST" action="eliminar.php" class="inline-delete-form" onsubmit="return confirm('¿Eliminar este comentario?');">
                            <?php echo csrfInput(); ?>
                            <input type="hidden" name="id" value="<?php echo (int)$comentario['id_comentario']; ?>">
                            <button type="submit" class="btn-eliminar">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
</div>

</body>
</html>
