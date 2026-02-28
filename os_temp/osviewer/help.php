<?php
$title = "Help";
include_once 'include/header.php';
include_once 'include/viewer_context.php';

if (!empty($IS_VIEWER)) {
    echo '<style>
        body { padding-top: 0 !important; padding-bottom: 0 !important; }
        .navbar, .navbar-expand-lg { display: none !important; }
        .footer-modern { display: none !important; }
        .wrap { max-width: 100% !important; margin: 0 !important; padding: 8px !important; }
    </style>';
}
?>

<main class="content-card">
    <section class="mb-4">
        <h1 class="mb-1">Help &amp; support</h1>
        <p class="text-muted mb-0">
            This page provides quick help for using <?php echo SITE_NAME; ?> both in your viewer and on the web.
        </p>
    </section>

    <section class="mb-4">
        <h2 class="h5">Common tasks</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="border rounded p-3 h-100">
                    <h3 class="h6 mb-2">Manage your account</h3>
                    <ul class="mb-0 small">
                        <li>Change your password, email, and profile text on the
                            <a href="account/index.php">Account page</a>.</li>
                        <li>Update your first-life profile and partner information from the same page.</li>
                        <li>Review your last connection time to check when you last logged in.</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-6">
                <div class="border rounded p-3 h-100">
                    <h3 class="h6 mb-2">Search, friends &amp; regions</h3>
                    <ul class="mb-0 small">
                        <li>Use <a href="ossearch.php">Search</a> for places, events, classifieds, people,
                            groups and land.</li>
                        <li>Manage your friends list and permissions from the Account page, or use viewer tools in-world.</li>
                        <li>See a list of regions you own or manage on the Account page.</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section class="mb-4">
        <h2 class="h5">Using search from the viewer</h2>
        <p class="small text-muted">
            When you open the Search window in your viewer, it loads this website inside the viewer.
            The same tabs are also available when you open <a href="ossearch.php">Search</a> in a normal browser.
        </p>
        <ul class="small mb-0">
            <li><strong>Places</strong> – find regions and parcels by name, description or keywords.</li>
            <li><strong>Events</strong> – browse upcoming events; click an event to see details and teleport location.</li>
            <li><strong>Classifieds</strong> – see resident-created ads for stores, clubs and services.</li>
            <li><strong>People</strong> – search for residents by name.</li>
            <li><strong>Groups</strong> – look up groups, then join them in-world using your viewer.</li>
            <li><strong>Land sales</strong> – find parcels that are set for sale.</li>
        </ul>
    </section>

    <section class="mb-4">
        <h2 class="h5">Troubleshooting</h2>
        <ul class="small mb-0">
            <li><strong>Search shows no results:</strong> check your internet connection, then try again.
                If the problem continues, the grid search backend may be offline briefly.</li>
            <li><strong>Pages look cut off in the viewer:</strong> try resizing the search/help window or
                open the same URL in an external browser.</li>
            <li><strong>Password problems:</strong> use the Account page to change your password, then
                restart your viewer and log in with the new one.</li>
        </ul>
    </section>

    <section class="mb-2">
        <h2 class="h5">More help</h2>
        <p class="small mb-0">
            For additional assistance, contact grid staff in-world or use the contact/support options provided
            on the main <?php echo SITE_NAME; ?> website.
        </p>
    </section>
</main>

<?php include_once 'include/footer.php'; ?>
