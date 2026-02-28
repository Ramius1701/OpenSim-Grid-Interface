<?php
$title = "Events Admin";
include_once __DIR__ . '/../include/config.php';
include_once __DIR__ . '/../include/env.php';
include_once __DIR__ . '/../include/auth.php';
include_once __DIR__ . '/../include/file_store.php';
include_once __DIR__ . '/../include/header.php';

require_admin();
$path = PATH_EVENTS_JSON;
$events = [];
if (is_file($path)) {
    $events = json_decode(file_get_contents($path), true) ?: [];
}
$action = $_POST['action'] ?? '';
if ($action === 'save') {
    $e = [
      'id' => $_POST['id'] ?: uniqid('evt_'),
      'title' => trim($_POST['title'] ?? ''),
      'dateUTC' => trim($_POST['dateUTC'] ?? ''),
      'duration' => (int)($_POST['duration'] ?? 60),
      'cover' => trim($_POST['cover'] ?? ''),
      'desc' => trim($_POST['desc'] ?? ''),
      'region' => trim($_POST['region'] ?? ''),
      'pos' => trim($_POST['pos'] ?? '128,128,25'),
    ];
    // upsert by id
    $found = false;
    foreach ($events as &$x) { if (($x['id'] ?? '') === $e['id']) { $x = $e; $found=true; break; } }
    if (!$found) $events[] = $e;
    if (safe_write_json($path, $events)) { echo '<div class="card">Saved.</div>'; } else { echo '<div class="card danger">Save failed.</div>'; }
}
if ($action === 'delete') {
    $id = $_POST['id'] ?? '';
    $events = array_values(array_filter($events, fn($x)=>($x['id'] ?? '') !== $id));
    if (safe_write_json($path, $events)) { echo '<div class="card">Deleted.</div>'; } else { echo '<div class="card danger">Delete failed.</div>'; }
}

echo '<div class="card"><h1>Events</h1>';
echo '<form method="post" class="toolbar"><input type="hidden" name="action" value="save" />';
echo '<input name="id" placeholder="id (blank=new)" />';
echo '<input name="title" placeholder="title" required />';
echo '<input name="dateUTC" placeholder="YYYY-MM-DD HH:MM UTC" required />';
echo '<input name="duration" type="number" min="0" step="15" value="60" />';
echo '<input name="region" placeholder="Region" />';
echo '<input name="pos" placeholder="x,y,z" value="128,128,25" />';
echo '<input name="cover" placeholder="cover URL (optional)" style="min-width:260px" />';
echo '<input name="desc" placeholder="description" style="min-width:260px" />';
echo '<button>Save</button></form>';

echo '<table><thead><tr><th>When</th><th>Title</th><th>Region</th><th>Pos</th><th></th></tr></thead><tbody>';
foreach ($events as $e) {
  $id = htmlspecialchars($e['id'] ?? '');
  $when = htmlspecialchars($e['dateUTC'] ?? '');
  $title = htmlspecialchars($e['title'] ?? '');
  $reg = htmlspecialchars($e['region'] ?? '');
  $pos = htmlspecialchars($e['pos'] ?? '');
  echo "<tr><td>$when</td><td>$title</td><td>$reg</td><td class=\"muted\">$pos</td><td><form method=\"post\" class=\"actions\"><input type=\"hidden\" name=\"action\" value=\"delete\"><input type=\"hidden\" name=\"id\" value=\"$id\"><button class=\"danger\">Delete</button></form></td></tr>";
}
echo '</tbody></table></div>';

include_once __DIR__ . '/../include/footer.php';
