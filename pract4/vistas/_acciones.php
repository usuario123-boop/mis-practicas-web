<?php
// Botones de la columna Acciones. Espera $doc.
?>
<!-- Ver: abre el PDF en una ventana modal sobre la misma página -->
<button type="button" class="btn-accion ver-pdf"
        data-id="<?= (int) $doc['id'] ?>"
        data-oficio="<?= e($doc['no_oficio']) ?>"
        data-asunto="<?= e($doc['asunto']) ?>"
        title="Ver PDF sin salir de la página">
    <i class="bi bi-eye"></i>
</button>

<!-- Descargar el archivo original -->
<a href="descargar.php?id=<?= (int) $doc['id'] ?>" class="btn-accion d-inline-block" title="Descargar PDF">
    <i class="bi bi-download"></i>
</a>

<!-- Editar los datos del documento -->
<a href="index.php?vista=editar&id=<?= (int) $doc['id'] ?>" class="btn-accion d-inline-block" title="Editar documento">
    <i class="bi bi-pencil"></i>
</a>

<!-- Menú con el cambio rápido de estatus y la eliminación -->
<div class="dropdown d-inline-block">
    <button class="btn-accion" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" title="Más acciones">
        <i class="bi bi-three-dots-vertical"></i>
    </button>
    <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3">
        <li><h6 class="dropdown-header">Cambiar estatus</h6></li>
        <?php foreach (estatusValidos() as $opcion): ?>
            <li>
                <a class="dropdown-item small cambiar-estatus <?= $doc['estatus'] === $opcion ? 'active' : '' ?>"
                   href="#" data-id="<?= (int) $doc['id'] ?>" data-estatus="<?= e($opcion) ?>">
                    <span class="badge-status <?= claseEstatus($opcion) ?> me-2" style="font-size:.7rem"><?= e($opcion) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
        <li><hr class="dropdown-divider"></li>
        <?php if (!empty($doc['github_url'])): ?>
            <li>
                <a class="dropdown-item small" href="<?= e($doc['github_url']) ?>" target="_blank" rel="noopener">
                    <i class="bi bi-github me-2"></i>Ver respaldo en GitHub
                </a>
            </li>
        <?php endif; ?>
        <li>
            <a class="dropdown-item small text-danger eliminar-doc" href="#"
               data-id="<?= (int) $doc['id'] ?>" data-oficio="<?= e($doc['no_oficio']) ?>">
                <i class="bi bi-trash me-2"></i>Eliminar documento
            </a>
        </li>
    </ul>
</div>
