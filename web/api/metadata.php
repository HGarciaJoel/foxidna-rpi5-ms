<?php

declare(strict_types=1);

/*
 * foxidna - Video Metadata Endpoint
 *
 * Uso:
 *   /api/metadata.php?id=HowToVoidVessel
 *
 * Responsabilidad:
 *   Obtener metadatos técnicos de un vídeo HLS concreto.
 */

require_once __DIR__ . '/lib/catalog.php';

header('Content-Type: application/json; charset=utf-8');

/*
 * ------------------------------------------------------------
 * Parámetro ID
 * ------------------------------------------------------------
 */

$id = $_GET['id'] ?? '';

if (!is_string($id) || $id === '') {
    http_response_code(400);

    echo json_encode(
        [
            'error' => 'Falta el parámetro id.'
        ],
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );

    exit;
}

/*
 * ------------------------------------------------------------
 * Obtener catálogo
 * ------------------------------------------------------------
 */

$hlsDirectory = __DIR__ . '/../hls';

$videos = scanCatalog($hlsDirectory);

$video = null;

foreach ($videos as $candidate) {
    if ($candidate['id'] === $id) {
        $video = $candidate;
        break;
    }
}

/*
 * ------------------------------------------------------------
 * Validar vídeo
 * ------------------------------------------------------------
 */

if ($video === null) {
    http_response_code(404);

    echo json_encode(
        [
            'error' => 'Vídeo no encontrado.'
        ],
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );

    exit;
}

/*
 * ------------------------------------------------------------
 * Leer master.m3u8
 *
 * El master contiene BANDWIDTH y AVERAGE-BANDWIDTH
 * para cada variante.
 * ------------------------------------------------------------
 */

$masterPath =
    $hlsDirectory .
    DIRECTORY_SEPARATOR .
    $video['id'] .
    DIRECTORY_SEPARATOR .
    'master.m3u8';

$masterContent = file_get_contents($masterPath);

if ($masterContent === false) {
    http_response_code(500);

    echo json_encode(
        [
            'error' => 'No fue posible leer el master.m3u8.'
        ],
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );

    exit;
}

/*
 * ------------------------------------------------------------
 * Extraer información de las variantes del master
 * ------------------------------------------------------------
 */

$masterVariants = [];

$lines = preg_split(
    '/\r\n|\r|\n/',
    $masterContent
);

$currentAttributes = null;

foreach ($lines as $line) {

    $line = trim($line);

    if ($line === '') {
        continue;
    }

    if (str_starts_with($line, '#EXT-X-STREAM-INF:')) {

        $attributeText =
            substr(
                $line,
                strlen('#EXT-X-STREAM-INF:')
            );

        $currentAttributes = [
            'bandwidth' => null,
            'average_bandwidth' => null,
        ];

        if (
            preg_match(
                '/(?:^|,)BANDWIDTH=(\d+)/',
                $attributeText,
                $match
            )
        ) {
            $currentAttributes['bandwidth'] =
                (int) $match[1];
        }

        if (
            preg_match(
                '/(?:^|,)AVERAGE-BANDWIDTH=(\d+)/',
                $attributeText,
                $match
            )
        ) {
            $currentAttributes['average_bandwidth'] =
                (int) $match[1];
        }

        continue;
    }

    /*
     * La línea posterior a EXT-X-STREAM-INF
     * contiene la URI de la variante.
     */
    if (
        $currentAttributes !== null &&
        !str_starts_with($line, '#')
    ) {

        $variant = explode('/', $line, 2)[0];

        $masterVariants[$variant] =
            $currentAttributes;

        $currentAttributes = null;
    }
}

/*
 * ------------------------------------------------------------
 * Consultar FFprobe
 * ------------------------------------------------------------
 */

$qualities = [];

foreach ($video['qualities'] as $variant => $quality) {

    if (!$quality['available']) {
        $qualities[$variant] = [
            'label' => $quality['label'],
            'available' => false,
        ];

        continue;
    }

    $playlistPath =
        $hlsDirectory .
        DIRECTORY_SEPARATOR .
        $video['id'] .
        DIRECTORY_SEPARATOR .
        $variant .
        DIRECTORY_SEPARATOR .
        'prog_index.m3u8';

    /*
     * Solo solicitamos a FFprobe los campos que realmente
     * necesitamos.
     */
    $command =
        '/usr/bin/ffprobe ' .
        '-v error ' .
        '-select_streams v:0 ' .
        '-show_entries ' .
        'stream=codec_name,width,height:' .
        'format=duration ' .
        '-of json ' .
        escapeshellarg($playlistPath) .
        ' 2>&1';

    $outputLines = [];

    $exitCode = 0;

    exec(
        $command,
        $outputLines,
        $exitCode
    );

    if ($exitCode !== 0) {
        $qualities[$variant] = [
            'label' => $quality['label'],
            'available' => false,
            'error' => 'FFprobe no pudo analizar la variante.',
        ];

        continue;
    }

    $probeData = json_decode(
        implode("\n", $outputLines),
        true
    );

    if (!is_array($probeData)) {
        $qualities[$variant] = [
            'label' => $quality['label'],
            'available' => false,
            'error' => 'Respuesta JSON inválida de FFprobe.',
        ];

        continue;
    }

    /*
     * FFprobe puede devolver el stream dentro de
     * "streams" y también dentro de "programs".
     *
     * Con -select_streams v:0 usamos "streams".
     */
    $stream = $probeData['streams'][0] ?? null;

    if (!is_array($stream)) {
        $qualities[$variant] = [
            'label' => $quality['label'],
            'available' => false,
            'error' => 'No se encontró stream de vídeo.',
        ];

        continue;
    }

    /*
     * Duración global.
     */
    if (
        !isset($duration) &&
        isset($probeData['format']['duration'])
    ) {
        $duration = (float) $probeData['format']['duration'];
    }

    /*
     * Usamos AVERAGE-BANDWIDTH como bitrate principal.
     * BANDWIDTH queda como valor máximo declarado.
     */
    $averageBitrate =
        $masterVariants[$variant]['average_bandwidth']
        ?? null;

    $peakBitrate =
        $masterVariants[$variant]['bandwidth']
        ?? null;

    $qualities[$variant] = [
        'label' => $quality['label'],
        'available' => true,
        'width' => isset($stream['width'])
            ? (int) $stream['width']
            : null,
        'height' => isset($stream['height'])
            ? (int) $stream['height']
            : null,
        'codec' => $stream['codec_name'] ?? null,
        'bitrate' => $averageBitrate,
        'peak_bitrate' => $peakBitrate,
    ];
}

/*
 * ------------------------------------------------------------
 * Respuesta
 * ------------------------------------------------------------
 */

$response = [
    'id' => $video['id'],
    'title' => $video['title'],
    'duration' => $duration ?? null,
    'qualities' => $qualities,
];

echo json_encode(
    $response,
    JSON_PRETTY_PRINT
    | JSON_UNESCAPED_SLASHES
    | JSON_UNESCAPED_UNICODE
);
