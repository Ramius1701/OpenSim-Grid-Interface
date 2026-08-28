<?php
$title = "Password Reset";
require_once 'include/config.php';
include_once 'include/' . HEADER_FILE;
require_once __DIR__ . '/include/security.php';

// SHARED DB CONNECTION (UserAccounts/auth - core OpenSim data)
$conn = db();
// This site's own recovery-codes table (ws_recovery_codes) lives in its own
// SQLite database instead - see include/ws_db.php.
$wsdb = ws_db();


$msg = "";
$msgType = ""; // success or danger

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fname = trim($_POST['firstName'] ?? '');
    $lname = trim($_POST['lastName'] ?? '');
    $code  = strtoupper(preg_replace('/[^A-Z0-9]/', '', trim($_POST['recoveryCode'] ?? '')));
    $pass  = $_POST['newPassword'] ?? '';
    $pass2 = $_POST['confirmPassword'] ?? '';

    // Generic message for every "this didn't work" outcome below the CSRF/
    // rate-limit checks - using distinct messages for "no such avatar" vs.
    // "wrong recovery code" lets an attacker enumerate which avatar names
    // exist on the grid just by watching which message comes back.
    $invalidMsg = "Invalid name or recovery code. Please check your spelling and try again.";

    if (!verify_csrf_token()) {
        $msg = "Your session has expired or the form was submitted incorrectly. Please try again.";
        $msgType = "danger";
    } else {
        // Rate limit by IP (broad anti-automation) and by the target avatar
        // name (so an attacker can't dodge the account-level limit by
        // spreading guesses across many IPs). Recovery codes are only 32
        // bits of entropy, so this matters more here than almost anywhere
        // else in the app.
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ipRateOk    = OSWebSecurity::checkRateLimit('reset_ip:' . $ip, 15, 900);
        $accountKey  = 'reset_user:' . strtolower($fname . ' ' . $lname);
        $acctRateOk  = OSWebSecurity::checkRateLimit($accountKey, 8, 900);

        // 1. Basic Validation
        if (!$ipRateOk || !$acctRateOk) {
            $msg = "Too many attempts. Please wait a few minutes and try again.";
            $msgType = "danger";
        } elseif ($pass !== $pass2) {
            $msg = "New passwords do not match.";
            $msgType = "danger";
        } elseif (strlen($pass) < 6) {
            $msg = "Password must be at least 6 characters.";
            $msgType = "danger";
        } else {
            // 2. Find User UUID
            $sql = "SELECT PrincipalID FROM UserAccounts WHERE FirstName = ? AND LastName = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $fname, $lname);
            $stmt->execute();
            $res = $stmt->get_result();

            if (($row = $res->fetch_assoc()) && $wsdb) {
                $uuid = $row['PrincipalID'];
                ws_ensure_recovery_table($wsdb);

                // 3. Fetch all UNUSED recovery codes for this user
                // We verify them one by one using password_verify()
                $codeFound = false;
                $codeIDToInvalidate = 0;

                $stmtCodes = $wsdb->prepare("SELECT id, code_hash FROM ws_recovery_codes WHERE PrincipalID = ? AND is_used = 0");
                $stmtCodes->execute([$uuid]);

                foreach ($stmtCodes->fetchAll() as $cRow) {
                    // Check if the input code matches this hash
                    if (password_verify($code, $cRow['code_hash'])) {
                        $codeFound = true;
                        $codeIDToInvalidate = $cRow['id'];
                        break; // Stop looking, we found a match
                    }
                }

                if ($codeFound) {
                    // 4. Code is valid! Perform the password reset.
                    //
                    // auth (MySQL) and ws_recovery_codes (SQLite) are two
                    // separate database engines now, so this can no longer
                    // be one atomic transaction. Update the password first -
                    // that's the security-critical half and must succeed on
                    // its own. If burning the code afterward fails, the
                    // password reset has still succeeded (the important
                    // outcome); the worst case is that one code stays
                    // "unused" and could theoretically be reused later - an
                    // accepted, low-severity tradeoff rather than building
                    // real two-phase commit for a single-operator app.
                    $newSalt = md5(uniqid(mt_rand(), true));
                    $newHash = md5(md5($pass) . ":" . $newSalt);

                    $upSql = "UPDATE auth SET passwordHash = ?, passwordSalt = ? WHERE UUID = ?";
                    $upStmt = $conn->prepare($upSql);
                    $upStmt->bind_param("sss", $newHash, $newSalt, $uuid);

                    if (!$upStmt->execute()) {
                        $msg = "Database error updating password.";
                        $msgType = "danger";
                    } else {
                        $msg = "Success! Your password has been reset. You can log in now.";
                        $msgType = "success";

                        try {
                            $burnStmt = $wsdb->prepare("UPDATE ws_recovery_codes SET is_used = 1 WHERE id = ? AND is_used = 0");
                            $burnStmt->execute([$codeIDToInvalidate]);
                        } catch (Throwable $e) {
                            error_log("Recovery code burn failed after successful password reset: " . $e->getMessage());
                            // Deliberately not surfaced to the user - their password change succeeded.
                        }
                    }
                } else {
                    $msg = $invalidMsg;
                    $msgType = "danger";
                }
            } else {
                $msg = $invalidMsg;
                $msgType = "danger";
            }
        }
    }
}
?>

<div class="container mt-5 mb-5" style="max-width: 500px;">
    
    <div class="text-center mb-4">
        <h1><i class="bi bi-life-preserver"></i> Account Recovery</h1>
        <p class="text-muted">Use one of your saved Recovery Codes to reset your password.</p>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-<?php echo $msgType; ?>"><?php echo $msg; ?></div>
    <?php endif; ?>

    <div class="card shadow">
        <div class="card-body">
            <form method="POST">
                <?php echo csrf_token_field(); ?>
                <div class="row mb-3">
                    <div class="col">
                        <label class="form-label fw-bold">First Name</label>
                        <input type="text" name="firstName" class="form-control" required>
                    </div>
                    <div class="col">
                        <label class="form-label fw-bold">Last Name</label>
                        <input type="text" name="lastName" class="form-control" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold text-danger">Recovery Code</label>
                    <input type="text" name="recoveryCode" class="form-control text-uppercase" placeholder="e.g. A1B2C3D4" required>
                    <div class="form-text">Enter any unused code from your saved list.</div>
                </div>

                <hr>

                <div class="mb-3">
                    <label class="form-label fw-bold">New Password</label>
                    <input type="password" name="newPassword" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Confirm New Password</label>
                    <input type="password" name="confirmPassword" class="form-control" required>
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-danger">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once "include/" . FOOTER_FILE; ?>