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
