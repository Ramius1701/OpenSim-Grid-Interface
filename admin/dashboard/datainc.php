<?php
// Sicherheitsheader setzen
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
if (session_status() === PHP_SESSION_NONE) {
	session_start([
		'cookie_httponly' => true,
		'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
		'cookie_samesite' => 'Strict',
	]);
}

// Admin-Zugriff erzwingen, bevor irgendetwas aus der Datenbank gelesen wird
require_once __DIR__ . '/../../include/config.php';
require_once __DIR__ . '/../../include/auth.php';
require_admin();

// Datenbankverbindung für Statistiksoftware (credentials from include/env.php,
// already loaded by include/config.php above - not hardcoded/committed here)
$dsn = 'mysql:host=' . DB_SERVER . ';dbname=' . DB_NAME . ';charset=utf8mb4';
$user = DB_USERNAME;
$pass = DB_PASSWORD;
$options = [
	PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
	PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
	PDO::ATTR_EMULATE_PREPARES => false,
];

try {
	$pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
	// Im Produktivbetrieb keine Details ausgeben!
	http_response_code(500);
	exit('Interner Fehler.');
}