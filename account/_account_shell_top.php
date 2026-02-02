<?php
// _account_shell_top.php
// Requires: _account_common.php already included (header rendered, data loaded)

$account_active = isset($account_active) ? (string)$account_active : 'dashboard';

if (!function_exists('account_nav_item')) {
    function account_nav_item(string $href, string $icon, string $label, string $key, string $metaHtml = ''): void {
        $active = (isset($GLOBALS['account_active']) && $GLOBALS['account_active'] === $key);
        $cardClass = 'card content-card h-100 shadow-sm border-0';
        if ($active) {
            $cardClass .= ' border border-2';
        }
        $metaHtml = trim($metaHtml);
        echo '<div class="col-6 col-md-4 col-lg-3">';
        echo '  <a data-no-rewrite="1" class="text-decoration-none" href="' . h($href) . '" aria-current="' . ($active ? 'page' : 'false') . '">';
        echo '    <div class="' . h($cardClass) . '">';
        echo '      <div class="card-body py-3">';
        echo '        <div class="d-flex align-items-center justify-content-between">';
        echo '          <div class="d-flex align-items-center">';
        echo '            <i class="bi ' . h($icon) . ' me-2"></i>'; 
        echo '            <div class="fw-semibold">' . h($label) . '</div>';
        echo '          </div>';
        if ($metaHtml !== '') {
            echo '          <div class="ms-2">' . $metaHtml . '</div>';
        }
        echo '        </div>';
        echo '      </div>';
        echo '    </div>';
        echo '  </a>';
        echo '</div>';
    }
}

// Dashboard notifications summary (messages, offline IMs, friends, tickets, admin tickets)
$accountAlerts = [];
if (isset($nav_unreadMessagesCount) && $nav_unreadMessagesCount > 0) {
    $accountAlerts[] = 'You have ' . (int)$nav_unreadMessagesCount . ' unread web message(s).';
}
if (isset($nav_offlineMessagesCount) && $nav_offlineMessagesCount > 0) {
    $accountAlerts[] = 'You have ' . (int)$nav_offlineMessagesCount . ' offline message(s) waiting.';
}
if (isset($nav_pendingFriendRequestsCount) && $nav_pendingFriendRequestsCount > 0) {
    $accountAlerts[] = 'You have ' . (int)$nav_pendingFriendRequestsCount . ' pending friend request(s).';
}
if (isset($nav_userOpenTicketsCount) && $nav_userOpenTicketsCount > 0) {
    $accountAlerts[] = 'You have ' . (int)$nav_userOpenTicketsCount . ' open support ticket(s).';
}
if (!empty($showAdminAnalyticsLink) && isset($nav_adminOpenTicketsCount) && $nav_adminOpenTicketsCount > 0) {
    $accountAlerts[] = 'There are ' . (int)$nav_adminOpenTicketsCount . ' open support ticket(s) awaiting admin attention.';
}

$picksCount   = isset($myPicks) ? count($myPicks) : 0;
$friendsCount = (isset($friendsLocal) ? count($friendsLocal) : 0) + (isset($friendsHG) ? count($friendsHG) : 0);
$groupsCount  = isset($myGroups) ? count($myGroups) : 0;
$regionsCount = isset($myRegions) ? count($myRegions) : 0;

$partnerMeta = '';
if (isset($partner_is_reciprocal, $partner) && $partner_is_reciprocal && !empty($partner['name'])) {
    $partnerMeta = '<span class="badge bg-success">Partnered</span>';
} elseif (!empty($outgoingPartner)) {
    $partnerMeta = '<span class="badge bg-warning text-dark">Pending</span>';
}
?>

<style>
/* Account section navigation cards */
.account-nav .card {
    transition: transform 0.12s ease, box-shadow 0.12s ease;
}
.account-nav a:hover .card {
    transform: translateY(-2px);
}
.account-nav a[aria-current="page"] .card {
    box-shadow: 0 0 0 2px var(--accent-color, #0d6efd);
}
</style>

<div class="container my-4">
    <div class="row">
        <div class="col-22 col-xl-20 mx-auto">

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                        <div>
                            <h1 class="h4 mb-0"><i class="bi bi-person-circle me-2"></i> My Profile</h1>
                            <small class="text-muted">Manage your avatar profile, friends, favorites, groups and regions.</small>
                        </div>
                        <div class="text-end mt-2 mt-sm-0">
                            <div class="small text-muted">Avatar UUID</div>
                            <code class="small"><?php echo h($profile['principal_id']); ?></code>
                        </div>
                    </div>

                    <?php if (!empty($messages)): ?>
                        <?php foreach ($messages as $msg): ?>
                            <div class="alert alert-success py-2"><?php echo h($msg); ?></div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <?php foreach ($errors as $msg): ?>
                            <div class="alert alert-danger py-2"><?php echo h($msg); ?></div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!empty($accountAlerts)): ?>
                        <div class="alert alert-info mb-3">
                            <div class="fw-semibold mb-1"><i class="bi bi-bell"></i> You have new activity:</div>
                            <ul class="mb-0">
                                <?php foreach ($accountAlerts as $msg): ?>
                                    <li><?php echo h($msg); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($newRecoveryCodes)): ?>
                        <div class="alert alert-warning border-2 shadow-sm">
                            <h4 class="alert-heading"><i class="bi bi-exclamation-triangle-fill"></i> New Recovery Codes Generated</h4>
                            <p>These are your <strong>only</strong> way to reset your password if you forget it. Save them now.</p>
                            <div class="bg-white p-3 border rounded text-center">
                                <code class="fs-4 d-block text-dark tracking-wide"><?php echo implode('<br>', $newRecoveryCodes); ?></code>
                            </div>
                            <hr>
                            <p class="mb-0 small text-muted">These codes will not be shown again.</p>
                        </div>
                    <?php endif; ?>

                    <div class="row g-3 account-nav mt-3">
                        <?php
                        account_nav_item('/account/index.php', 'bi-speedometer2', 'Overview', 'dashboard');
                        account_nav_item('/account/inworld.php', 'bi-person', 'Inworld', 'inworld');
                        account_nav_item('/account/account.php', 'bi-gear', 'Account', 'account');
                        account_nav_item('/account/favorites.php', 'bi-star', 'Favorites', 'favorites', '<span class="badge bg-primary">' . (int)$picksCount . '</span>');
                        account_nav_item('/account/friends.php', 'bi-people', 'Friends', 'friends', '<span class="badge bg-primary">' . (int)$friendsCount . '</span>');
                        account_nav_item('/account/groups.php', 'bi-people-fill', 'Groups', 'groups', '<span class="badge bg-primary">' . (int)$groupsCount . '</span>');
                        account_nav_item('/account/firstlife.php', 'bi-heart', 'First Life', 'firstlife');
                        account_nav_item('/account/partner.php', 'bi-person-heart', 'Partner', 'partner', $partnerMeta);
                        account_nav_item('/account/regions.php', 'bi-map', 'My Regions', 'regions', '<span class="badge bg-primary">' . (int)$regionsCount . '</span>');
                        ?>
                    </div>

                </div>
            </div>

            <div class="card content-card shadow-sm border-0 mb-4">
                <div class="card-body">
