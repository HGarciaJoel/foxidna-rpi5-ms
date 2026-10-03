<?php

declare(strict_types=1);

/*
 * foxidna - HLS Video Scanner
 *
 * Escanea:
 *   /var/www/html/hls/
 *
 * Reglas:
 *   1. Solo se revisan los subdirectorios directos de hls/.
 *   2. Un directorio es un video válido si contiene master.m3u8.
 *   3. Se detectan las variantes v0, v1 y v2.
 *   4. El resultado se devuelve como JSON.
 */

// ------------------------------------------------------------
// Configuración
// ------------------------------------------------------------

$hlsDirectory = __DIR__ . '/../hls';

$qualities = [
    'v0' => '2K',
    'v1' => '1080p',
    'v2' => '720p',
];

// ------------------------------------------------------------
// Comprobación del directorio HLS
// ------------------------------------------------------------

if (!is_dir($hlsDirectory)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        [
            'error' => 'No se encontró el directorio HLS.'
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    exit;
}

// ------------------------------------------------------------
// Escaneo de videos
// ------------------------------------------------------------

$videos = [];

$entries = scandir($hlsDirectory);

if ($entries === false) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        [
            'error' => 'No fue posible leer el directorio HLS.'
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    exit;
}

foreach ($entries as $entry) {

    // Ignorar "." y ".."
    if ($entry === '.' || $entry === '..') {
        continue;
    }

    $videoDirectory = $hlsDirectory . DIRECTORY_SEPARATOR . $entry;

    // Solo nos interesan directorios directos de /hls/
    if (!is_dir($videoDirectory)) {
        continue;
    }

    // --------------------------------------------------------
    // Regla principal:
    // debe existir /hls/<video>/master.m3u8
    // --------------------------------------------------------

    $masterPlaylist = $videoDirectory . DIRECTORY_SEPARATOR . 'master.m3u8';

    if (!is_file($masterPlaylist)) {
        continue;
    }

    // --------------------------------------------------------
    // Información básica del video
    // --------------------------------------------------------

    $video = [
        'id' => $entry,
        'title' => $entry,
        'hls' => '/hls/' . rawurlencode($entry) . '/master.m3u8',
        'qualities' => [],
    ];

    // --------------------------------------------------------
    // Detección de v0, v1 y v2
    // --------------------------------------------------------

    foreach ($qualities as $variant => $label) {

        $variantDirectory = $videoDirectory
            . DIRECTORY_SEPARATOR
            . $variant;

        // Playlist utilizada actualmente por nuestras variantes
        $variantPlaylist = $variantDirectory
            . DIRECTORY_SEPARATOR
            . 'prog_index.m3u8';

        if (
            is_dir($variantDirectory) &&
            is_file($variantPlaylist)
        ) {
            $video['qualities'][$variant] = [
                'label' => $label,
                'available' => true,
                'playlist' => '/hls/'
                    . rawurlencode($entry)
                    . '/'
                    . $variant
                    . '/prog_index.m3u8',
            ];
        } else {
            $video['qualities'][$variant] = [
                'label' => $label,
                'available' => false,
                'playlist' => null,
            ];
        }
    }

    $videos[] = $video;
}

// ------------------------------------------------------------
// Orden alfabético por ID
// ------------------------------------------------------------

usort(
    $videos,
    static function (array $a, array $b): int {
        return strnatcasecmp($a['id'], $b['id']);
    }
);

// ------------------------------------------------------------
// Respuesta JSON
// ------------------------------------------------------------

header('Content-Type: application/json; charset=utf-8');

echo json_encode(
    [
        'count' => count($videos),
        'videos' => $videos,
    ],
    JSON_PRETTY_PRINT
    | JSON_UNESCAPED_SLASHES
    | JSON_UNESCAPED_UNICODE
);
