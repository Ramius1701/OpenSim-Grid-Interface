<?php
// --- OpenSim 0.9.3.x (lickx) login handler — in-place, no layout changes ---
// Put this as the FIRST bytes of login.php (before any HTML).

require_once __DIR__ . '/include/config.php';

// Minimal DB connector for this page (uses include/env.php constants)
if (!function_exists('db')) {
  function db() {
    if (!defined('DB_SERVER')) { return null; }
    if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
    $conn = @mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    return $conn ?: null;
  }
}

if (session_status() === PHP_SESSION_NONE) session_start();

// Turn on temporarily if you need verbose reasons in $login_error
const DEBUG_LOGIN = false;

// CSRF seed (enforced only if form sends csrf_token)
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

function safe_next($val) {
  $val = trim((string)$val);
  if ($val === '' || stripos(basename($val), 'login.php') !== false) return 'account/';
  if (preg_match('~^(?:https?:)?//~i', $val)) return 'account/';
  if (strpos($val, "\n") !== false || strpos($val, "\r") !== false) return 'account/';
  return $val;
}

// ---- Password verifier for OpenSim classic scheme ----
// Official formula: md5( md5(password) . ':' . passwordSalt )
function verify_opensim_password(string $password, string $storedHash = '', string $salt = ''): bool {
  $storedHash = strtolower((string)$storedHash);
  $salt       = (string)$salt;
  if ($storedHash === '') return false;

  // bcrypt (some grids migrate) — accept if present
  if (preg_match('/^\$2[aby]\$/', $storedHash)) {
    return password_verify($password, $storedHash);
  }

  // canonical lickx/opensim MD5 form
  $h1 = md5(md5($password) . ':' . $salt);
  if (strtolower($h1) === $storedHash) return true;

  // a couple of common drift variants we’ve seen in the wild
  $alt = [
    md5($salt . md5($password)),
    md5(md5($password) . $salt),
    md5($password . ':' . $salt),
    md5($salt . ':' . $password),
    md5($password . $salt),
    md5($salt . $password),
  ];
  foreach ($alt as $cand) if (strtolower($cand) === $storedHash) return true;

  return false;
}

// ---- mysqlnd-free readers (bind_result, no get_result()) ----
function find_user_by_email(mysqli $conn, string $email): ?array {
  // useraccounts (PrincipalID, FirstName, LastName, Email)
  // auth (UUID, passwordHash, passwordSalt)
  $sql = "SELECT u.PrincipalID, u.FirstName, u.LastName, u.Email,
                 a.passwordHash, a.passwordSalt
            FROM `useraccounts` u
       LEFT JOIN `auth` a ON a.UUID = u.PrincipalID
           WHERE u.Email = ? LIMIT 1";
  if (!($stmt = $conn->prepare($sql))) return null;
  $stmt->bind_param('s', $email);
  if (!$stmt->execute()) { $stmt->close(); return null; }
  $stmt->bind_result($PrincipalID,$FirstName,$LastName,$Email,$PasswordHash,$PasswordSalt);
  $row = null;
  if ($stmt->fetch()) {
    $row = [
      'PrincipalID'  => $PrincipalID,
      'FirstName'    => $FirstName,
      'LastName'     => $LastName,
      'Email'        => $Email,
      'PasswordHash' => $PasswordHash,
      'PasswordSalt' => $PasswordSalt,
    ];
  }
  $stmt->close();
  return $row;
}

function find_user_by_name(mysqli $conn, string $first, string $last): ?array {
  $sql = "SELECT u.PrincipalID, u.FirstName, u.LastName, u.Email,
                 a.passwordHash, a.passwordSalt
            FROM `useraccounts` u
       LEFT JOIN `auth` a ON a.UUID = u.PrincipalID
           WHERE u.FirstName = ? AND u.LastName = ? LIMIT 1";
  if (!($stmt = $conn->prepare($sql))) return null;
  $stmt->bind_param('ss', $first, $last);
  if (!$stmt->execute()) { $stmt->close(); return null; }
  $stmt->bind_result($PrincipalID,$FirstName,$LastName,$Email,$PasswordHash,$PasswordSalt);
  $row = null;
  if ($stmt->fetch()) {
    $row = [
      'PrincipalID'  => $PrincipalID,
      'FirstName'    => $FirstName,
      'LastName'     => $LastName,
      'Email'        => $Email,
      'PasswordHash' => $PasswordHash,
      'PasswordSalt' => $PasswordSalt,
    ];
  }
  $stmt->close();
  return $row;
}

// ---- main handler ----
$login_error = '';
$reasons = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // CSRF (only if your form includes it)
  if (isset($_POST['csrf_token'])) {
    if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf_token'])) {
      $login_error = 'Session expired. Please reload and try again.';
    }
  }

  if ($login_error === '') {
    // Your form uses name="username"
    $user = trim($_POST['username'] ?? $_POST['email'] ?? '');
    $pass = (string)($_POST['password'] ?? $_POST['pass'] ?? '');
    $next = safe_next($_POST['next'] ?? $_GET['next'] ?? 'account/');

    if ($user === '' || $pass === '') {
      $login_error = 'Please enter both username/email and password.';
    } else {
      $conn = db();
      if (!$conn) {
        $login_error = 'Database not available. Check include/config.php.';
      } else {
        // Try email first if it looks like one
        $row = null;
        if (strpos($user, '@') !== false) {
          $row = find_user_by_email($conn, $user);
          if (!$row) $reasons[] = 'no email match';
        }
        // Then try "First Last" or "First.Last"
        if (!$row) {
          $name = str_replace('.', ' ', $user);
          $parts = preg_split('/\s+/', trim($name), 2);
          if (count($parts) === 2) {
            [$first,$last] = $parts;
            $row = find_user_by_name($conn, $first, $last);
            if (!$row) $reasons[] = 'no name match';
          } else {
            $reasons[] = 'name not parseable';
          }
        }

        if (!$row) {
          $login_error = 'Invalid credentials (user not found).';
        } else {
          $ph = (string)($row['PasswordHash'] ?? '');
          $ps = (string)($row['PasswordSalt'] ?? '');
          if ($ph === '') {
            $login_error = 'Account has no password set.';
          } elseif (!verify_opensim_password($pass, $ph, $ps)) {
            $login_error = 'Invalid credentials (password mismatch).';
          } else {
            // SUCCESS
            $_SESSION['user'] = [
              'principal_id' => $row['PrincipalID'],
              'email'        => $row['Email'],
              'name'         => trim(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? '')),
            ];
            $dest = $next; // default account/
            if (!headers_sent()) { header('Location: ' . $dest); exit; }
            echo '<script>location.href=' . json_encode($dest) . ';</script>';
            exit;
          }
        }
      }
    }
  }
}

if (DEBUG_LOGIN && $login_error !== '') {
  $login_error .= (!empty($reasons) ? ' — ' . implode(', ', $reasons) : '');
}
// $login_error is printed by your existing HTML block above the form.
?>

<?php
// After processing, render the site's header/footer + Bootstrap form
$title = "Login";
include_once 'include/header.php';

$login_error = $login_error ?? '';
$next = isset($_POST['next']) ? safe_next($_POST['next']) : 'account/';
?>

<main class="content-card">
  <section class="mb-4" style="max-width:680px;margin:0 auto;">
    <h1 class="mb-1">Sign in</h1>
    <p class="text-muted">Use your in-world account credentials (format: <em>First Last</em>) or your account email.</p>

    <?php if (!empty($login_error)): ?>
      <div class="alert alert-danger" role="alert">
        <?php echo htmlspecialchars($login_error, ENT_QUOTES, 'UTF-8'); ?>
      </div>
    <?php endif; ?>

    <form method="post" action="login.php" autocomplete="on" accept-charset="UTF-8" class="mt-3">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf'] ?? ''); ?>">
      <input type="hidden" name="next" value="<?php echo htmlspecialchars($next, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="text" name="website" value="" style="display:none" aria-hidden="true" tabindex="-1" autocomplete="off">

      <div class="mb-3">
        <label for="username" class="form-label">Username or Email</label>
        <input id="username" name="username" type="text" required autocomplete="username" inputmode="text" class="form-control" placeholder="First Last or email">
      </div>

      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input id="password" name="password" type="password" required autocomplete="current-password" class="form-control" placeholder="••••••••">
      </div>

      <button class="btn btn-primary w-100" type="submit">Sign in</button>
    </form>

    <div class="mt-3 small text-muted">
      <p class="mb-1">Tip: If login fails, verify your name spelling (e.g., <em>First Last</em>) or try your account email.</p>
      <p class="mb-0">After signing in, you’ll be redirected to your profile.</p>
    </div>
  </section>
</main>

<?php include_once 'include/footer.php'; ?>
