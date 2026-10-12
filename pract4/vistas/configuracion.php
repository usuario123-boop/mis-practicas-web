<?php
// Diagnóstico del entorno: base de datos, almacenamiento y respaldo en GitHub.

require_once __DIR__ . '/../includes/github.php';

$tablas = [];
$res = $conexion->query('SHOW TABLES');
while ($fila = $res->fetch_array(MYSQLI_NUM)) $tablas[] = $fila[0];

$conteos = [];
foreach ($tablas as $t) {
    $conteos[$t] = (int) $conexion->query("SELECT COUNT(*) AS c FROM `$t`")->fetch_assoc()['c'];
}

$carpetaExiste    = is_dir(DIR_PDF);
$carpetaEscribible = $carpetaExiste && is_writable(DIR_PDF);
$archivos = $carpetaExiste ? array_filter(scandir(DIR_PDF), fn($f) => str_ends_with(strtolower($f), '.pdf')) : [];
$pesoTotal = 0;
foreach ($archivos as $a) $pesoTotal += filesize(DIR_PDF . DIRECTORY_SEPARATOR . $a);

$estadoGh = githubEstado();

$datosServidor = [
    'Versión de PHP'            => PHP_VERSION,
    'Servidor web'              => $_SERVER['SERVER_SOFTWARE'] ?? 'desconocido',
    'Versión de MySQL'          => $conexion->server_info,
    'Subida máxima permitida'   => ini_get('upload_max_filesize'),
    'Tamaño máximo del POST'    => ini_get('post_max_size'),
    'Límite propio de la app'   => tamanoLegible(MAX_PDF_BYTES),
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
                    $carpetaEscribible,
                    $carpetaEscribible
                        ? count($archivos) . ' archivo(s) guardados · ' . tamanoLegible($pesoTotal) . ' en total.'
                        : 'La carpeta pdf/ no existe o no tiene permisos de escritura.'
                );

                filaEstado(
                    'Respaldo versionado en GitHub',
                    $estadoGh['ok'],
                    $estadoGh['detalle']
                );
                ?>
            </ul>

            <?php if (!$estadoGh['ok']): ?>
                <div class="alert alert-light border small mt-3 mb-0">
                    <strong><i class="bi bi-info-circle me-1"></i>Cómo activar el respaldo en GitHub</strong>
                    <ol class="mb-0 mt-2 ps-3">
                        <li>Genera un token personal en GitHub con permiso de escritura sobre el repositorio.</li>
                        <li>Define las variables de entorno <code>GITHUB_TOKEN</code>, <code>GITHUB_REPO</code>,
                            <code>GITHUB_BRANCH</code> y <code>GITHUB_PATH</code>.</li>
                        <li>En local puedes agregarlas al archivo <code>php.ini</code> o al
                            <code>httpd.conf</code> de XAMPP con <code>SetEnv</code>;
                            en Vercel se capturan en <em>Settings &rarr; Environment Variables</em>.</li>
                    </ol>
                </div>
            <?php endif; ?>
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
