<?php
require_once '../config/sesion.php';
verificarSesion();
require_once '../controllers/LearningController.php';
require_once '../controllers/VideoController.php';
require_once '../includes/learning_helpers.php';

$learning = new LearningController();
$videoController = new VideoController();
$categorias = $videoController->listarCategorias();
$idUsuario = (int)$_SESSION['id_usuario'];
$idAsignacion = (int)($_GET['asignacion'] ?? $_POST['asignacion'] ?? 0);
$resultado = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrfPost();
    try {
        $resultado = $learning->registrarIntento($idAsignacion, $idUsuario, $_POST['respuesta'] ?? []);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$detalle = $learning->detalleAsignacion($idAsignacion, $idUsuario);
if (!$detalle || !$detalle['evaluacion']) {
    header('Location:aprendizaje.php');
    exit;
}

$eval = $detalle['evaluacion'];
$preguntas = $learning->preguntasEvaluacion((int)$eval['id_evaluacion']);
$intentos = $learning->intentosAsignacion($idAsignacion, (int)$eval['id_evaluacion']);
$revision = $learning->revisionEvaluacionAgotada($idAsignacion, $idUsuario);
$permiso = $resultado ? ['ok' => false] : $learning->puedeRendirEvaluacion($idAsignacion, $idUsuario);
$intentosUsados = count($intentos);
$intentosPermitidos = (int)$eval['intentos_permitidos'];
$intentosRestantes = max(0, $intentosPermitidos - $intentosUsados);
?>
<?php include '../includes/public_header.php'; ?>
<?php include '../includes/public_navbar.php'; ?>
<div class="layout">
    <?php include '../includes/public_sidebar.php'; ?>
    <main class="public-content learning-public-page">
        <a class="learning-back-link" href="curso.php?asignacion=<?php echo $idAsignacion; ?>">← Volver al curso</a>

        <section class="page-hero-compact learning-exam-header">
            <span class="section-kicker">Evaluación final</span>
            <h1><?php echo learningH($eval['titulo']); ?></h1>
            <p><?php echo learningH($eval['descripcion']); ?></p>
            <div class="learning-course-hero-meta">
                <span>Nota mínima <?php echo round((float)$eval['nota_minima']); ?>%</span>
                <span>Intentos <?php echo $intentosUsados; ?>/<?php echo $intentosPermitidos; ?></span>
                <span><?php echo count($preguntas); ?> preguntas</span>
            </div>
        </section>

        <?php if ($resultado): ?>
            <section class="learning-result-card <?php echo $resultado['aprobado'] ? 'is-pass' : 'is-fail'; ?>">
                <div class="learning-result-score"><?php echo round((float)$resultado['porcentaje'], 1); ?>%</div>
                <div>
                    <span class="section-kicker">Resultado del intento <?php echo (int)$resultado['numero_intento']; ?></span>
                    <h2><?php echo $resultado['aprobado'] ? '¡Evaluación aprobada!' : 'Aún no alcanzaste la nota mínima'; ?></h2>
                    <?php if ($resultado['aprobado']): ?>
                        <p>La nota mínima es <?php echo round((float)$resultado['nota_minima']); ?>%. La capacitación queda registrada como completada.</p>
                    <?php elseif ($revision): ?>
                        <p>Agotaste los intentos disponibles. Ya puedes revisar cada pregunta, tu respuesta y la respuesta correcta.</p>
                    <?php else: ?>
                        <p>La nota mínima es <?php echo round((float)$resultado['nota_minima']); ?>%. Te quedan <strong><?php echo $intentosRestantes; ?></strong> intento(s). Revisa las lecciones antes de volver a intentarlo.</p>
                    <?php endif; ?>

                    <?php if ($resultado['nuevos_logros']): ?>
                        <div class="learning-new-achievements">
                            <strong>Nuevos logros:</strong>
                            <?php foreach ($resultado['nuevos_logros'] as $l): ?>
                                <span><?php echo learningH($l['icono'] . ' ' . $l['nombre']); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="learning-result-actions">
                        <?php if (!$resultado['aprobado'] && !$revision && $intentosRestantes > 0): ?>
                            <a class="btn-primary-modern" href="evaluacion.php?asignacion=<?php echo $idAsignacion; ?>">Intentar nuevamente</a>
                        <?php elseif ($revision): ?>
                            <a class="btn-primary-modern" href="#revision-final">Revisar respuestas</a>
                        <?php else: ?>
                            <a class="btn-primary-modern" href="curso.php?asignacion=<?php echo $idAsignacion; ?>">Volver al curso</a>
                        <?php endif; ?>
                        <a class="btn-secondary-modern" href="curso.php?asignacion=<?php echo $idAsignacion; ?>">Curso</a>
                        <a class="btn-secondary-modern" href="logros.php">Ver logros</a>
                    </div>
                </div>
            </section>

        <?php elseif (!$permiso['ok'] && !$revision): ?>
            <div class="empty-state">
                <span class="empty-icon">🔒</span>
                <h3>Evaluación no disponible</h3>
                <p><?php echo learningH($permiso['motivo'] ?? 'No puedes rendir esta evaluación ahora.'); ?></p>
                <a class="btn-primary-modern" href="curso.php?asignacion=<?php echo $idAsignacion; ?>">Volver al curso</a>
            </div>

        <?php elseif (!$revision): ?>
            <?php if ($error): ?>
                <div class="learning-alert-danger"><?php echo learningH($error); ?></div>
            <?php endif; ?>
            <form method="POST" class="learning-exam-form" onsubmit="return confirm('¿Enviar tus respuestas? El intento quedará registrado.');">
                <?php echo csrfInput(); ?>
                <input type="hidden" name="asignacion" value="<?php echo $idAsignacion; ?>">
                <?php foreach ($preguntas as $idx => $p): ?>
                    <article class="learning-question">
                        <div class="learning-question-heading">
                            <span><?php echo str_pad((string)($idx + 1), 2, '0', STR_PAD_LEFT); ?></span>
                            <h3><?php echo learningH($p['pregunta']); ?></h3>
                            <small><?php echo learningH($p['puntos']); ?> pts</small>
                        </div>
                        <div class="learning-answer-list">
                            <?php foreach ($p['opciones'] as $o): ?>
                                <label>
                                    <input type="radio" name="respuesta[<?php echo (int)$p['id_pregunta']; ?>]" value="<?php echo (int)$o['id_opcion']; ?>" required>
                                    <span><?php echo learningH($o['texto']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
                <div class="learning-exam-submit">
                    <p>Revisa tus respuestas antes de enviar. Las respuestas correctas solo se mostrarán si agotas todos tus intentos sin aprobar.</p>
                    <button class="btn-primary-modern" type="submit">Finalizar evaluación</button>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($revision): ?>
            <?php $revIntento = $revision['intento']; ?>
            <section class="learning-review-section" id="revision-final">
                <div class="section-heading-row learning-review-title-row">
                    <div>
                        <span class="section-kicker">Retroalimentación final</span>
                        <h2>Revisión del último intento</h2>
                        <p>Ya utilizaste todos los intentos permitidos. Esta revisión te muestra en qué acertaste y qué debes reforzar.</p>
                    </div>
                    <span class="learning-badge is-warning">Intentos agotados</span>
                </div>

                <div class="learning-review-summary">
                    <div><strong><?php echo round((float)$revIntento['porcentaje'], 1); ?>%</strong><span>Puntaje final</span></div>
                    <div><strong><?php echo (int)$revision['correctas']; ?>/<?php echo (int)$revision['total']; ?></strong><span>Respuestas correctas</span></div>
                    <div><strong><?php echo round((float)$revIntento['puntos_obtenidos'], 1); ?>/<?php echo round((float)$revIntento['puntos_totales'], 1); ?></strong><span>Puntos obtenidos</span></div>
                </div>

                <div class="learning-review-list">
                    <?php foreach ($revision['respuestas'] as $idx => $r): $esCorrecta = !empty($r['es_correcta']); ?>
                        <article class="learning-review-question <?php echo $esCorrecta ? 'is-correct' : 'is-wrong'; ?>">
                            <div class="learning-review-question-head">
                                <span class="learning-review-number"><?php echo str_pad((string)($idx + 1), 2, '0', STR_PAD_LEFT); ?></span>
                                <div>
                                    <h3><?php echo learningH($r['pregunta']); ?></h3>
                                    <small><?php echo round((float)$r['puntos_obtenidos'], 1); ?>/<?php echo round((float)$r['puntos'], 1); ?> puntos</small>
                                </div>
                                <span class="learning-review-status"><?php echo $esCorrecta ? '✓ Correcta' : '✕ Incorrecta'; ?></span>
                            </div>
                            <div class="learning-review-answers">
                                <div class="learning-review-answer user-answer">
                                    <span>Tu respuesta</span>
                                    <strong><?php echo learningH($r['respuesta_usuario'] ?: 'Sin respuesta'); ?></strong>
                                </div>
                                <?php if (!$esCorrecta): ?>
                                    <div class="learning-review-answer correct-answer">
                                        <span>Respuesta correcta</span>
                                        <strong><?php echo learningH($r['respuesta_correcta'] ?: 'No disponible'); ?></strong>
                                    </div>
                                <?php else: ?>
                                    <div class="learning-review-answer correct-answer">
                                        <span>Resultado</span>
                                        <strong>Respuesta correcta</strong>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="learning-review-footer">
                    <p>Utiliza esta revisión para identificar los temas que necesitas reforzar antes de una nueva capacitación o evaluación asignada.</p>
                    <a class="btn-primary-modern" href="curso.php?asignacion=<?php echo $idAsignacion; ?>">Volver al curso</a>
                </div>
            </section>
        <?php endif; ?>
    </main>
</div>
<?php include '../includes/public_footer.php'; ?>
