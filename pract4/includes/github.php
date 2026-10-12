<?php
// Respaldo de los PDF en el repositorio mediante la Contents API de GitHub.
// Solo se activa si existe la variable de entorno GITHUB_TOKEN.

require_once __DIR__ . '/../config.php';

function githubPeticion(string $metodo, string $ruta, ?array $datos = null): array
{
    $ch = curl_init('https://api.github.com' . $ruta);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . GH_TOKEN,
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
            'User-Agent: UNACH-GestionDocumentos',
            'Content-Type: application/json',
        ],
    ]);

    if ($datos !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($datos));
    }

    $respuesta = curl_exec($ch);
    $error     = curl_error($ch) ?: null;
    $codigo    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'codigo' => $codigo,
        'cuerpo' => $respuesta ? json_decode($respuesta, true) : null,
        'error'  => $error,
    ];
}

function githubSubirArchivo(string $rutaLocal, string $nombre, string $mensaje): array
{
    if (!GH_ACTIVO) {
        return ['ok' => false, 'url' => null, 'detalle' => 'Respaldo en GitHub desactivado (falta GITHUB_TOKEN).'];
    }
    if (!is_readable($rutaLocal)) {
        return ['ok' => false, 'url' => null, 'detalle' => 'No se pudo leer el archivo local.'];
    }

    $rutaRepo = trim(GH_RUTA, '/') . '/' . $nombre;
    $endpoint = '/repos/' . GH_REPO . '/contents/' . rutaCodificada($rutaRepo);

    // Para reemplazar un archivo existente hay que mandar su sha.
    $existente = githubPeticion('GET', $endpoint . '?ref=' . urlencode(GH_BRANCH));
    $sha = ($existente['codigo'] === 200 && isset($existente['cuerpo']['sha']))
        ? $existente['cuerpo']['sha']
        : null;

    $cuerpo = [
        'message' => $mensaje,
        'content' => base64_encode(file_get_contents($rutaLocal)),
        'branch'  => GH_BRANCH,
    ];
    if ($sha) $cuerpo['sha'] = $sha;

    $resultado = githubPeticion('PUT', $endpoint, $cuerpo);

    if (in_array($resultado['codigo'], [200, 201], true)) {
        return [
            'ok'      => true,
            'url'     => $resultado['cuerpo']['content']['html_url'] ?? null,
            'detalle' => 'Respaldo creado en GitHub.',
        ];
    }

    $mensajeApi = $resultado['cuerpo']['message'] ?? $resultado['error'] ?? 'Error desconocido';
    return [
        'ok'      => false,
        'url'     => null,
        'detalle' => 'GitHub respondió ' . $resultado['codigo'] . ': ' . $mensajeApi,
    ];
}

function githubEliminarArchivo(string $nombre, string $mensaje): bool
{
    if (!GH_ACTIVO) return false;

    $rutaRepo = trim(GH_RUTA, '/') . '/' . $nombre;
    $endpoint = '/repos/' . GH_REPO . '/contents/' . rutaCodificada($rutaRepo);

    $existente = githubPeticion('GET', $endpoint . '?ref=' . urlencode(GH_BRANCH));
    if ($existente['codigo'] !== 200 || empty($existente['cuerpo']['sha'])) return false;

    $resultado = githubPeticion('DELETE', $endpoint, [
        'message' => $mensaje,
        'sha'     => $existente['cuerpo']['sha'],
        'branch'  => GH_BRANCH,
    ]);

    return $resultado['codigo'] === 200;
}

// Se usa cuando el archivo ya no está en el servidor y hay que traerlo del respaldo.
function githubDescargarArchivo(string $nombre): ?string
{
    if (!GH_ACTIVO) return null;

    $rutaRepo = trim(GH_RUTA, '/') . '/' . $nombre;
    $endpoint = '/repos/' . GH_REPO . '/contents/' . rutaCodificada($rutaRepo)
              . '?ref=' . urlencode(GH_BRANCH);

    $resultado = githubPeticion('GET', $endpoint);
    if ($resultado['codigo'] !== 200 || empty($resultado['cuerpo']['content'])) return null;

    $binario = base64_decode(str_replace("\n", '', $resultado['cuerpo']['content']), true);
    return $binario !== false ? $binario : null;
}

// Codifica cada segmento de la ruta sin escapar las diagonales.
function rutaCodificada(string $ruta): string
{
    return implode('/', array_map('rawurlencode', explode('/', $ruta)));
}

function githubEstado(): array
{
    if (!GH_ACTIVO) {
        return ['ok' => false, 'detalle' => 'Sin configurar (no existe la variable GITHUB_TOKEN).'];
    }
    $resultado = githubPeticion('GET', '/repos/' . GH_REPO);
    if ($resultado['codigo'] === 200) {
        return ['ok' => true, 'detalle' => 'Conectado a ' . GH_REPO . ' (rama ' . GH_BRANCH . ').'];
    }
    return [
        'ok'      => false,
        'detalle' => 'No se pudo acceder al repositorio: ' . ($resultado['cuerpo']['message'] ?? 'error ' . $resultado['codigo']),
    ];
}
