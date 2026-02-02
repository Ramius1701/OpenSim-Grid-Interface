<?php
// datatest.php ist der Test für data.php und datainc.php
// Es soll aus jeder Tabelle der Datenbank der erste Eintrag gelesen werden, aber 'data' in der Tabelle 'assets' soll ausgeblendet werden.

require_once __DIR__ . '/data.php';

// RobustDB mit datainc.php-Verbindung nutzen
$db = new RobustDB('datainc');

global $ROBUST_TABLES;

foreach ($ROBUST_TABLES as $table => $columns) {
    // Für assets: alle Spalten außer 'data' abfragen
    if ($table === 'assets') {
        $cols = array_filter($columns, function($c) { return $c !== 'data'; });
        $sql = "SELECT " . implode(", ", $cols) . " FROM `$table` LIMIT 1";
        $result = $db->query($sql);
        echo "<h3>$table</h3>\n";
        if ($result && count($result) > 0) {
            $row = $result[0];
            $row['[BLOB]'] = '[BLOB ausgeblendet]';
            echo '<pre>' . htmlspecialchars(print_r($row, true)) . '</pre>';
        } else {
            echo '<em>Keine Einträge gefunden.</em>';
        }
    } else {
        $result = $db->query("SELECT * FROM `$table` LIMIT 1");
        echo "<h3>$table</h3>\n";
        if ($result && count($result) > 0) {
            echo '<pre>' . htmlspecialchars(print_r($result[0], true)) . '</pre>';
        } else {
            echo '<em>Keine Einträge gefunden.</em>';
        }
    }
}