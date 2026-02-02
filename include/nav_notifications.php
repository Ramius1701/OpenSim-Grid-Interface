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

// 1) Unread internal web messages (ws_messages)
if ($stmt = @mysqli_prepare($con, "SELECT COUNT(*) FROM ws_messages WHERE receiver_uuid = ? AND is_read = 0 AND receiver_deleted = 0")) {
    mysqli_stmt_bind_param($stmt, 's', $userId);
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_bind_result($stmt, $__navTmpCount);
        if (mysqli_stmt_fetch($stmt)) {
            $nav_unreadMessagesCount = (int)$__navTmpCount;
        }
    }
    mysqli_stmt_close($stmt);
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

// 4) Open / in-progress tickets for this user (ws_tickets — may or may not exist yet)
if ($stmt = @mysqli_prepare($con, "SELECT COUNT(*) FROM ws_tickets WHERE user_uuid = ? AND status IN ('open','in_progress')")) {
    mysqli_stmt_bind_param($stmt, 's', $userId);
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_bind_result($stmt, $__navTmpCount);
        if (mysqli_stmt_fetch($stmt)) {
            $nav_userOpenTicketsCount = (int)$__navTmpCount;
        }
    }
    mysqli_stmt_close($stmt);
}

// 5) Open tickets grid-wide (for admin menu badge)
// We don't check $showAdminAnalyticsLink here; the Admin menu markup already
// hides this from non-admins.
if ($stmt = @mysqli_prepare($con, "SELECT COUNT(*) FROM ws_tickets WHERE status IN ('open','in_progress')")) {
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_bind_result($stmt, $__navTmpCount);
        if (mysqli_stmt_fetch($stmt)) {
            $nav_adminOpenTicketsCount = (int)$__navTmpCount;
        }
    }
    mysqli_stmt_close($stmt);
}

// Total global badge for the account menu
$nav_totalNotificationCount =
    $nav_unreadMessagesCount +
    $nav_offlineMessagesCount +
    $nav_pendingFriendRequestsCount +
    $nav_adminOpenTicketsCount; // admin tickets only counted for admins
