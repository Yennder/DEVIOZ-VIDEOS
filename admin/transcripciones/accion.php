<?php

require_once '../../config/sesion.php';
verificarAdmin();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: listar.php');
    exit;
}

verificarCsrf($_POST['csrf_token'] ?? null);
require_once '../../controllers/TranscripcionController.php';
$transcripcionConfig = require '../../config/transcripcion.php';
$modeloDefault = (string)($transcripcionConfig['modelo'] ?? 'small');
$idiomaDefault = (string)($transcripcionConfig['idioma'] ?? 'es');

$idVideo = filter_input(INPUT_POST, 'id_video', FILTER_VALIDATE_INT);
$accion = trim($_POST['accion'] ?? '');

if (!in_array($accion, ['generar','regenerar','generar_todos'], true)) {
    header('Location: listar.php?error=' . urlencode('Solicitud invalida.'));
    exit;
}

try {
    $controller = new TranscripcionController();

    if ($accion === 'generar_todos') {
        $pendientes = $controller->listarVideos('', 'no_generada');
        $cantidad = 0;
        foreach ($pendientes as $item) {
            $controller->encolar((int)$item['id_video'], false, $idiomaDefault, $modeloDefault);
            $cantidad++;
        }
        header('Location: listar.php?msg=' . urlencode($cantidad . ' videos agregados a la cola local.'));
        exit;
    }

    if (!$idVideo) {
        throw new RuntimeException('Video invalido.');
    }

    $controller->encolar((int)$idVideo, $accion === 'regenerar', $idiomaDefault, $modeloDefault);
    header('Location: listar.php?msg=' . urlencode('Video agregado a la cola de transcripcion local.'));
} catch (Throwable $e) {
    error_log('TECHFLIX V3 transcripcion: ' . $e->getMessage());
    header('Location: listar.php?error=' . urlencode($e->getMessage()));
}
exit;
