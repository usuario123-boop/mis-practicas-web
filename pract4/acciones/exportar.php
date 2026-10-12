<?php
/**
 * acciones/exportar.php
 * Exporta el inventario de documentos a un archivo CSV compatible con Excel.
 * Respeta los mismos filtros que el listado, de modo que se puede exportar
 * únicamente el resultado de una consulta.
 */

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/consultas.php';

$filtros = [
    'buscar'  => trim($_GET['buscar'] ?? ''),
    'estatus' => $_GET['estatus'] ?? 'Todos',
    'desde'   => $_GET['desde'] ?? '',
    'hasta'   => $_GET['hasta'] ?? '',
];

$documentos = listarDocumentos($conexion, $filtros, 5000, 0, 'fecha_desc');

$nombreArchivo = 'documentos_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');

$salida = fopen('php://output', 'w');

// BOM UTF-8: hace que Excel muestre correctamente los acentos.
fwrite($salida, "\xEF\xBB\xBF");

fputcsv($salida, ['No. Oficio', 'Fecha', 'Asunto', 'Remitente', 'Destinatario', 'Estatus', 'Archivo', 'Registrado'], ';');

foreach ($documentos as $doc) {
    fputcsv($salida, [
        $doc['no_oficio'],
        fechaLocal($doc['fecha']),
        $doc['asunto'],
        $doc['remitente'],
        $doc['destinatario'],
        $doc['estatus'],
        $doc['archivo_nombre'],
        $doc['creado_en'],
    ], ';');
}

fclose($salida);
