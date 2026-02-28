<?php
function debug($variable)
{
	echo '<pre>' . print_r($variable, true) . '</pre>';
}

function getUsernameByUUID($db, $uuid)
{
    $sql = $db->prepare("
        SELECT *
        FROM UserAccounts
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

function getPartnerStatusByNumber($number)
{
    if ($number == 0) return '<span class="label label-primary">Waiting</span>';
    else if ($number == 1) return '<span class="label label-success">Approved</span>';
    else if ($number == 2) return '<span class="label label-warning">Ignored</span>';
    return '<span class="label label-danger">Error</span>';
}

function sanitize($data)
{
   $data = trim($data);
   $data = stripslashes($data);
   $data = htmlspecialchars($data);
   return $data;
}
?>
