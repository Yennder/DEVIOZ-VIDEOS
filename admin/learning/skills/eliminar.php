<?php
require_once '../../../config/sesion.php'; verificarAdmin();
require_once '../../../controllers/SkillController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listar.php');
    exit;
}

verificarCsrfPost();
$id = (int)($_POST['id'] ?? 0);
$skills = new SkillController();

if ($id <= 0) {
    header('Location: listar.php?error=' . urlencode('Skill no válida.'));
    exit;
}

try {
    if (!$skills->eliminar($id)) {
        header('Location: listar.php?error=' . urlencode('No se puede eliminar una skill asociada a cursos. Puedes desactivarla o quitar primero sus asociaciones.'));
        exit;
    }
    header('Location: listar.php?msg=' . urlencode('Skill eliminada correctamente.'));
    exit;
} catch (Throwable $e) {
    header('Location: listar.php?error=' . urlencode('No fue posible eliminar la skill.'));
    exit;
}
