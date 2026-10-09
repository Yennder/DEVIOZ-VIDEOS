<?php
/** Textos de interfaz de Watch & Build en ES/EN/PT (contenido del usuario no se traduce). */
require_once __DIR__ . '/../includes/idiomas.php';
if (!function_exists('wbE')) {
    function wbE(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    function wbT(string $es): string
    {
        $lang = deviozIdiomaActual();
        if ($lang === 'es') return $es;
        static $map = [
            'Watch & Build' => ['Watch & Build','Watch & Build'],
            'Retos prácticos' => ['Practical challenges','Desafios práticos'],
            'Nuevo reto' => ['New challenge','Novo desafio'],
            'Editar reto' => ['Edit challenge','Editar desafio'],
            'Mis retos' => ['My challenges','Meus desafios'],
            'Entregas y revisiones' => ['Submissions & reviews','Entregas e avaliações'],
            'Revisar entrega' => ['Review submission','Avaliar entrega'],
            'Evidencias prácticas' => ['Practical evidence','Evidências práticas'],
            'Aprende haciendo' => ['Learn by doing','Aprenda fazendo'],
            'Resuelve proyectos reales y demuestra lo que aprendiste.' => ['Complete real projects and demonstrate what you learned.','Resolva projetos reais e demonstre o que aprendeu.'],
            'Los retos se publican para todos los usuarios registrados. No modifican las notas de Learning Lab.' => ['Challenges are visible to registered users. They do not change Learning Lab grades.','Os desafios são visíveis para usuários registrados. Não alteram notas do Learning Lab.'],
            'Crea retos vinculados a un video, un curso o una Skill. El administrador revisa cada entrega.' => ['Create challenges linked to a video, course or Skill. An administrator reviews each submission.','Crie desafios ligados a um vídeo, curso ou Skill. Um administrador avalia cada entrega.'],
            'Ver retos' => ['Browse challenges','Ver desafios'],
            'Ver entregas' => ['View submissions','Ver entregas'],
            'Ver detalle' => ['View details','Ver detalhes'],
            'Ver reto' => ['View challenge','Ver desafio'],
            'Abrir' => ['Open','Abrir'],
            'Volver a retos' => ['Back to challenges','Voltar aos desafios'],
            'Volver a entregas' => ['Back to submissions','Voltar às entregas'],
            'Buscar' => ['Search','Buscar'],
            'Título o descripción' => ['Title or description','Título ou descrição'],
            'Todos los estados' => ['All statuses','Todos os estados'],
            'Todas las skills' => ['All skills','Todas as skills'],
            'El administrador revisa cada entrega.' => ['An administrator reviews every submission.','Um administrador avalia cada entrega.'],
            'Estado' => ['Status','Status'],
            'Acciones' => ['Actions','Ações'],
            'Nivel' => ['Level','Nível'],
            'Dificultad' => ['Difficulty','Dificuldade'],
            'Básica' => ['Basic','Básica'],
            'Intermedia' => ['Intermediate','Intermediária'],
            'Avanzada' => ['Advanced','Avançada'],
            'Borrador' => ['Draft','Rascunho'],
            'Publicado' => ['Published','Publicado'],
            'Archivado' => ['Archived','Arquivado'],
            'Enviada' => ['Submitted','Enviada'],
            'Correcciones' => ['Changes requested','Correções solicitadas'],
            'Aprobada' => ['Approved','Aprovada'],
            'No aprobada' => ['Not approved','Não aprovada'],
            'Pendiente de revisión' => ['Awaiting review','Aguardando avaliação'],
            'Aprobado' => ['Passed','Aprovado'],
            'Sin entregas' => ['No submissions','Sem entregas'],
            'Sin evaluar' => ['Not graded','Não avaliado'],
            'Sin fecha límite' => ['No deadline','Sem prazo'],
            'Fecha límite' => ['Deadline','Prazo'],
            'Fecha de entrega' => ['Submission date','Data da entrega'],
            'Fecha de revisión' => ['Review date','Data da avaliação'],
            'Nota' => ['Score','Nota'],
            'Nota mínima' => ['Passing score','Nota mínima'],
            'Intento' => ['Attempt','Tentativa'],
            'Participantes' => ['Participants','Participantes'],
            'Por revisar' => ['Pending review','Pendentes de avaliação'],
            'Entrega' => ['Submission','Entrega'],
            'Entregas' => ['Submissions','Entregas'],
            'Trabajador' => ['Worker','Colaborador'],
            'Reto' => ['Challenge','Desafio'],
            'Curso' => ['Course','Curso'],
            'Video' => ['Video','Vídeo'],
            'Skill' => ['Skill','Skill'],
            'Todos' => ['All','Todos'],
            'Sin relación' => ['Not linked','Sem vínculo'],
            'Vínculos' => ['Links to content','Vínculos'],
            'Elige al menos un video, curso o Skill.' => ['Select at least one video, course or Skill.','Selecione ao menos um vídeo, curso ou Skill.'],
            'Crear reto' => ['Create challenge','Criar desafio'],
            'Guardar reto' => ['Save challenge','Salvar desafio'],
            'Título' => ['Title','Título'],
            'Descripción' => ['Description','Descrição'],
            'Instrucciones' => ['Instructions','Instruções'],
            'Criterios de evaluación' => ['Assessment criteria','Critérios de avaliação'],
            'Título del reto' => ['Challenge title','Título do desafio'],
            'Qué se espera aprender y construir' => ['What should be learned and built','O que deve ser aprendido e construído'],
            'Pasos, requisitos y resultado esperado' => ['Steps, requirements and expected result','Etapas, requisitos e resultado esperado'],
            'Cómo se calificará la evidencia' => ['How evidence will be graded','Como a evidência será avaliada'],
            'Opcional' => ['Optional','Opcional'],
            'No se puede editar criterios ni vínculos después de recibir entregas.' => ['Criteria and content links cannot be edited after submissions arrive.','Critérios e vínculos não podem ser alterados após receber entregas.'],
            'Puedes subir PDF, JPG, PNG, WEBP o ZIP (máximo 10 MB).' => ['Upload PDF, JPG, PNG, WEBP or ZIP (up to 10 MB).','Envie PDF, JPG, PNG, WEBP ou ZIP (até 10 MB).'],
            'Tu evidencia' => ['Your evidence','Sua evidência'],
            'Describe tu trabajo' => ['Describe your work','Descreva seu trabalho'],
            'Explica cómo lo desarrollaste, qué lograste y cómo comprobarlo.' => ['Explain how you built it, what you achieved and how to verify it.','Explique como fez, o que conseguiu e como verificar.'],
            'Enlace de evidencia' => ['Evidence link','Link da evidência'],
            'Archivo adjunto' => ['Attachment','Arquivo anexado'],
            'Adjuntar archivo' => ['Attach file','Anexar arquivo'],
            'Archivo protegido' => ['Protected file','Arquivo protegido'],
            'Descargar evidencia' => ['Download evidence','Baixar evidência'],
            'Abrir enlace' => ['Open link','Abrir link'],
            'Enviar entrega' => ['Submit work','Enviar entrega'],
            'Mis intentos' => ['My attempts','Minhas tentativas'],
            'Historial de entregas' => ['Submission history','Histórico de entregas'],
            'Observaciones del revisor' => ['Reviewer feedback','Comentários do avaliador'],
            'Puntuación obtenida' => ['Score achieved','Pontuação obtida'],
            'Puedes enviar una nueva versión después de recibir correcciones o no aprobar.' => ['You may submit a new version after receiving feedback or not passing.','Você pode enviar nova versão após receber correções ou não aprovação.'],
            'Ya entregaste este reto. Espera la revisión administrativa.' => ['This challenge is submitted. Wait for an administrator review.','Você já enviou este desafio. Aguarde a avaliação.'],
            'Reto aprobado. Tu evidencia queda en el historial.' => ['Challenge approved. Your evidence remains in your history.','Desafio aprovado. Sua evidência permanece no histórico.'],
            'El plazo de entrega terminó.' => ['The submission deadline has passed.','O prazo de entrega terminou.'],
            'Este reto no está disponible para nuevas entregas.' => ['This challenge is not open for submissions.','Este desafio não aceita novas entregas.'],
            'Necesitas iniciar sesión para participar.' => ['Sign in to participate.','Entre na sua conta para participar.'],
            'No hay retos para estos filtros.' => ['No challenges match these filters.','Nenhum desafio corresponde aos filtros.'],
            'Todavía no hay entregas.' => ['No submissions yet.','Ainda não há entregas.'],
            'No hay retos publicados todavía.' => ['No published challenges yet.','Ainda não há desafios publicados.'],
            'Evaluar entrega' => ['Grade submission','Avaliar entrega'],
            'Decisión' => ['Decision','Decisão'],
            'Aprobar' => ['Approve','Aprovar'],
            'Solicitar correcciones' => ['Request changes','Solicitar correções'],
            'No aprobar' => ['Not approve','Não aprovar'],
            'Retroalimentación' => ['Feedback','Comentários'],
            'Explica qué salió bien o qué debe mejorarse' => ['Explain what worked and what needs improvement','Explique o que funcionou e o que melhorar'],
            'Guardar revisión' => ['Save review','Salvar avaliação'],
            'Revisión registrada.' => ['Review saved.','Avaliação registrada.'],
            'Entrega enviada correctamente.' => ['Submission sent successfully.','Entrega enviada com sucesso.'],
            'Reto guardado correctamente.' => ['Challenge saved.','Desafio salvo.'],
            'Aún no hay evidencia práctica aprobada asociada a Skills.' => ['No approved practical evidence linked to Skills yet.','Ainda não há evidência prática aprovada ligada a Skills.'],
            'Retos prácticos aprobados' => ['Approved practical challenges','Desafios práticos aprovados'],
            'Estas evidencias son complementarias; no modifican tus porcentajes académicos ni certificados.' => ['This is complementary evidence. It does not change academic scores or certificates.','Estas evidências são complementares. Não alteram percentuais acadêmicos nem certificados.'],
            'Abrir Watch & Build' => ['Open Watch & Build','Abrir Watch & Build'],
            'Filtrar' => ['Filter','Filtrar'],
            'Limpiar' => ['Clear','Limpar'],
            'Cerrar' => ['Close','Fechar'],
        ];
        return $map[$es][$lang==='en'?0:1] ?? $es;
    }
    function wbH(string $es): string { return wbE(wbT($es)); }
    function wbEstado(string $estado): string
    {
        $labels=['borrador'=>'Borrador','publicado'=>'Publicado','archivado'=>'Archivado','enviada'=>'Enviada','correcciones'=>'Correcciones','aprobada'=>'Aprobada','no_aprobada'=>'No aprobada'];
        return wbT($labels[$estado] ?? $estado);
    }
    function wbDificultad(string $nivel): string
    {
        return wbT(['basica'=>'Básica','intermedia'=>'Intermedia','avanzada'=>'Avanzada'][$nivel] ?? $nivel);
    }
    function wbFecha(?string $value): string
    {
        return $value ? date('d/m/Y H:i', strtotime($value)) : wbT('Sin fecha límite');
    }
}
