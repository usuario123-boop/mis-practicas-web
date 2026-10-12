<?php
// Convierte la fotografía tomada con la cámara en un PDF de una página.
// El formato PDF admite datos JPEG tal cual mediante el filtro DCTDecode, así que
// la imagen se incrusta sin recomprimir y sin necesitar GD ni librerías externas.

const PDF_ANCHO_PAGINA = 612.0;   // tamaño carta en puntos (1 pt = 1/72")
const PDF_ALTO_PAGINA  = 792.0;
const PDF_MARGEN       = 28.0;

function imagenJpegAPdf(string $rutaJpeg, string $rutaPdf): bool
{
    $info = @getimagesize($rutaJpeg);
    if ($info === false || $info[2] !== IMAGETYPE_JPEG) {
        return false;
    }

    [$anchoPx, $altoPx] = $info;
    $binario = file_get_contents($rutaJpeg);
    if ($binario === false || $anchoPx < 1 || $altoPx < 1) {
        return false;
    }

    $canales = $info['channels'] ?? 3;
    $espacio = $canales === 1 ? '/DeviceGray' : ($canales === 4 ? '/DeviceCMYK' : '/DeviceRGB');

    // Escalar para que quepa en la página sin deformar la imagen.
    $maxAncho = PDF_ANCHO_PAGINA - 2 * PDF_MARGEN;
    $maxAlto  = PDF_ALTO_PAGINA  - 2 * PDF_MARGEN;
    $escala   = min($maxAncho / $anchoPx, $maxAlto / $altoPx);

    $ancho = $anchoPx * $escala;
    $alto  = $altoPx  * $escala;
    $x     = (PDF_ANCHO_PAGINA - $ancho) / 2;
    $y     = (PDF_ALTO_PAGINA  - $alto)  / 2;

    $contenido = sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /Im0 Do Q", $ancho, $alto, $x, $y);

    $objetos = [];
    $objetos[1] = "<< /Type /Catalog /Pages 2 0 R >>";
    $objetos[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
    $objetos[3] = sprintf(
        "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] "
        . "/Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>",
        PDF_ANCHO_PAGINA, PDF_ALTO_PAGINA
    );
    $objetos[4] = "<< /Type /XObject /Subtype /Image /Width $anchoPx /Height $altoPx "
                . "/ColorSpace $espacio /BitsPerComponent 8 /Filter /DCTDecode "
                . "/Length " . strlen($binario) . " >>\nstream\n" . $binario . "\nendstream";
    $objetos[5] = "<< /Length " . strlen($contenido) . " >>\nstream\n" . $contenido . "\nendstream";

    $salida     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $posiciones = [];

    foreach ($objetos as $numero => $cuerpo) {
        $posiciones[$numero] = strlen($salida);
        $salida .= "$numero 0 obj\n$cuerpo\nendobj\n";
    }

    // Tabla de referencias cruzadas: guarda el byte donde empieza cada objeto.
    $inicioXref = strlen($salida);
    $total      = count($objetos) + 1;

    $salida .= "xref\n0 $total\n0000000000 65535 f \n";
    for ($i = 1; $i <= count($objetos); $i++) {
        $salida .= sprintf("%010d 00000 n \n", $posiciones[$i]);
    }
    $salida .= "trailer\n<< /Size $total /Root 1 0 R >>\nstartxref\n$inicioXref\n%%EOF";

    return file_put_contents($rutaPdf, $salida) !== false;
}
