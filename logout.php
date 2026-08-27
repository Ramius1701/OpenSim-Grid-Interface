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
    // Strip all leading slashes first so a protocol-relative value like
    // "//evil.com" collapses to the harmless relative path "evil.com"
    // before we validate what's left.
    $n = ltrim((string)$_GET['next'], '/');
    if ($n !== ''
        && strpos($n, '\\') === false
        && strpos($n, "\n") === false
        && strpos($n, "\r") === false
        // Reject anything starting with a URI scheme (e.g. "https:evil.com",
        // "javascript:..."). Browsers parse "scheme:host" as an absolute URL
        // for special schemes even without "//", so checking for "://" alone
        // (the previous check) isn't enough to catch it.
        && !preg_match('/^[a-zA-Z][a-zA-Z0-9+.\-]*:/', $n)
    ) {
        $dest = $n;
    }
}
header('Location: ' . $dest, true, 302);
exit;
