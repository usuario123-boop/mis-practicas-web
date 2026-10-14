<?php
// Pie: barra inferior en móvil, ventanas modales compartidas y scripts.
?>
        </main>
    </div>
</div>

<nav class="bottom-nav d-lg-none">
    <a href="index.php?vista=inicio" class="bottom-nav-item <?= $vista === 'inicio' ? 'active' : '' ?>">
        <i class="bi bi-house-door"></i><span>Inicio</span>
    </a>
    <a href="index.php?vista=documentos" class="bottom-nav-item <?= in_array($vista, ['documentos','editar'], true) ? 'active' : '' ?>">
        <i class="bi bi-file-earmark-text"></i><span>Documentos</span>
    </a>
    <a href="index.php?vista=nuevo" class="bottom-nav-item">
        <div class="btn-plus-floating"><i class="bi bi-plus-lg"></i></div>
        <span class="text-primary fw-bold">Nuevo</span>
    </a>
    <a href="index.php?vista=buscar" class="bottom-nav-item <?= $vista === 'buscar' ? 'active' : '' ?>">
        <i class="bi bi-search"></i><span>Buscar</span>
    </a>
    <a href="index.php?vista=configuracion" class="bottom-nav-item <?= $vista === 'configuracion' ? 'active' : '' ?>">
        <i class="bi bi-person"></i><span>Perfil</span>
    </a>
</nav>

<div class="modal fade modal-visor" id="modalVisor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-md-down">
        <div class="modal-content border-0 rounded-4 overflow-hidden">
            <div class="modal-header bg-white">
                <div class="me-auto">
                    <h6 class="modal-title fw-bold mb-0" id="visorTitulo">Documento</h6>
                    <small class="text-muted texto-recortado" id="visorSubtitulo"></small>
                </div>
                <a href="#" id="visorDescargar" class="btn btn-sm btn-outline-primary me-2" title="Descargar PDF">
                    <i class="bi bi-download"></i><span class="d-none d-sm-inline ms-1">Descargar</span>
                </a>
                <a href="#" id="visorNuevaPestana" target="_blank" rel="noopener"
                   class="btn btn-sm btn-outline-secondary me-2" title="Abrir en pestaña nueva">
                    <i class="bi bi-box-arrow-up-right"></i>
                </a>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <!-- El src se asigna con jQuery al abrir y se limpia al cerrar -->
                <iframe id="visorMarco" src="about:blank" title="Visor de documento PDF"></iframe>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEliminar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header border-0">
                <h6 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Eliminar documento</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-0">
                Se eliminará el oficio <strong id="eliminarOficio"></strong>, su archivo PDF y su bitácora.
                Esta acción no se puede deshacer.
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancelar</button>
                <form action="acciones/eliminar.php" method="POST" id="formEliminar" class="d-inline">
                    <?= campoCsrf() ?>
                    <input type="hidden" name="id" value="">
                    <button type="submit" class="btn btn-danger rounded-3">
                        <i class="bi bi-trash me-1"></i>Sí, eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Contenedor de avisos emergentes -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="contenedorToasts" style="z-index:1090; margin-bottom:80px"></div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.CSRF_TOKEN = <?= json_encode(tokenCsrf()) ?>;</script>
<script src="<?= e(versionAsset('assets/js/app.js')) ?>"></script>
</body>
</html>
