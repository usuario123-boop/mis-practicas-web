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

// Los PDF se guardan dentro de MySQL, en la columna documentos.archivo_datos.
// Así la aplicación se comporta igual en XAMPP y en Vercel, donde el disco es
// de solo lectura y lo que se escriba en /tmp se pierde al terminar la petición.
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

define('APP_NOMBRE', 'UNACH · Gestión de Documentos');
define('POR_PAGINA', (int) env('POR_PAGINA', 5));

date_default_timezone_set('America/Mexico_City');

// Copia local de los PDF. En XAMPP, además de guardarlos en la base de datos,
// se escribe una copia dentro de prac4/pdf/ para tener los archivos a la mano
// en el disco. En Vercel no se hace: el sistema de archivos es de solo lectura.
define('RUTA_PDF', __DIR__ . '/pdf');
define('COPIA_LOCAL_PDF', !EN_VERCEL);
