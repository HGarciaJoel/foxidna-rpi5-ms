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
 *
 * Caché:
 *   Los resultados de FFprobe se almacenan en:
 *
 *   /var/cache/foxidna/metadata/
 *
 *   La caché se invalida automáticamente cuando cambia
 *   cualquiera de las playlists HLS asociadas al vídeo.
 */

require_once __DIR__ . '/lib/catalog.php';
require_once __DIR__ . '/lib/metadata_cache.php';

header('Content-Type: application/json; charset=utf-8');


/*
 * ------------------------------------------------------------
 * Configuración de caché
 * ------------------------------------------------------------
 */

$cacheDirectory = '/var/cache/foxidna/metadata';


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
 * Rutas de las fuentes HLS
 * ------------------------------------------------------------
 */

$videoDirectory =
    $hlsDirectory .
    DIRECTORY_SEPARATOR .
    $video['id'];

$sourceFiles = [
    'master' =>
        $videoDirectory .
        DIRECTORY_SEPARATOR .
        'master.m3u8',

    'v0' =>
        $videoDirectory .
        DIRECTORY_SEPARATOR .
        'v0' .
        DIRECTORY_SEPARATOR .
        'prog_index.m3u8',

    'v1' =>
        $videoDirectory .
        DIRECTORY_SEPARATOR .
        'v1' .
        DIRECTORY_SEPARATOR .
        'prog_index.m3u8',

    'v2' =>
        $videoDirectory .
        DIRECTORY_SEPARATOR .
        'v2' .
        DIRECTORY_SEPARATOR .
        'prog_index.m3u8',
];


/*
 * ------------------------------------------------------------
 * Firma de la caché
 * ------------------------------------------------------------
 */

$signature = buildMetadataCacheSignature(
    $sourceFiles
);


/*
 * ------------------------------------------------------------
 * Archivo de caché
 *
 * El nombre se basa en un hash del ID para evitar problemas
 * con caracteres especiales en los nombres de archivo.
 * ------------------------------------------------------------
 */

$cacheFile =
    $cacheDirectory .
    DIRECTORY_SEPARATOR .
    hash('sha256', $video['id']) .
    '.json';


/*
 * ------------------------------------------------------------
 * Intentar recuperar caché
 * ------------------------------------------------------------
 */

$cachedMetadata = loadMetadataCache(
    $cacheFile,
    $signature
);

if ($cachedMetadata !== null) {

    header(
        'X-Foxidna-Metadata-Cache: HIT'
    );

    echo json_encode(
        $cachedMetadata,
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*
 * No existe una caché válida.
 * FFprobe tendrá que ejecutarse.
 */

header(
    'X-Foxidna-Metadata-Cache: MISS'
);


/*
 * ------------------------------------------------------------
 * Leer master.m3u8
 * ------------------------------------------------------------
 */

$masterPath = $sourceFiles['master'];

$masterContent =
    file_get_contents($masterPath);

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

    if (str_starts_with(
        $line,
        '#EXT-X-STREAM-INF:'
    )) {

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

    if (
        $currentAttributes !== null &&
        !str_starts_with($line, '#')
    ) {

        $variant =
            explode('/', $line, 2)[0];

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

$duration = null;

foreach ($video['qualities'] as $variant => $quality) {

    if (!$quality['available']) {

        $qualities[$variant] = [
            'label' => $quality['label'],
            'available' => false,
        ];

        continue;
    }

    $playlistPath =
        $videoDirectory .
        DIRECTORY_SEPARATOR .
        $variant .
        DIRECTORY_SEPARATOR .
        'prog_index.m3u8';

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
            'error' =>
                'FFprobe no pudo analizar la variante.',
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
            'error' =>
                'Respuesta JSON inválida de FFprobe.',
        ];

        continue;
    }

    $stream =
        $probeData['streams'][0] ?? null;

    if (!is_array($stream)) {

        $qualities[$variant] = [
            'label' => $quality['label'],
            'available' => false,
            'error' =>
                'No se encontró stream de vídeo.',
        ];

        continue;
    }

    if (
        $duration === null &&
        isset($probeData['format']['duration'])
    ) {

        $duration =
            (float) $probeData['format']['duration'];
    }

    $averageBitrate =
        $masterVariants[$variant]['average_bandwidth']
        ?? null;

    $peakBitrate =
        $masterVariants[$variant]['bandwidth']
        ?? null;

    $qualities[$variant] = [

        'label' => $quality['label'],

        'available' => true,

        'width' =>
            isset($stream['width'])
            ? (int) $stream['width']
            : null,

        'height' =>
            isset($stream['height'])
            ? (int) $stream['height']
            : null,

        'codec' =>
            $stream['codec_name']
            ?? null,

        'bitrate' =>
            $averageBitrate,

        'peak_bitrate' =>
            $peakBitrate,
    ];
}


/*
 * ------------------------------------------------------------
 * Construir respuesta
 * ------------------------------------------------------------
 */

$metadata = [

    'id' => $video['id'],

    'title' => $video['title'],

    'duration' => $duration,

    'qualities' => $qualities,
];


/*
 * ------------------------------------------------------------
 * Guardar caché
 * ------------------------------------------------------------
 */

saveMetadataCache(
    $cacheFile,
    $signature,
    $metadata
);


/*
 * ------------------------------------------------------------
 * Respuesta JSON
 * ------------------------------------------------------------
 */

echo json_encode(
    $metadata,
    JSON_PRETTY_PRINT
    | JSON_UNESCAPED_SLASHES
    | JSON_UNESCAPED_UNICODE
);
