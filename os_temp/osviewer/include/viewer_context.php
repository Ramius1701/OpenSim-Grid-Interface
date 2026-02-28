<?php
function os_detect_viewer(): bool {
    foreach (['HTTP_X_SECONDLIFE_OWNER_NAME','HTTP_X_SECONDLIFE_REGION','HTTP_X_SECONDLIFE_SHARD'] as $h) {
        if (!empty($_SERVER[$h])) return true;
    }
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $needles = ['Firestorm', 'Second Life', 'SLViewer', 'Kokua', 'Cool VL', 'Singularity', 'Black Dragon', 'Dayturn', 'Alchemy'];
    foreach ($needles as $n) { if (stripos($ua, $n) !== false) return true; }
    if (isset($_GET['view'])) {
        $v = strtolower((string)$_GET['view']);
        if (in_array($v, ['viewer','web'], true)) { setcookie('view', $v, 0, '/'); return $v === 'viewer'; }
    }
    if (!empty($_COOKIE['view'])) return strtolower((string)$_COOKIE['view']) === 'viewer';
    return false;
}
$IS_VIEWER = os_detect_viewer();
