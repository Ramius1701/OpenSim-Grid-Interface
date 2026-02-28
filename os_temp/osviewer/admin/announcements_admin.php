<?php
$title = "Announcements Admin";
include_once __DIR__ . '/../include/config.php';
include_once __DIR__ . '/../include/env.php';
include_once __DIR__ . '/../include/auth.php';
include_once __DIR__ . '/../include/file_store.php';
include_once __DIR__ . '/../include/header.php';

require_admin();
$path = PATH_ANNOUNCEMENTS_JSON;
$ann = [];
if (is_file($path)) {
    $ann = json_decode(file_get_contents($path), true) ?: [];
}
$action = $_POST['action'] ?? '';
if ($action === 'save') {
    $a = [
      'id' => $_POST['id'] ?: uniqid('ann_'),
      'title' => trim($_POST['title'] ?? ''),
      'dateUTC' => trim($_POST['dateUTC'] ?? ''),
      'text' => trim($_POST['text'] ?? ''),
      'level' => trim($_POST['level'] ?? 'info'),
      'link' => trim($_POST['link'] ?? ''),
    ];
    $found = false;
    foreach ($ann as &$x) { if (($x['id'] ?? '') === $a['id']) { $x = $a; $found=true; break; } }
    if (!$found) $ann[] = $a;
    if (safe_write_json($path, $ann)) { echo '<div class="card">Saved.</div>'; } else { echo '<div class="card danger">Save failed.</div>'; }
}
if ($action === 'delete') {
    $id = $_POST['id'] ?? '';
    $ann = array_values(array_filter($ann, fn($x)=>($x['id'] ?? '') !== $id));
    if (safe_write_json($path, $ann)) { echo '<div class="card">Deleted.</div>'; } else { echo '<div class="card danger">Delete failed.</div>'; }
}

echo '<div class="card"><h1>Announcements</h1>';
echo '<form method="post" class="toolbar"><input type="hidden" name="action" value="save" />';
echo '<input name="id" placeholder="id (blank=new)" />';
echo '<input name="title" placeholder="title" required />';
echo '<input name="dateUTC" placeholder="YYYY-MM-DD HH:MM UTC" required />';
echo '<input name="link" placeholder="link (optional)" style="min-width:260px" />';
echo '<select name="level"><option>info</option><option>warn</option><option>success</option><option>critical</option></select>';
echo '<input name="text" placeholder="text" style="min-width:260px" />';
echo '<button>Save</button></form>';

echo '<table><thead><tr><th>When</th><th>Title</th><th>Level</th><th>Link</th><th></th></tr></thead><tbody>';
foreach ($ann as $a) {
  $id = htmlspecialchars($a['id'] ?? '');
  $when = htmlspecialchars($a['dateUTC'] ?? '');
  $title = htmlspecialchars($a['title'] ?? '');
  $level = htmlspecialchars($a['level'] ?? '');
  $link = htmlspecialchars($a['link'] ?? '');
  echo "<tr><td>$when</td><td>$title</td><td class=\"muted\">$level</td><td class=\"muted\">$link</td><td><form method=\"post\"><input type=\"hidden\" name=\"action\" value=\"delete\"><input type=\"hidden\" name=\"id\" value=\"$id\"><button class=\"danger\">Delete</button></form></td></tr>";
}
echo '</tbody></table></div>';

include_once __DIR__ . '/../include/footer.php';
