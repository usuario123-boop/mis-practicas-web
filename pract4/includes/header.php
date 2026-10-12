<?php
// Cabecera: <head>, barra superior y menús. Recibe $vista desde index.php.

$vista = $vista ?? 'inicio';

// clave => [etiqueta, icono]
$menu = [
    'inicio'        => ['Inicio',           'bi-house-door'],
    'documentos'    => ['Documentos',       'bi-file-earmark-text'],
    'nuevo'         => ['Nuevo Documento',  'bi-plus-square'],
    'buscar'        => ['Buscar',           'bi-search'],
    'reportes'      => ['Reportes',         'bi-bar-chart'],
    'usuarios'      => ['Usuarios',         'bi-people'],
    'configuracion' => ['Configuración',    'bi-gear'],
];

// Se usa dos veces: en el menú lateral y en el deslizable.
function pintarMenu(array $menu, string $vista): void
{
    foreach ($menu as $clave => [$etiqueta, $icono]) {
        $activo = ($vista === $clave || ($vista === 'editar' && $clave === 'documentos')) ? ' active' : '';
        echo '<a href="index.php?vista=' . $clave . '" class="nav-link' . $activo . '">'
           . '<i class="bi ' . $icono . '"></i> ' . $etiqueta . '</a>';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Plataforma web responsiva para registrar, consultar y dar seguimiento a los documentos recibidos en una oficina. UNACH · ETDA Campus IV Tapachula.">
    <meta name="theme-color" content="#0b2447">
    <title><?= e(APP_NOMBRE) ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/estilos.css" rel="stylesheet">
</head>
<body>

<nav class="navbar-unach px-3 py-2">
    <div class="container-fluid d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <!-- Botón que abre el menú deslizable en móvil -->
            <button class="btn btn-link text-white d-lg-none p-0 border-0" type="button"
                    data-bs-toggle="offcanvas" data-bs-target="#menuMovil" aria-label="Abrir menú">
                <i class="bi bi-list fs-2"></i>
            </button>
            <div class="d-flex align-items-center gap-2">
                <div class="escudo"><i class="bi bi-bank text-white"></i></div>
                <div>
                    <h6 class="fw-bold mb-0 text-white lh-1">UNACH</h6>
                    <small class="text-white-50" style="font-size:.72rem">Gestión de Documentos · Oficina Digital</small>
                </div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3 text-white">
            <a href="index.php?vista=documentos&estatus=Recibido" class="text-white position-relative" title="Documentos por atender">
                <i class="bi bi-bell fs-5"></i>
                <?php if (!empty($pendientes)): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.6rem">
                        <?= (int) $pendientes ?>
                    </span>
                <?php endif; ?>
            </a>
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-person-circle fs-3"></i>
                <div class="d-none d-md-block text-end lh-1">
                    <small class="fw-bold d-block">Administrador</small>
                    <small class="text-white-50" style="font-size:.7rem">ETDA · Campus IV</small>
                </div>
            </div>
        </div>
    </div>
</nav>

<div class="offcanvas offcanvas-start offcanvas-unach" tabindex="-1" id="menuMovil">
    <div class="offcanvas-header border-bottom border-secondary">
        <h6 class="offcanvas-title fw-bold mb-0">Menú</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0">
        <div class="nav flex-column"><?php pintarMenu($menu, $vista); ?></div>
    </div>
</div>

<div class="container-fluid">
    <div class="row">

        <aside class="col-lg-2 d-none d-lg-block sidebar p-0">
            <div class="nav flex-column mt-3"><?php pintarMenu($menu, $vista); ?></div>
        </aside>

        <main class="col-12 col-lg-10 p-3 p-md-4">

            <?php if ($flash = obtenerFlash()): ?>
                <div class="alert alert-<?= e($flash['tipo']) ?> alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert">
                    <i class="bi bi-<?= $flash['tipo'] === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i>
                    <?= $flash['mensaje'] /* el mensaje ya viene escapado desde la acción */ ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
