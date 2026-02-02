<?php
/**
 * Compatibility shim (Casperia Prime):
 *
 * Many OpenSimulator setups point the in-viewer "splash" to this legacy filename.
 * Force viewer/splash mode so the page renders without the full site chrome.
 */

// Force viewer mode via the existing detector (also drops a session cookie when possible).
if (!isset($_GET['view'])) {
    $_GET['view'] = 'viewer';
}

require_once __DIR__ . '/welcome.php';
