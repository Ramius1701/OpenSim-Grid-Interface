<?php
// casperia/gridmap.php  – drop-in replacement
// 1.  do all PHP first  2.  emit HTML only when ready

$title = 'Grid Map';

require_once __DIR__ . '/../include/config.php';   // loads env.php for you

/* ---------- 1.  DB ---------- */
$dbHost = $DB['host']    ?? $DB['server'] ?? (defined('DB_SERVER')   ? DB_SERVER   : '127.0.0.1');
$dbUser = $DB['user']    ?? $DB['username'] ?? (defined('DB_USERNAME') ? DB_USERNAME : 'root');
$dbPass = $DB['pass']    ?? $DB['password'] ?? (defined('DB_PASSWORD') ? DB_PASSWORD : '');
$dbName = $DB['name']    ?? $DB['database'] ?? (defined('DB_NAME')     ? DB_NAME     : '');
$dbPort = (int)($DB['port'] ?? (defined('DB_PORT') ? DB_PORT : 3306));

if (!$dbName) {
    http_response_code(500);
    die('<h3>Configuration error: database name missing in env.php / config.php</h3>');
}

/* ---------- 2.  VIEWPORT ---------- */
$cx = isset($_GET['cx']) ? (int)$_GET['cx'] : (defined('MAP_CENTER_X') ? (int)MAP_CENTER_X : 1000);
$cy = isset($_GET['cy']) ? (int)$_GET['cy'] : (defined('MAP_CENTER_Y') ? (int)MAP_CENTER_Y : 1000);
$w  = isset($_GET['w'])  ? max(4,(int)$_GET['w'])  : (defined('MAP_TILES_X') ? (int)MAP_TILES_X : 32);
$h  = isset($_GET['h'])  ? max(4,(int)$_GET['h'])  : (defined('MAP_TILES_Y') ? (int)MAP_TILES_Y : 32);
$w = $h = 4 * (int)round($w / 4);                 // keep even for zoom symmetry
$focusRegion = isset($_GET['region']) ? trim($_GET['region']) : '';

/* ---------- 3.  COLOURS ---------- */
function cfg_color(array $names, string $def): string {
    foreach ($names as $c) if (defined($c) && constant($c) !== '') return (string)constant($c);
    return $def;
}
$C_OPENSPACE = cfg_color(['MAP_COLOR_OPENSPACE','OPENSPACE_COLOR'], '#dae90eff');
$C_HOMESTEAD = cfg_color(['MAP_COLOR_HOMESTEAD','HOMESTEAD_COLOR'], '#a855f7');
$C_STANDARD  = cfg_color(['MAP_COLOR_STANDARD','MAP_COLOR_SINGLE','BESCHLAGT_COLOR','HEADER_COLOR'], '#22c55e');
$C_VAR       = cfg_color(['MAP_COLOR_VAR','VARREGION_COLOR','PRIMARY_COLOR'],   '#16a34a');
$C_FREE      = cfg_color(['MAP_COLOR_FREE','FREI_COLOR','LINK_COLOR','ACCENT_COLOR'], '#3b82f6');
$C_CENTER    = cfg_color(['MAP_COLOR_CENTER','CENTER_COLOR','HIGHLIGHT_COLOR'], '#f59e0b');
$px = defined('MAP_TILE_PX') ? (int)MAP_TILE_PX
    : (defined('TILE_SIZE')  ? (int)preg_replace('/\D+/', '', TILE_SIZE) ?: 25 : 25);

/* ---------- 4.  CONNECT ---------- */
try {
    $pdo = new PDO("mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4",
                   $dbUser, $dbPass,
                   [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Throwable $e) {
    http_response_code(500);
    die('<h3>DB connection failed: '.htmlspecialchars($e->getMessage()).'</h3>');
}

/* ---------- 5.  TABLE / COLUMNS ---------- */
$tables = ['GridRegions','gridregions','regions','Regions'];
$in     = implode(',', array_fill(0, count($tables), '?'));
$stmt   = $pdo->prepare("SHOW TABLES FROM `$dbName` WHERE Tables_in_$dbName IN ($in)");
$stmt->execute($tables);
$found  = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
$table  = $found[0] ?? null;
if (!$table) {
    http_response_code(500);
    die('<h3>No region table found (tried GridRegions/regions).</h3>');
}
$cols = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN, 0);
$find = fn(...$n) => array_values(array_intersect($cols, $n))[0] ?? null;

$colUUID = $find('RegionUUID','UUID','uuid');
$colName = $find('RegionName','regionName','name');
$colLocX = $find('LocX','locX','xloc','X');
$colLocY = $find('LocY','locY','yloc','Y');
if (!$colUUID || !$colName || !$colLocX || !$colLocY) {
    http_response_code(500);
    die('<h3>Mandatory region columns missing in '.$table.'.</h3>');
}
$colSrvURI = $find('ServerURI','serverURI','serverUrl','serverURL','server_uri');
$colSizeX  = $find('SizeX','sizeX','sizex');
$colSizeY  = $find('SizeY','sizeY','sizey');
$colType   = $find('RegionType','regionType','ProductType','productType','Type','type');

/* ---------- 6.  WINDOW BOUNDS + FETCH ---------- */
$startX = $cx - intdiv($w, 2); $endX = $startX + $w - 1;
$startY = $cy - intdiv($h, 2); $endY = $startY + $h - 1;

$fields = [
    'uuid'       => "`$colUUID`",
    'regionName' => "`$colName`",
    'locX'       => "`$colLocX`",
    'locY'       => "`$colLocY`",
    'serverURI'  => $colSrvURI ? "`$colSrvURI`" : "''",
    'sizeX'      => $colSizeX  ? "`$colSizeX`"  : '256',
    'sizeY'      => $colSizeY  ? "`$colSizeY`"  : '256',
    'regionType' => $colType   ? "`$colType`"   : "''",
];
$select = 'SELECT '.implode(', ', array_map(
    fn($expr, $alias) => "$expr AS `$alias`", $fields, array_keys($fields)))
. " FROM `$table` WHERE `$colLocX` BETWEEN :x1 AND :x2 AND `$colLocY` BETWEEN :y1 AND :y2";

$stmt = $pdo->prepare($select);
$stmt->execute([':x1'=>$startX*256, ':x2'=>$endX*256, ':y1'=>$startY*256, ':y2'=>$endY*256]);
$rows = $stmt->fetchAll();

/* ---------- 7.  NORMALISE & CLASSIFY ---------- */
$grid = []; $focusUUID = null;
$gridCenterX = defined('MAP_CENTER_X') ? (int)MAP_CENTER_X : 1000;
$gridCenterY = defined('MAP_CENTER_Y') ? (int)MAP_CENTER_Y : 1000;

function classify(array $r): string {
    $t = strtolower(trim($r['regionType']));
    if (str_contains($t,'open'))  return 'openspace';
    if (str_contains($t,'home'))  return 'homestead';
    if (str_contains($t,'standard')||str_contains($t,'full')||str_contains($t,'main')) return 'standard';
    return ((int)$r['sizeX']>256||(int)$r['sizeY']>256) ? 'var' : 'standard';
}

foreach ($rows as $r) {
    $gx = (int)($r['locX'] / 256);
    $gy = (int)($r['locY'] / 256);
    $sx = max(256, (int)$r['sizeX']);
    $sy = max(256, (int)$r['sizeY']);
    $tx = max(1, intdiv($sx, 256));
    $ty = max(1, intdiv($sy, 256));
    $type = classify($r);

    for ($dy=0; $dy<$ty; $dy++) for ($dx=0; $dx<$tx; $dx++) {
        $grid[$gx+$dx][$gy+$dy] = [
            'name'=>$r['regionName'],
            'uuid'=>$r['uuid'],
            'sx'=>$sx,'sy'=>$sy,
            'type'=>$type,
            'root'=>!$dx&&!$dy
        ];
    }
    if ($focusRegion !== '' && !strcasecmp($r['regionName'], $focusRegion)) {
        if (!isset($_GET['cx']) && !isset($_GET['cy'])) { $cx=$gx; $cy=$gy; }
        $focusUUID = $r['uuid'];
    }
}

/* ---------- 8.  TELEPORT HELPERS ---------- */
function slapp(string $region, int $x=128, int $y=128, int $z=25): string {
    $region = str_replace(' ', '%20', $region);
    return "secondlife://{$region}/{$x}/{$y}/{$z}";
}
function hop(string $hgHost, string $region, int $x=128, int $y=128, int $z=25): string {
    $region = str_replace(' ', '%20', $region);
    return "hop://{$hgHost}/{$region}/{$x}/{$y}/{$z}";
}
$HG = defined('HG_HOST') ? HG_HOST : (defined('GRID_URI') ? GRID_URI : '');

/* ---------- 9.  CACHE HEADER (before ANY output) ---------- */
header('Cache-Control: public, max-age=300');

/* ---------- 10.  HTML ---------- */
require_once __DIR__ . '/../include/header.php';
?>
<style>
  .map-wrap{width:min(1200px,96vw);margin:0 auto;padding:1.2rem;display:grid;gap:1rem}
  .map-subcard{width:100%;margin:0 auto;padding:1rem;display:flex;flex-direction:column;align-items:center;gap:.6rem}
  .map-controls{display:flex;flex-wrap:wrap;gap:.6rem 1rem;align-items:end;justify-content:center}
  .map-controls label{font-size:.9rem;display:flex;flex-direction:column;gap:.25rem}
  .map-controls input{padding:.35rem .5rem;border-radius:8px;border:1px solid rgba(0,0,0,.25)}
  .legend{display:flex;flex-wrap:wrap;gap:.6rem 1rem;font-size:.9rem;justify-content:center}
  .legend .item{display:inline-flex;align-items:center;gap:.35rem}
  .legend .swatch{width:14px;height:14px;border-radius:3px;border:1px solid rgba(0,0,0,.25)}
  .map-grid-wrap{width:100%;overflow:auto;display:flex;justify-content:center}
  .map-grid{display:grid;gap:1px;padding:4px;border-radius:12px;
    background-image:linear-gradient(to right,color-mix(in srgb,var(--primary-color),transparent 88%) 1px,transparent 1px),
                     linear-gradient(to bottom,color-mix(in srgb,var(--primary-color),transparent 88%) 1px,transparent 1px);
    background-size:var(--map-tile) var(--map-tile);background-position:center center}
  .map-tile{width:var(--map-tile);height:var(--map-tile);border-radius:2px;border:1px solid rgba(0,0,0,.2);
    transition:transform .12s,box-shadow .12s;position:relative}
  .map-tile.center{outline:2px solid currentColor;outline-offset:-2px;transform:scale(1.06);z-index:2}
  .map-tile.focus-region{box-shadow:0 0 0 2px <?=$C_CENTER?> inset;border-color:<?=$C_CENTER?>}
  .map-tile:hover{box-shadow:0 0 0 1px rgba(255,255,255,.6)}
</style>

<div class="container-fluid mt-4 mb-4">
  <div class="row">
    <div class="col-md-3">
      <div class="card mb-3"><div class="card-header"><h5 class="mb-0"><i class="bi bi-map me-1"></i> Grid Map</h5></div><div class="card-body">
        <form class="map-controls" method="get" action="<?=htmlspecialchars($_SERVER['PHP_SELF'])?>" id="mapForm">
          <!-- hidden fields carry the current values unless a button overrides them -->
          <input type="hidden" name="cx" value="<?=$cx?>" id="cx">
          <input type="hidden" name="cy" value="<?=$cy?>" id="cy">
          <input type="hidden" name="w"  value="<?=$w?>"  id="w">
          <input type="hidden" name="h"  value="<?=$h?>"  id="h">

          <!-- Center X -->
          <label>Center X
            <div class="input-group input-group-sm">
              <button class="btn btn-outline-secondary" type="submit"
                onclick="this.form.cx.value=<?=$cx-1?>">−</button>
              <span class="input-group-text"><?=$cx?></span>
              <button class="btn btn-outline-secondary" type="submit"
                onclick="this.form.cx.value=<?=$cx+1?>">+</button>
            </div>
          </label>

          <!-- Center Y -->
          <label>Center Y
            <div class="input-group input-group-sm">
              <button class="btn btn-outline-secondary" type="submit"
                onclick="this.form.cy.value=<?=$cy-1?>">−</button>
              <span class="input-group-text"><?=$cy?></span>
              <button class="btn btn-outline-secondary" type="submit"
                onclick="this.form.cy.value=<?=$cy+1?>">+</button>
            </div>
          </label>

          <!-- Width --><!--
          <label>Width
            <div class="input-group input-group-sm">
              <button class="btn btn-outline-secondary" type="submit"
                onclick="this.form.w.value=<?=max(4,$w-4)?>">−</button>
              <span class="input-group-text"><?=$w?></span>
              <button class="btn btn-outline-secondary" type="submit"
                onclick="this.form.w.value=<?=$w+4?>">+</button>
            </div>
          </label>-->

          <!-- Height --><!--
          <label>Height
            <div class="input-group input-group-sm">
              <button class="btn btn-outline-secondary" type="submit"
                onclick="this.form.h.value=<?=max(4,$h-4)?>">−</button>
              <span class="input-group-text"><?=$h?></span>
              <button class="btn btn-outline-secondary" type="submit"
                onclick="this.form.h.value=<?=$h+4?>">+</button>
            </div>
          </label>-->

          <!-- Preset sizes --><!--
          <div class="btn-group btn-group-sm w-100">
            <button class="btn btn-outline-primary" type="submit"
              onclick="this.form.w.value=32;this.form.h.value=32">32×32</button>
            <button class="btn btn-outline-primary" type="submit"
              onclick="this.form.w.value=64;this.form.h.value=64">64×64</button>
            <button class="btn btn-outline-primary" type="submit"
              onclick="this.form.w.value=128;this.form.h.value=128">128×128</button>
          </div>-->

          <!-- Reset -->
          <a class="btn btn-sm btn-secondary w-100" href="<?=htmlspecialchars($_SERVER['PHP_SELF'])?>">Reset view</a>
        </form>

<!-- helper: turn “wh” into both w & h -->
<?php if (isset($_GET['wh'])):?>
  <input type="hidden" name="w" value="<?=max(4,(int)$_GET['wh'])?>">
  <input type="hidden" name="h" value="<?=max(4,(int)$_GET['wh'])?>">
<?php endif;?>
      </div></div>

      <div class="card mb-3"><div class="card-header"><h6 class="mb-0"><i class="bi bi-info-circle me-1"></i> Region Details</h6></div><div class="card-body"><div class="map-details">
        <p class="text-muted" id="map-details-hint">Hover over a region tile to see details.</p>
        <div id="map-details-body"><p class="text-muted mb-0">No region selected.</p></div>
      </div></div></div>

      <div class="card"><div class="card-header"><h6 class="mb-0"><i class="bi bi-palette me-1"></i> Legend</h6></div><div class="card-body small">
        <div class="legend">
          <div class="item"><span class="swatch" style="background:<?=$C_OPENSPACE?>"></span> Openspace</div>
          <div class="item"><span class="swatch" style="background:<?=$C_HOMESTEAD?>"></span> Homestead</div>
          <div class="item"><span class="swatch" style="background:<?=$C_STANDARD?>"></span> Standard</div>
          <div class="item"><span class="swatch" style="background:<?=$C_VAR?>"></span> Variable</div>
          <div class="item"><span class="swatch" style="background:<?=$C_FREE?>"></span> Free</div>
          <div class="item"><span class="swatch" style="background:<?=$C_CENTER?>"></span> Grid Center</div>
        </div>
      </div></div>
    </div><!-- /col-md-3 -->

    <div class="col-md-9">
      <div class="card"><div class="card-header d-flex justify-content-between align-items-center">
        <div><h5 class="mb-0"><i class="bi bi-grid-3x3-gap me-1"></i> Map</h5><div class="small">
          Center: <strong><?=htmlspecialchars("$cx,$cy")?></strong> ·
          Window: <strong><?=$w?>×<?=$h?></strong>
          <?php if ($focusRegion!==''):?> · Region: <strong><?=htmlspecialchars($focusRegion)?></strong><?php endif;?>
        </div></div>
      </div><div class="card-body"><div class="map-grid-wrap"><div class="map-grid" style="--map-tile:<?=$px?>px;grid-template-columns:repeat(<?=$w?>,var(--map-tile))">
<?php
for ($y=$endY; $y>=$startY; $y--) {
  for ($x=$startX; $x<=$endX; $x++) {
    $isCenter = ($x===$cx && $y===$cy);
    $isGridC  = ($x===$gridCenterX && $y===$gridCenterY);
    $cell = $grid[$x][$y] ?? null;

    if ($cell) {
      $bg = match($cell['type']){
        'openspace'=>$C_OPENSPACE,'homestead'=>$C_HOMESTEAD,
        'var'=>$C_VAR,'standard'=>$C_STANDARD,$isGridC=>$C_CENTER,
        default=>$C_STANDARD
      };
      $title = "($x,$y) • {$cell['name']} • {$cell['sx']}×{$cell['sy']}";
      $tp    = $HG ? hop($HG,$cell['name']) : slapp($cell['name']);
      $cls   = 'map-tile'.($isCenter?' center':'').($focusUUID&&$cell['uuid']===$focusUUID?' focus-region':'');
      echo '<a class="'.htmlspecialchars($cls).'" href="'.htmlspecialchars($tp).'"'
          .' style="background:'.htmlspecialchars($bg).'"'
          .' data-region-name="'.htmlspecialchars($cell['name'],ENT_QUOTES).'"'
          .' data-coords="'.htmlspecialchars("$x,$y",ENT_QUOTES).'"'
          .' data-size="'.htmlspecialchars("{$cell['sx']}×{$cell['sy']}",ENT_QUOTES).'"'
          .' data-type="'.htmlspecialchars($cell['type'],ENT_QUOTES).'"'
          .' data-teleport="'.htmlspecialchars($tp,ENT_QUOTES).'"'
          .' data-is-center="'.($isCenter?1:0).'"'
          .' title="'.htmlspecialchars($title,ENT_QUOTES).'"></a>';
    } else {
      $bg = $isGridC ? $C_CENTER : $C_FREE;
      echo '<div class="map-tile'.($isCenter?' center':'').'" style="background:'.htmlspecialchars($bg).'"'
          .' data-region-name="Free region" data-coords="'.htmlspecialchars("$x,$y",ENT_QUOTES).'"'
          .' data-size="256×256" data-type="free" data-teleport="" data-is-center="'.($isCenter?1:0).'"></div>';
    }
  }
}
?>
      </div></div></div></div>
    </div><!-- /col-md-9 -->
  </div><!-- /row -->
</div><!-- /container-fluid -->

<script>
document.addEventListener('DOMContentLoaded',()=>{
  const detailsBody=document.getElementById('map-details-body');
  const detailsHint=document.getElementById('map-details-hint');
  if(!detailsBody)return;

  function render(tile){
    const name=tile.dataset.regionName||'Unknown';
    const coord=tile.dataset.coords||'';
    const size =tile.dataset.size||'';
    const type =tile.dataset.type||'';
    const tp   =tile.dataset.teleport||'';
    let html='<p><strong>'+name.escape()+'</strong></p>';
    if(coord) html+='<p>Grid coords: '+coord.escape()+'</p>';
    if(size)  html+='<p>Size: '+size.escape()+'</p>';
    if(type)  html+='<p>Type: '+type.escape()+'</p>';
    //if(tp)    html+='<p class="mb-0"><a href="'+tp.escape()+'" target="_blank">Teleport / Open in viewer</a></p>';
    detailsBody.innerHTML=html;
    detailsHint.textContent='Hover another tile to inspect a different region.';
  }
  // initial tile
  const initial=document.querySelector('.map-tile[data-region-name][data-is-center="1"]')||document.querySelector('.map-tile[data-region-name]');
  if(initial) render(initial);

  // event delegation
  document.querySelector('.map-grid').addEventListener('pointerover',e=>{
    const t=e.target.closest('.map-tile[data-region-name]');
    if(t) render(t);
  });
});
String.prototype.escape=function(){const t=document.createElement('span');t.textContent=this;return t.innerHTML;};
</script>
<?php require_once __DIR__ . '/../include/footer.php'; ?>