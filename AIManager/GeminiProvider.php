<?php


class GeminiProvider
{

    private $apiKey;

    private $modelo;

    private $endpointBase;



    public function __construct()
    {

        $this->apiKey =
            getenv(
                "GEMINI_API_KEY"
            );


        $this->modelo =
            "gemini-3.8-flash";


        $this->endpointBase =
            "https://generativelanguage.googleapis.com/v1beta/models/";

    }



    // =========================================
    // NOMBRE DEL PROVEEDOR
    // =========================================

    public function obtenerNombre()
    {

        return "gemini";

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
                    "La API de Gemini no está configurada."

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
        // SEPARAR SYSTEM PROMPT
        // =========================================

        $systemInstruction =
            "";


        $contents =
            [];



        foreach(
            $mensajes
            as
            $mensaje
        )
        {

            if(
                !is_array(
                    $mensaje
                )
            )
            {

                continue;

            }


            $role =
                $mensaje["role"]
                ??
                "";


            $content =
                trim(
                    $mensaje["content"]
                    ??
                    ""
                );


            if($content === "")
            {

                continue;

            }



            // =========================================
            // SYSTEM
            // =========================================

            if($role === "system")
            {

                if(
                    $systemInstruction !==
                    ""
                )
                {

                    $systemInstruction .=
                        "\n\n";

                }


                $systemInstruction .=
                    $content;


                continue;

            }



            // =========================================
            // USER
            // =========================================

            if($role === "user")
            {

                $contents[] = [

                    "role" =>
                        "user",

                    "parts" => [

                        [

                            "text" =>
                                $content

                        ]

                    ]

                ];


                continue;

            }



            // =========================================
            // ASSISTANT -> MODEL
            // =========================================

            if($role === "assistant")
            {

                $contents[] = [

                    "role" =>
                        "model",

                    "parts" => [

                        [

                            "text" =>
                                $content

                        ]

                    ]

                ];

            }

        }



        // =========================================
        // VALIDAR CONTENIDO
        // =========================================

        if(empty($contents))
        {

            return [

                "ok" =>
                    false,

                "provider" =>
                    $this->obtenerNombre(),

                "mensaje" =>
                    "No hay mensajes válidos para enviar a Gemini."

            ];

        }



        // =========================================
        // PAYLOAD
        // =========================================

        $payload = [

            "contents" =>
                $contents,


            "generationConfig" => [

                "temperature" =>
                    $temperature,

                "maxOutputTokens" =>
                    $maxTokens

            ]

        ];



        // =========================================
        // SYSTEM INSTRUCTION
        // =========================================

        if(
            $systemInstruction !==
            ""
        )
        {

            $payload[
                "systemInstruction"
            ] = [

                "parts" => [

                    [

                        "text" =>
                            $systemInstruction

                    ]

                ]

            ];

        }



        // =========================================
        // JSON
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
                    "No se pudo preparar la solicitud para Gemini."

            ];

        }



        // =========================================
        // ENDPOINT
        // =========================================

        $endpoint =
            $this->endpointBase
            .
            $this->modelo
            .
            ":generateContent";



        // =========================================
        // CURL
        // =========================================

        $curl =
            curl_init(
                $endpoint
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

                    "x-goog-api-key: "
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
                "DEVIOZ AI - Gemini CURL: "
                .
                $errorCurl
            );


            return [

                "ok" =>
                    false,

                "provider" =>
                    $this->obtenerNombre(),

                "mensaje" =>
                    "No se pudo conectar con Gemini."

            ];

        }



        // =========================================
        // DECODIFICAR
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
                "DEVIOZ AI - Gemini HTTP "
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
                    "Gemini no pudo generar una respuesta."

            ];

        }



        // =========================================
        // EXTRAER TEXTO
        // =========================================

        $texto =
            $datos[
                "candidates"
            ][0][
                "content"
            ][
                "parts"
            ][0][
                "text"
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
                    "Gemini devolvió una respuesta vacía."

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