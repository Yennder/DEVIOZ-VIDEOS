<?php
require_once '../../config/sesion.php';
verificarAdmin();
require_once '../../models/Genero.php';
$modelo = new Genero();
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verificarCsrfPost();
    $accion = (string)($_POST['accion'] ?? '');
    $idPost = filter_var($_POST['id_genero'] ?? 0, FILTER_VALIDATE_INT);
    $id = $idPost && $idPost > 0 ? $idPost : 0;
    if ($accion === 'guardar') {
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        $descripcion = trim((string)($_POST['descripcion'] ?? ''));
        if ($id > 0 && !$modelo->buscar($id)) {
            $error = 'No se encontro el genero que intentas editar.';
        } elseif (!$modelo->guardar($id, $nombre, $descripcion)) {
            $error = 'Revisa el nombre y la descripcion. El nombre no debe repetirse.';
        } else {
            header('Location: listar.php?ok=guardado');
            exit;
        }
    } elseif ($accion === 'estado' && $id > 0 && $modelo->buscar($id)) {
        $modelo->cambiarEstado($id, (string)($_POST['nuevo_estado'] ?? '0') === '1');
        header('Location: listar.php?ok=estado');
        exit;
    } else {
        $error = 'La accion solicitada no es valida.';
    }
}

$idEditar = filter_var($_GET['editar'] ?? 0, FILTER_VALIDATE_INT);
$generoEditar = $idEditar && $idEditar > 0 ? $modelo->buscar($idEditar) : null;
$generos = $modelo->listar();
function generoH($texto): string { return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Géneros - DEVIOZ VIDEOS</title>
<link rel="stylesheet" href="../../assets/css/admin.css">
<link rel="stylesheet" href="../../assets/css/generos.css?v=4.5.2">
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="admin-main">
<?php include '../includes/navbar.php'; ?>
<section class="admin-content">
  <div class="gestion-header"><div><h1>Gestión de géneros</h1><p>Clasifica películas, documentales, tutoriales y capítulos. Un video puede tener varios géneros.</p></div>
    <a class="btn" href="listar.php">+ Nuevo género</a>
  </div>
  <?php if ($error): ?><p class="mensaje-error" role="alert"><?php echo generoH($error); ?></p><?php endif; ?>
  <?php if (!empty($_GET['ok'])): ?><p class="genre-success" role="status">Los cambios del género se guardaron correctamente.</p><?php endif; ?>
  <div class="genre-manager-grid">
    <form method="POST" class="admin-form genre-manager-form">
      <?php echo csrfInput(); ?>
      <input type="hidden" name="accion" value="guardar">
      <input type="hidden" name="id_genero" value="<?php echo (int)($generoEditar['id_genero'] ?? 0); ?>">
      <h2><?php echo $generoEditar ? 'Editar género' : 'Nuevo género'; ?></h2>
      <label for="genre-name">Nombre</label>
      <input id="genre-name" type="text" name="nombre" maxlength="120" required value="<?php echo generoH($generoEditar['nombre'] ?? ($_POST['nombre'] ?? '')); ?>" placeholder="Ej.: Inteligencia Artificial & Automatización">
      <label for="genre-description">Descripción (opcional)</label>
      <textarea id="genre-description" name="descripcion" maxlength="500" rows="3"><?php echo generoH($generoEditar['descripcion'] ?? ($_POST['descripcion'] ?? '')); ?></textarea>
      <button class="btn" type="submit"><?php echo $generoEditar ? 'Guardar cambios' : 'Crear género'; ?></button>
      <?php if ($generoEditar): ?><a href="listar.php">Cancelar edición</a><?php endif; ?>
    </form>
    <div class="genre-manager-table-wrap">
      <h2>Géneros registrados (<?php echo count($generos); ?>)</h2>
      <table class="genre-manager-table">
        <thead><tr><th>Nombre</th><th>Videos</th><th>Estado</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($generos as $genero): ?>
          <tr>
            <td><strong><?php echo generoH($genero['nombre']); ?></strong></td>
            <td><?php echo (int)$genero['total_videos']; ?></td>
            <td><?php echo (int)$genero['estado'] === 1 ? 'Activo' : 'Inactivo'; ?></td>
            <td class="genre-actions"><a href="listar.php?editar=<?php echo (int)$genero['id_genero']; ?>">Editar</a>
            <form method="POST"><?php echo csrfInput(); ?>
              <input type="hidden" name="accion" value="estado">
              <input type="hidden" name="id_genero" value="<?php echo (int)$genero['id_genero']; ?>">
              <input type="hidden" name="nuevo_estado" value="<?php echo (int)$genero['estado'] === 1 ? 0 : 1; ?>">
              <button type="submit"><?php echo (int)$genero['estado'] === 1 ? 'Desactivar' : 'Activar'; ?></button>
            </form></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p class="genre-muted">Desactivar un género lo oculta de los filtros públicos, pero conserva las etiquetas de los videos ya clasificados.</p>
    </div>
  </div>
</section></div>
</body></html>
