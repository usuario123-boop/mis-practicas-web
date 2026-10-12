<?php
/**
 * ver_pdf.php
 * Entrega el archivo PDF de un documento para mostrarlo **dentro** de la
 * aplicación (el <iframe> de la ventana modal del listado).
 *
 * Nunca se enlaza la carpeta pdf/ de forma directa: así el archivo solo se
 * sirve a través de PHP, que valida el identificador y controla las cabeceras.
 *
 * Si el archivo no está en el servidor (por ejemplo, en un despliegue con
 * almacenamiento efímero) se recupera del respaldo de GitHub.
 */

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/consultas.php';
require_once __DIR__ . '/includes/github.php';

/** Muestra un mensaje de error legible dentro del visor. */
function errorVisor(string $mensaje, int $codigo = 404): void
{
    http_response_code($codigo);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="es"><meta charset="utf-8">'
       . '<body style="font-family:system-ui;background:#525659;color:#fff;display:grid;'
       . 'place-items:center;height:100vh;margin:0;text-align:center;padding:24px">'
       . '<div><h3 style="margin:0 0 8px">Documento no disponible</h3>'
       . '<p style="opacity:.8;margin:0">' . htmlspecialchars($mensaje) . '</p></div></body></html>';
    exit;
}

$id  = (int) ($_GET['id'] ?? 0);
$doc = obtenerDocumento($conexion, $id);

if (!$doc) {
    errorVisor('El documento solicitado no existe en la base de datos.');
}

$nombre = $doc['archivo_nombre'] ?: basename($doc['archivo_pdf']);
$ruta   = DIR_PDF . DIRECTORY_SEPARATOR . $nombre;

// Nombre amigable para el navegador y para la descarga.
$nombreVisible = 'Oficio_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $doc['no_oficio']) . '.pdf';

// ¿La petición viene de descargar.php? Entonces se fuerza la descarga.
$descargar = isset($_GET['descargar']) && $_GET['descargar'] === '1';
$disposicion = $descargar ? 'attachment' : 'inline';

header('Content-Type: application/pdf');
header('Content-Disposition: ' . $disposicion . '; filename="' . $nombreVisible . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=600');

if (is_file($ruta)) {
    header('Content-Length: ' . filesize($ruta));
    readfile($ruta);
    exit;
}

// Respaldo: se recupera el archivo desde el repositorio de GitHub.
$binario = githubDescargarArchivo($nombre);
if ($binario !== null) {
    header('Content-Length: ' . strlen($binario));
    echo $binario;
    exit;
}

// Se limpian las cabeceras de PDF antes de mostrar el aviso en HTML.
header_remove('Content-Type');
header_remove('Content-Disposition');
errorVisor('El archivo PDF no se encuentra en el servidor ni en el respaldo de GitHub.');
