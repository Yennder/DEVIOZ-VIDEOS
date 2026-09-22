<?php

return [
    // Modelo recomendado para comenzar en CPU.
    // Opciones soportadas por la interfaz: tiny, base, small, medium, large-v3.
    'modelo' => getenv('DEVIOZ_WHISPER_MODEL') ?: 'small',

    // Idioma principal del contenido. Usa 'es' para tus videos actuales.
    'idioma' => getenv('DEVIOZ_WHISPER_LANGUAGE') ?: 'es',

    // El worker usa estas variables de entorno si deseas cambiarlas:
    // DEVIOZ_WHISPER_DEVICE=cpu|cuda
    // DEVIOZ_WHISPER_COMPUTE_TYPE=int8|float16|...
];
