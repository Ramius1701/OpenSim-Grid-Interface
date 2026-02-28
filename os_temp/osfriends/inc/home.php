<?php if (isset($_SESSION['valid'])): ?>
    <h1>Home<i class="glyphicon glyphicon-home pull-right"></i></h1>
<?php endif; ?>

<!-- Fash Message -->
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
    if (isset($_POST['accept']) || isset($_POST['decline']) || isset($_POST['delete']))
    {
        if (!empty($_POST['accept']))
        {
            $sql = $db->prepare("
                INSERT INTO ".$tbname." (
                    PrincipalID, 
                    Friend, 
                    Flags, 
                    Offered
                )
                VALUES (
                    '".$_POST['owner']."', 
                    '".$_POST['accept']."', 
                    '1', 
                    '0'
                )
            ");
            $sql->execute();

            $sql = $db->prepare("
                UPDATE ".$tbname." 
                SET  Flags = '1'
                WHERE (
                    PrincipalID = '".$_SESSION['useruuid']."'
                    AND Friend = '".$_POST['owner']."'
                )
            ");
            $sql->execute();
        }

        else if (!empty($_POST['decline']))
        {
            $sql = $db->prepare("
                DELETE FROM ".$tbname."
                WHERE (
                    PrincipalID = '".$_POST['decline']."'
                    AND 
                    Friend = '".$_POST['owner']."'
                )
            ");
            $sql->execute();
        }

        else if (!empty($_POST['delete']))
        {
            $sql = $db->prepare("
                DELETE FROM ".$tbname."
                WHERE (
                    PrincipalID = '".$_POST['delete']."'
                    AND 
                    Friend = '".$_POST['owner']."'
                )
            ");
            $sql->execute();

            $sql = $db->prepare("
                DELETE FROM ".$tbname."
                WHERE (
                    PrincipalID = '".$_POST['owner']."'
                    AND 
                    Friend = '".$_POST['delete']."'
                )
            ");
            $sql->execute();
        }
    }

    $sql = $db->prepare("
        SELECT *
        FROM ".$tbname."
        WHERE PrincipalID = '".$_SESSION['useruuid']."'
        OR Friend = '".$_SESSION['useruuid']."'
    ");

    $sql->execute();
    $rows = $sql->rowCount();

    if ($rows <> 0)
    {
        echo '<div class="table-responsive">';
        echo '<table class="table table-hover">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>#</th>';
        echo '<th>Friends</th>';
        echo '<th>Flags</th>';
        echo '<th>Offered</th>';
        echo '<th class="text-right">Actions</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        echo '<tr>';

        $counter = 0;
        $sql->execute();

        while ($row = $sql->fetch(PDO::FETCH_ASSOC))
        {
            $PrincipalID    = $row['PrincipalID'];
            $Friend         = $row['Friend'];
            $Flags          = $row['Flags'];
            $Offered        = $row['Offered'];

            if ($Friend == $_SESSION['useruuid'] && $Flags >= 0)
            {
                ++$counter;
                $Flags = getFlagNameByNumber($Flags);

                echo '<tr class="">';
                echo '<td><span class="badge">'.$counter.'</span></td>';
                echo '<td>'.getUsernameByUUID($db, $PrincipalID).'</td>';
                echo '<td>'.$Flags.'</td>';
                echo '<td>'.$Offered.'</td>';
                echo '<td class="text-right">';
                echo '<form action="" method="post">';
                echo '<input class="hidden" name="owner" value="'.$Friend.'">';
                echo '<button class="btn btn-danger btn-xs" type="submit" name="delete" value="'.$PrincipalID.'">';
                echo '<i class="glyphicon glyphicon-trash"></i> Delete</button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }

            else if ($PrincipalID == $_SESSION['useruuid'] && $Flags >= 0)
            {
                if ($Flags == 0)
                {
                    ++$counter;
                    $Flags = getFlagNameByNumber($Flags);

                    echo '<tr class="warning">';
                    echo '<td><span class="badge">'.$counter.'</span></td>';
                    echo '<td>'.getUsernameByUUID($db, $Friend).'</td>';
                    echo '<td>'.$Flags.'</td>';
                    echo '<td>'.$Offered.'</td>';
                    echo '<td class="text-right">';
                    echo '<form class="form form-inline" action="" method="post">';
                    echo '<input class="hidden" name="owner" value="'.$Friend.'">';
                    echo '<button class="btn btn-success btn-xs" type="submit" name="accept" value="'.$PrincipalID.'">';
                    echo '<i class="glyphicon glyphicon-ok"></i> Accept</button> ';
                    echo '<button class="btn btn-warning btn-xs" type="submit" name="decline" value="'.$PrincipalID.'">';
                    echo '<i class="glyphicon glyphicon-remove"></i> Decline</button>';
                    echo '</form>';
                    echo '</td>';
                    echo '</tr>';
                }
            }
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    else
    {
        echo 'You have <span class="badge">0</span> Friend at now ...';
    }
    unset($sql);
}
?>
