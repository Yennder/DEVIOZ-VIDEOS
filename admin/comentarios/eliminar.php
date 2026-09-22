<?php
require_once '../../config/sesion.php';
verificarAdmin();

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    header('Location: listar.php');
    exit;
}

verificarCsrfPost();
require_once '../../controllers/InteraccionController.php';

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if($id){
    $controller = new InteraccionController();
    $controller->eliminarComentario((int)$_SESSION['id_usuario'], $id, true);
}

header('Location: listar.php');
exit;
