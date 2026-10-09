<?php
require_once '../../../config/sesion.php';
verificarAdmin();
require_once '../../../models/SkillMapa.php';
require_once '../../../includes/learning_helpers.php';
require_once '../../../includes/skill_mapa_i18n.php';

$modelo = new SkillMapa();
$idUsuario = max(0, (int)($_POST['id_usuario'] ?? $_GET['id_usuario'] ?? 0));
$usuarios = $modelo->usuarios();
$usuariosPorId = [];
foreach ($usuarios as $u) $usuariosPorId[(int)$u['id_usuario']] = $u;
if ($idUsuario && !isset($usuariosPorId[$idUsuario])) $idUsuario = 0;
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verificarCsrfPost();
    if (!$idUsuario) {
        $error = 'Selecciona un trabajador activo.';
    } else {
        try {
            $idSkill = (int)($_POST['id_skill'] ?? 0);
            $accion = (string)($_POST['accion'] ?? 'guardar');
            if ($accion === 'quitar') {
                $modelo->eliminarObjetivo($idUsuario, $idSkill);
                $accion = 'eliminado';
            } else if ($accion === 'guardar') {
                $modelo->guardarObjetivo(
                    $idUsuario, $idSkill, (string)($_POST['nivel_objetivo'] ?? ''),
                    (string)($_POST['prioridad'] ?? ''), (string)($_POST['observacion'] ?? ''),
                    (int)$_SESSION['id_usuario']
                );
                $accion = 'guardado';
            } else {
                throw new InvalidArgumentException('Accion invalida.');
            }
            header('Location: objetivos.php?id_usuario=' . $idUsuario . '&resultado=' . $accion, true, 303);
            exit;
        } catch (InvalidArgumentException $e) { $error = $e->getMessage(); }
        catch (PDOException $e) { error_log('V5.1 objetivo Skill: ' . $e->getMessage()); $error = 'No fue posible guardar el objetivo. Verifica la migracion V5.1.'; }
    }
}
$catalogo = $modelo->catalogo();
$manuales = $idUsuario ? $modelo->objetivosConfigurados($idUsuario) : [];
$mapa = $idUsuario ? $modelo->mapaUsuario($idUsuario) : ['filas'=>[], 'resumen'=>[]];
$actuales = [];
foreach ($mapa['filas'] as $fila) $actuales[(int)$fila['id_skill']] = $fila;
$resultado = (string)($_GET['resultado'] ?? '');
$notificacion = $resultado === 'guardado' ? 'Objetivo guardado.' : ($resultado === 'eliminado' ? 'Objetivo personalizado eliminado.' : '');
?>
<!DOCTYPE html><html lang="<?php echo deviozIdiomaActual() === 'pt' ? 'pt-BR' : deviozIdiomaActual(); ?>"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo mapaH('Objetivos por trabajador'); ?> - DEVIOZ</title><link rel="stylesheet" href="../../../assets/css/admin.css"><link rel="stylesheet" href="../../../assets/css/skill_mapa.css?v=5.1"></head>
<body><?php include '../../includes/sidebar.php'; ?><div class="admin-main"><?php include '../../includes/navbar.php'; ?>
<main class="admin-content learning-admin-page v51-page">
<header class="gestion-header"><div><span class="learning-admin-kicker">V5.1 · <?php echo mapaH('Configuración manual'); ?></span><h1><?php echo mapaH('Objetivos por trabajador'); ?></h1>
<p class="dashboard-subtitle"><?php echo mapaH('Al quitar un objetivo personalizado, se recupera la meta derivada del curso asignado si existe.'); ?></p></div>
<a class="btn-secundario" href="mapa.php"><?php echo mapaH('Volver al mapa'); ?></a></header>
<form class="v51-user-selector" method="get" action="objetivos.php">
<label><?php echo mapaH('Trabajador'); ?><select name="id_usuario" required><option value=""><?php echo mapaH('Selecciona un trabajador'); ?></option>
<?php foreach ($usuarios as $u): ?><option value="<?php echo (int)$u['id_usuario']; ?>" <?php echo $idUsuario===(int)$u['id_usuario']?'selected':''; ?>><?php echo learningH($u['nombre']); ?> · <?php echo learningH($u['email']); ?></option><?php endforeach; ?></select></label>
<button class="v51-action" type="submit"><?php echo mapaH('Abrir objetivos'); ?></button>
</form>
<?php if ($error): ?><p class="v51-alert v51-alert--error" role="alert"><?php echo learningH($error); ?></p><?php endif; ?>
<?php if ($notificacion): ?><p class="v51-alert" role="status"><?php echo mapaH($notificacion); ?></p><?php endif; ?>
<?php if ($idUsuario): ?>
<h2 class="v51-subhead"><?php echo mapaH('Objetivos personalizados del trabajador'); ?> · <?php echo learningH($usuariosPorId[$idUsuario]['nombre']); ?></h2>
<p class="v51-disclaimer"><?php echo mapaH('Los porcentajes reflejan evidencia de aprendizaje, no certifican la competencia profesional.'); ?></p>
<div class="v51-editor-grid">
<?php foreach ($catalogo as $skill):
    $idSkill = (int)$skill['id_skill'];
    $manual = $manuales[$idSkill] ?? null;
    $fila = $actuales[$idSkill] ?? null;
    $nivel = $manual ? (string)$manual['nivel_objetivo'] : (string)($fila['nivel_objetivo'] ?? 'basico');
    $prioridad = $manual ? (string)$manual['prioridad'] : 'media';
?>
<article class="v51-editor-card">
  <div class="v51-editor-head"><span class="v51-editor-icon"><?php echo learningH($skill['icono']); ?></span><div><small><?php echo learningH($skill['categoria']); ?></small><h3><?php echo learningH($skill['nombre']); ?></h3></div><span class="v51-pill"><?php echo mapaH($manual?'Personalizada':($fila?'Meta del curso':'Sin meta')); ?></span></div>
  <p class="v51-editor-caption"><?php echo mapaH('Evidencia actual'); ?>: <strong><?php echo (float)($fila['actual'] ?? 0); ?>%</strong><?php if ($fila): ?> · <?php echo mapaH('Meta'); ?> <?php echo (float)$fila['objetivo']; ?>%<?php endif; ?></p>
  <form method="post" class="v51-goal-form" action="objetivos.php?id_usuario=<?php echo $idUsuario; ?>">
    <?php echo csrfInput(); ?><input type="hidden" name="id_usuario" value="<?php echo $idUsuario; ?>"><input type="hidden" name="id_skill" value="<?php echo $idSkill; ?>">
    <label><?php echo mapaH('Nivel objetivo'); ?><select name="nivel_objetivo">
      <?php foreach (['basico'=>'Básico','intermedio'=>'Intermedio','avanzado'=>'Avanzado'] as $value=>$label): ?><option value="<?php echo $value; ?>" <?php echo $nivel===$value?'selected':''; ?>><?php echo mapaH($label); ?> (<?php echo (int)SkillBrechas::umbral($value); ?>%)</option><?php endforeach; ?></select></label>
    <label><?php echo mapaH('Prioridad'); ?><select name="prioridad">
      <?php foreach (['alta'=>'Alta','media'=>'Media','baja'=>'Baja'] as $value=>$label): ?><option value="<?php echo $value; ?>" <?php echo $prioridad===$value?'selected':''; ?>><?php echo mapaH($label); ?></option><?php endforeach; ?></select></label>
    <label class="v51-form-wide"><?php echo mapaH('Observación'); ?><textarea name="observacion" maxlength="500" rows="2" placeholder="<?php echo mapaH('Objetivo de desarrollo'); ?>"><?php echo learningH($manual['observacion'] ?? ''); ?></textarea></label>
    <div class="v51-editor-buttons"><button class="v51-action" type="submit" name="accion" value="guardar"><?php echo mapaH('Guardar objetivo'); ?></button>
      <?php if ($manual): ?><button class="v51-action v51-action--outline" type="submit" name="accion" value="quitar" formnovalidate><?php echo mapaH('Quitar objetivo'); ?></button><?php endif; ?></div>
  </form>
</article>
<?php endforeach; ?>
</div>
<?php endif; ?>
</main></div></body></html>
