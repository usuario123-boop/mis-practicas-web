<?php
// Consultas a la base de datos. Los filtros llegan de la URL, así que todas van
// con sentencias preparadas.

// El PDF vive en la columna archivo_datos, que puede pesar megabytes. Ninguna
// consulta de listado debe tocarla, así que se enumeran las demás columnas en
// lugar de usar SELECT *.
const COLUMNAS_DOCUMENTO = 'id, no_oficio, fecha, asunto, remitente, destinatario,
                            estatus, archivo_pdf, archivo_nombre, archivo_tam,
                            creado_en, actualizado_en';

// Devuelve la cláusula WHERE junto con los tipos y valores para bind_param.
function filtrosDocumentos(array $f): array
{
    $condiciones = ['1=1'];
    $tipos   = '';
    $valores = [];

    if (!empty($f['buscar'])) {
        $condiciones[] = '(no_oficio LIKE ? OR asunto LIKE ? OR remitente LIKE ? OR destinatario LIKE ?)';
        $like = '%' . $f['buscar'] . '%';
        $tipos .= 'ssss';
        array_push($valores, $like, $like, $like, $like);
    }
    if (!empty($f['estatus']) && $f['estatus'] !== 'Todos' && in_array($f['estatus'], estatusValidos(), true)) {
        $condiciones[] = 'estatus = ?';
        $tipos .= 's';
        $valores[] = $f['estatus'];
    }
    if (!empty($f['desde'])) {
        $condiciones[] = 'fecha >= ?';
        $tipos .= 's';
        $valores[] = $f['desde'];
    }
    if (!empty($f['hasta'])) {
        $condiciones[] = 'fecha <= ?';
        $tipos .= 's';
        $valores[] = $f['hasta'];
    }
    if (!empty($f['remitente'])) {
        $condiciones[] = 'remitente = ?';
        $tipos .= 's';
        $valores[] = $f['remitente'];
    }

    return [
        'sql'     => 'WHERE ' . implode(' AND ', $condiciones),
        'tipos'   => $tipos,
        'valores' => $valores,
    ];
}

function contarDocumentos(mysqli $conexion, array $filtros): int
{
    $w    = filtrosDocumentos($filtros);
    $stmt = $conexion->prepare("SELECT COUNT(*) AS total FROM documentos {$w['sql']}");
    if ($w['tipos'] !== '') $stmt->bind_param($w['tipos'], ...$w['valores']);
    $stmt->execute();
    $total = (int) $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    return $total;
}

function listarDocumentos(mysqli $conexion, array $filtros, int $limite, int $desplazamiento, string $orden = 'fecha_desc'): array
{
    // El ORDER BY no se puede parametrizar, así que se elige de una lista fija.
    $ordenamientos = [
        'fecha_desc'  => 'fecha DESC, id DESC',
        'fecha_asc'   => 'fecha ASC, id ASC',
        'oficio_asc'  => 'no_oficio ASC',
        'oficio_desc' => 'no_oficio DESC',
        'estatus'     => 'estatus ASC, fecha DESC',
    ];
    $orderBy = $ordenamientos[$orden] ?? $ordenamientos['fecha_desc'];

    $w     = filtrosDocumentos($filtros);
    $sql   = "SELECT " . COLUMNAS_DOCUMENTO . " FROM documentos {$w['sql']}
              ORDER BY {$orderBy} LIMIT ? OFFSET ?";
    $tipos = $w['tipos'] . 'ii';
    $vals  = array_merge($w['valores'], [$limite, $desplazamiento]);

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param($tipos, ...$vals);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $filas;
}

function obtenerDocumento(mysqli $conexion, int $id): ?array
{
    $stmt = $conexion->prepare('SELECT ' . COLUMNAS_DOCUMENTO . ' FROM documentos WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $fila ?: null;
}

// El PDF se pide aparte y solo cuando hay que entregarlo, para que los listados
// no arrastren megabytes por cada renglón.
function obtenerArchivoPdf(mysqli $conexion, int $id): ?array
{
    $stmt = $conexion->prepare(
        'SELECT archivo_nombre, archivo_tam, archivo_datos FROM documentos WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return ($fila && $fila['archivo_datos'] !== null) ? $fila : null;
}

function resumenEstatus(mysqli $conexion): array
{
    $resumen = ['Recibido' => 0, 'En proceso' => 0, 'Atendido' => 0, 'Total' => 0];

    $res = $conexion->query('SELECT estatus, COUNT(*) AS total FROM documentos GROUP BY estatus');
    while ($fila = $res->fetch_assoc()) {
        $resumen[$fila['estatus']] = (int) $fila['total'];
        $resumen['Total']         += (int) $fila['total'];
    }
    return $resumen;
}

function documentosPorMes(mysqli $conexion, int $meses = 6): array
{
    $sql = "SELECT DATE_FORMAT(fecha, '%Y-%m') AS periodo, COUNT(*) AS total
            FROM documentos
            WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
            GROUP BY periodo
            ORDER BY periodo ASC";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param('i', $meses);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $filas;
}

function remitentesFrecuentes(mysqli $conexion, int $limite = 5): array
{
    $stmt = $conexion->prepare(
        'SELECT remitente, COUNT(*) AS total FROM documentos
         GROUP BY remitente ORDER BY total DESC, remitente ASC LIMIT ?'
    );
    $stmt->bind_param('i', $limite);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $filas;
}

// Alimenta los <select> y <datalist> de los formularios.
function valoresDistintos(mysqli $conexion, string $columna): array
{
    if (!in_array($columna, ['remitente', 'destinatario'], true)) return [];

    $res    = $conexion->query("SELECT DISTINCT $columna AS v FROM documentos ORDER BY v ASC");
    $salida = [];
    while ($fila = $res->fetch_assoc()) $salida[] = $fila['v'];
    return $salida;
}

function registrarBitacora(mysqli $conexion, int $documentoId, string $accion, string $detalle = ''): void
{
    $stmt = $conexion->prepare('INSERT INTO bitacora (documento_id, accion, detalle) VALUES (?, ?, ?)');
    $stmt->bind_param('iss', $documentoId, $accion, $detalle);
    $stmt->execute();
    $stmt->close();
}

function ultimosMovimientos(mysqli $conexion, int $limite = 8): array
{
    $stmt = $conexion->prepare(
        'SELECT b.*, d.no_oficio FROM bitacora b
         INNER JOIN documentos d ON d.id = b.documento_id
         ORDER BY b.registrado_en DESC, b.id DESC LIMIT ?'
    );
    $stmt->bind_param('i', $limite);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $filas;
}
