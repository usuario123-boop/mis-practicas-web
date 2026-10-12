<?php
/**
 * acciones/guardar.php
 * Registra un documento nuevo o actualiza uno existente.
 *
 * Flujo:
 *   1. Validar el token CSRF y los datos del formulario.
 *   2. Procesar el archivo subido (PDF directo, o foto JPEG que se convierte a PDF).
 *   3. Guardar el archivo en la carpeta pdf/ y respaldarlo en GitHub.
 *   4. Insertar o actualizar el registro en MySQL mediante sentencias preparadas.
 */

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/consultas.php';
require_once __DIR__ . '/../includes/github.php';
require_once __DIR__ . '/../includes/pdf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('../index.php?vista=documentos');
}
validarCsrf();

// ---------------------------------------------------------------------------
// 1. Validación de los campos del formulario
// ---------------------------------------------------------------------------
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

// Documento previo (solo en edición), necesario para conservar o reemplazar el archivo.
$anterior = $esEdicion ? obtenerDocumento($conexion, $id) : null;
if ($esEdicion && !$anterior) {
    flash('danger', 'El documento que intentas editar ya no existe.');
    redirigir('../index.php?vista=documentos');
}

// ---------------------------------------------------------------------------
// 2. Procesamiento del archivo digitalizado
// ---------------------------------------------------------------------------
$archivoNombre = $anterior['archivo_nombre'] ?? null;
$archivoRuta   = $anterior['archivo_pdf']    ?? null;
$archivoTam    = (int) ($anterior['archivo_tam'] ?? 0);
$githubUrl     = $anterior['github_url']     ?? null;
$avisoGithub   = '';

// Se acepta el archivo de cualquiera de los dos inputs (cámara o selector).
$subido = null;
foreach (['archivo_pdf', 'archivo_camara'] as $campo) {
    if (isset($_FILES[$campo]) && $_FILES[$campo]['error'] === UPLOAD_ERR_OK) {
        $subido = $_FILES[$campo];
        break;
    }
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
        flash('danger', 'No fue posible guardar el archivo en el servidor. Revisa los permisos de la carpeta pdf/.');
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

    // ----- Respaldo versionado en GitHub -----
    if (GH_ACTIVO) {
        $respaldo = githubSubirArchivo(
            $rutaDestino,
            $nuevoNombre,
            'Documento ' . $no_oficio . ' (' . $fecha . ') — respaldo automático desde la aplicación'
        );
        if ($respaldo['ok']) {
            $githubUrl   = $respaldo['url'];
            $avisoGithub = ' Respaldo creado en GitHub.';
        } else {
            $avisoGithub = ' (El respaldo en GitHub no se completó: ' . e($respaldo['detalle']) . ')';
        }
    }
}

// ---------------------------------------------------------------------------
// 3. Escritura en la base de datos
// ---------------------------------------------------------------------------
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
