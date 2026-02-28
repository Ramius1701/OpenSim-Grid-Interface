<?php
// Ensure $class, $title, and $displaypanelfooter are defined
if (!isset($class)) {
    $class = ''; // Default class or appropriate value
}

if (!isset($title)) {
    $title = 'Default Title'; // Default title or appropriate value
}

if (!isset($displaypanelfooter)) {
    $displaypanelfooter = FALSE; // Default value
}
?>

<div class="panel panel-default <?php echo htmlspecialchars($class, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="panel-heading">
        <h3 class="panel-title">
            <i class="glyphicon glyphicon-picture pull-right"></i>
            <!--<strong><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></strong>-->
            <strong>Sponsors</strong>
        </h3>
    </div>
    <div class="panel-body">
        <center>
            <a href="./">
                <img class="img-rounded img-responsive" src="./img/logo.png" alt="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>" />
            </a>
        </center>
    </div>
    <?php if ($displaypanelfooter === TRUE): ?>
        <div class="panel-footer"></div>
    <?php endif; ?>
</div>
