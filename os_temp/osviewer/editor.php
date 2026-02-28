<?php
// editor.php — backend for eventedit.php (writes data/events/events.json (via PATH_EVENTS_JSON))
session_start();

require_once __DIR__ . '/include/config.php';

// Optional: require the same event password auth as eventedit.php
if (empty($_SESSION['authenticated'])) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Not authorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_POST['events'])) {
    echo json_encode(['success' => false, 'message' => 'Missing events payload.']);
    exit;
}

$events = json_decode($_POST['events'], true);
if (!is_array($events)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON in events.']);
    exit;
}

$eventsFile  = PATH_EVENTS_JSON;
$backupFile  = __DIR__ . '/calendar/events_' . date('Y-m-d_H-i-s') . '.bak.json';

// Make backup if existing
if (file_exists($eventsFile)) {
    @copy($eventsFile, $backupFile);
}

file_put_contents(
    $eventsFile,
    json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);

echo json_encode(['success' => true, 'message' => 'Events saved successfully.']);
