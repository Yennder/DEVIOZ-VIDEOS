<?php require_once __DIR__ . '/../../config/sesion.php'; ?>
<link rel="stylesheet" href="/DEVIOZ-VIDEOS/assets/css/idiomas.css?v=4.5.6">
<script>window.DEVIOZ_LANG = <?php echo json_encode(deviozIdiomaActual()); ?>;</script>
<script src="/DEVIOZ-VIDEOS/assets/js/idiomas_diccionario.js?v=4.5.6" defer></script>
<script src="/DEVIOZ-VIDEOS/assets/js/idiomas.js?v=4.5.6" defer></script>
<header class="admin-navbar">


    <div class="admin-title">

        Panel Administrativo

    </div>



    <div class="admin-actions">
        <?php echo deviozSelectorIdioma('admin'); ?>


        <!-- =========================================
             SWITCH MODO CLARO / OSCURO
        ========================================== -->

        <div class="admin-theme-control">


            <span
            class="admin-theme-icon"
            id="adminThemeIcon"
            >

                ☀️

            </span>


            <label
            class="admin-theme-switch"
            title="Cambiar tema"
            >


                <input
                type="checkbox"
                id="adminThemeSwitch"
                >


                <span class="admin-theme-slider"></span>


            </label>


        </div>



        <!-- =========================================
             VER SITIO PUBLICO
        ========================================== -->

        <a
        href="/DEVIOZ-VIDEOS/public/index.php"
        class="btn-public"
        >

            🌎 Ver sitio público

        </a>



        <!-- =========================================
             USUARIO
        ========================================== -->

        <div class="admin-user">

            👤

            <?php echo htmlspecialchars($_SESSION["nombre"]); ?>

        </div>


    </div>


</header>
<script>
(function(){
    try {
        if(localStorage.getItem("devioz_admin_theme") === "dark") {
            document.body.classList.add("admin-dark-mode");
        }
    } catch(e) {}
})();
</script>
<script src="/DEVIOZ-VIDEOS/assets/js/admin.js"></script>
