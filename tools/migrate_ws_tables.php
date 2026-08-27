<?php
/**
 * tools/migrate_ws_tables.php
 *
 * One-time (but safely re-runnable) copy of this site's own ws_* tables from
 * the shared OpenSim/Robust MySQL database into the new dedicated SQLite
 * database (see include/ws_db.php).
 *
 * This is only relevant for an existing install that already has ws_* data
 * sitting in MySQL from before this project moved those tables to SQLite. A
 * brand new grid owner never needs to run this - ws_db() already creates
 * empty, ready-to-use SQLite tables on its own, and current code never
 * creates ws_* tables in MySQL in the first place.
 *
 * - Never modifies the MySQL side beyond SELECT. Does not DROP or TRUNCATE
 *   anything there - see the printed cleanup block at the end.
 * - Safe to run more than once: existing rows are skipped (INSERT OR
 *   IGNORE), never duplicated or overwritten.
 * - Skips (does not fail) any table that doesn't exist in MySQL at all -
 *   that's the expected, normal case for a fresh install.
 *
 * Usage: php tools/migrate_ws_tables.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("This script is CLI-only.\n");
}

require_once __DIR__ . '/../include/config.php';

/** @var array<string, string[]> Column list per table, in a fixed order shared by both DBs. */
$TABLES = [
    'ws_search_log' => ['id', 'term', 'area', 'hits', 'last_search'],
    'ws_tickets' => ['id', 'user_uuid', 'user_name', 'contact_email', 'category', 'subject', 'message', 'status', 'created_at', 'updated_at'],
    'ws_messages' => ['id', 'sender_uuid', 'receiver_uuid', 'subject', 'body', 'created_at', 'is_read', 'sender_deleted', 'receiver_deleted'],
    'ws_recovery_codes' => ['id', 'PrincipalID', 'code_hash', 'is_used'],
    'ws_rate_limits' => ['id', 'rl_key', 'attempted_at'],
    'ws_hub_destinations' => ['id', 'category', 'title', 'region', 'x', 'y', 'z', 'description', 'tags', 'image_url', 'maturity', 'active', 'sort_order', 'created_at', 'updated_at'],
    'ws_hub_events' => ['id', 'title', 'start_time', 'end_time', 'region', 'x', 'y', 'z', 'host', 'category', 'description', 'maturity', 'active', 'created_at'],
    'ws_hub_land' => ['id', 'title', 'region', 'x', 'y', 'z', 'price', 'prims', 'size_m2', 'rental_period', 'contact', 'description', 'active', 'created_at'],
    'ws_hub_jobs' => ['id', 'title', 'pay', 'region', 'x', 'y', 'z', 'contact', 'description', 'active', 'created_at'],
    'ws_hub_teleport_log' => ['id', 'region', 'x', 'y', 'z', 'case_name', 'label', 'user_agent', 'ip', 'created_at'],
];

/**
 * Real installs drift from the schema the current code would create fresh:
 * columns get added later (or never), old installs may predate a column
 * entirely. Rather than assume the MySQL side matches our target column
 * list, discover what's actually there and only copy the intersection -
 * anything in our target list that's missing from the source table just
 * gets its normal SQLite column default instead.
 *
 * @return string[]|null Column names in MySQL's own order, or null if the table doesn't exist.
 */
function mysql_table_columns(mysqli $con, string $table): ?array {
    $stmt = mysqli_prepare(
        $con,
        "SELECT column_name FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ?
         ORDER BY ordinal_position"
    );
    mysqli_stmt_bind_param($stmt, 's', $table);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $cols = [];
    while ($row = mysqli_fetch_row($result)) {
        $cols[] = $row[0];
    }
    mysqli_stmt_close($stmt);
    return $cols ?: null;
}

function reconcile_autoincrement(PDO $sqlite, string $table): void {
    $maxId = (int)$sqlite->query("SELECT COALESCE(MAX(id), 0) FROM \"$table\"")->fetchColumn();
    if ($maxId <= 0) {
        return;
    }
    $sqlite->exec("INSERT INTO sqlite_sequence (name, seq)
        SELECT '$table', $maxId
        WHERE NOT EXISTS (SELECT 1 FROM sqlite_sequence WHERE name = '$table')");
    $sqlite->exec("UPDATE sqlite_sequence SET seq = $maxId
        WHERE name = '$table' AND seq < $maxId");
}

$mysql = db();
if (!$mysql) {
    fwrite(STDERR, "Could not connect to the MySQL database via db(). Aborting.\n");
    exit(1);
}

$sqlite = ws_db();
if (!$sqlite) {
    fwrite(STDERR, "Could not open the SQLite database via ws_db(). Aborting.\n");
    exit(1);
}

echo "Copying ws_* tables from MySQL into " . WS_DB_PATH . "\n";
echo str_repeat('-', 70) . "\n";

$grandTotalInserted = 0;

foreach ($TABLES as $table => $targetCols) {
    $realCols = mysql_table_columns($mysql, $table);
    if ($realCols === null) {
        echo sprintf("%-22s not present in MySQL - skipped (normal for a fresh install)\n", $table);
        continue;
    }

    // Only copy columns that actually exist on this install's version of the
    // table. Anything in our target schema that's missing from the source
    // (an older install, a column added later, etc.) just gets its normal
    // SQLite column default instead of failing the whole table.
    $cols = array_values(array_intersect($targetCols, $realCols));
    $hasId = in_array('id', $cols, true);
    if (count($cols) < count($targetCols)) {
        $missing = array_diff($targetCols, $cols);
        echo sprintf("%-22s note: source is missing column(s): %s\n", $table, implode(', ', $missing));
    }

    $colList = implode(', ', array_map(fn($c) => "`$c`", $cols));
    $orderBy = $hasId ? ' ORDER BY id' : '';
    $result  = mysqli_query($mysql, "SELECT $colList FROM `$table`$orderBy");
    if (!$result) {
        echo sprintf("%-22s MySQL SELECT failed: %s\n", $table, mysqli_error($mysql));
        continue;
    }

    $sqliteColList = implode(', ', array_map(fn($c) => "\"$c\"", $cols));
    $placeholders  = implode(', ', array_fill(0, count($cols), '?'));
    $insertStmt    = $sqlite->prepare("INSERT OR IGNORE INTO \"$table\" ($sqliteColList) VALUES ($placeholders)");

    $sourceRows = 0;
    $inserted   = 0;
    $errors     = 0;

    $sqlite->beginTransaction();
    try {
        while ($row = mysqli_fetch_assoc($result)) {
            $sourceRows++;
            $values = [];
            foreach ($cols as $c) {
                $values[] = $row[$c];
            }
            try {
                $insertStmt->execute($values);
                if ($insertStmt->rowCount() > 0) {
                    $inserted++;
                }
            } catch (Throwable $e) {
                $errors++;
                $rowLabel = $hasId ? "id={$row['id']}" : ('row #' . $sourceRows);
                fwrite(STDERR, "  row error in $table ($rowLabel): " . $e->getMessage() . "\n");
            }
        }
        $sqlite->commit();
        if ($hasId) {
            reconcile_autoincrement($sqlite, $table);
        }
    } catch (Throwable $e) {
        $sqlite->rollBack();
        fwrite(STDERR, "  transaction error in $table: " . $e->getMessage() . "\n");
        $errors++;
    }

    $skipped = $sourceRows - $inserted - $errors;
    $grandTotalInserted += $inserted;
    echo sprintf(
        "%-22s %d source rows, %d inserted, %d already present, %d errors\n",
        $table,
        $sourceRows,
        $inserted,
        max(0, $skipped),
        $errors
    );
}

echo str_repeat('-', 70) . "\n";
echo "Done. $grandTotalInserted new row(s) copied into SQLite.\n\n";

echo "This script never modifies MySQL. Once you've verified the data in\n";
echo WS_DB_PATH . ", you can drop the old MySQL tables yourself, e.g.:\n\n";
foreach (array_keys($TABLES) as $table) {
    echo "  DROP TABLE IF EXISTS $table;\n";
}
echo "\n(Only do this after confirming the site is running correctly against\nthe new SQLite database - this script does not verify that for you.)\n";
