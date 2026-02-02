<?php
// friends_api.php — JSON API for friends.php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

require_once __DIR__ . '/../include/config.php';

header('Content-Type: application/json; charset=utf-8');

if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
$con = @db();
if (!$con) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed.'
    ]);
    exit;
}

function api_respond(bool $success, string $message, array $extra = []): void {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// --- Current logged-in user ---
if (empty($_SESSION['user']['principal_id'])) {
    api_respond(false, 'You must be logged in to manage friends.');
}
$currentUserId = $_SESSION['user']['principal_id'];

// --- Helper: resolve avatar by UUID or "First Last" ---
function resolve_avatar(mysqli $con, string $input): ?array {
    $input = trim($input);
    // Looks like a UUID already
    if (strlen($input) === 36 && strpos($input, '-') !== false) {
        $uuid = mysqli_real_escape_string($con, $input);
        $res = mysqli_query(
            $con,
            "SELECT PrincipalID, FirstName, LastName
             FROM UserAccounts
             WHERE PrincipalID = '$uuid'
             LIMIT 1"
        );
        if ($res && $row = mysqli_fetch_assoc($res)) {
            return $row;
        }
        return null;
    }

    // Try "First Last"
    $parts = preg_split('/\s+/', $input);
    if (count($parts) < 2) {
        return null;
    }
    $first = mysqli_real_escape_string($con, $parts[0]);
    $last  = mysqli_real_escape_string($con, $parts[1]);

    $res = mysqli_query(
        $con,
        "SELECT PrincipalID, FirstName, LastName
         FROM UserAccounts
         WHERE FirstName = '$first' AND LastName = '$last'
         LIMIT 1"
    );
    if ($res && $row = mysqli_fetch_assoc($res)) {
        return $row;
    }
    return null;
}

// --- Helper: check if friendship already exists ---
function friends_are_connected(mysqli $con, string $u1, string $u2): bool {
    $u1 = mysqli_real_escape_string($con, $u1);
    $u2 = mysqli_real_escape_string($con, $u2);

    $res = mysqli_query(
        $con,
        "SELECT COUNT(*) AS c
         FROM Friends
         WHERE ((PrincipalID = '$u1' AND Friend = '$u2')
            OR  (PrincipalID = '$u2' AND Friend = '$u1'))
           AND CAST(Flags AS UNSIGNED) > 0"
    );
    if ($res && $row = mysqli_fetch_assoc($res)) {
        return ((int)$row['c']) > 0;
    }
    return false;
}

// --- Parse JSON / fallback to POST ---
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST ?? [];
}

$action  = $data['action']  ?? '';
$userRaw = $data['user_id'] ?? '';

if ($action === '' || $userRaw === '') {
    api_respond(false, 'Missing action or user_id.');
}

$target = resolve_avatar($con, $userRaw);
if (!$target) {
    api_respond(false, 'Target avatar not found.');
}

$targetId   = $target['PrincipalID'];
$targetName = $target['FirstName'] . ' ' . $target['LastName'];

if ($targetId === $currentUserId) {
    api_respond(false, 'You cannot add yourself as a friend.');
}

$me = mysqli_real_escape_string($con, $currentUserId);
$other = mysqli_real_escape_string($con, $targetId);

switch ($action) {
    case 'send_request':
        // Check existing relation
        $res = mysqli_query(
            $con,
            "SELECT PrincipalID, Friend, Flags
             FROM Friends
             WHERE (PrincipalID = '$me'   AND Friend = '$other')
                OR (PrincipalID = '$other' AND Friend = '$me')"
        );

        $rows = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = $row;
            }
        }

        foreach ($rows as $row) {
            $flags = (int)$row['Flags'];
            if ($flags > 0) {
                api_respond(false, 'You are already friends.');
            }
        }

        // If they already requested you, accept instead
        $pendingFromOther = array_filter($rows, function ($row) use ($other, $me) {
            return $row['PrincipalID'] === $other &&
                   $row['Friend']       === $me &&
                   (int)$row['Flags']   === 0;
        });

        if (!empty($pendingFromOther)) {
            // Turn into an accepted friendship
            mysqli_begin_transaction($con);

            mysqli_query(
                $con,
                "UPDATE Friends
                 SET Flags = '1', Offered = '0'
                 WHERE PrincipalID = '$other' AND Friend = '$me'"
            );

            mysqli_query(
                $con,
                "INSERT INTO Friends (PrincipalID, Friend, Flags, Offered)
                 VALUES ('$me', '$other', '1', '0')
                 ON DUPLICATE KEY UPDATE Flags = '1', Offered = '0'"
            );

            mysqli_commit($con);

            api_respond(true, "Friend request from {$targetName} accepted automatically.");
        }

        // Otherwise create a new request from me -> them
        mysqli_query(
            $con,
            "INSERT INTO Friends (PrincipalID, Friend, Flags, Offered)
             VALUES ('$me', '$other', '0', '1')
             ON DUPLICATE KEY UPDATE Flags = '0', Offered = '1'"
        );

        api_respond(true, "Friend request sent to {$targetName}.");
        break;

    case 'accept_request':
        // They requested me: Friend=me, PrincipalID=other, Flags=0
        mysqli_begin_transaction($con);

        $updated = mysqli_query(
            $con,
            "UPDATE Friends
             SET Flags = '1', Offered = '0'
             WHERE PrincipalID = '$other'
               AND Friend = '$me'
               AND CAST(Flags AS UNSIGNED) = 0"
        );

        if (!$updated || mysqli_affected_rows($con) === 0) {
            mysqli_rollback($con);
            api_respond(false, 'No pending friend request from this user.');
        }

        mysqli_query(
            $con,
            "INSERT INTO Friends (PrincipalID, Friend, Flags, Offered)
             VALUES ('$me', '$other', '1', '0')
             ON DUPLICATE KEY UPDATE Flags = '1', Offered = '0'"
        );

        mysqli_commit($con);

        api_respond(true, "Friend request from {$targetName} accepted.");
        break;

    case 'decline_request':
        mysqli_query(
            $con,
            "DELETE FROM Friends
             WHERE PrincipalID = '$other'
               AND Friend = '$me'
               AND CAST(Flags AS UNSIGNED) = 0"
        );

        if (mysqli_affected_rows($con) === 0) {
            api_respond(false, 'No pending friend request from this user.');
        }

        api_respond(true, "Friend request from {$targetName} declined.");
        break;

    case 'remove_friend':
        mysqli_query(
            $con,
            "DELETE FROM Friends
             WHERE (PrincipalID = '$me'   AND Friend = '$other')
                OR (PrincipalID = '$other' AND Friend = '$me')"
        );

        if (mysqli_affected_rows($con) === 0) {
            api_respond(false, 'No friendship exists to remove.');
        }

        api_respond(true, "You are no longer friends with {$targetName}.");
        break;

    default:
        api_respond(false, 'Unknown action: ' . $action);
}
