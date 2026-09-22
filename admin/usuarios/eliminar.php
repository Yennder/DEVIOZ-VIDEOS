<?php
require_once '../../config/sesion.php';
verificarAdmin();

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    header('Location: listar.php');
    exit;
}

verificarCsrfPost();
require_once '../../controllers/UsuarioController.php';

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if(!$id){
    header('Location: listar.php');
    exit;
}

if($id === (int)($_SESSION['id_usuario'] ?? 0)){
    header('Location: listar.php');
    exit;
}

$controller = new UsuarioController();
$controller->eliminar($id);

header('Location: listar.php');
exit;
