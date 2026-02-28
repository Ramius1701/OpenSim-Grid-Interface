<?php
// Check if configuration files exist
if (!file_exists(__DIR__ . '/config.php')) {
    // Redirect to setup assistant
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['REQUEST_URI']);
    $setupUrl = $protocol . '://' . $host . $path . '/setup.php';
    
    header("Location: $setupUrl");
    exit;
}

require_once 'config.php';
include HEADER_FILE;
?>
