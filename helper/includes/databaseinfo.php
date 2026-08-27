<?php
// Credentials come from the site's own gitignored include/env.php (the same
// file the rest of the site uses) instead of being duplicated/hardcoded
// here - that file is never committed to source control.
require_once dirname( __DIR__, 2 ) . '/include/env.php';

$DB_HOST     = DB_SERVER;
$DB_USER     = DB_USERNAME;
$DB_PASSWORD = DB_PASSWORD;
$DB_NAME     = DB_NAME;
?>
