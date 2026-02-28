<?php if (isset($_SESSION['valid'])): ?>
    <h1><?php echo $ospartner; ?><span class="pull-right">Home</span></h1>
<?php endif; ?>

<!-- Flash Message -->
<?php if(isset($_SESSION['flash'])): ?>
    <?php foreach($_SESSION['flash'] as $type => $message): ?>
        <div class="alert alert-<?php echo $type; ?> alert-anim">
            <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
            <?php echo $message; ?>
        </div>
    <?php endforeach; ?>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<!-- Login Form -->
<?php if (!isset($_SESSION['valid'])): ?>
<form class="form-signin" role="form" action="?login" method="post" >
<h2 class="form-signin-heading">Please login</h2>
    <label for="username" class="sr-only">User name</label>
    <input type="text" name="username" class="form-control" placeholder="Username" required autofocus>
    <label for="password" class="sr-only">Password</label>
    <input type="password" name="password" class="form-control" placeholder="Password" required>
    <div class="checkbox">
        <label>
            <input type="checkbox" value="remember-me"> Remember me
        </label>
    </div>        
    <button class="btn btn-lg btn-primary btn-block" type="submit" name="login">
        <i class="glyphicon glyphicon-log-in"></i> Log-in
    </button>
</form>
<?php endif; ?>

<?php 
if (isset($_SESSION['valid']))
{
    if (isset($_POST['sendPartnerRequest']))
    {
        if (!empty($_POST['sendPartnerRequest']))
        {
            if ($_POST['sendPartnerRequest'] <> $_SESSION['useruuid'])
            {
                if (isset($_POST['sendPartnerRequest']))
                {
                    if (empty($_POST["comment"]))
                        $comment = "No comment.";
                    else $comment = $_POST["comment"];
                }

                // DISPLAY PARTNER
                $sql = $db->prepare("
                    SELECT *
                    FROM ".$tbname."
                ");

                $sql->execute();
                $rows = $sql->rowCount();

                if ($rows >= 0)
                {
                    // INSERT INTO PARTNER DATABASE
                    $sql = $db->prepare("
                        INSERT INTO ".$tbname." (
                            profileUuid, 
                            profilePartner, 
                            profilePartnerText, 
                            profilePartnerStatus
                        )
                        VALUES (
                            '".$_POST['sendPartnerRequest']."', 
                            '".$_SESSION['useruuid']."', 
                            '".$comment."', 
                            '0'
                        )
                    ");
                    $sql->execute();

                    echo '<div class="alert alert-success">';
                    echo '<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>';
                    echo '<i class="glyphicon glyphicon-ok"></i> ';
                    echo 'Your partner request was sent successfully to '.getUsernamebyUUID($db, $_POST['sendPartnerRequest']).'. ';
                    echo 'Now you need to wait for approval.';
                    echo '</div>';
                }

                else
                {
                    echo '<div class="alert alert-danger alert-anim">';
                    echo '<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>';
                    echo getUsernamebyUUID($db, $_POST['sendPartnerRequest']).' has already got a partner.';
                    echo '</div>';
                }
            }

            else
            {
                echo '<div class="alert alert-danger alert-anim">';
                echo '<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>';
                echo 'You cannot partner with youself.';
                echo '</div>';
            }
        }

        else
        {
            echo '<div class="alert alert-danger alert-anim">';
            echo '<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>';
            echo 'Please select a partner.';
            echo '</div>';
        }
    }

    else if (isset($_POST['addNewPartner']))
    {
        if (empty($_POST['addNewPartner']))
        {
            $sql = $db->prepare("
                SELECT *
                FROM UserAccounts
            ");
            $sql->execute();

            echo '<div class="form-group col-xs-4">';
            echo '<form class="form form-group" action="" method="post">';
            echo '<label for="sel1">Members list:</label>';
            echo '<select class="form-control" id="sel1" name="sendPartnerRequest">';
            echo '<option value="" selected >Select a user</option>';

            while ($row = $sql->fetch(PDO::FETCH_ASSOC))
            {
                $PrincipalID = $row['PrincipalID'];

                if ($PrincipalID <> $_SESSION['useruuid'])
                    echo '<option value="'.$PrincipalID.'">'.getUsernamebyUUID($db, $PrincipalID).'</option>';
            }

            echo '</select> ';

            echo '<div class="form-group">';
            echo '<label for="comment">Comment:</label>';
            echo '<textarea class="form-control" rows="3" name="comment" id="comment"></textarea>';
            echo '</div>';

            echo '<button class="btn btn-success" type="submit">';
            echo '<i class="glyphicon glyphicon-ok"></i> Send Partner Request</button>';
            echo '</form>';
            echo '</div>';
        }
    }

    else if (isset($_POST['acceptPartnerRequest']))
    {
        // UPDATE PARTNER DATABASE
        $sql = $db->prepare("
            UPDATE ".$tbname." 
            SET  profilePartnerStatus = '1'
            WHERE (
                profileUuid = '".$_SESSION['useruuid']."'
                AND profilePartnerStatus = '0'
                OR profilePartnerStatus = '2'
            )
        ");
        $sql->execute();

        // AND UPDATE PROFILE DATABASE
        $sql = $db->prepare("
            UPDATE userprofile
            SET  profilePartner = '".$_POST['acceptPartnerRequest']."'
            WHERE (
                useruuid = '".$_SESSION['useruuid']."'
                AND profilePartner = '00000000-0000-0000-0000-000000000000'
            )
        ");
        $sql->execute();
        
        // AND UPDATE PROFILE DATABASE
        $sql = $db->prepare("
            UPDATE userprofile
            SET  profilePartner = '".$_SESSION['useruuid']."'
            WHERE (
                useruuid = '".$_POST['acceptPartnerRequest']."'
                AND profilePartner = '00000000-0000-0000-0000-000000000000'
            )
        ");
        $sql->execute();

        echo '<div class="alert alert-success">';
        echo '<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>';
        echo '<i class="glyphicon glyphicon-ok"></i> ';
        echo 'You accept a partner request from '.getUsernamebyUUID($db, $_POST['acceptPartnerRequest']).'. ';
        echo 'You are now in-world partners with '.getUsernamebyUUID($db, $_POST['acceptPartnerRequest']).'.';
        echo '</div>';
    }

    else if (isset($_POST['ignorePartnerRequest']))
    {
        $sql = $db->prepare("
            UPDATE ".$tbname." 
            SET  profilePartnerStatus = '2'
            WHERE (
                profileUuid = '".$_SESSION['useruuid']."'
                AND profilePartnerStatus = '0'
                OR profilePartnerStatus = '1'
            )
        ");
        $sql->execute();
        
        // AND UPDATE PROFILE DATABASE
        $sql = $db->prepare("
            UPDATE userprofile
            SET  profilePartner = '00000000-0000-0000-0000-000000000000'
            WHERE (
                useruuid = '".$_SESSION['useruuid']."'
                AND profilePartner = '".$_POST['ignorePartnerRequest']."'
            )
        ");
        $sql->execute();
        
        // AND UPDATE PROFILE DATABASE
        $sql = $db->prepare("
            UPDATE userprofile
            SET  profilePartner = '00000000-0000-0000-0000-000000000000'
            WHERE (
                useruuid = '".$_POST['ignorePartnerRequest']."'
                AND profilePartner = '".$_SESSION['useruuid']."'
            )
        ");
        $sql->execute();

        echo '<div class="alert alert-warning">';
        echo '<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>';
        echo '<i class="glyphicon glyphicon-ok"></i> ';
        echo 'You ignore a partner request from '.getUsernamebyUUID($db, $_POST['ignorePartnerRequest']).'. ';
        echo 'Now '.getUsernamebyUUID($db, $_POST['ignorePartnerRequest']).' needs to wait for approval again.';
        echo '</div>';
    }

    else if (isset($_POST['declinePartnerRequest']))
    {
        $sql = $db->prepare("
            DELETE FROM ".$tbname."
            WHERE profileUuid = '".$_POST['declinePartnerRequest']."'
        ");
        $sql->execute();

        // AND UPDATE PROFILE DATABASE
        $sql = $db->prepare("
            UPDATE userprofile
            SET  profilePartner = '00000000-0000-0000-0000-000000000000'
            WHERE useruuid = '".$_POST['declinePartnerRequest']."'
        ");
        $sql->execute();
        
        // AND UPDATE PROFILE DATABASE
        $sql = $db->prepare("
            UPDATE userprofile
            SET  profilePartner = '00000000-0000-0000-0000-000000000000'
            WHERE useruuid = '".$_SESSION['useruuid']."'
        ");
        $sql->execute();

        echo '<div class="alert alert-success">';
        echo '<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>';
        echo '<i class="glyphicon glyphicon-ok"></i> ';
        echo 'You decline a partner request from '.getUsernamebyUUID($db, $_POST['declinePartnerRequest']).'. ';
        echo getUsernamebyUUID($db, $_POST['declinePartnerRequest']).'\' request is deleted.';
        echo '</div>';
    }

    // DISPLAY PARTNER
    // WHERE profileUuid = '".$_SESSION['useruuid']."'
    // OR profilePartner = "'.$_SESSION['useruuid'].'"
    $sql = $db->prepare("
        SELECT *
        FROM ".$tbname."
        WHERE profileUuid = '".$_SESSION['useruuid']."' 
        OR profilePartner = '".$_SESSION['useruuid']."'
    ");

    $sql->execute();
    $rows = $sql->rowCount();

    if ($rows <> 0)
    {
        echo '<table class="table table-hover">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>From Username</th>';
        echo '<th>To Username</th>';
        echo '<th>Comment</th>';
        echo '<th>Status</th>';
        echo '<th class="text-right">Actions</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        echo '<tr>';

        while ($row = $sql->fetch(PDO::FETCH_ASSOC))
        {
            $profileUuid            = $row['profileUuid'];
            $profilePartner         = $row['profilePartner'];
            $profilePartnerText     = $row['profilePartnerText'];
            $profilePartnerStatus   = $row['profilePartnerStatus'];

            if ($profilePartnerStatus == "0" && $profileUuid == $_SESSION['useruuid'])
            {
                echo '<p>You have <span class="badge">'.$rows.'</span> ';
                echo 'pending approval request from user ';
                echo '<strong>'.getUsernamebyUUID($db, $profilePartner).'</strong></p>';
                echo '<td>'.getUsernamebyUUID($db, $profilePartner).'</td>';
                echo '<td>'.getUsernamebyUUID($db, $profileUuid).'</td>';
                echo '<td>'.$profilePartnerText.'</td>';
                echo '<td>'.getPartnerStatusByNumber($profilePartnerStatus).'</td>';
                
                echo '<td>';
                echo '<form action="" method="post" class="pull-right text-right">';
                echo '<input class=hidden name="owner" value="'.$_SESSION['useruuid'].'">';
                echo '<button class="btn btn-success btn-xs" type="submit" name="acceptPartnerRequest" value="'.$profilePartner.'">';
                echo '<i class="glyphicon glyphicon-ok"></i> Accept</button> ';
                echo '<button class="btn btn-warning btn-xs" type="submit" name="ignorePartnerRequest" value="'.$profilePartner.'">';
                echo '<i class="glyphicon glyphicon-remove"></i> Ignore</button> ';
                echo '<button class="btn btn-danger btn-xs" type="submit" name="declinePartnerRequest" value="'.$profileUuid.'">';
                echo '<i class="glyphicon glyphicon-trash"></i> Decline</button> ';
                echo '</form>';
                echo '</td>';
            }
            
            else if ($profilePartnerStatus == "0" && $profilePartner == $_SESSION['useruuid'])
            {
                echo '<p>You have <span class="badge">'.$rows.'</span> ';
                echo 'pending approval request from user ';
                echo '<strong>'.getUsernamebyUUID($db, $profileUuid).'</strong></p>';
                echo '<td>'.getUsernamebyUUID($db, $profilePartner).'</td>';
                echo '<td>'.getUsernamebyUUID($db, $profileUuid).'</td>';
                echo '<td>'.$profilePartnerText.'</td>';
                echo '<td>'.getPartnerStatusByNumber($profilePartnerStatus).'</td>';
                
                echo '<td>';
                echo '<form action="" method="post" class="pull-right text-right">';
                echo '<input class=hidden name="owner" value="'.$_SESSION['useruuid'].'">';
                echo '<button class="btn btn-danger btn-xs" type="submit" name="declinePartnerRequest" value="'.$profileUuid.'">';
                echo '<i class="glyphicon glyphicon-trash"></i> Cancel</button> ';
                echo '</form>';
                echo '</td>';
            }

            else if ($profilePartnerStatus == "1" && $profileUuid == $_SESSION['useruuid'])
            {
                echo '<p>You have <span class="badge">'.$rows.'</span> approved partner.</p>';
                echo '<td>'.getUsernamebyUUID($db, $profilePartner).'</td>';
                echo '<td>'.getUsernamebyUUID($db, $profileUuid).'</td>';
                echo '<td>'.$profilePartnerText.'</td>';
                echo '<td>'.getPartnerStatusByNumber($profilePartnerStatus).'</td>';

                echo '<td class="text-right">';
                echo '<form action="" method="post">';
                echo '<input class=hidden name="owner" value="'.$_SESSION['useruuid'].'">';
                echo '<button class="btn btn-warning btn-xs" type="submit" name="ignorePartnerRequest" value="'.$profilePartner.'">';
                echo '<i class="glyphicon glyphicon-remove"></i> Ignore</button> ';
                echo '<button class="btn btn-danger btn-xs" type="submit" name="declinePartnerRequest" value="'.$profileUuid.'">';
                echo '<i class="glyphicon glyphicon-trash"></i> Delete</button>';
                echo '</form>';
                echo '</td>';
            }

            else if ($profilePartnerStatus == "1" && $profilePartner == $_SESSION['useruuid'])
            {
                echo '<p>You have <span class="badge">'.$rows.'</span> partner.</p>';
                echo '<td>'.getUsernamebyUUID($db, $profilePartner).'</td>';
                echo '<td>'.getUsernamebyUUID($db, $profileUuid).'</td>';
                echo '<td>'.$profilePartnerText.'</td>';
                echo '<td>'.getPartnerStatusByNumber($profilePartnerStatus).'</td>';

                echo '<td class="text-right">';
                echo '<form action="" method="post">';
                echo '<input class=hidden name="owner" value="'.$_SESSION['useruuid'].'">';
                echo '<button class="btn btn-danger btn-xs" type="submit" name="declinePartnerRequest" value="'.$profileUuid.'">';
                echo '<i class="glyphicon glyphicon-trash"></i> Delete</button>';
                echo '</form>';
                echo '</td>';
            }

            else if ($profilePartnerStatus == "2" && $profileUuid == $_SESSION['useruuid'])
            {
                echo '<p>You have <span class="badge">'.$rows.'</span> ';
                echo 'partner request waiting for approval.</p>';
                echo '<td>'.getUsernamebyUUID($db, $profilePartner).'</td>';
                echo '<td>'.getUsernamebyUUID($db, $profileUuid).'</td>';
                echo '<td>'.$profilePartnerText.'</td>';
                echo '<td>'.getPartnerStatusByNumber($profilePartnerStatus).'</td>';

                echo '<td class="text-right">';
                echo '<form action="" method="post">';
                echo '<input class=hidden name="owner" value="'.$_SESSION['useruuid'].'">';
                echo '<button class="btn btn-success btn-xs" type="submit" name="acceptPartnerRequest" value="'.$profilePartner.'">';
                echo '<i class="glyphicon glyphicon-ok"></i> Accept</button> ';
                echo '<button class="btn btn-danger btn-xs" type="submit" name="declinePartnerRequest" value="'.$profileUuid.'">';
                echo '<i class="glyphicon glyphicon-trash"></i> Delete</button>';
                echo '</form>';
                echo '</td>';
            }

            else if ($profilePartnerStatus == "2" && $profilePartner == $_SESSION['useruuid'])
            {
                echo '<p>You have <span class="badge">'.$rows.'</span> ';
                echo 'partner request waiting for approval</p>';
                echo '<td>'.getUsernamebyUUID($db, $profilePartner).'</td>';
                echo '<td>'.getUsernamebyUUID($db, $profileUuid).'</td>';
                echo '<td>'.$profilePartnerText.'</td>';
                echo '<td>'.getPartnerStatusByNumber($profilePartnerStatus).'</td>';

                echo '<td class="text-right">';
                echo '<form action="" method="post">';
                echo '<input class=hidden name="owner" value="'.$_SESSION['useruuid'].'">';
                echo '<button class="btn btn-danger btn-xs" type="submit" name="declinePartnerRequest" value="'.$profileUuid.'">';
                echo '<i class="glyphicon glyphicon-trash"></i> Delete</button>';
                echo '</form>';
                echo '</td>';
            }
        }

        echo '</tr>';
        echo '</tbody>';
        echo '</table>';
    }

    // NO PARTNER YET
    else if ($rows == 0 && !isset($_POST['addNewPartner']))
    {
        echo '<p>You have <span class="badge">'.$rows.'</span> partner.</p>';
        echo '<form action="" method="post">';
        echo '<input class=hidden name="owner" value="'.$_SESSION['useruuid'].'">';
        echo '<button class="btn btn-success" type="submit" name="addNewPartner" value="">';
        echo '<i class="glyphicon glyphicon-plus"></i> Add New Partner</button>';
        echo '</form>';
    }
    unset($sql);
}
?>
