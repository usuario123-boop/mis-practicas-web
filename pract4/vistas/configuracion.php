<?php
// Diagnóstico del entorno: base de datos, almacenamiento y extensiones de PHP.

$tablas = [];
$res = $conexion->query('SHOW TABLES');
while ($fila = $res->fetch_array(MYSQLI_NUM)) $tablas[] = $fila[0];

$conteos = [];
foreach ($tablas as $t) {
    $conteos[$t] = (int) $conexion->query("SELECT COUNT(*) AS c FROM `$t`")->fetch_assoc()['c'];
}

// Los PDF viven en la propia tabla, así que el inventario sale de una consulta
// en lugar de leer una carpeta.
$resumen = $conexion->query(
    'SELECT COUNT(*) AS n, COALESCE(SUM(archivo_tam), 0) AS peso
       FROM documentos WHERE archivo_datos IS NOT NULL')->fetch_assoc();

$almacenOk = true;
$almacenDetalle = (int) $resumen['n'] . ' archivo(s) guardados · '
    . tamanoLegible((int) $resumen['peso']) . ' en total, dentro de la base de datos.';

// Segundo semáforo, solo en XAMPP: la carpeta pdf/ donde queda la copia en disco.
$copiaOk = $copiaDetalle = null;
if (COPIA_LOCAL_PDF) {
    $archivosLocales = is_dir(RUTA_PDF) ? glob(RUTA_PDF . '/*.pdf') : [];
    $pesoLocal       = array_sum(array_map('filesize', $archivosLocales));
    $copiaOk         = is_dir(RUTA_PDF) && is_writable(RUTA_PDF);
    $copiaDetalle    = $copiaOk
        ? count($archivosLocales) . ' archivo(s) · ' . tamanoLegible((int) $pesoLocal)
          . ' en la carpeta pdf/ del proyecto.'
        : 'La carpeta ' . RUTA_PDF . ' no existe o no tiene permiso de escritura.';
}

$faltantes = array_values(array_filter(
    ['mysqli', 'fileinfo', 'openssl'],
    fn($ext) => !extension_loaded($ext)
));

$datosServidor = [
    'Entorno'                   => EN_VERCEL ? 'Vercel (' . env('VERCEL_ENV', 'production') . ')' : 'Servidor local',
    'Versión de PHP'            => PHP_VERSION,
    'Servidor web'              => $_SERVER['SERVER_SOFTWARE'] ?? 'desconocido',
    'Versión de MySQL'          => $conexion->server_info,
    'Subida máxima permitida'   => ini_get('upload_max_filesize'),
    'Tamaño máximo del POST'    => ini_get('post_max_size'),
    'Límite propio de la app'   => tamanoLegible(MAX_PDF_BYTES),
    'Conexión cifrada a MySQL'  => DB_SSL ? 'Sí (TLS)' : 'No',
    'Zona horaria'              => date_default_timezone_get(),
];

/** Dibuja una fila de diagnóstico con su semáforo. */
function filaEstado(string $titulo, bool $ok, string $detalle): void
{
    $icono = $ok ? 'check-circle-fill text-success' : 'x-circle-fill text-danger';
    echo '<li class="d-flex gap-3 py-3 border-bottom">'
       . '<i class="bi bi-' . $icono . ' fs-5"></i>'
       . '<div class="small"><div class="fw-semibold">' . e($titulo) . '</div>'
       . '<div class="text-muted">' . e($detalle) . '</div></div></li>';
}
?>

<h5 class="fw-bold mb-1"><i class="bi bi-gear text-primary me-2"></i>Configuración y diagnóstico</h5>
<p class="text-muted small mb-4">Verifica que los tres componentes del sistema estén funcionando.</p>

<div class="row g-3">

    <div class="col-12 col-xl-7">
        <div class="main-card h-100">
            <h6 class="fw-bold mb-3">Estado de los componentes</h6>
            <ul class="list-unstyled mb-0">
                <?php
                filaEstado(
                    'Base de datos MySQL',
                    true,
                    'Conectado a "' . DB_NAME . '" en ' . DB_HOST . '. Tablas: ' . implode(', ', $tablas) . '.'
                );

                filaEstado(
                    'Almacenamiento de archivos PDF',
                    $almacenOk,
                    $almacenDetalle
                );

                if (COPIA_LOCAL_PDF) {
                    filaEstado('Copia local en la carpeta pdf/', $copiaOk, $copiaDetalle);
                }

                filaEstado(
                    'Extensiones de PHP',
                    empty($faltantes),
                    empty($faltantes)
                        ? 'mysqli, fileinfo y openssl están disponibles.'
                        : 'Faltan: ' . implode(', ', $faltantes) . '.'
                );
                ?>
            </ul>

            <div class="alert alert-light border small mt-3 mb-0">
                <strong><i class="bi bi-info-circle me-1"></i>Dónde se guardan los PDF</strong>
                <p class="mb-0 mt-2">
                    Cada documento se almacena en la columna <code>archivo_datos</code> de la
                    tabla <code>documentos</code>, junto con sus datos. Así la aplicación se
                    comporta igual en XAMPP y en Vercel, donde el disco es de solo lectura y
                    cualquier archivo escrito se perdería al terminar la petición. Al borrar
                    un documento su PDF desaparece con él, sin dejar archivos huérfanos.
                </p>
                <p class="mb-0 mt-2">
                    En XAMPP, además, cada PDF se escribe como copia en la carpeta
                    <code>pdf/</code> del proyecto, para poder abrirlo directamente desde el
                    explorador de archivos. El botón <em>Descargar</em> del visor no usa esa
                    carpeta: la descarga la hace el navegador y el archivo va a la carpeta de
                    descargas del equipo o del teléfono.
                </p>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="main-card h-100">
            <h6 class="fw-bold mb-3">Entorno de ejecución</h6>
            <table class="table table-sm small mb-0">
                <tbody>
                    <?php foreach ($datosServidor as $clave => $valor): ?>
                        <tr>
                            <td class="text-muted"><?= e($clave) ?></td>
                            <td class="text-end fw-semibold"><?= e($valor) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="col-12">
        <div class="main-card">
            <h6 class="fw-bold mb-3">Contenido de la base de datos</h6>
            <div class="row g-3">
                <?php foreach ($conteos as $tabla => $cantidad): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="border rounded-3 p-3 text-center">
                            <div class="fs-4 fw-bold text-primary"><?= $cantidad ?></div>
                            <div class="small text-muted"><?= e($tabla) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="small text-muted mt-3 mb-0">
                <i class="bi bi-info-circle me-1"></i>
                El script <code>database.sql</code> incluido en el proyecto recrea estas tablas con datos de prueba.
            </p>
        </div>
    </div>

</div>
