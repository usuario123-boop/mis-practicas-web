<?php
// Diagnóstico temporal: informa qué variables de entorno está recibiendo la
// aplicación, sin revelar la contraseña. Se elimina en cuanto el despliegue
// quede funcionando.

require_once __DIR__ . '/config.php';

if (($_GET['clave'] ?? '') !== 'unach2026') {
    http_response_code(404);
    exit('404');
}

header('Content-Type: text/plain; charset=utf-8');

echo "Entorno: " . (EN_VERCEL ? 'Vercel' : 'local') . "\n";
echo str_repeat('-', 56) . "\n";

foreach (['DB_HOST', 'DB_PORT', 'DB_USER', 'DB_PASS', 'DB_NAME'] as $v) {
    $valor = env($v);
    if ($valor === null) {
        printf("%-8s NO DEFINIDA\n", $v);
        continue;
    }
    // Se marcan los espacios al inicio o final, que rompen la conexión sin avisar.
    $limpio = trim((string) $valor);
    $aviso  = $limpio !== (string) $valor ? '  <-- TIENE ESPACIOS SOBRANTES' : '';
    $visible = $v === 'DB_PASS'
        ? '(' . strlen($valor) . ' caracteres)'
        : $valor;
    printf("%-8s %s%s\n", $v, $visible, $aviso);
}

echo str_repeat('-', 56) . "\n";
echo "Todas las variables que empiezan con DB_ o GITHUB_:\n";
$nombres = array_filter(array_keys($_ENV + $_SERVER),
    fn($k) => str_starts_with($k, 'DB_') || str_starts_with($k, 'GITHUB_'));
sort($nombres);
echo $nombres ? '  ' . implode("\n  ", $nombres) . "\n" : "  (ninguna)\n";
