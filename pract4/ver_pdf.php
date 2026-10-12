<?php
// Entrega el PDF de un documento al iframe de la ventana modal.
// La carpeta pdf/ nunca se enlaza directamente: todo pasa por aquí, que valida
// el id y controla las cabeceras. Si el archivo no está en el servidor se
// recupera del respaldo de GitHub.

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/consultas.php';
require_once __DIR__ . '/includes/github.php';

// Mensaje de error legible dentro del visor.
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

// descargar.php entra por aquí con este parámetro.
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

// No está en disco: se trae del respaldo.
$binario = githubDescargarArchivo($nombre);
if ($binario !== null) {
    header('Content-Length: ' . strlen($binario));
    echo $binario;
    exit;
}

// Se quitan las cabeceras de PDF antes de mostrar el aviso en HTML.
header_remove('Content-Type');
header_remove('Content-Disposition');
errorVisor('El archivo PDF no se encuentra en el servidor ni en el respaldo de GitHub.');
