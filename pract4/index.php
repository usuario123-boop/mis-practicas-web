<?php
// Enrutador: lee ?vista= y carga la pantalla correspondiente dentro del mismo diseño.

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/consultas.php';

// Lista blanca de vistas: evita que se pueda incluir cualquier archivo desde la URL.
$vistasDisponibles = [
    'inicio'        => 'inicio.php',
    'documentos'    => 'documentos.php',
    'nuevo'         => 'formulario.php',
    'editar'        => 'formulario.php',
    'buscar'        => 'buscar.php',
    'reportes'      => 'reportes.php',
    'usuarios'      => 'usuarios.php',
    'configuracion' => 'configuracion.php',
];

$vista = $_GET['vista'] ?? 'inicio';
if (!isset($vistasDisponibles[$vista])) {
    $vista = 'inicio';
}

// Pendientes para el contador de la campana.
$pendientes = (int) $conexion->query("SELECT COUNT(*) AS t FROM documentos WHERE estatus = 'Recibido'")
                             ->fetch_assoc()['t'];

require __DIR__ . '/includes/header.php';
require __DIR__ . '/vistas/' . $vistasDisponibles[$vista];
require __DIR__ . '/includes/footer.php';
