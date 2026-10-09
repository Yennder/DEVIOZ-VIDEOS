<?php
/**
 * HOTFIX V4.5.5.1 - Enlaces compartibles de video.
 * La URL base vive en la tabla configuracion (sin migracion nueva).
 * Nunca se envian mensajes ni se guardan datos de destinatarios.
 */

if (!function_exists('deviozNormalizarUrlPublica')) {
    /** URL base HTTPS del proyecto, sin /public; null si es invalida. */
    function deviozNormalizarUrlPublica($entrada): ?string
    {
        if (!is_string($entrada)) return null;
        $valor = trim($entrada);
        if ($valor === '') return '';
        if (strlen($valor) > 500 || preg_match('/[\x00-\x1F\x7F]/', $valor)) return null;
        $partes = parse_url($valor);
        if (!is_array($partes) || strtolower((string)($partes['scheme'] ?? '')) !== 'https'
            || empty($partes['host']) || isset($partes['user']) || isset($partes['pass'])
            || isset($partes['query']) || isset($partes['fragment'])) return null;

        $host = strtolower(rtrim((string)$partes['host'], '.'));
        if ($host === 'localhost' || str_ends_with($host, '.localhost')
            || preg_match('/\.(?:local|lan|internal|test|invalid|example)$/i', $host)) return null;

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return null;
            $hostUrl = str_contains($host, ':') ? '[' . $host . ']' : $host;
        } else {
            if (!str_contains($host, '.') || !filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) return null;
            $hostUrl = $host;
        }

        $ruta = (string)($partes['path'] ?? '');
        if (preg_match('~(?:^|/)\.{1,2}(?:/|$)~', rawurldecode($ruta))) return null;
        $ruta = rtrim($ruta, '/');
        if (preg_match('~/public$~i', $ruta)) $ruta = substr($ruta, 0, -7);
        if (preg_match('~\.(?:php|html?)$~i', $ruta)) return null;
        $puerto = isset($partes['port']) && (int)$partes['port'] !== 443 ? ':' . (int)$partes['port'] : '';
        return 'https://' . $hostUrl . $puerto . $ruta;
    }
}

if (!function_exists('deviozUrlPublicaVideo')) {
    function deviozUrlPublicaVideo(int $idVideo, $basePublica): ?string
    {
        if ($idVideo < 1) return null;
        $base = deviozNormalizarUrlPublica($basePublica);
        if (!$base) return null;
        return $base . '/public/detalle.php?id=' . $idVideo;
    }
}
