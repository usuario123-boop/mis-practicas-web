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

// En Vercel el disco es de solo lectura y cada petición puede atenderse en una
// instancia distinta: eso cambia dónde se guardan los PDF y dónde las sesiones.
define('EN_VERCEL', env('VERCEL') !== null || env('VERCEL_ENV') !== null);

// Base de datos
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', (int) env('DB_PORT', 3306));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_NAME', env('DB_NAME', 'gestion_documentos'));
// Los proveedores de MySQL en la nube exigen TLS; en XAMPP se deja apagado.
define('DB_SSL',  filter_var(env('DB_SSL', EN_VERCEL ? '1' : '0'), FILTER_VALIDATE_BOOL));

// Archivos PDF. En Vercel solo /tmp acepta escritura y su contenido se pierde
// al terminar la petición, por eso ahí GitHub deja de ser respaldo y pasa a ser
// el almacenamiento real.
define('DIR_PDF', env('DIR_PDF', EN_VERCEL
    ? sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pdf'
    : __DIR__ . DIRECTORY_SEPARATOR . 'pdf'));
define('ALMACEN_EFIMERO', EN_VERCEL);

// Convierte valores de php.ini como "8M" o "512K" a bytes.
function bytesIni(string $directiva): int
{
    $valor = trim((string) ini_get($directiva));
    if ($valor === '' || $valor === '-1') return PHP_INT_MAX;

    $numero = (int) $valor;
    switch (strtolower(substr($valor, -1))) {
        case 'g': return $numero * 1024 * 1024 * 1024;
        case 'm': return $numero * 1024 * 1024;
        case 'k': return $numero * 1024;
        default:  return $numero;
    }
}

// El tope efectivo es el menor entre el de la app, el de PHP y el de la
// plataforma: Vercel rechaza cuerpos mayores a 4.5 MB antes de que PHP los vea.
define('MAX_PDF_BYTES', min(
    (int) env('MAX_PDF_BYTES', EN_VERCEL ? 4 * 1024 * 1024 : 10 * 1024 * 1024),
    bytesIni('upload_max_filesize'),
    bytesIni('post_max_size')
));

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
