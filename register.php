<?php
// Ensure session is available for the multi-step registration flow
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$title = "Register Avatar";
include_once 'include/header.php';

// SHARED DB CONNECTION
$conn = db();


if (!$conn || !($conn instanceof mysqli)) {
    die('Database connection failed.');
}
$conn->set_charset('utf8mb4');
// Helper: Generate UUID
function gen_uuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Helper: Generate Short Recovery Code
function gen_recovery_code() {
    return strtoupper(bin2hex(random_bytes(4))); // Generates 8-char code (e.g., A1B2C3D4)
}

// Helper: Check whether recovery table exists (NO auto-create; keep schema untouched)
function ensure_recovery_table($conn) {
    try {
        if (!($conn instanceof mysqli)) return false;
        $rs = $conn->query("SHOW TABLES LIKE 'ws_recovery_codes'");
        $ok = ($rs && $rs->num_rows > 0);
        if ($rs) $rs->close();
        return $ok;
    } catch (Throwable $e) {
        return false;
    }
}


// --- Helper: Ensure GridUser row exists (needed for some grids on first login) ---
function ensure_griduser_row(mysqli $conn, string $uuid): bool {
    $stmt = $conn->prepare("SELECT UserID FROM GridUser WHERE UserID=? LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param("s", $uuid);
    $stmt->execute();
    $stmt->store_result();
    $exists = ($stmt->num_rows > 0);
    $stmt->close();
    if ($exists) return true;

    // Insert minimal row; remaining fields have defaults per schema
    $zero = '00000000-0000-0000-0000-000000000000';
    $pos  = '<0,0,0>';
    $stmt2 = $conn->prepare("INSERT INTO GridUser (UserID, HomeRegionID, HomePosition, LastRegionID, LastPosition) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt2) return false;
    $stmt2->bind_param("sssss", $uuid, $zero, $pos, $zero, $pos);
    $ok = $stmt2->execute();
    $stmt2->close();
    return (bool)$ok;
}

// --- Helper: Create default inventory folder skeleton (prevents 'Inventory service is not responding' for DB-created users) ---
function ensure_inventory_skeleton(mysqli $conn, string $uuid, string &$err): bool {
    $err = '';
    $zero = '00000000-0000-0000-0000-000000000000';

    // 1) Find or create root 'My Inventory'
    $root = null;
    $stmt = $conn->prepare("SELECT folderID FROM inventoryfolders WHERE agentID=? AND parentFolderID=? AND type=8 LIMIT 1");
    if (!$stmt) { $err = "DB error preparing inventory root query."; return false; }
    $stmt->bind_param("ss", $uuid, $zero);
    $stmt->execute();
    $stmt->bind_result($folderID);
    if ($stmt->fetch()) {
        $root = $folderID;
    }
    $stmt->close();

    if (!$root) {
        $root = gen_uuid();
        $version = 17; // historical default used by this codebase
        $type = 8;
        $name = 'My Inventory';
        $stmtIns = $conn->prepare("INSERT INTO inventoryfolders (folderName, type, version, folderID, agentID, parentFolderID) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmtIns) { $err = "DB error preparing inventory root insert."; return false; }
        $stmtIns->bind_param("siisss", $name, $type, $version, $root, $uuid, $zero);
        if (!$stmtIns->execute()) {
            $err = "DB error creating inventory root.";
            $stmtIns->close();
            return false;
        }
        $stmtIns->close();
    }

    // 2) Standard system folders under root
    $folders = [
        ['Textures',        0,  1],
        ['Sounds',          1,  1],
        ['Calling Cards',   2,  2],
        ['Landmarks',       3,  1],
        ['Clothing',        5,  3],
        ['Objects',         6,  1],
        ['Notecards',       7,  1],
        ['Scripts',         10, 1],
        ['Body Parts',      13, 5],
        ['Trash',           14, 1],
        ['Photo Album',     15, 1],
        ['Lost And Found',  16, 1],
        ['Animations',      20, 1],
        ['Gestures',        21, 1],
        ['Favorites',       23, 1],
        ['Current Outfit',  46, 1],
        ['My Outfits',      48, 1],
    ];

    $stmtChk = $conn->prepare("SELECT folderID FROM inventoryfolders WHERE agentID=? AND parentFolderID=? AND folderName=? LIMIT 1");
    $stmtAdd = $conn->prepare("INSERT INTO inventoryfolders (folderName, type, version, folderID, agentID, parentFolderID) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmtChk || !$stmtAdd) { $err = "DB error preparing inventory folder statements."; return false; }

    foreach ($folders as $f) {
        [$name, $type, $version] = $f;

        // Skip if folder already exists (by name under root)
        $stmtChk->bind_param("sss", $uuid, $root, $name);
        $stmtChk->execute();
        $stmtChk->store_result();
        if ($stmtChk->num_rows > 0) {
            continue;
        }

        $fid = gen_uuid();
        $stmtAdd->bind_param("siisss", $name, $type, $version, $fid, $uuid, $root);
        if (!$stmtAdd->execute()) {
            $err = "DB error creating inventory folder: " . $name;
            $stmtChk->close();
            $stmtAdd->close();
            return false;
        }
    }

    $stmtChk->close();
    $stmtAdd->close();
    return true;
}

// Helper: Money Server Sync (Email Only - Best Effort, No Money)
// - Some money modules lock the in-viewer "Buy" button until an Email exists.
// - This is best-effort: failures are logged, but do not break registration.
function sync_money_server($uuid, $email, $conn = null) {

    // 0) Basic sanity
    if (empty($uuid) || empty($email)) {
        return false;
    }

    // 1) Try updating UserAccounts.Email in the *current* OpenSim DB (usually already set by registration)
    try {
        if ($conn instanceof mysqli) {
            $stmt = $conn->prepare("UPDATE UserAccounts SET Email=? WHERE PrincipalID=?");
            if ($stmt) {
                $stmt->bind_param("ss", $email, $uuid);
                $stmt->execute();
                $stmt->close();
            }
        }
    } catch (Throwable $e) {
        error_log("Money Server Sync (UserAccounts) Error: " . $e->getMessage());
    }

    // 2) Try updating UserInfo.Email in the *same* DB (if the table exists)
    try {
        if ($conn instanceof mysqli) {
            $has = $conn->query("SHOW TABLES LIKE 'UserInfo'");
            if ($has && $has->num_rows > 0) {
                $stmt2 = $conn->prepare("UPDATE UserInfo SET Email=? WHERE PrincipalID=?");
                if ($stmt2) {
                    $stmt2->bind_param("ss", $email, $uuid);
                    $stmt2->execute();
                    $stmt2->close();
                    return true;
                }
            }
        }
    } catch (Throwable $e) {
        error_log("Money Server Sync (UserInfo same DB) Error: " . $e->getMessage());
    }

    // 3) Optional: update UserInfo in a *separate* Money DB if configured
    // Define these in your config if your money tables live in another database/port:
    //   MONEY_DB_HOST, MONEY_DB_PORT, MONEY_DB_USER, MONEY_DB_PASS, MONEY_DB_NAME
    try {
        if (!defined('MONEY_DB_NAME') || empty(constant('MONEY_DB_NAME'))) {
            return false;
        }

        $host = defined('MONEY_DB_HOST') ? constant('MONEY_DB_HOST') : 'localhost';
        $port = defined('MONEY_DB_PORT') ? (int)constant('MONEY_DB_PORT') : 3306;
        $user = defined('MONEY_DB_USER') ? constant('MONEY_DB_USER') : 'root';
        $pass = defined('MONEY_DB_PASS') ? constant('MONEY_DB_PASS') : '';
        $name = constant('MONEY_DB_NAME');

        $m = @new mysqli($host, $user, $pass, $name, $port);
        if ($m->connect_errno) {
            error_log("Money Server Sync (separate DB) connect error: " . $m->connect_error);
            return false;
        }
        $m->set_charset('utf8mb4');

        $has = $m->query("SHOW TABLES LIKE 'UserInfo'");
        if ($has && $has->num_rows > 0) {
            $stmt3 = $m->prepare("UPDATE UserInfo SET Email=? WHERE PrincipalID=?");
            if ($stmt3) {
                $stmt3->bind_param("ss", $email, $uuid);
                $stmt3->execute();
                $stmt3->close();
                $m->close();
                return true;
            }
        }

        $m->close();
        return false;

    } catch (Throwable $e) {
        error_log("Money Server Sync (separate DB) Error: " . $e->getMessage());
        return false;
    }
}


// Helper: Create user via ROBUST /accounts createuser (preferred, uses OpenSim's own creation pipeline)
// - Requires ROBUST [UserAccountService] AllowCreateUser = true
// - Endpoint: POST http://<robust-host>:<private-port>/accounts with METHOD=createuser
function osv_robust_createuser(string $first, string $last, string $pass, string $email, string $uuid, string $scope, string &$err): bool {
    $err = '';

    // Default to local Robust private port (standard default setup). Override via ROBUST_ACCOUNTS_URL if defined.
    $url = defined('ROBUST_ACCOUNTS_URL') ? constant('ROBUST_ACCOUNTS_URL') : 'http://127.0.0.1:8003/accounts';

    // Build POST body as application/x-www-form-urlencoded
    $post = http_build_query([
        'METHOD'      => 'createuser',
        'FirstName'   => $first,
        'LastName'    => $last,
        'Password'    => $pass,
        'Email'       => $email,
        'PrincipalID' => $uuid,
        'ScopeID'     => $scope,
    ]);

    $headers = [
        'Content-Type: application/x-www-form-urlencoded',
        'Content-Length: ' . strlen($post),
    ];

    // Optional Basic Auth (Robust supports this for private services)
    $basicUser = defined('ROBUST_HTTP_USER') ? constant('ROBUST_HTTP_USER') : '';
    $basicPass = defined('ROBUST_HTTP_PASS') ? constant('ROBUST_HTTP_PASS') : '';

    // Use cURL if available (more reliable than file_get_contents)
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        if ($basicUser !== '') {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $basicUser . ':' . $basicPass);
        }

        $resp = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false || $resp === '' || $httpCode >= 400) {
            $err = $curlErr ? ("ROBUST createuser HTTP error: " . $curlErr) : ("ROBUST createuser HTTP status: " . $httpCode);
            return false;
        }

        // Expect XML: <ServerResponse><result>Success/Failure or structured result</result></ServerResponse>
        $xml = @simplexml_load_string($resp);
        if ($xml === false || !isset($xml->result)) {
            $err = 'ROBUST createuser returned unexpected response.';
            return false;
        }

        // Failure is an explicit string "Failure"
        if (trim((string)$xml->result) === 'Failure') {
            $err = 'ROBUST createuser returned Failure (AllowCreateUser may be disabled).';
            return false;
        }

        return true;
    }

    // Fallback: file_get_contents
    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => implode("\r\n", array_merge($headers, $basicUser !== '' ? ['Authorization: Basic ' . base64_encode($basicUser . ':' . $basicPass)] : [])),
            'content' => $post,
            'timeout' => 5,
        ]
    ];
    $context = stream_context_create($opts);
    $resp = @file_get_contents($url, false, $context);
    if ($resp === false || $resp === '') {
        $err = 'ROBUST createuser request failed.';
        return false;
    }

    $xml = @simplexml_load_string($resp);
    if ($xml === false || !isset($xml->result)) {
        $err = 'ROBUST createuser returned unexpected response.';
        return false;
    }
    if (trim((string)$xml->result) === 'Failure') {
        $err = 'ROBUST createuser returned Failure (AllowCreateUser may be disabled).';
        return false;
    }
    return true;
}

$step = isset($_SESSION['reg_step']) ? $_SESSION['reg_step'] : 1;
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- STEP 1: VALIDATE INFO ---
    if (isset($_POST['step1'])) {
        $fname = trim($_POST['firstName']);
        $lname = trim($_POST['lastName']);
        $email = trim($_POST['email']);
        $pass  = $_POST['password'];
        $pass2 = $_POST['password_confirm'];

        if ($pass !== $pass2) $error = "Passwords do not match.";
        elseif (strlen($pass) < 6) $error = "Password must be at least 6 characters.";
        else {
            
// Basic validation
if ($fname === '' || $lname === '' || $email === '') {
    $error = "All fields are required.";
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = "Please enter a valid email address.";
} elseif (preg_match('/\s/', $fname) || preg_match('/\s/', $lname)) {
    $error = "First/Last name cannot contain spaces.";
} else {

    // Check Duplicate Name
    $chk = $conn->prepare("SELECT PrincipalID FROM UserAccounts WHERE FirstName=? AND LastName=? LIMIT 1");
    if (!$chk) {
        $error = "Database Error: " . $conn->error;
    } else {
        $chk->bind_param("ss", $fname, $lname);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            $error = "Avatar Name already taken.";
        }
        $chk->close();
    }

    // Check Duplicate Email (optional but recommended)
    if (!$error) {
        $chkE = $conn->prepare("SELECT PrincipalID FROM UserAccounts WHERE Email=? AND Email<>'' LIMIT 1");
        if ($chkE) {
            $chkE->bind_param("s", $email);
            $chkE->execute();
            $chkE->store_result();
            if ($chkE->num_rows > 0) {
                $error = "Email already in use.";
            }
            $chkE->close();
        }
    }

    // Generate 5 Recovery Codes
                if (!$error) {
                    $codes = [];
                for($i=0; $i<5; $i++) { $codes[] = gen_recovery_code(); }
                
                // Save to Session
                $_SESSION['reg_data'] = ['f'=>$fname, 'l'=>$lname, 'e'=>$email, 'p'=>$pass];
                $_SESSION['reg_codes'] = $codes;
                $_SESSION['reg_step'] = 2;
                $step = 2;
                }
            }
        }
    }

    // --- STEP 2: USER CONFIRMS CODES ---
    elseif (isset($_POST['step2'])) {
        if (isset($_POST['saved_codes'])) {
            $step = 3;
            $_SESSION['reg_step'] = 3;
        } else {
            $error = "You must confirm that you saved your codes.";
        }
    }

    // --- STEP 3: CREATE ACCOUNT ---
    elseif (isset($_POST['step3'])) {
        $hasRecovery = ensure_recovery_table($conn);
        $d = $_SESSION['reg_data'] ?? null;
        $codes = $_SESSION['reg_codes'] ?? null;
        
        if (empty($d) || empty($codes) || !is_array($codes)) {
            $error = "Registration session expired. Please start over.";
        } else {
        
        $uuid = gen_uuid();
        $scope = '00000000-0000-0000-0000-000000000000';
        $created = time();
        

// Registration create mode:
//   'db'    = pure DB inserts (no ROBUST call)
//   'robust' = ROBUST /accounts createuser only
//   'auto'  = try ROBUST first, then fall back to DB inserts
$mode = defined('REGISTRATION_CREATE_MODE') ? strtolower(trim((string)constant('REGISTRATION_CREATE_MODE'))) : 'db';
$useRobust = ($mode === 'robust' || $mode === 'auto');

// Optional: ask ROBUST to create the user (preferred when enabled, because it provisions inventory/default avatar)
$robustErr = '';
$robustOk  = false;
if ($useRobust) {
    $robustOk = osv_robust_createuser($d['f'], $d['l'], $d['p'], $d['e'], $uuid, $scope, $robustErr);
}

if ($robustOk) {
    // Store recovery codes only if ws_recovery_codes exists (best-effort)
    if ($hasRecovery) {
        try {
            $sql3 = "INSERT INTO ws_recovery_codes (PrincipalID, code_hash) VALUES (?, ?)";
            if ($stmt3 = $conn->prepare($sql3)) {
                foreach ($codes as $c) {
                    $c_hash = password_hash($c, PASSWORD_DEFAULT);
                    $stmt3->bind_param("ss", $uuid, $c_hash);
                    $stmt3->execute();
                }
                $stmt3->close();
            }
        } catch (Throwable $e) {
            error_log("Recovery code save failed: " . $e->getMessage());
        }
    }

    // Email sync (best-effort)
    sync_money_server($uuid, $d['e'], $conn);

    $step = 4;
    unset($_SESSION['reg_data'], $_SESSION['reg_codes'], $_SESSION['reg_step']);

} else {

    // If admin forced ROBUST-only mode, do NOT DB-create partial users.
    if ($mode === 'robust') {
        $error = "Registration is temporarily unavailable (account service not responding). Please contact an administrator.";

    } else {
        // DB mode (or AUTO fallback): create user/auth + seed GridUser + seed inventory skeleton

        // 1. Create User
        $serviceUrls = 'HomeURI= InventoryServerURI= AssetServerURI=';
        $sql1 = "INSERT INTO UserAccounts (PrincipalID, ScopeID, FirstName, LastName, Email, ServiceURLs, Created, UserLevel, UserFlags, UserTitle, active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, '', 1)";
        $stmt1 = $conn->prepare($sql1);
        $stmt1->bind_param("ssssssi", $uuid, $scope, $d['f'], $d['l'], $d['e'], $serviceUrls, $created);

        // 2. Create Auth (Password)
        $salt = md5(uniqid(mt_rand(), true));
        $hash = md5(md5($d['p']) . ":" . $salt);

        $sql2 = "INSERT INTO auth (UUID, passwordHash, passwordSalt, accountType, webLoginKey)
                 VALUES (?, ?, ?, 'UserAccount', '00000000-0000-0000-0000-000000000000')";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("sss", $uuid, $hash, $salt);

        // Use a transaction so we don't create partial accounts
        $conn->begin_transaction();
        try {
            if (!$stmt1->execute()) {
                throw new Exception("Database Error creating user account.");
            }
            if (!$stmt2->execute()) {
                throw new Exception("Database Error creating authentication record.");
            }

            if (!ensure_griduser_row($conn, $uuid)) {
                throw new Exception("Database Error creating GridUser record.");
            }

            $invErr = '';
            if (!ensure_inventory_skeleton($conn, $uuid, $invErr)) {
                throw new Exception($invErr ?: "Database Error creating inventory folders.");
            }

            if (!$conn->commit()) {
                throw new Exception("Database Error committing transaction.");
            }

            // Store recovery codes only if ws_recovery_codes exists (best-effort)
            if ($hasRecovery) {
                try {
                    $sql3 = "INSERT INTO ws_recovery_codes (PrincipalID, code_hash) VALUES (?, ?)";
                    if ($stmt3 = $conn->prepare($sql3)) {
                        foreach ($codes as $c) {
                            $c_hash = password_hash($c, PASSWORD_DEFAULT);
                            $stmt3->bind_param("ss", $uuid, $c_hash);
                            $stmt3->execute();
                        }
                        $stmt3->close();
                    }
                } catch (Throwable $e) {
                    error_log("Recovery code save failed: " . $e->getMessage());
                }
            }

            // Email sync (best-effort)
            sync_money_server($uuid, $d['e'], $conn);

            $step = 4;
            unset($_SESSION['reg_data'], $_SESSION['reg_codes'], $_SESSION['reg_step']);

        } catch (Throwable $e) {
            $conn->rollback();
            $error = "Database Error: " . ($conn->error ?: $e->getMessage());
        }

    } // end db/auto

} // end robust failed


        } // end session ok
    }
}
?>

<div class="container mt-5 mb-5" style="max-width: 650px;">
    
    <div class="text-center mb-4">
        <h1><i class="bi bi-person-plus-fill"></i> Join the Grid</h1>
    </div>

    <?php if($error) echo "<div class='alert alert-danger'>$error</div>"; ?>

    <?php if ($step == 1): ?>
    <div class="card shadow">
        <div class="card-header bg-primary text-white">Step 1: Account Details</div>
        <div class="card-body">
            <form method="POST">
                <div class="row mb-3">
                    <div class="col">
                        <label class="fw-bold">First Name</label>
                        <input type="text" name="firstName" class="form-control" required>
                    </div>
                    <div class="col">
                        <label class="fw-bold">Last Name</label>
                        <input type="text" name="lastName" class="form-control" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="fw-bold">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="fw-bold">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="fw-bold">Confirm Password</label>
                    <input type="password" name="password_confirm" class="form-control" required>
                </div>
                <button type="submit" name="step1" class="btn btn-primary w-100">Next</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($step == 2): ?>
    <div class="card shadow border-danger">
        <div class="card-header bg-danger text-white">Step 2: Save Your Recovery Codes</div>
        <div class="card-body text-center">
            <p class="text-muted">
                If you forget your password, these codes are the <strong>ONLY</strong> way to reset it yourself.<br>
                Please copy them to a safe place now.
            </p>
            
            <div class="bg-light p-3 border rounded mb-3">
                <code style="font-size: 1.4rem; letter-spacing: 2px; line-height: 2;">
                    <?php 
                    foreach ($_SESSION['reg_codes'] as $code) {
                        echo $code . "<br>";
                    } 
                    ?>
                </code>
            </div>

            <form method="POST">
                <div class="form-check text-start mb-3 d-flex justify-content-center">
                    <div>
                        <input class="form-check-input" type="checkbox" name="saved_codes" id="saveCheck" required>
                        <label class="form-check-label fw-bold" for="saveCheck">
                            I have saved these codes safely.
                        </label>
                    </div>
                </div>
                <button type="submit" name="step2" class="btn btn-danger w-100">Continue</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($step == 3): ?>
    <div class="card shadow border-success">
        <div class="card-header bg-success text-white">Step 3: Finalize</div>
        <div class="card-body">
            <h5>Ready to create avatar?</h5>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($_SESSION['reg_data']['f'] . " " . $_SESSION['reg_data']['l']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['reg_data']['e']); ?></p>
            
            <form method="POST">
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" required>
                    <label class="form-check-label">I agree to the Terms of Service.</label>
                </div>
                <button type="submit" name="step3" class="btn btn-success w-100">Create Account</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($step == 4): ?>
    <div class="text-center mt-5">
        <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
        <h2 class="mt-3">Welcome to the Grid!</h2>
        <p class="lead">Your avatar has been created.</p>
        <a href="index.php" class="btn btn-primary btn-lg">Return Home</a>
    </div>
    <?php endif; ?>

</div>

<?php include_once "include/" . FOOTER_FILE; ?>