<?php
// casperia/maps/index.php
// Standalone interactive grid map UI (Leaflet)
// Only depends on the Casperia baseline config (DB + constants).

require_once __DIR__ . '/../include/config.php';

// Optional: use APP_NAME as a default label until JS loads live stats.
$gridName = defined('APP_NAME') ? APP_NAME : 'Casperia Prime';
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($gridName); ?> - Map</title>

  <!-- Bootstrap (matches site baseline CDN) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

  <!-- Leaflet -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous">

  <!-- Map styling -->
  <link rel="stylesheet" href="map-style-new.css">
</head>
<body>
  <div class="container-fluid p-0">
    <div class="row g-0">
      <!-- LEFT SIDEBAR -->
      <aside class="col-lg-4 col-xl-3 sidebar-section">
        <div class="sidebar-content">
          <div class="sidebar-header">
            <h1 class="mb-1"><?php echo htmlspecialchars($gridName); ?> Map</h1>
            <div class="text-muted small">
              Regions: <span id="headerRegionCount">—</span>
            </div>
          </div>

          <!-- SEARCH -->
          <div class="search-section">
            <div class="section-title">Search</div>
            <div class="input-group">
              <input id="searchInput" type="text" class="form-control" placeholder="Search regions or parcels...">
              <button id="searchBtn" class="btn btn-outline-light" type="button" title="Search">
                <i class="bi bi-search"></i>
              </button>
              <button id="clearSearch" class="btn btn-outline-light d-none" type="button" title="Clear">
                <i class="bi bi-x-lg"></i>
              </button>
            </div>
            <div id="searchResults" class="search-results mt-2" style="display:none;"></div>
          </div>

          <!-- STATS -->
          <div class="stats-section">
            <div class="section-title">Grid Stats</div>

            <div class="stat-card-mini d-flex align-items-center justify-content-between" style="background: linear-gradient(135deg,#4ecdc4 0%,#44a08d 100%);">
              <div>
                <div class="stat-value" id="statOnlineNow">—</div>
                <div class="stat-label">Users Online</div>
                <div class="stat-sublabel" id="statNewUsersToday"></div>
              </div>
              <div class="stat-icon"><i class="bi bi-broadcast"></i></div>
            </div>

            <div class="stat-card-mini d-flex align-items-center justify-content-between" style="background: linear-gradient(135deg,#667eea 0%,#764ba2 100%);">
              <div>
                <div class="stat-value" id="statRegions">—</div>
                <div class="stat-label">Total Regions</div>
                <div class="stat-sublabel" id="statRegionsSub"></div>
              </div>
              <div class="stat-icon"><i class="bi bi-grid-3x3-gap"></i></div>
            </div>

            <div class="stat-card-mini d-flex align-items-center justify-content-between" style="background: linear-gradient(135deg,#ff6b35 0%,#f78c6b 100%);">
              <div>
                <div class="stat-value" id="statTotalUsers">—</div>
                <div class="stat-label">Total Users</div>
                <div class="stat-sublabel" id="transVolume"></div>
              </div>
              <div class="stat-icon"><i class="bi bi-people"></i></div>
            </div>

            <div class="stat-card-mini d-flex align-items-center justify-content-between" style="background: linear-gradient(135deg,#1a1d29 0%,#0f1015 100%); border:1px solid rgba(255,255,255,.15);">
              <div>
                <div class="stat-value" id="statTransactions">—</div>
                <div class="stat-label">Transactions</div>
              </div>
              <div class="stat-icon"><i class="bi bi-cash-coin"></i></div>
            </div>
          </div>

          <!-- QUICK LINKS -->
          <div class="quick-links-section">
            <div class="section-title">Quick Links</div>
            <div class="d-grid gap-2">
              <a class="btn btn-outline-light" href="../">
                <i class="bi bi-house-door me-1"></i> Back to Site
              </a>
              <a class="btn btn-outline-light" href="../gridmap.php">
                <i class="bi bi-map me-1"></i> Legacy Grid Map
              </a>
            </div>
          </div>
        </div>
      </aside>

      <!-- RIGHT MAP -->
      <main class="col-lg-8 col-xl-9 map-section">
        <div class="map-container">
          <div id="map"></div>

          <div id="loadingOverlay" class="loading-overlay">
            <div class="spinner-border text-light" role="status" aria-hidden="true"></div>
            <div class="mt-3 text-light fw-semibold">Loading map…</div>
          </div>
</div>
        </div>
      </main>
    </div>
  </div>

  <!-- JS deps -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
          integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="anonymous"></script>

  <!-- Map script (must be non-module; it relies on document.currentScript.src) -->
  <script src="map-script.js"></script>
</body>
</html>
