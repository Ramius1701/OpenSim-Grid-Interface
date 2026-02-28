<?php
// Passwortschutz
session_start();

$title = "Event Editor Service";
include_once 'include/header.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input_password = $_POST['password'] ?? '';

    // Überprüfen des Passworts
    if (in_array($input_password, $registration_passwords_events)) {
        $_SESSION['authenticated'] = true;
    } else {
        $error_message = "Falsches Passwort. Bitte versuchen Sie es erneut.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    session_destroy();
    header("Location: eventedit.php"); // Wo ist dieser Editor.
    exit;
}

if (!isset($_SESSION['authenticated'])) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events Calendar Editor</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: auto; }
        h2 { text-align: center; }
        .input-group { margin-bottom: 15px; }
        input[type="password"] { width: 100%; padding: 8px; box-sizing: border-box; }
        button { padding: 10px 20px; background: #007bff; color: #fff; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <h2>Events Calendar Editor</h2>
    <form method="POST">
        <div class="input-group">
            <input type="password" name="password" placeholder="Passwort eingeben" required />
        </div>
        <button type="submit">Login</button>
    </form>
</body>
</html>
<?php
    exit;
}

// Funktion zum Speichern der Events in einer JSON-Datei
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['events'])) {
    $events = json_decode($_POST['events'], true);
    $backupFilename = 'events_' . date('Y-m-d_H-i-s') . '.bak.json';
    copy('calendar/events.json', $backupFilename);
    file_put_contents('calendar/events.json', json_encode($events, JSON_PRETTY_PRINT));
    echo "<script>alert('Events erfolgreich gespeichert!');</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events Editor</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: auto; }
        h1 { text-align: center; }
        .input-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input[type="text"], input[type="date"], input[type="color"], textarea { width: 100%; padding: 8px; box-sizing: border-box; }
        button { padding: 10px 20px; background: #007bff; color: #fff; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .event-list { margin-top: 20px; }
        .event-item { border: 1px solid #ccc; padding: 10px; border-radius: 5px; margin-bottom: 10px; }
        .event-item h3 { margin: 0 0 10px; }
        form.logout { display: flex; justify-content: center; margin-top: 20px; }
    </style>
</head>
<body>
    <h1>Events Editor</h1>

    <div id="editor">
        <div class="input-group">
            <label for="event-date">Datum:</label>
            <input type="date" id="event-date" />
        </div>
        <div class="input-group">
            <label for="event-image">Bild URL:</label>
            <input type="text" id="event-image" />
        </div>
        <div class="input-group">
            <label for="event-texts">Texte (kommagetrennt):</label>
            <textarea id="event-texts"></textarea>
        </div>
        <div class="input-group">
            <label for="event-link">Link:</label>
            <input type="text" id="event-link" />
        </div>
        <div class="input-group">
            <label for="event-color">Hintergrundfarbe:</label>
            <input type="color" id="event-color" />
        </div>
        <div class="input-group">
            <label for="event-txtcolor">Schriftfarbe:</label>
            <input type="color" id="event-txtcolor" />
        </div>
        <button onclick="addEvent()">Event hinzufügen</button>
        <button onclick="downloadEvents()">Download</button>

        <div class="event-list" id="event-list"></div>

        <form method="POST" class="logout">
            <button type="submit" name="logout">Session beenden</button>
        </form>
    </div>

    <script>
        let events = [];

        async function fetchEvents() {
            const response = await fetch('calendar/events.json');
            events = await response.json();
            displayEvents();
        }

        function displayEvents() {
            const eventList = document.getElementById('event-list');
            eventList.innerHTML = '';
            events.forEach((event, index) => {
                const eventItem = document.createElement('div');
                eventItem.classList.add('event-item');
                eventItem.innerHTML = `
                    <h3>${event.date}</h3>
                    <p><strong>Bild URL:</strong> ${event.image}</p>
                    <p><strong>Texte:</strong> ${event.texts.join(', ')}</p>
                    <p><strong>Link:</strong> <a href="${event.link}" target="_blank">${event.link}</a></p>
                    <p><strong>Hintergrundfarbe:</strong> <span style="background-color:${event.color}; padding: 2px 5px;">${event.color}</span></p>
                    <p><strong>Schriftfarbe:</strong> <span style="background-color:${event.txtcolor}; padding: 2px 5px;">${event.txtcolor}</span></p>
                    <button onclick="deleteEvent(${index})">Löschen</button>
                `;
                eventList.appendChild(eventItem);
            });
        }

        function addEvent() {
            const date = document.getElementById('event-date').value;
            const image = document.getElementById('event-image').value;
            const texts = document.getElementById('event-texts').value.split(',');
            const link = document.getElementById('event-link').value;
            const color = document.getElementById('event-color').value;
            const txtcolor = document.getElementById('event-txtcolor').value;

            if (date && texts.length > 0 && link) {
                events.push({ date, image, texts, link, color, txtcolor });
                displayEvents();
            } else {
                alert('Bitte alle Felder ausfüllen.');
            }
        }

        function deleteEvent(index) {
            events.splice(index, 1);
            displayEvents();
        }

        function formatJSON(json) {
            return JSON.stringify(json, null, 2);
        }

        function downloadEvents() {
            const formattedEvents = formatJSON(events);
            const blob = new Blob([formattedEvents], { type: 'application/json' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'calendar/events.json';
            link.click();
        }

        async function saveEvents() {
            const formattedEvents = formatJSON(events);
            const formData = new FormData();
            formData.append('events', formattedEvents);

            const response = await fetch('editor.php', {
                method: 'POST',
                body: formData
            });

            if (response.ok) {
                alert('Events erfolgreich gespeichert!');
            } else {
                alert('Fehler beim Speichern.');
            }
        }

        document.addEventListener('DOMContentLoaded', fetchEvents);
    </script>
</body>
</html>
