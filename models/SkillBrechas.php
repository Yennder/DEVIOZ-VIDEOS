<?php
/** V5.1: comparacion de evidencia academica y metas, NO certificacion de habilidades. */
final class SkillBrechas
{
    public static function umbral(string $nivel): float
    {
        return ['basico' => 40.0, 'intermedio' => 60.0, 'avanzado' => 80.0][$nivel] ?? 40.0;
    }

    public static function nivelTexto(string $nivel): string
    {
        return ['basico' => 'Básico', 'intermedio' => 'Intermedio', 'avanzado' => 'Avanzado'][$nivel] ?? 'Básico';
    }

    public static function evaluar(float $evidencia, string $nivelObjetivo): array
    {
        $actual = round(max(0.0, min(100.0, $evidencia)), 1);
        $objetivo = self::umbral($nivelObjetivo);
        $brecha = round(max(0.0, $objetivo - $actual), 1);
        $estado = $actual <= 0 ? 'sin_evidencia' : ($brecha > 0 ? 'brecha' : 'alcanzado');
        return [
            'actual' => $actual,
            'objetivo' => $objetivo,
            'brecha' => $brecha,
            'estado' => $estado,
            'estado_texto' => [
                'sin_evidencia' => 'Sin evidencia académica',
                'brecha' => 'Brecha de evidencia',
                'alcanzado' => 'Meta de evidencia alcanzada',
            ][$estado],
        ];
    }
}
