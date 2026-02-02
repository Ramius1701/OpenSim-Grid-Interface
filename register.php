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

// Helper: Ensure Recovery Table Exists
function ensure_recovery_table($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS ws_recovery_codes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        PrincipalID CHAR(36) NOT NULL,
        code_hash VARCHAR(255) NOT NULL,
        is_used TINYINT(1) DEFAULT 0,
        INDEX (PrincipalID)
    )";
    $conn->query($sql);
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
        ensure_recovery_table($conn);
        $d = $_SESSION['reg_data'] ?? null;
        $codes = $_SESSION['reg_codes'] ?? null;
        
        if (empty($d) || empty($codes) || !is_array($codes)) {
            $error = "Registration session expired. Please start over.";
        } else {
        
        $uuid = gen_uuid();
        $scope = '00000000-0000-0000-0000-000000000000';
        $created = time();
        
        // 1. Create User
        $sql1 = "INSERT INTO UserAccounts (PrincipalID, ScopeID, FirstName, LastName, Email, Created) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt1 = $conn->prepare($sql1);
        $stmt1->bind_param("sssssi", $uuid, $scope, $d['f'], $d['l'], $d['e'], $created);
        
        // 2. Create Auth (Password)
        $salt = md5(uniqid(mt_rand(), true));
        $hash = md5(md5($d['p']) . ":" . $salt);
        
        $sql2 = "INSERT INTO auth (UUID, passwordHash, passwordSalt, accountType, webLoginKey) VALUES (?, ?, ?, 'UserAccount', '00000000-0000-0000-0000-000000000000')";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("sss", $uuid, $hash, $salt);
        
        // 3. Save Recovery Codes (Hashed for security)
        $sql3 = "INSERT INTO ws_recovery_codes (PrincipalID, code_hash) VALUES (?, ?)";
        $stmt3 = $conn->prepare($sql3);
        
        // Use a transaction so we don't create partial accounts (user/auth/codes must all succeed)
        $conn->begin_transaction();
        try {
            if (!$stmt1->execute()) {
                throw new Exception("Database Error creating user account.");
            }
            if (!$stmt2->execute()) {
                throw new Exception("Database Error creating authentication record.");
            }

            foreach ($codes as $c) {
                $c_hash = password_hash($c, PASSWORD_DEFAULT); // Secure hash
                $stmt3->bind_param("ss", $uuid, $c_hash);
                if (!$stmt3->execute()) {
                    throw new Exception("Database Error saving recovery codes.");
                }
            }

            if (!$conn->commit()) {
                throw new Exception("Database Error committing transaction.");
            }

            // --- SUCCESS! SYNC EMAIL ONLY (Wait for login to give money) ---
            sync_money_server($uuid, $d['e'], $conn);
            // ---------------------------------------------------------------

            $step = 4;
            // Clean Session
            unset($_SESSION['reg_data']);
            unset($_SESSION['reg_codes']);
            unset($_SESSION['reg_step']);
        } catch (Throwable $e) {
            $conn->rollback();
            $error = "Database Error: " . ($conn->error ?: $e->getMessage());
        }

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