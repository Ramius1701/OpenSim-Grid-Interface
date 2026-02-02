<?php
// Datenbankverbindung für Statistiksoftware
$dsn = 'mysql:host=localhost;dbname=casperia;charset=utf8mb4';
$user = 'casperia';
$pass = 'D7pibxuXXdOrk8sp';
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

try {
	$pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
	die('Verbindung zur Datenbank fehlgeschlagen: ' . $e->getMessage());
}