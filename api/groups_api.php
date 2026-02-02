<?php
// groups_api.php — JSON API for groups.php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

require_once __DIR__ . '/../include/config.php';

header('Content-Type: application/json; charset=utf-8');

if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
$con = @db();
if (!$con) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

function g_api_respond(bool $success, string $message, array $extra = []): void {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['user']['principal_id'])) {
    g_api_respond(false, 'You must be logged in to manage groups.');
}
$currentUserId = $_SESSION['user']['principal_id'];

// Simple UUID v4 generator for tokens / InviteID
function uuidv4(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

// Parse JSON / fallback
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST ?? [];
}

$action  = $data['action']   ?? '';
$groupId = $data['group_id'] ?? '';

if ($action === '' || $groupId === '') {
    g_api_respond(false, 'Missing action or group_id.');
}

$gId = mysqli_real_escape_string($con, $groupId);
$uId = mysqli_real_escape_string($con, $currentUserId);

// Load group info
$res = mysqli_query(
    $con,
    "SELECT GroupID, Name, MembershipFee, OpenEnrollment, FounderID
     FROM os_groups_groups
     WHERE GroupID = '$gId'
     LIMIT 1"
);
$group = $res ? mysqli_fetch_assoc($res) : null;

if (!$group) {
    g_api_respond(false, 'Group not found.');
}

$groupName = $group['Name'] ?? $group['GroupID'];

// Already a member?
$memRes = mysqli_query(
    $con,
    "SELECT COUNT(*) AS c
     FROM os_groups_membership
     WHERE GroupID = '$gId' AND PrincipalID = '$uId'"
);
$memRow = $memRes ? mysqli_fetch_assoc($memRes) : ['c' => 0];
if ((int)$memRow['c'] > 0 && $action !== 'request_invite') {
    g_api_respond(true, "You are already a member of {$groupName}.");
}

// Determine default role ID (Everyone role is usually GroupID)
$defaultRoleId = $group['GroupID']; // reasonable default

switch ($action) {
    case 'join_group':
        // Respect OpenEnrollment flag
        $open = trim((string)$group['OpenEnrollment']) !== ''
             && trim((string)$group['OpenEnrollment']) !== '0'
             && strcasecmp((string)$group['OpenEnrollment'], 'false') !== 0;

        if (!$open) {
            g_api_respond(false, 'This group is invite-only. Please request an invite.');
        }

        // TODO: MembershipFee + economy handling (not implemented yet)
        // For now, ignore fees and just join.

        mysqli_begin_transaction($con);

        mysqli_query(
            $con,
            "INSERT INTO os_groups_membership
             (GroupID, PrincipalID, SelectedRoleID, Contribution, ListInProfile, AcceptNotices, AccessToken)
             VALUES
             ('$gId', '$uId', '$defaultRoleId', 0, 1, 1, '" . uuidv4() . "')
             ON DUPLICATE KEY UPDATE SelectedRoleID = VALUES(SelectedRoleID)"
        );

        mysqli_query(
            $con,
            "INSERT IGNORE INTO os_groups_rolemembership
             (GroupID, RoleID, PrincipalID)
             VALUES ('$gId', '$defaultRoleId', '$uId')"
        );

        mysqli_commit($con);

        g_api_respond(true, "You have joined the group {$groupName}.");
        break;

    case 'request_invite':
        // For now, treat "request invite" as: auto-create an invite entry and membership.
        // This at least provides a working flow and can be tightened later.
        mysqli_begin_transaction($con);

        // Create an invite record
        $inviteId = uuidv4();
        mysqli_query(
            $con,
            "INSERT INTO os_groups_invites
             (InviteID, GroupID, RoleID, PrincipalID, TMStamp)
             VALUES
             ('$inviteId', '$gId', '$defaultRoleId', '$uId', NOW())"
        );

        // Also add membership immediately so it "just works"
        mysqli_query(
            $con,
            "INSERT INTO os_groups_membership
             (GroupID, PrincipalID, SelectedRoleID, Contribution, ListInProfile, AcceptNotices, AccessToken)
             VALUES
             ('$gId', '$uId', '$defaultRoleId', 0, 1, 1, '" . uuidv4() . "')
             ON DUPLICATE KEY UPDATE SelectedRoleID = VALUES(SelectedRoleID)"
        );

        mysqli_query(
            $con,
            "INSERT IGNORE INTO os_groups_rolemembership
             (GroupID, RoleID, PrincipalID)
             VALUES ('$gId', '$defaultRoleId', '$uId')"
        );

        mysqli_commit($con);

        g_api_respond(true, "Invitation request processed. You are now a member of {$groupName}.");
        break;

    default:
        g_api_respond(false, 'Unknown action: ' . $action);
}
