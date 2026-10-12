<?php
// Cambio de estatus desde el listado. Responde JSON porque se llama por AJAX.

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/consultas.php';

header('Content-Type: application/json; charset=utf-8');

function responder(array $datos): void
{
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    responder(['ok' => false, 'mensaje' => 'Método no permitido.']);
}

if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(403);
    responder(['ok' => false, 'mensaje' => 'Sesión expirada. Recarga la página.']);
}

$id      = (int) ($_POST['id'] ?? 0);
$estatus = $_POST['estatus'] ?? '';

if (!in_array($estatus, estatusValidos(), true)) {
    responder(['ok' => false, 'mensaje' => 'Estatus no válido.']);
}

$doc = obtenerDocumento($conexion, $id);
if (!$doc) {
    responder(['ok' => false, 'mensaje' => 'El documento no existe.']);
}

if ($doc['estatus'] === $estatus) {
    responder(['ok' => true, 'estatus' => $estatus, 'clase' => claseEstatus($estatus)]);
}

$stmt = $conexion->prepare('UPDATE documentos SET estatus = ? WHERE id = ?');
$stmt->bind_param('si', $estatus, $id);
$stmt->execute();
$stmt->close();

registrarBitacora($conexion, $id, 'Cambio de estatus',
    'De "' . $doc['estatus'] . '" a "' . $estatus . '".');

responder([
    'ok'      => true,
    'estatus' => $estatus,
    'clase'   => claseEstatus($estatus),
]);
