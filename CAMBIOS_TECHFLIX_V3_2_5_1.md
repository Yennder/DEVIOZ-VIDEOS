# TechFlix V3.2.5.1 - Skills manuales + deteccion automatica con IA

## Objetivo
Incorporar un catalogo de competencias y permitir que cada curso pueda definir las skills que desarrolla de dos maneras:

1. Asignacion manual por el administrador.
2. Deteccion automatica con DEVIOZ AI a partir de las transcripciones ya generadas por faster-whisper.

## Flujo de deteccion automatica

Curso -> lecciones -> transcripciones completadas -> DEVIOZ AI -> skills sugeridas -> revision del administrador -> guardar.

DEVIOZ AI usa Groq como proveedor principal y Gemini como respaldo, respetando la configuracion existente de la plataforma.

La IA propone:
- skill;
- categoria;
- peso relativo;
- nivel objetivo;
- confianza;
- justificacion breve.

La IA no publica nada automaticamente. El administrador puede cambiar pesos, niveles, desmarcar sugerencias o continuar usando el modo manual.

## Skills nuevas
Si la IA identifica una competencia que no existe en el catalogo, la muestra como "Nueva sugerida". Solo se crea en la base de datos cuando el administrador la aprueba.

## Cobertura de transcripciones
La pantalla indica cuantas lecciones del curso estan transcritas. Se puede analizar un curso parcialmente transcrito, aunque se recomienda completar todas las transcripciones para mejorar el resultado.

## Trazabilidad
Las asociaciones generadas desde IA almacenan:
- origen = ia;
- confianza;
- justificacion;
- proveedor;
- modelo;
- fecha del analisis.

Las asociaciones creadas manualmente quedan con origen = manual.

## No modifica
- transcripciones existentes;
- videos;
- capacitaciones;
- evaluaciones;
- certificados;
- notificaciones;
- archivos de Groq/Gemini;
- uploads.
