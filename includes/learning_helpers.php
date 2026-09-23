<?php
if (!function_exists('learningH')) {
    function learningH($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('learningEstadoTexto')) {
    function learningEstadoTexto(string $estado): string {
        $map=['pendiente'=>'Pendiente','en_progreso'=>'En progreso','completada'=>'Completada','vencida'=>'Vencida','desaprobada'=>'Desaprobada','planificada'=>'Planificada','activa'=>'Activa','cerrada'=>'Cerrada','publicado'=>'Publicado','borrador'=>'Borrador','archivado'=>'Archivado','publicada'=>'Publicada'];
        return $map[$estado] ?? ucfirst(str_replace('_',' ',$estado));
    }
}
if (!function_exists('learningEstadoClase')) {
    function learningEstadoClase(string $estado): string {
        $map=['completada'=>'is-success','publicado'=>'is-success','publicada'=>'is-success','activa'=>'is-success','en_progreso'=>'is-info','pendiente'=>'is-warning','planificada'=>'is-warning','vencida'=>'is-danger','desaprobada'=>'is-danger','cerrada'=>'is-muted','borrador'=>'is-muted','archivado'=>'is-muted'];
        return $map[$estado] ?? 'is-muted';
    }
}
if (!function_exists('learningDuracion')) {
    function learningDuracion(int $min): string {
        if ($min <= 0) return 'Por definir';
        $h=intdiv($min,60); $m=$min%60;
        return $h>0 ? $h.' h'.($m>0?' '.$m.' min':'') : $m.' min';
    }
}
