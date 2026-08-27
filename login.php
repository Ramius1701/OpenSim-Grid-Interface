<?php
// --- OpenSim 0.9.3.x (lickx) login handler — in-place, no layout changes ---
// Put this as the FIRST bytes of login.php (before any HTML).

require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/security.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Turn on temporarily if you need verbose reasons in $login_error
const DEBUG_LOGIN = false;

// CSRF seed (always required - see the check below)
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

function safe_next($val) {
  $val = trim((string)$val);
  if ($val === '') return 'account/';

  // Browsers silently strip tab/newline/CR characters before parsing a URL,
  // so remove them here too - otherwise "//evil.com" can be hidden as
  // "/\t/evil.com" and slip past the checks below.
  $val = str_replace(["\t", "\n", "\r"], '', $val);
  if ($val === '' || stripos(basename($val), 'login.php') !== false) return 'account/';

  // Reject backslashes: some browsers normalize "\" to "/" while parsing a
  // URL, so "/\evil.com" can become the protocol-relative "//evil.com".
  if (strpos($val, '\\') !== false) return 'account/';

  // Reject protocol-relative ("//host") and absolute ("http(s)://host") URLs.
  if (preg_match('~^(?:https?:)?//~i', $val)) return 'account/';

  // Reject any URI scheme (e.g. "https:evil.com", "javascript:..."). Browsers
  // parse "scheme:host" as an absolute URL for special schemes even without
  // "//", so checking for "://" / "//" alone isn't enough to catch it.
  if (preg_match('/^[a-zA-Z][a-zA-Z0-9+.\-]*:/', $val)) return 'account/';

  return $val;
}

// ---- Password verifier for OpenSim classic scheme ----
// Official formula: md5( md5(password) . ':' . passwordSalt )
function verify_opensim_password(string $password, string $storedHash = '', string $salt = ''): bool {
  $storedHash = (string)$storedHash;
  $salt       = (string)$salt;
  if ($storedHash === '') return false;

  // bcrypt (some grids migrate) — accept if present. Must check/verify
  // against the hash's original case: bcrypt output is case-sensitive, so
  // lowercasing it first (as the MD5 paths below need to) would corrupt it
  // and every bcrypt-migrated account would silently fail to ever log in.
  if (preg_match('/^\$2[aby]\$/', $storedHash)) {
    return password_verify($password, $storedHash);
  }

  // canonical lickx/opensim MD5 form (hex digests, safe to compare
  // case-insensitively from here on)
  $storedHash = strtolower($storedHash);
  $h1 = md5(md5($password) . ':' . $salt);
  if (strtolower($h1) === $storedHash) return true;

  // a couple of common drift variants we’ve seen in the wild
  // A few legacy hash-order variants seen on migrated grids. Kept for
  // compatibility (removing them outright would risk locking out real
  // accounts we can't identify in advance) but every account that verifies
  // via one of these gets transparently rehashed to the canonical form on
  // its next successful login - see rehash_to_canonical_if_needed() below -
  // so the accepted surface shrinks over time instead of staying wide open
  // indefinitely.
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

// If a login just succeeded via a non-canonical hash variant, rewrite the
// stored hash to the canonical form (same password, same salt, just the
// standard concatenation order) so this account no longer depends on the
// wider fallback matching in verify_opensim_password() on future logins.
// No-op (and safe to call) when the stored hash is already canonical.
function rehash_to_canonical_if_needed(mysqli $conn, string $uuid, string $password, string $storedHash, string $salt): void {
  $storedHash = strtolower($storedHash);
  // Never touch a bcrypt hash - it's already stronger than the MD5 scheme
  // this function standardizes on; rewriting it would be a downgrade.
  if (preg_match('/^\$2[aby]\$/', $storedHash)) return;

  $canonical = strtolower(md5(md5($password) . ':' . $salt));
  if ($storedHash === $canonical) return;

  $stmt = $conn->prepare("UPDATE auth SET passwordHash = ? WHERE UUID = ?");
  if (!$stmt) return;
  $stmt->bind_param('ss', $canonical, $uuid);
  $stmt->execute();
  $stmt->close();
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
  // CSRF: always required. A forged cross-site POST that simply omits the
  // field must fail here too, not skip validation.
  $csrf_token = (string)($_POST['csrf_token'] ?? '');
  if (empty($_SESSION['csrf']) || $csrf_token === '' || !hash_equals($_SESSION['csrf'], $csrf_token)) {
    $login_error = 'Session expired. Please reload and try again.';
  }

  if ($login_error === '') {
    // Your form uses name="username"
    $user = trim($_POST['username'] ?? $_POST['email'] ?? '');
    $pass = (string)($_POST['password'] ?? $_POST['pass'] ?? '');
    $next = safe_next($_POST['next'] ?? $_GET['next'] ?? 'account/');

    // Rate limit by IP (broad anti-automation) and by the submitted
    // identifier (so an attacker can't dodge the account-level limit by
    // spreading attempts across many IPs). Either one tripping blocks the
    // request.
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    // Both calls must run (not short-circuit) so each dimension's attempt
    // count is recorded regardless of the other's result.
    $ipRateOk   = OSWebSecurity::checkRateLimit('login_ip:' . $ip, 15, 900);
    $userRateOk = OSWebSecurity::checkRateLimit('login_user:' . strtolower($user), 8, 900);

    if (!$ipRateOk || !$userRateOk) {
      $login_error = 'Too many login attempts. Please wait a few minutes and try again.';
    } elseif ($user === '' || $pass === '') {
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
            // SUCCESS - regenerate the session ID before writing the
            // authenticated session, so a pre-existing (possibly attacker-
            // seeded) session ID never carries over into a logged-in session.
            OSWebSecurity::regenerateSessionOnLogin();
            rehash_to_canonical_if_needed($conn, (string)$row['PrincipalID'], $pass, $ph, $ps);
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
    <h1 class="mb-1"><i class="bi bi-box-arrow-in-right me-2"></i> Sign in</h1>
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

<?php include_once "include/" . FOOTER_FILE; ?>
