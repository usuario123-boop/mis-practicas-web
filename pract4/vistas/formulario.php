<?php
// Alta y edición. En edición se precargan los datos y el PDF deja de ser obligatorio.

$esEdicion = ($vista === 'editar');
$doc = [
    'id' => 0, 'no_oficio' => '', 'fecha' => date('Y-m-d'), 'asunto' => '',
    'remitente' => '', 'destinatario' => '', 'estatus' => 'Recibido',
    'archivo_nombre' => '', 'archivo_tam' => 0, 'github_url' => null,
];

if ($esEdicion) {
    $encontrado = obtenerDocumento($conexion, (int) ($_GET['id'] ?? 0));
    if (!$encontrado) {
        flash('danger', 'El documento solicitado no existe.');
        redirigir('index.php?vista=documentos');
    }
    $doc = $encontrado;
}
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-10 col-lg-8 col-xl-7">
        <div class="main-card">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-<?= $esEdicion ? 'pencil' : 'plus' ?> text-primary"></i>
                    <?= $esEdicion ? 'Editar Documento' : 'Registrar Documento' ?>
                </h5>
                <a href="index.php?vista=documentos" class="btn btn-sm btn-light rounded-3">
                    <i class="bi bi-arrow-left me-1"></i>Volver
                </a>
            </div>

            <form action="acciones/guardar.php" method="POST" enctype="multipart/form-data"
                  id="formDocumento" data-requiere-archivo="<?= $esEdicion ? 'false' : 'true' ?>" novalidate>

                <?= campoCsrf() ?>
                <input type="hidden" name="id" value="<?= (int) $doc['id'] ?>">

                <div class="row g-3 mb-3">
                    <div class="col-12 col-sm-6">
                        <label for="no_oficio" class="form-label small fw-bold">
                            No. de Oficio <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="no_oficio" name="no_oficio"
                               value="<?= e($doc['no_oficio']) ?>" placeholder="Ej. 0452/2026" maxlength="50" required>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label for="fecha" class="form-label small fw-bold">
                            Fecha <span class="text-danger">*</span>
                        </label>
                        <input type="date" class="form-control" id="fecha" name="fecha"
                               value="<?= e($doc['fecha']) ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="asunto" class="form-label small fw-bold">
                        Asunto <span class="text-danger">*</span>
                    </label>
                    <textarea class="form-control" id="asunto" name="asunto" rows="2" maxlength="255"
                              placeholder="Descripción breve del asunto del documento" required><?= e($doc['asunto']) ?></textarea>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-sm-6">
                        <label for="remitente" class="form-label small fw-bold">
                            Remitente <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="remitente" name="remitente" list="listaRemitentes"
                               value="<?= e($doc['remitente']) ?>" placeholder="Nombre o dependencia" maxlength="150" required>
                        <datalist id="listaRemitentes">
                            <?php foreach (valoresDistintos($conexion, 'remitente') as $v): ?>
                                <option value="<?= e($v) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label for="destinatario" class="form-label small fw-bold">
                            Destinatario <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="destinatario" name="destinatario" list="listaDestinatarios"
                               value="<?= e($doc['destinatario']) ?>" placeholder="Nombre o área" maxlength="150" required>
                        <datalist id="listaDestinatarios">
                            <?php foreach (valoresDistintos($conexion, 'destinatario') as $v): ?>
                                <option value="<?= e($v) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="estatus" class="form-label small fw-bold">
                        Estatus <span class="text-danger">*</span>
                    </label>
                    <select class="form-select" id="estatus" name="estatus" required>
                        <option value="Recibido"   <?= $doc['estatus'] === 'Recibido'   ? 'selected' : '' ?>>🟢 Recibido</option>
                        <option value="En proceso" <?= $doc['estatus'] === 'En proceso' ? 'selected' : '' ?>>🟡 En proceso</option>
                        <option value="Atendido"   <?= $doc['estatus'] === 'Atendido'   ? 'selected' : '' ?>>🔵 Atendido</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold">
                        Documento (foto o PDF)
                        <?= $esEdicion ? '<span class="text-muted fw-normal">(opcional: solo si deseas reemplazarlo)</span>'
                                       : '<span class="text-danger">*</span>' ?>
                    </label>

                    <div class="row g-2">
                        <div class="col-6">
                            <div class="btn-upload-option" id="btnCamara" role="button" tabindex="0">
                                <i class="bi bi-camera fs-2 text-primary"></i>
                                <div class="small fw-bold mt-2">Tomar foto<br>del documento</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="btn-upload-option" id="btnArchivo" role="button" tabindex="0">
                                <i class="bi bi-upload fs-2 text-primary"></i>
                                <div class="small fw-bold mt-2">Seleccionar<br>archivo PDF</div>
                            </div>
                        </div>
                    </div>

                    <!--
                        Dos inputs distintos: el de cámara usa capture="environment"
                        para abrir la cámara trasera en dispositivos móviles; la foto
                        resultante se convierte a PDF en el servidor.
                    -->
                    <input type="file" class="d-none" id="entradaCamara" name="archivo_camara"
                           accept="image/jpeg" capture="environment">
                    <input type="file" class="d-none" id="entradaArchivo" name="archivo_pdf"
                           accept="application/pdf,image/jpeg">

                    <!-- Vista previa del archivo elegido -->
                    <div id="cajaPrevia" class="mt-3 d-none p-3 bg-light rounded-3 border d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <i id="iconoArchivo" class="bi bi-file-earmark-pdf-fill text-danger fs-3"></i>
                            <div>
                                <small class="fw-bold d-block" id="nombreArchivo"></small>
                                <small class="text-muted" id="tamanoArchivo"></small>
                            </div>
                        </div>
                        <button type="button" class="btn-close" id="btnQuitarArchivo" aria-label="Quitar archivo"></button>
                    </div>

                    <div id="avisoArchivo" class="alert alert-warning small mt-3 d-none mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Debes adjuntar el documento digitalizado (PDF o fotografía).
                    </div>

                    <?php if ($esEdicion && $doc['archivo_nombre']): ?>
                        <div class="mt-3 p-3 bg-light rounded-3 border d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-file-earmark-pdf-fill text-danger fs-3"></i>
                                <div>
                                    <small class="fw-bold d-block">Archivo actual</small>
                                    <small class="text-muted"><?= e($doc['archivo_nombre']) ?>
                                        <?= $doc['archivo_tam'] ? ' · ' . tamanoLegible((int) $doc['archivo_tam']) : '' ?>
                                    </small>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-3 ver-pdf"
                                    data-id="<?= (int) $doc['id'] ?>"
                                    data-oficio="<?= e($doc['no_oficio']) ?>"
                                    data-asunto="<?= e($doc['asunto']) ?>">
                                <i class="bi bi-eye me-1"></i>Ver
                            </button>
                        </div>
                    <?php endif; ?>

                    <small class="text-muted d-block mt-2">
                        Formatos aceptados: PDF o fotografía JPG. Tamaño máximo: 10 MB.
                        <?php if (GH_ACTIVO): ?>
                            <br><i class="bi bi-github"></i> El archivo también se respaldará automáticamente en el repositorio de GitHub.
                        <?php endif; ?>
                    </small>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-bold">
                    <i class="bi bi-save me-1"></i>
                    <?= $esEdicion ? 'Guardar cambios' : 'Guardar Documento' ?>
                </button>
            </form>

        </div>
    </div>
</div>
