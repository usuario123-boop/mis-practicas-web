<?php
// Alta y actualización de documentos. La foto JPEG se convierte a PDF antes
// de guardarla, para que todo el acervo quede en un solo formato.

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/consultas.php';
require_once __DIR__ . '/../includes/github.php';
require_once __DIR__ . '/../includes/pdf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('../index.php?vista=documentos');
}

// Si el envío excede post_max_size, PHP descarta $_POST y el fallo aparecería
// como un token CSRF inválido en lugar de un problema de tamaño.
if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('danger', 'El envío superó el tamaño máximo permitido (' . tamanoLegible(MAX_PDF_BYTES) . '). Usa un archivo más pequeño.');
    redirigir('../index.php?vista=nuevo');
}

validarCsrf();

$id           = (int) ($_POST['id'] ?? 0);
$esEdicion    = $id > 0;
$no_oficio    = trim($_POST['no_oficio'] ?? '');
$fecha        = trim($_POST['fecha'] ?? '');
$asunto       = trim($_POST['asunto'] ?? '');
$remitente    = trim($_POST['remitente'] ?? '');
$destinatario = trim($_POST['destinatario'] ?? '');
$estatus      = $_POST['estatus'] ?? 'Recibido';

$errores = [];
if ($no_oficio === '')                       $errores[] = 'el número de oficio';
if ($asunto === '')                          $errores[] = 'el asunto';
if ($remitente === '')                       $errores[] = 'el remitente';
if ($destinatario === '')                    $errores[] = 'el destinatario';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $errores[] = 'una fecha válida';
if (!in_array($estatus, estatusValidos(), true)) $estatus = 'Recibido';

if ($errores) {
    flash('danger', 'Faltan datos obligatorios: ' . e(implode(', ', $errores)) . '.');
    redirigir('../index.php?vista=' . ($esEdicion ? "editar&id=$id" : 'nuevo'));
}

// En edición hace falta el registro previo para conservar o reemplazar el archivo.
$anterior = $esEdicion ? obtenerDocumento($conexion, $id) : null;
if ($esEdicion && !$anterior) {
    flash('danger', 'El documento que intentas editar ya no existe.');
    redirigir('../index.php?vista=documentos');
}

$archivoNombre = $anterior['archivo_nombre'] ?? null;
$archivoRuta   = $anterior['archivo_pdf']    ?? null;
$archivoTam    = (int) ($anterior['archivo_tam'] ?? 0);
$githubUrl     = $anterior['github_url']     ?? null;
$avisoGithub   = '';

// Se acepta el archivo de cualquiera de los dos inputs (cámara o selector).
$subido      = null;
$errorSubida = UPLOAD_ERR_NO_FILE;
foreach (['archivo_pdf', 'archivo_camara'] as $campo) {
    if (!isset($_FILES[$campo])) continue;
    if ($_FILES[$campo]['error'] === UPLOAD_ERR_OK) {
        $subido = $_FILES[$campo];
        break;
    }
    if ($_FILES[$campo]['error'] !== UPLOAD_ERR_NO_FILE) {
        $errorSubida = $_FILES[$campo]['error'];
    }
}

if ($subido === null && $errorSubida !== UPLOAD_ERR_NO_FILE) {
    $motivos = [
        UPLOAD_ERR_INI_SIZE   => 'el archivo supera el límite de ' . tamanoLegible(MAX_PDF_BYTES),
        UPLOAD_ERR_FORM_SIZE  => 'el archivo supera el límite del formulario',
        UPLOAD_ERR_PARTIAL    => 'la carga se interrumpió a medias',
        UPLOAD_ERR_NO_TMP_DIR => 'el servidor no tiene carpeta temporal disponible',
        UPLOAD_ERR_CANT_WRITE => 'el servidor no pudo escribir el archivo temporal',
    ];
    flash('danger', 'No se recibió el archivo: ' . ($motivos[$errorSubida] ?? 'error ' . $errorSubida) . '.');
    redirigir('../index.php?vista=' . ($esEdicion ? "editar&id=$id" : 'nuevo'));
}

if ($subido === null && !$esEdicion) {
    flash('danger', 'Debes adjuntar el documento digitalizado (PDF o fotografía).');
    redirigir('../index.php?vista=nuevo');
}

if ($subido !== null) {

    // En Vercel el disco se borra al terminar la petición: sin GitHub el PDF
    // quedaría registrado en la base pero no existiría en ningún lado.
    if (ALMACEN_EFIMERO && !GH_ACTIVO) {
        flash('danger', 'Falta configurar la variable <code>GITHUB_TOKEN</code>: sin ella no hay dónde almacenar el PDF.');
        redirigir('../index.php?vista=' . ($esEdicion ? "editar&id=$id" : 'nuevo'));
    }

    if ($subido['size'] > MAX_PDF_BYTES) {
        flash('danger', 'El archivo supera el límite de ' . tamanoLegible(MAX_PDF_BYTES) . '.');
        redirigir('../index.php?vista=' . ($esEdicion ? "editar&id=$id" : 'nuevo'));
    }

    // El tipo real se determina leyendo el contenido, no la extensión declarada.
    $tipoReal = (new finfo(FILEINFO_MIME_TYPE))->file($subido['tmp_name']);
    if (!in_array($tipoReal, ['application/pdf', 'image/jpeg'], true)) {
        flash('danger', 'Solo se aceptan archivos PDF o fotografías JPG. Se recibió: ' . e($tipoReal) . '.');
        redirigir('../index.php?vista=' . ($esEdicion ? "editar&id=$id" : 'nuevo'));
    }

    if (!is_dir(DIR_PDF)) {
        mkdir(DIR_PDF, 0775, true);
    }

    // Nombre único y seguro: fecha + aleatorio + número de oficio normalizado.
    $base = preg_replace('/[^A-Za-z0-9_-]/', '_', $no_oficio);
    $nuevoNombre = date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6) . '_' . $base . '.pdf';
    $rutaDestino = DIR_PDF . DIRECTORY_SEPARATOR . $nuevoNombre;

    if ($tipoReal === 'application/pdf') {
        $guardado = move_uploaded_file($subido['tmp_name'], $rutaDestino);
    } else {
        // Fotografía: se incrusta en un PDF de una página antes de almacenarla.
        $guardado = imagenJpegAPdf($subido['tmp_name'], $rutaDestino);
    }

    if (!$guardado) {
        $motivo = ALMACEN_EFIMERO
            ? 'No fue posible preparar el archivo en el servidor.'
            : 'No fue posible guardar el archivo. Revisa los permisos de la carpeta pdf/.';
        flash('danger', $motivo);
        redirigir('../index.php?vista=' . ($esEdicion ? "editar&id=$id" : 'nuevo'));
    }

    // Se elimina el archivo anterior para no dejar basura acumulada.
    if ($esEdicion && $archivoNombre) {
        $viejo = DIR_PDF . DIRECTORY_SEPARATOR . $archivoNombre;
        if (is_file($viejo)) unlink($viejo);
    }

    $archivoNombre = $nuevoNombre;
    $archivoRuta   = 'pdf/' . $nuevoNombre;
    $archivoTam    = (int) filesize($rutaDestino);

    if (GH_ACTIVO) {
        $respaldo = githubSubirArchivo(
            $rutaDestino,
            $nuevoNombre,
            'Documento ' . $no_oficio . ' (' . $fecha . ') — respaldo automático desde la aplicación'
        );
        if ($respaldo['ok']) {
            $githubUrl   = $respaldo['url'];
            $avisoGithub = ' Archivo almacenado en GitHub.';
        } elseif (ALMACEN_EFIMERO) {
            @unlink($rutaDestino);
            flash('danger', 'El documento no se guardó porque falló la subida a GitHub: ' . e($respaldo['detalle']));
            redirigir('../index.php?vista=' . ($esEdicion ? "editar&id=$id" : 'nuevo'));
        } else {
            $avisoGithub = ' (El respaldo en GitHub no se completó: ' . e($respaldo['detalle']) . ')';
        }
    }
}

if ($esEdicion) {

    $sql = 'UPDATE documentos
               SET no_oficio = ?, fecha = ?, asunto = ?, remitente = ?, destinatario = ?,
                   estatus = ?, archivo_pdf = ?, archivo_nombre = ?, archivo_tam = ?, github_url = ?
             WHERE id = ?';
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param(
        'ssssssssisi',
        $no_oficio, $fecha, $asunto, $remitente, $destinatario,
        $estatus, $archivoRuta, $archivoNombre, $archivoTam, $githubUrl, $id
    );
    $stmt->execute();
    $stmt->close();

    registrarBitacora($conexion, $id, 'Documento actualizado',
        'Se modificaron los datos del oficio ' . $no_oficio . '.');

    flash('success', 'El documento <strong>' . e($no_oficio) . '</strong> se actualizó correctamente.' . $avisoGithub);
    redirigir('../index.php?vista=documentos');

} else {

    $sql = 'INSERT INTO documentos
                (no_oficio, fecha, asunto, remitente, destinatario, estatus,
                 archivo_pdf, archivo_nombre, archivo_tam, github_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param(
        'ssssssssis',
        $no_oficio, $fecha, $asunto, $remitente, $destinatario,
        $estatus, $archivoRuta, $archivoNombre, $archivoTam, $githubUrl
    );
    $stmt->execute();
    $nuevoId = $stmt->insert_id;
    $stmt->close();

    registrarBitacora($conexion, $nuevoId, 'Documento registrado',
        'Alta del oficio ' . $no_oficio . ' con estatus "' . $estatus . '".');

    flash('success', 'El documento <strong>' . e($no_oficio) . '</strong> se registró correctamente.' . $avisoGithub);
    redirigir('../index.php?vista=documentos');
}
