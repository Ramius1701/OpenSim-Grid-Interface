<?php
declare(strict_types=1);

/**
 * Casperia Prime OpenSim Map Tile Proxy
 * Version: 1.0.1
 *
 * Single-purpose same-origin proxy for the known ROBUST MapGet service.
 * No config includes, no port probing, no redirect following, no fake tiles.
 *
 * Debug:
 *   /maps/map-tile.php?debug=1&x=1000&y=1000&z=1
 */

// Deliberately not sourced from config.php (see file header: this proxy
// intentionally avoids config includes). It's a loopback address to the
// local ROBUST instance, not the grid's public GRID_PORT/BASE_URL.
const MAP_TILE_SOURCE = 'http://127.0.0.1:8002';

$x = filter_input(INPUT_GET, 'x', FILTER_VALIDATE_INT);
$y = filter_input(INPUT_GET, 'y', FILTER_VALIDATE_INT);
$z = filter_input(INPUT_GET, 'z', FILTER_VALIDATE_INT);
$debug = isset($_GET['debug']) && (string) $_GET['debug'] === '1';

if ($x === false || $x === null || $y === false || $y === null) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Map tile coordinates x and y are required.';
    exit;
}

$z = ($z === false || $z === null) ? 1 : max(1, min(8, $z));
$sourceUrl = MAP_TILE_SOURCE . "/map-{$z}-{$x}-{$y}-objects.jpg";

$status = 0;
$contentType = '';
$error = '';
$body = '';
$transport = '';

if (function_exists('curl_init')) {
    $transport = 'curl';
    $ch = curl_init($sourceUrl);

    if ($ch !== false) {
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_USERAGENT => 'CasperiaMapProxy/1.0.1',
        ]);

        $result = curl_exec($ch);

        if ($result === false) {
            $error = curl_error($ch) ?: 'cURL request failed';
        } else {
            $body = $result;
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
    } else {
        $error = 'Unable to initialize cURL';
    }
} else {
    $transport = 'stream';
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
            'ignore_errors' => true,
            'follow_location' => 0,
            'header' => "User-Agent: CasperiaMapProxy/1.0.1\r\n",
        ],
    ]);

    $result = @file_get_contents($sourceUrl, false, $context);
    if ($result === false) {
        $error = 'file_get_contents request failed';
    } else {
        $body = $result;
    }

    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $headerLine) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $headerLine, $match)) {
                $status = (int) $match[1];
            } elseif (stripos($headerLine, 'Content-Type:') === 0) {
                $contentType = trim(substr($headerLine, 13));
            }
        }
    }
}

$isJpeg = strlen($body) >= 3 && substr($body, 0, 3) === "\xFF\xD8\xFF";
$isPng = strlen($body) >= 8 && substr($body, 0, 8) === "\x89PNG\r\n\x1A\n";

if ($debug) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    echo json_encode([
        'success' => $status === 200 && ($isJpeg || $isPng),
        'sourceUrl' => $sourceUrl,
        'transport' => $transport,
        'httpStatus' => $status,
        'sourceContentType' => $contentType,
        'bytes' => strlen($body),
        'first16Hex' => bin2hex(substr($body, 0, 16)),
        'last16Hex' => bin2hex(substr($body, -16)),
        'isJpeg' => $isJpeg,
        'isPng' => $isPng,
        'error' => $error,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($status !== 200) {
    http_response_code($status > 0 ? $status : 502);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo 'OpenSim map tile request failed.';
    exit;
}

if (!$isJpeg && !$isPng) {
    http_response_code(502);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo 'OpenSim returned invalid map tile image data.';
    exit;
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . ($isPng ? 'image/png' : 'image/jpeg'));
header('Content-Length: ' . strlen($body));
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff');

echo $body;
exit;
