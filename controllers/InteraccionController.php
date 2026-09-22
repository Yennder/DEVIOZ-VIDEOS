<?php

require_once __DIR__ . '/../models/Interaccion.php';

class InteraccionController
{
    private $modelo;

    public function __construct()
    {
        $this->modelo = new Interaccion();
    }

    public function estadoVideo($u, $v) { return $this->modelo->estadoVideo($u, $v); }
    public function toggleLike($u, $v) { return $this->modelo->toggleLike($u, $v); }
    public function toggleFavorito($u, $v) { return $this->modelo->toggleFavorito($u, $v); }
    public function registrarHistorial($u, $v) { return $this->modelo->registrarHistorial($u, $v); }
    public function guardarProgreso($u, $v, $p, $d) { return $this->modelo->guardarProgreso($u, $v, $p, $d); }
    public function obtenerProgreso($u, $v) { return $this->modelo->obtenerProgreso($u, $v); }
    public function favoritosUsuario($u) { return $this->modelo->favoritosUsuario($u); }
    public function historialUsuario($u, $l=60) { return $this->modelo->historialUsuario($u, $l); }
    public function continuarViendoUsuario($u, $l=8) { return $this->modelo->continuarViendoUsuario($u, $l); }
    public function videosPopulares($l=8) { return $this->modelo->videosPopulares($l); }
    public function recomendadosUsuario($u, $l=8) { return $this->modelo->recomendadosUsuario($u, $l); }
    public function comentariosVideo($v) { return $this->modelo->comentariosVideo($v); }
    public function agregarComentario($u, $v, $c) { return $this->modelo->agregarComentario($u, $v, $c); }
    public function agregarRespuestaComentario($u, $v, $p, $c) { return $this->modelo->agregarRespuestaComentario($u, $v, $p, $c); }
    public function editarComentario($u, $id, $c) { return $this->modelo->editarComentario($u, $id, $c); }
    public function eliminarComentario($u, $id, $a=false) { return $this->modelo->eliminarComentario($u, $id, $a); }
    public function playlistsUsuario($u) { return $this->modelo->playlistsUsuario($u); }
    public function crearPlaylist($u, $n, $d='') { return $this->modelo->crearPlaylist($u, $n, $d); }
    public function agregarVideoPlaylist($u, $p, $v) { return $this->modelo->agregarVideoPlaylist($u, $p, $v); }
    public function quitarVideoPlaylist($u, $p, $v) { return $this->modelo->quitarVideoPlaylist($u, $p, $v); }
    public function eliminarPlaylist($u, $p) { return $this->modelo->eliminarPlaylist($u, $p); }
    public function videosPlaylist($u, $p) { return $this->modelo->videosPlaylist($u, $p); }
    public function resumenUsuario($u) { return $this->modelo->resumenUsuario($u); }
    public function totalesGlobales() { return $this->modelo->totalesGlobales(); }
    public function comentariosAdmin($b='') { return $this->modelo->comentariosAdmin($b); }
}
