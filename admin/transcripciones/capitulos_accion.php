<?php

require_once '../../config/sesion.php';
verificarAdmin();
require_once '../../controllers/CapituloIAController.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: listar.php');
    exit;
}

verificarCsrf($_POST['csrf_token'] ?? null);

$idVideo = filter_input(INPUT_POST, 'id_video', FILTER_VALIDATE_INT) ?: 0;
$idGeneracion = filter_input(INPUT_POST, 'id_generacion', FILTER_VALIDATE_INT) ?: 0;
$accion = trim((string)($_POST['accion'] ?? ''));

if ($idVideo <= 0) {
    header('Location: listar.php?error=' . urlencode('Video inválido.'));
    exit;
}

$volver = 'capitulos.php?id_video=' . $idVideo;
$controller = new CapituloIAController();

try {
    if ($accion === 'generar') {
        $controller->generar($idVideo, (int)$_SESSION['id_usuario']);
        header('Location: ' . $volver . '&msg=' . urlencode('DEVIOZ AI generó una nueva propuesta de capítulos. Revísala antes de publicarla.'));
        exit;
    }

    if ($accion === 'descartar') {
        if ($idGeneracion <= 0) {
            throw new RuntimeException('Borrador inválido.');
        }
        $controller->descartar($idVideo, $idGeneracion);
        header('Location: ' . $volver . '&msg=' . urlencode('Borrador descartado. La versión publicada, si existe, no fue modificada.'));
        exit;
    }

    if (in_array($accion, ['guardar', 'guardar_publicar'], true)) {
        if ($idGeneracion <= 0) {
            throw new RuntimeException('Borrador inválido.');
        }

        $inicios = is_array($_POST['inicio'] ?? null) ? $_POST['inicio'] : [];
        $titulos = is_array($_POST['titulo'] ?? null) ? $_POST['titulo'] : [];
        $resumenes = is_array($_POST['resumen'] ?? null) ? $_POST['resumen'] : [];
        $conceptos = is_array($_POST['conceptos'] ?? null) ? $_POST['conceptos'] : [];
        $total = max(count($inicios), count($titulos), count($resumenes), count($conceptos));
        $filas = [];

        for ($i = 0; $i < $total; $i++) {
            $filas[] = [
                'inicio' => $inicios[$i] ?? '0',
                'titulo' => $titulos[$i] ?? '',
                'resumen' => $resumenes[$i] ?? '',
                'conceptos' => $conceptos[$i] ?? '',
            ];
        }

        $controller->guardarBorrador($idVideo, $idGeneracion, $filas);

        if ($accion === 'guardar_publicar') {
            $controller->publicar($idVideo, $idGeneracion, (int)$_SESSION['id_usuario']);
            header('Location: ' . $volver . '&msg=' . urlencode('Capítulos guardados y publicados. Ya están disponibles en el reproductor.'));
            exit;
        }

        header('Location: ' . $volver . '&msg=' . urlencode('Borrador guardado correctamente.'));
        exit;
    }

    throw new RuntimeException('Acción no reconocida.');
} catch (Throwable $e) {
    error_log('TECHFLIX V4.4.3 - Capitulos IA admin: ' . $e->getMessage());
    header('Location: ' . $volver . '&error=' . urlencode($e->getMessage()));
    exit;
}
