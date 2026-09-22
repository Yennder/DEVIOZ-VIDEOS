// =================================
// DEVIOZ VIDEOS - ADMIN JS
// =================================


// Confirmación eliminación

function confirmarEliminar(){

    return confirm(
        "¿Está seguro de eliminar este registro?"
    );

}




document.addEventListener(
"DOMContentLoaded",
function(){



    // ================================
    // VALIDACIÓN FORMULARIOS
    // ================================


    const formularios = document.querySelectorAll("form");


    formularios.forEach(function(form){


        form.addEventListener(
        "submit",
        function(e){


            const inputs = form.querySelectorAll(
                "[required]"
            );


            let valido = true;


            inputs.forEach(function(input){


                if(input.value.trim()=="")
                {

                    valido=false;

                }


            });



            if(!valido)
            {


                alert(
                    "Complete todos los campos obligatorios"
                );


                e.preventDefault();


            }



        });


    });







    // ================================
    // PREVISUALIZAR MINIATURA VIDEO
    // ================================


    const inputImagen = document.querySelector(
        'input[name="miniatura"]'
    );



    if(inputImagen)
    {


        inputImagen.addEventListener(
        "change",
        function(){


            const archivo=this.files[0];


            if(archivo)
            {


                const lector=new FileReader();



                lector.onload=function(e)
                {


                    const imagen=document.getElementById(
                        "preview"
                    );



                    if(imagen)
                    {

                        imagen.src=e.target.result;

                    }


                }



                lector.readAsDataURL(archivo);


            }


        });


    }







    // ================================
    // MOSTRAR CAMPOS DE SERIES
    // ================================


    const tipoContenido = document.getElementById(
        "tipoContenido"
    );



    const datosSerie = document.getElementById(
        "datosSerie"
    );



    if(tipoContenido && datosSerie)
    {



        function mostrarCamposSerie()
        {


            if(tipoContenido.value === "serie")
            {


                datosSerie.style.display="block";


            }
            else
            {


                datosSerie.style.display="none";


            }


        }




        // Ejecutar al cargar página

        mostrarCamposSerie();




        // Ejecutar cuando cambie selección

        tipoContenido.addEventListener(
            "change",
            mostrarCamposSerie
        );



    }



// ================================
// CARGAR TEMPORADAS POR SERIE
// ================================


const serieSelect = document.getElementById(
    "serie"
);


const temporadaSelect = document.getElementById(
    "temporada"
);



if(serieSelect && temporadaSelect)
{


    serieSelect.addEventListener(
    "change",
    function(){


        let idSerie = this.value;



        temporadaSelect.innerHTML = 
        `
        <option value="">
        Cargando temporadas...
        </option>
        `;



        if(idSerie=="")
        {

            temporadaSelect.innerHTML =
            `
            <option value="">
            Seleccione temporada
            </option>
            `;


            return;

        }



        fetch(
        "../ajax/get_temporadas.php?id_serie="
        + idSerie
        )


        .then(response=>response.json())


        .then(data=>{


            temporadaSelect.innerHTML =
            `
            <option value="">
            Seleccione temporada
            </option>
            `;



            data.forEach(function(temp){


                temporadaSelect.innerHTML +=

                `
                <option value="${temp.id_temporada}">

                Temporada ${temp.numero_temporada}

                </option>
                `;


            });



        });



    });



}

});

// =========================================
// DEVIOZ ADMIN - MODO CLARO / OSCURO
// =========================================

document.addEventListener(
"DOMContentLoaded",
function()
{


    const themeSwitch =
        document.getElementById(
            "adminThemeSwitch"
        );


    const themeIcon =
        document.getElementById(
            "adminThemeIcon"
        );


    if(!themeSwitch)
    {

        return;

    }



    // =========================================
    // LEER TEMA GUARDADO
    // =========================================

    const temaGuardado =
        localStorage.getItem(
            "devioz_admin_theme"
        );



    // =========================================
    // APLICAR TEMA
    // =========================================

    function aplicarTemaAdmin(tema)
    {


        if(tema === "dark")
        {

            document.body.classList.add(
                "admin-dark-mode"
            );


            themeSwitch.checked = true;


            if(themeIcon)
            {

                themeIcon.textContent = "🌙";

            }

        }
        else
        {

            document.body.classList.remove(
                "admin-dark-mode"
            );


            themeSwitch.checked = false;


            if(themeIcon)
            {

                themeIcon.textContent = "☀️";

            }

        }


    }



    // =========================================
    // TEMA INICIAL
    // =========================================

    if(temaGuardado === "dark")
    {

        aplicarTemaAdmin("dark");

    }
    else
    {

        aplicarTemaAdmin("light");

    }



    // =========================================
    // CAMBIO DEL SWITCH
    // =========================================

    themeSwitch.addEventListener(
        "change",
        function()
        {


            if(this.checked)
            {

                aplicarTemaAdmin("dark");


                localStorage.setItem(
                    "devioz_admin_theme",
                    "dark"
                );

            }
            else
            {

                aplicarTemaAdmin("light");


                localStorage.setItem(
                    "devioz_admin_theme",
                    "light"
                );

            }


        }
    );


});