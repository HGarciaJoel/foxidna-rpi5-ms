<?php

declare(strict_types=1);

/*
 * foxidna - Metadata Cache
 *
 * Guarda y recupera metadatos generados por FFprobe.
 *
 * La caché queda invalidada cuando cambia cualquiera
 * de los archivos fuente asociados al vídeo.
 */


/**
 * Construye una firma para los archivos fuente.
 *
 * @param array<string, string> $sourceFiles
 *
 * @return string
 */
function buildMetadataCacheSignature(
    array $sourceFiles
): string {

    $state = [];

    foreach ($sourceFiles as $name => $path) {

        if (!is_file($path)) {
            $state[$name] = null;
            continue;
        }

        $stat = stat($path);

        if ($stat === false) {
            $state[$name] = null;
            continue;
        }

        $state[$name] = [
            'size' => $stat['size'],
            'mtime' => $stat['mtime'],
        ];
    }

    ksort($state);

    return hash(
        'sha256',
        json_encode(
            $state,
            JSON_UNESCAPED_SLASHES
        )
    );
}


/**
 * Carga una entrada de caché si su firma coincide.
 *
 * @return array|null
 */
function loadMetadataCache(
    string $cacheFile,
    string $signature
): ?array {

    if (!is_file($cacheFile)) {
        return null;
    }

    $content = file_get_contents($cacheFile);

    if ($content === false) {
        return null;
    }

    $cache = json_decode($content, true);

    if (!is_array($cache)) {
        return null;
    }

    if (
        !isset($cache['signature']) ||
        $cache['signature'] !== $signature
    ) {
        return null;
    }

    if (
        !isset($cache['metadata']) ||
        !is_array($cache['metadata'])
    ) {
        return null;
    }

    return $cache['metadata'];
}


/**
 * Guarda metadatos en caché.
 *
 * Se escribe primero en un archivo temporal y después
 * se renombra para evitar una caché parcialmente escrita.
 *
 * @return bool
 */
function saveMetadataCache(
    string $cacheFile,
    string $signature,
    array $metadata
): bool {

    $cache = [
        'signature' => $signature,
        'generated_at' => date(DATE_ATOM),
        'metadata' => $metadata,
    ];

    $content = json_encode(
        $cache,
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );

    if ($content === false) {
        return false;
    }

    $temporaryFile =
        $cacheFile .
        '.tmp.' .
        getmypid();

    $written = file_put_contents(
        $temporaryFile,
        $content,
        LOCK_EX
    );

    if ($written === false) {
        @unlink($temporaryFile);
        return false;
    }

    @chmod($temporaryFile, 0640);

    if (!rename($temporaryFile, $cacheFile)) {
        @unlink($temporaryFile);
        return false;
    }

    return true;
}
