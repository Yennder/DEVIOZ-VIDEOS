<?php

require_once __DIR__ . '/../models/Notificacion.php';

class NotificacionController
{
    private Notificacion $modelo;

    public function __construct()
    {
        $this->modelo = new Notificacion();
    }

    public function sincronizarUsuario(int $idUsuario): void { $this->modelo->sincronizarRecordatorios($idUsuario); }
    public function listar(int $idUsuario, int $limite = 30): array { return $this->modelo->listar($idUsuario,$limite); }
    public function noLeidas(int $idUsuario, int $limite = 6): array { return $this->modelo->noLeidas($idUsuario,$limite); }
    public function contarNoLeidas(int $idUsuario): int { return $this->modelo->contarNoLeidas($idUsuario); }
    public function marcarLeida(int $idNotificacion, int $idUsuario): bool { return $this->modelo->marcarLeida($idNotificacion,$idUsuario); }
    public function marcarTodas(int $idUsuario): int { return $this->modelo->marcarTodas($idUsuario); }
    public function eliminar(int $idNotificacion, int $idUsuario): bool { return $this->modelo->eliminar($idNotificacion,$idUsuario); }
}
