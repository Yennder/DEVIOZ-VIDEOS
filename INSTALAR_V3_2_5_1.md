# Instalar TechFlix V3.2.5.1

> Esta version REEMPLAZA el parche preliminar V3.2.5.1-SKILLS que no llegaste a instalar. No instales ambos.

## 1. Copiar el parche
Copia el contenido del ZIP sobre tu proyecto actual:

`C:\xampp\htdocs\DEVIOZ-VIDEOS`

No reemplaces ni borres `uploads`.

## 2. Base de datos
En phpMyAdmin selecciona `devioz_videos` e importa solamente:

`database/migracion_v3_2_5_1_skills_ia.sql`

No vuelvas a importar tu base completa.

## 3. Refrescar
Usa `Ctrl + F5`.

## 4. Prueba sugerida
1. Administrador -> Skills: verificar el catalogo inicial.
2. Crear una skill manual de prueba y editarla.
3. Cursos -> Introduccion a Docker -> Skills.
4. Revisar cobertura de transcripciones.
5. Pulsar `Detectar skills con DEVIOZ AI`.
6. Confirmar que propone skills, pesos, nivel y justificacion.
7. Cambiar un peso y verificar que el total siga en 100%.
8. Desmarcar una sugerencia y ajustar el resto hasta 100%.
9. Aprobar y aplicar.
10. Volver a entrar: las skills deben quedar asociadas y mostrar `Detectada por IA`.
11. Probar tambien `Guardar skills manualmente`.
12. Si la IA sugiere una skill nueva, aprobarla y comprobar que aparece en el catalogo.

## Requisitos
- Las tablas de transcripcion V3 deben existir.
- Al menos una leccion del curso debe tener transcripcion completada para usar la deteccion automatica.
- Debe estar configurado GROQ_API_KEY o GEMINI_API_KEY igual que en DEVIOZ AI.
