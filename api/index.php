<?php
// Punto de entrada único para Vercel.
//
// Vercel crea una función por cada archivo .php que se declare como entrypoint,
// y el plan Hobby admite 12 como máximo. Este archivo recibe todas las
// peticiones y carga el .php que corresponde a la URL, de modo que el proyecto
// completo viaja en una sola función.
//
// En XAMPP este archivo no se usa: Apache sirve cada .php directamente.

$raiz = realpath(__DIR__ . '/..');

$uri   = $_SERVER['REQUEST_URI'] ?? '/';
$ruta  = '/' . ltrim(rawurldecode((string) parse_url($uri, PHP_URL_PATH)), '/');
$query = (string) parse_url($uri, PHP_URL_QUERY);

// Todos los enlaces de las prácticas son relativos ("index.php?vista=…",
// "acciones/guardar.php"), así que el navegador los resuelve contra la carpeta
// actual de la URL. Sin la diagonal final, /pract4 tiene como carpeta la raíz
// del sitio y cada enlace apuntaría a /acciones/guardar.php. Apache evita eso
// redirigiendo a /pract4/ y aquí hay que hacer lo mismo.
if (substr($ruta, -1) !== '/' && is_dir($raiz . $ruta)) {
    header('Location: ' . rtrim($ruta, '/') . '/' . ($query !== '' ? '?' . $query : ''), true, 301);
    exit;
}

if (substr($ruta, -1) === '/') {
    $ruta .= 'index.php';
}

$destino = realpath($raiz . $ruta);

// El archivo debe existir, ser .php y quedar dentro del repositorio: realpath
// ya resolvió los "..", así que basta comprobar el prefijo.
$valido = $destino !== false
    && is_file($destino)
    && strpos($destino, $raiz . DIRECTORY_SEPARATOR) === 0
    && strtolower(pathinfo($destino, PATHINFO_EXTENSION)) === 'php'
    && $destino !== __FILE__;

// includes/ y vistas/ solo se cargan con require desde index.php; pedirlos
// por URL daria errores de variables no definidas.
if (preg_match("#/(includes|vistas)/#", $ruta)) {
    $valido = false;
}

if (!$valido) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo '404 · No se encontró ' . $ruta;
    exit;
}

// Las rutas relativas del script se resuelven desde su propia carpeta.
chdir(dirname($destino));
require $destino;
