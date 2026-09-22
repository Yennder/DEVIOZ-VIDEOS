<?php
require_once '../../config/sesion.php';
verificarAdmin();

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    header('Location: listar.php');
    exit;
}

verificarCsrfPost();
require_once '../../controllers/CategoriaController.php';

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if(!$id){
    header('Location: listar.php');
    exit;
}

$controller = new CategoriaController();
$controller->eliminar($id);

header('Location: listar.php');
exit;
