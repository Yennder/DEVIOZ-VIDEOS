<?php
/**
 * V5.1 - Metas por trabajador y brechas de EVIDENCIA academica.
 * La valoracion humana de supervisor se muestra aparte y nunca modifica
 * los puntajes calculados por Learning Lab.
 */
require_once __DIR__ . '/Skill.php';
require_once __DIR__ . '/SkillBrechas.php';

final class SkillMapa
{
    private PDO $db;
    private Skill $skills;

    public function __construct(?PDO $db = null, ?Skill $skills = null)
    {
        $this->db = $db ?? (new Conexion())->conectar();
        $this->skills = $skills ?? new Skill();
    }

    public function catalogo(): array
    {
        return $this->db->query("SELECT id_skill, codigo, nombre, categoria, descripcion, icono FROM learning_skills WHERE estado=1 ORDER BY categoria, nombre")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function usuarios(string $buscar = ''): array
    {
        return $this->skills->usuariosSkillsAdmin($buscar);
    }

    public function objetivosConfigurados(int $idUsuario): array
    {
        $stmt = $this->db->prepare("SELECT o.* FROM learning_skill_objetivos_usuario o INNER JOIN learning_skills s ON s.id_skill=o.id_skill AND s.estado=1 WHERE o.id_usuario=:usuario");
        $stmt->execute([':usuario' => $idUsuario]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int)$row['id_skill']] = $row;
        }
        return $map;
    }

    /** Muestra solo cursos existentes y publicados, sin prometer acceso a un curso no asignado. */
    private function cursosVinculados(array $idsSkills): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $idsSkills), static fn(int $id): bool => $id > 0)));
        if (!$ids) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("SELECT cs.id_skill, c.id_curso, c.titulo, cs.peso, cs.nivel_objetivo FROM learning_curso_skills cs JOIN learning_cursos c ON c.id_curso=cs.id_curso AND c.estado='publicado' WHERE cs.id_skill IN ($placeholders) ORDER BY cs.peso DESC, c.titulo ASC");
        $stmt->execute($ids);
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (int)$row['id_skill'];
            if (count($result[$id] ?? []) < 3) {
                $result[$id][] = $row;
            }
        }
        return $result;
    }

    /** Ultima evaluacion del supervisor, nunca incorporada al calculo automatico. */
    private function evaluacionesSupervisor(array $idsUsuarios): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $idsUsuarios), static fn(int $id): bool => $id > 0)));
        if (!$ids) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("SELECT e.id_usuario,e.id_skill,e.puntaje,e.nivel,e.fecha_evaluacion,e.comentario,u.nombre AS evaluador FROM learning_skill_evaluaciones e JOIN (SELECT id_usuario,id_skill,MAX(id_skill_evaluacion) AS ultimo FROM learning_skill_evaluaciones GROUP BY id_usuario,id_skill) ult ON ult.ultimo=e.id_skill_evaluacion JOIN usuarios u ON u.id_usuario=e.id_evaluador WHERE e.id_usuario IN ($placeholders)");
        $stmt->execute($ids);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int)$row['id_usuario']][(int)$row['id_skill']] = $row;
        }
        return $map;
    }

    /**
     * La meta procede del curso asignado, salvo objetivo personalizado por admin.
     * Si existe objetivo manual sin curso, se muestra como SIN EVIDENCIA.
     */
    public function mapaUsuario(int $idUsuario, bool $conSupervisor = false): array
    {
        $perfil = $this->skills->perfilUsuario($idUsuario);
        $objetivos = $this->objetivosConfigurados($idUsuario);
        $mapa = [];
        foreach ($perfil['skills'] ?? [] as $skill) {
            $mapa[(int)$skill['id_skill']] = $skill;
        }

        if ($objetivos) {
            $faltantes = array_diff(array_keys($objetivos), array_keys($mapa));
            if ($faltantes) {
                $places = implode(',', array_fill(0, count($faltantes), '?'));
                $stmt = $this->db->prepare("SELECT id_skill,codigo,nombre,categoria,descripcion,icono FROM learning_skills WHERE estado=1 AND id_skill IN ($places)");
                $stmt->execute(array_values($faltantes));
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
                    $s['porcentaje'] = 0.0;
                    $s['nivel'] = 'pendiente';
                    $s['nivel_texto'] = 'Pendiente';
                    $s['nivel_objetivo'] = 'basico';
                    $s['cursos'] = [];
                    $s['cursos_asociados'] = 0;
                    $s['cursos_completados'] = 0;
                    $mapa[(int)$s['id_skill']] = $s;
                }
            }
        }

        $relacionados = $this->cursosVinculados(array_keys($mapa));
        $supervisores = $conSupervisor ? ($this->evaluacionesSupervisor([$idUsuario])[$idUsuario] ?? []) : [];
        $filas = [];
        $resumen = [
            'total' => 0, 'alcanzadas' => 0, 'con_brecha' => 0,
            'sin_evidencia' => 0, 'prioridad_alta' => 0,
            'brecha_promedio' => 0.0,
        ];
        $totalBrecha = 0.0;
        foreach ($mapa as $idSkill => $s) {
            $manual = $objetivos[$idSkill] ?? null;
            $nivel = $manual ? (string)$manual['nivel_objetivo'] : (string)($s['nivel_objetivo'] ?? 'basico');
            $prioridad = $manual ? (string)$manual['prioridad'] : 'media';
            $meta = SkillBrechas::evaluar((float)($s['porcentaje'] ?? 0), $nivel);
            $fila = array_merge($s, $meta, [
                'id_skill' => $idSkill,
                'nivel_objetivo' => $nivel,
                'nivel_objetivo_texto' => SkillBrechas::nivelTexto($nivel),
                'prioridad' => $prioridad,
                'fuente_objetivo' => $manual ? 'administrador' : 'curso',
                'objetivo_manual' => $manual !== null,
                'observacion_objetivo' => (string)($manual['observacion'] ?? ''),
                'cursos_vinculados' => $relacionados[$idSkill] ?? [],
                'supervisor' => $supervisores[$idSkill] ?? null,
            ]);
            $filas[] = $fila;
            $resumen['total']++;
            $resumen[$meta['estado'] === 'alcanzado' ? 'alcanzadas' : ($meta['estado'] === 'brecha' ? 'con_brecha' : 'sin_evidencia')]++;
            if ($meta['estado'] !== 'alcanzado' && $prioridad === 'alta') $resumen['prioridad_alta']++;
            $totalBrecha += $meta['brecha'];
        }
        $resumen['brecha_promedio'] = $resumen['total'] > 0 ? round($totalBrecha / $resumen['total'], 1) : 0.0;
        $ordenPrioridad = ['alta' => 0, 'media' => 1, 'baja' => 2];
        usort($filas, static function(array $a, array $b) use ($ordenPrioridad): int {
            $aa = (int)($a['estado'] === 'alcanzado');
            $bb = (int)($b['estado'] === 'alcanzado');
            if ($aa !== $bb) return $aa <=> $bb;
            $prioridad = ($ordenPrioridad[$a['prioridad']] ?? 1) <=> ($ordenPrioridad[$b['prioridad']] ?? 1);
            if ($prioridad !== 0) return $prioridad;
            $brecha = (float)$b['brecha'] <=> (float)$a['brecha'];
            return $brecha !== 0 ? $brecha : strcmp((string)$a['nombre'], (string)$b['nombre']);
        });
        return ['filas' => $filas, 'resumen' => $resumen];
    }

    public function mapaEquipo(string $buscar = '', int $idSkill = 0, string $estado = '', string $prioridad = ''): array
    {
        $usuarios = $this->usuarios($buscar);
        $supervisores = $this->evaluacionesSupervisor(array_column($usuarios, 'id_usuario'));
        $filas = [];
        $trabajadoresConMapa = [];
        $resumen = ['trabajadores' => 0, 'metas' => 0, 'alcanzadas' => 0, 'con_brecha' => 0, 'sin_evidencia' => 0, 'prioridad_alta' => 0, 'brecha_promedio' => 0.0];
        $sumaBrecha = 0.0;
        foreach ($usuarios as $usuario) {
            $userId = (int)$usuario['id_usuario'];
            $datos = $this->mapaUsuario($userId);
            foreach ($datos['filas'] as $fila) {
                if ($idSkill > 0 && (int)$fila['id_skill'] !== $idSkill) continue;
                if ($estado !== '' && $fila['estado'] !== $estado) continue;
                if ($prioridad !== '' && $fila['prioridad'] !== $prioridad) continue;
                $fila['id_usuario'] = $userId;
                $fila['usuario_nombre'] = (string)$usuario['nombre'];
                $fila['usuario_email'] = (string)$usuario['email'];
                $fila['supervisor'] = $supervisores[$userId][(int)$fila['id_skill']] ?? null;
                $filas[] = $fila;
                $trabajadoresConMapa[$userId] = true;
                $resumen['metas']++;
                $resumen[$fila['estado'] === 'alcanzado' ? 'alcanzadas' : ($fila['estado'] === 'brecha' ? 'con_brecha' : 'sin_evidencia')]++;
                if ($fila['estado'] !== 'alcanzado' && $fila['prioridad'] === 'alta') $resumen['prioridad_alta']++;
                $sumaBrecha += $fila['brecha'];
            }
        }
        $resumen['trabajadores'] = count($trabajadoresConMapa);
        $resumen['brecha_promedio'] = $resumen['metas'] ? round($sumaBrecha / $resumen['metas'], 1) : 0.0;
        usort($filas, static function(array $a, array $b): int {
            $n = (float)$b['brecha'] <=> (float)$a['brecha'];
            return $n !== 0 ? $n : strcmp($a['usuario_nombre'], $b['usuario_nombre']);
        });
        return ['filas' => $filas, 'resumen' => $resumen];
    }

    public function guardarObjetivo(int $idUsuario, int $idSkill, string $nivel, string $prioridad, string $observacion, int $admin): void
    {
        if ($idUsuario <= 0 || $idSkill <= 0 || $admin <= 0) throw new InvalidArgumentException('Selecciona un trabajador y una skill.');
        if (!in_array($nivel, ['basico','intermedio','avanzado'], true)) throw new InvalidArgumentException('Selecciona un nivel objetivo valido.');
        if (!in_array($prioridad, ['alta','media','baja'], true)) throw new InvalidArgumentException('La prioridad seleccionada no es valida.');
        $observacion = trim($observacion);
        if (function_exists('mb_strlen') ? mb_strlen($observacion, 'UTF-8') > 500 : strlen($observacion) > 500) {
            throw new InvalidArgumentException('La observacion no puede superar 500 caracteres.');
        }
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM usuarios WHERE id_usuario=:id AND rol='usuario' AND estado=1");
        $stmt->execute([':id' => $idUsuario]);
        if (!(int)$stmt->fetchColumn()) throw new InvalidArgumentException('El trabajador no esta activo.');
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM learning_skills WHERE id_skill=:id AND estado=1');
        $stmt->execute([':id' => $idSkill]);
        if (!(int)$stmt->fetchColumn()) throw new InvalidArgumentException('La skill no esta activa.');
        $stmt = $this->db->prepare("INSERT INTO learning_skill_objetivos_usuario (id_usuario,id_skill,nivel_objetivo,prioridad,observacion,configurado_por) VALUES (:usuario,:skill,:nivel,:prioridad,:observacion,:admin) ON DUPLICATE KEY UPDATE nivel_objetivo=VALUES(nivel_objetivo),prioridad=VALUES(prioridad),observacion=VALUES(observacion),configurado_por=VALUES(configurado_por)");
        $stmt->execute([':usuario' => $idUsuario, ':skill' => $idSkill, ':nivel' => $nivel, ':prioridad' => $prioridad, ':observacion' => $observacion !== '' ? $observacion : null, ':admin' => $admin]);
    }

    public function eliminarObjetivo(int $idUsuario, int $idSkill): void
    {
        if ($idUsuario <= 0 || $idSkill <= 0) throw new InvalidArgumentException('Identificadores no validos.');
        $stmt = $this->db->prepare('DELETE FROM learning_skill_objetivos_usuario WHERE id_usuario=:usuario AND id_skill=:skill');
        $stmt->execute([':usuario' => $idUsuario, ':skill' => $idSkill]);
    }
}
