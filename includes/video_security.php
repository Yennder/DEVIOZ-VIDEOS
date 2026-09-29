<?php

require_once __DIR__ . '/../config/sesion.php';

if (!function_exists('deviozVideoStreamToken')) {
    function deviozVideoStreamToken(int $idVideo, int $expira): string
    {
        $clave = csrfToken();
        $mensaje = $idVideo . '|' . $expira . '|' . session_id();
        return hash_hmac('sha256', $mensaje, $clave);
    }
}

if (!function_exists('deviozVideoStreamTokenValido')) {
    function deviozVideoStreamTokenValido(int $idVideo, int $expira, string $token): bool
    {
        if ($idVideo <= 0 || $expira < time() || $token === '') {
            return false;
        }

        $esperado = deviozVideoStreamToken($idVideo, $expira);
        return hash_equals($esperado, $token);
    }
}

if (!function_exists('deviozStreamArchivo')) {
    function deviozStreamArchivo(string $ruta, string $mime, string $nombre, bool $attachment = false): void
    {
        if (!is_file($ruta) || !is_readable($ruta)) {
            http_response_code(404);
            exit('Archivo no disponible.');
        }

        $tamano = filesize($ruta);
        if ($tamano === false || $tamano < 1) {
            http_response_code(404);
            exit('Archivo no disponible.');
        }

        $inicio = 0;
        $fin = $tamano - 1;
        $status = 200;

        if (!$attachment && isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/i', (string)$_SERVER['HTTP_RANGE'], $m)) {
            if ($m[1] !== '') {
                $inicio = max(0, (int)$m[1]);
            }
            if ($m[2] !== '') {
                $fin = min($fin, (int)$m[2]);
            }
            if ($inicio > $fin || $inicio >= $tamano) {
                header('Content-Range: bytes */' . $tamano);
                http_response_code(416);
                exit;
            }
            $status = 206;
        }

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        @set_time_limit(0);
        header('X-Content-Type-Options: nosniff');
        header('Accept-Ranges: bytes');
        header('Content-Type: ' . $mime);
        header('Cache-Control: private, no-store, max-age=0');
        header('Pragma: no-cache');

        $nombreSeguro = preg_replace('/[^A-Za-z0-9._-]+/', '-', $nombre) ?: 'video.mp4';
        header('Content-Disposition: ' . ($attachment ? 'attachment' : 'inline') . '; filename="' . $nombreSeguro . '"');

        if ($status === 206) {
            http_response_code(206);
            header('Content-Range: bytes ' . $inicio . '-' . $fin . '/' . $tamano);
            header('Content-Length: ' . (($fin - $inicio) + 1));
        } else {
            header('Content-Length: ' . $tamano);
        }

        $fp = fopen($ruta, 'rb');
        if ($fp === false) {
            http_response_code(500);
            exit('No se pudo abrir el archivo.');
        }

        if ($inicio > 0) {
            fseek($fp, $inicio);
        }

        $restante = ($fin - $inicio) + 1;
        $buffer = 1024 * 1024;

        while (!feof($fp) && $restante > 0 && connection_status() === CONNECTION_NORMAL) {
            $leer = min($buffer, $restante);
            $datos = fread($fp, $leer);
            if ($datos === false || $datos === '') {
                break;
            }
            echo $datos;
            flush();
            $restante -= strlen($datos);
        }

        fclose($fp);
        exit;
    }
}
