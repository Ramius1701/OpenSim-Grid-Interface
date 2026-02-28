<?php
// Ensure $class is defined
if (!isset($class)) {
    $class = 'default-class'; // You can set an appropriate default class or an empty string
}

// Ensure $flashinfo and $displaypanelfooter are defined
if (!isset($flashinfo)) {
    $flashinfo = ''; // Default content or an appropriate default value
}

if (!isset($displaypanelfooter)) {
    $displaypanelfooter = FALSE; // Default value or appropriate setting
}
?>

<div class="panel panel-default <?php echo $class; ?>">
    <div class="panel-heading">
        <h3 class="panel-title">
            <i class="glyphicon glyphicon-exclamation-sign pull-right"></i>
            <strong>Info</strong>
        </h3>
    </div>
    <div class="panel-body"><?php echo $flashinfo; ?></div>
    <?php if ($displaypanelfooter === TRUE): ?>
        <div class="panel-footer"></div>
    <?php endif; ?>
</div>
