<?php
// Ensure $class, $carousel_images, $carousel_class, and $displaypanelfooter are defined
if (!isset($class)) {
    $class = ''; // Default class or appropriate value
}

if (!isset($carousel_images)) {
    $carousel_images = []; // Default to an empty array
}

if (!isset($carousel_class)) {
    $carousel_class = 'img-responsive'; // Default class or appropriate value
}

if (!isset($displaypanelfooter)) {
    $displaypanelfooter = FALSE; // Default value
}
?>

<div class="panel panel-default <?php echo htmlspecialchars($class, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="panel-heading">
        <h3 class="panel-title">
            <i class="glyphicon glyphicon-picture pull-right"></i>
            <strong>Carousel</strong>
        </h3>
    </div>
    <div class="panel-body">
        <div class="carousel slide" id="myCarousel">
            <div class="carousel-inner">
                <?php
                $n = 0;
                foreach ($carousel_images as $image) {
                    ++$n;
                    if ($n == 1) {
                        echo '<div class="item active">';
                    } else {
                        echo '<div class="item">';
                    }
                    echo '<div class="col-xs-3">';
                    echo '<a href="#"><img src="img/carousel/' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . '" class="' . htmlspecialchars($carousel_class, ENT_QUOTES, 'UTF-8') . '"></a>';
                    echo '</div>';
                    echo '</div>';
                }
                ?>
            </div>
            <a class="left carousel-control" href="#myCarousel" data-slide="prev"><i class="glyphicon glyphicon-chevron-left"></i></a>
            <a class="right carousel-control" href="#myCarousel" data-slide="next"><i class="glyphicon glyphicon-chevron-right"></i></a>
        </div>
    </div>
    <?php if ($displaypanelfooter === TRUE): ?>
        <div class="panel-footer"></div>
    <?php endif; ?>
</div>
