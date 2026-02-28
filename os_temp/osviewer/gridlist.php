<?php
// gridlist.php — unified with site theme, server-side CSV loading

$title = "Grid List";

require_once __DIR__ . '/include/config.php';
include_once __DIR__ . '/include/header.php';

// Resolve CSV path relative to this script and GRIDLIST_FILE from config.php
$csvPath = __DIR__ . '/' . GRIDLIST_FILE;
$grids = [];

if (is_readable($csvPath)) {
    if (($fh = fopen($csvPath, 'r')) !== false) {
        // Explicitly pass all parameters to avoid PHP deprecation warnings about $escape
        while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
            if (count($row) < 2) continue;
            $name = trim($row[0]);
            $uri  = trim($row[1]);
            if ($name === '' || $uri === '') continue;
            // Skip header row
            if (stripos($name, 'gridname') === 0) continue;

            $grids[] = [
                'name' => $name,
                'uri'  => $uri,
            ];
        }
        fclose($fh);
    }
}
?>
<main class="content-card">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0">
      <i class="bi bi-diagram-3"></i>
      Grid List
    </h1>
    <span class="text-muted small">
      Viewer-ready list of OpenSimulator grids.
    </span>
  </div>

  <p class="text-muted">
    Click the <strong>Add to Viewer</strong> button to send the grid login URI to your OpenSimulator-compatible viewer
    (via <code>secondlife:///app/gridmanager/addgrid/...</code>). You can also search by grid name or address.
  </p>

  <div class="mb-3">
    <input
      type="text"
      id="gridSearch"
      class="form-control"
      placeholder="Search for grids by name or address..."
      autocomplete="off"
    >
  </div>

  <?php if (empty($grids)): ?>
    <div class="alert alert-warning">
      <i class="bi bi-exclamation-triangle-fill"></i>
      No grids are currently available. Make sure
      <code><?php echo htmlspecialchars(GRIDLIST_FILE, ENT_QUOTES); ?></code> exists and is readable.
    </div>
  <?php else: ?>
    <ul class="list-group" id="gridList">
      <?php foreach ($grids as $g): ?>
        <?php
          $name = $g['name'];
          $uri  = $g['uri'];
          $dataName = strtolower($name);
          $dataUri  = strtolower($uri);
          $href     = 'secondlife:///app/gridmanager/addgrid/' . $uri;
        ?>
        <li class="list-group-item d-flex justify-content-between align-items-center grid-row"
            data-name="<?php echo htmlspecialchars($dataName, ENT_QUOTES); ?>"
            data-uri="<?php echo htmlspecialchars($dataUri, ENT_QUOTES); ?>">
          <div class="me-3">
            <div class="fw-semibold">
              <?php echo htmlspecialchars($name, ENT_QUOTES); ?>
            </div>
            <div class="small text-muted">
              <?php echo htmlspecialchars($uri, ENT_QUOTES); ?>
            </div>
          </div>
          <a class="btn btn-sm btn-primary" href="<?php echo htmlspecialchars($href, ENT_QUOTES); ?>">
            <i class="bi bi-box-arrow-in-right"></i>
            Add to Viewer
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

</main>

<script>
(function() {
  const input = document.getElementById('gridSearch');
  const rows  = Array.from(document.querySelectorAll('.grid-row'));
  if (!input || !rows.length) return;

  input.addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    rows.forEach(li => {
      const n = li.dataset.name || '';
      const u = li.dataset.uri  || '';
      if (!q || n.includes(q) || u.includes(q)) {
        li.style.display = '';
      } else {
        li.style.display = 'none';
      }
    });
  });
})();
</script>

<?php include_once __DIR__ . '/include/footer.php'; ?>
