<?php
// Búsqueda avanzada: texto libre + remitente + estatus + rango de fechas.

$seBuscó = isset($_GET['enviado']);

$filtros = [
    'buscar'    => trim($_GET['buscar'] ?? ''),
    'estatus'   => $_GET['estatus'] ?? 'Todos',
    'desde'     => $_GET['desde'] ?? '',
    'hasta'     => $_GET['hasta'] ?? '',
    'remitente' => $_GET['remitente'] ?? '',
];

$documentos = [];
$total      = 0;

if ($seBuscó) {
    $total      = contarDocumentos($conexion, $filtros);
    $documentos = listarDocumentos($conexion, $filtros, 200, 0, 'fecha_desc');
}
?>

<div class="main-card mb-3">
    <h5 class="fw-bold mb-1 d-flex align-items-center gap-2">
        <i class="bi bi-search text-primary"></i> Búsqueda avanzada
    </h5>
    <p class="text-muted small mb-4">
        Localiza un documento combinando varios criterios. Deja en blanco los campos que no quieras usar.
    </p>

    <form method="GET" action="index.php" class="row g-3">
        <input type="hidden" name="vista" value="buscar">
        <input type="hidden" name="enviado" value="1">

        <div class="col-12">
            <label for="b-texto" class="form-label small fw-bold">Texto a buscar</label>
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" class="form-control border-start-0" id="b-texto" name="buscar"
                       value="<?= e($filtros['buscar']) ?>"
                       placeholder="No. de oficio, asunto, remitente o destinatario">
            </div>
            <small class="text-muted">Se busca una coincidencia parcial en los cuatro campos.</small>
        </div>

        <div class="col-12 col-md-6">
            <label for="b-remitente" class="form-label small fw-bold">Remitente</label>
            <select class="form-select" id="b-remitente" name="remitente">
                <option value="">Cualquiera</option>
                <?php foreach (valoresDistintos($conexion, 'remitente') as $v): ?>
                    <option value="<?= e($v) ?>" <?= $filtros['remitente'] === $v ? 'selected' : '' ?>><?= e($v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12 col-md-6">
            <label for="b-estatus" class="form-label small fw-bold">Estatus</label>
            <select class="form-select" id="b-estatus" name="estatus">
                <option value="Todos">Todos</option>
                <?php foreach (estatusValidos() as $op): ?>
                    <option value="<?= e($op) ?>" <?= $filtros['estatus'] === $op ? 'selected' : '' ?>><?= e($op) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-6 col-md-6">
            <label for="b-desde" class="form-label small fw-bold">Fecha desde</label>
            <input type="date" class="form-control" id="b-desde" name="desde" value="<?= e($filtros['desde']) ?>">
        </div>

        <div class="col-6 col-md-6">
            <label for="b-hasta" class="form-label small fw-bold">Fecha hasta</label>
            <input type="date" class="form-control" id="b-hasta" name="hasta" value="<?= e($filtros['hasta']) ?>">
        </div>

        <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary rounded-3 fw-bold px-4">
                <i class="bi bi-search me-1"></i> Buscar
            </button>
            <a href="index.php?vista=buscar" class="btn btn-light rounded-3">Limpiar</a>
        </div>
    </form>
</div>

<?php if ($seBuscó): ?>
    <div class="main-card">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <h6 class="fw-bold mb-0">
                Resultados
                <span class="badge bg-primary-subtle text-primary ms-1"><?= $total ?></span>
            </h6>
            <?php if ($total > 0): ?>
                <input type="search" class="form-control form-control-sm w-auto" id="busquedaRapida"
                       placeholder="Afinar dentro de los resultados…">
            <?php endif; ?>
        </div>

        <?php require __DIR__ . '/_tabla.php'; ?>

        <?php if ($total > 200): ?>
            <p class="small text-muted mt-3 mb-0">
                <i class="bi bi-info-circle me-1"></i>
                Se muestran los primeros 200 resultados. Acota los criterios para ver el resto.
            </p>
        <?php endif; ?>
    </div>
<?php endif; ?>
