<h1><?php echo $oswhereavatars; ?><span class="pull-right">Home<span></h1>

<?php
$query = $db->prepare('
    SELECT RegionID, UserID, COUNT(RegionID)
    FROM '.$tbname.'
    GROUP BY RegionID
    ORDER BY COUNT(RegionID) DESC
');

$query->execute();
$presence_counter = $query->rowCount();

if ($presence_counter == 0)
{
    echo 'There is currently <span class="badge">0</span> populated region ...';
}

else
{
    echo '<div class="table-responsive">';
    echo '<table class="table table-hover table-responsive">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>#</th>';
    echo '<th>Region</th>';
    echo '<th>Online</th>';
    echo '<th class="text-right">Teleports</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    $i = 0;

    while ($row = $query->fetch(PDO::FETCH_ASSOC))
    {
        $UserID = $row['UserID'];
        $RegionID = $row['RegionID'];
        $RegionNB = $row['COUNT(RegionID)'];

        $sql = $db->prepare("
            SELECT regionName
            FROM regions
            WHERE uuid = '".$RegionID."'
        ");

        $sql->execute();
        $region_counter = $sql->rowCount();

        if ($region_counter == 0)
        {
            echo '<p class="alert alert-danger alert-anim">0 region found ...</p>';
            exit;
        }

        while ($row = $sql->fetch(PDO::FETCH_ASSOC))
        {
            if (!empty($row['regionName']))
                $regionName = $row['regionName'];
            else $regionName = 'Unknow Region Name';

            echo '<tr>';
            echo '<td><span class="badge">'.++$i.'</span></td>';
            echo '<td>'.$regionName.'</td>';
            echo '<td><span class="badge">'.$RegionNB.'</span> <i class="glyphicon glyphicon-user"></i> Avatar(s)</td>';
            echo '<td class="text-right">';
            echo '<a class="btn btn-primary btn-xs" href="secondlife://'.$regionName.'/128/128/128">';
            echo '<i class="glyphicon glyphicon-plane"></i> Local</a> ';
            echo '<a class="btn btn-info btn-xs" href="secondlife://'.$robustHOST.':'.$robustPORT.'/'.$regionName.'/128/128/128">';
            echo '<i class="glyphicon glyphicon-plane"></i> HG</a> ';
            echo '<a class="btn btn-warning btn-xs" href="secondlife://http|!!'.$robustHOST.'|'.$robustPORT.'+'.$regionName.'">';
            echo '<i class="glyphicon glyphicon-plane"></i> HG V3</a> ';
            echo '<a class="btn btn-danger btn-xs" href="hop://'.$robustHOST.':'.$robustPORT.'/'.$regionName.'/128/128/128">';
            echo '<i class="glyphicon glyphicon-plane"></i> Hop</a> ';
            echo '</td>';
            echo '</tr>';
        }

        unset($UserID);
        unset($RegionID);
        unset($RegionNB);
        unset($presence_counter);
        unset($region_counter);
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}
$query = null;
$sql = null;
?>