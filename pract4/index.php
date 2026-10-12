<?php
/**
 * index.php
 * Punto de entrada único de la aplicación.
 *
 * Funciona como enrutador: lee el parámetro ?vista= y carga la pantalla
 * correspondiente de la carpeta vistas/, siempre dentro del mismo diseño
 * (cabecera + menú + pie), de modo que la navegación se sienta como una sola
 * aplicación responsiva.
 *
 * UNACH · Escuela de Tecnologías Digitales Aplicadas · Campus IV Tapachula
 */

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/consultas.php';

/** Vistas disponibles: clave de la URL => archivo dentro de vistas/ */
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

// Contador de documentos pendientes: se muestra en la campana de la barra superior.
$pendientes = (int) $conexion->query("SELECT COUNT(*) AS t FROM documentos WHERE estatus = 'Recibido'")
                             ->fetch_assoc()['t'];

require __DIR__ . '/includes/header.php';
require __DIR__ . '/vistas/' . $vistasDisponibles[$vista];
require __DIR__ . '/includes/footer.php';
