<?php
// Force admin access before anything is read from the database
require_once __DIR__ . '/../../include/config.php';
require_once __DIR__ . '/../../include/auth.php';
require_admin();

// Database connection for the statistics dashboard (credentials from
// include/env.php, already loaded by include/config.php above - not
// hardcoded/committed here)
$dsn = 'mysql:host=' . DB_SERVER . ';dbname=' . DB_NAME . ';charset=utf8mb4';
$user = DB_USERNAME;
$pass = DB_PASSWORD;
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

try {
	$pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
	error_log('ssinc.php: DB connection failed: ' . $e->getMessage());
	die('Database connection failed.');
}