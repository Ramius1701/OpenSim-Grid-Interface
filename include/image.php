<?php
// Include configuration
include_once "include/config.php";

// Check whether the 'image_url' parameter was provided
$image_url = isset($_GET['image_url']) ? $_GET['image_url'] : '';

// Make sure an image URL is provided
if (empty($image_url)) {
    die('No image URL provided!');
}

// Fetch the image from the URL
$image_data = file_get_contents($image_url);

// If the image could not be loaded
if ($image_data === false) {
    die('Error loading image!');
}

// Use Imagick to convert the image to the desired format
try {
    $imagick = new Imagick();
    $imagick->readImageBlob($image_data);

    // If this is a JP2 image, convert it to JPG
    if ($imagick->getImageFormat() == 'JPEG2000') {
        $imagick->setImageFormat('jpg'); // Convert to JPG
    }

    // Output the image to the browser
    header('Content-Type: image/jpeg'); // JPG MIME type for JPG images
    echo $imagick->getImageBlob();
} catch (Exception $e) {
    die('Error converting image: ' . $e->getMessage());
}
?>
