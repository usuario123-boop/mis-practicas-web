<?php
// Configuración general. Los valores sensibles se leen de variables de entorno
// para no dejarlos escritos en el repositorio.

function env(string $clave, $porDefecto = null)
{
    $valor = getenv($clave);
    if ($valor === false && isset($_ENV[$clave]))    $valor = $_ENV[$clave];
    if ($valor === false && isset($_SERVER[$clave])) $valor = $_SERVER[$clave];
    return ($valor === false || $valor === '') ? $porDefecto : $valor;
}

// Base de datos
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', (int) env('DB_PORT', 3306));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_NAME', env('DB_NAME', 'gestion_documentos'));

// Archivos PDF
define('DIR_PDF', __DIR__ . DIRECTORY_SEPARATOR . 'pdf');
define('MAX_PDF_BYTES', 10 * 1024 * 1024);

// Respaldo de los PDF en GitHub. Sin GITHUB_TOKEN la función queda desactivada
// y la aplicación trabaja solo con el almacenamiento local.
define('GH_TOKEN',  env('GITHUB_TOKEN', ''));
define('GH_REPO',   env('GITHUB_REPO', 'usuario123-boop/mis-practicas-web'));
define('GH_BRANCH', env('GITHUB_BRANCH', 'main'));
define('GH_RUTA',   env('GITHUB_PATH', 'pract4/pdf'));
define('GH_ACTIVO', GH_TOKEN !== '');

define('APP_NOMBRE', 'UNACH · Gestión de Documentos');
define('POR_PAGINA', (int) env('POR_PAGINA', 5));

date_default_timezone_set('America/Mexico_City');
