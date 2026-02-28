<?php
// My Account / Profile dashboard for OpenSimulator web interface
// Uses global header/footer + site-wide CSS (Bootstrap-based)

// Set page title
if (!isset($title)) { $title = "My Profile"; }

// Shared header (handles config, sessions, HTML <head>, etc.)
require_once __DIR__ . '/../include/header.php';

if (!function_exists('format_region_location')) {
    /**
     * Format region location for display.
     * Accepts either meter-based (locX/locY in multiples of 256) or grid coordinates.
     * Returns "x, y" in grid coordinate space.
     */
    function format_region_location($x, $y) : string {
        if ($x === null || $y === null) return '';
        $xi = (int)$x;
        $yi = (int)$y;

        // If values look like meter-based coordinates (e.g., 256000), convert to region grid coords.
        if (($xi >= 8192 || $yi >= 8192) && ($xi % 256 === 0) && ($yi % 256 === 0)) {
            $xi = intdiv($xi, 256);
            $yi = intdiv($yi, 256);
        }
        return $xi . ', ' . $yi;
    }
}

// Require login. If user is not logged in, show a friendly message and stop.
if (empty($_SESSION['user']['principal_id'])): ?>
    <div class="container my-5">
        <div class="row">
            <div class="col-md-8 col-lg-6 mx-auto">
                <div class="card shadow-sm border-0">
                    <div class="card-body text-center">
                        <h1 class="h4 mb-3"><i class="bi bi-person-circle"></i> My Profile</h1>
                        <p class="text-muted mb-4">
                            You need to be logged in to view your account dashboard.
                        </p>
                        <a class="btn btn-primary"
                           href="../login.php?next=<?php echo urlencode('account/index.php'); ?>">
                            Log in
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
    require_once __DIR__ . "/../include/" . FOOTER_FILE;
    exit;
endif;

$UID = (string)($_SESSION['user']['principal_id'] ?? '');
$messages = [];
$errors   = [];
$newRecoveryCodes = null; // Used for displaying new codes after regeneration

// ------------------------------------------------------------------
// Local helpers (namespaced to avoid collisions)
// ------------------------------------------------------------------
if (!function_exists('h')) {
    function h($s) {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('osv_db')) {
    function osv_db() {
        if (!defined('DB_SERVER')) return null;
        $c = @db();
        if (!$c) return null;
        if (function_exists('mysqli_set_charset')) {
            mysqli_set_charset($c, 'utf8mb4');
        }
        return $c;
    }
}

if (!function_exists('osv_table_exists')) {
    function osv_table_exists(mysqli $c, string $t): bool {
        $t = $c->real_escape_string($t);
        if ($rs = $c->query("SHOW TABLES LIKE '{$t}'")) {
            $ok = $rs->num_rows > 0;
            $rs->close();
            return $ok;
        }
        return false;
    }
}

if (!function_exists('osv_get_columns')) {
    function osv_get_columns(mysqli $c, string $t): array {
        $cols = [];
        if ($rs = $c->query("SHOW COLUMNS FROM `{$t}`")) {
            while ($row = $rs->fetch_assoc()) {
                $cols[strtolower($row['Field'])] = $row['Field'];
            }
            $rs->close();
        }
        return $cols;
    }
}

if (!function_exists('osv_pick_col')) {
    function osv_pick_col(array $cols, array $cands): ?string {
        foreach ($cands as $cand) {
            $k = strtolower($cand);
            if (isset($cols[$k])) return $cols[$k];
        }
        return null;
    }
}

if (!function_exists('osv_bind_params')) {
    /**
     * Safely bind a dynamic list of parameters to a mysqli prepared statement.
     * mysqli_stmt::bind_param requires parameters to be passed by reference.
     */
    function osv_bind_params(mysqli_stmt $stmt, string $types, array &$params): bool {
        $refs = [];
        $refs[] = $types;

        foreach ($params as $k => &$v) {
            $refs[] = &$v;
        }
        // call_user_func_array preserves references in $refs
        return call_user_func_array([$stmt, 'bind_param'], $refs);
    }
}



if (!function_exists('osv_parse_service_urls')) {
    /**
     * Parse OpenSim-style ServiceURLs string into a lower-cased key/value map.
     * Typical format: "HomeURI=...;GatekeeperURI=...;InventoryServerURI=..."
     */
    function osv_parse_service_urls(?string $raw): array {
        $out = [];
        $s = trim((string)$raw);
        if ($s === '') return $out;

        // Some OpenSim installs store this as:
        //   HomeURI=...;GatekeeperURI=...;InventoryServerURI=...
        // Others use whitespace/newlines as separators, and values may be URL-encoded.
        // Normalize all common separators into whitespace tokens.
        $s = str_replace(["\r\n", "\r", "\n", ";", "\t"], ' ', $s);
        $s = preg_replace('/\s+/', ' ', trim($s));

        $tokens = $s === '' ? [] : explode(' ', $s);
        foreach ($tokens as $t) {
            if ($t === '') continue;
            $eq = strpos($t, '=');
            if ($eq === false) continue;

            $k = strtolower(trim(substr($t, 0, $eq)));
            $v = trim(substr($t, $eq + 1));
            if ($k === '') continue;

            // Values are sometimes stored URL-encoded (e.g. http%3a%2f%2f...)
            if (strpos($v, '%') !== false) {
                $v = rawurldecode($v);
            }

            $out[$k] = $v;
        }

        return $out;
    }
}

if (!function_exists('osv_local_grid_host')) {
    /**
     * Best-effort local grid host (used to distinguish local users from HG visitors).
     */
    function osv_local_grid_host(): string {
        // Prefer explicit host constant if your config defines it
        if (defined('HG_HOST') && (string)HG_HOST !== '') {
            return strtolower((string)HG_HOST);
        }

        $cands = [];
        if (defined('GRID_URI') && (string)GRID_URI !== '') $cands[] = (string)GRID_URI;
        if (isset($GLOBALS['GRID_URI']) && (string)$GLOBALS['GRID_URI'] !== '') $cands[] = (string)$GLOBALS['GRID_URI'];
        if (isset($_ENV['GRID_URI']) && (string)$_ENV['GRID_URI'] !== '') $cands[] = (string)$_ENV['GRID_URI'];

        foreach ($cands as $u) {
            $h = parse_url($u, PHP_URL_HOST);
            if ($h) return strtolower($h);
        }

        // Fallback: strip protocol and path if user passed a bare host
        foreach ($cands as $u) {
            $u = preg_replace('~^https?://~i', '', trim($u));
            $u = preg_replace('~/.*$~', '', $u);
            $u = preg_replace('~:\\d+$~', '', $u);
            if ($u !== '') return strtolower($u);
        }

        return '';
    }
}
if (!function_exists('osv_fmt_ts')) {
    function osv_fmt_ts($v): ?string {
        if ($v === null || $v === '') return null;
        if (ctype_digit((string)$v)) {
            $ts = (int)$v;
            if ($ts <= 0) return null;
            return date('Y-m-d H:i:s', $ts);
        }
        return (string)$v;
    }
}

if (!function_exists('osv_rights_from_flags')) {
    function osv_rights_from_flags($flags): array {
        $f = (int)$flags;
        return [
            'see_online' => (bool)($f & 1),
            'see_on_map' => (bool)($f & 2),
            'modify'     => (bool)($f & 4),
        ];
    }
}

// ------------------------------------------------------------------
// Write helpers (email, profile, partner, password)
// ------------------------------------------------------------------

if (!function_exists('osv_ensure_profile_row')) {
    function osv_ensure_profile_row(mysqli $conn, string $UID, string $table, array $cols): void {
        $idCol = osv_pick_col($cols, ['useruuid','UserUUID','PrincipalID','UUID']);
        if (!$idCol) {
            return;
        }

        // Already present?
        $sql = "SELECT 1 FROM `{$table}` WHERE `{$idCol}` = ? LIMIT 1";
        if (!($stmt = $conn->prepare($sql))) {
            return;
        }
        $stmt->bind_param('s', $UID);
        $exists = false;
        if ($stmt->execute() && ($res = $stmt->get_result())) {
            if ($res->fetch_row()) {
                $exists = true;
            }
            $res->close();
        }
        $stmt->close();
        if ($exists) {
            return;
        }

        // Insert a row that satisfies NOT NULL columns with no defaults (MySQL strict-safe)
        $colMeta = [];
        if ($rs = $conn->query("SHOW COLUMNS FROM `{$table}`")) {
            while ($row = $rs->fetch_assoc()) {
                $colMeta[] = $row; // Field, Type, Null, Default, Extra
            }
            $rs->close();
        }

        $insertCols = [$idCol];
        $params     = [$UID];
        $types      = 's';

        foreach ($colMeta as $c) {
            $field = $c['Field'];
            if (strcasecmp($field, $idCol) === 0) {
                continue;
            }

            $null  = strtoupper((string)$c['Null']);
            $def   = $c['Default']; // may be NULL
            $extra = strtolower((string)$c['Extra']);
            $type  = strtolower((string)$c['Type']);

            // We only need to supply values for NOT NULL columns with no default and not auto-increment
            if ($null !== 'NO' || $def !== null || strpos($extra, 'auto_increment') !== false) {
                continue;
            }

            $insertCols[] = $field;

            $lname = strtolower($field);
            $val = '';

            // UUID-ish fields
            if (strpos($lname, 'uuid') !== false || preg_match('/\b(char|varchar)\(36\)\b/', $type)) {
                $val = '00000000-0000-0000-0000-000000000000';
            }
            // numeric
            elseif (preg_match('/\b(tinyint|smallint|mediumint|int|bigint)\b/', $type)) {
                $val = '0';
            }
            elseif (preg_match('/\b(decimal|float|double)\b/', $type)) {
                $val = '0';
            }
            // date/time
            elseif (strpos($type, 'datetime') !== false || strpos($type, 'timestamp') !== false) {
                $val = '1970-01-01 00:00:00';
            }
            elseif (strpos($type, 'date') !== false) {
                $val = '1970-01-01';
            }
            // everything else: empty string is safe for text-ish columns
            else {
                $val = '';
            }

            $params[] = $val;
            $types .= 's';
        }

        $colsSql = '`' . implode('`,`', $insertCols) . '`';
        $phSql   = implode(',', array_fill(0, count($insertCols), '?'));
        $sql2    = "INSERT INTO `{$table}` ({$colsSql}) VALUES ({$phSql})";

        if (!($stmt2 = $conn->prepare($sql2))) {
            return;
        }

        // Use existing helper to bind params by reference safely
        if (!osv_bind_params($stmt2, $types, $params)) {
            $stmt2->close();
            return;
        }

        $stmt2->execute();
        $stmt2->close();
    }
}

if (!function_exists('osv_update_email')) {
    function osv_update_email(mysqli $conn, string $UID, string $email, array &$errors): bool {
        $UA = osv_table_exists($conn, 'useraccounts') ? 'useraccounts'
            : (osv_table_exists($conn, 'UserAccounts') ? 'UserAccounts'
            : (osv_table_exists($conn, 'casperia_useraccounts') ? 'casperia_useraccounts' : ''));
        if (!$UA) {
            $errors[] = 'Account table could not be found (useraccounts).';
            return false;
        }
        $cols     = osv_get_columns($conn, $UA);
        $idCol    = osv_pick_col($cols, ['PrincipalID','UUID','UserID']);
        $emailCol = osv_pick_col($cols, ['Email','email','EMail']);
        if (!$idCol || !$emailCol) {
            $errors[] = 'Unable to locate email column for this grid.';
            return false;
        }
        $sql = "UPDATE `{$UA}` SET `{$emailCol}` = ? WHERE `{$idCol}` = ?";
        if (!($stmt = $conn->prepare($sql))) {
            $errors[] = 'Could not prepare email update.';
            return false;
        }
        $stmt->bind_param('ss', $email, $UID);
        $ok = $stmt->execute();
        if (!$ok) {
            $errors[] = 'Database error while updating email.';
        }
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('osv_update_avatar_profile')) {
    function osv_update_avatar_profile(mysqli $conn, string $UID, string $about, string $imageUuid, array &$errors): bool {
        $UP = osv_table_exists($conn, 'userprofile') ? 'userprofile'
            : (osv_table_exists($conn, 'UserProfile') ? 'UserProfile' : '');
        if (!$UP) {
            $errors[] = 'Profile table (userprofile) could not be found.';
            return false;
        }
        $cols      = osv_get_columns($conn, $UP);
        $idCol     = osv_pick_col($cols, ['useruuid','UserUUID','PrincipalID','UUID']);
        $aboutCol  = osv_pick_col($cols, ['profileAboutText','profileabouttext','AboutText','abouttext']);
        $imageCol  = osv_pick_col($cols, ['profileImage','profileimage','Image','image']);
        if (!$idCol) {
            $errors[] = 'Unable to locate profile ID column.';
            return false;
        }

        osv_ensure_profile_row($conn, $UID, $UP, $cols);

        $fields = [];
        $types  = '';
        $values = [];

        if ($aboutCol !== null) {
            $fields[] = "`{$aboutCol}` = ?";
            $types   .= 's';
            $values[] = $about;
        }
        if ($imageCol !== null) {
            $fields[] = "`{$imageCol}` = ?";
            $types   .= 's';
            $values[] = $imageUuid;
        }

        if (!$fields) {
            $errors[] = 'No profile columns available to update.';
            return false;
        }

        $sql = "UPDATE `{$UP}` SET " . implode(', ', $fields) . " WHERE `{$idCol}` = ?";
        $types   .= 's';
        $values[] = $UID;

        if (!($stmt = $conn->prepare($sql))) {
            $errors[] = 'Could not prepare in-world profile update.';
            return false;
        }
        osv_bind_params($stmt, $types, $values);
        $ok = $stmt->execute();
        if (!$ok) {
            $errors[] = 'Database error while updating in-world profile.';
        }
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('osv_update_firstlife')) {
    function osv_update_firstlife(mysqli $conn, string $UID, string $firstText, string $firstImage, array &$errors): bool {
        $UP = osv_table_exists($conn, 'userprofile') ? 'userprofile'
            : (osv_table_exists($conn, 'UserProfile') ? 'UserProfile' : '');
        if (!$UP) {
            $errors[] = 'Profile table (userprofile) could not be found.';
            return false;
        }
        $cols       = osv_get_columns($conn, $UP);
        $idCol      = osv_pick_col($cols, ['useruuid','UserUUID','PrincipalID','UUID']);
        $firstTextC = osv_pick_col($cols, ['profileFirstText','profilefirsttext','FirstText','firsttext']);
        $firstImgC  = osv_pick_col($cols, ['profileFirstImage','profilefirstimage','FirstImage','firstimage']);
        if (!$idCol) {
            $errors[] = 'Unable to locate profile ID column.';
            return false;
        }

        osv_ensure_profile_row($conn, $UID, $UP, $cols);

        $fields = [];
        $types  = '';
        $values = [];

        if ($firstTextC !== null) {
            $fields[] = "`{$firstTextC}` = ?";
            $types   .= 's';
            $values[] = $firstText;
        }
        if ($firstImgC !== null) {
            $fields[] = "`{$firstImgC}` = ?";
            $types   .= 's';
            $values[] = $firstImage;
        }

        if (!$fields) {
            $errors[] = 'No first-life columns available to update.';
            return false;
        }

        $sql = "UPDATE `{$UP}` SET " . implode(', ', $fields) . " WHERE `{$idCol}` = ?";
        $types   .= 's';
        $values[] = $UID;

        if (!($stmt = $conn->prepare($sql))) {
            $errors[] = 'Could not prepare first-life update.';
            return false;
        }
        osv_bind_params($stmt, $types, $values);
        $ok = $stmt->execute();
        if (!$ok) {
            $errors[] = 'Database error while updating first-life profile.';
        }
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('osv_find_user_by_name_or_uuid')) {
    function osv_find_user_by_name_or_uuid(mysqli $conn, string $input): ?array {
        $input = trim($input);
        if ($input === '') return null;

        $UA = osv_table_exists($conn, 'useraccounts') ? 'useraccounts'
            : (osv_table_exists($conn, 'UserAccounts') ? 'UserAccounts'
            : (osv_table_exists($conn, 'casperia_useraccounts') ? 'casperia_useraccounts' : ''));
        if (!$UA) return null;

        $cols  = osv_get_columns($conn, $UA);
        $idCol = osv_pick_col($cols, ['PrincipalID','UUID','UserID']);
        $fCol  = osv_pick_col($cols, ['FirstName','firstname','first_name','First_Name','first']);
        $lCol  = osv_pick_col($cols, ['LastName','lastname','last_name','Last_Name','last']);
        if (!$idCol) return null;

        // UUID lookup
        if (preg_match('/^[0-9a-fA-F-]{36}$/', $input)) {
            $sql = "SELECT `{$idCol}` AS id, `{$fCol}` AS fn, `{$lCol}` AS ln FROM `{$UA}` WHERE `{$idCol}` = ? LIMIT 1";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param('s', $input);
                if ($stmt->execute() && ($res = $stmt->get_result())) {
                    if ($row = $res->fetch_assoc()) {
                        $res->close();
                        $name = trim(($row['fn'] ?? '') . ' ' . ($row['ln'] ?? ''));
                        return ['uuid' => (string)$row['id'], 'name' => $name];
                    }
                    $res->close();
                }
                $stmt->close();
            }
            return null;
        }

        // Name lookup
        $parts = preg_split('/\s+/', $input);
        if ($fCol && $lCol && count($parts) >= 2) {
            $first = $parts[0];
            $last  = $parts[count($parts)-1];
            $sql   = "SELECT `{$idCol}` AS id, `{$fCol}` AS fn, `{$lCol}` AS ln
                      FROM `{$UA}` WHERE `{$fCol}` = ? AND `{$lCol}` = ? LIMIT 1";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param('ss', $first, $last);
                if ($stmt->execute() && ($res = $stmt->get_result())) {
                    if ($row = $res->fetch_assoc()) {
                        $res->close();
                        $name = trim(($row['fn'] ?? '') . ' ' . ($row['ln'] ?? ''));
                        return ['uuid' => (string)$row['id'], 'name' => $name];
                    }
                    $res->close();
                }
                $stmt->close();
            }
        }

        if ($fCol) {
            $sql = "SELECT `{$idCol}` AS id, `{$fCol}` AS fn, `{$lCol}` AS ln
                    FROM `{$UA}` WHERE `{$fCol}` = ? LIMIT 1";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param('s', $input);
                if ($stmt->execute() && ($res = $stmt->get_result())) {
                    if ($row = $res->fetch_assoc()) {
                        $res->close();
                        $name = trim(($row['fn'] ?? '') . ' ' . ($row['ln'] ?? ''));
                        return ['uuid' => (string)$row['id'], 'name' => $name];
                    }
                    $res->close();
                }
                $stmt->close();
            }
        }

        return null;
    }
}

if (!function_exists('osv_update_partner')) {
    function osv_update_partner(mysqli $conn, string $UID, ?string $partnerUUID, array &$errors): bool {
        $UP = osv_table_exists($conn, 'userprofile') ? 'userprofile'
            : (osv_table_exists($conn, 'UserProfile') ? 'UserProfile' : '');
        if (!$UP) {
            $errors[] = 'Profile table (userprofile) could not be found.';
            return false;
        }
        $cols        = osv_get_columns($conn, $UP);
        $idCol       = osv_pick_col($cols, ['useruuid','UserUUID','PrincipalID','UUID']);
        $partnerCol  = osv_pick_col($cols, ['profilePartner','profilepartner','partner','Partner','partneruuid','PartnerUUID']);
        if (!$idCol || !$partnerCol) {
            $errors[] = 'Unable to locate partner column on profile table.';
            return false;
        }

        osv_ensure_profile_row($conn, $UID, $UP, $cols);

        $sql = "UPDATE `{$UP}` SET `{$partnerCol}` = ? WHERE `{$idCol}` = ?";
        if (!($stmt = $conn->prepare($sql))) {
            $errors[] = 'Could not prepare partner update.';
            return false;
        }
        $partnerUUID = $partnerUUID ?? '';
        $stmt->bind_param('ss', $partnerUUID, $UID);
        $ok = $stmt->execute();
        if (!$ok) {
            $errors[] = 'Database error while updating partner.';
        }
        $stmt->close();
        return $ok;
    }
}


// --- Partner helpers (mutual consent) ---
if (!function_exists('osv_get_partner_uuid')) {
    function osv_get_partner_uuid(mysqli $conn, string $UID, array &$errors = []): ?string {
        $UP = osv_table_exists($conn, 'userprofile') ? 'userprofile'
            : (osv_table_exists($conn, 'UserProfile') ? 'UserProfile' : '');
        if (!$UP) {
            $errors[] = 'Profile table (userprofile) could not be found.';
            return null;
        }
        $cols       = osv_get_columns($conn, $UP);
        $idCol      = osv_pick_col($cols, ['useruuid','UserUUID','PrincipalID','UUID']);
        $partnerCol = osv_pick_col($cols, ['profilePartner','profilepartner','partner','Partner','partneruuid','PartnerUUID']);
        if (!$idCol || !$partnerCol) {
            $errors[] = 'Unable to locate partner column on profile table.';
            return null;
        }
        $sql = "SELECT `{$partnerCol}` FROM `{$UP}` WHERE `{$idCol}` = ? LIMIT 1";
        if (!($stmt = $conn->prepare($sql))) {
            return null;
        }
        $stmt->bind_param('s', $UID);
        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }
        $stmt->bind_result($p);
        $pVal = null;
        if ($stmt->fetch()) {
            $pVal = trim((string)$p);
        }
        $stmt->close();
        return ($pVal !== '') ? $pVal : null;
    }
}

if (!function_exists('osv_is_reciprocal_partner')) {
    function osv_is_reciprocal_partner(mysqli $conn, string $UID, string $partnerUUID): bool {
        $errs = [];
        $theirPartner = osv_get_partner_uuid($conn, $partnerUUID, $errs);
        return $theirPartner && strcasecmp($theirPartner, $UID) === 0;
    }
}

if (!function_exists('osv_password_hash_opensim')) {
    function osv_password_hash_opensim(string $plain, string $salt): string {
        return md5(md5($plain) . ':' . $salt);
    }
}

if (!function_exists('osv_change_password')) {
    function osv_change_password(mysqli $conn, string $UID, string $newPassword, array &$errors): bool {
        $AT = osv_table_exists($conn, 'auth') ? 'auth'
            : (osv_table_exists($conn, 'Auth') ? 'Auth' : '');
        if (!$AT) {
            $errors[] = 'Auth table could not be found; password change is not available.';
            return false;
        }

        $cols   = osv_get_columns($conn, $AT);
        $idCol  = osv_pick_col($cols, ['UUID','PrincipalID','UserID']);
        $hashCol= osv_pick_col($cols, ['passwordHash','passwordhash','Hash','hash']);
        $saltCol= osv_pick_col($cols, ['passwordSalt','passwordsalt','Salt','salt']);
        if (!$idCol || !$hashCol || !$saltCol) {
            $errors[] = 'Auth table does not have expected password columns.';
            return false;
        }

        $exists = false;
        $sql = "SELECT 1 FROM `{$AT}` WHERE `{$idCol}` = ? LIMIT 1";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('s', $UID);
            if ($stmt->execute() && ($res = $stmt->get_result())) {
                if ($res->fetch_row()) {
                    $exists = true;
                }
                $res->close();
            }
            $stmt->close();
        }

        try {
            $salt = bin2hex(random_bytes(16));
        } catch (Exception $e) {
            $salt = md5(uniqid('', true));
        }
        $hash = osv_password_hash_opensim($newPassword, $salt);

        if ($exists) {
            $sql2 = "UPDATE `{$AT}` SET `{$saltCol}` = ?, `{$hashCol}` = ? WHERE `{$idCol}` = ?";
            if (!($stmt2 = $conn->prepare($sql2))) {
                $errors[] = 'Could not prepare password update.';
                return false;
            }
            $stmt2->bind_param('sss', $salt, $hash, $UID);
        } else {
            $sql2 = "INSERT INTO `{$AT}` (`{$idCol}`, `{$saltCol}`, `{$hashCol}`) VALUES (?,?,?)";
            if (!($stmt2 = $conn->prepare($sql2))) {
                $errors[] = 'Could not prepare password insert.';
                return false;
            }
            $stmt2->bind_param('sss', $UID, $salt, $hash);
        }

        $ok = $stmt2->execute();
        if (!$ok) {
            $errors[] = 'Database error while updating password.';
        }
        $stmt2->close();
        return $ok;
    }
}

// ------------------------------------------------------------------
// Handle POST actions (email, password, profiles, partner)
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';
    if ($action !== '') {
        $connPost = osv_db();
        if (!$connPost) {
            $errors[] = 'Database connection failed; your changes could not be saved.';
        } elseif ($UID === '') {
            $errors[] = 'You must be logged in to change your account.';
        } else {
            switch ($action) {
                case 'update_email':
                    $email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
                    if ($email === '') {
                        $errors[] = 'Email cannot be empty.';
                    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = 'Please enter a valid email address.';
                    } else {
                        if (osv_update_email($connPost, $UID, $email, $errors)) {
                            $messages[] = 'Email address updated.';
                            $_SESSION['user']['email'] = $email;
                        }
                    }
                    break;

                case 'update_password':
                    $pw1 = isset($_POST['password']) ? (string)$_POST['password'] : '';
                    $pw2 = isset($_POST['confirm_password']) ? (string)$_POST['confirm_password'] : '';
                    if ($pw1 === '' || $pw2 === '') {
                        $errors[] = 'Password fields cannot be empty.';
                    } elseif ($pw1 !== $pw2) {
                        $errors[] = 'New password entries do not match.';
                    } elseif (strlen($pw1) < 8) {
                        $errors[] = 'Password must be at least 8 characters long.';
                    } else {
                        if (osv_change_password($connPost, $UID, $pw1, $errors)) {
                            $messages[] = 'Password updated. Use this new password next time you log in.';
                        }
                    }
                    break;
                
                // --- NEW ACTION: REGENERATE CODES ---
                case 'regenerate_codes':
                    if (!osv_table_exists($connPost, 'ws_recovery_codes')) {
                        $errors[] = 'Recovery codes are not available on this system.';
                        break;
                    }

                    // Detect column names (supports slight schema variations)
                    $rcTable = 'ws_recovery_codes';
                    $rcCols  = osv_get_columns($connPost, $rcTable);
                    $pidCol  = osv_pick_col($rcCols, ['PrincipalID','principalid','UserUUID','useruuid','PrincipalId']);
                    $hashCol = osv_pick_col($rcCols, ['code_hash','codehash','CodeHash','hash']);
                    $usedCol = osv_pick_col($rcCols, ['is_used','isused','IsUsed','used']);

                    if (!$pidCol || !$hashCol) {
                        $errors[] = 'Recovery codes table schema is missing required columns.';
                        break;
                    }

                    $new_codes_raw = [];
                    $hash = ''; // bound by reference

                    $didTx = false;
                    if (method_exists($connPost, 'begin_transaction')) {
                        $connPost->begin_transaction();
                        $didTx = true;
                    }

                    try {
                        // 1) Delete old codes for this user (prepared)
                        $sqlDel = "DELETE FROM `{$rcTable}` WHERE `{$pidCol}` = ?";
                        if (!($stDel = $connPost->prepare($sqlDel))) {
                            throw new Exception('Could not prepare recovery-code delete.');
                        }
                        $stDel->bind_param('s', $UID);
                        if (!$stDel->execute()) {
                            throw new Exception('Could not clear existing recovery codes.');
                        }
                        $stDel->close();

                        // 2) Insert 5 new ones (store hashes only; raw codes are shown once)
                        $sqlInsert = $usedCol
                            ? "INSERT INTO `{$rcTable}` (`{$pidCol}`, `{$hashCol}`, `{$usedCol}`) VALUES (?,?,0)"
                            : "INSERT INTO `{$rcTable}` (`{$pidCol}`, `{$hashCol}`) VALUES (?,?)";

                        if (!($stmt = $connPost->prepare($sqlInsert))) {
                            throw new Exception('Could not prepare recovery-code insert.');
                        }

                        // Bind once; $hash is updated each loop
                        $stmt->bind_param('ss', $UID, $hash);

                        for ($i = 0; $i < 5; $i++) {
                            $raw = strtoupper(bin2hex(random_bytes(4))); // 8 chars
                            $new_codes_raw[] = $raw;
                            $hash = password_hash($raw, PASSWORD_DEFAULT);

                            if (!$stmt->execute()) {
                                throw new Exception('Database error while generating recovery codes.');
                            }
                        }
                        $stmt->close();

                        if ($didTx) {
                            $connPost->commit();
                        }

                        $newRecoveryCodes = $new_codes_raw; // Pass to view
                        $messages[] = 'New recovery codes generated. Save them now; they will not be shown again.';
                    } catch (Throwable $e) {
                        if ($didTx) {
                            $connPost->rollback();
                        }
                        $errors[] = $e->getMessage();
                    }
                    break;

                case 'update_profile':
                    $about = isset($_POST['about']) ? trim((string)$_POST['about']) : '';
                    $img   = isset($_POST['profile_image_uuid']) ? trim((string)$_POST['profile_image_uuid']) : '';
                    if ($about === '' && $img === '') {
                        $errors[] = 'Nothing to update in in-world profile.';
                    } else {
                        if (osv_update_avatar_profile($connPost, $UID, $about, $img, $errors)) {
                            $messages[] = 'In-world profile updated.';
                        }
                    }
                    break;

                case 'update_firstlife':
                    $firstImg  = isset($_POST['first_image_uuid']) ? trim((string)$_POST['first_image_uuid']) : '';
                    $firstText = isset($_POST['first_text']) ? trim((string)$_POST['first_text']) : '';
                    if ($firstImg === '' && $firstText === '') {
                        $errors[] = 'Nothing to update in first-life profile.';
                    } else {
                        if (osv_update_firstlife($connPost, $UID, $firstText, $firstImg, $errors)) {
                            $messages[] = 'First-life profile updated.';
                        }
                    }
                    break;

                case 'partner_request':
                    $input = isset($_POST['partner_input']) ? trim((string)$_POST['partner_input']) : '';
                    if ($input === '') {
                        $errors[] = 'Please enter an avatar name or UUID.';
                        break;
                    }
                    // block if already partnered (reciprocal)
                    $myPartner = osv_get_partner_uuid($connPost, $UID, $errors);
                    if ($myPartner && osv_is_reciprocal_partner($connPost, $UID, $myPartner)) {
                        $errors[] = 'You already have a partner. End the partnership before sending a new request.';
                        break;
                    }
                    // block if you already have a pending outgoing request
                    if ($myPartner && !osv_is_reciprocal_partner($connPost, $UID, $myPartner)) {
                        $errors[] = 'You already have a pending partner request. Cancel it before sending a new one.';
                        break;
                    }
                    $target = osv_find_user_by_name_or_uuid($connPost, $input);
                    if (!$target) {
                        $errors[] = 'Could not find an avatar matching that name or UUID.';
                    } elseif ($target['uuid'] === $UID) {
                        $errors[] = 'You cannot set yourself as partner.';
                    } else {
                        // block if target already has a reciprocal partner with someone else
                        $tp = osv_get_partner_uuid($connPost, $target['uuid'], $errors);
                        if ($tp && osv_is_reciprocal_partner($connPost, $target['uuid'], $tp) && strcasecmp($tp, $UID) !== 0) {
                            $errors[] = 'That avatar is already partnered.';
                        } else {
                            if (osv_update_partner($connPost, $UID, $target['uuid'], $errors)) {
                                $name = $target['name'] !== '' ? $target['name'] : $target['uuid'];
                                $messages[] = 'Partner request sent to ' . $name . '.';
                            }
                        }
                    }
                    break;

                case 'partner_cancel':
                    if (osv_update_partner($connPost, $UID, null, $errors)) {
                        $messages[] = 'Partner request canceled.';
                    }
                    break;

                case 'partner_accept':
                    $from = isset($_POST['from_uuid']) ? trim((string)$_POST['from_uuid']) : '';
                    if ($from === '') {
                        $errors[] = 'Invalid partner request.';
                        break;
                    }
                    $myPartner = osv_get_partner_uuid($connPost, $UID, $errors);
                    if ($myPartner && osv_is_reciprocal_partner($connPost, $UID, $myPartner) && strcasecmp($myPartner, $from) !== 0) {
                        $errors[] = 'You already have a partner. End the partnership before accepting another request.';
                        break;
                    }
                    $reqPartner = osv_get_partner_uuid($connPost, $from, $errors);
                    if (!$reqPartner || strcasecmp($reqPartner, $UID) !== 0) {
                        $errors[] = 'This request is no longer valid.';
                        break;
                    }
                    if (osv_update_partner($connPost, $UID, $from, $errors)) {
                        $messages[] = 'Partnership established.';
                    }
                    break;

                case 'partner_decline':
                    $from = isset($_POST['from_uuid']) ? trim((string)$_POST['from_uuid']) : '';
                    if ($from === '') {
                        $errors[] = 'Invalid partner request.';
                        break;
                    }
                    $reqPartner = osv_get_partner_uuid($connPost, $from, $errors);
                    if ($reqPartner && strcasecmp($reqPartner, $UID) === 0) {
                        if (osv_update_partner($connPost, $from, null, $errors)) {
                            $messages[] = 'Partner request declined.';
                        }
                    } else {
                        $messages[] = 'Partner request already cleared.';
                    }
                    break;

                case 'partner_break':
                    $myPartner = osv_get_partner_uuid($connPost, $UID, $errors);
                    if (!$myPartner) {
                        $errors[] = 'No partner set on this account.';
                        break;
                    }
                    if (osv_update_partner($connPost, $UID, null, $errors)) {
                        if (osv_is_reciprocal_partner($connPost, $UID, $myPartner)) {
                            $tmpErrs = [];
                            osv_update_partner($connPost, $myPartner, null, $tmpErrs);
                        }
                        $messages[] = 'Partnership ended.';
                    }
                    break;


                default:
                    // Unknown / not yet implemented action; ignore safely.
                    break;
            }
        }

        if ($connPost instanceof mysqli) {
            $connPost->close();
        }
    }
}

// ------------------------------------------------------------------
// Load profile + related info (useraccounts, GridUser, regions,
// picks, friends, partner).
// All reads only – no writes yet (safe against DB).
// ------------------------------------------------------------------
$profile = [
    'principal_id' => $UID,
    'name'         => $_SESSION['user']['name']  ?? '',
    'email'        => $_SESSION['user']['email'] ?? '',
    'created'      => null,
    'created_human'=> null,
    'user_level'   => null,
    'online'       => null,
    'last_login'   => null,
    'last_logout'  => null,
    'home_region'  => null,
];

$conn  = osv_db();
$UA    = '';
$uaCols = [];
$recoveryCount = 0; // New variable for security tab

if ($conn) {
    // --- useraccounts table (identity, email, created, level) ---
    $UA = osv_table_exists($conn, 'useraccounts') ? 'useraccounts'
        : (osv_table_exists($conn, 'UserAccounts') ? 'UserAccounts'
        : (osv_table_exists($conn, 'casperia_useraccounts') ? 'casperia_useraccounts' : ''));

    if ($UA) {
        $uaCols     = osv_get_columns($conn, $UA);
        $ua_id      = osv_pick_col($uaCols, ['PrincipalID','UUID','UserID']);
        $ua_first   = osv_pick_col($uaCols, ['FirstName','firstname','first_name','First_Name','first']);
        $ua_last    = osv_pick_col($uaCols, ['LastName','lastname','last_name','Last_Name','last']);
        $ua_email   = osv_pick_col($uaCols, ['Email','email','EMail']);
        $ua_created = osv_pick_col($uaCols, ['Created','created','created_at','createdAt']);
        $ua_level   = osv_pick_col($uaCols, ['UserLevel','userLevel','user_level','Level']);

        // Basic identity
        if ($ua_id && $ua_first && $ua_last && $ua_email) {
            $sql = "SELECT u.`{$ua_first}`, u.`{$ua_last}`, u.`{$ua_email}` 
                    FROM `{$UA}` u WHERE u.`{$ua_id}` = ? LIMIT 1";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param('s', $UID);
                if ($stmt->execute()) {
                    $stmt->bind_result($FirstName, $LastName, $Email);
                    if ($stmt->fetch()) {
                        $profile['name']  = trim(($FirstName ?? '') . ' ' . ($LastName ?? ''));
                        $profile['email'] = (string)$Email;
                    }
                }
                $stmt->close();
            }
        }

        // Created / level
        if ($ua_id && ($ua_created || $ua_level)) {
            $fields = [];
            if ($ua_created) $fields[] = "u.`{$ua_created}`";
            if ($ua_level)   $fields[] = "u.`{$ua_level}`";

            if ($fields) {
                $sql = "SELECT " . implode(',', $fields) . " FROM `{$UA}` u WHERE u.`{$ua_id}` = ? LIMIT 1";
                if ($stmt = $conn->prepare($sql)) {
                    $stmt->bind_param('s', $UID);
                    if ($stmt->execute()) {
                        if ($ua_created && $ua_level) {
                            $stmt->bind_result($Created, $UserLevel);
                            if ($stmt->fetch()) {
                                $profile['created']    = (string)$Created;
                                $profile['user_level'] = (string)$UserLevel;
                            }
                        } elseif ($ua_created) {
                            $stmt->bind_result($Created);
                            if ($stmt->fetch()) {
                                $profile['created'] = (string)$Created;
                            }
                        } elseif ($ua_level) {
                            $stmt->bind_result($UserLevel);
                            if ($stmt->fetch()) {
                                $profile['user_level'] = (string)$UserLevel;
                            }
                        }
                    }
                    $stmt->close();
                }
                $profile['created_human'] = osv_fmt_ts($profile['created']);
            }
        }
    }

    // --- GridUser : online, last login/logout, home region ---
    $GU = osv_table_exists($conn, 'GridUser') ? 'GridUser'
        : (osv_table_exists($conn, 'griduser') ? 'griduser' : '');

    if ($GU) {
        $guCols    = osv_get_columns($conn, $GU);
        $gu_id     = osv_pick_col($guCols, ['UserID','UUID','PrincipalID']);
        $gu_online = osv_pick_col($guCols, ['Online','online']);
        $gu_llogin = osv_pick_col($guCols, ['Login','login','LastLogin','lastlogin','LastSeen','lastSeen']);
        $gu_llogout= osv_pick_col($guCols, ['Logout','logout','LastLogout','lastlogout']);
        $gu_home   = osv_pick_col($guCols, ['HomeRegionID','homeRegionID','HomeRegion','homeRegion']);

        if ($gu_id && ($gu_online || $gu_llogin || $gu_llogout || $gu_home)) {
            $fields = [];
            if ($gu_online)  $fields[] = "g.`{$gu_online}`";
            if ($gu_llogin)  $fields[] = "g.`{$gu_llogin}`";
            if ($gu_llogout) $fields[] = "g.`{$gu_llogout}`";
            if ($gu_home)    $fields[] = "g.`{$gu_home}`";

            $sql = "SELECT " . implode(',', $fields) . " 
                    FROM `{$GU}` g WHERE g.`{$gu_id}` = ? LIMIT 1";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param('s', $UID);
                if ($stmt->execute()) {
                    $o = $ll = $lo = $hr = null;
                    switch (count($fields)) {
                        case 4: $stmt->bind_result($o, $ll, $lo, $hr); break;
                        case 3: $stmt->bind_result($o, $ll, $lo); break;
                        case 2: $stmt->bind_result($o, $ll); break;
                        case 1: $stmt->bind_result($o); break;
                    }
                    if ($stmt->fetch()) {
                        if ($gu_online) {
                            $profile['online'] = isset($o)
                                ? (is_numeric($o) ? ((int)$o > 0) : (strtolower((string)$o) === 'true'))
                                : null;
                        }
                        if ($gu_llogin)  $profile['last_login']  = osv_fmt_ts($ll);
                        if ($gu_llogout) $profile['last_logout'] = osv_fmt_ts($lo);
                        if ($gu_home)    $profile['home_region'] = (string)$hr;
                    }
                }
                $stmt->close();
            }
        }
    }
    
    // --- LOAD RECOVERY CODES COUNT (NEW) ---
    if (osv_table_exists($conn, 'ws_recovery_codes')) {
        $rcTable = 'ws_recovery_codes';
        $rcCols  = osv_get_columns($conn, $rcTable);

        $pidCol  = osv_pick_col($rcCols, ['PrincipalID','principalid','UserUUID','useruuid','PrincipalId']);
        $usedCol = osv_pick_col($rcCols, ['is_used','isused','IsUsed','used']);

        if ($pidCol) {
            $sql = "SELECT COUNT(*) FROM `{$rcTable}` WHERE `{$pidCol}` = ?"
                . ($usedCol ? " AND `{$usedCol}` = 0" : "");

            if ($st = $conn->prepare($sql)) {
                $st->bind_param('s', $UID);
                if ($st->execute()) {
                    $cnt = 0;
                    $st->bind_result($cnt);
                    if ($st->fetch()) {
                        $recoveryCount = (int)$cnt;
                    }
                }
                $st->close();
            }
        }
    }
}

// --- Regions owned by this user ---
$myRegions = [];
if ($conn && $UID !== '') {
    $REGIONS = osv_table_exists($conn, 'regions') ? 'regions'
        : (osv_table_exists($conn, 'GridRegions') ? 'GridRegions' : '');
    $ESET    = osv_table_exists($conn, 'estate_settings') ? 'estate_settings'
        : (osv_table_exists($conn, 'EstateSettings') ? 'EstateSettings' : '');
    $EMAP    = osv_table_exists($conn, 'estate_map') ? 'estate_map'
        : (osv_table_exists($conn, 'EstateMap') ? 'EstateMap' : '');

    if ($REGIONS && $ESET && $EMAP) {
        $rCols = osv_get_columns($conn, $REGIONS);
        $eCols = osv_get_columns($conn, $ESET);
        $mCols = osv_get_columns($conn, $EMAP);

        $r_uuid  = osv_pick_col($rCols, ['regionUUID','uuid','RegionID','region_id']);
        $r_name  = osv_pick_col($rCols, ['regionName','name','RegionName']);
        $r_x     = osv_pick_col($rCols, ['locX','x','gridX']);
        $r_y     = osv_pick_col($rCols, ['locY','y','gridY']);
        $r_sx    = osv_pick_col($rCols, ['sizeX','SizeX']);
        $r_sy    = osv_pick_col($rCols, ['sizeY','SizeY']);

        $m_region= osv_pick_col($mCols, ['RegionID','regionID','regionUUID','uuid']);
        $m_est   = osv_pick_col($mCols, ['EstateID','estateID']);

        $e_id    = osv_pick_col($eCols, ['EstateID','estateID']);
        $e_owner = osv_pick_col($eCols, ['OwnerUUID','EstateOwner','ownerUUID']);
        $e_name  = osv_pick_col($eCols, ['EstateName','name']);

        if ($r_uuid && $r_name && $m_region && $m_est && $e_id && $e_owner) {
            $fields = "r.`$r_uuid` AS uuid, r.`$r_name` AS name";
            if ($r_x && $r_y)   $fields .= ", r.`$r_x` AS x, r.`$r_y` AS y";
            if ($r_sx && $r_sy) $fields .= ", r.`$r_sx` AS sx, r.`$r_sy` AS sy";
            if ($e_name)        $fields .= ", es.`$e_name` AS estate";

            $sql = "SELECT $fields
                    FROM `$REGIONS` r
                    JOIN `$EMAP`   em ON em.`$m_region` = r.`$r_uuid`
                    JOIN `$ESET`   es ON es.`$e_id`     = em.`$m_est`
                    WHERE es.`$e_owner` = ?
                    ORDER BY r.`$r_name` ASC";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param('s', $UID);
                if ($stmt->execute() && ($res = $stmt->get_result())) {
                    while ($row = $res->fetch_assoc()) {
                        $myRegions[] = $row;
                    }
                }
                $stmt->close();
            }
        } else {
            // Fallback: some forks store owner directly on regions
            $r_owner = osv_pick_col($rCols, ['owner_uuid','OwnerUUID','ownerID','OwnerID']);
            if ($r_owner && $r_uuid && $r_name) {
                $fields = "r.`$r_uuid` AS uuid, r.`$r_name` AS name";
                if ($r_x && $r_y)   $fields .= ", r.`$r_x` AS x, r.`$r_y` AS y";
                if ($r_sx && $r_sy) $fields .= ", r.`$r_sx` AS sx, r.`$r_sy` AS sy";

                $sql = "SELECT $fields FROM `$REGIONS` r 
                        WHERE r.`$r_owner` = ? ORDER BY r.`$r_name`";
                if ($stmt = $conn->prepare($sql)) {
                    $stmt->bind_param('s', $UID);
                    if ($stmt->execute() && ($res = $stmt->get_result())) {
                        while ($row = $res->fetch_assoc()) {
                            $myRegions[] = $row;
                        }
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// --- Picks / Favorites ---
$myPicks = [];
if ($conn && $UID !== '') {
    $PICKS = osv_table_exists($conn, 'userpicks') ? 'userpicks'
        : (osv_table_exists($conn, 'UserPicks') ? 'UserPicks'
        : (osv_table_exists($conn, 'casperia_userpicks') ? 'casperia_userpicks' : ''));

    if ($PICKS) {
        $pCols    = osv_get_columns($conn, $PICKS);
        $p_creator= osv_pick_col($pCols, ['creatorid','CreatorID','creatorId','UserID','PrincipalID']);
        $p_name   = osv_pick_col($pCols, ['name','PickName','label']);
        $p_desc   = osv_pick_col($pCols, ['description','desc','PickDesc']);
        $p_parcel = osv_pick_col($pCols, ['parceluuid','ParcelUUID','parcel','ParcelID']);
        $p_snap   = osv_pick_col($pCols, ['snapshotuuid','SnapshotUUID','snapshot','TextureID']);
        $p_uuid   = osv_pick_col($pCols, ['pickuuid','PickUUID','uuid','ID']);

        if ($p_creator && $p_name) {
            $fields = [];
            $fields[] = "p.`{$p_name}` AS name";
            if ($p_desc)   $fields[] = "p.`{$p_desc}` AS description";
            if ($p_parcel) $fields[] = "p.`{$p_parcel}` AS parceluuid";
            if ($p_snap)   $fields[] = "p.`{$p_snap}` AS snapshotuuid";
            if ($p_uuid)   $fields[] = "p.`{$p_uuid}` AS uuid";

            $sql = "SELECT " . implode(',', $fields) . " 
                    FROM `{$PICKS}` p WHERE p.`{$p_creator}` = ? 
                    ORDER BY p.`{$p_name}` ASC";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param('s', $UID);
                if ($stmt->execute() && ($res = $stmt->get_result())) {
                    while ($row = $res->fetch_assoc()) {
                        $myPicks[] = $row;
                    }
                }
                $stmt->close();
            }
        }
    }
}

// --- Friends (local + HG + online subset) ---
$friendsLocal = [];
$friendsHG = [];
$friendsOnlineLocal = [];

// Debug/testing helpers for Hypergrid friend parsing (admin only)
$friendsDebug = isset($_GET['friends_debug']) && ((int)($profile['user_level'] ?? 0) >= 200);
$friendsMockHg = isset($_GET['mock_hg']) && $friendsDebug;

if ($conn && $UID !== '') {
    $FR = osv_table_exists($conn, 'friends') ? 'friends'
        : (osv_table_exists($conn, 'Friends') ? 'Friends' : '');

    if ($FR) {
        $fCols   = osv_get_columns($conn, $FR);
        $f_owner = osv_pick_col($fCols, ['PrincipalID','principalid','UserID','userid']);
        $f_friend= osv_pick_col($fCols, ['Friend','friend','FriendID','friendid']);
        $f_my    = osv_pick_col($fCols, ['MyFlags','myflags','Flags','flags']);
        $f_their = osv_pick_col($fCols, ['TheirFlags','theirflags']);
        $f_offered = osv_pick_col($fCols, ['Offered','offered','IsOffered','isOffered']);

        if ($f_owner && $f_friend) {
            $sql = "SELECT f.`{$f_friend}` AS friend, " .
                ($f_my ? "f.`{$f_my}` AS myflags" : "0 AS myflags") .
                ($f_offered ? ", f.`{$f_offered}` AS offered" : "") .
                " FROM `{$FR}` f WHERE f.`{$f_owner}` = ?" .
                ($f_offered ? " AND f.`{$f_offered}` = 0" : "");
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param('s', $UID);
                if ($stmt->execute() && ($res = $stmt->get_result())) {
                    $UUIDs = [];
                    $rows  = [];
                    while ($row = $res->fetch_assoc()) {
                        $fid = (string)($row['friend'] ?? '');
                        if ($fid === '' || $fid === $UID) {
                            continue;
                        }
                        if (!isset($seenFriends)) $seenFriends = [];
                        if (isset($seenFriends[$fid])) {
                            continue;
                        }
                        $seenFriends[$fid] = true;

                        $rows[]  = $row;
                        $UUIDs[] = $fid;
                    }
                    $stmt->close();

                    // Map friend uuid => account meta (name + local/HG inference via ServiceURLs)
                    $friendAcct = []; // uuid => ['name'=>..,'is_hg'=>bool,'grid_label'=>..,'homeuri'=>..,'gatekeeper'=>..]
                    $localHost = osv_local_grid_host();
                    if ($UA && $UUIDs) {
                        $ua_id    = osv_pick_col($uaCols, ['PrincipalID','UUID','UserID']);
                        $ua_first = osv_pick_col($uaCols, ['FirstName','firstname','first_name','First_Name','first']);
                        $ua_last  = osv_pick_col($uaCols, ['LastName','lastname','last_name','Last_Name','last']);
                        $ua_svc   = osv_pick_col($uaCols, ['ServiceURLs','serviceurls','ServiceUrl','serviceurl','ServiceURLS']);
                        if ($ua_id && $ua_first && $ua_last) {
                            $chunks = array_chunk($UUIDs, 100);
                            foreach ($chunks as $chunk) {
                                $ph = implode(',', array_fill(0, count($chunk), '?'));

                                $fields = [
                                    "u.`{$ua_id}` AS id",
                                    "u.`{$ua_first}` AS fn",
                                    "u.`{$ua_last}` AS ln",
                                ];
                                if ($ua_svc) $fields[] = "u.`{$ua_svc}` AS svc";

                                $sql2 = "SELECT " . implode(',', $fields) . "
                                         FROM `{$UA}` u WHERE u.`{$ua_id}` IN ($ph)";
                                if ($st2 = $conn->prepare($sql2)) {
                                    $types = str_repeat('s', count($chunk));
                                    osv_bind_params($st2, $types, $chunk);
                                    if ($st2->execute() && ($r2 = $st2->get_result())) {
                                        while ($r = $r2->fetch_assoc()) {
                                            $id = (string)($r['id'] ?? '');
                                            if ($id === '') continue;

                                            $name = trim(($r['fn'] ?? '') . ' ' . ($r['ln'] ?? ''));
                                            if ($name === '') $name = $id;

                                            $svcMap = $ua_svc ? osv_parse_service_urls($r['svc'] ?? '') : [];
                                            $home   = $svcMap['homeuri'] ?? '';
                                            $gate   = $svcMap['gatekeeperuri'] ?? '';
                                            $remote = $home !== '' ? $home : $gate;

                                            $remoteHost = '';
                                            if ($remote !== '') {
                                                $remoteHost = parse_url($remote, PHP_URL_HOST);
                                                if (!$remoteHost) {
                                                    $remoteHost = preg_replace('~^https?://~i', '', trim($remote));
                                                    $remoteHost = preg_replace('~/.*$~', '', $remoteHost);
                                                    $remoteHost = preg_replace('~:\\d+$~', '', $remoteHost);
                                                }
                                                $remoteHost = strtolower((string)$remoteHost);
                                            }

                                            // HG visitor detection: if HomeURI/GatekeeperURI exists and doesn't match local host
                                            $isHg = false;
                                            if ($remote !== '') {
                                                $isHg = ($localHost !== '') ? (strcasecmp($remoteHost, $localHost) !== 0) : true;
                                            }

                                            $friendAcct[$id] = [
                                                'name'       => $name,
                                                'is_hg'      => $isHg,
                                                'grid_label' => $isHg ? ($remoteHost !== '' ? $remoteHost : 'Hypergrid') : 'Local',
                                                'homeuri'    => $home,
                                                'gatekeeper' => $gate,
                                            ];
                                        }
                                    }
                                    $st2->close();
                                }
                            }
                        }
                    }

// Online map (local only)
                    $onlineMap = [];
                    $GU = osv_table_exists($conn, 'GridUser') ? 'GridUser'
                        : (osv_table_exists($conn, 'griduser') ? 'griduser' : '');
                    if ($GU && $UUIDs) {
                        $guCols    = osv_get_columns($conn, $GU);
                        $gu_id     = osv_pick_col($guCols, ['UserID','UUID','PrincipalID']);
                        $gu_online = osv_pick_col($guCols, ['Online','online']);
                        if ($gu_id && $gu_online) {
                            $chunks = array_chunk($UUIDs, 100);
                            foreach ($chunks as $chunk) {
                                $ph = implode(',', array_fill(0, count($chunk), '?'));
                                $sql3 = "SELECT g.`{$gu_id}` AS id, g.`{$gu_online}` AS onl 
                                         FROM `{$GU}` g WHERE g.`{$gu_id}` IN ($ph)";
                                if ($st3 = $conn->prepare($sql3)) {
                                    $types = str_repeat('s', count($chunk));
                                    osv_bind_params($st3, $types, $chunk);
                                    if ($st3->execute() && ($r3 = $st3->get_result())) {
                                        while ($r = $r3->fetch_assoc()) {
                                            $on = $r['onl'];
                                            $onlineMap[$r['id']] = is_numeric($on)
                                                ? ((int)$on > 0)
                                                : (strtolower((string)$on) === 'true');
                                        }
                                    }
                                    $st3->close();
                                }
                            }
                        }
                    }

                    // Partition local vs HG (HG visitors in UserAccounts stay in HG list if ServiceURLs points off-grid)
                    foreach ($rows as $row) {
                        $fid   = (string)($row['friend'] ?? '');
                        if ($fid === '') continue;

                        $flags = osv_rights_from_flags($row['myflags'] ?? 0);

                        $acct    = $friendAcct[$fid] ?? null;
                        $isHg    = $acct ? (bool)($acct['is_hg'] ?? false) : true;
                        $isLocal = $acct ? !$isHg : false;

                        $name      = $acct ? (string)($acct['name'] ?? $fid) : $fid;
                        $gridLabel = $acct ? (string)($acct['grid_label'] ?? ($isLocal ? 'Local' : 'Hypergrid')) : 'Hypergrid';

                        $entry = [
                            'uuid'       => $fid,
                            'name'       => $name,
                            'grid'       => $gridLabel,
                            'see_online' => $flags['see_online'],
                            'see_on_map' => $flags['see_on_map'],
                            'modify'     => $flags['modify'],
                            'online'     => $isLocal ? (bool)($onlineMap[$fid] ?? false) : false,
                            'homeuri'    => $acct ? (string)($acct['homeuri'] ?? '') : '',
                            'gatekeeper' => $acct ? (string)($acct['gatekeeper'] ?? '') : '',
                            'is_hg'      => (bool)$isHg,
                        ];

                        if ($isLocal) $friendsLocal[] = $entry;
                        else          $friendsHG[]    = $entry;
                    }

// Sort friends (stable display)
                    if ($friendsLocal) {
                        usort($friendsLocal, fn($a, $b) => strcasecmp((string)$a['name'], (string)$b['name']));
                    }
                    if ($friendsHG) {
                        usort($friendsHG, fn($a, $b) => strcasecmp((string)$a['name'], (string)$b['name']));
                    }

// Online subset (local)
                    foreach ($friendsLocal as $fr) {
                        if ($fr['online']) $friendsOnlineLocal[] = $fr;
                    }
                } else {
                    $stmt->close();
                }
            }
        }
    }
}





// Optional mock HG friend for testing (visit this page with ?friends_debug=1&mock_hg=1 as admin)
if ($friendsMockHg) {
    $friendsHG[] = [
        'uuid'       => '00000000-0000-0000-0000-000000000000',
        'name'       => 'Mock HG Friend',
        'grid'       => 'example.org:8002',
        'online'     => false,
        'see_online' => false,
        'see_on_map' => false,
        'modify'     => false,
        'homeuri'    => 'http://example.org:8002/',
        'gatekeeper' => 'http://example.org:8002/',
    ];
}


// --- Groups (memberships) ---
$myGroups = [];

if ($conn && $UID !== '') {
    $GG = osv_table_exists($conn, 'os_groups_groups') ? 'os_groups_groups'
        : (osv_table_exists($conn, 'osgroup') ? 'osgroup' : '');
    $GM = osv_table_exists($conn, 'os_groups_membership') ? 'os_groups_membership'
        : (osv_table_exists($conn, 'osgroupmembership') ? 'osgroupmembership' : '');

    if ($GG && $GM) {
        $ggCols = osv_get_columns($conn, $GG);
        $gmCols = osv_get_columns($conn, $GM);

        $gg_id      = osv_pick_col($ggCols, ['GroupID','groupID','group_id']);
        $gg_name    = osv_pick_col($ggCols, ['Name','name','GroupName','groupName']);
        $gg_charter = osv_pick_col($ggCols, ['Charter','charter']);
        $gg_founder = osv_pick_col($ggCols, ['FounderID','founderID','Founder']);

        $gm_group   = osv_pick_col($gmCols, ['GroupID','groupID','GroupUUID','groupUUID']);
        $gm_member  = osv_pick_col($gmCols, ['PrincipalID','principalID','UserID','userid']);
        $gm_list    = osv_pick_col($gmCols, ['ListInProfile','listInProfile','ListInProfileInProfile']);
        $gm_notice  = osv_pick_col($gmCols, ['AcceptNotices','acceptNotices','ReceiveGroupNotices','receiveGroupNotices']);

        if ($gg_id && $gg_name && $gm_group && $gm_member) {
            $fields = [];
            $fields[] = "g.`{$gg_id}` AS group_id";
            $fields[] = "g.`{$gg_name}` AS name";
            if ($gg_charter) {
                $fields[] = "g.`{$gg_charter}` AS charter";
            }
            if ($gg_founder) {
                $fields[] = "g.`{$gg_founder}` AS founder_id";
            }
            if ($gm_list) {
                $fields[] = "m.`{$gm_list}` AS list_in_profile";
            }
            if ($gm_notice) {
                $fields[] = "m.`{$gm_notice}` AS accept_notices";
            }

            $sql = "SELECT " . implode(', ', $fields) . "
                    FROM `{$GM}` m
                    JOIN `{$GG}` g ON m.`{$gm_group}` = g.`{$gg_id}`
                    WHERE m.`{$gm_member}` = ?
                    ORDER BY g.`{$gg_name}` ASC";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param('s', $UID);
                if ($stmt->execute() && ($res = $stmt->get_result())) {
                    while ($row = $res->fetch_assoc()) {
                        $row['is_owner'] = ($gg_founder && isset($row['founder_id']) && $row['founder_id'] === $UID);
                        $myGroups[] = $row;
                    }
                    $res->close();
                }
                $stmt->close();
            }
        }
    }
}

// --- Avatar profile (in-world text & image) ---
$profileAboutText = '';
$profileImageUUID = '';

if ($conn && $UID !== '') {
    $UP = osv_table_exists($conn, 'userprofile') ? 'userprofile'
        : (osv_table_exists($conn, 'UserProfile') ? 'UserProfile' : '');
    if ($UP) {
        $upCols   = osv_get_columns($conn, $UP);
        $up_id    = osv_pick_col($upCols, ['useruuid','UserUUID','PrincipalID','UUID']);
        $up_about = osv_pick_col($upCols, ['profileAboutText','profileabouttext','AboutText','abouttext']);
        $up_image = osv_pick_col($upCols, ['profileImage','profileimage','Image','image']);

        if ($up_id && ($up_about || $up_image)) {
            $fields = [];
            if ($up_about) {
                $fields[] = "p.`{$up_about}` AS about";
            }
            if ($up_image) {
                $fields[] = "p.`{$up_image}` AS image_uuid";
            }

            if (!empty($fields)) {
                $sql = "SELECT " . implode(', ', $fields) . " FROM `{$UP}` p WHERE p.`{$up_id}` = ? LIMIT 1";
                if ($stmt = $conn->prepare($sql)) {
                    $stmt->bind_param('s', $UID);
                    if ($stmt->execute() && ($res = $stmt->get_result())) {
                        if ($row = $res->fetch_assoc()) {
                            if ($up_about && array_key_exists('about', $row)) {
                                $profileAboutText = (string)($row['about'] ?? '');
                            }
                            if ($up_image && array_key_exists('image_uuid', $row)) {
                                $profileImageUUID = (string)($row['image_uuid'] ?? '');
                            }
                        }
                        $res->close();
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// --- Partner info & requests (mutual consent) ---
$partner = null;               // holds accepted partner
$outgoingPartner = null;       // holds pending outgoing target
$partner_is_reciprocal = false;
$incoming_requests = [];

if ($conn && $UID !== '') {
    $UP = osv_table_exists($conn, 'userprofile') ? 'userprofile'
        : (osv_table_exists($conn, 'UserProfile') ? 'UserProfile' : '');
    if ($UP) {
        $upCols     = osv_get_columns($conn, $UP);
        $up_id      = osv_pick_col($upCols, ['useruuid','UserUUID','PrincipalID','UUID']);
        $up_partner = osv_pick_col($upCols, ['profilePartner','profilepartner','partner','Partner','partneruuid','PartnerUUID']);

        if ($up_id && $up_partner) {
            // My partner field
            $sql = "SELECT p.`{$up_partner}` AS partner FROM `{$UP}` p WHERE p.`{$up_id}` = ? LIMIT 1";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param('s', $UID);
                if ($stmt->execute() && ($res = $stmt->get_result())) {
                    if ($row = $res->fetch_assoc()) {
                        $pid = trim((string)($row['partner'] ?? ''));
                        if ($pid !== '') {
                            $outgoingPartner = ['uuid' => $pid, 'name' => $pid];

                            // resolve outgoing partner name (local users)
                            if ($UA) {
                                $ua_id  = osv_pick_col($uaCols, ['PrincipalID','UserUUID','UUID','useruuid']);
                                $ua_fn  = osv_pick_col($uaCols, ['FirstName','firstname','first']);
                                $ua_ln  = osv_pick_col($uaCols, ['LastName','lastname','last']);
                                if ($ua_id && $ua_fn) {
                                    $sql2 = "SELECT u.`{$ua_fn}` AS fn, u.`{$ua_ln}` AS ln
                                             FROM `{$UA}` u WHERE u.`{$ua_id}` = ? LIMIT 1";
                                    if ($st = $conn->prepare($sql2)) {
                                        $st->bind_param('s', $pid);
                                        if ($st->execute() && ($r2 = $st->get_result())) {
                                            if ($urow = $r2->fetch_assoc()) {
                                                $fn = (string)($urow['fn'] ?? '');
                                                $ln = (string)($urow['ln'] ?? '');
                                                $name = trim($fn . ' ' . $ln);
                                                if ($name !== '') {
                                                    $outgoingPartner['name'] = $name;
                                                }
                                            }
                                        }
                                        $st->close();
                                    }
                                }
                            }

                            // check reciprocity
                            if (osv_is_reciprocal_partner($conn, $UID, $pid)) {
                                $partner_is_reciprocal = true;
                                $partner = $outgoingPartner;
                                $outgoingPartner = null;
                            }
                        }
                    }
                    $res->close();
                }
                $stmt->close();
            }

            // Incoming requests = people who set you as partner, but you haven't reciprocated them
            $sql = "SELECT p.`{$up_id}` AS uuid FROM `{$UP}` p
                    WHERE p.`{$up_partner}` = ? AND p.`{$up_id}` <> ?
                    ORDER BY p.`{$up_id}` ASC LIMIT 50";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param('ss', $UID, $UID);
                if ($stmt->execute() && ($res = $stmt->get_result())) {

                    $ua_id  = $UA ? osv_pick_col($uaCols, ['PrincipalID','UserUUID','UUID','useruuid']) : null;
                    $ua_fn  = $UA ? osv_pick_col($uaCols, ['FirstName','firstname','first']) : null;
                    $ua_ln  = $UA ? osv_pick_col($uaCols, ['LastName','lastname','last']) : null;

                    while ($r = $res->fetch_assoc()) {
                        $rid = trim((string)($r['uuid'] ?? ''));
                        if ($rid === '') continue;

                        // Ignore actual reciprocal partner (already handled above)
                        if ($partner_is_reciprocal && $partner && strcasecmp($partner['uuid'], $rid) === 0) {
                            continue;
                        }

                        $req = ['uuid' => $rid, 'name' => $rid];
                        if ($UA && $ua_id && $ua_fn) {
                            $sql2 = "SELECT u.`{$ua_fn}` AS fn, u.`{$ua_ln}` AS ln
                                     FROM `{$UA}` u WHERE u.`{$ua_id}` = ? LIMIT 1";
                            if ($st = $conn->prepare($sql2)) {
                                $st->bind_param('s', $rid);
                                if ($st->execute() && ($r2 = $st->get_result())) {
                                    if ($urow = $r2->fetch_assoc()) {
                                        $fn = (string)($urow['fn'] ?? '');
                                        $ln = (string)($urow['ln'] ?? '');
                                        $name = trim($fn . ' ' . $ln);
                                        if ($name !== '') $req['name'] = $name;
                                    }
                                }
                                $st->close();
                            }
                        }
                        $incoming_requests[] = $req;
                    }
                    $res->close();
                }
                $stmt->close();
            }
        }
    }
}
// NOTE: Most forms below are wired for updates (email, password, profile, partner).
// Any remaining placeholders will simply reload without changing DB.
?>
