<?php
// Baja de un documento. El PDF vive en la misma fila, así que desaparece con
// ella; la bitácora se borra en cascada.

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/consultas.php';

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

$stmt = $conexion->prepare('DELETE FROM documentos WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

flash('success', 'El documento <strong>' . e($doc['no_oficio']) . '</strong> se eliminó correctamente.');
redirigir('../index.php?vista=documentos');
