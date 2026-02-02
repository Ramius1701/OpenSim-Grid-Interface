<?php
// asset_texture.php - OpenSim texture proxy with JPEG2000 -> PNG conversion using OpenJPEG.
//
// Flow:
//   1. id=<uuid> comes in via GET
//   2. If cached PNG exists in data/profile_images/<uuid>.png -> serve it
//   3. Else fetch XML from GRID_ASSETS_SERVER . uuid
//   4. Extract <Data>, base64_decode to J2K bytes -> save as <uuid>.j2k
//   5. Call opj_decompress (OpenJPEG) to convert J2K -> PNG
//   6. On success serve PNG, on failure redirect to ASSET_FEHLT or 404
//
// Requirements:
//   - GRID_ASSETS_SERVER and ASSET_FEHLT defined in include/config.php
//   - OpenJPEG installed (opj_decompress.exe) and J2K_CONVERTER_PATH pointing to it
//   - PHP exec() enabled
//
// This file is designed for your Windows/Bearsampp stack.

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

// --- Ensure GRID_ASSETS_SERVER defined ---
if (!defined('GRID_ASSETS_SERVER') || !GRID_ASSETS_SERVER) {
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

// Paths for J2K and PNG cache
$j2kPath = rtrim($cacheDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $uuid . '.j2k';
$pngPath = rtrim($cacheDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $uuid . '.png';

// --- If PNG already exists, just serve it ---
if (file_exists($pngPath)) {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400');
    readfile($pngPath);
    exit;
}

// --- If J2K not cached yet, fetch from asset server ---
if (!file_exists($j2kPath)) {
    $upstreamUrl = GRID_ASSETS_SERVER . urlencode($uuid);

    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'follow_location' => 1,
        ],
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ],
    ]);

    $xml = @file_get_contents($upstreamUrl, false, $context);
    if ($xml === false || trim($xml) === '') {
        asset_texture_fallback();
    }

    $assetXml = @simplexml_load_string($xml);
    if (!$assetXml || !isset($assetXml->Data)) {
        asset_texture_fallback();
    }

    $j2kBase64 = (string)$assetXml->Data;
    $j2kBytes   = base64_decode($j2kBase64, true);
    if ($j2kBytes === false || $j2kBytes === '') {
        asset_texture_fallback();
    }

    if (@file_put_contents($j2kPath, $j2kBytes) === false) {
        asset_texture_fallback();
    }
}

// --- Convert J2K -> PNG using OpenJPEG ---
// You must have opj_decompress.exe installed. Configure its path in config.php as:
// define('J2K_CONVERTER_PATH', 'S:/Tools/openjpeg/opj_decompress.exe');

$converterPath = defined('J2K_CONVERTER_PATH') ? J2K_CONVERTER_PATH : 'S:/Tools/openjpeg/opj_decompress.exe';
if (!file_exists($converterPath)) {
    asset_texture_fallback();
}

// Ensure any old PNG is removed before reconverting
if (file_exists($pngPath)) {
    @unlink($pngPath);
}

// Build command. Note: OpenJPEG picks output format from extension (.png here).
$cmd = '"' . $converterPath . '" -i "' . $j2kPath . '" -o "' . $pngPath . '" 2>&1';

$output = [];
$returnVar = 0;
@exec($cmd, $output, $returnVar);

if ($returnVar !== 0 || !file_exists($pngPath)) {
    // Conversion failed; we could log $output here if desired.
    asset_texture_fallback();
}

// --- Serve the PNG ---
header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
readfile($pngPath);
exit;
