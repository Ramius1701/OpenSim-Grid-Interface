<?php
// Emergency redirect to setup if no configuration exists
if (!file_exists('include/config.php') || !file_exists('include/env.php')) {
    header('Location: setup.php');
    exit;
}

// If configuration exists, redirect to welcome page
header('Location: welcome.php');
exit;
?>