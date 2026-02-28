<?php
// Basic auth for lightweight admin pages (safe for viewer-embedded use)
if (!defined('ADMIN_USER')) { define('ADMIN_USER', 'admin'); }
if (!defined('ADMIN_PASS')) { define('ADMIN_PASS', 'change_me'); }

function require_admin() {
    $u = $_SERVER['PHP_AUTH_USER'] ?? '';
    $p = $_SERVER['PHP_AUTH_PW'] ?? '';
    if ($u !== ADMIN_USER || $p !== ADMIN_PASS) {
        header('WWW-Authenticate: Basic realm="OSViewer Admin"');
        header('HTTP/1.0 401 Unauthorized');
        echo '<!doctype html><meta charset="utf-8"><title>Auth required</title><body style="background:#0b1020;color:#e5e7eb;font-family:system-ui">';
        echo '<div style="max-width:600px;margin:2rem auto;padding:1rem;border:1px solid #1f2937;border-radius:12px;background:#111827">';
        echo '<h2>Authentication required</h2><div class="muted">Set ADMIN_USER / ADMIN_PASS in include/env.php</div></div></body>';
        exit;
    }
}
