<header class="admin-navbar">


    <div class="admin-title">

        Panel Administrativo

    </div>



    <div class="admin-actions">


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
