<?php
require_once __DIR__ . '/../config/conexion.php';

/** Generos editoriales (no equivalen a las skills del Learning Lab). */
class Genero
{
    private PDO $db;

    public function __construct()
    {
        $this->db = (new Conexion())->conectar();
    }

    public function listar(bool $soloActivos = false): array
    {
        $where = $soloActivos ? 'WHERE g.estado = 1' : '';
        $sql = "SELECT g.id_genero, g.nombre, g.descripcion, g.estado,
                    (SELECT COUNT(*) FROM video_generos vg WHERE vg.id_genero = g.id_genero) AS total_videos
                FROM generos g {$where} ORDER BY g.nombre ASC";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM generos WHERE id_genero = :id');
        $st->execute([':id' => $id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function idsDeVideo(int $idVideo): array
    {
        $st = $this->db->prepare('SELECT id_genero FROM video_generos WHERE id_video = :id');
        $st->execute([':id' => $idVideo]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    public function guardar(int $id, string $nombre, string $descripcion): bool
    {
        if ($nombre === '' || strlen($nombre) > 480 || strlen($descripcion) > 2000) {
            return false;
        }
        $largoNombre = function_exists('mb_strlen') ? mb_strlen($nombre, 'UTF-8') : strlen($nombre);
        $largoDescripcion = function_exists('mb_strlen') ? mb_strlen($descripcion, 'UTF-8') : strlen($descripcion);
        if ($largoNombre > 120 || $largoDescripcion > 500) {
            return false;
        }
        try {
            if ($id > 0) {
                $st = $this->db->prepare('UPDATE generos SET nombre=:nombre, descripcion=:descripcion WHERE id_genero=:id');
                return $st->execute([':nombre'=>$nombre, ':descripcion'=>$descripcion, ':id'=>$id]);
            }
            $st = $this->db->prepare('INSERT INTO generos (nombre, descripcion) VALUES (:nombre, :descripcion)');
            return $st->execute([':nombre'=>$nombre, ':descripcion'=>$descripcion]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') return false; // nombre duplicado
            throw $e;
        }
    }

    public function cambiarEstado(int $id, bool $activo): bool
    {
        $st = $this->db->prepare('UPDATE generos SET estado=:estado WHERE id_genero=:id');
        return $st->execute([':estado'=>$activo ? 1 : 0, ':id'=>$id]);
    }
}
