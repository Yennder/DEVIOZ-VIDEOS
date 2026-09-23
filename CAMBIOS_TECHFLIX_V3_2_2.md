# TechFlix V3.2.2 - Evaluaciones mejoradas

## Objetivo
Mejorar la retroalimentacion del alumno cuando no aprueba una evaluacion y agota todos los intentos disponibles, sin revelar respuestas mientras aun pueda volver a rendir el examen.

## Cambios
- Si el alumno falla un intento pero aun tiene oportunidades, solo ve su puntaje y cuantos intentos le quedan.
- Las respuestas correctas permanecen ocultas mientras haya intentos disponibles.
- Si el alumno agota todos sus intentos sin aprobar, se habilita una revision final.
- La revision muestra por pregunta:
  - respuesta seleccionada por el alumno;
  - respuesta correcta;
  - indicador de acierto/error;
  - puntos obtenidos frente a puntos posibles.
- Se agrega un resumen con puntaje final, respuestas correctas y puntos obtenidos.
- Desde la pantalla del curso, cuando los intentos estan agotados, el boton cambia de "Rendir evaluacion" a "Revisar evaluacion".
- La revision solo puede consultarla el usuario propietario de la asignacion.

## Base de datos
No requiere migracion SQL. V3.2.2 reutiliza `learning_intentos`, `learning_respuestas`, `learning_preguntas` y `learning_opciones`.
