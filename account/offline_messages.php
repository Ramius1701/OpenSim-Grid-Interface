<?php
// account/offline_messages.php — Web UI to view & delete pending Offline IMs (OpenSim Offline IM v2)
// Uses site config via include/header.php -> include/config.php -> include/env.php

$title = "Offline Messages";
require_once __DIR__ . '/../include/header.php';

// Require login
if (empty($_SESSION['user']['principal_id'])): ?>
    <div class="container my-5">
        <div class="row">
            <div class="col-md-8 col-lg-6 mx-auto">
                <div class="card shadow-sm border-0 content-card">
                    <div class="card-body">
                        <h3 class="card-title mb-3">Login Required</h3>
                        <p class="mb-0">Please log in to view your offline messages.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php require_once __DIR__ . '/../include/footer.php'; exit; ?>
<?php endif;

$userId = $_SESSION['user']['principal_id'];

if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
$con = @db();

$db_error = null;
$messages = [];
$notice = null;

// Handle deletes (single or bulk)
if ($con && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_one') {
        $id = $_POST['id'] ?? '';
        if (ctype_digit($id)) {
            $stmtDel = mysqli_prepare($con, "DELETE FROM im_offline WHERE ID = ? AND PrincipalID = ?");
            if ($stmtDel) {
                $idInt = (int)$id;
                mysqli_stmt_bind_param($stmtDel, "is", $idInt, $userId);
                mysqli_stmt_execute($stmtDel);
                $affected = mysqli_stmt_affected_rows($stmtDel);
                mysqli_stmt_close($stmtDel);
                $notice = $affected > 0 ? "Message deleted." : "Message not found.";
            }
        }

    } elseif ($action === 'delete_selected') {
        $ids = $_POST['ids'] ?? [];
        $ids = array_values(array_filter($ids, fn($x) => ctype_digit((string)$x)));

        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "DELETE FROM im_offline WHERE ID IN ($placeholders) AND PrincipalID = ?";
            $stmtDel = mysqli_prepare($con, $sql);

            if ($stmtDel) {
                $types = str_repeat('i', count($ids)) . 's';
                $idInts = array_map('intval', $ids);

                // Build bind params by reference (no splat operator)
                $bindParams = [];
                $bindParams[] = $stmtDel;
                $bindParams[] = &$types;
                foreach ($idInts as $i => $val) {
                    $bindParams[] = &$idInts[$i];
                }
                $bindParams[] = &$userId;

                call_user_func_array('mysqli_stmt_bind_param', $bindParams);

                mysqli_stmt_execute($stmtDel);
                $affected = mysqli_stmt_affected_rows($stmtDel);
                mysqli_stmt_close($stmtDel);

                $notice = $affected > 0 ? "$affected message(s) deleted." : "No messages deleted.";
            }
        } else {
            $notice = "No messages were selected.";
        }
    }
}

function parse_offline_im(string $xml, ?string $fallbackStamp, ?mysqli $con): array {
    $out = [
        'from'    => 'Unknown',
        'text'    => '',
        'time'    => null,
    ];

    $sx = @simplexml_load_string($xml);
    if ($sx !== false) {
        $get = function(string $k) use ($sx) {
            return isset($sx->$k) ? trim((string)$sx->$k) : null;
        };

        $out['from'] = $get('fromAgentName') ?? $get('fromAgent') ?? 'Unknown';
        $out['text'] = $get('message') ?? $get('Message') ?? '';

        $ts = $get('timestamp') ?? $get('Timestamp');
        if ($ts !== null && ctype_digit($ts)) {
            $out['time'] = (int)$ts;
        }
    }

    if (!$out['time'] && $fallbackStamp !== null && ctype_digit((string)$fallbackStamp)) {
        $out['time'] = (int)$fallbackStamp;
    }

    // If no name but XML includes fromAgentID, resolve against UserAccounts
    if (($out['from'] === 'Unknown' || $out['from'] === '') && $con) {
        if (preg_match('/<fromAgentID>([^<]+)<\/fromAgentID>/i', $xml, $m)) {
            $from_id = trim($m[1]);
            $u = mysqli_real_escape_string($con, $from_id);
            $res = mysqli_query($con, "SELECT FirstName, LastName FROM UserAccounts WHERE PrincipalID='{$u}' LIMIT 1");
            if ($res && ($r = mysqli_fetch_assoc($res))) {
                $out['from'] = trim($r['FirstName'] . ' ' . $r['LastName']);
            }
        }
    }

    return $out;
}

if (!$con) {
    $db_error = "Database connection failed.";
} else {
    $stmt = mysqli_prepare($con, "SELECT ID, Message, TMStamp FROM im_offline WHERE PrincipalID = ? ORDER BY TMStamp ASC");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $userId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $m = parse_offline_im($row['Message'] ?? '', $row['TMStamp'] ?? null, $con);
                $m['id'] = (int)$row['ID'];
                $messages[] = $m;
            }
        }
        mysqli_stmt_close($stmt);
    } else {
        $db_error = "Query prepare failed. (im_offline table missing?)";
    }
    mysqli_close($con);
}
?>

<div class="container-fluid mt-4 mb-4">
  <div class="row">

    <!-- Left column: info card -->
    <div class="col-md-3">
      <div class="card mb-3">
        <div class="card-header">
          <h5 class="mb-0">
            <i class="bi bi-chat-dots me-1"></i> Messaging
          </h5>
        </div>
        <div class="card-body">
          <p class="small text-muted mb-0">
            These are pending offline IMs stored on the grid for your avatar. Once delivered
            by your viewer, they may no longer appear here.
          </p>
        </div>
      </div>
    </div>

    <!-- Right column: offline messages -->
    <div class="col-md-9">
      <div class="card mb-3">
        <div class="card-header">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
              <h5 class="mb-1">
                <i class="bi bi-envelope-slash me-1"></i> Offline Messages
              </h5>
              <div class="text-muted small">
                View and delete pending offline instant messages for your account.
              </div>
            </div>
          </div>
        </div>

        <?php if ($notice): ?>
          <div class="alert alert-success mb-0">
            <?php echo htmlspecialchars($notice); ?>
          </div>
        <?php endif; ?>

        <div class="card-body">

          <div class="alert alert-info">
            These are <strong>pending</strong> offline IMs.
            If your viewer has already delivered them on login, this list may be empty.
          </div>

          <?php if ($db_error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($db_error); ?></div>

          <?php elseif (empty($messages)): ?>
            <div class="text-center text-muted">
              <p class="mb-0">
                <i class="bi bi-inbox me-2"></i>No offline messages are currently stored for your account.
              </p>
            </div>

          <?php else: ?>
            <!-- SINGLE BULK FORM wraps everything so selected ids submit correctly -->
            <form method="post" id="bulkForm">
              <input type="hidden" name="action" value="delete_selected">

              <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="d-flex align-items-center gap-2">
                  <input class="form-check-input" type="checkbox" id="selectAll">
                  <label class="form-check-label small text-muted" for="selectAll">
                    Select all
                  </label>
                  <span class="small text-muted ms-2" id="selectedCount"></span>
                </div>

                <button type="submit" class="btn btn-sm btn-danger"
                        onclick="return confirm('Delete selected messages?');">
                  Delete Selected
                </button>
              </div>

              <?php foreach ($messages as $m):
                  $timeText = $m['time'] ? date('Y-m-d H:i:s', (int)$m['time']) : 'Unknown time';
                  $id = (int)$m['id'];
              ?>
                <div class="card content-card mb-3 shadow-sm border-0">
                  <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                      <div class="d-flex gap-2 align-items-start">
                        <input class="form-check-input mt-1 msg-check" type="checkbox" name="ids[]"
                               value="<?php echo $id; ?>" aria-label="Select message">
                        <div>
                          <div class="fw-semibold"><?php echo htmlspecialchars($m['from']); ?></div>
                          <div class="small text-muted"><?php echo htmlspecialchars($timeText); ?></div>
                        </div>
                      </div>

                      <!-- per-message delete uses same form via formaction -->
                      <button type="submit"
                              class="btn btn-sm btn-outline-danger ms-2"
                              name="action"
                              value="delete_one"
                              formaction=""
                              formmethod="post"
                              onclick="return confirm('Delete this message?');">
                        Delete
                      </button>
                      <input type="hidden" name="id" value="<?php echo $id; ?>">
                    </div>

                    <div class="card-text">
                      <?php echo nl2br(htmlspecialchars($m['text'])); ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </form>

            <script>
            (function(){
                const selectAll = document.getElementById('selectAll');
                const checks = Array.from(document.querySelectorAll('.msg-check'));
                const countEl = document.getElementById('selectedCount');

                function updateCount() {
                    const n = checks.filter(c => c.checked).length;
                    countEl.textContent = n ? (n + ' selected') : '';
                    selectAll.indeterminate = n > 0 && n < checks.length;
                }

                selectAll.addEventListener('change', () => {
                    checks.forEach(c => c.checked = selectAll.checked);
                    updateCount();
                });

                checks.forEach(c => c.addEventListener('change', updateCount));
                updateCount();
            })();
            </script>

          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/../include/footer.php'; ?>
