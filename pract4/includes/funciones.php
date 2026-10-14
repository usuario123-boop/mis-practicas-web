<?php
// Funciones de apoyo usadas por las vistas y las acciones.

require_once __DIR__ . '/sesion.php';

iniciarSesion($GLOBALS['conexion']);

// Escapa texto antes de imprimirlo en HTML.
function e($texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function obtenerFlash(): ?array
{
    if (empty($_SESSION['flash'])) return null;
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function redirigir(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function claseEstatus(string $estatus): string
{
    switch ($estatus) {
        case 'En proceso': return 'status-proceso';
        case 'Atendido':   return 'status-atendido';
        default:           return 'status-recibido';
    }
}

function fechaLocal(?string $fecha): string
{
    if (empty($fecha)) return '—';
    $ts = strtotime($fecha);
    return $ts ? date('d/m/Y', $ts) : '—';
}

function tamanoLegible(int $bytes): string
{
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)    return number_format($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

// Arma una URL conservando los parámetros actuales. Un valor null elimina el parámetro.
function urlCon(array $parametros): string
{
    $actuales = $_GET;
    foreach ($parametros as $clave => $valor) {
        if ($valor === null) unset($actuales[$clave]);
        else                 $actuales[$clave] = $valor;
    }
    return 'index.php' . (empty($actuales) ? '' : '?' . http_build_query($actuales));
}

function tokenCsrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function campoCsrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(tokenCsrf()) . '">';
}

function validarCsrf(): void
{
    $recibido = $_POST['csrf'] ?? $_GET['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $recibido)) {
        http_response_code(403);
        exit('Solicitud no válida (token CSRF incorrecto).');
    }
}

// Debe coincidir con el ENUM de la tabla documentos.
function estatusValidos(): array
{
    return ['Recibido', 'En proceso', 'Atendido'];
}

// --- Copia local de los PDF (solo en XAMPP) ---------------------------------
// La base de datos sigue siendo el origen de los archivos: esta copia existe
// para que la carpeta pdf/ contenga realmente los documentos registrados y se
// puedan abrir desde el explorador de Windows.

function guardarCopiaPdf(string $nombre, string $contenido): bool
{
    if (!COPIA_LOCAL_PDF || $nombre === '') return false;
    if (!is_dir(RUTA_PDF) && !@mkdir(RUTA_PDF, 0777, true)) return false;

    return @file_put_contents(RUTA_PDF . '/' . basename($nombre), $contenido) !== false;
}

function borrarCopiaPdf(?string $nombre): void
{
    if (!COPIA_LOCAL_PDF || empty($nombre)) return;

    $ruta = RUTA_PDF . '/' . basename($nombre);
    if (is_file($ruta)) @unlink($ruta);
}

// Añade la fecha del archivo a la URL de un asset, para que el navegador
// descargue la versión nueva en lugar de la que tenga en caché. Sin esto, un
// teléfono que ya visitó el sitio puede seguir ejecutando el app.js anterior.
function versionAsset(string $relativo): string
{
    $fecha = @filemtime(__DIR__ . '/../' . $relativo);
    return $relativo . '?v=' . ($fecha ?: '1');
}
