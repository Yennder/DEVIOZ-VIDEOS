# TechFlix V3.2.5.2 - Skills del trabajador

Este avance lleva las competencias configuradas en V3.2.5.1 a la experiencia del usuario.

## Incluye
- Nueva pantalla publica **Mi perfil tecnologico**.
- Enlace **Mis skills** en Learning Lab.
- Vista resumida de skills dentro de **Mi progreso**.
- Calculo dinamico usando evidencia academica real.
- Desglose de evidencia por curso.
- Nivel estimado: Pendiente, En desarrollo, Basico, Intermedio o Avanzado.
- Nivel objetivo heredado de la configuracion del curso.
- Diseño responsive y compatible con tema claro/oscuro.

## Regla de calculo por curso
Si existe evaluacion publicada:
- 40% progreso de lecciones obligatorias.
- 40% mejor nota de evaluacion.
- 20% finalizacion satisfactoria de la capacitacion.

Si no existe evaluacion:
- 80% progreso de lecciones.
- 20% finalizacion.

Cuando una skill aparece en varios cursos, se combina la mejor evidencia de cada curso ponderada por la relevancia asignada a esa skill dentro del curso.

## Importante
No requiere nuevas tablas ni migracion SQL. Reutiliza las tablas de V3.2.5.1.
