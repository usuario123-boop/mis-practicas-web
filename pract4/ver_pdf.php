<?php
// Entrega el PDF de un documento al iframe de la ventana modal.
// El archivo se guarda dentro de la base de datos, así que no hay ninguna ruta
// pública que enlazar: todo pasa por aquí, que valida el id y fija las cabeceras.

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/consultas.php';

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

$archivo = obtenerArchivoPdf($conexion, $id);

if ($archivo === null) {
    errorVisor('Este documento no tiene ningún archivo PDF almacenado.');
}

// Nombre amigable para el navegador y para la descarga.
$nombreVisible = 'Oficio_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $doc['no_oficio']) . '.pdf';

// descargar.php entra por aquí con este parámetro.
$descargar = isset($_GET['descargar']) && $_GET['descargar'] === '1';
$disposicion = $descargar ? 'attachment' : 'inline';

header('Content-Type: application/pdf');
header('Content-Disposition: ' . $disposicion . '; filename="' . $nombreVisible . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=600');

header('Content-Length: ' . strlen($archivo['archivo_datos']));
echo $archivo['archivo_datos'];
