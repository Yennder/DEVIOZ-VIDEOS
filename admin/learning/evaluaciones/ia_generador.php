<?php
$iaDisponible = !empty($iaPanel['tabla_disponible']);
$iaCobertura = $iaPanel['cobertura'] ?? ['transcritas'=>0,'total'=>0];
$iaLecciones = $iaPanel['lecciones'] ?? [];
$iaBorrador = $iaPanel['borrador'] ?? null;
?>
<section class="dashboard-section learning-section-gap ai-eval-section" id="generador-ia" data-ai-eval-root data-course-id="<?php echo $idCurso; ?>" data-csrf="<?php echo learningH(csrfToken()); ?>" data-api="../../../api/evaluacion_ia.php" data-has-draft="<?php echo $iaBorrador ? '1' : '0'; ?>">
    <div class="dashboard-section-header ai-eval-title-row">
        <div>
            <span class="learning-admin-kicker">DEVIOZ AI · V4.4.2</span>
            <h2>Generador inteligente de evaluaciones</h2>
            <p class="dashboard-subtitle">Crea un borrador de preguntas usando únicamente las transcripciones del curso. Nada se agrega al examen hasta que lo revises y lo apruebes.</p>
        </div>
        <div class="ai-eval-coverage">
            <strong><?php echo (int)$iaCobertura['transcritas']; ?>/<?php echo (int)$iaCobertura['total']; ?></strong>
            <span>lecciones transcritas</span>
        </div>
    </div>

    <?php if (!$iaDisponible): ?>
        <div class="mensaje-error">Importa primero <strong>database/migracion_v4_4_2_evaluaciones_ia.sql</strong> para activar el generador.</div>
    <?php elseif ((int)$iaCobertura['transcritas'] === 0): ?>
        <div class="admin-empty-state">Este curso todavía no tiene lecciones con transcripción completada. Transcribe al menos una lección antes de generar preguntas.</div>
    <?php else: ?>
        <div class="ai-eval-builder-grid">
            <div class="ai-eval-config-card">
                <h3>Configurar generación</h3>
                <div class="learning-inline-fields ai-eval-inline-fields">
                    <div>
                        <label for="aiEvalCantidad">Cantidad</label>
                        <select id="aiEvalCantidad">
                            <?php foreach ([5,8,10,12,15,20] as $n): ?>
                                <option value="<?php echo $n; ?>" <?php echo $n===10?'selected':''; ?>><?php echo $n; ?> preguntas</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="aiEvalDificultad">Dificultad</label>
                        <select id="aiEvalDificultad">
                            <option value="basica">Básica</option>
                            <option value="intermedia" selected>Intermedia</option>
                            <option value="avanzada">Avanzada</option>
                        </select>
                    </div>
                </div>

                <label>Tipos de pregunta</label>
                <div class="ai-eval-check-row">
                    <label><input type="checkbox" value="opcion_multiple" data-ai-eval-type checked> Opción múltiple</label>
                    <label><input type="checkbox" value="verdadero_falso" data-ai-eval-type checked> Verdadero / Falso</label>
                </div>

                <label>Fuente</label>
                <div class="ai-eval-scope-row">
                    <label><input type="radio" name="ai_eval_scope" value="curso" checked data-ai-eval-scope> Todo el curso</label>
                    <label><input type="radio" name="ai_eval_scope" value="lecciones" data-ai-eval-scope> Lecciones seleccionadas</label>
                </div>

                <div class="ai-eval-lessons" data-ai-eval-lessons hidden>
                    <?php foreach ($iaLecciones as $l): ?>
                        <label class="ai-eval-lesson <?php echo empty($l['transcrita'])?'is-disabled':''; ?>">
                            <input type="checkbox" value="<?php echo (int)$l['id_leccion']; ?>" data-ai-eval-lesson <?php echo empty($l['transcrita'])?'disabled':''; ?>>
                            <span>
                                <strong><?php echo (int)$l['orden']; ?>. <?php echo learningH($l['titulo_mostrar']); ?></strong>
                                <small><?php echo !empty($l['transcrita'])?'Transcripción lista':'Sin transcripción completada'; ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="ai-eval-generate-actions">
                    <button type="button" class="btn-crear" data-ai-eval-generate>✨ <?php echo $iaBorrador?'Generar nuevo borrador':'Generar borrador con IA'; ?></button>
                    <span class="ai-eval-status" data-ai-eval-status></span>
                </div>
                <p class="ai-eval-note">DEVIOZ AI usa solo las transcripciones seleccionadas y compara las nuevas preguntas con las que ya existen para reducir duplicados.</p>
            </div>

            <div class="ai-eval-safety-card">
                <span class="ai-eval-shield">🛡️</span>
                <div>
                    <h3>Revisión humana obligatoria</h3>
                    <p>Las preguntas generadas quedan en un <strong>borrador IA</strong>. Puedes editar texto, alternativas, respuesta correcta, dificultad y explicación antes de agregarlas al examen.</p>
                    <ul>
                        <li>No publica automáticamente.</li>
                        <li>No reemplaza las preguntas existentes.</li>
                        <li>Conserva el proveedor y modelo usados.</li>
                    </ul>
                </div>
            </div>
        </div>

        <?php if ($iaBorrador): ?>
            <div class="ai-eval-draft" data-ai-eval-draft>
                <div class="ai-eval-draft-head">
                    <div>
                        <span class="learning-admin-kicker">Borrador IA #<?php echo (int)$iaBorrador['id_borrador']; ?></span>
                        <h3><?php echo count($iaBorrador['preguntas']); ?> preguntas para revisar</h3>
                        <p>Generado el <?php echo learningH(date('d/m/Y H:i',strtotime((string)$iaBorrador['fecha_creacion']))); ?> · <?php echo learningH(ucfirst((string)$iaBorrador['dificultad'])); ?> · <?php echo (int)$iaBorrador['fuente_items']; ?> lecciones usadas<?php if(!empty($iaBorrador['proveedor'])): ?> · <?php echo learningH(strtoupper((string)$iaBorrador['proveedor'])); ?><?php endif; ?></p>
                    </div>
                    <form method="POST" onsubmit="return confirm('¿Descartar este borrador? Las preguntas no se agregarán al examen.');">
                        <?php echo csrfInput(); ?>
                        <input type="hidden" name="accion" value="descartar_borrador_ia">
                        <input type="hidden" name="id_curso" value="<?php echo $idCurso; ?>">
                        <input type="hidden" name="id_borrador" value="<?php echo (int)$iaBorrador['id_borrador']; ?>">
                        <button class="btn-limpiar" type="submit">Descartar borrador</button>
                    </form>
                </div>

                <div class="ai-eval-question-list">
                    <?php foreach ($iaBorrador['preguntas'] as $idx=>$p):
                        $isVf = $p['tipo']==='verdadero_falso';
                        $opciones = $p['opciones'] ?? [];
                        $correctaIndex = 0;
                        foreach ($opciones as $oIdx=>$o) if (!empty($o['es_correcta'])) { $correctaIndex=$oIdx; break; }
                    ?>
                        <article class="ai-eval-question-card">
                            <div class="ai-eval-question-heading">
                                <div class="learning-question-num"><?php echo $idx+1; ?></div>
                                <div class="ai-eval-question-meta">
                                    <strong><?php echo $isVf?'Verdadero / Falso':'Opción múltiple'; ?></strong>
                                    <span><?php echo learningH(ucfirst((string)$p['dificultad'])); ?></span>
                                    <span><?php echo learningH((string)$p['puntos']); ?> pt</span>
                                </div>
                            </div>

                            <form method="POST" class="admin-form learning-compact-form ai-eval-question-form" data-ai-question-form>
                                <?php echo csrfInput(); ?>
                                <input type="hidden" name="accion" value="editar_pregunta_ia">
                                <input type="hidden" name="id_curso" value="<?php echo $idCurso; ?>">
                                <input type="hidden" name="id_pregunta_ia" value="<?php echo (int)$p['id_pregunta_ia']; ?>">
                                <label>Pregunta</label>
                                <textarea name="pregunta" rows="2" required><?php echo learningH($p['pregunta']); ?></textarea>
                                <div class="learning-inline-fields">
                                    <div>
                                        <label>Tipo</label>
                                        <select name="tipo" data-ai-question-type>
                                            <option value="opcion_multiple" <?php echo !$isVf?'selected':''; ?>>Opción múltiple</option>
                                            <option value="verdadero_falso" <?php echo $isVf?'selected':''; ?>>Verdadero / Falso</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label>Dificultad</label>
                                        <select name="dificultad">
                                            <?php foreach (['basica'=>'Básica','intermedia'=>'Intermedia','avanzada'=>'Avanzada'] as $v=>$txt): ?>
                                                <option value="<?php echo $v; ?>" <?php echo $p['dificultad']===$v?'selected':''; ?>><?php echo $txt; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label>Puntos</label>
                                        <input type="number" name="puntos" min="0.1" max="100" step="0.1" value="<?php echo learningH((string)$p['puntos']); ?>">
                                    </div>
                                </div>

                                <div data-ai-question-mc <?php echo $isVf?'hidden':''; ?>>
                                    <label>Alternativas</label>
                                    <div class="learning-mc-options">
                                        <?php for($i=0;$i<4;$i++): $o=$opciones[$i]??null; ?>
                                            <div class="learning-option-input">
                                                <input type="radio" name="correcta" value="<?php echo $i+1; ?>" <?php echo $correctaIndex===$i?'checked':''; ?>>
                                                <input name="opcion_<?php echo $i+1; ?>" value="<?php echo learningH((string)($o['texto']??'')); ?>" placeholder="Opción <?php echo $i+1; ?>">
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>

                                <div class="learning-vf-options" data-ai-question-vf <?php echo !$isVf?'hidden':''; ?>>
                                    <label><input type="radio" name="respuesta_vf" value="verdadero" <?php echo $correctaIndex===0?'checked':''; ?>> Verdadero</label>
                                    <label><input type="radio" name="respuesta_vf" value="falso" <?php echo $correctaIndex===1?'checked':''; ?>> Falso</label>
                                </div>

                                <label>Explicación para revisión</label>
                                <textarea name="explicacion" rows="2" placeholder="Justificación breve basada en la transcripción"><?php echo learningH((string)$p['explicacion']); ?></textarea>
                                <button class="btn-editar" type="submit">Guardar cambios</button>
                            </form>

                            <form method="POST" class="ai-eval-delete-form" onsubmit="return confirm('¿Eliminar esta pregunta del borrador?');">
                                <?php echo csrfInput(); ?>
                                <input type="hidden" name="accion" value="eliminar_pregunta_ia">
                                <input type="hidden" name="id_curso" value="<?php echo $idCurso; ?>">
                                <input type="hidden" name="id_pregunta_ia" value="<?php echo (int)$p['id_pregunta_ia']; ?>">
                                <button class="btn-eliminar" type="submit">Eliminar del borrador</button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="ai-eval-approve-box">
                    <div>
                        <h3>¿Borrador revisado?</h3>
                        <?php if (!$eval): ?>
                            <p>Guarda primero la configuración del examen final para poder agregar estas preguntas.</p>
                        <?php elseif (($eval['estado']??'') === 'publicada'): ?>
                            <p class="ai-eval-live-warning">⚠ La evaluación está publicada. Al aprobar el borrador, estas preguntas quedarán activas en el examen actual.</p>
                        <?php else: ?>
                            <p>Las preguntas pasarán al banco del examen. La evaluación seguirá en estado <strong>Borrador</strong> hasta que tú la publiques.</p>
                        <?php endif; ?>
                    </div>
                    <form method="POST" onsubmit="return confirm('¿Agregar todas las preguntas revisadas de este borrador a la evaluación?');">
                        <?php echo csrfInput(); ?>
                        <input type="hidden" name="accion" value="aprobar_borrador_ia">
                        <input type="hidden" name="id_curso" value="<?php echo $idCurso; ?>">
                        <input type="hidden" name="id_borrador" value="<?php echo (int)$iaBorrador['id_borrador']; ?>">
                        <button class="btn-crear" type="submit" <?php echo (!$eval || empty($iaBorrador['preguntas']))?'disabled':''; ?>>✓ Aprobar y agregar <?php echo count($iaBorrador['preguntas']); ?> preguntas</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<script>
(function(){
    const root=document.querySelector('[data-ai-eval-root]');
    if(!root)return;
    const scopes=[...root.querySelectorAll('[data-ai-eval-scope]')];
    const lessons=root.querySelector('[data-ai-eval-lessons]');
    const syncScope=()=>{const v=scopes.find(x=>x.checked)?.value||'curso';if(lessons)lessons.hidden=v!=='lecciones';};
    scopes.forEach(x=>x.addEventListener('change',syncScope));syncScope();

    root.querySelectorAll('[data-ai-question-form]').forEach(form=>{
        const type=form.querySelector('[data-ai-question-type]');
        const mc=form.querySelector('[data-ai-question-mc]');
        const vf=form.querySelector('[data-ai-question-vf]');
        const sync=()=>{const isV=type.value==='verdadero_falso';mc.hidden=isV;vf.hidden=!isV;};
        type.addEventListener('change',sync);sync();
    });

    const btn=root.querySelector('[data-ai-eval-generate]');
    const status=root.querySelector('[data-ai-eval-status]');
    if(!btn)return;
    btn.addEventListener('click',async()=>{
        if(root.dataset.hasDraft==='1'&&!confirm('Ya existe un borrador IA. Si generas uno nuevo, el actual se marcará como descartado. ¿Continuar?'))return;
        const tipos=[...root.querySelectorAll('[data-ai-eval-type]:checked')].map(x=>x.value);
        if(!tipos.length){status.textContent='Selecciona al menos un tipo de pregunta.';status.className='ai-eval-status is-error';return;}
        const alcance=scopes.find(x=>x.checked)?.value||'curso';
        const ids=[...root.querySelectorAll('[data-ai-eval-lesson]:checked')].map(x=>Number(x.value));
        if(alcance==='lecciones'&&!ids.length){status.textContent='Selecciona al menos una lección transcrita.';status.className='ai-eval-status is-error';return;}
        btn.disabled=true;btn.dataset.original=btn.textContent;btn.textContent='DEVIOZ AI generando…';
        status.textContent='Analizando transcripciones y evitando preguntas repetidas…';status.className='ai-eval-status is-loading';
        try{
            const response=await fetch(root.dataset.api,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({accion:'generar',id_curso:Number(root.dataset.courseId),cantidad:Number(document.getElementById('aiEvalCantidad').value),dificultad:document.getElementById('aiEvalDificultad').value,tipos,alcance,lecciones_ids:ids,csrf_token:root.dataset.csrf})});
            const data=await response.json().catch(()=>({ok:false,mensaje:'Respuesta inválida del servidor.'}));
            if(!response.ok||!data.ok)throw new Error(data.mensaje||'No se pudo generar el borrador.');
            status.textContent='Borrador generado. Cargando preguntas…';status.className='ai-eval-status is-success';
            const url=new URL(window.location.href);url.searchParams.set('ia_generadas',String(data.generadas||0));url.hash='generador-ia';window.location.href=url.toString();
        }catch(error){status.textContent=error.message||'No se pudo generar el borrador.';status.className='ai-eval-status is-error';btn.disabled=false;btn.textContent=btn.dataset.original||'✨ Generar borrador con IA';}
    });
})();
</script>
