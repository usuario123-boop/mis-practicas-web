<?php

require_once __DIR__ . '/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conexion = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    $conexion->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    // No se muestran los datos de conexión para no filtrar credenciales.
    http_response_code(500);
    exit('<h3 style="font-family:sans-serif">No fue posible conectar con la base de datos.</h3>'
       . '<p style="font-family:sans-serif">Verifica que MySQL esté iniciado y que exista la base '
       . '<code>' . htmlspecialchars(DB_NAME) . '</code> (importa <code>database.sql</code>).</p>');
}
