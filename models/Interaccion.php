<?php

require_once __DIR__ . '/../config/conexion.php';

class Interaccion
{
    private $conexion;
    private $comentariosConRespuestas = null;

    public function __construct()
    {
        $db = new Conexion();
        $this->conexion = $db->conectar();
    }

    private function soportaRespuestasComentarios()
    {
        if ($this->comentariosConRespuestas !== null) {
            return $this->comentariosConRespuestas;
        }

        $stmt = $this->conexion->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'comentarios'
              AND COLUMN_NAME = 'id_comentario_padre'
        ");
        $stmt->execute();
        $this->comentariosConRespuestas = ((int)$stmt->fetchColumn()) > 0;
        return $this->comentariosConRespuestas;
    }

    public function estadoVideo($idUsuario, $idVideo)
    {
        $sql = "
            SELECT
                EXISTS(SELECT 1 FROM likes WHERE id_usuario = :usuario_like AND id_video = :video_like) AS liked,
                EXISTS(SELECT 1 FROM favoritos WHERE id_usuario = :usuario_fav AND id_video = :video_fav) AS favorito,
                (SELECT COUNT(*) FROM likes WHERE id_video = :video_total) AS total_likes
        ";

        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([
            ':usuario_like' => $idUsuario,
            ':video_like' => $idVideo,
            ':usuario_fav' => $idUsuario,
            ':video_fav' => $idVideo,
            ':video_total' => $idVideo,
        ]);

        $estado = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'liked' => !empty($estado['liked']),
            'favorito' => !empty($estado['favorito']),
            'total_likes' => (int)($estado['total_likes'] ?? 0),
        ];
    }

    public function totalLikesVideo($idVideo)
    {
        $stmt = $this->conexion->prepare('SELECT COUNT(*) FROM likes WHERE id_video = :video');
        $stmt->execute([':video' => $idVideo]);
        return (int)$stmt->fetchColumn();
    }

    public function toggleLike($idUsuario, $idVideo)
    {
        $this->conexion->beginTransaction();

        try {
            $stmt = $this->conexion->prepare(
                'SELECT id_like FROM likes WHERE id_usuario = :usuario AND id_video = :video LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([':usuario' => $idUsuario, ':video' => $idVideo]);
            $id = $stmt->fetchColumn();

            if ($id) {
                $del = $this->conexion->prepare('DELETE FROM likes WHERE id_like = :id');
                $del->execute([':id' => $id]);
                $activo = false;
            } else {
                $ins = $this->conexion->prepare(
                    'INSERT INTO likes (id_usuario, id_video) VALUES (:usuario, :video)'
                );
                $ins->execute([':usuario' => $idUsuario, ':video' => $idVideo]);
                $activo = true;
            }

            $this->conexion->commit();

            return [
                'activo' => $activo,
                'total' => $this->totalLikesVideo($idVideo),
            ];
        } catch (Throwable $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $e;
        }
    }

    public function toggleFavorito($idUsuario, $idVideo)
    {
        $this->conexion->beginTransaction();

        try {
            $stmt = $this->conexion->prepare(
                'SELECT id_favorito FROM favoritos WHERE id_usuario = :usuario AND id_video = :video LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([':usuario' => $idUsuario, ':video' => $idVideo]);
            $id = $stmt->fetchColumn();

            if ($id) {
                $del = $this->conexion->prepare('DELETE FROM favoritos WHERE id_favorito = :id');
                $del->execute([':id' => $id]);
                $activo = false;
            } else {
                $ins = $this->conexion->prepare(
                    'INSERT INTO favoritos (id_usuario, id_video) VALUES (:usuario, :video)'
                );
                $ins->execute([':usuario' => $idUsuario, ':video' => $idVideo]);
                $activo = true;
            }

            $this->conexion->commit();
            return ['activo' => $activo];
        } catch (Throwable $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $e;
        }
    }

    public function registrarHistorial($idUsuario, $idVideo)
    {
        $sql = "
            INSERT INTO historial (id_usuario, id_video, veces_visto)
            VALUES (:usuario, :video, 1)
            ON DUPLICATE KEY UPDATE
                ultima_visualizacion = CURRENT_TIMESTAMP,
                veces_visto = veces_visto + 1
        ";
        $stmt = $this->conexion->prepare($sql);
        return $stmt->execute([':usuario' => $idUsuario, ':video' => $idVideo]);
    }

    public function tocarHistorial($idUsuario, $idVideo)
    {
        $sql = "
            INSERT INTO historial (id_usuario, id_video, veces_visto)
            VALUES (:usuario, :video, 1)
            ON DUPLICATE KEY UPDATE ultima_visualizacion = CURRENT_TIMESTAMP
        ";
        $stmt = $this->conexion->prepare($sql);
        return $stmt->execute([':usuario' => $idUsuario, ':video' => $idVideo]);
    }

    public function guardarProgreso($idUsuario, $idVideo, $posicion, $duracion)
    {
        $posicion = max(0, (int)$posicion);
        $duracion = max(0, (int)$duracion);
        $porcentaje = $duracion > 0 ? min(100, round(($posicion / $duracion) * 100, 2)) : 0;

        $sql = "
            INSERT INTO video_progreso
                (id_usuario, id_video, posicion_segundos, duracion_segundos, porcentaje)
            VALUES
                (:usuario, :video, :posicion, :duracion, :porcentaje)
            ON DUPLICATE KEY UPDATE
                posicion_segundos = VALUES(posicion_segundos),
                duracion_segundos = VALUES(duracion_segundos),
                porcentaje = VALUES(porcentaje),
                fecha_actualizacion = CURRENT_TIMESTAMP
        ";

        $stmt = $this->conexion->prepare($sql);
        $ok = $stmt->execute([
            ':usuario' => $idUsuario,
            ':video' => $idVideo,
            ':posicion' => $posicion,
            ':duracion' => $duracion,
            ':porcentaje' => $porcentaje,
        ]);

        if ($ok) {
            $this->tocarHistorial($idUsuario, $idVideo);
        }

        return [
            'ok' => $ok,
            'porcentaje' => $porcentaje,
            'posicion' => $posicion,
        ];
    }

    public function obtenerProgreso($idUsuario, $idVideo)
    {
        $stmt = $this->conexion->prepare(
            'SELECT posicion_segundos, duracion_segundos, porcentaje FROM video_progreso WHERE id_usuario = :usuario AND id_video = :video LIMIT 1'
        );
        $stmt->execute([':usuario' => $idUsuario, ':video' => $idVideo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: [
            'posicion_segundos' => 0,
            'duracion_segundos' => 0,
            'porcentaje' => 0,
        ];
    }

    private function longitud($texto)
    {
        return function_exists('mb_strlen')
            ? mb_strlen((string)$texto, 'UTF-8')
            : strlen((string)$texto);
    }

    private function videoSelectBase()
    {
        return "
            SELECT
                v.*,
                c.nombre AS categoria,
                (SELECT COUNT(*) FROM likes l WHERE l.id_video = v.id_video) AS likes,
                (SELECT COUNT(*) FROM favoritos f WHERE f.id_video = v.id_video) AS favoritos
            FROM videos v
            INNER JOIN categorias c ON c.id_categoria = v.id_categoria
        ";
    }

    public function favoritosUsuario($idUsuario)
    {
        $sql = $this->videoSelectBase() . "
            INNER JOIN favoritos uf ON uf.id_video = v.id_video
            WHERE uf.id_usuario = :usuario
              AND v.estado = 'publicado'
            ORDER BY uf.fecha_creacion DESC
        ";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([':usuario' => $idUsuario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function historialUsuario($idUsuario, $limite = 60)
    {
        $limite = max(1, min(200, (int)$limite));
        $sql = "
            SELECT
                v.*,
                c.nombre AS categoria,
                h.ultima_visualizacion,
                h.veces_visto,
                COALESCE(vp.porcentaje, 0) AS porcentaje,
                COALESCE(vp.posicion_segundos, 0) AS posicion_segundos,
                (SELECT COUNT(*) FROM likes l WHERE l.id_video = v.id_video) AS likes,
                (SELECT COUNT(*) FROM favoritos f WHERE f.id_video = v.id_video) AS favoritos
            FROM historial h
            INNER JOIN videos v ON v.id_video = h.id_video
            INNER JOIN categorias c ON c.id_categoria = v.id_categoria
            LEFT JOIN video_progreso vp
                ON vp.id_usuario = h.id_usuario AND vp.id_video = h.id_video
            WHERE h.id_usuario = :usuario
              AND v.estado = 'publicado'
            ORDER BY h.ultima_visualizacion DESC
            LIMIT {$limite}
        ";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([':usuario' => $idUsuario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function continuarViendoUsuario($idUsuario, $limite = 8)
    {
        $limite = max(1, min(30, (int)$limite));
        $sql = "
            SELECT
                v.*,
                c.nombre AS categoria,
                h.ultima_visualizacion,
                COALESCE(vp.porcentaje, 0) AS porcentaje,
                COALESCE(vp.posicion_segundos, 0) AS posicion_segundos,
                (SELECT COUNT(*) FROM likes l WHERE l.id_video = v.id_video) AS likes,
                (SELECT COUNT(*) FROM favoritos f WHERE f.id_video = v.id_video) AS favoritos
            FROM video_progreso vp
            INNER JOIN videos v ON v.id_video = vp.id_video
            INNER JOIN categorias c ON c.id_categoria = v.id_categoria
            LEFT JOIN historial h
                ON h.id_usuario = vp.id_usuario AND h.id_video = vp.id_video
            WHERE vp.id_usuario = :usuario
              AND v.estado = 'publicado'
              AND vp.porcentaje >= 1
              AND vp.porcentaje < 95
            ORDER BY vp.fecha_actualizacion DESC
            LIMIT {$limite}
        ";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([':usuario' => $idUsuario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function videosPopulares($limite = 8)
    {
        $limite = max(1, min(30, (int)$limite));
        $sql = $this->videoSelectBase() . "
            WHERE v.estado = 'publicado'
            ORDER BY (v.vistas + ((SELECT COUNT(*) FROM likes lx WHERE lx.id_video = v.id_video) * 5) + ((SELECT COUNT(*) FROM favoritos fx WHERE fx.id_video = v.id_video) * 2)) DESC, v.fecha_publicacion DESC
            LIMIT {$limite}
        ";
        return $this->conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function videosPopularesNoVistos($idUsuario, $limite = 8)
    {
        $limite = max(1, min(30, (int)$limite));
        $sql = $this->videoSelectBase() . "
            WHERE v.estado = 'publicado'
              AND NOT EXISTS (
                    SELECT 1 FROM historial h
                    WHERE h.id_usuario = :usuario AND h.id_video = v.id_video
              )
            ORDER BY (v.vistas + ((SELECT COUNT(*) FROM likes lx WHERE lx.id_video = v.id_video) * 5) + ((SELECT COUNT(*) FROM favoritos fx WHERE fx.id_video = v.id_video) * 2)) DESC,
                     v.fecha_publicacion DESC
            LIMIT {$limite}
        ";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([':usuario' => $idUsuario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function recomendadosUsuario($idUsuario, $limite = 8)
    {
        $limite = max(1, min(30, (int)$limite));

        $categoriasSql = "
            SELECT id_categoria, SUM(peso) peso
            FROM (
                SELECT v.id_categoria, 3 AS peso
                FROM likes l INNER JOIN videos v ON v.id_video = l.id_video
                WHERE l.id_usuario = :u1
                UNION ALL
                SELECT v.id_categoria, 3 AS peso
                FROM favoritos f INNER JOIN videos v ON v.id_video = f.id_video
                WHERE f.id_usuario = :u2
                UNION ALL
                SELECT v.id_categoria, 1 AS peso
                FROM historial h INNER JOIN videos v ON v.id_video = h.id_video
                WHERE h.id_usuario = :u3
            ) datos
            GROUP BY id_categoria
            ORDER BY peso DESC
            LIMIT 4
        ";

        $stmt = $this->conexion->prepare($categoriasSql);
        $stmt->execute([':u1' => $idUsuario, ':u2' => $idUsuario, ':u3' => $idUsuario]);
        $categorias = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        if (!$categorias) {
            $noVistos = $this->videosPopularesNoVistos($idUsuario, $limite);
            return $noVistos ?: $this->videosPopulares($limite);
        }

        $placeholders = implode(',', array_fill(0, count($categorias), '?'));
        $sql = $this->videoSelectBase() . "
            WHERE v.estado = 'publicado'
              AND v.id_categoria IN ({$placeholders})
              AND v.id_video NOT IN (
                    SELECT h.id_video FROM historial h WHERE h.id_usuario = ?
              )
            ORDER BY (v.vistas + ((SELECT COUNT(*) FROM likes lx WHERE lx.id_video = v.id_video) * 5) + ((SELECT COUNT(*) FROM favoritos fx WHERE fx.id_video = v.id_video) * 2)) DESC, v.fecha_publicacion DESC
            LIMIT {$limite}
        ";

        $stmt = $this->conexion->prepare($sql);
        $params = $categorias;
        $params[] = $idUsuario;
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($rows) {
            return $rows;
        }

        $noVistos = $this->videosPopularesNoVistos($idUsuario, $limite);
        return $noVistos ?: $this->videosPopulares($limite);
    }

    public function comentariosVideo($idVideo)
    {
        $campoPadre = $this->soportaRespuestasComentarios()
            ? 'co.id_comentario_padre'
            : 'NULL AS id_comentario_padre';

        $sql = "
            SELECT co.id_comentario, co.id_usuario, {$campoPadre}, co.contenido, co.fecha_creacion,
                   co.fecha_actualizacion, u.nombre
            FROM comentarios co
            INNER JOIN usuarios u ON u.id_usuario = co.id_usuario
            WHERE co.id_video = :video AND co.estado = 'publicado'
            ORDER BY co.fecha_creacion ASC, co.id_comentario ASC
        ";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([':video' => $idVideo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function agregarComentario($idUsuario, $idVideo, $contenido)
    {
        $contenido = trim((string)$contenido);
        if ($contenido === '' || $this->longitud($contenido) > 1000) {
            return false;
        }

        $stmt = $this->conexion->prepare(
            "INSERT INTO comentarios (id_usuario, id_video, contenido)
             SELECT :usuario, :video, :contenido
             WHERE NOT EXISTS (
                 SELECT 1 FROM comentarios
                 WHERE id_usuario = :usuario_reciente
                   AND fecha_creacion >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 10 SECOND)
             )"
        );
        $stmt->execute([
            ':usuario' => $idUsuario,
            ':video' => $idVideo,
            ':contenido' => $contenido,
            ':usuario_reciente' => $idUsuario,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function agregarRespuestaComentario($idUsuario, $idVideo, $idComentarioPadre, $contenido)
    {
        if (!$this->soportaRespuestasComentarios()) {
            return false;
        }

        $contenido = trim((string)$contenido);
        $idVideo = (int)$idVideo;
        $idComentarioPadre = (int)$idComentarioPadre;
        if ($idVideo <= 0 || $idComentarioPadre <= 0 || $contenido === '' || $this->longitud($contenido) > 1000) {
            return false;
        }

        $padre = $this->conexion->prepare(
            "SELECT id_comentario FROM comentarios
             WHERE id_comentario = :padre
               AND id_video = :video
               AND estado = 'publicado'
             LIMIT 1"
        );
        $padre->execute([':padre' => $idComentarioPadre, ':video' => $idVideo]);
        if (!$padre->fetchColumn()) {
            return false;
        }

        // Las respuestas se mantienen a un solo nivel para que la conversacion
        // sea legible: si se responde a una respuesta, se conserva su padre raiz.
        $raiz = $this->conexion->prepare(
            'SELECT COALESCE(id_comentario_padre, id_comentario) FROM comentarios WHERE id_comentario = :id LIMIT 1'
        );
        $raiz->execute([':id' => $idComentarioPadre]);
        $idRaiz = (int)$raiz->fetchColumn();
        if ($idRaiz <= 0) {
            return false;
        }

        $stmt = $this->conexion->prepare(
            "INSERT INTO comentarios (id_usuario, id_video, id_comentario_padre, contenido)
             SELECT :usuario, :video, :padre, :contenido
             WHERE NOT EXISTS (
                 SELECT 1 FROM comentarios
                 WHERE id_usuario = :usuario_reciente
                   AND fecha_creacion >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 3 SECOND)
             )"
        );
        $stmt->execute([
            ':usuario' => $idUsuario,
            ':video' => $idVideo,
            ':padre' => $idRaiz,
            ':contenido' => $contenido,
            ':usuario_reciente' => $idUsuario,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function editarComentario($idUsuario, $idComentario, $contenido)
    {
        $contenido = trim((string)$contenido);
        if ($contenido === '' || $this->longitud($contenido) > 1000) {
            return false;
        }

        $stmt = $this->conexion->prepare(
            'UPDATE comentarios SET contenido = :contenido, fecha_actualizacion = CURRENT_TIMESTAMP WHERE id_comentario = :id AND id_usuario = :usuario'
        );
        $stmt->execute([
            ':contenido' => $contenido,
            ':id' => $idComentario,
            ':usuario' => $idUsuario,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function eliminarComentario($idUsuario, $idComentario, $esAdmin = false)
    {
        if ($esAdmin) {
            $stmt = $this->conexion->prepare('DELETE FROM comentarios WHERE id_comentario = :id');
            $stmt->execute([':id' => $idComentario]);
        } else {
            $stmt = $this->conexion->prepare(
                'DELETE FROM comentarios WHERE id_comentario = :id AND id_usuario = :usuario'
            );
            $stmt->execute([':id' => $idComentario, ':usuario' => $idUsuario]);
        }

        return $stmt->rowCount() > 0;
    }

    public function playlistsUsuario($idUsuario)
    {
        $sql = "
            SELECT p.*,
                   COUNT(pv.id_playlist_video) AS total_videos
            FROM playlists p
            LEFT JOIN playlist_videos pv ON pv.id_playlist = p.id_playlist
            WHERE p.id_usuario = :usuario
            GROUP BY p.id_playlist
            ORDER BY p.fecha_actualizacion DESC, p.fecha_creacion DESC
        ";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([':usuario' => $idUsuario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crearPlaylist($idUsuario, $nombre, $descripcion = '')
    {
        $nombre = trim((string)$nombre);
        $descripcion = trim((string)$descripcion);
        if (
            $nombre === ''
            || $this->longitud($nombre) > 100
            || $this->longitud($descripcion) > 500
        ) {
            return false;
        }

        $stmt = $this->conexion->prepare(
            'INSERT INTO playlists (id_usuario, nombre, descripcion) VALUES (:usuario, :nombre, :descripcion)'
        );
        try {
            $stmt->execute([
                ':usuario' => $idUsuario,
                ':nombre' => $nombre,
                ':descripcion' => $descripcion,
            ]);
            return (int)$this->conexion->lastInsertId();
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return false;
            }
            throw $e;
        }
    }

    public function agregarVideoPlaylist($idUsuario, $idPlaylist, $idVideo)
    {
        $check = $this->conexion->prepare(
            'SELECT id_playlist FROM playlists WHERE id_playlist = :playlist AND id_usuario = :usuario LIMIT 1'
        );
        $check->execute([':playlist' => $idPlaylist, ':usuario' => $idUsuario]);
        if (!$check->fetchColumn()) {
            return false;
        }

        $stmt = $this->conexion->prepare(
            'INSERT IGNORE INTO playlist_videos (id_playlist, id_video) VALUES (:playlist, :video)'
        );
        $stmt->execute([':playlist' => $idPlaylist, ':video' => $idVideo]);

        $touch = $this->conexion->prepare(
            'UPDATE playlists SET fecha_actualizacion = CURRENT_TIMESTAMP WHERE id_playlist = :playlist'
        );
        $touch->execute([':playlist' => $idPlaylist]);
        return true;
    }

    public function quitarVideoPlaylist($idUsuario, $idPlaylist, $idVideo)
    {
        $sql = "
            DELETE pv FROM playlist_videos pv
            INNER JOIN playlists p ON p.id_playlist = pv.id_playlist
            WHERE pv.id_playlist = :playlist
              AND pv.id_video = :video
              AND p.id_usuario = :usuario
        ";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([
            ':playlist' => $idPlaylist,
            ':video' => $idVideo,
            ':usuario' => $idUsuario,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function eliminarPlaylist($idUsuario, $idPlaylist)
    {
        $stmt = $this->conexion->prepare(
            'DELETE FROM playlists WHERE id_playlist = :playlist AND id_usuario = :usuario'
        );
        $stmt->execute([':playlist' => $idPlaylist, ':usuario' => $idUsuario]);
        return $stmt->rowCount() > 0;
    }

    public function videosPlaylist($idUsuario, $idPlaylist)
    {
        $sql = $this->videoSelectBase() . "
            INNER JOIN playlist_videos pv ON pv.id_video = v.id_video
            INNER JOIN playlists p ON p.id_playlist = pv.id_playlist
            WHERE p.id_usuario = :usuario
              AND p.id_playlist = :playlist
              AND v.estado = 'publicado'
            ORDER BY pv.orden ASC, pv.fecha_agregado DESC
        ";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([':usuario' => $idUsuario, ':playlist' => $idPlaylist]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function resumenUsuario($idUsuario)
    {
        $sql = "
            SELECT
                (SELECT COUNT(*) FROM favoritos WHERE id_usuario = :u1) favoritos,
                (SELECT COUNT(*) FROM likes WHERE id_usuario = :u2) likes,
                (SELECT COUNT(*) FROM historial WHERE id_usuario = :u3) vistos,
                (SELECT COUNT(*) FROM playlists WHERE id_usuario = :u4) playlists
        ";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([':u1'=>$idUsuario, ':u2'=>$idUsuario, ':u3'=>$idUsuario, ':u4'=>$idUsuario]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['favoritos'=>0,'likes'=>0,'vistos'=>0,'playlists'=>0];
    }

    public function totalesGlobales()
    {
        $sql = "
            SELECT
                (SELECT COUNT(*) FROM likes) total_likes,
                (SELECT COUNT(*) FROM favoritos) total_favoritos,
                (SELECT COUNT(*) FROM comentarios) total_comentarios,
                (SELECT COUNT(*) FROM historial) total_historial,
                (SELECT COUNT(*) FROM playlists) total_playlists
        ";
        return $this->conexion->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function comentariosAdmin($buscar = '')
    {
        $sql = "
            SELECT co.*, u.nombre AS usuario, v.titulo AS video
            FROM comentarios co
            INNER JOIN usuarios u ON u.id_usuario = co.id_usuario
            INNER JOIN videos v ON v.id_video = co.id_video
            WHERE 1=1
        ";
        $params = [];
        if ($buscar !== '') {
            $sql .= ' AND (co.contenido LIKE :buscar OR u.nombre LIKE :buscar OR v.titulo LIKE :buscar)';
            $params[':buscar'] = '%' . $buscar . '%';
        }
        $sql .= ' ORDER BY co.fecha_creacion DESC LIMIT 300';
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
