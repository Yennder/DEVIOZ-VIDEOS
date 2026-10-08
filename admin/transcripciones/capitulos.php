<?php

require_once '../../config/sesion.php';
verificarAdmin();
require_once '../../controllers/CapituloIAController.php';

$idVideo = filter_input(INPUT_GET, 'id_video', FILTER_VALIDATE_INT) ?: 0;
if ($idVideo <= 0) {
    header('Location: listar.php');
    exit;
}

$controller = new CapituloIAController();
$mensaje = trim((string)($_GET['msg'] ?? ''));
$error = trim((string)($_GET['error'] ?? ''));

try {
    $estado = $controller->estadoAdmin($idVideo);
} catch (Throwable $e) {
    header('Location: listar.php?error=' . urlencode($e->getMessage()));
    exit;
}

$video = $estado['video'];
$borrador = $estado['borrador'];
$publicada = $estado['publicada'];

function capituloTiempo(float $segundos): string
{
    $total = max(0, (int)round($segundos));
    $h = intdiv($total, 3600);
    $resto = $total % 3600;
    $m = intdiv($resto, 60);
    $s = $resto % 60;
    return $h > 0 ? sprintf('%02d:%02d:%02d', $h, $m, $s) : sprintf('%02d:%02d', $m, $s);
}

function capituloFecha(?string $fecha): string
{
    if (!$fecha) {
        return '-';
    }
    $ts = strtotime($fecha);
    return $ts ? date('d/m/Y H:i', $ts) : $fecha;
}

function capituloConceptosTexto(array $conceptos): string
{
    return implode(', ', array_map(static fn($v): string => trim((string)$v), $conceptos));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Capítulos inteligentes - DEVIOZ VIDEOS</title>
<link rel="stylesheet" href="../../assets/css/admin.css">
<link rel="stylesheet" href="../../assets/css/capitulos_admin.css">
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="admin-main">
<?php include '../includes/navbar.php'; ?>
<section class="admin-content ai-chapters-admin-page">
    <div class="gestion-header">
        <div>
            <span class="admin-nav-label">DEVIOZ AI · V4.4.3</span>
            <h1>Capítulos inteligentes</h1>
            <p><?php echo htmlspecialchars((string)$video['titulo']); ?></p>
        </div>
        <div class="acciones-contenedor">
            <a class="btn-limpiar" href="editar.php?id_video=<?php echo (int)$idVideo; ?>">Volver a transcripción</a>
            <a class="btn-editar" target="_blank" href="../../public/detalle.php?id=<?php echo (int)$idVideo; ?>">Ver video</a>
        </div>
    </div>

    <?php if ($mensaje !== ''): ?>
        <div class="transcription-admin-alert is-success"><?php echo htmlspecialchars($mensaje); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="transcription-admin-alert is-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if (!$estado['instalado']): ?>
        <div class="transcription-admin-alert is-error">
            <strong>Falta instalar V4.4.3.</strong>
            <span>Importa <code>database/migracion_v4_4_3_capitulos_inteligentes.sql</code> en <code>devioz_videos</code>.</span>
        </div>
    <?php else: ?>

    <div class="ai-chapters-overview">
        <article>
            <span>Transcripción</span>
            <strong><?php echo $estado['fuente_disponible'] ? 'Lista' : 'No disponible'; ?></strong>
            <small><?php echo (int)$estado['segmentos']; ?> segmentos</small>
        </article>
        <article>
            <span>Duración</span>
            <strong><?php echo htmlspecialchars(capituloTiempo((float)$estado['duracion_segundos'])); ?></strong>
            <small>Referencia temporal</small>
        </article>
        <article>
            <span>Borrador IA</span>
            <strong><?php echo $borrador ? count($borrador['capitulos']) . ' capítulos' : 'Sin borrador'; ?></strong>
            <small><?php echo $borrador ? capituloFecha($borrador['generacion']['fecha_generacion'] ?? null) : 'Genera una propuesta'; ?></small>
        </article>
        <article>
            <span>Publicado</span>
            <strong><?php echo $publicada ? count($publicada['capitulos']) . ' capítulos' : 'Aún no'; ?></strong>
            <small><?php echo $publicada ? capituloFecha($publicada['generacion']['fecha_publicacion'] ?? null) : 'Los usuarios todavía no ven escenas'; ?></small>
        </article>
    </div>

    <?php if (!$estado['fuente_disponible']): ?>
        <div class="ai-chapters-source-note is-warning">
            <strong>No se puede generar todavía.</strong>
            <span><?php echo htmlspecialchars((string)$estado['motivo']); ?></span>
        </div>
    <?php else: ?>
        <?php if (!empty($estado['publicada_desactualizada'])): ?>
            <div class="ai-chapters-source-note is-warning">
                <strong>La transcripción cambió después de la publicación.</strong>
                <span>La versión publicada sigue visible, pero conviene generar una propuesta nueva y revisarla.</span>
            </div>
        <?php endif; ?>
        <?php if (!empty($estado['borrador_desactualizado'])): ?>
            <div class="ai-chapters-source-note is-warning">
                <strong>El borrador usa una versión anterior de la transcripción.</strong>
                <span>Regenera la propuesta antes de publicarla para trabajar con el contenido más reciente.</span>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <section class="ai-chapters-generate-card">
        <div>
            <span class="admin-nav-label">GENERACIÓN ASISTIDA</span>
            <h2>Propuesta de escenas con DEVIOZ AI</h2>
            <p>La IA analiza la transcripción con timestamps, agrupa cambios de tema y crea un borrador. Nada se publica sin tu revisión.</p>
        </div>
        <?php if ($estado['fuente_disponible']): ?>
        <form method="POST" action="capitulos_accion.php" onsubmit="return confirm('<?php echo $borrador ? 'Se archivará el borrador actual y se generará uno nuevo. ¿Continuar?' : '¿Generar una propuesta de capítulos con DEVIOZ AI?'; ?>')">
            <?php echo csrfInput(); ?>
            <input type="hidden" name="accion" value="generar">
            <input type="hidden" name="id_video" value="<?php echo (int)$idVideo; ?>">
            <button type="submit" class="btn"><?php echo $borrador ? '↻ Regenerar propuesta IA' : '✨ Generar capítulos con IA'; ?></button>
        </form>
        <?php endif; ?>
    </section>

    <?php if ($borrador): ?>
    <?php $gen = $borrador['generacion']; ?>
    <section class="ai-chapters-editor-card">
        <div class="ai-chapters-editor-head">
            <div>
                <span class="admin-nav-label">BORRADOR EN REVISIÓN</span>
                <h2>Edita antes de publicar</h2>
                <?php $proveedorTexto = trim(implode(' · ', array_filter([(string)($gen['proveedor'] ?? ''), (string)($gen['modelo'] ?? '')]))); ?>
                <p>Proveedor: <strong><?php echo htmlspecialchars($proveedorTexto !== '' ? $proveedorTexto : '-'); ?></strong> · generado <?php echo htmlspecialchars(capituloFecha($gen['fecha_generacion'] ?? null)); ?></p>
            </div>
            <form method="POST" action="capitulos_accion.php" onsubmit="return confirm('¿Descartar este borrador? La versión publicada no se modificará.')">
                <?php echo csrfInput(); ?>
                <input type="hidden" name="accion" value="descartar">
                <input type="hidden" name="id_video" value="<?php echo (int)$idVideo; ?>">
                <input type="hidden" name="id_generacion" value="<?php echo (int)$gen['id_generacion']; ?>">
                <button type="submit" class="btn-eliminar">Descartar borrador</button>
            </form>
        </div>

        <form method="POST" action="capitulos_accion.php" id="chapterDraftForm">
            <?php echo csrfInput(); ?>
            <input type="hidden" name="id_video" value="<?php echo (int)$idVideo; ?>">
            <input type="hidden" name="id_generacion" value="<?php echo (int)$gen['id_generacion']; ?>">

            <div class="ai-chapter-editor-list" id="chapterEditorList" data-duration="<?php echo htmlspecialchars((string)$estado['duracion_segundos']); ?>">
                <?php foreach ($borrador['capitulos'] as $index => $capitulo): ?>
                <article class="ai-chapter-editor-row" data-chapter-row>
                    <div class="ai-chapter-editor-number" data-chapter-number><?php echo $index + 1; ?></div>
                    <div class="ai-chapter-editor-fields">
                        <div class="ai-chapter-editor-grid">
                            <label>
                                <span>Inicio</span>
                                <input type="text" name="inicio[]" value="<?php echo htmlspecialchars(capituloTiempo((float)$capitulo['inicio_segundos'])); ?>" placeholder="00:00" required>
                            </label>
                            <label class="is-wide">
                                <span>Título</span>
                                <input type="text" name="titulo[]" maxlength="180" value="<?php echo htmlspecialchars((string)$capitulo['titulo']); ?>" required>
                            </label>
                        </div>
                        <label>
                            <span>Resumen de la escena</span>
                            <textarea name="resumen[]" rows="2" maxlength="2000"><?php echo htmlspecialchars((string)$capitulo['resumen']); ?></textarea>
                        </label>
                        <label>
                            <span>Conceptos <small>(separados por coma)</small></span>
                            <input type="text" name="conceptos[]" maxlength="600" value="<?php echo htmlspecialchars(capituloConceptosTexto($capitulo['conceptos'] ?? [])); ?>">
                        </label>
                    </div>
                    <button type="button" class="ai-chapter-remove" data-remove-chapter aria-label="Eliminar capítulo">×</button>
                </article>
                <?php endforeach; ?>
            </div>

            <div class="ai-chapters-editor-toolbar">
                <button type="button" class="btn-editar" id="btnAddChapter">+ Agregar capítulo</button>
                <span>Al guardar, los capítulos se ordenan automáticamente por su tiempo de inicio.</span>
            </div>

            <div class="ai-chapters-savebar">
                <div>
                    <strong>Revisión humana obligatoria</strong>
                    <small>Guardar no cambia lo que ven los usuarios hasta que publiques.</small>
                </div>
                <div class="acciones-contenedor">
                    <button type="submit" name="accion" value="guardar" class="btn-editar">Guardar borrador</button>
                    <button type="submit" name="accion" value="guardar_publicar" class="btn" onclick="return confirm('¿Guardar y publicar estos capítulos para los usuarios?')">✓ Guardar y publicar</button>
                </div>
            </div>
        </form>
    </section>
    <?php endif; ?>

    <?php if ($publicada): ?>
    <section class="ai-chapters-published-card">
        <div class="ai-chapters-editor-head">
            <div>
                <span class="admin-nav-label">VERSIÓN PUBLICADA</span>
                <h2>Capítulos visibles para los usuarios</h2>
                <p>Publicados <?php echo htmlspecialchars(capituloFecha($publicada['generacion']['fecha_publicacion'] ?? null)); ?>. Una nueva propuesta no reemplaza esta versión hasta que la apruebes.</p>
            </div>
            <span class="ai-chapter-state-pill">Publicada</span>
        </div>
        <div class="ai-chapters-preview-grid">
            <?php foreach ($publicada['capitulos'] as $index => $capitulo): ?>
            <article>
                <div><span><?php echo str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT); ?></span><time><?php echo htmlspecialchars(capituloTiempo((float)$capitulo['inicio_segundos'])); ?></time></div>
                <h3><?php echo htmlspecialchars((string)$capitulo['titulo']); ?></h3>
                <?php if (!empty($capitulo['resumen'])): ?><p><?php echo htmlspecialchars((string)$capitulo['resumen']); ?></p><?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php endif; ?>
</section>
</div>
<script src="../../assets/js/capitulos_admin.js"></script>
</body>
</html>
