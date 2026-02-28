<?php
$title = "MapTile";
include_once 'include/header.php';

// ------------------------------------------------------------
// DB connect (no exceptions so we can safely probe)
// ------------------------------------------------------------
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
$con = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$con) {
    echo '<div style="max-width:800px;margin:1rem auto;padding:1rem;border:1px solid #eee;border-radius:8px;background:#fff">';
    echo '<h3>Map error</h3><div>Database connection failed.</div>';
    echo '<pre style="white-space:pre-wrap;color:#6b7280">'.htmlspecialchars(mysqli_connect_error()).'</pre></div>';
    include_once 'include/footer.php';
    exit;
}

// ------------------------------------------------------------
// Config knobs (can live in config.php)
// If MAP_LOC_IN_METERS constant exists, use it. Else default true (your code div by 256).
// URL override: ?meters=0|1
// ------------------------------------------------------------
$mapInMeters = defined('MAP_LOC_IN_METERS') ? (bool)MAP_LOC_IN_METERS : true;
if (isset($_GET['meters'])) {
    $m = $_GET['meters'];
    if ($m === '0' || $m === '1') { $mapInMeters = ($m === '1'); }
}
if (!defined('HOME_REGION_UUID'))  define('HOME_REGION_UUID', '');
if (!defined('HOME_REGION_NAME'))  define('HOME_REGION_NAME', '');

// ------------------------------------------------------------
// Center window (keep your logic) + URL override (?cx=?, ?cy=?)
// ------------------------------------------------------------
$centerX = CONF_CENTER_COORD_X;
$centerY = CONF_CENTER_COORD_Y;
if (isset($_GET['cx'])) $centerX = (int)$_GET['cx'];
if (isset($_GET['cy'])) $centerY = (int)$_GET['cy'];

if ($centerY <= 30)   { $centerY = 100; }
if ($centerX <= 30)   { $centerX = 100; }
if ($centerX >= 99999){ $centerX = CONF_CENTER_COORD_X; }
if ($centerY >= 99999){ $centerY = CONF_CENTER_COORD_Y; }

$startX = $centerX - floor(MAPS_X / 2);
$startY = $centerY - floor(MAPS_Y / 2);
$endX   = $centerX + floor(MAPS_X / 2);
$endY   = $centerY + floor(MAPS_Y / 2);

// ------------------------------------------------------------
// Helpers
// ------------------------------------------------------------
function map_show_tables($con) {
    $out = [];
    if ($res = @mysqli_query($con, "SHOW TABLES")) {
        while ($r = mysqli_fetch_row($res)) { $out[] = $r[0]; }
        mysqli_free_result($res);
    }
    return $out;
}
function map_show_cols($con, $table) {
    $cols = [];
    if ($res = @mysqli_query($con, "SHOW COLUMNS FROM `".$table."`")) {
        while ($r = mysqli_fetch_assoc($res)) { $cols[strtolower($r['Field'])] = $r['Field']; }
        mysqli_free_result($res);
    }
    return $cols;
}
function pick_table($con, $order) {
    $have = array_map('strtolower', map_show_tables($con));
    foreach ($order as $cand) {
        $i = array_search(strtolower($cand), $have, true);
        if ($i !== false) return map_show_tables($con)[$i];
    }
    return null;
}
function pick_col($cols, $cands) {
    foreach ($cands as $c) { $k = strtolower($c); if (isset($cols[$k])) return $cols[$k]; }
    return null;
}
function render_error($msg, $detail='') {
    echo '<div class="map-center-wrap"><div style="padding:1rem;border:1px solid #eee;border-radius:8px;background:#fff;max-width:680px">';
    echo '<h3 style="margin:0 0 .5rem">Map error</h3><div>'.htmlspecialchars($msg).'</div>';
    if ($detail) echo '<pre style="white-space:pre-wrap;color:#6b7280;margin-top:.5rem">'.htmlspecialchars($detail).'</pre>';
    echo '</div></div>';
    include_once 'include/footer.php';
    exit;
}

// ------------------------------------------------------------
// Detect table/columns (UUID optional)
// ------------------------------------------------------------
$table = pick_table($con, ['regions','GridRegion','GridRegions']);
if (!$table) { render_error("No region table found (tried: regions, GridRegion, GridRegions)."); }

$cols = map_show_cols($con, $table);
$C = [
  'name'  => pick_col($cols, ['regionName','RegionName','name']),
  'uuid'  => pick_col($cols, ['uuid','UUID','RegionID','regionID','RegionUUID','regionUUID','region_id','RegionGUID','regionGUID']),
  'locX'  => pick_col($cols, ['locX','LocX','locx']),
  'locY'  => pick_col($cols, ['locY','LocY','locy']),
  'uri'   => pick_col($cols, ['serverURI','ServerURI','serveruri']),
  'sizeX' => pick_col($cols, ['sizeX','SizeX','sizex']),
  'sizeY' => pick_col($cols, ['sizeY','SizeY','sizey']),
  'owner' => pick_col($cols, ['owner_uuid','Owner_uuid','ownerUUID','OwnerUUID','owner']),
];
foreach (['name','locX','locY'] as $must) {
    if (!$C[$must]) render_error("Required column '$must' missing in table ".htmlspecialchars($table));
}

// ------------------------------------------------------------
// Build SELECT (only visible region rects) with intersection WHERE
// ------------------------------------------------------------
$sel = "SELECT `{$C['name']}` AS regionName, `{$C['locX']}` AS locX, `{$C['locY']}` AS locY";
if ($C['uuid'])  $sel .= ", `{$C['uuid']}` AS uuid";
if ($C['uri'])   $sel .= ", `{$C['uri']}` AS serverURI";
if ($C['sizeX']) $sel .= ", `{$C['sizeX']}` AS sizeX";
if ($C['sizeY']) $sel .= ", `{$C['sizeY']}` AS sizeY";
if ($C['owner']) $sel .= ", `{$C['owner']}` AS owner_uuid";
$sel .= " FROM `{$table}`";

if ($C['sizeX'] && $C['sizeY']) {
    if ($mapInMeters) {
        $vx0 = $startX * 256; $vy0 = $startY * 256;
        $vx1 = ($endX + 1) * 256 - 256;
        $vy1 = ($endY + 1) * 256 - 256;
        $sel .= " WHERE `{$C['locX']}` <= {$vx1} AND (`{$C['locX']}` + `{$C['sizeX']}` - 256) >= {$vx0}"
              . " AND `{$C['locY']}` <= {$vy1} AND (`{$C['locY']}` + `{$C['sizeY']}` - 256) >= {$vy0}";
    } else {
        $sel .= " WHERE `{$C['locX']}` <= {$endX} AND (`{$C['locX']}` + (CEIL(`{$C['sizeX']}`/256)) - 1) >= {$startX}"
              . " AND `{$C['locY']}` <= {$endY} AND (`{$C['locY']}` + (CEIL(`{$C['sizeY']}`/256)) - 1) >= {$startY}";
    }
} else {
    $pad = 4;
    if ($mapInMeters) {
        $vx0 = ($startX - $pad) * 256; $vy0 = ($startY - $pad) * 256;
        $vx1 = ($endX + $pad) * 256;   $vy1 = ($endY + $pad) * 256;
        $sel .= " WHERE `{$C['locX']}` BETWEEN {$vx0} AND {$vx1} AND `{$C['locY']}` BETWEEN {$vy0} AND {$vy1}";
    } else {
        $vx0 = $startX - $pad; $vy0 = $startY - $pad;
        $vx1 = $endX + $pad;   $vy1 = $endY + $pad;
        $sel .= " WHERE `{$C['locX']}` BETWEEN {$vx0} AND {$vx1} AND `{$C['locY']}` BETWEEN {$vy0} AND {$vy1}";
    }
}

$res = @mysqli_query($con, $sel);
if (!$res) { render_error("Query failed.", mysqli_error($con)); }

// ------------------------------------------------------------
// Build grid; fill ALL covered cells for var regions
// ------------------------------------------------------------
$grid = []; // $grid[cellX][cellY] = info
while ($row = mysqli_fetch_assoc($res)) {
    // normalize loc to grid cells
    $cellX = $mapInMeters ? (int)floor(((int)$row['locX']) / 256) : (int)$row['locX'];
    $cellY = $mapInMeters ? (int)floor(((int)$row['locY']) / 256) : (int)$row['locY'];

    $sizeX = (int)($row['sizeX'] ?? 256);
    $sizeY = (int)($row['sizeY'] ?? 256);
    $wCells = max(1, (int)round($sizeX / 256));
    $hCells = max(1, (int)round($sizeY / 256));

    $uuid  = $row['uuid'] ?? '';
    $isVar = ($wCells > 1 || $hCells > 1);
    $isHome = (HOME_REGION_UUID && $uuid !== '' && strcasecmp($uuid, HOME_REGION_UUID) === 0) ||
              (HOME_REGION_NAME && isset($row['regionName']) && strcasecmp($row['regionName'], HOME_REGION_NAME) === 0);

    $baseColor = ($sizeX == 256 && $sizeY == 256) ? BESCHLAGT_COLOR : VARREGION_COLOR;

    for ($dx = 0; $dx < $wCells; $dx++) {
        for ($dy = 0; $dy < $hCells; $dy++) {
            $cx = $cellX + $dx;
            $cy = $cellY + $dy;
            if ($cx < $startX || $cx > $endX || $cy < $startY || $cy > $endY) continue;

            $color = $baseColor;
            if ($isHome && defined('CENTER_COLOR')) {
                $color = CENTER_COLOR;
            } elseif ($cx == $centerX && $cy == $centerY && defined('CENTER_COLOR')) {
                $color = CENTER_COLOR;
            }

            $grid[$cx][$cy] = [
                'color'      => $color,
                'regionName' => $row['regionName'] ?? '',
                'sizeX'      => $sizeX,
                'sizeY'      => $sizeY,
                'uuid'       => $uuid,
                'serverURI'  => $row['serverURI'] ?? '',
                'owner_uuid' => $row['owner_uuid'] ?? '',
                'isVar'      => $isVar,
                'isHome'     => $isHome,
            ];
        }
    }
}
mysqli_free_result($res);
mysqli_close($con);

// TILE_SIZE might be "32px" etc. Extract numeric for JS scrolling step:
$tile_px_num = (int)preg_replace('/[^0-9]/', '', (string)TILE_SIZE);
if ($tile_px_num <= 0) $tile_px_num = 32;
?>

<style>
/* Centering + desktop UX */
.map-center-wrap {
    max-width: 100%;
    display: flex;
    justify-content: center; /* horizontal center */
    overflow-x: auto; /* allow scroll if very wide */
}
.map-container { width: max-content; transform-origin: top left; cursor: grab; }
.map-container.dragging { cursor: grabbing; }

.card {
    display: none;
    position: fixed; /* follow viewport */
    top: 0; left: 0;
    padding: 12px 14px;
    background-color: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 10px 24px rgba(0,0,0,.12);
    z-index: 1000;
    color: #111;
    max-width: 340px;
}
.card.active { display: block; }
.card p { margin: .25rem 0; }

/* Tiles */
.map-container .map-tile.home { outline: 2px solid #0f62fe; outline-offset: -1px; }
.map-container .map-tile.var { box-shadow: inset 0 0 0 2px rgba(0,0,0,.08); }

/* Context menu */
#ctxmenu button { cursor:pointer; }
#ctxmenu button:hover { background:#f3f4f6; }
</style>

<main>
    <!-- Legend -->
    <div style="display:flex;gap:1rem;justify-content:center;margin:.25rem 0 1rem">
      <span style="display:inline-flex;align-items:center;gap:.4rem">
        <i style="width:12px;height:12px;background:<?php echo htmlspecialchars(BESCHLAGT_COLOR, ENT_QUOTES, 'UTF-8'); ?>;border:1px solid #ddd;border-radius:2px"></i> 256×256
      </span>
      <span style="display:inline-flex;align-items:center;gap:.4rem">
        <i style="width:12px;height:12px;background:<?php echo htmlspecialchars(VARREGION_COLOR, ENT_QUOTES, 'UTF-8'); ?>;border:1px solid #ddd;border-radius:2px"></i> Var region
      </span>
      <span style="display:inline-flex;align-items:center;gap:.4rem">
        <i style="width:12px;height:12px;background:<?php echo htmlspecialchars(FREI_COLOR, ENT_QUOTES, 'UTF-8'); ?>;border:1px solid #ddd;border-radius:2px"></i> Free
      </span>
      <?php if (defined('CENTER_COLOR')): ?>
      <span style="display:inline-flex;align-items:center;gap:.4rem">
        <i style="width:12px;height:12px;background:<?php echo htmlspecialchars(CENTER_COLOR, ENT_QUOTES, 'UTF-8'); ?>;border:1px solid #ddd;border-radius:2px"></i> Home/Center
      </span>
      <?php endif; ?>
    </div>

    <div class="map-center-wrap">
      <div class="map-container"
           style="display: grid; grid-template-columns: repeat(<?php echo MAPS_X; ?>, <?php echo TILE_SIZE; ?>); gap: 1px;">

          <?php
          // Render row-by-row, and map each screen cell to a 90° CCW world coordinate
          for ($row = 0; $row < MAPS_Y; $row++) {
              for ($col = 0; $col < MAPS_X; $col++) {

                  // 90° CCW mapping:
                  // screen (row, col) -> world (x, y)
                  $worldX = $startX + (MAPS_X - 1 - $row);
                  $worldY = $startY + $col;

                  $has  = isset($grid[$worldX][$worldY]);
                  $tile = $has
                      ? $grid[$worldX][$worldY]
                      : [
                          'color'      => FREI_COLOR,
                          'regionName' => 'Free',
                          'sizeX'      => 0,
                          'sizeY'      => 0,
                          'uuid'       => '',
                          'serverURI'  => '',
                          'owner_uuid' => '',
                          'isVar'      => false,
                          'isHome'     => false
                      ];

                  $tooltip = ($tile['regionName'] !== 'Free')
                      ? "Coords: ($worldX, $worldY) — Region: {$tile['regionName']} — Size: {$tile['sizeX']}×{$tile['sizeY']} m"
                      : "Coords: ($worldX, $worldY) — Free";

                  $payload = htmlspecialchars(json_encode([
                      'x'          => $worldX,
                      'y'          => $worldY,
                      'regionName' => $tile['regionName'],
                      'uuid'       => $tile['uuid'],
                      'sizeX'      => $tile['sizeX'],
                      'sizeY'      => $tile['sizeY'],
                      'serverURI'  => $tile['serverURI'],
                      'owner_uuid' => $tile['owner_uuid']
                  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');

                  $classes = 'map-tile';
                  if ($tile['isVar'])  $classes .= ' var';
                  if ($tile['isHome']) $classes .= ' home';

                  $clickAction = $tile['regionName'] === 'Free'
                      ? "showFreeRegionCard(event, {$worldX}, {$worldY})"
                      : "showOccupiedRegionCard(event, this.dataset.payload)";

                  echo "<div class='$classes' role='button' tabindex='0' "
                    . "title='" . htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8') . "' "
                    . "style='width: " . TILE_SIZE . "; height: " . TILE_SIZE . "; background-color: {$tile['color']};' "
                    . "data-payload='{$payload}' "
                    . "onclick=\"$clickAction\" "
                    . "onkeydown=\"tileKey(event,this)\"></div>";
              }
          }
          ?>

      </div>
    </div>

    <!-- Free cell card (compact) -->
    <div id="freeRegionCard" class="card" aria-live="polite">
      <h4 style="margin:.25rem 0 .5rem">Free cell</h4>
      <div id="free-coords" style="margin-bottom:.5rem"></div>

      <div class="actions" style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.25rem">
        <button onclick="copyFreeSkeleton()">Copy region skeleton</button>
        <button onclick="hideCard()">Close</button>
      </div>

      <details id="free-advanced" style="margin-top:.5rem">
        <summary>Advanced</summary>
        <div>RegionUUID: <span id="free-uuid"></span></div>
        <div>Maptile UUID: <span id="free-maptile-uuid"></span></div>
        <div>InternalPort: <span id="free-port"></span></div>
      </details>
    </div>

    <!-- Occupied region card (compact) -->
    <div id="occupiedRegionCard" class="card" aria-live="polite">
      <h4 id="occ-name" style="margin:.25rem 0 .25rem"></h4>
      <div id="occ-coords" style="opacity:.8"></div>
      <div id="occ-size"   style="margin:.25rem 0 .5rem"></div>

      <div class="actions" style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.25rem">
        <button onclick="copyOccInfo()">Copy</button>
        <button onclick="recenterHere()">Recenter here</button>
        <button onclick="hideCard()">Close</button>
      </div>

      <details style="margin-top:.5rem">
        <summary>Advanced</summary>
        <div>UUID: <span id="occ-uuid"></span></div>
        <div>Server URI: <span id="occ-uri"></span></div>
        <div>Owner UUID: <span id="occ-owner"></span></div>
      </details>
    </div>

    <!-- Desktop context menu -->
    <div id="ctxmenu" style="display:none;position:fixed;z-index:1001;background:#fff;border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 10px 24px rgba(0,0,0,.12);min-width:220px">
      <button data-act="copyName"  style="display:block;width:100%;text-align:left;padding:.5rem .75rem;border:0;background:transparent">Copy region name</button>
      <button data-act="copyUUID"  style="display:block;width:100%;text-align:left;padding:.5rem .75rem;border:0;background:transparent">Copy region UUID</button>
      <button data-act="copyCoords"style="display:block;width:100%;text-align:left;padding:.5rem .75rem;border:0;background:transparent">Copy coords</button>
      <hr style="margin:.25rem 0;border:none;border-top:1px solid #eee">
      <button data-act="recenter" style="display:block;width:100%;text-align:left;padding:.5rem .75rem;border:0;background:transparent">Recenter view here</button>
    </div>
</main>

<script>
// ------- Utility -------
function clampToViewport(card, x, y){
    const pad = 10;
    card.style.left = x + 'px';
    card.style.top  = y + 'px';
    const r = card.getBoundingClientRect();
    const vw = window.innerWidth, vh = window.innerHeight;
    let left = r.left, top = r.top;
    if (left + r.width  + pad > vw) left = vw - r.width  - pad;
    if (top  + r.height + pad > vh) top  = vh - r.height - pad;
    if (left < pad) left = pad;
    if (top  < pad)  top  = pad;
    card.style.left = left + 'px';
    card.style.top  = top  + 'px';
}
function uuidv4(){
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c=>{
    const r = crypto.getRandomValues(new Uint8Array(1))[0] & 15;
    const v = c === 'x' ? r : (r & 0x3 | 0x8);
    return v.toString(16);
  });
}
function randPort(min=9000, max=9250){ return Math.floor(Math.random()*(max-min+1))+min; }
async function copyText(txt){ try { await navigator.clipboard.writeText(txt); } catch(_){} }

// ------- Cards (COMPACT) -------
function showFreeRegionCard(event, x, y) {
  const card = document.getElementById('freeRegionCard');
  card.querySelector('#free-coords').textContent = `Coords: (${x}, ${y})`;
  // advanced (generated only when opened)
  card.querySelector('#free-uuid').textContent = uuidv4();
  card.querySelector('#free-maptile-uuid').textContent = uuidv4();
  card.querySelector('#free-port').textContent = randPort();
  card.dataset.x = x;
  card.dataset.y = y;

  card.classList.add('active');
  clampToViewport(card, event.clientX + 12, event.clientY + 12);
}
let lastOccupiedPayload = null;
function showOccupiedRegionCard(event, payload) {
  let d;
  try { d = (typeof payload === 'string') ? JSON.parse(payload) : payload; } catch(e){ d = {}; }
  lastOccupiedPayload = d;

  const card = document.getElementById('occupiedRegionCard');
  card.querySelector('#occ-name').textContent   = d.regionName || '(unnamed)';
  card.querySelector('#occ-coords').textContent = (d.x!=null && d.y!=null) ? `Coords: (${d.x}, ${d.y})` : '';
  card.querySelector('#occ-size').textContent   = (d.sizeX && d.sizeY) ? `Size: ${d.sizeX}×${d.sizeY} m` : '';
  card.querySelector('#occ-uuid').textContent   = d.uuid || '';
  card.querySelector('#occ-uri').textContent    = d.serverURI || '';
  card.querySelector('#occ-owner').textContent  = d.owner_uuid || '';

  card.classList.add('active');
  clampToViewport(card, event.clientX + 12, event.clientY + 12);
}
function hideCard() {
  document.querySelectorAll('.card').forEach(c=>c.classList.remove('active'));
}
function copyFreeSkeleton(){
  const card = document.getElementById('freeRegionCard');
  const x = card.dataset.x, y = card.dataset.y;
  const regionUuid = card.querySelector('#free-uuid').textContent;
  const maptileUuid = card.querySelector('#free-maptile-uuid').textContent;
  const port = card.querySelector('#free-port').textContent;
  const ini = [
    `[Region_${x}_${y}]`,
    `Location = ${x},${y}`,
    `RegionUUID = ${regionUuid}`,
    `SizeX = 256`,
    `SizeY = 256`,
    `SizeZ = 256`,
    `InternalAddress = 0.0.0.0`,
    `InternalPort = ${port}`,
    `ResolveAddress = False`,
    `ExternalHostName = SYSTEMIP`,
    `MaptileStaticUUID = ${maptileUuid}`
  ].join('\\n');
  copyText(ini);
}
function copyOccInfo(){
  const d = lastOccupiedPayload || {};
  const lines = [];
  if (d.regionName) lines.push(`Region: ${d.regionName}`);
  if (d.x!=null && d.y!=null) lines.push(`Coords: ${d.x},${d.y}`);
  if (d.sizeX && d.sizeY) lines.push(`Size: ${d.sizeX}×${d.sizeY} m`);
  if (d.uuid) lines.push(`UUID: ${d.uuid}`);
  if (d.serverURI) lines.push(`ServerURI: ${d.serverURI}`);
  if (d.owner_uuid) lines.push(`Owner: ${d.owner_uuid}`);
  copyText(lines.join('\\n'));
}
function recenterHere(){
  const d = lastOccupiedPayload || {};
  if (d.x==null || d.y==null) return;
  const url = new URL(location.href);
  url.searchParams.set('cx', d.x);
  url.searchParams.set('cy', d.y);
  if (!url.searchParams.has('meters')) {
    url.searchParams.set('meters', <?php echo $mapInMeters ? '1' : '0'; ?>);
  }
  location.href = url.toString();
}

// ------- Keyboard, focus, click-outside -------
function tileKey(e, el){
  if (e.key==='Enter' || e.key===' ') {
    e.preventDefault();
    const payload = el.dataset.payload || '{}';
    if (payload.includes('"regionName":"Free"')) {
      const d = JSON.parse(payload); showFreeRegionCard(e, d.x, d.y);
    } else {
      showOccupiedRegionCard(e, payload);
    }
  }
}
document.addEventListener('keydown', (e)=>{
  if (e.key==='Escape') hideCard();
  const wrap = document.querySelector('.map-center-wrap');
  const step = <?php echo (int)$tile_px_num; ?> || 32;
  if (e.key==='ArrowLeft')  wrap.scrollLeft -= step;
  if (e.key==='ArrowRight') wrap.scrollLeft += step;
  if (e.key==='ArrowUp')    wrap.scrollTop  -= step;
  if (e.key==='ArrowDown')  wrap.scrollTop  += step;
});
document.addEventListener('click', (e)=>{
  const anyCard = document.querySelector('.card.active');
  if (anyCard && !anyCard.contains(e.target) && !e.target.classList.contains('map-tile')) hideCard();
});

// ------- Desktop zoom (Ctrl/⌘ + wheel) & drag-to-pan -------
(function(){
  const wrap = document.querySelector('.map-center-wrap');
  const grid = document.querySelector('.map-container');
  let zoom = 1, isDrag=false, sx=0, sy=0, sl=0, st=0;

  function applyZoom(newZ){
    newZ = Math.max(0.5, Math.min(2.0, newZ));
    const factor = newZ / zoom;
    wrap.scrollLeft = wrap.scrollLeft * factor;
    wrap.scrollTop  = wrap.scrollTop  * factor;
    zoom = newZ;
    grid.style.transform = 'scale('+zoom+')';
  }

  wrap.addEventListener('wheel', (e)=>{
    if (e.ctrlKey || e.metaKey) {
      e.preventDefault();
      const delta = e.deltaY > 0 ? -0.1 : 0.1;
      applyZoom(zoom + delta);
    }
  }, {passive:false});

  grid.addEventListener('mousedown', (e)=>{
    if (e.button !== 0 && e.button !== 1) return;
    isDrag = true; grid.classList.add('dragging');
    sx = e.clientX; sy = e.clientY;
    sl = wrap.scrollLeft; st = wrap.scrollTop;
    e.preventDefault();
  });
  window.addEventListener('mousemove', (e)=>{
    if (!isDrag) return;
    wrap.scrollLeft = sl - (e.clientX - sx);
    wrap.scrollTop  = st - (e.clientY - sy);
  });
  window.addEventListener('mouseup', ()=>{ isDrag=false; grid.classList.remove('dragging'); });
})();

// ------- Context menu (right-click on tile) -------
(function(){
  const menu = document.getElementById('ctxmenu');
  let lastTile = null;

  function showMenu(x,y){ menu.style.left=x+'px'; menu.style.top=y+'px'; menu.style.display='block'; }
  function hideMenu(){ menu.style.display='none'; lastTile=null; }

  document.addEventListener('contextmenu', (e)=>{
    const t = e.target.closest('.map-tile');
    if (!t) return;
    e.preventDefault();
    lastTile = t;
    showMenu(e.clientX, e.clientY);
  });
  document.addEventListener('click', (e)=>{ if (!menu.contains(e.target)) hideMenu(); });

  menu.addEventListener('click', (e)=>{
    const act = e.target.getAttribute('data-act'); if (!act || !lastTile) return;
    const d = JSON.parse(lastTile.dataset.payload || '{}');
    if (act==='copyName')   navigator.clipboard?.writeText(d.regionName || '');
    if (act==='copyUUID')   navigator.clipboard?.writeText(d.uuid || '');
    if (act==='copyCoords') navigator.clipboard?.writeText(`${d.x ?? ''},${d.y ?? ''}`);
    if (act==='recenter')  {
      const cx = d.x ?? <?php echo (int)$centerX; ?>;
      const cy = d.y ?? <?php echo (int)$centerY; ?>;
      const url = new URL(location.href);
      url.searchParams.set('cx', cx);
      url.searchParams.set('cy', cy);
      if (!url.searchParams.has('meters')) {
        url.searchParams.set('meters', <?php echo $mapInMeters ? '1' : '0'; ?>);
      }
      location.href = url.toString();
    }
    hideMenu();
  });
})();
</script>

<?php include_once 'include/footer.php'; ?>
