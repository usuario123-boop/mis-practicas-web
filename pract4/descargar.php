<?php
// Descarga el PDF. Reutiliza ver_pdf.php cambiando solo el Content-Disposition.

$_GET['descargar'] = '1';
require __DIR__ . '/ver_pdf.php';
