<?php
include_once __DIR__ . "/include/config.php";

if (file_exists(__DIR__ . "/include/viewer_context.php")) {
    include_once __DIR__ . "/include/viewer_context.php";
}

$con = db(); // optional

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function clamp_int($v, int $min, int $max, int $fallback): int {
    if (!is_numeric($v)) return $fallback;
    $n = (int)$v;
    if ($n < $min) return $min;
    if ($n > $max) return $max;
    return $n;
}

/* Inputs */
$region = trim((string)($_GET['region'] ?? ''));
$x = clamp_int($_GET['x'] ?? 128, 0, 255, 128);
$y = clamp_int($_GET['y'] ?? 128, 0, 255, 128);
$z = clamp_int($_GET['z'] ?? 25,  0, 4096, 25);

$case  = trim((string)($_GET['case'] ?? 'hub'));
$label = trim((string)($_GET['label'] ?? ''));

/* Where to return after launching */
$return = (string)($_GET['return'] ?? '');

/* Prefer explicit return; else safe-referer; else hub */
function safe_return_url(string $candidate): string {
    $fallback = "ossearch.php?case=hub";

    $candidate = trim($candidate);
    if ($candidate === '') return $fallback;

    // Allow relative URLs only (prevents open redirects)
    // Examples allowed: "ossearch.php?case=events", "/casperia/ossearch.php?case=hub"
    if (preg_match('#^https?://#i', $candidate)) {
        // Disallow absolute URLs entirely
        return $fallback;
    }

    // Must start with "/" or look like a local filename/path (no scheme)
    if ($candidate[0] === '/' || preg_match('#^[a-zA-Z0-9_\-\/\.]+(\?.*)?$#', $candidate)) {
        return $candidate;
    }

    return $fallback;
}

if ($return === '') {
    // Try to use referer (but keep it safe + local)
    $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
    if ($ref !== '') {
        // Strip scheme+host if present and convert to path/query
        $u = @parse_url($ref);
        if (is_array($u) && !empty($u['path'])) {
            $refCandidate = $u['path'] . (isset($u['query']) ? ('?' . $u['query']) : '');
            $return = $refCandidate;
        }
    }
}

$return = safe_return_url($return);

if ($region === '') {
    http_response_code(400);
    echo "Missing region.";
    exit;
}

$sl = "secondlife://" . rawurlencode($region) . "/$x/$y/$z";

/* Optional click logging */
if ($con) {
    try {
        $exists = mysqli_query(
            $con,
            "SELECT 1 FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name='ws_hub_teleport_log' LIMIT 1"
        );
        if ($exists && mysqli_num_rows($exists) > 0) {
            $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
            $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);

            $stmt = mysqli_prepare(
                $con,
                "INSERT INTO ws_hub_teleport_log (region, x, y, z, case_name, label, user_agent, ip, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "siiissss", $region, $x, $y, $z, $case, $label, $ua, $ip);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
    } catch (Throwable $e) {}
    mysqli_close($con);
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Teleport</title>
<style>
    :root{ --neon:#00d2ff; --bg:#0a0c10; --edge:#1f2630; --muted:#7a8a9a; }
    body{ margin:0; background:var(--bg); color:#e0e6ed; font-family:Segoe UI, sans-serif; }
    .box{ padding:18px; }
    .title{ font-weight:900; font-size:18px; }
    .muted{ color:var(--muted); margin-top:8px; font-size:14px; }
    .btns{ margin-top:14px; display:flex; gap:10px; flex-wrap:wrap; }
    .btn{
        display:inline-block; padding:12px 14px; border-radius:12px;
        text-decoration:none; font-weight:900; font-size:14px;
    }
    .primary{ background:var(--neon); color:#000; }
    .secondary{ background:rgba(0,0,0,0.35); color:var(--neon); border:1px solid var(--edge); }
</style>
</head>
<body>
<div class="box">
    <div class="title">Launching Teleport…</div>
    <div class="muted"><?php echo h($region); ?> (<?php echo (int)$x; ?>,<?php echo (int)$y; ?>,<?php echo (int)$z; ?>)</div>
    <div class="btns">
        <a class="btn primary" href="<?php echo h($sl); ?>">Teleport</a>
        <a class="btn secondary" href="<?php echo h($return); ?>">Back</a>
    </div>
    <div class="muted" style="margin-top:12px;">
        <div class="muted" id="autoback" style="margin-top:12px;">Returning in 10s…</div>
    </div>
</div>

<script>
  const sl = <?php echo json_encode($sl); ?>;
  const back = <?php echo json_encode($return); ?>;

  // Try to launch teleport once
  try { location.href = sl; } catch (e) {}

  // Auto-back timer (give user time to click Teleport if launch fails)
  let seconds = 10; // <— adjust to taste (8–12 is a good range)
  const statusEl = document.getElementById('autoback');

  const tick = () => {
    if (!statusEl) return;
    statusEl.textContent = `Returning in ${seconds}s…`;
    seconds--;
    if (seconds < 0) {
      try { location.replace(back); } catch (e) { location.href = back; }
      return;
    }
    setTimeout(tick, 1000);
  };
  tick();
</script>

</body>
</html>
