<?php
/**
 * descargar.php
 * Descarga el PDF de un documento.
 * Reutiliza ver_pdf.php cambiando únicamente la cabecera Content-Disposition.
 */

$_GET['descargar'] = '1';
require __DIR__ . '/ver_pdf.php';
