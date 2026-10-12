<?php

require_once __DIR__ . '/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    if (DB_SSL) {
        // Los MySQL administrados (TiDB, Aiven, Clever Cloud…) solo aceptan TLS.
        $conexion = mysqli_init();
        $conexion->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);

        $ca = env('DB_SSL_CA');
        if ($ca && is_readable($ca)) {
            $conexion->ssl_set(null, null, $ca, null, null);
            $bandera = MYSQLI_CLIENT_SSL;
        } else {
            $bandera = MYSQLI_CLIENT_SSL | MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT;
        }

        $conexion->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT, null, $bandera);
    } else {
        $conexion = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    }

    $conexion->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    // No se muestran los datos de conexión para no filtrar credenciales.
    http_response_code(500);
    $ayuda = EN_VERCEL
        ? '<p style="font-family:sans-serif">Revisa las variables <code>DB_HOST</code>, <code>DB_PORT</code>, '
        . '<code>DB_USER</code>, <code>DB_PASS</code> y <code>DB_NAME</code> en Vercel &rarr; Settings &rarr; '
        . 'Environment Variables, y que la base acepte conexiones desde cualquier IP.</p>'
        : '<p style="font-family:sans-serif">Verifica que MySQL esté iniciado y que exista la base '
        . '<code>' . htmlspecialchars(DB_NAME) . '</code> (importa <code>database.sql</code>).</p>';
    exit('<h3 style="font-family:sans-serif">No fue posible conectar con la base de datos.</h3>' . $ayuda);
}
