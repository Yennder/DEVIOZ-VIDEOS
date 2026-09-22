# DEVIOZ VIDEOS + TechFlix Learning Lab V2


> **Actualización V2:** esta entrega corrige el flujo de progreso, autoplay, evaluación obligatoria, tema del administrador, tablas de acciones, tipografía y scrolls. Si ya tenías V1 funcionando, **no importes nuevamente ningún SQL**. Conserva tu base actual y sigue `INSTALAR_V2_SIN_PERDER_DATOS.md`.

Esta entrega agrega el **núcleo funcional de capacitación y cursos** sobre la plataforma DEVIOZ existente. No reemplaza el catálogo audiovisual, las series, los videos, las interacciones ni las integraciones de IA actuales.

## Qué agrega esta versión

### Administración

Nuevo bloque **TechFlix Learning Lab** en el panel administrativo con:

- Dashboard de aprendizaje.
- CRUD funcional de cursos.
- Lecciones de curso reutilizando videos ya publicados.
- Capacitaciones vinculadas a un curso.
- Asignación de una capacitación a uno o varios usuarios.
- Fecha de inicio y fecha límite.
- Seguimiento por usuario: progreso, estado, vencimiento y mejor nota.
- Evaluación final por curso.
- Preguntas de opción múltiple y Verdadero/Falso.
- Nota mínima e intentos permitidos.
- Logros/insignias automáticas y configurables.

### Usuario

Nuevo módulo **Mi aprendizaje** con:

- Capacitaciones asignadas.
- Fecha límite y días restantes.
- Estado: pendiente, en progreso, completada o vencida.
- Progreso de lecciones.
- Acceso al video de cada lección.
- Sincronización automática del progreso del reproductor existente (90% = lección completada).
- Evaluación final desbloqueada al completar las lecciones obligatorias; la capacitación solo queda completada al aprobarla.
- Registro de intentos, porcentaje, aprobado/no aprobado.
- Logros obtenidos y logros por desbloquear.
- Pantalla de progreso general.

## Qué NO cambia en esta fase

No se modificaron las integraciones técnicas existentes de IA:

- `AIManager/AIManager.php`
- `AIManager/GroqProvider.php`
- `AIManager/GeminiProvider.php`
- `api/devioz_ai.php`
- `models/DeviozAIContext.php`

Esta fase **no agrega** transcripción, RAG, IA contextual al video, generación automática de exámenes, Tech Reality Check ni Aprender por Escenas. Esas funciones quedan para una etapa posterior.

## Actualizar tu instalación actual sin perder videos

### 1. Respaldar

Antes de actualizar:

1. Exporta `devioz_videos` desde phpMyAdmin.
2. Copia tu carpeta actual `C:\xampp\htdocs\DEVIOZ-VIDEOS\uploads` a un respaldo.
3. Si deseas máxima seguridad, renombra tu proyecto actual a `DEVIOZ-VIDEOS-RESPALDO`.

### 2. Base de datos en V2

Si vienes de **Learning V1**, no debes importar ningún SQL. Conserva tu base `devioz_videos` actual con tus videos, cursos, capacitaciones, evaluaciones y usuarios.

`database/migracion_learning_v1.sql` se mantiene únicamente para una instalación que todavía no tenga el módulo Learning.

### 3. Actualizar el código

Extrae el ZIP nuevo como:

```text
C:\xampp\htdocs\DEVIOZ-VIDEOS
```

Después fusiona el contenido de tu respaldo de `uploads` dentro de la nueva carpeta `uploads`.

**No reemplaces la carpeta nueva completa a ciegas:** conserva el `.htaccess` incluido en la versión nueva y copia dentro tus videos, miniaturas, portadas y logo.

## Flujo para presentación

### Administrador

```text
Curso
  ↓
Agregar lecciones (videos existentes)
  ↓
Crear capacitación
  ↓
Definir inicio / fecha límite
  ↓
Asignar usuarios
  ↓
Configurar evaluación
  ↓
Seguimiento de progreso y notas
```

### Usuario

```text
Mi aprendizaje
  ↓
Capacitación asignada
  ↓
Lecciones / videos
  ↓
Progreso
  ↓
Evaluación final
  ↓
Curso completado
  ↓
Logro / insignia
```

## Tablas nuevas

La migración crea:

```text
learning_cursos
learning_curso_lecciones
learning_capacitaciones
learning_asignaciones
learning_progreso_lecciones
learning_evaluaciones
learning_preguntas
learning_opciones
learning_intentos
learning_respuestas
learning_logros
learning_usuario_logros
```

Todas se relacionan con las tablas existentes `usuarios` y `videos` mediante claves foráneas. No se duplican archivos MP4: una lección referencia el `id_video` ya existente.

## Logros incluidos por defecto

La migración agrega tres reglas iniciales:

- **Primer curso**: completar la primera capacitación.
- **Constancia**: completar cinco capacitaciones.
- **Nota perfecta**: obtener 100% en una evaluación.

El administrador puede crear logros adicionales y asociarlos a la finalización de un curso específico.

## Requisitos

- Windows + XAMPP.
- Apache.
- MariaDB/MySQL.
- PHP 8.2 compatible.
- `pdo_mysql`, `mbstring`, `fileinfo`, `curl` según las funciones ya existentes del proyecto.

## Importante sobre los archivos multimedia

Este ZIP de trabajo se entrega sin tus capítulos pesados. Los registros de tu base actual siguen apuntando a sus nombres de archivo. Después de actualizar, vuelve a fusionar:

```text
uploads/videos/
uploads/thumbnails/
uploads/series/
uploads/config/
```

desde tu respaldo.

## Validación realizada

- Sintaxis PHP verificada en todo el proyecto.
- Revisión de relaciones y nombres de las nuevas tablas.
- Rutas administrativas y públicas revisadas.
- CSRF conservado en operaciones POST.
- Rol administrador requerido en los módulos administrativos.
- Archivos técnicos de IA comparados para verificar que no se alteraron.

La prueba final end-to-end de MySQL debe realizarse en tu XAMPP después de importar la migración, porque el entorno de preparación no dispone de un servidor MariaDB local.

---

## TECHFLIX V3 - Transcripcion local

La V3 agrega transcripcion automatica local con faster-whisper, subtitulos VTT, transcripcion sincronizada y modo contextual de DEVIOZ AI.

Antes de usar el modulo consulta:

- `INSTALAR_V3_TRANSCRIPCION_LOCAL.md`
- `CAMBIOS_TECHFLIX_V3.md`
- `database/LEEME_V3_IMPORTAR_MIGRACION.txt`
- `python/transcripcion/GUIA_RAPIDA.txt`

## TechFlix V3.1

Esta entrega incorpora mejoras de reproductor, inicio, playlists y comunidad. Para conservar la base existente, importa únicamente `database/migracion_v3_1_interacciones.sql`. Revisa `CAMBIOS_TECHFLIX_V3_1.md` e `INSTALAR_V3_1.md`.
