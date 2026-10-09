<?php

require_once __DIR__ . '/../config/conexion.php';

class Busqueda
{
    private PDO $db;

    public function __construct()
    {
        $this->db = (new Conexion())->conectar();
    }

    public function videos(string $q, int $limit = 12): array
    {
        $q = trim($q);
        if ($q === '') return [];
        $limit = max(1, min(30, $limit));
        $like = '%' . $q . '%';
        $prefix = $q . '%';
        $sql = "
            SELECT v.id_video,v.titulo,v.descripcion,v.miniatura,v.vistas,v.fecha_publicacion,
                   c.nombre AS categoria,s.titulo AS serie,
                   (SELECT COALESCE(ROUND(AVG(vr.estrellas), 1), 0) FROM video_valoraciones vr WHERE vr.id_video = v.id_video) AS valoracion_promedio,
                   (SELECT COUNT(*) FROM video_valoraciones vr WHERE vr.id_video = v.id_video) AS valoracion_total
            FROM videos v
            INNER JOIN categorias c ON c.id_categoria=v.id_categoria
            LEFT JOIN series s ON s.id_serie=v.id_serie
            WHERE v.estado='publicado'
              AND (v.titulo LIKE :like1 OR v.descripcion LIKE :like2 OR c.nombre LIKE :like3 OR s.titulo LIKE :like4)
            ORDER BY CASE WHEN v.titulo LIKE :prefix THEN 0 ELSE 1 END,
                     v.vistas DESC,v.fecha_publicacion DESC
            LIMIT {$limit}
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':like1'=>$like,':like2'=>$like,':like3'=>$like,':like4'=>$like,':prefix'=>$prefix]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function series(string $q, int $limit = 8): array
    {
        $q = trim($q);
        if ($q === '') return [];
        $limit = max(1, min(20, $limit));
        $like = '%' . $q . '%';
        $prefix = $q . '%';
        $sql = "
            SELECT s.id_serie,s.titulo,s.descripcion,s.imagen_portada,
                   COUNT(DISTINCT t.id_temporada) AS total_temporadas,
                   COUNT(DISTINCT v.id_video) AS total_capitulos
            FROM series s
            LEFT JOIN temporadas t ON t.id_serie=s.id_serie
            LEFT JOIN videos v ON v.id_serie=s.id_serie AND v.estado='publicado'
            WHERE s.estado=1 AND (s.titulo LIKE :like1 OR s.descripcion LIKE :like2)
            GROUP BY s.id_serie
            ORDER BY CASE WHEN s.titulo LIKE :prefix THEN 0 ELSE 1 END,s.titulo ASC
            LIMIT {$limit}
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':like1'=>$like,':like2'=>$like,':prefix'=>$prefix]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function cursos(string $q, int $limit = 8): array
    {
        $q = trim($q);
        if ($q === '') return [];
        $limit = max(1, min(20, $limit));
        $like = '%' . $q . '%';
        $prefix = $q . '%';
        $sql = "
            SELECT c.id_curso,c.titulo,c.descripcion,c.nivel,c.duracion_estimada_minutos,
                   COUNT(DISTINCT l.id_leccion) AS total_lecciones,
                   MIN(v.miniatura) AS miniatura_referencia
            FROM learning_cursos c
            LEFT JOIN learning_curso_lecciones l ON l.id_curso=c.id_curso
            LEFT JOIN videos v ON v.id_video=l.id_video
            WHERE c.estado='publicado' AND (c.titulo LIKE :like1 OR c.descripcion LIKE :like2 OR c.nivel LIKE :like3)
            GROUP BY c.id_curso
            ORDER BY CASE WHEN c.titulo LIKE :prefix THEN 0 ELSE 1 END,c.fecha_actualizacion DESC
            LIMIT {$limit}
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':like1'=>$like,':like2'=>$like,':like3'=>$like,':prefix'=>$prefix]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function sugerencias(string $q, bool $incluyeCursos, int $limit = 7): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) return [];
        $limit = max(3, min(10, $limit));
        $items = [];

        foreach ($this->videos($q, 4) as $v) {
            $items[] = [
                'tipo'=>'video','icono'=>'▶','titulo'=>$v['titulo'],
                'subtitulo'=>trim(($v['categoria'] ?? '') . (($v['serie'] ?? '') !== '' ? ' · ' . $v['serie'] : '')),
                'url'=>'/DEVIOZ-VIDEOS/public/detalle.php?id='.(int)$v['id_video']
            ];
        }
        foreach ($this->series($q, 2) as $s) {
            $items[] = [
                'tipo'=>'serie','icono'=>'▣','titulo'=>$s['titulo'],
                'subtitulo'=>'Serie · '.(int)$s['total_temporadas'].' temporadas',
                'url'=>'/DEVIOZ-VIDEOS/public/detalle_serie.php?id='.(int)$s['id_serie']
            ];
        }
        if ($incluyeCursos) {
            foreach ($this->cursos($q, 2) as $c) {
                $items[] = [
                    'tipo'=>'curso','icono'=>'🎓','titulo'=>$c['titulo'],
                    'subtitulo'=>'Curso · '.ucfirst((string)$c['nivel']),
                    'url'=>'/DEVIOZ-VIDEOS/public/aprendizaje.php'
                ];
            }
        }
        return array_slice($items, 0, $limit);
    }

    public function cursosParaScope(): array
    {
        return $this->db->query("SELECT id_curso,titulo FROM learning_cursos WHERE estado='publicado' ORDER BY titulo")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function seriesParaScope(): array
    {
        return $this->db->query("SELECT id_serie,titulo FROM series WHERE estado=1 ORDER BY titulo")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function videosParaScope(): array
    {
        return $this->db->query("SELECT id_video,titulo FROM videos WHERE estado='publicado' ORDER BY titulo LIMIT 250")->fetchAll(PDO::FETCH_ASSOC);
    }
}
