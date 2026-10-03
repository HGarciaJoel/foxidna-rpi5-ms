<?php

declare(strict_types=1);

/*
 * foxidna - Catálogo HLS
 *
 * Responsabilidad:
 *   Descubrir y describir los vídeos disponibles
 *   dentro del directorio HLS.
 *
 * Este archivo NO genera HTML ni JSON.
 * Devuelve estructuras PHP para que otros
 * componentes puedan utilizarlas.
 */


/**
 * Escanea el catálogo de vídeos HLS.
 *
 * Un vídeo válido debe contener:
 *
 *   /hls/<id>/master.m3u8
 *
 * Las variantes esperadas son:
 *
 *   v0 -> 2K
 *   v1 -> 1080p
 *   v2 -> 720p
 *
 * @param string   $hlsDirectory Directorio raíz de HLS.
 * @param int|null $limit        Número máximo de resultados.
 * @param int      $offset       Posición inicial.
 *
 * @return array
 */
function scanCatalog(
    string $hlsDirectory,
    ?int $limit = null,
    int $offset = 0
): array {

    if (!is_dir($hlsDirectory)) {
        return [];
    }

    if ($offset < 0) {
        $offset = 0;
    }

    if ($limit !== null && $limit < 1) {
        $limit = null;
    }

    $qualities = [
        'v0' => '2K',
        'v1' => '1080p',
        'v2' => '720p',
    ];

    $videos = [];

    $entries = scandir($hlsDirectory);

    if ($entries === false) {
        return [];
    }

    foreach ($entries as $entry) {

        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $videoDirectory =
            $hlsDirectory . DIRECTORY_SEPARATOR . $entry;

        /*
         * Solo se consideran directorios directamente
         * contenidos dentro de /hls/.
         */
        if (!is_dir($videoDirectory)) {
            continue;
        }

        /*
         * master.m3u8 es el requisito principal para
         * considerar válido un vídeo.
         */
        $masterPlaylist =
            $videoDirectory
            . DIRECTORY_SEPARATOR
            . 'master.m3u8';

        if (!is_file($masterPlaylist)) {
            continue;
        }

        $video = [
            'id' => $entry,
            'title' => $entry,
            'hls' => '/hls/' .
                rawurlencode($entry) .
                '/master.m3u8',
            'qualities' => [],
        ];

        foreach ($qualities as $variant => $label) {

            $variantDirectory =
                $videoDirectory .
                DIRECTORY_SEPARATOR .
                $variant;

            $variantPlaylist =
                $variantDirectory .
                DIRECTORY_SEPARATOR .
                'prog_index.m3u8';

            if (
                is_dir($variantDirectory) &&
                is_file($variantPlaylist)
            ) {
                $video['qualities'][$variant] = [
                    'label' => $label,
                    'available' => true,
                    'playlist' => '/hls/' .
                        rawurlencode($entry) .
                        '/' .
                        $variant .
                        '/prog_index.m3u8',
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

    /*
     * Orden natural por ID.
     *
     * Esto permite que:
     * video2
     * video10
     *
     * aparezcan en un orden más intuitivo.
     */
    usort(
        $videos,
        static function (array $a, array $b): int {
            return strnatcasecmp(
                $a['id'],
                $b['id']
            );
        }
    );

    /*
     * limit/offset quedan preparados para futuras
     * interfaces como la barra lateral del reproductor.
     */
    if ($offset > 0 || $limit !== null) {
        $videos = array_slice(
            $videos,
            $offset,
            $limit
        );
    }

    return $videos;
}
