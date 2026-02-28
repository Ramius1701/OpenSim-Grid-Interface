<?php
// economy_api.php — JSON API for economy.php ("Send Money" form)
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

require_once __DIR__ . '/../include/config.php';

header('Content-Type: application/json; charset=utf-8');

if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
$con = @mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$con) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

function e_respond(bool $success, string $message, array $extra = []): void {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (empty($_SESSION['user']['principal_id'])) {
    e_respond(false, 'You must be logged in to send money.');
}
$currentUserId = $_SESSION['user']['principal_id'];
$currentUserName = $_SESSION['user']['name'] ?? '';

// UUIDv4 for transactions / tokens
function uuidv4(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

// Resolve avatar by UUID or "First Last"
function econ_resolve_avatar(mysqli $con, string $input): ?array {
    $input = trim($input);
    if (strlen($input) === 36 && strpos($input, '-') !== false) {
        $uuid = mysqli_real_escape_string($con, $input);
        $res = mysqli_query(
            $con,
            "SELECT PrincipalID, FirstName, LastName
             FROM UserAccounts
             WHERE PrincipalID = '$uuid'
             LIMIT 1"
        );
        if ($res && $row = mysqli_fetch_assoc($res)) {
            return $row;
        }
        return null;
    }

    $parts = preg_split('/\s+/', $input);
    if (count($parts) < 2) {
        return null;
    }
    $first = mysqli_real_escape_string($con, $parts[0]);
    $last  = mysqli_real_escape_string($con, $parts[1]);

    $res = mysqli_query(
        $con,
        "SELECT PrincipalID, FirstName, LastName
         FROM UserAccounts
         WHERE FirstName = '$first' AND LastName = '$last'
         LIMIT 1"
    );
    if ($res && $row = mysqli_fetch_assoc($res)) {
        return $row;
    }
    return null;
}

// Get numeric balance (balances.user)
function get_balance(mysqli $con, string $userId): int {
    $uid = mysqli_real_escape_string($con, $userId);
    $res = mysqli_query($con, "SELECT balance FROM balances WHERE user = '$uid' LIMIT 1");
    if ($res && $row = mysqli_fetch_assoc($res)) {
        return (int)$row['balance'];
    }
    return 0;
}

// Update balance by delta (creates row if none)
function update_balance(mysqli $con, string $userId, int $delta): int {
    $uid = mysqli_real_escape_string($con, $userId);
    mysqli_query(
        $con,
        "INSERT INTO balances (user, balance, status, type)
         VALUES ('$uid', 0, NULL, 0)
         ON DUPLICATE KEY UPDATE balance = balance"
    );
    mysqli_query(
        $con,
        "UPDATE balances
         SET balance = balance + $delta
         WHERE user = '$uid'"
    );
    return get_balance($con, $userId);
}

// Parse JSON / fallback
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST ?? [];
}

$action      = $data['action']      ?? '';
$recipientIn = $data['recipient']   ?? '';
$amountIn    = $data['amount']      ?? '';
$descIn      = $data['description'] ?? '';

if ($action !== 'send_money') {
    e_respond(false, 'Unknown action.');
}

$amount = (int)$amountIn;
if ($amount <= 0) {
    e_respond(false, 'Amount must be greater than zero.');
}

$recipient = econ_resolve_avatar($con, (string)$recipientIn);
if (!$recipient) {
    e_respond(false, 'Recipient not found.');
}
$receiverId   = $recipient['PrincipalID'];
$receiverName = $recipient['FirstName'] . ' ' . $recipient['LastName'];

if ($receiverId === $currentUserId) {
    e_respond(false, 'You cannot send money to yourself.');
}

// Check sender balance
$senderBalance = get_balance($con, $currentUserId);
if ($senderBalance < $amount) {
    e_respond(false, 'Insufficient funds.');
}

$description = trim((string)$descIn);
if ($description === '') {
    $description = 'Web transfer';
}
if (strlen($description) > 255) {
    $description = substr($description, 0, 255);
}

mysqli_begin_transaction($con);

try {
    // Deduct & credit
    $newSenderBalance   = update_balance($con, $currentUserId, -$amount);
    $newReceiverBalance = update_balance($con, $receiverId, $amount);

    $txId     = uuidv4();
    $secureId = uuidv4();

    $senderEsc   = mysqli_real_escape_string($con, $currentUserId);
    $receiverEsc = mysqli_real_escape_string($con, $receiverId);
    $descEsc     = mysqli_real_escape_string($con, $description);

    // Insert into transactions table
    mysqli_query(
        $con,
        "INSERT INTO transactions
         (UUID, sender, receiver, amount, senderBalance, receiverBalance,
          objectUUID, objectName, regionHandle, regionUUID, type, time,
          secure, status, commonName, description)
         VALUES
         ('$txId',
          '$senderEsc',
          '$receiverEsc',
          $amount,
          $newSenderBalance,
          $newReceiverBalance,
          '',
          'Web Transfer',
          '0',
          '00000000-0000-0000-0000-000000000000',
          1000,
          " . time() . ",
          '$secureId',
          1,
          'WebTransfer',
          '$descEsc')"
    );

    mysqli_commit($con);

    e_respond(true, "Sent L$ {$amount} to {$receiverName}.", [
        'sender_balance'   => $newSenderBalance,
        'receiver_balance' => $newReceiverBalance
    ]);
} catch (Throwable $e) {
    mysqli_rollback($con);
    e_respond(false, 'Transaction failed: ' . $e->getMessage());
}
