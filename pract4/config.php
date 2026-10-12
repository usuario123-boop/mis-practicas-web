<?php
/**
 * config.php
 * Configuración central de la aplicación.
 *
 * Todos los valores sensibles se leen de variables de entorno para no exponer
 * credenciales en el repositorio. Si no existe la variable de entorno se usa el
 * valor por defecto, pensado para el entorno local de XAMPP.
 */

/**
 * Lee una variable de entorno con valor por defecto.
 * Soporta getenv(), $_ENV y $_SERVER (Vercel expone las variables en $_ENV).
 */
function env(string $clave, $porDefecto = null)
{
    $valor = getenv($clave);
    if ($valor === false && isset($_ENV[$clave]))    $valor = $_ENV[$clave];
    if ($valor === false && isset($_SERVER[$clave])) $valor = $_SERVER[$clave];
    return ($valor === false || $valor === '') ? $porDefecto : $valor;
}

// ---------------------------------------------------------------------------
// Base de datos MySQL
// ---------------------------------------------------------------------------
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', (int) env('DB_PORT', 3306));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_NAME', env('DB_NAME', 'gestion_documentos'));

// ---------------------------------------------------------------------------
// Almacenamiento de archivos PDF
// ---------------------------------------------------------------------------
define('DIR_PDF', __DIR__ . DIRECTORY_SEPARATOR . 'pdf');   // carpeta física
define('MAX_PDF_BYTES', 10 * 1024 * 1024);                  // 10 MB máximo

// ---------------------------------------------------------------------------
// Respaldo automático en GitHub (requisito: "que también se guarde en GitHub")
// Cada PDF registrado se sube al repositorio mediante la API de GitHub.
// Si no se define GITHUB_TOKEN la función se desactiva sin romper la aplicación.
// ---------------------------------------------------------------------------
define('GH_TOKEN',  env('GITHUB_TOKEN', ''));                       // token personal (scope: repo)
define('GH_REPO',   env('GITHUB_REPO', 'usuario123-boop/mis-practicas-web'));
define('GH_BRANCH', env('GITHUB_BRANCH', 'main'));
define('GH_RUTA',   env('GITHUB_PATH', 'pract4/pdf'));              // carpeta destino dentro del repo
define('GH_ACTIVO', GH_TOKEN !== '');

// ---------------------------------------------------------------------------
// Preferencias generales
// ---------------------------------------------------------------------------
define('APP_NOMBRE', 'UNACH · Gestión de Documentos');
define('POR_PAGINA', (int) env('POR_PAGINA', 5));   // registros por página en el listado

date_default_timezone_set('America/Mexico_City');
