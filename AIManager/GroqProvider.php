<?php


class GroqProvider
{

    private $apiKey;

    private $modelo;

    private $endpoint;



    public function __construct()
    {

        $this->apiKey =
            getenv(
                "GROQ_API_KEY"
            );


        $this->modelo =
            "openai/gpt-oss-120b";


        $this->endpoint =
            "https://api.groq.com/openai/v1/chat/completions";

    }



    // =========================================
    // NOMBRE DEL PROVEEDOR
    // =========================================

    public function obtenerNombre()
    {

        return "groq";

    }



    // =========================================
    // VERIFICAR CONFIGURACION
    // =========================================

    public function estaDisponible()
    {

        return
            !empty(
                $this->apiKey
            );

    }



    // =========================================
    // GENERAR RESPUESTA
    // =========================================

    public function generarRespuesta(
        array $mensajes,
        array $opciones = []
    )
    {

        // =========================================
        // VALIDAR API KEY
        // =========================================

        if(
            !$this->estaDisponible()
        )
        {

            return [

                "ok" =>
                    false,

                "provider" =>
                    $this->obtenerNombre(),

                "mensaje" =>
                    "La API de Groq no está configurada."

            ];

        }



        // =========================================
        // OPCIONES
        // =========================================

        $temperature =
            $opciones["temperature"]
            ??
            0.4;


        $maxTokens =
            $opciones["max_tokens"]
            ??
            900;



        // =========================================
        // PAYLOAD
        // =========================================

        $payload = [

            "model" =>
                $this->modelo,


            "messages" =>
                $mensajes,


            "temperature" =>
                $temperature,


            "max_tokens" =>
                $maxTokens

        ];



        // =========================================
        // CONVERTIR JSON
        // =========================================

        $jsonPayload =
            json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE
            );


        if(
            $jsonPayload ===
            false
        )
        {

            return [

                "ok" =>
                    false,

                "provider" =>
                    $this->obtenerNombre(),

                "mensaje" =>
                    "No se pudo preparar la solicitud para Groq."

            ];

        }



        // =========================================
        // CURL
        // =========================================

        $curl =
            curl_init(
                $this->endpoint
            );


        curl_setopt_array(
            $curl,
            [

                CURLOPT_RETURNTRANSFER =>
                    true,


                CURLOPT_POST =>
                    true,


                CURLOPT_HTTPHEADER => [

                    "Content-Type: application/json",

                    "Authorization: Bearer "
                    .
                    $this->apiKey

                ],


                CURLOPT_POSTFIELDS =>
                    $jsonPayload,


                CURLOPT_CONNECTTIMEOUT =>
                    10,


                CURLOPT_TIMEOUT =>
                    40

            ]
        );



        // =========================================
        // EJECUTAR
        // =========================================

        $respuesta =
            curl_exec(
                $curl
            );


        $errorCurl =
            curl_error(
                $curl
            );


        $httpCode =
            curl_getinfo(
                $curl,
                CURLINFO_HTTP_CODE
            );


        curl_close(
            $curl
        );



        // =========================================
        // ERROR CURL
        // =========================================

        if(
            $respuesta ===
            false
        )
        {

            error_log(
                "DEVIOZ AI - Groq CURL: "
                .
                $errorCurl
            );


            return [

                "ok" =>
                    false,

                "provider" =>
                    $this->obtenerNombre(),

                "mensaje" =>
                    "No se pudo conectar con Groq."

            ];

        }



        // =========================================
        // DECODIFICAR RESPUESTA
        // =========================================

        $datos =
            json_decode(
                $respuesta,
                true
            );



        // =========================================
        // ERROR HTTP
        // =========================================

        if(
            $httpCode < 200
            ||
            $httpCode >= 300
        )
        {

            $detalleError =
                $datos[
                    "error"
                ][
                    "message"
                ]
                ??
                "Error desconocido.";


            error_log(
                "DEVIOZ AI - Groq HTTP "
                .
                $httpCode
                .
                ": "
                .
                $detalleError
            );


            return [

                "ok" =>
                    false,

                "provider" =>
                    $this->obtenerNombre(),

                "http_code" =>
                    $httpCode,

                "mensaje" =>
                    "Groq no pudo generar una respuesta."

            ];

        }



        // =========================================
        // EXTRAER TEXTO
        // =========================================

        $texto =
            $datos[
                "choices"
            ][0][
                "message"
            ][
                "content"
            ]
            ??
            "";


        $texto =
            trim(
                $texto
            );



        // =========================================
        // VALIDAR TEXTO
        // =========================================

        if(
            $texto ===
            ""
        )
        {

            return [

                "ok" =>
                    false,

                "provider" =>
                    $this->obtenerNombre(),

                "mensaje" =>
                    "Groq devolvió una respuesta vacía."

            ];

        }



        // =========================================
        // RESPUESTA EXITOSA
        // =========================================

        return [

            "ok" =>
                true,

            "provider" =>
                $this->obtenerNombre(),

            "model" =>
                $this->modelo,

            "respuesta" =>
                $texto

        ];

    }

}