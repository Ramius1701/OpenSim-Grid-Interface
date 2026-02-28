<?php
// Set the timezone to Pacific Standard Time (PST)
date_default_timezone_set('America/Los_Angeles');  // PST is typically 'America/Los_Angeles'

// Get the current time in 12-hour format with AM/PM
$gridTime = date('h:i A');  // 'h:i A' formats the time as 12-hour format with AM/PM

// Online / Offline with socket
$socket = @fsockopen($robustIP, $robustPORT, $errno, $errstr, 1);
$online = is_resource($socket);
@fclose($socket);

// General counts using a single query to reduce redundancy
$sql = $db->prepare("
    SELECT
        (SELECT COUNT(PrincipalID) FROM UserAccounts) AS userscounter,
        (SELECT COUNT(UUID) FROM land) AS landscounter,
        (SELECT COUNT(uuid) FROM regions WHERE regionName NOT LIKE 'http%') AS regionscounter,
        (SELECT COUNT(UserID) FROM GridUser WHERE Login > UNIX_TIMESTAMP(NOW() - INTERVAL 1 DAY)) AS lastdayscounter,
        (SELECT COUNT(UserID) FROM GridUser WHERE Login > UNIX_TIMESTAMP(NOW() - INTERVAL 30 DAY)) AS lastmonthscounter,
        (SELECT COUNT(UserID) FROM Presence) AS nowonlinescounter,
        (SELECT COUNT(UserID) FROM GridUser WHERE UserID LIKE '%http%' AND Online = 'TRUE') AS hguserscounter,
        (SELECT COUNT(itemID) FROM primitems) AS objectscounter,
        (SELECT COUNT(UUID) FROM prims) AS primscounter,
        (SELECT COUNT(id) FROM fsassets) AS assetscounter,
        (SELECT COUNT(DISTINCT UserID) FROM GridUser WHERE UserID NOT LIKE '%http%' AND Login < FROM_UNIXTIME(UNIX_TIMESTAMP(NOW() - INTERVAL 30 DAY))) AS localpastmonth,
        (SELECT COUNT(DISTINCT UserID) FROM GridUser WHERE UserID LIKE '%http%' AND Login < FROM_UNIXTIME(UNIX_TIMESTAMP(NOW() - INTERVAL 30 DAY))) AS hgpastmonth
");
$sql->execute();
$results = $sql->fetch(PDO::FETCH_ASSOC);

// Assign results to variables
$userscounter = $results['userscounter'];
$landscounter = $results['landscounter'];
$regionscounter = $results['regionscounter'];
$lastdayscounter = $results['lastdayscounter'];
$lastmonthscounter = $results['lastmonthscounter'];
$nowonlinescounter = $results['nowonlinescounter'];
$hguserscounter = $results['hguserscounter'];
$objectscounter = $results['objectscounter'];
$primscounter = $results['primscounter'];
$assetscounter = $results['assetscounter'];
$pastmonth = $results['localpastmonth'];
$preshguser = $results['hgpastmonth'];

// Time calculations
$monthago = time() - 2592000; // 30 days ago

// Initialize statistics for new calculations
$totalregions = $totalvarregions = $totalsingleregions = $totalsize = $avatardensity = 0;

// Calculate statistics for regions and sizes
$regiondb = $db->query("SELECT sizeX, sizeY FROM regions");
while ($regions = $regiondb->fetch(PDO::FETCH_ASSOC)) {
    ++$totalregions;
    if ($regions['sizeX'] == 256) {
        ++$totalsingleregions;
    } else {
        ++$totalvarregions;
    }
    $rsize = $regions['sizeX'] * $regions['sizeY'];
    $totalsize += $rsize / 1000;
}

// Calculate avatar density
$avatardensity = $totalsingleregions > 0 ? $nowonlinescounter / $totalsingleregions : 0;

// Prepare response data
$arr = [
    'Avatar_Density_Now' => number_format($avatardensity, 2),
    'Online_Now' => number_format($nowonlinescounter),
    'HG_Visitors_Last_30_Days' => number_format($preshguser),
    'Local_Users_Last_30_Days' => number_format($pastmonth),
    'Total_Active_Last_30_Days' => number_format($pastmonth + $preshguser),
];
?>

<div class="panel panel-default <?php echo $class; ?>">
    <div class="panel-heading">
        <h3 class="panel-title">
            <i class="glyphicon glyphicon-stats"></i>
            <strong>Grid Status</strong>
            <?php if ($online): ?>
                <span class='label label-success pull-right'><strong>ONLINE <i class='glyphicon glyphicon-ok'></i></strong></span>
            <?php else: ?>
                <span class='label label-danger pull-right'><strong>OFFLINE <i class='glyphicon glyphicon-remove'></i></strong></span>
            <?php endif; ?>
        </h3>
    </div>

    <ul class="nav nav-pills nav-justified">
        <li class="active"><a data-toggle="pill" href="#users">Users</a></li>
        <li><a data-toggle="pill" href="#regions">Regions</a></li>
    </ul>

    <div class="tab-content">
        <div id="users" class="list-group tab-pane fade in active no-margin">
            <li class="list-group-item list-group-item-default">Grid Version<span class="badge">0.9.3.1 (Build 789)</span></li>
            <li class="list-group-item list-group-item-default">Grid Time<span class="badge"><?php echo $gridTime; ?></span></li>  <!-- Displaying Grid Time here -->
            <li class="list-group-item list-group-item-default">Total Users<span class="badge"><?php echo $userscounter; ?></span></li>
            <li class="list-group-item list-group-item-default">Total Users Online<span class="badge"><?php echo $nowonlinescounter; ?></span></li>
            <!--
            <li class="list-group-item list-group-item-default">HyperGrid Users Online<span class="badge"><?php echo $hguserscounter; ?></span></li>
            -->
            <li class="list-group-item list-group-item-default">Unique Visitors (24 hours)<span class="badge"><?php echo $lastdayscounter; ?></span></li>
            <li class="list-group-item list-group-item-default">HG Visitors Last (30 Days)<span class="badge"><?php echo $arr['HG_Visitors_Last_30_Days']; ?></span></li>
            <li class="list-group-item list-group-item-default">Local Users Last (30 Days)<span class="badge"><?php echo $arr['Local_Users_Last_30_Days']; ?></span></li>
            <li class="list-group-item list-group-item-default">Total Active Last (30 Days)<span class="badge"><?php echo $arr['Total_Active_Last_30_Days']; ?></span></li>
            <!--
            <li class="list-group-item list-group-item-default">Avatar Density Now<span class="badge"><?php echo $arr['Avatar_Density_Now']; ?></span></li>
            -->
        </div>

        <div id="regions" class="list-group tab-pane fade no-margin">
            <li class="list-group-item list-group-item-default">Total Regions<span class="badge"><?php echo $landscounter; ?></span></li>
            <li class="list-group-item list-group-item-default">Regions Online<span class="badge"><?php echo $regionscounter; ?></span></li>
            <?php if ($regionscounter > 0): ?>
                <li class="list-group-item list-group-item-default">Single Regions<span class="badge"><?php echo $totalsingleregions; ?></span></li>
                <li class="list-group-item list-group-item-default">Var Regions<span class="badge"><?php echo $totalvarregions; ?></span></li>
            <?php endif; ?>
            <li class="list-group-item list-group-item-default">Total Area (km²)<span class="badge"><?php echo number_format($totalsize, 2); ?></span></li>
            <li class="list-group-item list-group-item-default">Total Prims<span class="badge"><?php echo $primscounter; ?></span></li>
            <li class="list-group-item list-group-item-default">Total Objects<span class="badge"><?php echo $objectscounter; ?></span></li>
            <li class="list-group-item list-group-item-default">Total Assets<span class="badge"><?php echo $assetscounter; ?></span></li>
        </div>

    </div>
</div>
