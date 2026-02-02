<?php
/**
 * CASPERIA IN-WORLD GUIDE (Viewer Optimized)
 * Updated: Badges moved to Card Body (Matches Website)
 */

ob_start();
require_once __DIR__ . "/include/config.php";
require_once __DIR__ . "/include/utils.php";

$envPath = __DIR__ . "/include/env.php";
if (is_file($envPath)) {
    require_once $envPath;
}
ob_end_clean();

$mysqli = function_exists('db') ? db() : null;

define('IMAGE_DIR', 'region_images'); 

$CATEGORY_MAP = [
    3 => "Arts & Culture", 4 => "Business", 5 => "Education", 
    6 => "Gaming", 7 => "Hangout", 8 => "Newcomer", 
    9 => "Parks & Nature", 10 => "Residential", 11 => "Shopping", 
    13 => "Other", 14 => "Rental"
];

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function get_region_image($regionName) {
    $safeName = str_replace(['/', '\\'], '', $regionName);
    $base = IMAGE_DIR . '/' . $safeName;
    if (file_exists($base . '.jpg')) return $base . '.jpg';
    if (file_exists($base . '.png')) return $base . '.png';
    return false;
}

function make_viewer_link($region, $landing = "") {
    $regionEnc = rawurlencode($region);
    if (empty($landing)) {
        return "secondlife:///app/teleport/{$regionEnc}/128/128/25";
    }
    $coords = preg_split('/[\/\s]+/', trim((string)$landing));
    $x = $coords[0] ?? 128;
    $y = $coords[1] ?? 128;
    $z = $coords[2] ?? 25;
    return "secondlife:///app/teleport/{$regionEnc}/{$x}/{$y}/{$z}";
}

$popular  = [];
$featured = [];
$discover = [];

if ($mysqli) {
    // A. POPULAR
    $sqlPop = "SELECT r.regionname, p.parcelname, p.landingpoint, p.description, p.dwell, p.searchcategory
               FROM search_parcels p 
               JOIN search_regions r ON BINARY r.regionUUID = BINARY p.regionUUID 
               ORDER BY p.dwell DESC LIMIT 30";
    $res = $mysqli->query($sqlPop);
    if (!$res) {
        $sqlPop = str_replace("search_regions", "regions", $sqlPop);
        $sqlPop = str_replace("r.regionname", "r.regionName", $sqlPop);
        $res = $mysqli->query($sqlPop);
    }
    if ($res) while($row = $res->fetch_assoc()) $popular[] = $row;

    // B. FEATURED
    $sqlFeat = "SELECT r.regionname, p.parcelname, p.landingpoint, p.description, p.searchcategory 
                FROM search_parcels p 
                JOIN search_regions r ON BINARY r.regionUUID = BINARY p.regionUUID 
                WHERE p.searchcategory > 0 
                ORDER BY RAND() LIMIT 30";
    $res = $mysqli->query($sqlFeat);
    if (!$res) {
        $sqlFeat = str_replace("search_regions", "regions", $sqlFeat);
        $sqlFeat = str_replace("r.regionname", "r.regionName", $sqlFeat);
        $res = $mysqli->query($sqlFeat);
    }
    if ($res) while($row = $res->fetch_assoc()) $featured[] = $row;

    // C. DISCOVER
    $sqlDisc = "SELECT regionName FROM regions ORDER BY regionName ASC LIMIT 50";
    $res = $mysqli->query($sqlDisc);
    if (!$res || $res->num_rows == 0) {
        $res = $mysqli->query("SELECT regionName FROM gridregions ORDER BY regionName ASC LIMIT 50");
    }
    if ((!$res || $res->num_rows == 0)) {
         $res = $mysqli->query("SELECT regionname AS regionName FROM search_regions ORDER BY regionname ASC LIMIT 50");
    }
    if ($res) while($row = $res->fetch_assoc()) $discover[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Guide</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 0;
            background-color: #1a1a1a; 
            color: #eeeeee;
            font-family: 'Segoe UI', Tahoma, sans-serif;
            font-size: 13px;
            height: 100vh;
            display: flex; flex-direction: column; overflow: hidden;
        }

        .header {
            flex: 0 0 auto; background: #252525; border-bottom: 1px solid #333;
            padding: 8px 10px; display: flex; justify-content: space-between; align-items: center;
        }
        .brand { font-weight: 600; color: #ccc; display: flex; align-items: center; gap: 5px; }

        .nav-tabs { display: flex; background: #111; padding: 2px; border-radius: 4px; gap: 2px; }
        .nav-btn {
            background: transparent; border: none; color: #777;
            padding: 4px 10px; border-radius: 3px; cursor: pointer; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .nav-btn.active { background: #3a3a3a; color: #fff; font-weight: 600; }

        .viewport { flex: 1 1 auto; overflow-y: auto; padding: 10px; }
        .viewport::-webkit-scrollbar { width: 8px; }
        .viewport::-webkit-scrollbar-track { background: #1a1a1a; }
        .viewport::-webkit-scrollbar-thumb { background: #444; border-radius: 4px; border: 2px solid #1a1a1a; }

        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; }
        
        .card {
            background: #2b2b2b; border: 1px solid #363636; border-radius: 4px;
            overflow: hidden; display: flex; flex-direction: column;
            transition: transform 0.1s, border-color 0.1s;
        }
        .card:hover { border-color: #555; transform: translateY(-1px); }

        .card-img {
            height: 85px; 
            display: flex; align-items: center; justify-content: center;
            background-size: cover; background-position: center;
            font-weight: bold; font-size: 24px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
        }

        .card-body { padding: 8px; flex: 1; display: flex; flex-direction: column; }
        
        .card-title { font-weight: 600; font-size: 12px; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #f0f0f0; }
        
        .card-sub { font-size: 10px; color: #888; margin-bottom: 6px; }

        /* Metadata Badge Row */
        .meta-row {
            display: flex; align-items: center; gap: 8px; margin-bottom: 8px;
        }
        .badge {
            background: #444; color: #ccc;
            padding: 2px 5px; border-radius: 3px; font-size: 10px;
        }
        .traffic-info {
            font-size: 10px; color: #888; display: flex; align-items: center; gap: 3px;
        }

        .card-desc {
            font-size: 11px; color: #aaa; margin-bottom: 8px;
            height: 2.4em; overflow: hidden; line-height: 1.2;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
        }

        .btn-tp {
            margin-top: auto; display: block; text-align: center;
            background: #333; color: #ccc; text-decoration: none;
            padding: 5px; border-radius: 3px; font-size: 11px;
            border: 1px solid #3a3a3a;
        }
        .btn-tp:hover { background: #0078d7; color: white; border-color: #0078d7; }

        .view-section { display: none; animation: fadeIn 0.2s; }
        .view-section.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        
        .empty { text-align: center; color: #555; padding: 40px; font-style: italic; }
        .err { color: #f87171; padding: 20px; text-align: center; }
    </style>
</head>
<body>

    <div class="header">
        <div class="brand">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M8 16.016a7.5 7.5 0 0 0 1.962-14.74A7.5 7.5 0 0 0 8 1.269a7.5 7.5 0 0 0-1.962 14.74A7.5 7.5 0 0 0 8 16.016zm0-13a5.5 5.5 0 1 1 0 11 5.5 5.5 0 0 1 0-11z"/><path d="m6.94 7.44 4.95-2.83-2.83 4.95-4.949 2.83 2.828-4.95z"/></svg>
            <span>Guide</span>
        </div>
        <div class="nav-tabs">
            <button class="nav-btn active" onclick="setTab('popular')" id="btn-popular">Popular</button>
            <button class="nav-btn" onclick="setTab('featured')" id="btn-featured">Featured</button>
            <button class="nav-btn" onclick="setTab('discover')" id="btn-discover">Discover</button>
        </div>
    </div>

    <div class="viewport">
        <?php if (!$mysqli): ?>
            <div class="err">Database Connection Failed</div>
        <?php else: ?>

            <div id="view-popular" class="view-section active">
                <?php if (empty($popular)): ?>
                    <div class="empty">No popular regions found.</div>
                <?php else: ?>
                    <div class="grid">
                        <?php foreach ($popular as $row): 
                            $tp = make_viewer_link($row['regionname'], $row['landingpoint']);
                            $localImg = get_region_image($row['regionname']);
                            $catName = $CATEGORY_MAP[$row['searchcategory']] ?? 'General';
                            $hue = crc32($row['regionname']) % 360;
                            $bgStyle = "background-color: hsl({$hue}, 30%, 25%); color: hsl({$hue}, 80%, 70%);";
                            if ($localImg) $bgStyle .= " background-image: url('".h($localImg)."');";
                        ?>
                        <div class="card">
                            <div class="card-img" style="<?= $bgStyle ?>">
                                <?= !$localImg ? h(substr($row['parcelname'], 0, 1)) : '' ?>
                            </div>
                            
                            <div class="card-body">
                                <div class="card-title"><?= h($row['parcelname']) ?></div>
                                <div class="card-sub"><?= h($row['regionname']) ?></div>

                                <div class="meta-row">
                                    <span class="badge"><?= h($catName) ?></span>
                                    <?php if ($row['dwell'] > 0): ?>
                                        <span class="traffic-info">
                                            <span style="color:#0078d7;">&#9679;</span> Traffic: <?= number_format($row['dwell']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="card-desc"><?= h($row['description']) ?></div>
                                <a href="<?= h($tp) ?>" class="btn-tp">Teleport</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div id="view-featured" class="view-section">
                <?php if (empty($featured)): ?>
                    <div class="empty">No featured regions found.</div>
                <?php else: ?>
                    <div class="grid">
                        <?php foreach ($featured as $row): 
                            $tp = make_viewer_link($row['regionname'], $row['landingpoint']);
                            $localImg = get_region_image($row['regionname']);
                            $catName = $CATEGORY_MAP[$row['searchcategory']] ?? 'General';
                            $hue = crc32($row['regionname']) % 360;
                            $bgStyle = "background-color: hsl({$hue}, 30%, 25%); color: hsl({$hue}, 80%, 70%);";
                            if ($localImg) $bgStyle .= " background-image: url('".h($localImg)."');";
                        ?>
                        <div class="card">
                            <div class="card-img" style="<?= $bgStyle ?>">
                                <?= !$localImg ? h(substr($row['parcelname'], 0, 1)) : '' ?>
                            </div>
                            
                            <div class="card-body">
                                <div class="card-title"><?= h($row['parcelname']) ?></div>
                                <div class="card-sub"><?= h($row['regionname']) ?></div>
                                
                                <div class="meta-row">
                                    <span class="badge"><?= h($catName) ?></span>
                                </div>

                                <div class="card-desc"><?= h($row['description']) ?></div>
                                <a href="<?= h($tp) ?>" class="btn-tp">Teleport</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div id="view-discover" class="view-section">
                <?php if (empty($discover)): ?>
                    <div class="empty">No online regions found.</div>
                <?php else: ?>
                    <div class="grid">
                        <?php foreach ($discover as $row): 
                            $tp = make_viewer_link($row['regionName'], ""); 
                            $localImg = get_region_image($row['regionName']);
                            $hue = crc32($row['regionName']) % 360;
                            $bgStyle = "background-color: hsl({$hue}, 30%, 25%); color: hsl({$hue}, 80%, 70%);";
                            if ($localImg) $bgStyle .= " background-image: url('".h($localImg)."');";
                        ?>
                        <div class="card">
                            <div class="card-img" style="<?= $bgStyle ?>">
                                <?= !$localImg ? h(substr($row['regionName'], 0, 1)) : '' ?>
                            </div>
                            
                            <div class="card-body">
                                <div class="card-title"><?= h($row['regionName']) ?></div>
                                <div class="card-sub">Online Region</div>
                                
                                <div class="meta-row">
                                    <span class="badge">Online</span>
                                </div>

                                <a href="<?= h($tp) ?>" class="btn-tp">Teleport</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>

    <script>
        function setTab(name) {
            document.querySelectorAll('.view-section').forEach(el => el.classList.remove('active'));
            document.getElementById('view-' + name).classList.add('active');
            document.querySelectorAll('.nav-btn').forEach(el => el.classList.remove('active'));
            document.getElementById('btn-' + name).classList.add('active');
        }
    </script>
</body>
</html>