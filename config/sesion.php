<?php

if (!function_exists('deviozEsHttps')) {
    function deviozEsHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => deviozEsHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

function verificarSesion(): void
{
    if (!isset($_SESSION['id_usuario'])) {
        header('Location: /DEVIOZ-VIDEOS/views/login.php');
        exit;
    }
}

function usuarioAutenticado(): bool
{
    return isset($_SESSION['id_usuario']);
}

function verificarAdmin(): void
{
    verificarSesion();

    if (($_SESSION['rol'] ?? '') !== 'admin') {
        header('Location: /DEVIOZ-VIDEOS/public/index.php');
        exit;
    }
}

function esAdmin(): bool
{
    return ($_SESSION['rol'] ?? '') === 'admin';
}

function esUsuario(): bool
{
    return ($_SESSION['rol'] ?? '') === 'usuario';
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfInput(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8')
        . '">';
}

function csrfValido(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function verificarCsrf(?string $token): void
{
    if (!csrfValido($token)) {
        http_response_code(419);
        exit('La sesión de seguridad expiró. Recarga la página e inténtalo nuevamente.');
    }
}

function verificarCsrfPost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        verificarCsrf($_POST['csrf_token'] ?? null);
    }
}

function rutaDeviozInternaValida(string $ruta): bool
{
    return $ruta !== ''
        && str_starts_with($ruta, '/DEVIOZ-VIDEOS/')
        && !str_contains($ruta, "\r")
        && !str_contains($ruta, "\n");
}
