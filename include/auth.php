<?php
/**
 * include/auth.php
 *
 * Admin auth for Casperia:
 * - Uses the normal web login (DB-backed users in $_SESSION['user'])
 * - Uses UserLevel from the OpenSim UserAccounts table
 * - No HTTP Basic auth, no ADMIN_USER / ADMIN_PASS from env.php
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Minimum user level required for admin tools
if (!defined('ADMIN_USERLEVEL_MIN')) {
    define('ADMIN_USERLEVEL_MIN', 200);
}

/**
 * Return the current logged-in user array from the session, or null.
 *
 * Expected keys (from login.php):
 *   - principal_id
 *   - email
 *   - name
 * (Optionally cached:)
 *   - UserLevel
 */
function casperia_current_user(): ?array {
    return isset($_SESSION['user']) && is_array($_SESSION['user'])
        ? $_SESSION['user']
        : null;
}

/**
 * Check if the current user is logged in.
 */
function casperia_is_logged_in(): bool {
    $u = casperia_current_user();
    return !empty($u['principal_id']);
}

/**
 * Fetch UserLevel for the current user from the DB (UserAccounts table),
 * cache it in $_SESSION['user']['UserLevel'], and return it as int.
 * Returns null if it cannot be determined.
 */
function casperia_fetch_userlevel_from_db(): ?int {
    $u = casperia_current_user();
    if (!$u) {
        return null;
    }
    $pid = $u['principal_id'] ?? '';
    if ($pid === '' || !function_exists('db')) {
        return null;
    }

    $conn = db();
    if (!$conn) {
        return null;
    }

    // Match the logic already used in include/header.php:
    //   SELECT UserLevel FROM UserAccounts WHERE PrincipalID = ? LIMIT 1
    $sql = "SELECT UserLevel FROM UserAccounts WHERE PrincipalID = ? LIMIT 1";
    $level = null;

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 's', $pid);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_bind_result($stmt, $lvl);
            if (mysqli_stmt_fetch($stmt)) {
                $level = (int)$lvl;
            }
        }
        mysqli_stmt_close($stmt);
    }

    mysqli_close($conn);

    if ($level !== null) {
        $_SESSION['user']['UserLevel'] = $level; // cache for later
    }

    return $level;
}

/**
 * Check if the current user is an admin (UserLevel >= ADMIN_USERLEVEL_MIN).
 */
function casperia_is_admin(): bool {
    $u = casperia_current_user();
    if (!$u) {
        return false;
    }

    // If we've already cached UserLevel in the session, trust that first.
    if (isset($u['UserLevel'])) {
        return ((int)$u['UserLevel']) >= ADMIN_USERLEVEL_MIN;
    }

    // Otherwise, try to fetch from the DB and cache.
    $level = casperia_fetch_userlevel_from_db();
    if ($level === null) {
        return false;
    }

    return $level >= ADMIN_USERLEVEL_MIN;
}

/**
 * Enforce admin access.
 *
 * If the user is not an admin:
 *   - If headers are not sent, redirect to the normal login page with a returnTo.
 *   - Otherwise, print a simple error message and exit.
 *
 * This is safe to call before or after include/header.php, but it works best
 * when used *before* header output so the redirect can happen cleanly.
 */
function require_admin(): void {
    if (casperia_is_admin()) {
        return;
    }

    $target = $_SERVER['REQUEST_URI'] ?? '/';

    if (!headers_sent()) {
        // BASE_URL may be defined in config.php; fall back to relative path if not.
        $base = defined('BASE_URL') ? BASE_URL : '';
        $url = $base . '/login.php?next=' . urlencode($target);
        header('Location: ' . $url);
        exit;
    }

    // Fallback: headers already sent, so just show a minimal message
    echo '<main class="content-card mt-4">';
    echo '<h1 class="mb-3">Admin access required</h1>';
    echo '<div class="alert alert-danger">';
    echo 'You must be logged in as an administrator (UserLevel &ge; ' . (int)ADMIN_USERLEVEL_MIN . ') to access this page.';
    echo '</div></main>';
    exit;
}
