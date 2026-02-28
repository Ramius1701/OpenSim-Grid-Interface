<?php
$title = "Analytics Service";
include_once 'include/config.php';

$servername = DB_SERVER;
$username = DB_USERNAME;
$password = DB_PASSWORD;
$dbname = DB_NAME;

// Verbindung herstellen
$conn = new mysqli($servername, $username, $password, $dbname);

// Verbindung überprüfen
if ($conn->connect_error) {
    die("Verbindung zur Datenbank fehlgeschlagen: " . $conn->connect_error);
}

// Abfrage für die Anzahl der Benutzer
$sql = "SELECT COUNT(*) as total_users FROM UserAccounts";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$totalUsers = $row['total_users'];

// Abfrage für die Anzahl der Regionen
$sql = "SELECT COUNT(*) as total_regions FROM regions";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$totalRegions = $row['total_regions'];

// Abfrage für die Anzahl der Online-Benutzer
$sql = "SELECT COUNT(*) as online_users FROM Presence WHERE LastSeen > NOW() - INTERVAL 5 MINUTE";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$onlineUsers = $row['online_users'];

// Abfrage für die letzten Transaktionen
$sql = "SELECT sender, receiver, amount, time FROM transactions ORDER BY time DESC LIMIT 5";
$result = $conn->query($sql);
$recentTransactions = [];
while ($row = $result->fetch_assoc()) {
    $recentTransactions[] = $row;
}

// Abfrage für die letzten Benutzer
$sql = "SELECT PrincipalID, FirstName, LastName, Created FROM UserAccounts ORDER BY Created DESC LIMIT 5";
$result = $conn->query($sql);
$recentUsers = [];
while ($row = $result->fetch_assoc()) {
    $recentUsers[] = $row;
}

// Abfrage für die Gesamtsumme der Transaktionen
$sql = "SELECT SUM(amount) as total_amount FROM transactions";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$totalTransactions = $row['total_amount'];

// Abfrage für die Regionen mit den meisten Besuchern
$sql = "SELECT RegionID, COUNT(*) as visit_count FROM Presence GROUP BY RegionID ORDER BY visit_count DESC LIMIT 5";
$result = $conn->query($sql);
$topRegions = [];
while ($row = $result->fetch_assoc()) {
    $topRegions[] = $row;
}

// Abfrage für die letzten Benutzeraktivitäten
$sql = "SELECT UserID, LastSeen FROM Presence ORDER BY LastSeen DESC LIMIT 5";
$result = $conn->query($sql);
$recentActivities = [];
while ($row = $result->fetch_assoc()) {
    $recentActivities[] = $row;
}

// Verbindung schließen
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
<title><?php echo SITE_NAME; ?> Analytics</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Raleway">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<style>
html,body,h1,h2,h3,h4,h5 {font-family: "Raleway", sans-serif}
</style>
</head>
<body class="w3-light-grey">

<!-- Top container -->
<div class="w3-bar w3-top w3-black w3-large" style="z-index:4">
  <button class="w3-bar-item w3-button w3-hide-large w3-hover-none w3-hover-text-light-grey" onclick="w3_open();"><i class="fa fa-bars"></i>  Menu</button>
  <span class="w3-bar-item w3-right"><?php echo SITE_NAME; ?> Analytics</span>
</div>

<!-- Sidebar/menu -->
<nav class="w3-sidebar w3-collapse w3-white w3-animate-left" style="z-index:3;width:300px;" id="mySidebar"><br>
  <div class="w3-container w3-row">
    <div class="w3-col s4">
      <class="fa fa-user w3-xxxlarge w3-circle w3-margin-right" style="width:46px">
    </div>
    <div class="w3-col s8 w3-bar">
      <span>Welcome, <strong>Admin</strong></span><br>
      <a href="#" class="w3-bar-item w3-button"><i class="fa fa-envelope"></i></a>
      <a href="#" class="w3-bar-item w3-button"><i class="fa fa-user"></i></a>
      <a href="#" class="w3-bar-item w3-button"><i class="fa fa-cog"></i></a>
    </div>
  </div>
  <hr>
  <div class="w3-container">
    <h5>Dashboard</h5>
  </div>
  <div class="w3-col s4">
    <a href="#" class="w3-bar-item w3-button w3-padding-16 w3-hide-large w3-dark-grey w3-hover-black" onclick="w3_close()" title="close menu"><i class="fa fa-remove fa-fw"></i>  Close Menu</a>
    <a href="#" class="w3-bar-item w3-button w3-padding w3-blue"><i class="fa fa-users fa-fw"></i>  Overview</a>
    <!-- todo: Regionsliste mit Link hier $regionslink = "secondlife://$region/128/128/23"; -->
    <br><br>
            <?php
            $con = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
            $sql = "SELECT regionName, serverIP, serverPort FROM regions ORDER BY last_seen DESC LIMIT 10";
            $resultregions = mysqli_query($con, $sql);

            while ($dsatz = mysqli_fetch_assoc($resultregions)) {
                $region = htmlspecialchars($dsatz["regionName"]);
                $ip = htmlspecialchars($dsatz["serverIP"]);
                $port = htmlspecialchars($dsatz["serverPort"]);

                // Standard-Koordinaten für den Teleport-Link (X=128, Y=128, Z=23)
                // Alle Möglichkeiten:
                $regionslink1 = "$region";
                $regionslink2 = "hop://openmanniland.de:8002/$region/128/128/23";
                $regionslink3 = "secondlife://http|!!openmanniland.de|8002+$region";
                $regionslink4 = "secondlife://openmanniland.de:8002/$region/128/128/23";
                $regionslink5 = "secondlife://$region/128/128/23"; // Dies funktioniert beim Firestorm und beim Firefox.
                $regionslink6 = "openmanniland.de:8002:$region";
                
                $regionslink = $regionslink5;

                echo "<br><a class='region-link' href='$regionslink' target='_blank'><i class='fa fa-globe' style='font-size:24px'></i>$region</a><br>";  // <br> fas fa-globe
            }

            mysqli_close($con);
            ?>
  </div>
</nav>

<!-- Overlay effect when opening sidebar on small screens -->
<div class="w3-overlay w3-hide-large w3-animate-opacity" onclick="w3_close()" style="cursor:pointer" title="close side menu" id="myOverlay"></div>

<!-- !PAGE CONTENT! -->
<div class="w3-main" style="margin-left:300px;margin-top:43px;">

  <!-- Header -->
  <header class="w3-container" style="padding-top:22px">
    <h5><b><i class="fa fa-dashboard"></i> My Dashboard</b></h5>
  </header>

  <div class="w3-row-padding w3-margin-bottom">
    <div class="w3-quarter">
      <div class="w3-container w3-red w3-padding-16">
        <div class="w3-left"><i class="fa fa-users w3-xxxlarge"></i></div>
        <div class="w3-right">
          <h3><?php echo $totalUsers; ?></h3>
        </div>
        <div class="w3-clear"></div>
        <h4>Total Users</h4>
      </div>
    </div>
    <div class="w3-quarter">
      <div class="w3-container w3-blue w3-padding-16">
        <div class="w3-left"><i class="fa fa-globe w3-xxxlarge"></i></div>
        <div class="w3-right">
          <h3><?php echo $totalRegions; ?></h3>
        </div>
        <div class="w3-clear"></div>
        <h4>Total Regions</h4>
      </div>
    </div>
    <div class="w3-quarter">
      <div class="w3-container w3-teal w3-padding-16">
        <div class="w3-left"><i class="fa fa-user w3-xxxlarge"></i></div>
        <div class="w3-right">
          <h3><?php echo $onlineUsers; ?></h3>
        </div>
        <div class="w3-clear"></div>
        <h4>Online Users</h4>
      </div>
    </div>
    <div class="w3-quarter">
      <div class="w3-container w3-orange w3-text-white w3-padding-16">
        <div class="w3-left"><i class="fa fa-money w3-xxxlarge"></i></div>
        <div class="w3-right">
          <h3><?php echo $totalTransactions; ?></h3>
        </div>
        <div class="w3-clear"></div>
        <h4>Total Transactions</h4>
      </div>
    </div>
  </div>

  <!-- Recent Transactions -->
  <div class="w3-container">
    <h5>Recent Transactions</h5>
    <table class="w3-table w3-striped w3-white">
      <?php foreach ($recentTransactions as $transaction): ?>
      <tr>
        <td><i class="fa fa-exchange w3-text-blue w3-large"></i></td>
        <td><?php echo $transaction['sender']; ?> → <?php echo $transaction['receiver']; ?></td>
        <td><?php echo $transaction['amount']; ?></td>
        <td><i><?php echo date('Y-m-d H:i:s', $transaction['time']); ?></i></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <!-- Recent Users -->
  <div class="w3-container">
    <h5>Recent Users</h5>
    <ul class="w3-table w3-white">
      <?php foreach ($recentUsers as $user): ?>
      <li class="w3-table w3-striped w3-white">
        <i class="fa fa-user fa-fw w3-xlarge"></i><class="w3-left w3-circle w3-margin-right" style="width:35px">
        <span class="w3-xlarge"><?php echo $user['FirstName'] . ' ' . $user['LastName']; ?></span><br>
        <span class="w3-opacity"><?php echo date('Y-m-d H:i:s', $user['Created']); ?></span>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <p>New Visitors</p>
    <div class="w3-grey">
      <div class="w3-container w3-center w3-padding w3-green" style="width:25%">+25%</div>
    </div>

    <p>New Users</p>
    <div class="w3-grey">
      <div class="w3-container w3-center w3-padding w3-orange" style="width:50%">50%</div>
    </div>

    <p>Bounce Rate</p>
    <div class="w3-grey">
      <div class="w3-container w3-center w3-padding w3-red" style="width:45%">45%</div>
    </div>

  <!-- Top Regions -->
  <div class="w3-container">
    <h5>Top Regions by Visitors</h5>
    <table class="w3-table w3-striped w3-white">
      <?php foreach ($topRegions as $region): ?>
      <tr>
        <td><i class="fa fa-globe w3-text-green w3-large"></i></td>
        <td><?php echo $region['RegionID']; ?></td>
        <td><?php echo $region['visit_count']; ?> visits</td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <!-- Recent Activities -->
  <div class="w3-container">
    <h5>Recent User Activities</h5>
    <table class="w3-table w3-striped w3-white">
      <?php foreach ($recentActivities as $activity): ?>
      <tr>
        <td><i class="fa fa-user w3-text-blue w3-large"></i></td>
        <td><?php echo $activity['UserID']; ?></td>
        <td><?php echo date('Y-m-d H:i:s', strtotime($activity['LastSeen'])); ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <!-- End page content -->
</div>

<script>
// JavaScript für die Sidebar
var mySidebar = document.getElementById("mySidebar");
var overlayBg = document.getElementById("myOverlay");

function w3_open() {
  if (mySidebar.style.display === 'block') {
    mySidebar.style.display = 'none';
    overlayBg.style.display = "none";
  } else {
    mySidebar.style.display = 'block';
    overlayBg.style.display = "block";
  }
}

function w3_close() {
  mySidebar.style.display = "none";
  overlayBg.style.display = "none";
}
</script>

</body>
</html>