<?php
include_once "config.php";

// JSON-Datei einlesen
$events = json_decode(file_get_contents('../calendar/events.json'), true);

// Header setzen, um den Content-Type auf XML zu setzen
header("Content-Type: application/rss+xml; charset=UTF-8");

// Nächsten Termin auswählen
$currentDate = date('Y-m-d');
$nextEvent = null;

foreach ($events as $event) {
    if ($event['date'] >= $currentDate) {
        $nextEvent = $event;
        break;
    }
}

// RSS-Feed beginnen
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<rss version="2.0">
    <channel>
        <title><?php echo CALENDAR_TITLE; ?></title>
        <link><?php echo BASE_URL; ?></link>
        <description>RSS-Feed mit Kalenderereignissen</description>
        <language>de-de</language>

        <?php if ($nextEvent): ?>
            <item>
                <title>
                    <?php 
                        // Überprüfen, ob das 'title'-Feld existiert
                        if (isset($nextEvent['title'])) {
                            echo htmlspecialchars($nextEvent['title']); 
                        } else {
                            echo htmlspecialchars($nextEvent['texts'][0]);
                        }
                    ?>
                </title>
                <link><?php echo htmlspecialchars($nextEvent['link']); ?></link>
                <description>
                    <?php 
                        // Überprüfen, ob das 'description'-Feld existiert
                        if (isset($nextEvent['description'])) {
                            echo htmlspecialchars($nextEvent['description']); 
                        } else {
                            echo htmlspecialchars(join(', ', array_slice($nextEvent['texts'], 1)));
                        }
                    ?>
                </description>
                <pubDate><?php echo date('r', strtotime($nextEvent['date'])); ?></pubDate>
                <?php if (isset($nextEvent['image'])): ?>
                    <image><?php echo htmlspecialchars(BASE_URL . $nextEvent['image']); ?></image>
                <?php endif; ?>
            </item>
        <?php endif; ?>
    </channel>
</rss>
