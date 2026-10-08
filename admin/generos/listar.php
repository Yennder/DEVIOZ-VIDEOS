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
<link rel="stylesheet" href="../../assets/css/generos_hotfix.css?v=4.5.2.1">
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="admin-main">
<?php include '../includes/navbar.php'; ?>
<section class="admin-content genre-admin-page">
    <div class="gestion-header genre-page-header">
        <div class="genre-page-heading">
            <span class="genre-eyebrow"><span aria-hidden="true" class="genre-eyebrow-dot"></span> Organización del catálogo</span>
            <h1>Gestión de géneros</h1>
            <p>Organiza películas, documentales, tutoriales y capítulos por temática. Cada video puede pertenecer a varios géneros.</p>
        </div>
        <a class="btn genre-new-button" href="listar.php#genre-editor">+ Nuevo género</a>
    </div>

    <?php if ($error): ?>
        <p class="mensaje-error" role="alert"><?php echo generoH($error); ?></p>
    <?php endif; ?>
    <?php if (!empty($_GET['ok'])): ?>
        <p class="genre-success" role="status">Los cambios del género se guardaron correctamente.</p>
    <?php endif; ?>

    <div class="genre-overview" aria-label="Resumen de géneros">
        <span class="genre-overview-icon" aria-hidden="true">◇</span>
        <div class="genre-overview-total">
            <strong><?php echo count($generos); ?></strong>
            <span>géneros registrados</span>
        </div>
        <p>La categoría define el tipo de contenido; los géneros describen los temas que trata.</p>
    </div>

    <div class="genre-manager-grid">
        <form method="POST" class="admin-form genre-manager-form" id="genre-editor">
            <?php echo csrfInput(); ?>
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id_genero" value="<?php echo (int)($generoEditar['id_genero'] ?? 0); ?>">
            <div class="genre-card-heading">
                <div class="genre-card-icon" aria-hidden="true">✎</div>
                <div>
                    <span class="genre-card-eyebrow">EDITOR DE GÉNEROS</span>
                    <h2><?php echo $generoEditar ? 'Editar género' : 'Nuevo género'; ?></h2>
                    <p><?php echo $generoEditar ? 'Actualiza la información de este género.' : 'Crea una temática nueva para clasificar videos.'; ?></p>
                </div>
            </div>

            <div class="genre-form-field">
                <label for="genre-name">Nombre del género <span aria-hidden="true">*</span></label>
                <input id="genre-name" type="text" name="nombre" maxlength="120" required
                    value="<?php echo generoH($generoEditar['nombre'] ?? ($_POST['nombre'] ?? '')); ?>"
                    placeholder="Ej.: Inteligencia Artificial &amp; Automatización">
                <small>Debe ser un nombre único y fácil de identificar.</small>
            </div>

            <div class="genre-form-field">
                <label for="genre-description">Descripción <span class="genre-optional">(opcional)</span></label>
                <textarea id="genre-description" name="descripcion" maxlength="500" rows="4"
                    placeholder="Describe brevemente esta temática..."><?php echo generoH($generoEditar['descripcion'] ?? ($_POST['descripcion'] ?? '')); ?></textarea>
            </div>

            <div class="genre-form-actions">
                <button class="btn genre-save-button" type="submit"><?php echo $generoEditar ? 'Guardar cambios' : 'Crear género'; ?></button>
                <?php if ($generoEditar): ?>
                    <a class="btn-secundario genre-cancel-button" href="listar.php#genre-editor">Cancelar edición</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="genre-manager-table-wrap">
            <div class="genre-card-heading genre-table-heading">
                <div class="genre-card-icon" aria-hidden="true">☷</div>
                <div>
                    <span class="genre-card-eyebrow">LISTADO EDITORIAL</span>
                    <h2>Géneros registrados <span class="genre-heading-count"><?php echo count($generos); ?></span></h2>
                    <p>Consulta su uso y administra su disponibilidad en el catálogo.</p>
                </div>
            </div>
            <div class="genre-table-scroll">
                <table class="admin-table genre-manager-table">
                    <thead>
                        <tr><th scope="col">Nombre</th><th scope="col">Videos</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($generos as $genero): ?>
                        <tr>
                            <td>
                                <strong class="genre-name"><?php echo generoH($genero['nombre']); ?></strong>
                                <?php if (trim((string)($genero['descripcion'] ?? '')) !== ''): ?>
                                    <small class="genre-row-description"><?php echo generoH($genero['descripcion']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span class="genre-video-count"><?php echo (int)$genero['total_videos']; ?></span></td>
                            <td>
                                <span class="genre-status <?php echo (int)$genero['estado'] === 1 ? 'genre-status-active' : 'genre-status-inactive'; ?>">
                                    <span class="genre-status-dot" aria-hidden="true"></span>
                                    <?php echo (int)$genero['estado'] === 1 ? 'Activo' : 'Inactivo'; ?>
                                </span>
                            </td>
                            <td class="genre-actions">
                                <div class="genre-actions-inner">
                                    <a class="genre-action-edit" href="listar.php?editar=<?php echo (int)$genero['id_genero']; ?>#genre-editor">Editar</a>
                                    <form method="POST">
                                        <?php echo csrfInput(); ?>
                                        <input type="hidden" name="accion" value="estado">
                                        <input type="hidden" name="id_genero" value="<?php echo (int)$genero['id_genero']; ?>">
                                        <input type="hidden" name="nuevo_estado" value="<?php echo (int)$genero['estado'] === 1 ? 0 : 1; ?>">
                                        <button type="submit" class="genre-action-state <?php echo (int)$genero['estado'] === 1 ? 'genre-action-disable' : 'genre-action-enable'; ?>">
                                            <?php echo (int)$genero['estado'] === 1 ? 'Desactivar' : 'Activar'; ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($generos)): ?>
                        <tr><td colspan="4" class="genre-table-empty">Todavía no hay géneros registrados.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="genre-manager-footnote"><span aria-hidden="true">ⓘ</span> Desactivar un género lo oculta de los filtros públicos, pero no elimina las asociaciones existentes.</p>
        </div>
    </div>
</section>
</div>
</body>
</html>
