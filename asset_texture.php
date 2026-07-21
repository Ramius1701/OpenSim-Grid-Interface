<?php
// asset_texture.php - OpenSim texture proxy.
//
// Flow:
//   1. id=<uuid> comes in via GET
//   2. If cached PNG exists in data/profile_images/<uuid>.png -> serve it
//   3. Else call the Robust-side TexturePngService (see
//      opensim-enhanced/TexturePngService.ini.example), which decodes the
//      JPEG2000 asset to PNG server-side and returns it directly
//   4. Cache the result locally, serve it
//   5. On any failure, fall back to ASSET_FEHLT
//
// Previously this shelled out to a separately-installed OpenJPEG binary
// (opj_decompress.exe) via exec(). That's gone now - the decode happens on
// Robust using the same managed .NET JPEG2000 decoder OpenSim's own
// GetTexture capability and map tile renderer already use internally.
// Nothing extra to install on the web server.
//
// Requirements:
//   - GRID_ASSETS_SERVER, TEXTURE_PNG_SERVICE_URL, and ASSET_FEHLT defined
//     in include/config.php
//   - TexturePngService enabled on Robust (see
//     opensim-enhanced/TexturePngService.ini.example)

// --- Load config.php ---
$configLoaded = false;
$cfgCandidates = [
    __DIR__ . '/include/config.php',      // asset_texture.php in project root
    __DIR__ . '/../include/config.php',   // asset_texture.php in /osviewer or similar
];

foreach ($cfgCandidates as $cfgPath) {
    if (file_exists($cfgPath)) {
        require_once $cfgPath;
        $configLoaded = true;
        break;
    }
}

if (!$configLoaded) {
    http_response_code(500);
    echo "Configuration not found for asset_texture.php";
    exit;
}

// --- Helper: fallback ---
function asset_texture_fallback(): void {
    if (defined('ASSET_FEHLT') && ASSET_FEHLT) {
        // ASSET_FEHLT may be a data: URI (e.g. an inline SVG) rather than a
        // real URL - browsers block redirecting to data: URIs, so detect
        // that case and output the image directly instead of redirecting.
        if (str_starts_with(ASSET_FEHLT, 'data:')) {
            $withoutScheme = substr(ASSET_FEHLT, 5); // strip "data:"
            $commaPos = strpos($withoutScheme, ',');
            if ($commaPos !== false) {
                $meta    = substr($withoutScheme, 0, $commaPos);   // e.g. "image/svg+xml;utf8"
                $payload = substr($withoutScheme, $commaPos + 1);
                $mime    = strtok($meta, ';') ?: 'image/svg+xml';
                header('Content-Type: ' . $mime);
                echo urldecode($payload);
                exit;
            }
        }
        header('Location: ' . ASSET_FEHLT);
    } else {
        http_response_code(404);
    }
    exit;
}

// --- Validate UUID ---
$uuid = isset($_GET['id']) ? trim((string)$_GET['id']) : '';

if ($uuid === '' || !preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $uuid)) {
    asset_texture_fallback();
}

// --- Ensure the new decode service is configured ---
if (!defined('TEXTURE_PNG_SERVICE_URL') || !TEXTURE_PNG_SERVICE_URL) {
    error_log("[asset_texture] TEXTURE_PNG_SERVICE_URL not defined - check include/config.php and enable TexturePngService on Robust (see opensim-enhanced/TexturePngService.ini.example)");
    asset_texture_fallback();
}

// --- Determine cache directory ---
$cacheDir = __DIR__ . '/data/profile_images';
if (defined('PROFILE_IMAGE_CACHE_DIR') && PROFILE_IMAGE_CACHE_DIR) {
    $cacheDir = PROFILE_IMAGE_CACHE_DIR;
}

if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}

$pngPath = rtrim($cacheDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $uuid . '.png';

// --- If PNG already cached, just serve it ---
if (file_exists($pngPath)) {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400');
    readfile($pngPath);
    exit;
}

// --- Ask Robust to decode this texture to PNG for us ---
$requestUrl = TEXTURE_PNG_SERVICE_URL . '?id=' . urlencode($uuid);

$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'follow_location' => 1,
        'ignore_errors' => true, // so we can inspect non-200 responses below instead of file_get_contents just returning false
    ],
    'ssl' => [
        'verify_peer'      => false,
        'verify_peer_name' => false,
    ],
]);

$pngBytes = @file_get_contents($requestUrl, false, $context);

$statusLine = isset($http_response_header[0]) ? $http_response_header[0] : '';
$isOk = $pngBytes !== false && $pngBytes !== '' && strpos($statusLine, '200') !== false;

if (!$isOk) {
    error_log("[asset_texture] TexturePngService request failed for uuid={$uuid}: {$requestUrl} (status: {$statusLine})");
    asset_texture_fallback();
}

// --- Cache it locally so we don't have to ask Robust again next time ---
if (@file_put_contents($pngPath, $pngBytes) === false) {
    error_log("[asset_texture] Failed to write PNG cache file: {$pngPath} - check directory permissions (serving this one response anyway, just won't be cached)");
}

// --- Serve the PNG ---
header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
echo $pngBytes;
exit;
