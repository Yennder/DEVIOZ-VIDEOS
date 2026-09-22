<?php


require_once __DIR__ . "/GroqProvider.php";
require_once __DIR__ . "/GeminiProvider.php";


class AIManager
{

    private $providers;


    public function __construct()
    {

        $this->providers = [

            "groq" =>
                new GroqProvider(),

            "gemini" =>
                new GeminiProvider()

        ];

    }



    // =========================================
    // OBTENER PROVEEDOR
    // =========================================

    public function obtenerProveedor(
        $nombre
    )
    {

        $nombre =
            strtolower(
                trim(
                    $nombre
                )
            );


        return
            $this->providers[$nombre]
            ??
            null;

    }



    // =========================================
    // VERIFICAR SI EXISTE
    // =========================================

    public function existeProveedor(
        $nombre
    )
    {

        return
            $this->obtenerProveedor(
                $nombre
            )
            !== null;

    }



    // =========================================
    // LISTAR PROVEEDORES
    // =========================================

    public function listarProveedores()
    {

        $resultado =
            [];


        foreach(
            $this->providers
            as
            $nombre =>
            $provider
        )
        {

            $resultado[] = [

                "nombre" =>
                    $nombre,

                "disponible" =>
                    $provider->estaDisponible()

            ];

        }


        return $resultado;

    }



    // =========================================
    // GENERAR RESPUESTA
    // =========================================

    public function generarRespuesta(
        $providerNombre,
        array $mensajes,
        array $opciones = []
    )
    {

        $provider =
            $this->obtenerProveedor(
                $providerNombre
            );


        if(!$provider)
        {

            return [

                "ok" =>
                    false,

                "provider" =>
                    $providerNombre,

                "mensaje" =>
                    "Proveedor de IA no válido."

            ];

        }


        if(
            !$provider->estaDisponible()
        )
        {

            return [

                "ok" =>
                    false,

                "provider" =>
                    $providerNombre,

                "mensaje" =>
                    "El proveedor de IA seleccionado no está disponible."

            ];

        }


        return
            $provider->generarRespuesta(
                $mensajes,
                $opciones
            );

    }



    // =========================================
    // GENERAR CON FALLBACK
    // =========================================

    public function generarConFallback(
        array $mensajes,
        array $ordenProviders = [],
        array $opciones = []
    )
    {

        if(
            empty(
                $ordenProviders
            )
        )
        {

            $ordenProviders = [

                "groq",

                "gemini"

            ];

        }



        $errores =
            [];



        foreach(
            $ordenProviders
            as
            $providerNombre
        )
        {

            $provider =
                $this->obtenerProveedor(
                    $providerNombre
                );


            if(!$provider)
            {

                continue;

            }


            if(
                !$provider->estaDisponible()
            )
            {

                $errores[] = [

                    "provider" =>
                        $providerNombre,

                    "mensaje" =>
                        "Proveedor no disponible."

                ];


                continue;

            }



            $resultado =
                $provider->generarRespuesta(
                    $mensajes,
                    $opciones
                );



            if(
                !empty(
                    $resultado["ok"]
                )
            )
            {

                return $resultado;

            }



            $errores[] = [

                "provider" =>
                    $providerNombre,

                "mensaje" =>
                    $resultado["mensaje"]
                    ??
                    "Error desconocido."

            ];

        }



        // =========================================
        // NINGUN PROVEEDOR RESPONDIO
        // =========================================

        return [

            "ok" =>
                false,

            "provider" =>
                null,

            "mensaje" =>
                "Ningún proveedor de IA pudo generar una respuesta.",

            "errores" =>
                $errores

        ];

    }

}