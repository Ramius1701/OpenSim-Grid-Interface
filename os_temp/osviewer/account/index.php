<?php
// My Account / Profile dashboard for OpenSimulator web interface
// Uses global header/footer + site-wide CSS (Bootstrap-based)

// Set page title if your header uses it
$title = "My Profile";

// Shared header (handles config, sessions, HTML <head>, etc.)
require_once __DIR__ . '/../include/header.php';

// Require login. If user is not logged in, show a friendly message and stop.
if (empty($_SESSION['user']['principal_id'])): ?>
    <div class="container my-5">
        <div class="row">
            <div class="col-md-8 col-lg-6 mx-auto">
                <div class="card shadow-sm border-0">
                    <div class="card-body text-center">
                        <h1 class="h4 mb-3">My Profile</h1>
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
    require_once __DIR__ . '/../include/footer.php';
    exit;
endif;

$UID = (string)($_SESSION['user']['principal_id'] ?? '');
$messages = [];
$errors   = [];

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
        $c = @mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
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
        $sql2 = "INSERT INTO `{$table}` (`{$idCol}`) VALUES (?)";
        if ($stmt2 = $conn->prepare($sql2)) {
            $stmt2->bind_param('s', $UID);
            $stmt2->execute();
            $stmt2->close();
        }
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
        $stmt->bind_param($types, ...$values);
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
        $stmt->bind_param($types, ...$values);
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
                        // Empty input = clear partner
                        if (osv_update_partner($connPost, $UID, null, $errors)) {
                            $messages[] = 'Partner cleared.';
                        }
                    } else {
                        $target = osv_find_user_by_name_or_uuid($connPost, $input);
                        if (!$target) {
                            $errors[] = 'Could not find an avatar matching that name or UUID.';
                        } elseif ($target['uuid'] === $UID) {
                            $errors[] = 'You cannot set yourself as partner.';
                        } else {
                            if (osv_update_partner($connPost, $UID, $target['uuid'], $errors)) {
                                $name = $target['name'] !== '' ? $target['name'] : $target['uuid'];
                                $messages[] = 'Partner updated to ' . $name . '.';
                            }
                        }
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

if ($conn && $UID !== '') {
    $FR = osv_table_exists($conn, 'friends') ? 'friends'
        : (osv_table_exists($conn, 'Friends') ? 'Friends' : '');

    if ($FR) {
        $fCols   = osv_get_columns($conn, $FR);
        $f_owner = osv_pick_col($fCols, ['PrincipalID','principalid','UserID','userid']);
        $f_friend= osv_pick_col($fCols, ['Friend','friend','FriendID','friendid']);
        $f_my    = osv_pick_col($fCols, ['MyFlags','myflags','Flags','flags']);
        $f_their = osv_pick_col($fCols, ['TheirFlags','theirflags']);

        if ($f_owner && $f_friend) {
            $sql = "SELECT f.`{$f_friend}` AS friend, " .
                ($f_my ? "f.`{$f_my}` AS myflags" : "0 AS myflags") .
                " FROM `{$FR}` f WHERE f.`{$f_owner}` = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param('s', $UID);
                if ($stmt->execute() && ($res = $stmt->get_result())) {
                    $UUIDs = [];
                    $rows  = [];
                    while ($row = $res->fetch_assoc()) {
                        $rows[]  = $row;
                        $UUIDs[] = $row['friend'];
                    }
                    $stmt->close();

                    // Map friend uuid => name (local users via useraccounts)
                    $names = [];
                    if ($UA && $UUIDs) {
                        $ua_id    = osv_pick_col($uaCols, ['PrincipalID','UUID','UserID']);
                        $ua_first = osv_pick_col($uaCols, ['FirstName','firstname','first_name','First_Name','first']);
                        $ua_last  = osv_pick_col($uaCols, ['LastName','lastname','last_name','Last_Name','last']);
                        if ($ua_id && $ua_first && $ua_last) {
                            $chunks = array_chunk($UUIDs, 100);
                            foreach ($chunks as $chunk) {
                                $ph = implode(',', array_fill(0, count($chunk), '?'));
                                $sql2 = "SELECT u.`{$ua_id}` AS id, u.`{$ua_first}` AS fn, u.`{$ua_last}` AS ln 
                                         FROM `{$UA}` u WHERE u.`{$ua_id}` IN ($ph)";
                                if ($st2 = $conn->prepare($sql2)) {
                                    $types = str_repeat('s', count($chunk));
                                    $st2->bind_param($types, ...$chunk);
                                    if ($st2->execute() && ($r2 = $st2->get_result())) {
                                        while ($r = $r2->fetch_assoc()) {
                                            $names[$r['id']] = trim(($r['fn'] ?? '') . ' ' . ($r['ln'] ?? ''));
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
                                    $st3->bind_param($types, ...$chunk);
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

                    // Partition local vs HG by "has entry in useraccounts"
                    foreach ($rows as $row) {
                        $fid   = (string)$row['friend'];
                        $flags = osv_rights_from_flags($row['myflags'] ?? 0);
                        $isLocal = isset($names[$fid]);
                        $name    = $isLocal ? $names[$fid] : $fid;
                        $entry = [
                            'uuid'       => $fid,
                            'name'       => $name,
                            'grid'       => $isLocal ? 'Local' : 'HG',
                            'see_online' => $flags['see_online'],
                            'see_on_map' => $flags['see_on_map'],
                            'modify'     => $flags['modify'],
                            'online'     => $isLocal ? (bool)($onlineMap[$fid] ?? false) : false,
                        ];
                        if ($isLocal) $friendsLocal[] = $entry;
                        else          $friendsHG[]    = $entry;
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

// --- Partner info ---
$partner = null;
if ($conn && $UID !== '') {
    $UP = osv_table_exists($conn, 'userprofile') ? 'userprofile'
        : (osv_table_exists($conn, 'UserProfile') ? 'UserProfile' : '');

    if ($UP) {
        $upCols    = osv_get_columns($conn, $UP);
        $up_id     = osv_pick_col($upCols, ['useruuid','UserUUID','PrincipalID','UUID']);
        $up_partner= osv_pick_col($upCols, ['partner','Partner','partneruuid','PartnerUUID']);

        if ($up_id && $up_partner) {
            $sql = "SELECT p.`{$up_partner}` AS partner FROM `{$UP}` p 
                    WHERE p.`{$up_id}` = ? LIMIT 1";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param('s', $UID);
                if ($stmt->execute()) {
                    $stmt->bind_result($pid);
                    if ($stmt->fetch() && $pid) {
                        $partner = ['uuid' => (string)$pid, 'name' => (string)$pid];
                        // try resolve partner name
                        if ($UA) {
                            $ua_id    = osv_pick_col($uaCols, ['PrincipalID','UUID','UserID']);
                            $ua_first = osv_pick_col($uaCols, ['FirstName','firstname','first_name','First_Name','first']);
                            $ua_last  = osv_pick_col($uaCols, ['LastName','lastname','last_name','Last_Name','last']);
                            if ($ua_id && $ua_first && $ua_last) {
                                $sql2 = "SELECT u.`{$ua_first}` AS fn, u.`{$ua_last}` AS ln 
                                         FROM `{$UA}` u WHERE u.`{$ua_id}` = ? LIMIT 1";
                                if ($st = $conn->prepare($sql2)) {
                                    $st->bind_param('s', $partner['uuid']);
                                    if ($st->execute()) {
                                        $st->bind_result($fn, $ln);
                                        if ($st->fetch()) {
                                            $partner['name'] = trim(($fn ?? '') . ' ' . ($ln ?? ''));
                                        }
                                    }
                                    $st->close();
                                }
                            }
                        }
                    }
                }
                $stmt->close();
            }
        }
    }
}

// NOTE: Forms below are PRESENT but not wired to write to the DB yet.
// They are safe placeholders: submitting will just reload the page.
// We can hook actual update logic later once table schemas are final.
?>
<div class="container my-4">
    <div class="row">
        <div class="col-12 col-xl-10 mx-auto">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                        <div>
                            <h1 class="h4 mb-0">My Profile</h1>
                            <small class="text-muted">
                                View your avatar details, friends, favorites and regions.
                            </small>
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

                    <!-- Tabs navigation -->
                    <ul class="nav nav-tabs mb-3" id="accountTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tab-inworld-tab"
                                data-bs-toggle="tab" data-bs-target="#tab-inworld" type="button"
                                role="tab" aria-controls="tab-inworld" aria-selected="true">
                                Inworld
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-favorites-tab"
                                data-bs-toggle="tab" data-bs-target="#tab-favorites" type="button"
                                role="tab" aria-controls="tab-favorites" aria-selected="false">
                                Favorites
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-friends-tab"
                                data-bs-toggle="tab" data-bs-target="#tab-friends" type="button"
                                role="tab" aria-controls="tab-friends" aria-selected="false">
                                Friends
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-onlinefriends-tab"
                                data-bs-toggle="tab" data-bs-target="#tab-onlinefriends" type="button"
                                role="tab" aria-controls="tab-onlinefriends" aria-selected="false">
                                Online friends
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-firstlife-tab"
                                data-bs-toggle="tab" data-bs-target="#tab-firstlife" type="button"
                                role="tab" aria-controls="tab-firstlife" aria-selected="false">
                                First life
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-partner-tab"
                                data-bs-toggle="tab" data-bs-target="#tab-partner" type="button"
                                role="tab" aria-controls="tab-partner" aria-selected="false">
                                Partner
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-options-tab"
                                data-bs-toggle="tab" data-bs-target="#tab-options" type="button"
                                role="tab" aria-controls="tab-options" aria-selected="false">
                                Account
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-regions-tab"
                                data-bs-toggle="tab" data-bs-target="#tab-regions" type="button"
                                role="tab" aria-controls="tab-regions" aria-selected="false">
                                My regions
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-donate-tab"
                                data-bs-toggle="tab" data-bs-target="#tab-donate" type="button"
                                role="tab" aria-controls="tab-donate" aria-selected="false">
                                Donate
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="accountTabsContent">
                        <!-- INWORLD -->
                        <div class="tab-pane fade show active" id="tab-inworld" role="tabpanel" aria-labelledby="tab-inworld-tab">
                            <div class="row g-4">
                                <div class="col-sm-6 col-md-6">
                                    <h2 class="h5 mb-3">Avatar name</h2>
                                    <div class="mb-3">
                                        <label class="form-label">First name</label>
                                        <input type="text" class="form-control"
                                               value="<?php echo h($profile['first_name'] ?? (explode(' ', $profile['name'] ?? '')[0] ?? '')); ?>"
                                               readonly>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Last name</label>
                                        <input type="text" class="form-control"
                                               value="<?php
                                                   $parts = preg_split('/\s+/', $profile['name'] ?? '');
                                                   array_shift($parts);
                                                   echo h(implode(' ', $parts));
                                               ?>"
                                               readonly>
                                    </div>
                                </div>

                                <div class="col-sm-6 col-md-6">
                                    <h2 class="h5 mb-3">Password</h2>
                                    <form method="post" action="" class="needs-validation" autocomplete="new-password" novalidate>
                                        <input type="hidden" name="action" value="update_password">
                                        <div class="mb-3">
                                            <label for="password" class="form-label">New password</label>
                                            <input id="password" name="password" type="password"
                                                   class="form-control" minlength="8" maxlength="14" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="confirm_password" class="form-label">Confirm password</label>
                                            <input id="confirm_password" name="confirm_password" type="password"
                                                   class="form-control" minlength="8" maxlength="14" required>
                                        </div>
                                        <button class="btn btn-outline-secondary" type="submit">
                                            Change password
                                        </button>
                                        <div class="form-text">
                                            You can change your password here. You will need this new password next time you log in via the viewer.
                                        </div>
                                    </form>
                                </div>

                                <div class="col-12">
                                    <hr class="my-4">
                                    <h2 class="h5 mb-3">In-world profile text</h2>
                                    <form method="post" action="" class="needs-validation" novalidate>
                                        <input type="hidden" name="action" value="update_profile">
                                        <div class="row g-3">
                                            <div class="col-md-7">
                                                <div class="mb-3">
                                                    <label for="about" class="form-label">About / Bio</label>
                                                    <textarea id="about" name="about" rows="6" class="form-control"
                                                        placeholder="Tell others about your avatar..."></textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="profile_image_uuid" class="form-label">Profile image UUID</label>
                                                    <input id="profile_image_uuid" name="profile_image_uuid" type="text"
                                                           class="form-control"
                                                           placeholder="00000000-0000-0000-0000-000000000000">
                                                    <div class="form-text">
                                                        Texture UUID from your inventory to use as profile picture.
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-5">
                                                <div class="ratio ratio-1x1 mb-2">
                                                    <img src="data:image/svg+xml;utf8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22320%22%20height%3D%22320%22%3E%3Crect%20width%3D%22100%25%22%20height%3D%22100%25%22%20fill%3D%22%23111827%22%2F%3E%3Ctext%20x%3D%2250%25%22%20y%3D%2250%25%22%20fill%3D%22%2394a3b8%22%20dominant-baseline%3D%22middle%22%20text-anchor%3D%22middle%22%20font-family%3D%22sans-serif%22%20font-size%3D%2216%22%3ENo%20profile%20image%3C%2Ftext%3E%3C%2Fsvg%3E"
                                                         class="img-fluid rounded border" alt="Profile picture placeholder">
                                                </div>
                                                <p class="text-muted small mb-0">
                                                    Profile image is normally set inside the viewer. Web editing is optional.
                                                </p>
                                            </div>
                                        </div>
                                        <button class="btn btn-outline-secondary mt-2" type="submit">
                                            Save profile text
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- FAVORITES / PICKS -->
                        <div class="tab-pane fade" id="tab-favorites" role="tabpanel" aria-labelledby="tab-favorites-tab">
                            <h2 class="h5 mb-3">Favorites (Picks)</h2>
                            <?php if (empty($myPicks)): ?>
                                <div class="alert alert-warning">
                                    You do not have any favorites yet.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Name</th>
                                                <th>Description</th>
                                                <th>Parcel UUID</th>
                                                <th>Snapshot UUID</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($myPicks as $pk): ?>
                                                <tr>
                                                    <td><?php echo h($pk['name'] ?? '-'); ?></td>
                                                    <td><?php echo h($pk['description'] ?? ''); ?></td>
                                                    <td><code><?php echo h($pk['parceluuid'] ?? '—'); ?></code></td>
                                                    <td><code><?php echo h($pk['snapshotuuid'] ?? '—'); ?></code></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- FRIENDS -->
                        <div class="tab-pane fade" id="tab-friends" role="tabpanel" aria-labelledby="tab-friends-tab">
                            <h2 class="h5 mb-3">Friends</h2>

                            <div class="mb-4">
                                <h3 class="h6 d-flex align-items-center justify-content-between">
                                    <span>Local grid</span>
                                    <span class="badge bg-primary"><?php echo count($friendsLocal); ?> total</span>
                                </h3>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Name</th>
                                                <th>Grid</th>
                                                <th class="text-center">See online</th>
                                                <th class="text-center">See on map</th>
                                                <th class="text-center">Modify objects</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($friendsLocal)): ?>
                                                <tr>
                                                    <td colspan="5" class="text-muted">No local friends.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($friendsLocal as $fr): ?>
                                                    <tr>
                                                        <td><?php echo h($fr['name']); ?></td>
                                                        <td><?php echo h($fr['grid']); ?></td>
                                                        <td class="text-center">
                                                            <span class="badge bg-<?php echo $fr['see_online'] ? 'success' : 'secondary'; ?>">
                                                                <?php echo $fr['see_online'] ? 'Yes' : 'No'; ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="badge bg-<?php echo $fr['see_on_map'] ? 'success' : 'secondary'; ?>">
                                                                <?php echo $fr['see_on_map'] ? 'Yes' : 'No'; ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="badge bg-<?php echo $fr['modify'] ? 'success' : 'secondary'; ?>">
                                                                <?php echo $fr['modify'] ? 'Yes' : 'No'; ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div>
                                <h3 class="h6 d-flex align-items-center justify-content-between">
                                    <span>Hypergrid friends</span>
                                    <span class="badge bg-info"><?php echo count($friendsHG); ?> total</span>
                                </h3>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Name / UUID</th>
                                                <th>Grid</th>
                                                <th class="text-center">See online</th>
                                                <th class="text-center">See on map</th>
                                                <th class="text-center">Modify objects</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($friendsHG)): ?>
                                                <tr>
                                                    <td colspan="5" class="text-muted">No Hypergrid friends yet.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($friendsHG as $fr): ?>
                                                    <tr>
                                                        <td><?php echo h($fr['name']); ?></td>
                                                        <td>Hypergrid</td>
                                                        <td class="text-center">
                                                            <span class="badge bg-<?php echo $fr['see_online'] ? 'success' : 'secondary'; ?>">
                                                                <?php echo $fr['see_online'] ? 'Yes' : 'No'; ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="badge bg-<?php echo $fr['see_on_map'] ? 'success' : 'secondary'; ?>">
                                                                <?php echo $fr['see_on_map'] ? 'Yes' : 'No'; ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="badge bg-<?php echo $fr['modify'] ? 'success' : 'secondary'; ?>">
                                                                <?php echo $fr['modify'] ? 'Yes' : 'No'; ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- ONLINE FRIENDS -->
                        <div class="tab-pane fade" id="tab-onlinefriends" role="tabpanel" aria-labelledby="tab-onlinefriends-tab">
                            <h2 class="h5 mb-3 d-flex align-items-center justify-content-between">
                                <span>Online friends (local grid)</span>
                                <span class="badge bg-success">
                                    <?php echo count($friendsOnlineLocal); ?> online
                                </span>
                            </h2>
                            <p class="text-muted small">
                                Hypergrid friends are not shown here because their online status is often unreliable across grids.
                            </p>

                            <div class="table-responsive">
                                <table class="table table-sm table-striped table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Status</th>
                                            <th class="text-center">See online</th>
                                            <th class="text-center">See on map</th>
                                            <th class="text-center">Modify objects</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($friendsOnlineLocal)): ?>
                                            <tr>
                                                <td colspan="5" class="text-muted">No friends are currently online.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($friendsOnlineLocal as $fr): ?>
                                                <tr>
                                                    <td><?php echo h($fr['name']); ?></td>
                                                    <td>
                                                        <span class="badge bg-success">Online</span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-<?php echo $fr['see_online'] ? 'success' : 'secondary'; ?>">
                                                            <?php echo $fr['see_online'] ? 'Yes' : 'No'; ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-<?php echo $fr['see_on_map'] ? 'success' : 'secondary'; ?>">
                                                            <?php echo $fr['see_on_map'] ? 'Yes' : 'No'; ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-<?php echo $fr['modify'] ? 'success' : 'secondary'; ?>">
                                                            <?php echo $fr['modify'] ? 'Yes' : 'No'; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- FIRST LIFE -->
                        <div class="tab-pane fade" id="tab-firstlife" role="tabpanel" aria-labelledby="tab-firstlife-tab">
                            <h2 class="h5 mb-3">First life</h2>
                            <form method="post" action="" class="needs-validation" novalidate>
                                <input type="hidden" name="action" value="update_firstlife">
                                <div class="row g-3">
                                    <div class="col-md-7">
                                        <div class="mb-3">
                                            <label for="first_image_uuid" class="form-label">First-life image UUID</label>
                                            <input id="first_image_uuid" name="first_image_uuid" type="text"
                                                   class="form-control"
                                                   placeholder="00000000-0000-0000-0000-000000000000">
                                        </div>
                                        <div class="mb-3">
                                            <label for="first_text" class="form-label">First-life text</label>
                                            <textarea id="first_text" name="first_text" rows="5" class="form-control"
                                                      placeholder="Optional first life description..."></textarea>
                                        </div>
                                        <button class="btn btn-outline-secondary" type="submit">
                                            Save first-life info
                                        </button>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="ratio ratio-1x1 mb-2">
                                            <img src="data:image/svg+xml;utf8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22320%22%20height%3D%22320%22%3E%3Crect%20width%3D%22100%25%22%20height%3D%22100%25%22%20fill%3D%22%23111827%22%2F%3E%3Ctext%20x%3D%2250%25%22%20y%3D%2250%25%22%20fill%3D%22%2394a3b8%22%20dominant-baseline%3D%22middle%22%20text-anchor%3D%22middle%22%20font-family%3D%22sans-serif%22%20font-size%3D%2216%22%3ENo%20first-life%20image%3C%2Ftext%3E%3C%2Fsvg%3E"
                                                 class="img-fluid rounded border" alt="First-life picture placeholder">
                                        </div>
                                        <p class="text-muted small mb-0">
                                            Paste a texture UUID from inventory to show your first-life picture in supporting viewers.
                                        </p>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- PARTNER -->
                        <div class="tab-pane fade" id="tab-partner" role="tabpanel" aria-labelledby="tab-partner-tab">
                            <h2 class="h5 mb-3">Partner</h2>

                            <div class="alert alert-info">
                                <?php if ($partner): ?>
                                    Partnered with: <strong><?php echo h($partner['name']); ?></strong><br>
                                    <small class="text-monospace"><?php echo h($partner['uuid']); ?></small>
                                <?php else: ?>
                                    No partner is currently set on this account.
                                <?php endif; ?>
                            </div>

                            <div class="accordion" id="partnerHelp">
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="partnerHelpHeading">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                                data-bs-target="#partnerHelpBody" aria-expanded="false"
                                                aria-controls="partnerHelpBody">
                                            How does partner work?
                                        </button>
                                    </h2>
                                    <div id="partnerHelpBody" class="accordion-collapse collapse"
                                         aria-labelledby="partnerHelpHeading" data-bs-parent="#partnerHelp">
                                        <div class="accordion-body small">
                                            <ul>
                                                <li>Each account can have only one partner at a time.</li>
                                                <li>Partner changes are usually managed in-world or by grid staff.</li>
                                                <li>This web page shows your current partner as stored in <code>userprofile</code>.</li>
                                                <li>Future versions may allow sending web-based partner requests.</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <form method="post" action="" class="mt-3">
                                <input type="hidden" name="action" value="partner_request">
                                <div class="mb-3">
                                    <label for="partner_input" class="form-label">Set partner (avatar name or UUID)</label>
                                    <input id="partner_input" name="partner_input" type="text" class="form-control"
                                           placeholder="First Last or UUID">
                                </div>
                                <button class="btn btn-outline-secondary" type="submit">
                                    Save partner
                                </button>
                            </form>
                        </div>

                        <!-- OPTIONS / ACCOUNT -->
                        <div class="tab-pane fade" id="tab-options" role="tabpanel" aria-labelledby="tab-options-tab">
                            <h2 class="h5 mb-3">Account</h2>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted small">Avatar created</div>
                                        <div>
                                            <?php echo $profile['created_human']
                                                ? h($profile['created_human'])
                                                : '—'; ?>
                                        </div>
                                        <div class="text-muted small mt-2">Avatar age</div>
                                        <div>
                                            <?php
                                            if (!empty($profile['created_human'])) {
                                                try {
                                                    $dt  = new DateTime($profile['created_human']);
                                                    $now = new DateTime('now');
                                                    $diff = $dt->diff($now);
                                                    echo h($diff->y . ' years, ' . $diff->m . ' months');
                                                } catch (Exception $e) {
                                                    echo '—';
                                                }
                                            } else {
                                                echo '—';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted small">Last connection</div>
                                        <div>
                                            <?php
                                            $ll = $profile['last_login']  ?? null;
                                            $lo = $profile['last_logout'] ?? null;
                                            echo h($lo ?: $ll ?: '—');
                                            ?>
                                        </div>
                                        <div class="text-muted small mt-2">
                                            <?php if ($ll || $lo): ?>
                                                Last login: <?php echo h($ll ?: '—'); ?><br>
                                                Last logout: <?php echo h($lo ?: '—'); ?>
                                            <?php else: ?>
                                                No connection records yet.
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <form method="post" action="" class="mb-3 needs-validation" novalidate>
                                <input type="hidden" name="action" value="update_email">
                                <div class="mb-2">
                                    <label for="email" class="form-label">Email</label>
                                    <input id="email" name="email" type="email"
                                           class="form-control"
                                           value="<?php echo h($profile['email'] ?? ''); ?>" required>
                                </div>
                                <div class="form-text mb-2">
                                    Enter a valid email address for password recovery and notices.
                                </div>
                                <button class="btn btn-outline-secondary" type="submit">
                                    Update email
                                </button>
                            </form>

                            <p class="text-muted small mb-0">
                                <strong>Current account level:</strong>
                                <?php echo h((string)($profile['user_level'] ?? '0')); ?> (Standard)
                            </p>
                        </div>

                        <!-- REGIONS -->
                        <div class="tab-pane fade" id="tab-regions" role="tabpanel" aria-labelledby="tab-regions-tab">
                            <h2 class="h5 mb-3">My regions</h2>
                            <?php if (!empty($myRegions)): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Name</th>
                                                <th>Coords</th>
                                                <th>Size</th>
                                                <th>Estate</th>
                                                <th>UUID</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($myRegions as $r): ?>
                                                <tr>
                                                    <td><?php echo h($r['name'] ?? '-'); ?></td>
                                                    <td>
                                                        <?php
                                                        $cx = isset($r['x']) ? (int)$r['x'] : null;
                                                        $cy = isset($r['y']) ? (int)$r['y'] : null;
                                                        echo ($cx !== null && $cy !== null) ? h($cx . ',' . $cy) : '—';
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $sx = isset($r['sx']) ? (int)$r['sx'] : null;
                                                        $sy = isset($r['sy']) ? (int)$r['sy'] : null;
                                                        echo ($sx && $sy) ? h($sx . '×' . $sy) : '256×256';
                                                        ?>
                                                    </td>
                                                    <td><?php echo h($r['estate'] ?? '—'); ?></td>
                                                    <td><code><?php echo h($r['uuid'] ?? '-'); ?></code></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    This account does not own any regions.
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- DONATE -->
                        <div class="tab-pane fade" id="tab-donate" role="tabpanel" aria-labelledby="tab-donate-tab">
                            <h2 class="h5 mb-3">Support the grid</h2>
                            <p class="text-muted">
                                Donations help keep servers online and the grid running smoothly.
                                The buttons below are placeholders – replace them with your own
                                PayPal or donation links when ready.
                            </p>

                            <h3 class="h6 mt-4">One-time donation</h3>
                            <div class="d-flex flex-wrap gap-2 my-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" disabled>$10</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" disabled>$20</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" disabled>$50</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" disabled>Custom</button>
                            </div>

                            <h3 class="h6 mt-4">Monthly support</h3>
                            <div class="row g-2">
                                <div class="col-md-3 col-6">
                                    <button type="button" class="btn btn-outline-secondary w-100 btn-sm" disabled>
                                        $10 / month
                                    </button>
                                </div>
                                <div class="col-md-3 col-6">
                                    <button type="button" class="btn btn-outline-secondary w-100 btn-sm" disabled>
                                        $20 / month
                                    </button>
                                </div>
                                <div class="col-md-3 col-6">
                                    <button type="button" class="btn btn-outline-secondary w-100 btn-sm" disabled>
                                        $50 / month
                                    </button>
                                </div>
                                <div class="col-md-3 col-6">
                                    <button type="button" class="btn btn-outline-secondary w-100 btn-sm" disabled>
                                        $100 / month
                                    </button>
                                </div>
                            </div>

                            <p class="text-muted small mt-3 mb-0">
                                When you're ready, wire these buttons to your real donation provider.
                            </p>
                        </div>
                    </div> <!-- /.tab-content -->
                </div> <!-- /.card-body -->
            </div> <!-- /.card -->
        </div>
    </div>
</div>

<script>
// Fix header navigation links when this page is served from /account/index.php
document.addEventListener('DOMContentLoaded', function () {
    var base = '../';
    document.querySelectorAll('a[href]').forEach(function (a) {
        var href = a.getAttribute('href');
        if (!href) return;
        // Ignore absolute URLs, anchors, and javascript: links
        if (/^(?:[a-z]+:|\/|#)/i.test(href)) return;
        a.setAttribute('href', base + href);
    });
});
</script>

<?php
// Shared footer closes HTML/body and scripts
require_once __DIR__ . '/../include/footer.php';
?>
