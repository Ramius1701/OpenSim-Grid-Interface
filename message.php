<?php
// casperia/message.php — Internal web messaging (IM-style) for Casperia site
// Replaces legacy MOTD JSON endpoint. Keeps filename reserved for messaging.
// Storage: ws_messages table in the same DB as your OpenSim + site tables.

$title = "Messages";

include_once __DIR__ . "/include/config.php";
include_once __DIR__ . "/include/" . HEADER_FILE;

// ------------------------------------------------------------
// Require login
// ------------------------------------------------------------
$currentUserId = null;
$isLoggedIn = false;
$currentUserName = 'Guest';

if (isset($_SESSION['user']) && !empty($_SESSION['user']['principal_id'])) {
    $currentUserId = $_SESSION['user']['principal_id'];
    $isLoggedIn = true;
    $currentUserName = $_SESSION['user']['display_name'] ?? $_SESSION['user']['name'] ?? 'Resident';
}

if (!$isLoggedIn) {
    echo '<div class="content-card"><h1 class="mb-2">Messages</h1><p class="text-muted">Please log in to view or send messages.</p></div>';
    include_once __DIR__ . "/include/footer.php";
    exit;
}

// ------------------------------------------------------------
// DB connect
// ------------------------------------------------------------
$con = db();
if (!$con) {
    echo '<div class="content-card banner error">Database connection failed.</div>';
    include_once __DIR__ . "/include/footer.php";
    exit;
}

// Ensure ws_messages table exists (surgical, self-contained)
mysqli_query($con, "CREATE TABLE IF NOT EXISTS ws_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_uuid CHAR(36) NOT NULL,
    receiver_uuid CHAR(36) NOT NULL,
    subject VARCHAR(150) NOT NULL DEFAULT '',
    body TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    sender_deleted TINYINT(1) NOT NULL DEFAULT 0,
    receiver_deleted TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_receiver (receiver_uuid, is_read),
    INDEX idx_sender (sender_uuid),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ------------------------------------------------------------
// Helpers
// ------------------------------------------------------------
function avatar_name(mysqli $con, string $uuid): string {
    // Resolve OpenSim avatar name from UserAccounts
    $sql = "SELECT FirstName, LastName FROM UserAccounts WHERE PrincipalID = ? LIMIT 1";
    if ($st = mysqli_prepare($con, $sql)) {
        mysqli_stmt_bind_param($st, "s", $uuid);
        mysqli_stmt_execute($st);
        mysqli_stmt_bind_result($st, $fn, $ln);
        if (mysqli_stmt_fetch($st)) {
            mysqli_stmt_close($st);
            $name = trim($fn . ' ' . $ln);
            return $name !== '' ? $name : $uuid;
        }
        mysqli_stmt_close($st);
    }
    return $uuid;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$action = $_GET['action'] ?? 'inbox';
$flash  = null;
$flashType = 'success';

// ------------------------------------------------------------
// Handle POST: send message
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $to   = trim($_POST['to_uuid'] ?? '');
    $subj = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');

    if ($to === '' || !preg_match('/^[0-9a-fA-F-]{36}$/', $to)) {
        $flash = "Please choose a valid recipient.";
        $flashType = 'danger';
        $action = 'compose';
    } elseif ($body === '') {
        $flash = "Message body cannot be empty.";
        $flashType = 'danger';
        $action = 'compose';
    } else {
        $sql = "INSERT INTO ws_messages (sender_uuid, receiver_uuid, subject, body) VALUES (?,?,?,?)";
        $st = mysqli_prepare($con, $sql);
        mysqli_stmt_bind_param($st, "ssss", $currentUserId, $to, $subj, $body);
        $ok = mysqli_stmt_execute($st);
        mysqli_stmt_close($st);

        if ($ok) {
            $flash = "Message sent.";
            $flashType = 'success';
            $action = 'sent';
        } else {
            $flash = "Failed to send message.";
            $flashType = 'danger';
            $action = 'compose';
        }
    }
}

// ------------------------------------------------------------
// Handle delete
// ------------------------------------------------------------
if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    // Determine whether user is sender or receiver and soft-delete accordingly
    $sql = "SELECT sender_uuid, receiver_uuid FROM ws_messages WHERE id = ? LIMIT 1";
    $st = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($st, "i", $id);
    mysqli_stmt_execute($st);
    mysqli_stmt_bind_result($st, $sender, $receiver);
    if (mysqli_stmt_fetch($st)) {
        mysqli_stmt_close($st);
        if ($sender === $currentUserId) {
            mysqli_query($con, "UPDATE ws_messages SET sender_deleted=1 WHERE id=".(int)$id);
            $flash = "Message removed from Sent.";
            $flashType = 'success';
            $action = 'sent';
        } elseif ($receiver === $currentUserId) {
            mysqli_query($con, "UPDATE ws_messages SET receiver_deleted=1 WHERE id=".(int)$id);
            $flash = "Message removed from Inbox.";
            $flashType = 'success';
            $action = 'inbox';
        } else {
            $flash = "You don’t have permission to delete that message.";
            $flashType = 'danger';
            $action = 'inbox';
        }
    } else {
        mysqli_stmt_close($st);
        $flash = "Message not found.";
        $flashType = 'danger';
        $action = 'inbox';
    }
}



// ------------------------------------------------------------
// UI layout and views
// ------------------------------------------------------------
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
            Send and receive internal web messages with other residents.
          </p>
        </div>
      </div>
    </div>

    <!-- Right column: main messages card -->
    <div class="col-md-9">
      <div class="card mb-3">
        <div class="card-header">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
              <h5 class="mb-1">
                <i class="bi bi-envelope me-1"></i> Messages
              </h5>
              <div class="text-muted small">
                Signed in as <?=h($currentUserName)?>
              </div>
            </div>
            <div class="d-flex gap-2">
              <a class="btn btn-theme-outline<?= $action === 'inbox' ? ' active' : '' ?>" href="message.php?action=inbox">
                <i class="bi bi-inbox"></i> Inbox
              </a>
              <a class="btn btn-theme-outline<?= $action === 'sent' ? ' active' : '' ?>" href="message.php?action=sent">
                <i class="bi bi-send"></i> Sent
              </a>
              <a class="btn btn-theme-outline<?= $action === 'compose' ? ' active' : '' ?>" href="message.php?action=compose">
                <i class="bi bi-pencil-square"></i> Compose
              </a>
            </div>
          </div>
        </div>

        <?php if ($flash): ?>
          <div class="alert alert-<?=$flashType?> mb-0"><?=h($flash)?></div>
        <?php endif; ?>

        <div class="card-body">
<?php
// ------------------------------------------------------------
// Compose view
// ------------------------------------------------------------
if ($action === 'compose') {
    $to = $_GET['to'] ?? ($_POST['to_uuid'] ?? '');
    $to = preg_match('/^[0-9a-fA-F-]{36}$/', $to) ? $to : '';

    // Simple recipient picker: search term
    $search = trim($_GET['q'] ?? '');
    $candidates = [];
    if ($search !== '') {
        $like = '%'.$search.'%';
        $sql = "SELECT PrincipalID, FirstName, LastName
                FROM UserAccounts
                WHERE (FirstName LIKE ? OR LastName LIKE ? OR CONCAT(FirstName,' ',LastName) LIKE ?)
                ORDER BY FirstName, LastName
                LIMIT 50";
        if ($st = mysqli_prepare($con, $sql)) {
            mysqli_stmt_bind_param($st, "sss", $like, $like, $like);
            mysqli_stmt_execute($st);
            $res = mysqli_stmt_get_result($st);
            while ($row = mysqli_fetch_assoc($res)) {
                $candidates[] = $row;
            }
            mysqli_stmt_close($st);
        }
    }

    $toName = $to ? avatar_name($con, $to) : '';
    ?>
          <h2 class="mb-3">Compose Message</h2>

          <form class="row g-2 align-items-end mb-3" method="get" action="message.php">
            <input type="hidden" name="action" value="compose">
            <div class="col-sm-8">
              <label class="form-label">Find a resident</label>
              <input class="form-control" type="text" name="q" value="<?=h($search)?>" placeholder="Type a name to search…">
            </div>
            <div class="col-sm-4">
              <button class="btn btn-theme-outline w-100" type="submit">
                <i class="bi bi-search"></i> Search
              </button>
            </div>
          </form>

          <?php if ($search !== ''): ?>
            <div class="mb-3">
              <?php if (empty($candidates)): ?>
                <div class="text-muted">No residents found.</div>
              <?php else: ?>
                <div class="list-group">
                  <?php foreach ($candidates as $c):
                    $cid   = $c['PrincipalID'];
                    $cname = trim($c['FirstName'].' '.$c['LastName']);
                  ?>
                    <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                       href="message.php?action=compose&to=<?=h($cid)?>">
                      <span><?=h($cname)?></span>
                      <span class="badge bg-secondary"><?=h(substr($cid,0,8))?>…</span>
                    </a>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <form method="post" action="message.php?action=compose<?= $to ? '&to='.h($to) : '' ?>">
            <input type="hidden" name="send_message" value="1">
            <input type="hidden" name="to_uuid" value="<?=h($to)?>">

            <div class="mb-3">
              <label class="form-label">To</label>
              <input type="text" class="form-control" value="<?=h($toName ?: 'Select a resident above')?>" readonly>
            </div>

            <div class="mb-3">
              <label class="form-label">Subject</label>
              <input type="text" class="form-control" name="subject" maxlength="150"
                     value="<?=h($_POST['subject'] ?? '')?>">
            </div>

            <div class="mb-3">
              <label class="form-label">Message</label>
              <textarea class="form-control" name="body" rows="6"><?=h($_POST['body'] ?? '')?></textarea>
            </div>

            <button type="submit" class="btn btn-theme">
              <i class="bi bi-send"></i> Send Message
            </button>
          </form>
<?php
// ------------------------------------------------------------
// View single message
// ------------------------------------------------------------
} elseif ($action === 'view' && isset($_GET['id'])) {
    $id  = (int)$_GET['id'];
    $msg = null;

    if ($st = mysqli_prepare($con, "SELECT * FROM ws_messages WHERE id=? LIMIT 1")) {
        mysqli_stmt_bind_param($st, "i", $id);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        $msg = mysqli_fetch_assoc($res);
        mysqli_stmt_close($st);
    }

    if (!$msg) {
        ?>
          <div class="alert alert-danger mb-0">Message not found.</div>
        <?php
    } else {
        // Mark as read if we are the receiver
        if ($msg['receiver_uuid'] === $currentUserId) {
            mysqli_query($con, "UPDATE ws_messages SET is_read=1 WHERE id=".(int)$id);
        } elseif ($msg['sender_uuid'] !== $currentUserId) {
            ?>
              <div class="alert alert-danger mb-0">You don’t have permission to view that message.</div>
            <?php
        }

        $fromName = avatar_name($con, $msg['sender_uuid']);
        $toName   = avatar_name($con, $msg['receiver_uuid']);
        ?>
          <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <div>
              <h2 class="mb-1"><?=h($msg['subject'] ?: '(no subject)')?></h2>
              <div class="text-muted small">
                From: <?=h($fromName)?> · To: <?=h($toName)?> · <?=h($msg['created_at'])?>
              </div>
            </div>
            <div class="d-flex gap-2">
              <a class="btn btn-theme-outline"
                 href="message.php?action=compose&to=<?=h($msg['sender_uuid'])?>">
                <i class="bi bi-reply"></i> Reply
              </a>
              <a class="btn btn-theme-outline"
                 href="message.php?action=delete&id=<?=$msg['id']?>"
                 onclick="return confirm('Delete this message?');">
                <i class="bi bi-trash"></i> Delete
              </a>
            </div>
          </div>

          <div class="border rounded p-3" style="white-space:pre-wrap;"><?=h($msg['body'])?></div>
        <?php
    }

// ------------------------------------------------------------
// Inbox / Sent lists
// ------------------------------------------------------------
} else {
    $messages = [];

    if ($action === 'sent') {
        $sql = "SELECT id, receiver_uuid, subject, created_at
                FROM ws_messages
                WHERE sender_uuid=? AND sender_deleted=0
                ORDER BY created_at DESC
                LIMIT 200";
        if ($st = mysqli_prepare($con, $sql)) {
            mysqli_stmt_bind_param($st, "s", $currentUserId);
            mysqli_stmt_execute($st);
            $res = mysqli_stmt_get_result($st);
            while ($row = mysqli_fetch_assoc($res)) {
                $messages[] = $row;
            }
            mysqli_stmt_close($st);
        }
    } else {
        // inbox default
        $action = 'inbox';
        $sql = "SELECT id, sender_uuid, subject, created_at, is_read
                FROM ws_messages
                WHERE receiver_uuid=? AND receiver_deleted=0
                ORDER BY created_at DESC
                LIMIT 200";
        if ($st = mysqli_prepare($con, $sql)) {
            mysqli_stmt_bind_param($st, "s", $currentUserId);
            mysqli_stmt_execute($st);
            $res = mysqli_stmt_get_result($st);
            while ($row = mysqli_fetch_assoc($res)) {
                $messages[] = $row;
            }
            mysqli_stmt_close($st);
        }
    }
    ?>
          <h2 class="mb-3"><?= $action === 'sent' ? 'Sent Messages' : 'Inbox' ?></h2>

          <?php if (empty($messages)): ?>
            <div class="text-muted">No messages yet.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle">
                <thead>
                  <tr>
                    <th style="width:35%"><?= $action === 'sent' ? 'To' : 'From' ?></th>
                    <th>Subject</th>
                    <th style="width:20%">Date</th>
                    <th style="width:8%"></th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($messages as $m):
                    $otherUuid = $action === 'sent' ? $m['receiver_uuid'] : $m['sender_uuid'];
                    $otherName = avatar_name($con, $otherUuid);
                    $isUnread  = ($action !== 'sent' && empty($m['is_read']));
                ?>
                  <tr class="<?= $isUnread ? 'fw-semibold' : '' ?>">
                    <td><?=h($otherName)?></td>
                    <td>
                      <a href="message.php?action=view&id=<?= (int)$m['id'] ?>">
                        <?=h($m['subject'] ?: '(no subject)')?>
                      </a>
                    </td>
                    <td><?=h($m['created_at'])?></td>
                    <td class="text-end">
                      <a class="btn btn-sm btn-outline-danger"
                         href="message.php?action=delete&id=<?= (int)$m['id'] ?>"
                         onclick="return confirm('Delete this message?');">
                        <i class="bi bi-trash"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
    <?php
} // end action switch
?>
        </div> <!-- card-body -->
      </div> <!-- card -->
    </div> <!-- col-md-9 -->
  </div> <!-- row -->
</div> <!-- container -->

<?php include_once __DIR__ . "/include/footer.php"; ?>

