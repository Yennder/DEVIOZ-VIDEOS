<?php
/** DEVIOZ V4.5.6 - Preferencia de idioma. Nunca cambia datos académicos. */
require_once __DIR__ . '/config/sesion.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Método no permitido.');
}
verificarCsrfPost();
$idioma = $_POST['idioma'] ?? '';
if (!is_string($idioma) || !deviozGuardarIdioma($idioma)) {
    http_response_code(400);
    exit('Idioma inválido.');
}
$retorno = $_POST['retorno'] ?? '';
if (!is_string($retorno) || strlen($retorno) > 2048
    || !preg_match('#^/DEVIOZ-VIDEOS/(?!/)[a-zA-Z0-9_./?=&%+\-\x7E]*$#D', $retorno)
    || str_contains($retorno, '..') || str_contains($retorno, '//')
) {
    $retorno = '/DEVIOZ-VIDEOS/public/index.php';
}
header('Location: ' . $retorno, true, 303);
exit;
