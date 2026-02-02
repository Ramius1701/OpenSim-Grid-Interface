<?php
// FILE: maps/map-tile.php
// Proxy OpenSim map tiles (map-<zoom>-<x>-<y>-objects.jpg) through PHP so the map UI
// can load tiles without CORS/mixed-content issues.
//
// Optional Apache env override:
//   SetEnv OSMAP_TILE_SOURCE "http://127.0.0.1:8002"
// You can also provide multiple sources comma-separated to try in order.
//
// Debug mode:
//   /maps/map-tile.php?debug=1&x=1000&y=1000&z=1

declare(strict_types=1);

// Inputs
$x = isset($_GET['x']) ? (int)$_GET['x'] : 1000;
$y = isset($_GET['y']) ? (int)$_GET['y'] : 1000;
$z = isset($_GET['z']) ? (int)$_GET['z'] : 1; // OpenSim commonly uses 1 for objects tiles
$debug = isset($_GET['debug']) && (string)$_GET['debug'] !== '0';

if ($z < 1) $z = 1;
if ($z > 8) $z = 8;

// Build sources (try localhost first to avoid hairpin NAT issues)
$sources = [];

// 1) Explicit env var
$env = getenv('OSMAP_TILE_SOURCE');
if ($env) {
    foreach (preg_split('/\s*,\s*/', $env) as $s) {
        $s = trim($s);
        if ($s !== '') $sources[] = $s;
    }
}

// 2) Sensible defaults
if (!$sources) {
    $sources = [
        'http://127.0.0.1:8002',
        'http://127.0.0.1:8001',
        'http://127.0.0.1:8003',
        'http://127.0.0.1:9002',
        'http://localhost:8002',
    ];

    // 3) Try GRID_URI as a last resort if available
    $cfg = __DIR__ . '/../include/config.php';
    if (file_exists($cfg)) {
        // Avoid side effects: config.php is safe in Casperia baseline.
        require_once $cfg;
        if (defined('GRID_URI')) {
            $grid = (string)GRID_URI;
            // If GRID_URI already has a scheme, keep it; else try both http/https
            if (preg_match('#^https?://#i', $grid)) {
                $sources[] = $grid;
            } else {
                $sources[] = 'http://' . $grid;
                $sources[] = 'https://' . $grid;
            }
        }
    }
}

$attempts = [];

/**
 * Fetch a URL and return [status, body, contentType, err]
 */
function fetch_url(string $url): array {
    $status = 0;
    $body = '';
    $ctype = '';
    $err = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'CasperiaMapProxy/1.0',
            CURLOPT_HEADER => true,
        ]);
        $resp = curl_exec($ch);
        if ($resp === false) {
            $err = curl_error($ch) ?: 'curl_exec failed';
            curl_close($ch);
            return [$status, $body, $ctype, $err];
        }
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($resp, 0, $headerSize);
        $body = substr($resp, $headerSize);
        $ctype = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        // If content-type missing, parse from headers
        if (!$ctype && preg_match('/^content-type:\s*([^\r\n]+)/im', $headers, $m)) {
            $ctype = trim($m[1]);
        }
        curl_close($ch);
        return [$status, $body, $ctype, $err];
    }

    // file_get_contents fallback
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 4,
            'follow_location' => 1,
            'header' => "User-Agent: CasperiaMapProxy/1.0\r\n",
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);

    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        $err = 'file_get_contents failed';
        return [$status, '', '', $err];
    }

    // Try to extract status + content-type from $http_response_header
    global $http_response_header;
    if (is_array($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('#^HTTP/\d+\.\d+\s+(\d+)#', $h, $m)) $status = (int)$m[1];
            if (stripos($h, 'Content-Type:') === 0) $ctype = trim(substr($h, 13));
        }
    }
    if ($status === 0) $status = 200;
    return [$status, $body, $ctype, $err];
}

foreach ($sources as $src) {
    $src = rtrim($src, '/');
    $url = $src . "/map-{$z}-{$x}-{$y}-objects.jpg";

    [$status, $body, $ctype, $err] = fetch_url($url);

    $attempts[] = [
        'url' => $url,
        'status' => $status,
        'bytes' => strlen($body),
        'contentType' => $ctype,
        'error' => $err,
    ];

    $isImage = ($ctype && stripos($ctype, 'image/') === 0);

    if ($status === 200 && strlen($body) > 0 && ($isImage || preg_match('/^\xFF\xD8/', $body) || preg_match('/^\x89PNG/', $body))) {
        // Serve tile
        header('Content-Type: ' . ($isImage ? $ctype : 'image/jpeg'));
        header('Cache-Control: public, max-age=86400');
        echo $body;
        exit;
    }
}

// Debug response: show attempted URLs + statuses
if ($debug) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'x' => $x,
        'y' => $y,
        'z' => $z,
        'sourcesTried' => $sources,
        'attempts' => $attempts,
        'hint' => 'If all attempts are non-200, set Apache env OSMAP_TILE_SOURCE to your Robust map tile base URL (e.g. http://127.0.0.1:8002).',
    ], JSON_PRETTY_PRINT);
    exit;
}

// Otherwise: return a transparent 1x1 PNG so Leaflet doesn’t spam broken images
header('Content-Type: image/png');
header('Cache-Control: public, max-age=300');
echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
