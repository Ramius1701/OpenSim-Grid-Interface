<?php
$title = "Create Avatar";
include_once 'include/header.php';
include_once 'include/security.php';

OSWebSecurity::startSecureSession();

// Wegwerf-E-Mail-Domains (erweiterte Liste)
$disposable_domains = [
    'maildrop.cc', 'discard.email', 'fakeinbox.org', 'temp-mail.ru', 'mytrashmail.com',
    'getairmail.net', 'trash-mail.de', 'trashmail.me', 'mail-temporaire.fr', 'nada.email',
    'tempinbox.xyz', 'spambog.com', 'spambox.us', 'anonbox.net', 'mail.kz', 'temp-mail.org',
    'luxusmail.ru', '10minutemail.com', 'guerrillamail.com', 'mailinator.com'
];

$blocked_tlds = ['com', 'cn', 'ru', 'pl'];

// Initialization
$errors = [];
$success_message = '';
$current_step = 1;

// Check if we're continuing from a previous step
if (isset($_SESSION['avatar_creation_step'])) {
    $current_step = $_SESSION['avatar_creation_step'];
}

// Helper functions
function is_disposable_email($email, $disposable_domains, $blocked_tlds) {
    $domain = substr(strrchr($email, "@"), 1);
    $tld = substr(strrchr($domain, "."), 1);
    return in_array($domain, $disposable_domains) || in_array($tld, $blocked_tlds);
}

function sendVerificationEmail($email, $firstName, $lastName, $activationCode) {
    $subject = "Your activation code for " . SITE_NAME;
    $message = "Hello $firstName $lastName,\n\n";
    $message .= "Your activation code is: $activationCode\n\n";
    $message .= "Please use this code to complete your registration.\n\n";
    $message .= "Best regards,\n" . SITE_NAME;

    $headers = "From: noreply@" . parse_url(BASE_URL, PHP_URL_HOST) . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    return mail($email, $subject, $message, $headers);
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token
    if (!verify_csrf_token()) {
        $errors[] = "Security validation failed. Please try again.";
    } else {
        // Rate limiting
        $ip = $_SERVER['REMOTE_ADDR'];
        if (!OSWebSecurity::checkRateLimit($ip, 3, 600)) { // 3 attempts per 10 minutes
            $errors[] = "Too many registration attempts. Please wait 10 minutes before trying again.";
        } else {
            if (isset($_POST['submit_step1'])) {
                // Step 1: Basic Information
                $validation_rules = [
                    'firstName' => ['required', ['length' => ['min' => 2, 'max' => 31]], 'avatar_name'],
                    'lastName' => ['required', ['length' => ['min' => 2, 'max' => 31]], 'avatar_name'],
                    'email' => ['required', 'email'],
                    'password' => ['required', ['length' => ['min' => 8, 'max' => 50]]],
                    'password_confirm' => ['required']
                ];
                
                $form_data = [
                    'firstName' => $_POST['firstName'] ?? '',
                    'lastName' => $_POST['lastName'] ?? '',
                    'email' => $_POST['email'] ?? '',
                    'password' => $_POST['password'] ?? '',
                    'password_confirm' => $_POST['password_confirm'] ?? ''
                ];
                
                $validation_errors = validate_form($form_data, $validation_rules);
                
                // Additional validations
                if (empty($validation_errors)) {
                    if ($form_data['password'] !== $form_data['password_confirm']) {
                        $validation_errors['password_confirm'] = 'Passwords do not match';
                    }
                    
                    $password_check = OSWebSecurity::validatePassword($form_data['password']);
                    if ($password_check !== true) {
                        $validation_errors['password'] = implode(', ', $password_check);
                    }
                    
                    if (is_disposable_email($form_data['email'], $disposable_domains, $blocked_tlds)) {
                        $validation_errors['email'] = 'Disposable email addresses are not allowed';
                    }
                }
                
                if (empty($validation_errors)) {
                    // Store data in session
                    $_SESSION['avatar_data'] = [
                        'firstName' => sanitize_input($form_data['firstName']),
                        'lastName' => sanitize_input($form_data['lastName']),
                        'email' => sanitize_input($form_data['email'], 'email'),
                        'password' => $form_data['password'] // Will be hashed later
                    ];
                    
                    $_SESSION['avatar_creation_step'] = 2;
                    $current_step = 2;
                    
                    // Generate and send verification code
                    $activation_code = bin2hex(random_bytes(16));
                    $_SESSION['activation_code'] = $activation_code;
                    $_SESSION['code_expires'] = time() + 1800; // 30 minutes
                    
                    if (sendVerificationEmail($form_data['email'], $form_data['firstName'], $form_data['lastName'], $activation_code)) {
                        $success_message = 'Verification code sent to your email address.';
                    } else {
                        $errors[] = 'Failed to send verification email. Please check your email address.';
                    }
                } else {
                    $errors = array_values($validation_errors);
                }
                
            } elseif (isset($_POST['submit_step2'])) {
                // Step 2: Email Verification
                $provided_code = sanitize_input($_POST['activation_code'] ?? '');
                $stored_code = $_SESSION['activation_code'] ?? '';
                $code_expires = $_SESSION['code_expires'] ?? 0;
                
                if (empty($provided_code)) {
                    $errors[] = 'Please enter the activation code.';
                } elseif (time() > $code_expires) {
                    $errors[] = 'Activation code has expired. Please start registration again.';
                    session_destroy();
                } elseif (!hash_equals($stored_code, $provided_code)) {
                    $errors[] = 'Invalid activation code. Please check your email.';
                } else {
                    $_SESSION['avatar_creation_step'] = 3;
                    $current_step = 3;
                    $success_message = 'Email verified successfully!';
                }
                
            } elseif (isset($_POST['submit_step3'])) {
                // Step 3: Final Registration
                if (!isset($_SESSION['avatar_data'])) {
                    $errors[] = 'Session expired. Please start registration again.';
                    session_destroy();
                } else {
                    try {
                        $db = new OSWebDatabase();
                        $avatar_data = $_SESSION['avatar_data'];
                        
                        // Check if user already exists
                        $check_stmt = $db->prepare("SELECT COUNT(*) FROM UserAccounts WHERE FirstName = ? AND LastName = ?");
                        $check_stmt->bind_param("ss", $avatar_data['firstName'], $avatar_data['lastName']);
                        $check_stmt->execute();
                        $check_stmt->bind_result($count);
                        $check_stmt->fetch();
                        $check_stmt->close();
                        
                        if ($count > 0) {
                            $errors[] = 'An avatar with this name already exists.';
                        } else {
                            // Create avatar
                            $user_uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                                mt_rand(0, 0xffff),
                                mt_rand(0, 0x0fff) | 0x4000,
                                mt_rand(0, 0x3fff) | 0x8000,
                                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                            );
                            
                            // Hash password
                            $salt = md5(str_replace("-", "", $user_uuid));
                            $password_hash = md5(md5($avatar_data['password']) . ":" . $salt);
                            
                            // Insert user account
                            $stmt = $db->prepare("INSERT INTO UserAccounts (PrincipalID, ScopeID, FirstName, LastName, Email, ServiceURLs, Created, passwordHash, passwordSalt) VALUES (?, '00000000-0000-0000-0000-000000000000', ?, ?, ?, '', ?, ?, ?)");
                            $created = time();
                            $stmt->bind_param("ssssiiss", $user_uuid, $avatar_data['firstName'], $avatar_data['lastName'], $avatar_data['email'], $created, $password_hash, $salt);
                            
                            if ($stmt->execute()) {
                                $success_message = 'Avatar created successfully! You can now log in to the grid.';
                                $current_step = 4;
                                
                                // Clear session data
                                unset($_SESSION['avatar_data']);
                                unset($_SESSION['activation_code']);
                                unset($_SESSION['code_expires']);
                                unset($_SESSION['avatar_creation_step']);
                                
                            } else {
                                $errors[] = 'Failed to create avatar. Please try again.';
                                OSWebErrorHandler::logError('Avatar creation failed', ['error' => $stmt->error]);
                            }
                            
                            $stmt->close();
                        }
                        
                    } catch (Exception $e) {
                        $errors[] = 'A system error occurred. Please try again later.';
                        OSWebErrorHandler::logError('Avatar creation exception', ['error' => $e->getMessage()]);
                    }
                }
            }
        }
    }
}
?>

<div class="content-card">
    <div class="text-center mb-4">
        <i class="bi bi-person-plus text-primary" style="font-size: 3rem;"></i>
        <h1 class="mt-3">Create Your Avatar</h1>
        <p class="text-muted">Join <?php echo SITE_NAME; ?> and start your virtual journey</p>
    </div>
    
    <!-- Progress Bar -->
    <div class="progress mb-4" style="height: 8px;">
        <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo ($current_step / 4) * 100; ?>%"></div>
    </div>
    
    <div class="row justify-content-center">
        <div class="col-md-8">
            
            <!-- Display Errors -->
            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $error): ?>
                    <?php echo display_error($error); ?>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- Display Success Message -->
            <?php if ($success_message): ?>
                <?php echo display_error($success_message, 'success'); ?>
            <?php endif; ?>
            
            <?php if ($current_step == 1): ?>
                <!-- Step 1: Basic Information -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-1-circle"></i> Basic Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="needs-validation" novalidate>
                            <?php echo csrf_token_field(); ?>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="firstName" class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="firstName" name="firstName" 
                                           value="<?php echo $_POST['firstName'] ?? ''; ?>" required 
                                           pattern="[a-zA-Z0-9\s]{2,31}" maxlength="31">
                                    <div class="invalid-feedback">
                                        Please provide a valid first name (2-31 characters, letters and numbers only).
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="lastName" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="lastName" name="lastName" 
                                           value="<?php echo $_POST['lastName'] ?? ''; ?>" required 
                                           pattern="[a-zA-Z0-9\s]{2,31}" maxlength="31">
                                    <div class="invalid-feedback">
                                        Please provide a valid last name (2-31 characters, letters and numbers only).
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo $_POST['email'] ?? ''; ?>" required>
                                <div class="form-text">We'll send a verification code to this address.</div>
                                <div class="invalid-feedback">
                                    Please provide a valid email address.
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required minlength="8">
                                    <div class="form-text">
                                        At least 8 characters with uppercase, lowercase, and numbers.
                                    </div>
                                    <div class="invalid-feedback">
                                        Password must be at least 8 characters long.
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="password_confirm" class="form-label">Confirm Password</label>
                                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                                    <div class="invalid-feedback">
                                        Please confirm your password.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="submit_step1" class="btn btn-primary btn-lg">
                                    <i class="bi bi-arrow-right"></i> Continue to Verification
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
            <?php elseif ($current_step == 2): ?>
                <!-- Step 2: Email Verification -->
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="bi bi-2-circle"></i> Email Verification</h5>
                    </div>
                    <div class="card-body text-center">
                        <i class="bi bi-envelope-check text-warning" style="font-size: 4rem;"></i>
                        <h4 class="mt-3">Check Your Email</h4>
                        <p class="text-muted mb-4">
                            We've sent a verification code to <strong><?php echo $_SESSION['avatar_data']['email'] ?? ''; ?></strong>
                        </p>
                        
                        <form method="POST">
                            <?php echo csrf_token_field(); ?>
                            
                            <div class="row justify-content-center">
                                <div class="col-md-6">
                                    <label for="activation_code" class="form-label">Activation Code</label>
                                    <input type="text" class="form-control form-control-lg text-center" 
                                           id="activation_code" name="activation_code" required 
                                           placeholder="Enter code here" maxlength="32">
                                </div>
                            </div>
                            
                            <div class="d-grid mt-4">
                                <button type="submit" name="submit_step2" class="btn btn-warning btn-lg">
                                    <i class="bi bi-check-circle"></i> Verify Email
                                </button>
                            </div>
                        </form>
                        
                        <p class="text-muted mt-3">
                            <small>Code expires in 30 minutes. Didn't receive it? Check your spam folder.</small>
                        </p>
                    </div>
                </div>
                
            <?php elseif ($current_step == 3): ?>
                <!-- Step 3: Final Confirmation -->
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-3-circle"></i> Create Avatar</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h6><i class="bi bi-info-circle"></i> Avatar Details</h6>
                            <p class="mb-1"><strong>Name:</strong> <?php echo $_SESSION['avatar_data']['firstName'] ?? ''; ?> <?php echo $_SESSION['avatar_data']['lastName'] ?? ''; ?></p>
                            <p class="mb-0"><strong>Email:</strong> <?php echo $_SESSION['avatar_data']['email'] ?? ''; ?></p>
                        </div>
                        
                        <form method="POST">
                            <?php echo csrf_token_field(); ?>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="agreeTerms" required>
                                <label class="form-check-label" for="agreeTerms">
                                    I agree to the <a href="include/tos.php" target="_blank">Terms of Service</a> and 
                                    <a href="include/dmca.php" target="_blank">Privacy Policy</a>
                                </label>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="submit_step3" class="btn btn-success btn-lg">
                                    <i class="bi bi-person-check"></i> Create My Avatar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
            <?php elseif ($current_step == 4): ?>
                <!-- Step 4: Success -->
                <div class="card border-success">
                    <div class="card-body text-center">
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                        <h3 class="text-success mt-3">Avatar Created Successfully!</h3>
                        <p class="text-muted mb-4">
                            Welcome to <?php echo SITE_NAME; ?>! Your avatar has been created and is ready to explore the virtual world.
                        </p>
                        
                        <div class="d-grid gap-2 d-md-block">
                            <a href="welcomesplashpage.php" class="btn btn-primary">
                                <i class="bi bi-house"></i> Go to Homepage
                            </a>
                            <a href="help.php" class="btn btn-outline-primary">
                                <i class="bi bi-question-circle"></i> Getting Started Guide
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<script>
// Password strength indicator
document.addEventListener('DOMContentLoaded', function() {
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('password_confirm');
    
    if (password) {
        password.addEventListener('input', function() {
            const value = this.value;
            const strength = calculatePasswordStrength(value);
            updatePasswordStrengthIndicator(strength);
        });
    }
    
    if (confirmPassword) {
        confirmPassword.addEventListener('input', function() {
            const password1 = password.value;
            const password2 = this.value;
            
            if (password2 && password1 !== password2) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    }
    
    function calculatePasswordStrength(password) {
        let score = 0;
        if (password.length >= 8) score++;
        if (/[a-z]/.test(password)) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[^A-Za-z0-9]/.test(password)) score++;
        return score;
    }
    
    function updatePasswordStrengthIndicator(strength) {
        // Could add visual password strength indicator here
    }
});
</script>

<?php include_once 'include/footer.php'; ?>