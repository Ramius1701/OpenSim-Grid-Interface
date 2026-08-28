<?php
// include/nav_notifications.php
// Lightweight per-user notification counters for top navigation badges.
// Safe to include from header.php — uses existing db() helper and OpenSim/site tables.

// Default counts
$nav_unreadMessagesCount        = 0;
$nav_offlineMessagesCount       = 0;
$nav_pendingFriendRequestsCount = 0;
$nav_userOpenTicketsCount       = 0;
$nav_adminOpenTicketsCount      = 0;
$nav_totalNotificationCount     = 0;

// If db() isn't available or user isn't logged in, do nothing.
if (!function_exists('db')) {
    return;
}
if (empty($_SESSION['user']['principal_id'])) {
    return;
}

$userId = $_SESSION['user']['principal_id'];
$con    = @db();
if (!$con) {
    return;
}

$__navTmpCount = 0;

// 1) Unread internal web messages (ws_messages - this site's own SQLite DB)
if ($wsdb = @ws_db()) {
    try {
        $stmt = $wsdb->prepare("SELECT COUNT(*) FROM ws_messages WHERE receiver_uuid = ? AND is_read = 0 AND receiver_deleted = 0");
        $stmt->execute([$userId]);
        $nav_unreadMessagesCount = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        // leave default 0
    }
}

// 2) Pending Offline IMs (im_offline)
if ($stmt = @mysqli_prepare($con, "SELECT COUNT(*) FROM im_offline WHERE PrincipalID = ?")) {
    mysqli_stmt_bind_param($stmt, 's', $userId);
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_bind_result($stmt, $__navTmpCount);
        if (mysqli_stmt_fetch($stmt)) {
            $nav_offlineMessagesCount = (int)$__navTmpCount;
        }
    }
    mysqli_stmt_close($stmt);
}

// 3) Pending friend requests (Flags = 0 in Friends table)
if ($stmt = @mysqli_prepare($con, "SELECT COUNT(*) FROM Friends WHERE Friend = ? AND Flags = 0")) {
    mysqli_stmt_bind_param($stmt, 's', $userId);
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_bind_result($stmt, $__navTmpCount);
        if (mysqli_stmt_fetch($stmt)) {
            $nav_pendingFriendRequestsCount = (int)$__navTmpCount;
        }
    }
    mysqli_stmt_close($stmt);
}

// 4) Open / in-progress tickets for this user (ws_tickets - this site's own SQLite DB)
if ($wsdb = @ws_db()) {
    try {
        $stmt = $wsdb->prepare("SELECT COUNT(*) FROM ws_tickets WHERE user_uuid = ? AND status IN ('open','in_progress')");
        $stmt->execute([$userId]);
        $nav_userOpenTicketsCount = (int)$stmt->fetchColumn();

        // 5) Open tickets grid-wide (for the admin menu badge only - see the
        // $showAdminAnalyticsLink check below before this is added to the
        // account menu's total badge; header.php's own Admin dropdown badge
        // is separately gated by the same variable).
        if (!empty($showAdminAnalyticsLink)) {
            $nav_adminOpenTicketsCount = (int)$wsdb->query(
                "SELECT COUNT(*) FROM ws_tickets WHERE status IN ('open','in_progress')"
            )->fetchColumn();
        }
    } catch (Throwable $e) {
        // leave defaults 0
    }
}

// Total badge for the account menu: the user's own notifications, plus
// grid-wide open tickets only when they're actually an admin (otherwise
// $nav_adminOpenTicketsCount stays 0 from the guard above).
$nav_totalNotificationCount =
    $nav_unreadMessagesCount +
    $nav_offlineMessagesCount +
    $nav_pendingFriendRequestsCount +
    $nav_userOpenTicketsCount +
    $nav_adminOpenTicketsCount;
