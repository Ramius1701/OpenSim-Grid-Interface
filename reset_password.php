<?php
$title = "Reset Password";
include_once 'include/header.php';

// SHARED DB CONNECTION
$conn = db();

// Ensure Recovery Codes table exists (supports grids that predate this feature)
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


$msg = "";
$msgType = ""; // success or danger

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fname = trim($_POST['firstName']);
    $lname = trim($_POST['lastName']);
    $code  = strtoupper(preg_replace('/[^A-Z0-9]/', '', trim($_POST['recoveryCode'] ?? '')));
    $pass  = $_POST['newPassword'];
    $pass2 = $_POST['confirmPassword'];

    // 1. Basic Validation
    if ($pass !== $pass2) {
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
        
        if ($row = $res->fetch_assoc()) {
            $uuid = $row['PrincipalID'];
            ensure_recovery_table($conn);
            
            // 3. Fetch all UNUSED recovery codes for this user
            // We verify them one by one using password_verify()
            $codeFound = false;
            $codeIDToInvalidate = 0;
            
            $sqlCodes = "SELECT id, code_hash FROM ws_recovery_codes WHERE PrincipalID = ? AND is_used = 0";
            $stmtCodes = $conn->prepare($sqlCodes);
            $stmtCodes->bind_param("s", $uuid);
            $stmtCodes->execute();
            $resCodes = $stmtCodes->get_result();
            
            while ($cRow = $resCodes->fetch_assoc()) {
                // Check if the input code matches this hash
                if (password_verify($code, $cRow['code_hash'])) {
                    $codeFound = true;
                    $codeIDToInvalidate = $cRow['id'];
                    break; // Stop looking, we found a match
                }
            }
            
            if ($codeFound) {
                // 4. Code is valid! Perform Password Reset (atomic: update password + burn code)
                $conn->begin_transaction();
                try {
                    // A. Update Password in 'auth' table
                    $newSalt = md5(uniqid(mt_rand(), true));
                    $newHash = md5(md5($pass) . ":" . $newSalt);

                    $upSql = "UPDATE auth SET passwordHash = ?, passwordSalt = ? WHERE UUID = ?";
                    $upStmt = $conn->prepare($upSql);
                    $upStmt->bind_param("sss", $newHash, $newSalt, $uuid);

                    if (!$upStmt->execute()) {
                        throw new Exception("Database error updating password.");
                    }

                    // B. Burn the code (Mark as used) - only if still unused
                    $burnSql = "UPDATE ws_recovery_codes SET is_used = 1 WHERE id = ? AND is_used = 0";
                    $burnStmt = $conn->prepare($burnSql);
                    $burnStmt->bind_param("i", $codeIDToInvalidate);

                    if (!$burnStmt->execute() || $burnStmt->affected_rows !== 1) {
                        throw new Exception("Database error invalidating recovery code.");
                    }

                    if (!$conn->commit()) {
                        throw new Exception("Database error finalizing reset.");
                    }

                    $msg = "Success! Your password has been reset. You can log in now.";
                    $msgType = "success";
                } catch (Throwable $e) {
                    $conn->rollback();
                    $msg = $e->getMessage();
                    $msgType = "danger";
                }
} else {
                $msg = "Invalid Recovery Code. Please check your spelling or try a different code.";
                $msgType = "danger";
            }
            
        } else {
            $msg = "Avatar not found.";
            $msgType = "danger";
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