<?php

declare(strict_types=1);

/*
 * foxidna - Video Scanner
 *
 * Responsabilidad:
 *   Exponer mediante JSON el catálogo descubierto
 *   por lib/catalog.php.
 */

require_once __DIR__ . '/lib/catalog.php';

$hlsDirectory = __DIR__ . '/../hls';

$videos = scanCatalog($hlsDirectory);

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
