<?php
/**
 * includes/github.php
 * Respaldo de los documentos PDF en el repositorio de GitHub.
 *
 * Requisito del laboratorio: además de guardar el PDF en el servidor, el archivo
 * se envía al repositorio usando la API REST de GitHub (Contents API), de modo
 * que quede versionado junto con el código.
 *
 * Se activa únicamente cuando existe la variable de entorno GITHUB_TOKEN.
 * Si no está configurada, la aplicación sigue funcionando con el almacenamiento
 * local y simplemente no se realiza el respaldo.
 */

require_once __DIR__ . '/../config.php';

/**
 * Ejecuta una petición contra la API de GitHub.
 *
 * @return array{codigo:int, cuerpo:array|null, error:string|null}
 */
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

/**
 * Sube (o actualiza) un archivo en el repositorio configurado.
 *
 * @param string $rutaLocal  Archivo que se va a enviar.
 * @param string $nombre     Nombre con el que se guardará dentro de GITHUB_PATH.
 * @param string $mensaje    Mensaje del commit.
 * @return array{ok:bool, url:?string, detalle:string}
 */
function githubSubirArchivo(string $rutaLocal, string $nombre, string $mensaje): array
{
    if (!GH_ACTIVO) {
        return ['ok' => false, 'url' => null, 'detalle' => 'Respaldo en GitHub desactivado (falta GITHUB_TOKEN).'];
    }
    if (!is_readable($rutaLocal)) {
        return ['ok' => false, 'url' => null, 'detalle' => 'No se pudo leer el archivo local.'];
    }

    $rutaRepo = trim(GH_RUTA, '/') . '/' . $nombre;
    $endpoint = '/repos/' . GH_REPO . '/contents/' . rawurlencode_ruta($rutaRepo);

    // Si el archivo ya existe se necesita su SHA para poder reemplazarlo.
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

/** Elimina un archivo del repositorio (se usa al borrar un documento). */
function githubEliminarArchivo(string $nombre, string $mensaje): bool
{
    if (!GH_ACTIVO) return false;

    $rutaRepo = trim(GH_RUTA, '/') . '/' . $nombre;
    $endpoint = '/repos/' . GH_REPO . '/contents/' . rawurlencode_ruta($rutaRepo);

    $existente = githubPeticion('GET', $endpoint . '?ref=' . urlencode(GH_BRANCH));
    if ($existente['codigo'] !== 200 || empty($existente['cuerpo']['sha'])) return false;

    $resultado = githubPeticion('DELETE', $endpoint, [
        'message' => $mensaje,
        'sha'     => $existente['cuerpo']['sha'],
        'branch'  => GH_BRANCH,
    ]);

    return $resultado['codigo'] === 200;
}

/**
 * Descarga el contenido binario de un archivo respaldado en GitHub.
 * Sirve como respaldo cuando el servidor no conserva los archivos subidos
 * (por ejemplo, el sistema de archivos efímero de Vercel).
 */
function githubDescargarArchivo(string $nombre): ?string
{
    if (!GH_ACTIVO) return null;

    $rutaRepo = trim(GH_RUTA, '/') . '/' . $nombre;
    $endpoint = '/repos/' . GH_REPO . '/contents/' . rawurlencode_ruta($rutaRepo)
              . '?ref=' . urlencode(GH_BRANCH);

    $resultado = githubPeticion('GET', $endpoint);
    if ($resultado['codigo'] !== 200 || empty($resultado['cuerpo']['content'])) return null;

    $binario = base64_decode(str_replace("\n", '', $resultado['cuerpo']['content']), true);
    return $binario !== false ? $binario : null;
}

/** Codifica cada segmento de una ruta sin escapar las diagonales. */
function rawurlencode_ruta(string $ruta): string
{
    return implode('/', array_map('rawurlencode', explode('/', $ruta)));
}

/** Comprueba que el token y el repositorio configurados sean accesibles. */
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
