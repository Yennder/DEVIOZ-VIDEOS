<?php
require_once __DIR__ . '/../config/sesion.php';
?>
<!DOCTYPE html>
<html lang="<?php echo deviozIdiomaActual() === 'pt' ? 'pt-BR' : deviozIdiomaActual(); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#0b1117">
<meta name="color-scheme" content="light dark">
<title>DEVIOZ VIDEOS</title>
<link rel="stylesheet" href="../assets/css/public.css">
<link rel="stylesheet" href="../assets/css/capitulos.css">
<link rel="stylesheet" href="../assets/css/escenas_aprendizaje.css?v=4.4.4">
<link rel="stylesheet" href="../assets/css/skill_mapa.css?v=5.1">
<link rel="stylesheet" href="../assets/css/navbar_responsive_fix.css?v=4.5.1.2">
<link rel="stylesheet" href="../assets/css/generos.css?v=4.5.2">
<link rel="stylesheet" href="../assets/css/generos_hotfix.css?v=4.5.2.1">
<link rel="stylesheet" href="../assets/css/valoraciones.css?v=4.5.3">
<link rel="stylesheet" href="../assets/css/cuestionarios.css?v=4.5.4.1">
<link rel="stylesheet" href="../assets/css/video_whatsapp_compartir.css?v=4.5.5.1">
<link rel="stylesheet" href="../assets/css/idiomas.css?v=4.5.6">
<script>window.DEVIOZ_LANG = <?php echo json_encode(deviozIdiomaActual()); ?>;</script>
<script src="../assets/js/idiomas_diccionario.js?v=4.5.6" defer></script>
<script src="../assets/js/escenas_traducciones.js?v=4.4.4" defer></script>
<script src="../assets/js/idiomas.js?v=4.5.6" defer></script>
</head>
<body>
