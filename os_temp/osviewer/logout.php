<?php
// logout.php — ends session and returns to home or login
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$ok = true;
if (!empty($_SESSION['csrf'])) {
    $csrfgiven = $_GET['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'], (string)$csrfgiven)) {
        $ok = false;
    }
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

$dest = 'welcome.php';
if (!empty($_GET['next'])) {
    $n = (string)$_GET['next'];
    if (strpos($n, '://') === false && strpos($n, '\\') === false) {
        $dest = ltrim($n, '/');
    }
}
header('Location: ' . $dest, true, 302);
exit;
