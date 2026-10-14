<?php
// Alta y actualización de documentos. La foto JPEG se convierte a PDF antes
// de guardarla, para que todo el acervo quede en un solo formato.

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/consultas.php';
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

// Queda en null cuando se edita sin reemplazar el documento: en ese caso las
// columnas del archivo no se tocan y el PDF anterior se conserva.
$contenido = null;

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

    // Nombre único y seguro: fecha + aleatorio + número de oficio normalizado.
    // Ya no nombra ningún archivo en disco, pero sigue identificando al documento
    // y es el nombre con el que se descarga.
    $base = preg_replace('/[^A-Za-z0-9_-]/', '_', $no_oficio);
    $nuevoNombre = date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6) . '_' . $base . '.pdf';

    if ($tipoReal === 'application/pdf') {
        $contenido = file_get_contents($subido['tmp_name']);
    } else {
        // Fotografía: se incrusta en un PDF de una página antes de almacenarla.
        // La conversión escribe en un archivo, así que se usa uno temporal que
        // se descarta en cuanto el PDF está en memoria.
        $temporal  = tempnam(sys_get_temp_dir(), 'pdf');
        $contenido = imagenJpegAPdf($subido['tmp_name'], $temporal)
            ? file_get_contents($temporal)
            : false;
        @unlink($temporal);
    }

    if ($contenido === false || $contenido === '') {
        flash('danger', 'No fue posible preparar el archivo PDF.');
        redirigir('../index.php?vista=' . ($esEdicion ? "editar&id=$id" : 'nuevo'));
    }

    // En XAMPP, además de la base de datos, queda una copia en prac4/pdf/.
    // Si se reemplaza el documento de un oficio, la copia anterior se retira
    // para no dejar archivos sueltos en la carpeta.
    if ($esEdicion && !empty($anterior['archivo_nombre'])) {
        borrarCopiaPdf($anterior['archivo_nombre']);
    }
    guardarCopiaPdf($nuevoNombre, $contenido);

    $archivoNombre = $nuevoNombre;
    $archivoRuta   = $nuevoNombre;
    $archivoTam    = strlen($contenido);
}

if ($esEdicion) {

    if ($contenido !== null) {
        $sql = 'UPDATE documentos
                   SET no_oficio = ?, fecha = ?, asunto = ?, remitente = ?, destinatario = ?,
                       estatus = ?, archivo_pdf = ?, archivo_nombre = ?, archivo_tam = ?,
                       archivo_datos = ?
                 WHERE id = ?';
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param(
            'ssssssssisi',
            $no_oficio, $fecha, $asunto, $remitente, $destinatario,
            $estatus, $archivoRuta, $archivoNombre, $archivoTam, $contenido, $id
        );
    } else {
        // Sin documento nuevo: solo cambian los datos del oficio.
        $sql = 'UPDATE documentos
                   SET no_oficio = ?, fecha = ?, asunto = ?, remitente = ?, destinatario = ?,
                       estatus = ?
                 WHERE id = ?';
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param(
            'ssssssi',
            $no_oficio, $fecha, $asunto, $remitente, $destinatario, $estatus, $id
        );
    }
    $stmt->execute();
    $stmt->close();

    registrarBitacora($conexion, $id, 'Documento actualizado',
        'Se modificaron los datos del oficio ' . $no_oficio . '.');

    flash('success', 'El documento <strong>' . e($no_oficio) . '</strong> se actualizó correctamente.');
    redirigir('../index.php?vista=documentos');

} else {

    $sql = 'INSERT INTO documentos
                (no_oficio, fecha, asunto, remitente, destinatario, estatus,
                 archivo_pdf, archivo_nombre, archivo_tam, archivo_datos)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param(
        'ssssssssis',
        $no_oficio, $fecha, $asunto, $remitente, $destinatario,
        $estatus, $archivoRuta, $archivoNombre, $archivoTam, $contenido
    );
    $stmt->execute();
    $nuevoId = $stmt->insert_id;
    $stmt->close();

    registrarBitacora($conexion, $nuevoId, 'Documento registrado',
        'Alta del oficio ' . $no_oficio . ' con estatus "' . $estatus . '".');

    flash('success', 'El documento <strong>' . e($no_oficio) . '</strong> se registró correctamente.');
    redirigir('../index.php?vista=documentos');
}
