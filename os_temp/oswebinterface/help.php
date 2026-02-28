<?php
$title = "Help";
include_once 'include/header.php';

// Sprachauswahl (Standard: Deutsch)
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'de';

// Variablen für die Grid-URL
$txt1 = BASE_URL;
$txt2 = GRID_PORT;
?>

<style>
   .container {
      max-width: 800px;
      margin: 0 auto;
      padding: 20px;
      color: rgb(31, 31, 31);  /* Korrektur: 'color' statt 'Color' */
      background-color: rgb(238, 241, 241);
      border: 1px solid #ddd;
      border-radius: 5px;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
   }

   .language-switcher {
      text-align: right;
      margin-bottom: 20px;
   }

   .language-switcher a {
      text-decoration: none;
      color: #007BFF;
      margin: 0 5px;
   }

   .language-switcher a:hover {
      text-decoration: underline;
   }

   code {
      background-color: #f4f4f4;
      padding: 2px 5px;
      border-radius: 3px;
      font-family: monospace;
   }
</style>

<main class="container">
    <!-- Sprachauswahl -->
    <div class="language-switcher">
        <a href="?lang=de">Deutsch</a> | <a href="?lang=en">English</a>
    </div>

    <?php if ($lang == 'de'): ?>
        <!-- Deutsche Version -->
        <section>
            <h1>OpenSim Viewer mit einem Grid verbinden</h1>
            <h2>Schritt-für-Schritt Anleitung</h2>
            <ol>
                <li>Lade einen kompatiblen OpenSim-Viewer herunter (z. B. Firestorm).</li>
                <li>Installiere den Viewer auf deinem Computer.</li>
                <li>Starte den Viewer und öffne die Einstellungen.</li>
                <li>Suche den Bereich <strong>"Grids"</strong> oder <strong>"Grid-Manager"</strong>.</li>
                <li>Klicke auf <strong>"Neues Grid hinzufügen"</strong> oder eine ähnliche Option.</li>
                <li>Gib die <strong>Login-URL</strong> deines Grids ein (z. B. <code><?php echo $txt1, $txt2; ?>/</code>).</li>
                <li>Klicke auf <strong>"Hinzufügen" oder "Speichern"</strong>.</li>
                <li>Wähle das Grid aus der Liste und gib deine Anmeldedaten ein.</li>
                <li>Klicke auf <strong>"Anmelden"</strong>, um das Grid zu betreten.</li>
            </ol>
        </section>

        <section>
            <h2>Tipps</h2>
            <ul>
                <li>Stelle sicher, dass du die richtige Grid-URL hast.</li>
                <li>Falls der Viewer das Grid nicht erkennt, prüfe die Serververbindung.</li>
                <li>Nutze die neueste Version deines Viewers für beste Kompatibilität.</li>
            </ul>
        </section>

    <?php else: ?>
        <!-- Englische Version -->
        <section>
            <h1>Connecting an OpenSim Viewer to a Grid</h1>
            <h2>Step-by-Step Guide</h2>
            <ol>
                <li>Download a compatible OpenSim viewer (e.g., Firestorm).</li>
                <li>Install the viewer on your computer.</li>
                <li>Start the viewer and open the settings.</li>
                <li>Look for the <strong>"Grids"</strong> or <strong>"Grid Manager"</strong> section.</li>
                <li>Click on <strong>"Add New Grid"</strong> or a similar option.</li>
                <li>Enter the <strong>login URL</strong> of your grid (e.g., <code><?php echo $txt1, $txt2; ?>/</code>).</li>
                <li>Click <strong>"Add" or "Save"</strong>.</li>
                <li>Select the grid from the list and enter your login credentials.</li>
                <li>Click <strong>"Login"</strong> to enter the grid.</li>
            </ol>
        </section>

        <section>
            <h2>Tips</h2>
            <ul>
                <li>Make sure you have the correct grid URL.</li>
                <li>If the viewer does not recognize the grid, check the server connection.</li>
                <li>Use the latest version of your viewer for best compatibility.</li>
            </ul>
        </section>
    <?php endif; ?>
</main>

<?php include_once 'include/footer.php'; ?>