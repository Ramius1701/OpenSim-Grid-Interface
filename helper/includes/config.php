<?php
/**
 * config.php (Casperia Prime)
 *
 * Created from config.example.php to fix a missing-file fatal error -
 * helper/economy/currency.php and landtool.php require this file but only
 * the .example version existed, meaning this path was completely broken
 * if anyone ever tried to use it.
 *
 * This is an OPTIONAL alternate currency backend. Casperia Prime's default
 * currency backend is the C# MoneyServer (see opensim-enhanced), which
 * handles viewer currency purchases directly without needing this file at
 * all. Only relevant if you deliberately choose this path instead.
 *
 * @package		magicoli/opensim-helpers
 * @author 		Gudule Lapointe <gudule@speculoos.world>
 * @link 			https://github.com/magicoli/opensim-helpers
 * @license		AGPLv3
 */

// Shared database credentials for all helper/ subsystems (search, mute,
// events, economy) - one file instead of separate duplicated copies.
require_once dirname( __DIR__, 2 ) . '/databaseinfo.php';

define( 'OPENSIM_DB', true );
define( 'OPENSIM_DB_HOST', $DB_HOST );
define( 'OPENSIM_DB_NAME', $DB_NAME );
define( 'OPENSIM_DB_USER', $DB_USER );
define( 'OPENSIM_DB_PASS', $DB_PASSWORD );
define( 'SEARCH_TABLE_EVENTS', 'search_events' ); // matches this site's actual table naming, see helper/search

define( 'SEARCH_DB_HOST', OPENSIM_DB_HOST );
define( 'SEARCH_DB_NAME', OPENSIM_DB_NAME );
define( 'SEARCH_DB_USER', OPENSIM_DB_USER );
define( 'SEARCH_DB_PASS', OPENSIM_DB_PASS );

define( 'CURRENCY_DB_HOST', OPENSIM_DB_HOST );
define( 'CURRENCY_DB_NAME', OPENSIM_DB_NAME );
define( 'CURRENCY_DB_USER', OPENSIM_DB_USER );
define( 'CURRENCY_DB_PASS', OPENSIM_DB_PASS );
define( 'CURRENCY_MONEY_TBL', 'balances' );
define( 'CURRENCY_TRANSACTION_TBL', 'transactions' );

/**
 * Money Server settings.
 * Left disabled by default - this site's actual currency backend is the
 * C# MoneyServer, not this helper. Review carefully before enabling.
 */
define( 'CURRENCY_USE_MONEYSERVER', false );
define( 'CURRENCY_SCRIPT_KEY', '123456789' ); // CHANGE if this path is ever actually enabled
define( 'CURRENCY_RATE', 10 );
define( 'CURRENCY_RATE_PER', 1000 );
define( 'CURRENCY_PROVIDER', null ); // NULL, 'podex' or 'gloebit'
define( 'CURRENCY_HELPER_URL', 'https://casperia.ddns.net/helper/economy/' );

/**
 * DO NOT MAKE CHANGES BELOW THIS
 * Add your custom values above.
 */
require_once 'databases.php';
require_once 'functions.php';

$currency_addon = dirname( __DIR__ ) . '/addons/' . CURRENCY_PROVIDER . '.php';
if ( file_exists( $currency_addon ) ) {
	require_once $currency_addon;
}
