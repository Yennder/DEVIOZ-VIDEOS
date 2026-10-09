<?php
require_once '../../../config/sesion.php';
verificarAdmin();
require_once '../../../models/SkillMapa.php';
require_once '../../../includes/skill_mapa_i18n.php';
require_once '../../../includes/learning_helpers.php';

$modelo = new SkillMapa();
$buscar = trim((string)($_GET['buscar'] ?? ''));
$buscar = function_exists('mb_substr') ? mb_substr($buscar, 0, 120, 'UTF-8') : substr($buscar, 0, 120);
$skillId = max(0, (int)($_GET['skill'] ?? 0));
$estado = (string)($_GET['estado'] ?? '');
$prioridad = (string)($_GET['prioridad'] ?? '');
if (!in_array($estado, ['', 'alcanzado', 'brecha', 'sin_evidencia'], true)) $estado = '';
if (!in_array($prioridad, ['', 'alta', 'media', 'baja'], true)) $prioridad = '';
$catalogo = $modelo->catalogo();
$datos = $modelo->mapaEquipo($buscar, $skillId, $estado, $prioridad);
$resumen = $datos['resumen'];
$filas = $datos['filas'];
$etiquetas = ['alcanzado' => 'Meta de evidencia alcanzada', 'brecha' => 'Brecha de evidencia', 'sin_evidencia' => 'Sin evidencia académica'];
?>
<!DOCTYPE html><html lang="<?php echo deviozIdiomaActual() === 'pt' ? 'pt-BR' : deviozIdiomaActual(); ?>">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo mapaH('Mapa del equipo'); ?> - DEVIOZ</title>
<link rel="stylesheet" href="../../../assets/css/admin.css">
<link rel="stylesheet" href="../../../assets/css/skill_mapa.css?v=5.1"></head>
<body>
<?php include '../../includes/sidebar.php'; ?>
<div class="admin-main"><?php include '../../includes/navbar.php'; ?>
<main class="admin-content learning-admin-page v51-page">
  <header class="gestion-header">
    <div><span class="learning-admin-kicker">TECHFLIX LEARNING LAB · V5.1</span>
      <h1><?php echo mapaH('Mapa del equipo'); ?></h1>
      <p class="dashboard-subtitle"><?php echo mapaH('El mapa compara los objetivos con la evidencia académica de Learning Lab. La evaluación humana permanece separada.'); ?></p>
    </div>
    <div class="v51-links"><a class="btn-secundario" href="reporte.php">Reporte Skills</a><a class="btn-crear" href="objetivos.php"><?php echo mapaH('Definir objetivos'); ?></a></div>
  </header>
  <div class="v51-stats v51-stats--admin">
    <article><span><?php echo mapaH('Trabajadores con metas'); ?></span><strong><?php echo (int)$resumen['trabajadores']; ?></strong></article>
    <article><span><?php echo mapaH('Metas visibles'); ?></span><strong><?php echo (int)$resumen['metas']; ?></strong></article>
    <article><span><?php echo mapaH('Con brecha'); ?></span><strong><?php echo (int)$resumen['con_brecha']; ?></strong></article>
    <article><span><?php echo mapaH('Sin evidencia'); ?></span><strong><?php echo (int)$resumen['sin_evidencia']; ?></strong></article>
    <article><span><?php echo mapaH('Objetivos alcanzados'); ?></span><strong><?php echo (int)$resumen['alcanzadas']; ?></strong></article>
    <article><span><?php echo mapaH('Brecha media'); ?></span><strong><?php echo (float)$resumen['brecha_promedio']; ?> pp</strong></article>
  </div>
  <form method="get" class="v51-filters" aria-label="<?php echo mapaH('Filtrar resultados'); ?>">
    <label><?php echo mapaH('Trabajador'); ?><input name="buscar" value="<?php echo learningH($buscar); ?>" placeholder="<?php echo mapaH('Buscar nombre o correo'); ?>"></label>
    <label><?php echo mapaH('Skill'); ?><select name="skill"><option value="0"><?php echo mapaH('Todas las skills'); ?></option>
    <?php foreach ($catalogo as $s): ?><option value="<?php echo (int)$s['id_skill']; ?>" <?php echo $skillId === (int)$s['id_skill'] ? 'selected' : ''; ?>><?php echo learningH($s['nombre']); ?></option><?php endforeach; ?></select></label>
    <label><?php echo mapaH('Estado de brecha'); ?><select name="estado"><option value=""><?php echo mapaH('Todos los estados'); ?></option>
    <?php foreach ($etiquetas as $valor => $texto): ?><option value="<?php echo $valor; ?>" <?php echo $estado === $valor ? 'selected' : ''; ?>><?php echo mapaH($texto); ?></option><?php endforeach; ?></select></label>
    <label><?php echo mapaH('Prioridad'); ?><select name="prioridad"><option value=""><?php echo mapaH('Todas las prioridades'); ?></option>
      <?php foreach (['alta'=>'Alta','media'=>'Media','baja'=>'Baja'] as $id=>$text): ?><option value="<?php echo $id; ?>" <?php echo $prioridad===$id?'selected':''; ?>><?php echo mapaH($text); ?></option><?php endforeach; ?></select></label>
    <button type="submit" class="v51-action"><?php echo mapaH('Filtrar'); ?></button><a class="v51-action v51-action--outline" href="mapa.php"><?php echo mapaH('Limpiar'); ?></a>
  </form>
  <p class="v51-disclaimer"><?php echo mapaH('Los porcentajes reflejan evidencia de aprendizaje, no certifican la competencia profesional.'); ?></p>
  <div class="v51-table-scroll"><table class="v51-table"><thead><tr>
  <th><?php echo mapaH('Trabajador'); ?></th><th><?php echo mapaH('Skill'); ?></th><th><?php echo mapaH('Evidencia actual'); ?> / <?php echo mapaH('Meta'); ?></th>
  <th><?php echo mapaH('Brecha'); ?></th><th><?php echo mapaH('Evaluación supervisor'); ?></th><th><?php echo mapaH('Acciones'); ?></th>
  </tr></thead><tbody>
  <?php if (!$filas): ?><tr><td colspan="6"><?php echo mapaH('No hay resultados para los filtros aplicados.'); ?></td></tr><?php endif; ?>
  <?php foreach($filas as $r): ?>
  <tr><td><strong><?php echo learningH($r['usuario_nombre']); ?></strong><small><?php echo learningH($r['usuario_email']); ?></small></td>
  <td><strong><?php echo learningH($r['icono']); ?> <?php echo learningH($r['nombre']); ?></strong><small><?php echo learningH($r['categoria']); ?> · <?php echo mapaH($r['nivel_objetivo_texto']); ?> · <?php echo mapaH(ucfirst($r['prioridad'])); ?></small></td>
  <td><div class="v51-bar" aria-label="<?php echo mapaH('Evidencia actual'); ?> <?php echo (float)$r['actual']; ?>%, <?php echo mapaH('Meta'); ?> <?php echo (float)$r['objetivo']; ?>%"><i style="width:<?php echo (float)$r['objetivo']; ?>%" class="v51-goal"></i><i style="width:<?php echo (float)$r['actual']; ?>%" class="v51-now"></i></div><small><?php echo (float)$r['actual']; ?>% / <?php echo (float)$r['objetivo']; ?>% · <?php echo mapaH($r['fuente_objetivo']==='administrador'?'Configurado por administración':'Derivado de curso'); ?></small></td>
  <td><strong><?php echo (float)$r['brecha']; ?> pp</strong><span class="v51-status v51-status--<?php echo learningH($r['estado']); ?>"><?php echo mapaH($etiquetas[$r['estado']]); ?></span></td>
  <td><?php if ($r['supervisor']): ?><strong><?php echo (float)$r['supervisor']['puntaje']; ?>%</strong><small><?php echo learningH($r['supervisor']['evaluador']); ?></small><?php else: ?><small><?php echo mapaH('Sin evaluación'); ?></small><?php endif; ?></td>
  <td><div class="v51-row-actions"><a href="trabajador.php?id_usuario=<?php echo (int)$r['id_usuario']; ?>"><?php echo mapaH('Ver perfil'); ?></a><a href="objetivos.php?id_usuario=<?php echo (int)$r['id_usuario']; ?>"><?php echo mapaH('Administrar objetivos'); ?></a></div></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
</main></div></body></html>
