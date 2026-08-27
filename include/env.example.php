<?php
/**
 * include/env.example.php
 *
 * Copy this file to env.php and fill in your real credentials.
 * IMPORTANT: Do NOT commit env.php to source control.
 */

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'CHANGE_ME');
define('DB_PASSWORD', 'CHANGE_ME');
define('DB_NAME', 'CHANGE_ME');        // OpenSim database
define('DB_ASSET_NAME', 'CHANGE_ME');  // Asset database (often same as DB_NAME)

define('REMOTEADMIN_HTTPAUTHUSERNAME', 'CHANGE_ME');
define('REMOTEADMIN_HTTPAUTHPASSWORD', 'CHANGE_ME');

// Optional: separate Money-Server DB credentials (register.php's
// sync_money_server()). Only used if MONEY_DB_NAME is set in config.php -
// leave that empty (the default) to keep this feature off.
define('MONEY_DB_USER', 'root');
define('MONEY_DB_PASS', '');

// Robust /accounts admin API Basic Auth (register.php's
// osv_robust_createuser()). Only used when REGISTRATION_CREATE_MODE is
// 'robust' or 'auto'.
define('ROBUST_HTTP_USER', '');
define('ROBUST_HTTP_PASS', '');
?>
