<?php

require_once '../../config/sesion.php';
verificarAdmin();

header('Content-Type: application/json; charset=UTF-8');

require_once '../../controllers/TemporadaController.php';

$idSerie = filter_input(INPUT_GET, 'id_serie', FILTER_VALIDATE_INT);

if (!$idSerie) {
    http_response_code(400);
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

$controller = new TemporadaController();
$temporadas = $controller->listarPorSerie($idSerie);

echo json_encode($temporadas, JSON_UNESCAPED_UNICODE);
