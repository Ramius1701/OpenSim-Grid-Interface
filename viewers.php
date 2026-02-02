<?php
$title = "Viewer Configuration";
require_once __DIR__ . '/include/config.php';
include_once __DIR__ . '/include/' . HEADER_FILE;

// Build viewer URLs from config with safe fallbacks
$fsWin = defined('URL_VIEWER_WIN')
    ? URL_VIEWER_WIN
    : 'https://www.firestormviewer.org/windows-for-open-simulator/';
$fsMac = defined('URL_VIEWER_MAC')
    ? URL_VIEWER_MAC
    : 'https://www.firestormviewer.org/mac-for-open-simulator/';
$fsLin = defined('URL_VIEWER_LIN')
    ? URL_VIEWER_LIN
    : 'https://www.firestormviewer.org/linux-for-open-simulator/';
$cool  = defined('URL_COOLVL')
    ? URL_COOLVL
    : 'https://sldev.free.fr/';
?>

<div class="container-fluid mt-4 mb-4">
  <div class="card border-0 shadow-sm">
    <div class="card-body p-3 p-md-4">
  <section class="mb-4">
    <h1 class="mb-1"><i class="bi bi-display me-2"></i> Viewer Configuration</h1>
    <p class="text-body-secondary">
      Choose a viewer below. After installing, add/select
      <strong><?= htmlspecialchars(SITE_NAME) ?></strong> in the grid manager.
    </p>
  </section>

  <div class="row g-3 mb-3">
    <!-- Firestorm — Official Release -->
    <div class="col-md-6">
      <div class="card h-100 shadow-sm">
        <div class="card-header fw-semibold">Firestorm — Official Release (OpenSim builds)</div>
        <div class="list-group list-group-flush">
          <a href="<?= htmlspecialchars($fsWin) ?>" target="_blank" rel="noopener"
             class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <span>Windows — Firestorm for OpenSim</span>
            <span class="badge bg-primary">Download</span>
          </a>
          <a href="<?= htmlspecialchars($fsLin) ?>" target="_blank" rel="noopener"
             class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <span>Linux — Firestorm for OpenSim</span>
            <span class="badge bg-primary">Download</span>
          </a>
          <a href="<?= htmlspecialchars($fsMac) ?>" target="_blank" rel="noopener"
             class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <span>macOS — Firestorm for OpenSim</span>
            <span class="badge bg-primary">Download</span>
          </a>
        </div>
      </div>
    </div>

    <!-- Firestorm — Early Access (Beta) -->
    <div class="col-md-6">
      <div class="card h-100 shadow-sm">
        <div class="card-header fw-semibold">Firestorm — Early Access (Beta)</div>
        <div class="list-group list-group-flush">
          <a href="https://www.firestormviewer.org/early-access-beta-downloads/"
             target="_blank" rel="noopener"
             class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <span>Early Access downloads (official site)</span>
            <span class="badge bg-primary">Download</span>
          </a>
          <a href="https://www.firestormviewer.org/early-access-beta-downloads-legacy-cpus/"
             target="_blank" rel="noopener"
             class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <span>Early Access for older CPUs</span>
            <span class="badge bg-primary">Download</span>
          </a>
        </div>
      </div>
    </div>

    <!-- Firestorm — Nightly Builds -->
    <div class="col-md-6">
      <div class="card h-100 shadow-sm">
        <div class="card-header fw-semibold">Firestorm — Nightly Builds</div>
        <div class="list-group list-group-flush">
          <a href="https://www.firestormviewer.org/firestorm-nightly-build-downloads/"
             target="_blank" rel="noopener"
             class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <span>Nightly builds (official site)</span>
            <span class="badge bg-primary">Download</span>
          </a>
          <a href="https://www.firestormviewer.org/firestorm-night-build-downloads-legacy-cpus/"
             target="_blank" rel="noopener"
             class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <span>Nightly for older CPUs</span>
            <span class="badge bg-primary">Download</span>
          </a>
        </div>
      </div>
    </div>

    <!-- Cool VL Viewer -->
    <div class="col-md-6">
      <div class="card h-100 shadow-sm">
        <div class="card-header fw-semibold">Cool VL Viewer (OpenSim-compatible)</div>
        <div class="list-group list-group-flush">
          <a href="<?= htmlspecialchars($cool) ?>" target="_blank" rel="noopener"
             class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <span>Cool VL Viewer — official site / downloads</span>
            <span class="badge bg-primary">Download</span>
          </a>
        </div>
      </div>
    </div>
  </div>

  <section class="mt-4">
    <h2 class="h5 mb-2">Add our grid to your viewer</h2>
    <p class="mb-2">
      We’re not listed in the default grid list yet. Add our login URI in your viewer’s Grid Manager:
    </p>
    <div class="row g-2 align-items-center">
      <div class="col-md-8">
        <div class="input-group">
          <span class="input-group-text login-uri-label">Login URI</span>
          <input type="text" class="form-control"
                 value="<?= htmlspecialchars(GRID_URI) ?>" readonly>
        </div>
      </div>
    </div>
    <div class="mt-3 small">
      <ul class="mb-0">
        <li><strong>Firestorm:</strong> Preferences → <em>OpenSim</em> → <em>Add new grid</em> →
          paste the Login URI → <em>Apply/OK</em>. Select the grid on the login panel.</li>
        <li><strong>Cool VL Viewer:</strong> Preferences → <em>Grids</em> (or login panel grid manager) →
          <em>Add</em> → paste the Login URI → <em>Get/Apply</em>. Select the grid and log in.</li>
      </ul>
    </div>
  </section>

  <section class="mt-4">
    <div class="alert alert-info small mb-0">
      <div class="fw-semibold mb-1">Notes</div>
      <ul class="mb-0">
        <li>Early Access and Nightly builds are for testing; expect occasional issues.</li>
        <li>For macOS users on Apple Silicon, use the most recent Firestorm build available on the official pages.</li>
      </ul>
    </div>
  </section>
    </div>
  </div>
</div>

<?php include_once __DIR__ . "/include/" . FOOTER_FILE; ?>
