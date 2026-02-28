<?php
function debug($variable)
{
    echo '<pre>' . print_r($variable, true) . '</pre>';
}

function getUsernameByUUID($db, $uuid)
{
    $sql = $db->prepare("
        SELECT *
        FROM useraccounts
        WHERE PrincipalID = '".$uuid."'
    ");

    $sql->execute();
    $rows = $sql->rowCount();

    if ($rows <> 0)
    {
        while ($row = $sql->fetch(PDO::FETCH_ASSOC))
        {
            $firstname = $row['FirstName'];
            $lastname = $row['LastName'];
            $PrincipalID = $row['PrincipalID'];
        }
        return $firstname.' '.$lastname;
    }
}

function getFlagNameByNumber($number)
{
    // 0 = Fried request 
    // OR (if uuid = principalid)
    // 0 = Friend request accepted
    // 1 see if connected
    // 3 see on map
    // 4 move/take objects
    // 7 full
    if ($number == 0)
        $Flags = '<i data-toggle="tooltip" class="glyphicon glyphicon-ban-circle text-danger" title="No right"></i>';
    else if ($number == 1)
        $Flags = '<i data-toggle="tooltip" class="glyphicon glyphicon-eye-open text-success" title="See if connected"></i>';
    else if ($number == 3)
        $Flags = '<i data-toggle="tooltip" class="glyphicon glyphicon-globe text-success" title="See on map"></i>';
    else if ($number == 4)
        $Flags = '<i data-toggle="tooltip" class="glyphicon glyphicon-gift text-success" title="Move/Take objects"></i>';
    else if ($number == 7)
        $Flags = '<i data-toggle="tooltip" class="glyphicon glyphicon-education text-danger" title="Full right"></i>';
    else $number = '<i data-toggle="tooltip" class="glyphicon glyphicon-question-sign text-danger" title="Unknow flag"></i>';
    return $Flags;
}
?>
