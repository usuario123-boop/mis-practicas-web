<?php
/**
 * vistas/documentos.php
 * Listado de documentos con buscador, filtros, ordenamiento y paginación.
 */

$filtros = [
    'buscar'  => trim($_GET['buscar'] ?? ''),
    'estatus' => $_GET['estatus'] ?? 'Todos',
    'desde'   => $_GET['desde'] ?? '',
    'hasta'   => $_GET['hasta'] ?? '',
];
$orden = $_GET['orden'] ?? 'fecha_desc';

// --- Paginación -------------------------------------------------------------
$total     = contarDocumentos($conexion, $filtros);
$paginas   = max(1, (int) ceil($total / POR_PAGINA));
$pagina    = max(1, min($paginas, (int) ($_GET['pagina'] ?? 1)));
$desde     = ($pagina - 1) * POR_PAGINA;

$documentos = listarDocumentos($conexion, $filtros, POR_PAGINA, $desde, $orden);

$primero = $total === 0 ? 0 : $desde + 1;
$ultimo  = min($desde + POR_PAGINA, $total);
?>

<div class="main-card">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
            <i class="bi bi-file-earmark-text text-primary"></i> Listado de Documentos
        </h5>
        <a href="index.php?vista=nuevo" class="btn btn-primary px-3 rounded-3 fw-bold">
            <i class="bi bi-plus-lg me-1"></i> Nuevo Documento
        </a>
    </div>

    <!-- ===================== Buscador y filtros ===================== -->
    <form method="GET" action="index.php" class="row g-2 mb-4">
        <input type="hidden" name="vista" value="documentos">

        <div class="col-12">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" class="form-control border-start-0" name="buscar"
                       value="<?= e($filtros['buscar']) ?>"
                       placeholder="Buscar por No. de oficio, asunto, remitente o destinatario…">
                <?php if ($filtros['buscar'] !== ''): ?>
                    <a href="<?= e(urlCon(['buscar' => null, 'pagina' => null])) ?>" class="btn btn-outline-secondary" title="Limpiar búsqueda">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <label class="form-label small text-muted fw-bold mb-1">Estatus</label>
            <select class="form-select filtro-auto" name="estatus">
                <option value="Todos">Todos</option>
                <?php foreach (estatusValidos() as $op): ?>
                    <option value="<?= e($op) ?>" <?= $filtros['estatus'] === $op ? 'selected' : '' ?>><?= e($op) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-6 col-md-2">
            <label class="form-label small text-muted fw-bold mb-1">Desde</label>
            <input type="date" class="form-control" name="desde" value="<?= e($filtros['desde']) ?>">
        </div>

        <div class="col-6 col-md-2">
            <label class="form-label small text-muted fw-bold mb-1">Hasta</label>
            <input type="date" class="form-control" name="hasta" value="<?= e($filtros['hasta']) ?>">
        </div>

        <div class="col-6 col-md-3">
            <label class="form-label small text-muted fw-bold mb-1">Ordenar por</label>
            <select class="form-select filtro-auto" name="orden">
                <option value="fecha_desc"  <?= $orden === 'fecha_desc'  ? 'selected' : '' ?>>Más recientes</option>
                <option value="fecha_asc"   <?= $orden === 'fecha_asc'   ? 'selected' : '' ?>>Más antiguos</option>
                <option value="oficio_asc"  <?= $orden === 'oficio_asc'  ? 'selected' : '' ?>>No. de oficio (A-Z)</option>
                <option value="oficio_desc" <?= $orden === 'oficio_desc' ? 'selected' : '' ?>>No. de oficio (Z-A)</option>
                <option value="estatus"     <?= $orden === 'estatus'     ? 'selected' : '' ?>>Estatus</option>
            </select>
        </div>

        <div class="col-12 col-md-2 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-primary w-100 fw-bold rounded-3">
                <i class="bi bi-funnel me-1"></i> Filtrar
            </button>
        </div>
    </form>

    <?php
    // Indicador de filtros activos, con la opción de limpiarlos de un clic.
    $activos = array_filter([
        $filtros['buscar']  !== ''      ? 'Texto: "' . $filtros['buscar'] . '"' : null,
        $filtros['estatus'] !== 'Todos' ? 'Estatus: ' . $filtros['estatus']     : null,
        $filtros['desde']   !== ''      ? 'Desde: ' . fechaLocal($filtros['desde']) : null,
        $filtros['hasta']   !== ''      ? 'Hasta: ' . fechaLocal($filtros['hasta']) : null,
    ]);
    ?>
    <?php if ($activos): ?>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <?php foreach ($activos as $texto): ?>
                <span class="badge bg-light text-dark border fw-normal"><?= e($texto) ?></span>
            <?php endforeach; ?>
            <a href="index.php?vista=documentos" class="small text-decoration-none">Limpiar filtros</a>
        </div>
    <?php endif; ?>

    <!-- ===================== Resultados ===================== -->
    <?php require __DIR__ . '/_tabla.php'; ?>

    <!-- ===================== Paginación ===================== -->
    <?php if ($total > 0): ?>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mt-4">
            <small class="text-muted">
                Mostrando <?= $primero ?> a <?= $ultimo ?> de <?= $total ?> registro<?= $total === 1 ? '' : 's' ?>
            </small>

            <?php if ($paginas > 1): ?>
                <nav aria-label="Paginación de documentos">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $pagina === 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= e(urlCon(['pagina' => 1])) ?>" aria-label="Primera">&laquo;</a>
                        </li>
                        <li class="page-item <?= $pagina === 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= e(urlCon(['pagina' => $pagina - 1])) ?>" aria-label="Anterior">&lsaquo;</a>
                        </li>

                        <?php
                        // Se muestra una ventana de 5 páginas alrededor de la actual.
                        $ini = max(1, min($pagina - 2, $paginas - 4));
                        $fin = min($paginas, $ini + 4);
                        for ($p = $ini; $p <= $fin; $p++): ?>
                            <li class="page-item <?= $p === $pagina ? 'active' : '' ?>">
                                <a class="page-link" href="<?= e(urlCon(['pagina' => $p])) ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?= $pagina === $paginas ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= e(urlCon(['pagina' => $pagina + 1])) ?>" aria-label="Siguiente">&rsaquo;</a>
                        </li>
                        <li class="page-item <?= $pagina === $paginas ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= e(urlCon(['pagina' => $paginas])) ?>" aria-label="Última">&raquo;</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>
