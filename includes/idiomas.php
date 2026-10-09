<?php
/** DEVIOZ V4.5.6 - Idiomas de interfaz: español / inglés / portugués. */
if (!function_exists('deviozIdiomaActual')) {
    function deviozIdiomaActual(): string
    {
        $idioma = $_COOKIE['devioz_idioma'] ?? 'es';
        return is_string($idioma) && in_array($idioma, ['es', 'en', 'pt'], true) ? $idioma : 'es';
    }
}
if (!function_exists('deviozGuardarIdioma')) {
    function deviozGuardarIdioma(string $idioma): bool
    {
        if (!in_array($idioma, ['es', 'en', 'pt'], true)) return false;
        setcookie('devioz_idioma', $idioma, [
            'expires' => time() + 365 * 24 * 3600,
            'path' => '/DEVIOZ-VIDEOS/',
            'secure' => function_exists('deviozEsHttps') ? deviozEsHttps() : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE['devioz_idioma'] = $idioma;
        return true;
    }
}
if (!function_exists('deviozSelectorIdioma')) {
    function deviozSelectorIdioma(string $contexto = 'public'): string
    {
        $idioma = deviozIdiomaActual();
        $retorno = (string)($_SERVER['REQUEST_URI'] ?? '/DEVIOZ-VIDEOS/public/index.php');
        // URI is only used inside a hidden form field; the server validates it again.
        if (strlen($retorno) > 2048 || !str_starts_with($retorno, '/DEVIOZ-VIDEOS/')) {
            $retorno = '/DEVIOZ-VIDEOS/public/index.php';
        }
        $h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $options = ['es' => 'ES · Español', 'en' => 'EN · English', 'pt' => 'PT · Português'];
        $etiquetas = [
            'es' => ['Idioma', 'Idioma de la interfaz', 'Seleccionar idioma', 'Aplicar'],
            'en' => ['Language', 'Interface language', 'Select language', 'Apply'],
            'pt' => ['Idioma', 'Idioma da interface', 'Selecionar idioma', 'Aplicar'],
        ][$idioma];
        $html = '<form class="devioz-lang-switch devioz-lang-switch--' . $h($contexto) . '" '
            . 'method="post" action="/DEVIOZ-VIDEOS/cambiar_idioma.php" data-i18n-ignore>'
            . csrfInput()
            . '<input type="hidden" name="retorno" value="' . $h($retorno) . '">'
            . '<label for="deviozIdioma' . $h($contexto) . '" class="devioz-lang-icon" title="' . $h($etiquetas[0]) . '">'
            . '<span aria-hidden="true">🌐</span><span class="devioz-visually-hidden">' . $h($etiquetas[0]) . '</span></label>'
            . '<select id="deviozIdioma' . $h($contexto) . '" name="idioma" '
            . 'aria-label="' . $h($etiquetas[1]) . '" title="' . $h($etiquetas[2]) . '" onchange="this.form.requestSubmit()">';
        foreach ($options as $codigo => $texto) {
            $html .= '<option value="' . $codigo . '"' . ($codigo === $idioma ? ' selected' : '') . '>' . $h($texto) . '</option>';
        }
        return $html . '</select><noscript><button type="submit">' . $h($etiquetas[3]) . '</button></noscript></form>'; 
    }
}
