<?php
/**
 * includes/consultas.php
 * Consultas a la base de datos. Todas usan sentencias preparadas para evitar
 * inyección SQL (los filtros llegan directamente de la URL).
 */

/**
 * Arma la cláusula WHERE y los parámetros a partir de los filtros recibidos.
 *
 * @param array $f  ['buscar','estatus','desde','hasta','remitente']
 * @return array{sql:string, tipos:string, valores:array}
 */
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

/** Cuenta cuántos documentos cumplen los filtros (para la paginación). */
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

/** Devuelve una página de documentos aplicando filtros y ordenamiento. */
function listarDocumentos(mysqli $conexion, array $filtros, int $limite, int $desplazamiento, string $orden = 'fecha_desc'): array
{
    // Lista blanca de ordenamientos: nunca se concatena texto del usuario.
    $ordenamientos = [
        'fecha_desc'  => 'fecha DESC, id DESC',
        'fecha_asc'   => 'fecha ASC, id ASC',
        'oficio_asc'  => 'no_oficio ASC',
        'oficio_desc' => 'no_oficio DESC',
        'estatus'     => 'estatus ASC, fecha DESC',
    ];
    $orderBy = $ordenamientos[$orden] ?? $ordenamientos['fecha_desc'];

    $w     = filtrosDocumentos($filtros);
    $sql   = "SELECT * FROM documentos {$w['sql']} ORDER BY {$orderBy} LIMIT ? OFFSET ?";
    $tipos = $w['tipos'] . 'ii';
    $vals  = array_merge($w['valores'], [$limite, $desplazamiento]);

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param($tipos, ...$vals);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $filas;
}

/** Recupera un documento por su identificador. */
function obtenerDocumento(mysqli $conexion, int $id): ?array
{
    $stmt = $conexion->prepare('SELECT * FROM documentos WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $fila ?: null;
}

/** Totales por estatus más el total general. Alimenta el panel y los reportes. */
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

/** Documentos registrados por mes durante los últimos N meses. */
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

/** Remitentes que más documentos han enviado. */
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

/** Valores distintos de una columna, para llenar los <select> de filtros. */
function valoresDistintos(mysqli $conexion, string $columna): array
{
    // Solo se aceptan columnas conocidas: la lista blanca evita inyección SQL.
    if (!in_array($columna, ['remitente', 'destinatario'], true)) return [];

    $res    = $conexion->query("SELECT DISTINCT $columna AS v FROM documentos ORDER BY v ASC");
    $salida = [];
    while ($fila = $res->fetch_assoc()) $salida[] = $fila['v'];
    return $salida;
}

/** Registra un movimiento en la bitácora de un documento. */
function registrarBitacora(mysqli $conexion, int $documentoId, string $accion, string $detalle = ''): void
{
    $stmt = $conexion->prepare('INSERT INTO bitacora (documento_id, accion, detalle) VALUES (?, ?, ?)');
    $stmt->bind_param('iss', $documentoId, $accion, $detalle);
    $stmt->execute();
    $stmt->close();
}

/** Últimos movimientos registrados en todo el sistema. */
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
