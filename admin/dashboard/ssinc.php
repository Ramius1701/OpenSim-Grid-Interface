<?php
// Admin-Zugriff erzwingen, bevor irgendetwas aus der Datenbank gelesen wird
require_once __DIR__ . '/../../include/config.php';
require_once __DIR__ . '/../../include/auth.php';
require_admin();

// Datenbankverbindung für Statistiksoftware
$dsn = 'mysql:host=localhost;dbname=casperia;charset=utf8mb4';
$user = 'casperia';
$pass = '***REMOVED-DB-PASSWORD***';
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

try {
	$pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
	die('Verbindung zur Datenbank fehlgeschlagen: ' . $e->getMessage());
}