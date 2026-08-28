<?php
/**
 * tools/smoke_test.php — lightweight page-crash detector.
 *
 * Requests every real page in the site and flags anything that comes back
 * with a fatal PHP error or an unexpected HTTP status. This is NOT a test
 * of correctness (it won't catch "the number is wrong") - it only catches
 * "the page is actually broken", which is cheap to check and would have
 * caught several real bugs found by hand this session (a fatal function
 * redeclaration, a collation-mismatch crash, an undefined-constant fatal)
 * automatically, before they were noticed by someone actually visiting
 * the live site.
 *
 * Usage:
 *   php tools/smoke_test.php [base-url]
 *
 *   php tools/smoke_test.php                          # http://127.0.0.1:8091
 *   php tools/smoke_test.php http://127.0.0.1:8091
 *   php tools/smoke_test.php https://casperia.ddns.net
 *
 * Exit code is 0 if everything looked fine, 1 if anything failed - safe to
 * use as a pass/fail gate (e.g. run it after deploying, before telling
 * anyone the deploy is done).
 *
 * Admin pages are included but expected to redirect to login.php rather
 * than return 200 (a bare, unauthenticated GET can't reach them) - that
 * redirect itself is treated as a pass. What this script can't do is log
 * in and check admin pages while authenticated; that would need real
 * session cookies, which isn't something to hardcode into a repo file.
 */

declare(strict_types=1);

$baseUrl = rtrim($argv[1] ?? 'http://127.0.0.1:8091', '/');

// Public pages - a plain GET should always render something real.
$publicPages = [
    'welcome.php', 'features.php', 'events.php', 'economy.php', 'groups.php',
    'friends.php', 'classifieds.php', 'picks.php', 'profile.php',
    'gridstatus.php', 'gridsearch.php', 'gridsearch_viewer.php',
    'destinations.php', 'guide.php', 'search.php', 'ossearch.php',
    'register.php', 'login.php', 'reset_password.php', 'support.php',
    'message.php', 'avatarpicker.php', 'gridlist.php', 'events_manage.php',
    'viewers.php', 'help.php', 'about.php', 'tos.php', 'dmca.php',
    'gridstatusrss.php',
    'maps/index.php', 'maps/gridmap.php',
];

// Account pages - unauthenticated, so a redirect to login is the correct
// "pass" outcome, not a 200 with real content.
$accountPages = [
    'account/', 'account/account.php', 'account/favorites.php',
    'account/firstlife.php', 'account/friends.php', 'account/groups.php',
    'account/inworld.php', 'account/partner.php', 'account/regions.php',
    'account/offline_messages.php',
];

// Admin pages - same story: expect a redirect to login, not a fatal error.
$adminPages = [
    'admin/analytics.php', 'admin/announcements_admin.php',
    'admin/groups_admin.php', 'admin/holiday_add.php',
    'admin/holiday_admin.php', 'admin/regions_admin.php',
    'admin/tickets_admin.php', 'admin/users_admin.php',
    'admin/dashboard/index.php',
];

// JSON API endpoints - a bare GET (no session, no POST body) should return
// a clean "you must be logged in" / "unknown action" JSON error, not a raw
// PHP fatal mixed into what's supposed to be a JSON response.
// map-tile.php correctly 400s on a bare GET (it requires x/y coordinates -
// see its own validation at the top of the file) - that 400 is the proof
// it's alive and validating input, not a failure, so it gets its own
// expected-status entry instead of the generic 200-399 range everyone else
// in this group uses.
$apiPages = [
    'api/economy_api.php', 'api/friends_api.php', 'api/groups_api.php',
    'maps/map-data.php', 'maps/map-tile.php',
];
$apiPagesWithExpectedStatus = [
    'maps/map-tile.php' => [400],
];

$fatalPatterns = [
    'Fatal error', 'Parse error', 'Uncaught ', 'Undefined constant',
    'Class not found', 'Call to undefined',
];

/**
 * @param int[]|null $expectedStatuses Exact statuses that count as a pass.
 *   Null (the default) means "any 2xx or 3xx".
 * @return array{ok: bool, status: int, reason: string}
 */
function checkPage(string $url, array $fatalPatterns, ?array $expectedStatuses = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false, // a redirect is itself the signal we want to see, don't chase it
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // No curl_close() - a no-op since PHP 8.0, deprecated as of 8.5.

    if ($errno !== 0) {
        return ['ok' => false, 'status' => 0, 'reason' => "connection failed: $error"];
    }

    if ($body !== false) {
        foreach ($fatalPatterns as $pattern) {
            if (stripos($body, $pattern) !== false) {
                return ['ok' => false, 'status' => $status, 'reason' => "response contains \"$pattern\""];
            }
        }
    }

    if ($expectedStatuses !== null) {
        return in_array($status, $expectedStatuses, true)
            ? ['ok' => true, 'status' => $status, 'reason' => '']
            : ['ok' => false, 'status' => $status, 'reason' => 'unexpected HTTP status'];
    }

    // 2xx = rendered fine. 3xx = redirect (expected for auth-gated pages,
    // and gridstatusrss.php/some pages legitimately 30x too). Anything
    // else is worth a look.
    if ($status >= 200 && $status < 400) {
        return ['ok' => true, 'status' => $status, 'reason' => ''];
    }

    return ['ok' => false, 'status' => $status, 'reason' => "unexpected HTTP status"];
}

$groups = [
    'Public pages' => $publicPages,
    'Account pages (expect redirect-to-login)' => $accountPages,
    'Admin pages (expect redirect-to-login)' => $adminPages,
    'API endpoints (expect a clean JSON error, not a fatal)' => $apiPages,
];

$totalChecked = 0;
$failures = [];

foreach ($groups as $groupLabel => $pages) {
    echo "\n=== $groupLabel ===\n";
    foreach ($pages as $path) {
        $url = $baseUrl . '/' . $path;
        $expected = $apiPagesWithExpectedStatus[$path] ?? null;
        $result = checkPage($url, $fatalPatterns, $expected);
        $totalChecked++;
        $mark = $result['ok'] ? 'OK  ' : 'FAIL';
        printf("%-4s [%3d] %s%s\n", $mark, $result['status'], $path, $result['reason'] !== '' ? ' — ' . $result['reason'] : '');
        if (!$result['ok']) {
            $failures[] = $path . ' — ' . $result['reason'];
        }
    }
}

echo "\n" . str_repeat('-', 60) . "\n";
echo count($failures) === 0
    ? "All $totalChecked pages OK.\n"
    : count($failures) . " of $totalChecked pages FAILED:\n  - " . implode("\n  - ", $failures) . "\n";

exit(count($failures) === 0 ? 0 : 1);
