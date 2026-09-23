<?php

function certPdfText(string $texto): string
{
    $texto = str_replace(["\\","(",")"],["\\\\","\\(","\\)"],$texto);
    if (function_exists('iconv')) {
        $convertido = @iconv('UTF-8','Windows-1252//TRANSLIT',$texto);
        if ($convertido !== false) return $convertido;
    }
    return function_exists('utf8_decode') ? utf8_decode($texto) : $texto;
}

function certPdfCenter(string $texto, float $size, float $y, bool $bold=false): string
{
    $w = 842.0;
    $ascii = function_exists('iconv') ? @iconv('UTF-8','ASCII//TRANSLIT',$texto) : $texto;
    if ($ascii === false) $ascii = $texto;
    $aprox = strlen($ascii) * $size * 0.48;
    $x = max(45, ($w-$aprox)/2);
    return "BT /" . ($bold?'F2':'F1') . " {$size} Tf {$x} {$y} Td (" . certPdfText($texto) . ") Tj ET\n";
}

function generarCertificadoPdf(array $c, string $urlValidacion): string
{
    $content = "q 0.035 0.075 0.095 rg 0 0 842 595 re f Q\n";
    $content .= "q 0.08 0.78 0.86 RG 4 w 26 26 790 543 re S Q\n";
    $content .= "q 0.08 0.78 0.86 RG 1.2 w 40 40 762 515 re S Q\n";
    $content .= "0.08 0.78 0.86 rg\n";
    $content .= certPdfCenter('DEVIOZ - TECHFLIX LEARNING LAB',16,520,true);
    $content .= "0.92 0.96 0.98 rg\n";
    $content .= certPdfCenter('CERTIFICADO DE FINALIZACIÓN',28,465,true);
    $content .= certPdfCenter('Se certifica que',14,418,false);
    $content .= certPdfCenter((string)$c['nombre_participante'],27,370,true);
    $content .= certPdfCenter('completó satisfactoriamente la formación',13,330,false);
    $content .= certPdfCenter((string)$c['curso_titulo'],23,285,true);
    $content .= certPdfCenter((string)$c['capacitacion_nombre'],13,252,false);

    $nota = number_format((float)$c['nota_final'],1,'.','') . '%';
    $duracion = (int)$c['duracion_minutos'];
    $duracionTxt = $duracion > 0 ? ($duracion . ' minutos') : 'No especificada';
    $fecha = date('d/m/Y', strtotime((string)$c['fecha_finalizacion']));
    $content .= "BT /F1 12 Tf 150 194 Td (".certPdfText('Fecha de finalización: '.$fecha).") Tj ET\n";
    $content .= "BT /F1 12 Tf 345 194 Td (".certPdfText('Nota final: '.$nota).") Tj ET\n";
    $content .= "BT /F1 12 Tf 520 194 Td (".certPdfText('Duracion: '.$duracionTxt).") Tj ET\n";
    $content .= "0.08 0.78 0.86 rg\n";
    $content .= certPdfCenter('Código de verificación: '.(string)$c['codigo'],11,137,true);
    $content .= "0.72 0.78 0.82 rg\n";
    $content .= certPdfCenter($urlValidacion,9,111,false);
    $content .= certPdfCenter('Certificado emitido digitalmente por DEVIOZ.',9,74,false);

    $objects=[];
    $objects[1]="<< /Type /Catalog /Pages 2 0 R >>";
    $objects[2]="<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
    $objects[3]="<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>";
    $objects[4]="<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
    $objects[5]="<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";
    $objects[6]="<< /Length ".strlen($content)." >>\nstream\n".$content."endstream";

    $pdf="%PDF-1.4\n";
    $offsets=[0];
    foreach($objects as $n=>$obj){
        $offsets[$n]=strlen($pdf);
        $pdf.=$n." 0 obj\n".$obj."\nendobj\n";
    }
    $xref=strlen($pdf);
    $pdf.="xref\n0 7\n0000000000 65535 f \n";
    for($i=1;$i<=6;$i++) $pdf.=sprintf("%010d 00000 n \n",$offsets[$i]);
    $pdf.="trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    return $pdf;
}
