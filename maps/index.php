<?php
declare(strict_types=1);

/**
 * Casperia Prime /maps/ - site-integrated wrapper (MAP-ONLY UI)
 *
 * Goal:
 *  - Keep the working map logic (map-script.js, map-data.php, map-tile.php) intact.
 *  - Use Casperia's real header/footer.
 *  - Avoid header/footer breaking map assets (<base href> / CSP).
 *  - Fix "top alignment" by letting the page flow normally and sizing the map canvas
 *    from its on-page position (not hard 100vh).
 *
 * Optional debug: add ?debug=1
 */

$siteRoot = realpath(__DIR__ . '/..') ?: dirname(__DIR__);

// Load baseline config (for title only; safe)
$cfg = $siteRoot . '/include/config.php';
if (is_file($cfg)) {
    require_once $cfg;
}

$gridName = defined('APP_NAME') ? (string)APP_NAME : (defined('SITE_NAME') ? (string)SITE_NAME : 'Casperia Prime');

// Compute /maps URL (absolute URLs survive <base href>)
$scriptName = isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : '';
$mapsUrl = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
if ($mapsUrl === '') $mapsUrl = '/maps';

$debug = isset($_GET['debug']) && (string)$_GET['debug'] !== '0';

// Files
$headerFile = is_file($siteRoot . '/include/header.php') ? ($siteRoot . '/include/header.php') : null;
$footerFile = is_file($siteRoot . '/include/footer.php') ? ($siteRoot . '/include/footer.php') : null;

// Leaflet (prefer self-hosted if user adds it later)
$leafletCssCdn = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
$leafletJsCdn  = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
$leafletCss = is_file(__DIR__ . '/vendor/leaflet/leaflet.css') ? ($mapsUrl . '/vendor/leaflet/leaflet.css') : $leafletCssCdn;
$leafletJs  = is_file(__DIR__ . '/vendor/leaflet/leaflet.js')  ? ($mapsUrl . '/vendor/leaflet/leaflet.js')  : $leafletJsCdn;

// Map assets
$mapJs      = $mapsUrl . '/map-script.js';
$embedCss   = $mapsUrl . '/map-embed-v8.css';
$embedJs    = $mapsUrl . '/map-embed-v8.js';

// CSP: keep it permissive enough for Leaflet CDN (only for /maps/index.php).
// If your server enforces CSP globally, this page-level header may not override it;
// in that case, self-host Leaflet under /maps/vendor/leaflet/.
$csp = "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; "
     . "img-src 'self' data: blob: https: http:; "
     . "style-src 'self' 'unsafe-inline' https: http:; "
     . "script-src 'self' 'unsafe-inline' https: http:; "
     . "connect-src 'self' https: http:;";

function inject_before(string $html, string $needle, string $insert): string {
    $pos = stripos($html, $needle);
    if ($pos === false) return $html . $insert;
    return substr($html, 0, $pos) . $insert . substr($html, $pos);
}

function strip_meta_csp_and_base(string $html): string {
    // Remove meta http-equiv CSP tags (we set CSP as HTTP header for this page)
    $html = preg_replace('~<meta\b[^>]*http-equiv=["\']Content-Security-Policy["\'][^>]*>\s*~i', '', $html) ?? $html;
    $html = preg_replace('~<meta\b[^>]*http-equiv=["\']Content-Security-Policy-Report-Only["\'][^>]*>\s*~i', '', $html) ?? $html;
    // Remove <base ...> so it cannot rewrite relative URLs inside injected chrome
    return $html;
}

// MAP-ONLY body (no sidebar, no black theme)
$mapBodyHtml = <<<HTML
<div class="cp-map-page">
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
      <button type="button" class="cp-map-btn" id="cpMapResetBtn" title="Reset map view">
        Reset View
      </button>
    </div>
  </div>

  <div class="cp-map-search">
    <input id="searchInput" class="cp-map-input" type="text" placeholder="Search regions..." autocomplete="off">
    <button id="searchBtn" class="cp-map-btn" type="button">Search</button>
    <button id="clearSearch" class="cp-map-btn cp-map-btn-ghost d-none" type="button">Clear</button>
  </div>
  <div id="searchResults" class="cp-map-results" style="display:none;"></div>

  <div class="cp-map-canvas" id="cpMapCanvas">
    <div id="map"></div>

    <div id="loadingOverlay" class="cp-map-loading">
      <div class="cp-map-spinner" aria-hidden="true"></div>
      <div class="cp-map-loading-text">Loading map…</div>
    </div>
  </div>
  <div class="cp-map-debug" id="cpMapDebug" style="display:none;"></div>
</div>
HTML;

// Head inject
$headInject =
    "\n<!-- /maps v8 injected styles -->\n"
  . '<link rel="stylesheet" href="' . htmlspecialchars($leafletCss, ENT_QUOTES) . "\">\n"
  . '<link rel="stylesheet" href="' . htmlspecialchars($embedCss, ENT_QUOTES) . "\">\n";

// Script inject
$scriptInject =
    "\n<!-- /maps v8 injected scripts -->\n"
  . '<script src="' . htmlspecialchars($leafletJs, ENT_QUOTES) . "\"></script>\n"
  . '<script src="' . htmlspecialchars($mapJs, ENT_QUOTES) . "\"></script>\n"
  . '<script src="' . htmlspecialchars($embedJs, ENT_QUOTES) . "\"></script>\n";

if ($headerFile) {
    ob_start();
    include $headerFile;
    $headerHtml = ob_get_clean() ?: '';

    // Override CSP for THIS page after header.php had a chance to set headers.
    if (!headers_sent()) {
    }

    $headerHtml = strip_meta_csp_and_base($headerHtml);
    $headerHtml = inject_before($headerHtml, '</head>', $headInject);

    echo $headerHtml;
    echo $mapBodyHtml;

    if ($footerFile) {
        ob_start();
        include $footerFile;
        $footerHtml = ob_get_clean() ?: '';
        $footerHtml = inject_before($footerHtml, '</body>', $scriptInject);
        echo $footerHtml;
    } else {
        echo $scriptInject;
    }
    exit;
}

// --- Standalone fallback (if site header/footer not found) ---
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($gridName); ?> - Map</title>
  <?php echo $headInject; ?>
</head>
<body>
<?php echo $mapBodyHtml; ?>
<?php echo $scriptInject; ?>
</body>
</html>