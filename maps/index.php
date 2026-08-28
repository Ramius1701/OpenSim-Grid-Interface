<?php
declare(strict_types=1);

/**
 * Casperia Prime World Map
 * Version: 1.0.0
 *
 * Site-integrated Leaflet map.
 * Runtime files:
 *   - map-data.php
 *   - map-tile.php
 *   - map-script.js
 *   - map-style.css
 */

$siteRoot = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
$configFile = $siteRoot . '/include/config.php';

if (is_file($configFile)) {
    require_once $configFile;
}

$gridName = defined('SITE_NAME') ? (string) SITE_NAME : 'Casperia Prime';

$scriptName = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '/maps/index.php';
$mapsUrl = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
if ($mapsUrl === '' || $mapsUrl === '.') {
    $mapsUrl = '/maps';
}

$headerFile = $siteRoot . '/include/header.php';
$footerFile = $siteRoot . '/include/footer.php';

$leafletCss = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
$leafletJs  = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
$mapCss     = $mapsUrl . '/map-style.css?v=1.0.0';
$mapJs      = $mapsUrl . '/map-script.js?v=1.0.0';
$apiUrl     = $mapsUrl . '/map-data.php';
$tileUrl    = $mapsUrl . '/map-tile.php';

function cpInjectBefore(string $html, string $needle, string $insert): string
{
    $pos = stripos($html, $needle);
    if ($pos === false) {
        return $html . $insert;
    }

    return substr($html, 0, $pos) . $insert . substr($html, $pos);
}

$mapBody = sprintf(
    '<main class="cp-map-page" aria-label="%s World Map">
        <div class="cp-map-toolbar">
            <div class="cp-map-title">
                <h1 class="cp-map-h1">World Map</h1>
                <div class="cp-map-sub">
                    Regions: <span id="headerRegionCount">—</span>
                    <span class="cp-map-dot">•</span>
                    Users online: <span id="statOnlineNow">—</span>
                </div>
            </div>
            <div class="cp-map-actions">
                <button type="button" class="cp-map-btn" id="cpMapResetBtn">Reset View</button>
            </div>
        </div>

        <div class="cp-map-search">
            <input id="searchInput" class="cp-map-input" type="text" placeholder="Search regions or coordinates (1000,1000)..." autocomplete="off">
            <button id="searchBtn" class="cp-map-btn" type="button">Search</button>
            <button id="clearSearch" class="cp-map-btn cp-map-btn-ghost d-none" type="button">Clear</button>
        </div>

        <div id="searchResults" class="cp-map-results" hidden></div>

        <div class="cp-map-canvas" id="cpMapCanvas">
            <div id="map" data-api-url="%s" data-tile-url="%s"></div>

            <div id="loadingOverlay" class="cp-map-loading" role="status" aria-live="polite">
                <div class="cp-map-spinner" aria-hidden="true"></div>
                <div class="cp-map-loading-text">Loading map…</div>
            </div>

            <div id="mapStatus" class="cp-map-status" hidden></div>

            <div class="cp-map-legend" id="cpMapLegend">
                <div class="cp-map-legend-title">Legend</div>
                <div class="cp-map-legend-item">🟢 Online region</div>
                <div class="cp-map-legend-item">⚪ Offline region</div>
            </div>
        </div>

        <div class="cp-map-debug" id="cpMapDebug" hidden></div>
    </main>',
    htmlspecialchars($gridName, ENT_QUOTES, 'UTF-8'),
    htmlspecialchars($apiUrl, ENT_QUOTES, 'UTF-8'),
    htmlspecialchars($tileUrl, ENT_QUOTES, 'UTF-8')
);

$headAssets = "\n<!-- Casperia World Map v1.0.0 -->\n"
    . '<link rel="stylesheet" href="' . htmlspecialchars($leafletCss, ENT_QUOTES, 'UTF-8') . '">' . "\n"
    . '<link rel="stylesheet" href="' . htmlspecialchars($mapCss, ENT_QUOTES, 'UTF-8') . '">' . "\n";

$scriptAssets = "\n<!-- Casperia World Map v1.0.0 -->\n"
    . '<script src="' . htmlspecialchars($leafletJs, ENT_QUOTES, 'UTF-8') . '"></script>' . "\n"
    . '<script src="' . htmlspecialchars($mapJs, ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";

if (is_file($headerFile)) {
    ob_start();
    include $headerFile;
    $headerHtml = ob_get_clean() ?: '';

    echo cpInjectBefore($headerHtml, '</head>', $headAssets);
    echo $mapBody;

    if (is_file($footerFile)) {
        ob_start();
        include $footerFile;
        $footerHtml = ob_get_clean() ?: '';
        echo cpInjectBefore($footerHtml, '</body>', $scriptAssets);
    } else {
        echo $scriptAssets;
    }

    exit;
}

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($gridName, ENT_QUOTES, 'UTF-8'); ?> - World Map</title>
    <?php echo $headAssets; ?>
</head>
<body>
<?php echo $mapBody; ?>
<?php echo $scriptAssets; ?>
</body>
</html>
