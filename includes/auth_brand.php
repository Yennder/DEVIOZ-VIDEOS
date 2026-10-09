<?php
$logoSeguro = !empty($logoSitio) ? basename((string)$logoSitio) : '';
$logoPath = $logoSeguro !== '' ? (__DIR__ . '/../uploads/config/' . $logoSeguro) : '';
?>
<div class="logo-login">
    <?php if($logoPath !== '' && is_file($logoPath)): ?>
        <img src="/DEVIOZ-VIDEOS/uploads/config/<?php echo htmlspecialchars($logoSeguro); ?>" alt="DEVIOZ VIDEOS">
    <?php else: ?>
        <span class="auth-brand-symbol">DV</span>
        <span class="auth-brand-copy"><strong>DEVIOZ</strong><small>VIDEOS</small></span>
    <?php endif; ?>
</div>

<!-- V4.5.6: idioma disponible también antes de iniciar sesión. -->
<link rel="stylesheet" href="/DEVIOZ-VIDEOS/assets/css/idiomas.css?v=4.5.6">
<script>window.DEVIOZ_LANG = <?php echo json_encode(deviozIdiomaActual()); ?>;</script>
<script src="/DEVIOZ-VIDEOS/assets/js/idiomas_diccionario.js?v=4.5.6" defer></script>
<script src="/DEVIOZ-VIDEOS/assets/js/idiomas.js?v=4.5.6" defer></script>
<div class="devioz-lang-auth"><?php echo deviozSelectorIdioma('auth'); ?></div>
