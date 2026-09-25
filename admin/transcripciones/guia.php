<?php

require_once '../../config/sesion.php';
verificarAdmin();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Guia de transcripcion - DEVIOZ VIDEOS</title>
<link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="admin-main">
<?php include '../includes/navbar.php'; ?>
<section class="admin-content">
    <div class="gestion-header">
        <div>
            <span class="admin-nav-label">AYUDA LOCAL</span>
            <h1>Instalar transcripcion local</h1>
            <p>Pasos para preparar un nuevo equipo y usar faster-whisper desde TechFlix.</p>
        </div>
        <div class="acciones-contenedor">
            <a href="listar.php" class="btn">Volver</a>
            <a href="diagnostico.php" class="btn-editar">Abrir diagnostico</a>
        </div>
    </div>

    <div class="worker-guide-grid">
        <article class="worker-guide-step"><b>1</b><div><h3>Instala Python 3.11 de 64 bits</h3><p>Durante la instalacion activa Add python.exe to PATH. Cierra y vuelve a abrir CMD.</p><code>python --version</code></div></article>
        <article class="worker-guide-step"><b>2</b><div><h3>Instala el entorno de TechFlix</h3><p>Desde <code>python/transcripcion</code> ejecuta el instalador incluido.</p><code>INSTALAR_TRANSCRIPCION.bat</code></div></article>
        <article class="worker-guide-step"><b>3</b><div><h3>Comprueba el entorno</h3><p>El diagnostico verifica Python, faster-whisper, PyAV, MySQL y las tablas de transcripcion.</p><code>PROBAR_ENTORNO.bat</code></div></article>
        <article class="worker-guide-step"><b>4</b><div><h3>FFmpeg es opcional</h3><p>Si esta instalado se usa para normalizar el audio. Si no, faster-whisper puede procesar mediante PyAV.</p><code>ffmpeg -version</code></div></article>
        <article class="worker-guide-step"><b>5</b><div><h3>Inicia el worker</h3><p>En V3.3 puedes iniciarlo desde el panel de Transcripciones. El BAT sigue disponible como alternativa manual.</p><code>INICIAR_WORKER.bat</code></div></article>
        <article class="worker-guide-step"><b>6</b><div><h3>Prueba un video corto</h3><p>Encola un solo video, espera 100%, revisa la transcripcion y comprueba que el VTT se genere correctamente.</p></div></article>
    </div>

    <div class="worker-linux-note">
        <span class="admin-nav-label">FUTURO VPS / LINUX</span>
        <h3>La arquitectura ya no depende solamente de un BAT</h3>
        <p>El worker es un proceso Python independiente. V3.3 incluye un lanzador <code>INICIAR_WORKER.sh</code> y el control PHP distingue Windows y Linux. En produccion se recomienda ejecutarlo como servicio permanente del servidor.</p>
    </div>
</section>
</div>
</body>
</html>
