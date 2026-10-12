<?php
// Fragmento reutilizable. En escritorio se dibuja como tabla y en móvil como
// tarjetas, porque siete columnas no caben en la pantalla de un teléfono.
// Espera $documentos.

$documentos = $documentos ?? [];
?>

<?php if (empty($documentos)): ?>

    <div class="text-center py-5">
        <i class="bi bi-inbox text-muted" style="font-size:3rem"></i>
        <p class="text-muted mt-3 mb-1">No se encontraron documentos con esos criterios.</p>
        <a href="index.php?vista=nuevo" class="btn btn-sm btn-primary rounded-3 mt-2">
            <i class="bi bi-plus-lg me-1"></i>Registrar uno nuevo
        </a>
    </div>

<?php else: ?>

    <div class="table-responsive d-none d-lg-block">
        <table class="table align-middle tabla-docs mb-0">
            <thead>
                <tr>
                    <th>No. Oficio</th>
                    <th>Fecha</th>
                    <th>Asunto</th>
                    <th>Remitente</th>
                    <th>Destinatario</th>
                    <th>Estatus</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documentos as $doc): ?>
                    <tr class="fila-doc">
                        <td class="small fw-bold"><?= e($doc['no_oficio']) ?></td>
                        <td class="small text-nowrap"><?= fechaLocal($doc['fecha']) ?></td>
                        <td class="small"><?= e($doc['asunto']) ?></td>
                        <td class="small"><?= e($doc['remitente']) ?></td>
                        <td class="small"><?= e($doc['destinatario']) ?></td>
                        <td>
                            <span class="badge-status <?= claseEstatus($doc['estatus']) ?>" id="estatus-<?= (int) $doc['id'] ?>">
                                <?= e($doc['estatus']) ?>
                            </span>
                        </td>
                        <td class="text-center text-nowrap">
                            <?php require __DIR__ . '/_acciones.php'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="d-lg-none">
        <?php foreach ($documentos as $doc): ?>
            <article class="doc-card">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <div class="fw-bold"><?= e($doc['no_oficio']) ?></div>
                        <small class="text-muted"><?= fechaLocal($doc['fecha']) ?></small>
                    </div>
                    <div class="dropdown">
                        <button class="badge-status <?= claseEstatus($doc['estatus']) ?> border-0"
                                id="estatus-m-<?= (int) $doc['id'] ?>"
                                data-bs-toggle="dropdown" aria-expanded="false">
                            <?= e($doc['estatus']) ?> <i class="bi bi-chevron-down ms-1" style="font-size:.65rem"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3">
                            <li><h6 class="dropdown-header">Cambiar estatus</h6></li>
                            <?php foreach (estatusValidos() as $opcion): ?>
                                <li>
                                    <a class="dropdown-item small cambiar-estatus <?= $doc['estatus'] === $opcion ? 'active' : '' ?>"
                                       href="#" data-id="<?= (int) $doc['id'] ?>" data-estatus="<?= e($opcion) ?>">
                                        <span class="badge-status <?= claseEstatus($opcion) ?>" style="font-size:.7rem"><?= e($opcion) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <p class="mt-2 mb-2 small fw-semibold"><?= e($doc['asunto']) ?></p>

                <div class="doc-card__fila"><span>Remitente</span><span><?= e($doc['remitente']) ?></span></div>
                <div class="doc-card__fila"><span>Destinatario</span><span><?= e($doc['destinatario']) ?></span></div>

                <div class="d-flex gap-2 mt-3">
                    <button type="button"
                            class="btn btn-sm btn-primary flex-fill rounded-3 ver-pdf"
                            data-id="<?= (int) $doc['id'] ?>"
                            data-oficio="<?= e($doc['no_oficio']) ?>"
                            data-asunto="<?= e($doc['asunto']) ?>">
                        <i class="bi bi-eye me-1"></i>Ver PDF
                    </button>
                    <a href="descargar.php?id=<?= (int) $doc['id'] ?>" class="btn btn-sm btn-outline-secondary rounded-3" title="Descargar">
                        <i class="bi bi-download"></i>
                    </a>
                    <a href="index.php?vista=editar&id=<?= (int) $doc['id'] ?>" class="btn btn-sm btn-outline-secondary rounded-3" title="Editar">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-danger rounded-3 eliminar-doc"
                            data-id="<?= (int) $doc['id'] ?>" data-oficio="<?= e($doc['no_oficio']) ?>" title="Eliminar">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <div id="sinResultadosFiltro" class="text-center py-4 text-muted d-none">
        <i class="bi bi-search"></i> Ningún documento de esta página coincide con el texto escrito.
    </div>

<?php endif; ?>
