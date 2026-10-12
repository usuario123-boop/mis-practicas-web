<?php
/**
 * acciones/eliminar.php
 * Elimina un documento: su registro en MySQL, su archivo local y su respaldo
 * en GitHub. La bitácora asociada se borra en cascada (ON DELETE CASCADE).
 */

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

// 1. Archivo local
if (!empty($doc['archivo_nombre'])) {
    $ruta = DIR_PDF . DIRECTORY_SEPARATOR . $doc['archivo_nombre'];
    if (is_file($ruta)) unlink($ruta);
}

// 2. Respaldo en GitHub (si existe y el token está configurado)
if (GH_ACTIVO && !empty($doc['archivo_nombre'])) {
    githubEliminarArchivo(
        $doc['archivo_nombre'],
        'Baja del documento ' . $doc['no_oficio'] . ' desde la aplicación'
    );
}

// 3. Registro en la base de datos
$stmt = $conexion->prepare('DELETE FROM documentos WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

flash('success', 'El documento <strong>' . e($doc['no_oficio']) . '</strong> se eliminó correctamente.');
redirigir('../index.php?vista=documentos');
