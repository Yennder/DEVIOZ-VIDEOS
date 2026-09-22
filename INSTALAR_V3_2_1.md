# Instalacion V3.2.1 como parche

1. Haz commit o respaldo del estado actual V3.1.
2. Copia los archivos del parche respetando sus carpetas y reemplaza los existentes.
3. En phpMyAdmin selecciona `devioz_videos`.
4. Importa SOLO `database/migracion_v3_2_1_learning_progress.sql`.
5. Haz Ctrl+F5.
6. Prueba una capacitacion con una leccion que aun no este completada.

## Pruebas recomendadas
- Intentar arrastrar el video hacia adelante: debe regresar al maximo visto.
- Retroceder: debe permitirse.
- Salir y volver: debe reanudar desde el progreso academico.
- Abrir el mismo video fuera del curso: debe seguir funcionando como video publico.
- Revisar `videos.vistas`: una reproduccion desde la capacitacion no debe incrementarlo.
- Llegar a 90%: la leccion debe aparecer completada.
