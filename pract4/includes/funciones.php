<?php
/**
 * includes/funciones.php
 * Funciones de apoyo reutilizadas por vistas y acciones.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Escapa texto para imprimirlo con seguridad en HTML. */
function e($texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

/** Guarda un mensaje temporal que se mostrará en la siguiente página. */
function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

/** Devuelve y borra el mensaje temporal pendiente. */
function obtenerFlash(): ?array
{
    if (empty($_SESSION['flash'])) return null;
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/** Redirige a una URL interna y detiene la ejecución. */
function redirigir(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Clase CSS del badge según el estatus del documento. */
function claseEstatus(string $estatus): string
{
    switch ($estatus) {
        case 'En proceso': return 'status-proceso';
        case 'Atendido':   return 'status-atendido';
        default:           return 'status-recibido';
    }
}

/** Formatea una fecha de MySQL (Y-m-d) al formato local d/m/Y. */
function fechaLocal(?string $fecha): string
{
    if (empty($fecha)) return '—';
    $ts = strtotime($fecha);
    return $ts ? date('d/m/Y', $ts) : '—';
}

/** Convierte bytes a un texto legible (KB / MB). */
function tamanoLegible(int $bytes): string
{
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)    return number_format($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

/**
 * Construye una URL conservando los parámetros GET actuales
 * y sobrescribiendo/eliminando los que se indiquen.
 */
function urlCon(array $parametros): string
{
    $actuales = $_GET;
    foreach ($parametros as $clave => $valor) {
        if ($valor === null) unset($actuales[$clave]);
        else                 $actuales[$clave] = $valor;
    }
    return 'index.php' . (empty($actuales) ? '' : '?' . http_build_query($actuales));
}

/** Genera (y reutiliza) el token CSRF de la sesión. */
function tokenCsrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Campo oculto con el token CSRF, listo para insertar en un formulario. */
function campoCsrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(tokenCsrf()) . '">';
}

/** Valida el token CSRF recibido; termina la petición si no coincide. */
function validarCsrf(): void
{
    $recibido = $_POST['csrf'] ?? $_GET['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $recibido)) {
        http_response_code(403);
        exit('Solicitud no válida (token CSRF incorrecto).');
    }
}

/** Lista de estatus permitidos (debe coincidir con el ENUM de MySQL). */
function estatusValidos(): array
{
    return ['Recibido', 'En proceso', 'Atendido'];
}
