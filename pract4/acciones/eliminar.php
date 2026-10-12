<?php
// Baja de un documento: registro, archivo local y respaldo en GitHub.
// La bitácora se borra en cascada.

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/consultas.php';
require_once __DIR__ . '/../includes/github.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('../index.php?vista=documentos');
}
validarCsrf();

$id  = (int) ($_POST['id'] ?? 0);
$doc = obtenerDocumento($conexion, $id);

if (!$doc) {
    flash('danger', 'El documento ya no existe.');
    redirigir('../index.php?vista=documentos');
}

if (!empty($doc['archivo_nombre'])) {
    $ruta = DIR_PDF . DIRECTORY_SEPARATOR . $doc['archivo_nombre'];
    if (is_file($ruta)) unlink($ruta);
}

if (GH_ACTIVO && !empty($doc['archivo_nombre'])) {
    githubEliminarArchivo(
        $doc['archivo_nombre'],
        'Baja del documento ' . $doc['no_oficio'] . ' desde la aplicación'
    );
}

$stmt = $conexion->prepare('DELETE FROM documentos WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

flash('success', 'El documento <strong>' . e($doc['no_oficio']) . '</strong> se eliminó correctamente.');
redirigir('../index.php?vista=documentos');
