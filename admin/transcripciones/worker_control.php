<?php

require_once '../../config/sesion.php';
verificarAdmin();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: listar.php');
    exit;
}

verificarCsrf($_POST['csrf_token'] ?? null);

// Libera el bloqueo de sesion antes de iniciar/detener procesos largos.
// Asi otras paginas del panel pueden cargar aunque Windows tarde en crear el worker.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
@set_time_limit(20);
require_once '../../includes/TranscriptionWorkerManager.php';
require_once '../../controllers/TranscripcionController.php';

$accion = trim((string)($_POST['accion'] ?? ''));
if (!in_array($accion, ['iniciar', 'detener', 'reiniciar'], true)) {
    header('Location: listar.php?error=' . urlencode('Accion de worker invalida.'));
    exit;
}

try {
    $manager = new TranscriptionWorkerManager();
    $controller = new TranscripcionController();

    if ($accion === 'detener') {
        $result = $manager->stop();
        $recuperados = $controller->recuperarInterrumpidos();
        $mensaje = $result['message'];
        if ($recuperados > 0) {
            $mensaje .= ' ' . $recuperados . ' trabajo(s) fueron devueltos a Pendiente.';
        }
    } elseif ($accion === 'reiniciar') {
        $manager->stop();
        $recuperados = $controller->recuperarInterrumpidos();
        $result = $manager->start();
        $mensaje = $result['message'];
        if ($recuperados > 0) {
            $mensaje .= ' ' . $recuperados . ' trabajo(s) interrumpidos fueron recuperados.';
        }
    } else {
        $result = $manager->start();
        $mensaje = $result['message'];
    }

    header('Location: listar.php?msg=' . urlencode($mensaje));
} catch (Throwable $e) {
    error_log('TECHFLIX V3.3 worker: ' . $e->getMessage());
    header('Location: listar.php?error=' . urlencode($e->getMessage()));
}
exit;
