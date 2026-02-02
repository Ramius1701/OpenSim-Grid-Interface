<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$title = "AvatarPicker Service";
include_once 'include/header.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Connect to the database using the shared db() helper
$conn = db();

// Check connection
if (!$conn) {
    die("Database connection failed.");
}

$vorname = $nachname = '';
$inventory = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input_password = $_POST['input_password'] ?? '';
    $vorname = $_POST['vorname'] ?? '';
    $nachname = $_POST['nachname'] ?? '';

    // Check the password
    if (in_array($input_password, $registration_passwords_avatarpicker)) {
        $_SESSION['authenticated'] = true;

        // If authenticated, get inventory
        $inventory = listinventar($conn, $vorname, $nachname);
    } else {
        $error_message = "Incorrect password. Please try again.";
        $_SESSION['authenticated'] = false;
    }
} else {
    // User is not authenticated
    $show_login_form = true;
}

// Fetch the user inventory
function listinventar($conn, $vorname, $nachname) {
    $vorname = $conn->real_escape_string(strip_tags($vorname));
    $nachname = $conn->real_escape_string(strip_tags($nachname));
    $query = "SELECT PrincipalID FROM UserAccounts WHERE FirstName='$vorname' AND LastName='$nachname'";
    $result = $conn->query($query);

    if ($result->num_rows == 0) {
        echo "User not found.\n";
        return [];
    }

    $row = $result->fetch_assoc();
    $user_uuid = $row['PrincipalID'];

    // Query the "Outfits" folders
    $query = "SELECT folderID, folderName FROM inventoryfolders WHERE agentID='$user_uuid' AND type=47 ORDER BY folderName ASC, agentID ASC";
    $result = $conn->query($query);
    $inventory = [];

    while ($row = $result->fetch_assoc()) {
        $inventory[] = [
            'folderID' => $row['folderID'],
            'folderName' => $row['folderName']
        ];
    }

    if (empty($inventory)) {
        echo "No outfits found.\n";
    }

    return $inventory;
}

// Get an image by outfit name
function getImageByName($dir, $name) {
    foreach (glob($dir."*.jpg") as $filename) {
        $file = pathinfo($filename, PATHINFO_FILENAME);
        if ($file === $name) {
            return $dir.$file.".jpg";
        }
    }
    return $dir."default.jpg";
}
?>

<style>
  /* Page-scoped styles only — no body/html overrides */
  .avatarpicker-page .avatarpicker-card{
    max-width: 680px;
    margin: 0 auto;
  }
  .avatarpicker-page .inventory-list{
    display:flex;
    flex-wrap:wrap;
    gap:12px;
    justify-content:center;
    margin-top:1rem;
  }
  .avatarpicker-page .outfit-container{
    text-align:center;
    width:120px;
  }
  .avatarpicker-page .outfit-container p{
    margin-top:.5rem;
    font-size:.9rem;
  }
  .avatarpicker-page .img-thumbnail{
    max-width:100px;
    height:auto;
  }
</style>

<div class="container-fluid mt-4 mb-4 avatarpicker-page">
  <div class="row">
    <div class="col-md-8 col-lg-6 mx-auto">
      <div class="card p-3 p-md-4 shadow-sm avatarpicker-card">
    <h1 class="text-center mb-3"><i class="bi bi-person-badge me-2"></i> AvatarPicker</h1>

    <form method="POST" action="">
      <label for="vorname" class="form-label">First name</label>
      <input type="text" id="vorname" name="vorname" required class="form-control mb-3"
             value="<?= htmlspecialchars($vorname) ?>">

      <label for="nachname" class="form-label">Last name</label>
      <input type="text" id="nachname" name="nachname" required class="form-control mb-3"
             value="<?= htmlspecialchars($nachname) ?>">

      <label for="input_password" class="form-label">Password</label>
      <input type="password" id="input_password" name="input_password" required class="form-control mb-3">

      <button type="submit" class="btn btn-primary w-100">Show outfits</button>
    </form>

    <?php if (isset($error_message)): ?>
      <div class="alert alert-danger mt-3 mb-0">
        <?= htmlspecialchars($error_message) ?>
      </div>
    <?php endif; ?>

    <?php if (($_SESSION['authenticated'] ?? false) && !empty($inventory)): ?>
      <h2 class="mt-4 text-center">
        Outfits for <?= htmlspecialchars($vorname) ?> <?= htmlspecialchars($nachname) ?>
      </h2>

      <div class="inventory-list">
        <?php foreach ($inventory as $item): ?>
          <div class="outfit-container">
            <a href="secondlife:///app/wear_folder/?folder_id=<?= htmlspecialchars($item['folderID']) ?>"
               target="_self">
              <img class="img-thumbnail"
                   src="<?= htmlspecialchars(getImageByName(AVATARPICKER_DIR, $item['folderName'])) ?>"
                   alt="<?= htmlspecialchars($item['folderName']) ?>">
              <p><?= htmlspecialchars($item['folderName']) ?></p>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

      </div>
    </div>
  </div>
</div>

<?php include_once "include/" . FOOTER_FILE; ?>
