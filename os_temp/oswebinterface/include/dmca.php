<?php
$title = "DMCA";
include 'header.php';

// Sprachauswahl (Standard: Deutsch)
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'de';

// Platzhalter-Werte setzen
$variables = [
    "DEIN GRID-NAME" => "Virtual " . SITE_NAME . " Grid",
    "DATUM" => date("d.m.Y"),
    "DEINE EMAIL" => "support@" . SITE_NAME . ".com",
    "DEINE POSTADRESSE" => "Musterstraße 123, 12345 Musterstadt",
    "OPTIONAL" => "Discord: GridSupport" . BASE_URL,
    "DEINE KONTAKT-EMAIL" => "kontakt@" . SITE_NAME . ".com"
];
?>

<div class="language-switcher">
    <a href="?lang=de">Deutsch</a> | <a href="?lang=en">English</a>
</div>

<style>
   .markdown-content {
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

<?php
function replacePlaceholders($text, $variables = []) {
    foreach ($variables as $key => $value) {
        $text = str_replace("[$key]", $value, $text);
    }
    return $text;
}

function simpleMarkdownToHTML($text) {
    // Zeilenumbrüche umwandeln
    $text = nl2br($text);

    // Fettdruck **text** -> <strong>text</strong>
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);

    // Kursiv *text* -> <em>text</em>
    $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);

    // Überschriften # -> <h1>, ## -> <h2> usw.
    for ($i = 6; $i >= 1; $i--) {
        $text = preg_replace('/^' . str_repeat('#', $i) . '\s*(.*?)$/m', "<h$i>$1</h$i>", $text);
    }

    // Links [Text](URL) -> <a href="URL">Text</a>
    $text = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2">$1</a>', $text);

    // Inline-Code `text` -> <code>text</code>
    $text = preg_replace('/`(.*?)`/', '<code>$1</code>', $text);

    return $text;
}

// Deutsche DMCA-Richtlinie
$dmcaTextDE = "
# **DMCA-Richtlinie für [DEIN GRID-NAME]**  
**Letzte Aktualisierung:** [DATUM]  

Diese DMCA-Richtlinie beschreibt, wie **[DEIN GRID-NAME]** (nachfolgend „wir“, „uns“, „unser Grid“) 
auf Beschwerden bezüglich Urheberrechtsverletzungen gemäß dem **Digital Millennium Copyright Act (DMCA)** reagiert.  

Falls du glaubst, dass Inhalte innerhalb unseres OpenSimulator-Grids gegen dein Urheberrecht verstoßen, 
kannst du eine **DMCA-Beschwerde** einreichen, indem du die unten beschriebenen Schritte befolgst.  

---

## **1. Einreichen einer DMCA-Beschwerde (Takedown-Anfrage)**  
Falls du der Meinung bist, dass deine urheberrechtlich geschützten Inhalte ohne Erlaubnis in **[DEIN GRID-NAME]** verwendet wurden, 
sende bitte eine schriftliche Beschwerde an unseren **DMCA-Agenten** unter:  

📩 **E-Mail:** [DEINE EMAIL]  
📬 **Postadresse:** [DEINE POSTADRESSE]  
📞 **Telefon:** [OPTIONAL]  

**Deine DMCA-Beschwerde muss folgende Informationen enthalten:**  

1. **Identifikation des geschützten Werkes:**  
   - Eine detaillierte Beschreibung des urheberrechtlich geschützten Werkes (z. B. ein Screenshot, Link oder Dokumentation).  
   
2. **Standort des rechtsverletzenden Inhalts:**  
   - Genaue Position des Inhalts in unserem Grid, inklusive:  
     - Name der Region  
     - Koordinaten (falls möglich)  
     - UUID oder Asset-ID des betroffenen Objekts  
     - Screenshot oder Beschreibung  

3. **Kontaktdaten:**  
   - Dein Name, Adresse, E-Mail-Adresse und Telefonnummer.  

4. **Erklärung zur Rechtsverletzung:**  
   - Eine Erklärung, dass du in **gutem Glauben** davon ausgehst, dass die Nutzung nicht vom Urheberrechtsinhaber, 
   seinem Vertreter oder dem Gesetz (z. B. Fair Use) erlaubt ist.  

5. **Eidesstattliche Versicherung:**  
   - Eine Erklärung, dass die Angaben in deiner Beschwerde korrekt sind und du der rechtmäßige Inhaber des Urheberrechts oder ein autorisierter Vertreter bist.  

6. **Digitale oder physische Unterschrift:**  
   - Eine elektronische oder handschriftliche Unterschrift des Urheberrechtsinhabers oder dessen bevollmächtigten Vertreters.  

---

## **2. Unsere Reaktion auf eine DMCA-Beschwerde**  
Nach Erhalt einer gültigen DMCA-Anfrage werden wir:  

- Den mutmaßlich rechtsverletzenden Inhalt **vorläufig entfernen oder den Zugriff darauf sperren**.  
- Den Nutzer, der den Inhalt bereitgestellt hat, über die Beschwerde informieren.  
- Falls der betroffene Nutzer eine **Gegendarstellung** einreicht (siehe Abschnitt 3), den Urheberrechtsinhaber darüber informieren.  

Falls der Urheberrechtsinhaber innerhalb von **10 Werktagen** nach unserer Benachrichtigung keine rechtlichen Schritte einleitet, 
können wir den entfernten Inhalt wiederherstellen.  

---

## **3. Einreichen einer Gegendarstellung (Counter-Notice)**  
Falls du der Meinung bist, dass der entfernte Inhalt nicht gegen Urheberrechte verstößt oder du eine Erlaubnis zur Nutzung hattest, 
kannst du eine **Gegendarstellung** einreichen.  

Bitte sende deine Gegendarstellung an **[DEINE EMAIL]** mit folgenden Angaben:  

1. **Identifikation des entfernten Inhalts:**  
   - Die ursprüngliche Position des Inhalts (Region, Koordinaten, UUID, Screenshots).  

2. **Erklärung zur Unrechtmäßigkeit der Beschwerde:**  
   - Eine Erklärung, dass du **in gutem Glauben** der Meinung bist, dass die Entfernung aufgrund eines Fehlers oder falscher Identifizierung erfolgte.  

3. **Zustimmung zur Gerichtsbarkeit:**  
   - Falls du außerhalb der USA lebst, eine Erklärung, dass du die Gerichtsbarkeit der US-amerikanischen Bundesgerichte akzeptierst.  

4. **Eidesstattliche Versicherung:**  
   - Eine Versicherung, dass deine Angaben korrekt sind und du für eventuelle rechtliche Konsequenzen verantwortlich bist.  

5. **Digitale oder physische Unterschrift:**  
   - Deine Unterschrift oder die deines bevollmächtigten Vertreters.  

Nach Erhalt einer gültigen Gegendarstellung kann der entfernte Inhalt innerhalb von **10–14 Tagen** wiederhergestellt werden, 
sofern der ursprüngliche Beschwerdeführer keine Klage einreicht.  

---

## **4. Konsequenzen für wiederholte Verstöße**  
Nutzer, die wiederholt Urheberrechte verletzen, können:  
- **Verwarnt**,  
- **Vorübergehend gesperrt**,  
- Oder **dauerhaft aus dem Grid ausgeschlossen** werden.  

Wir behalten uns das Recht vor, Nutzerkonten ohne vorherige Warnung zu sperren, falls schwere Verstöße vorliegen.  

---

## **5. Keine Haftung für Nutzerinhalte**  
Unser Grid stellt eine Plattform für virtuelle Interaktionen bereit und hostet nutzergenerierte Inhalte. 
Wir übernehmen keine Haftung für urheberrechtlich geschützte Materialien, die von Nutzern hochgeladen wurden. 
Alle Nutzer sind für ihre hochgeladenen Inhalte selbst verantwortlich.  

Wir arbeiten jedoch aktiv daran, **rechtsverletzende Inhalte zu entfernen**, sobald wir eine **gültige DMCA-Beschwerde** erhalten.  

---

## **6. Kontakt für weitere Fragen**  
Falls du Fragen zur DMCA-Richtlinie hast, kannst du uns unter **[DEINE KONTAKT-EMAIL]** erreichen.  

---

### **Rechtlicher Hinweis:**  
Diese Vorlage dient nur zu **Informationszwecken** und stellt **keine Rechtsberatung** dar. 
Falls du eine rechtlich geprüfte DMCA-Richtlinie benötigst, konsultiere bitte einen Anwalt.  
";

// Englische DMCA-Richtlinie
$dmcaTextEN = "
# **DMCA Policy for [DEIN GRID-NAME]**  
**Last Updated:** [DATUM]  

This DMCA policy describes how **[DEIN GRID-NAME]** (hereinafter „we“, „us“, „our grid“) 
responds to complaints regarding copyright infringement under the **Digital Millennium Copyright Act (DMCA)**.  

If you believe that content within our OpenSimulator grid violates your copyright, 
you can file a **DMCA complaint** by following the steps below.  

---

## **1. Submitting a DMCA Complaint (Takedown Request)**  
If you believe that your copyrighted material has been used without permission in **[DEIN GRID-NAME]**, 
please send a written complaint to our **DMCA Agent** at:  

📩 **Email:** [DEINE EMAIL]  
📬 **Postal Address:** [DEINE POSTADRESSE]  
📞 **Phone:** [OPTIONAL]  

**Your DMCA complaint must include the following information:**  

1. **Identification of the copyrighted work:**  
   - A detailed description of the copyrighted work (e.g., a screenshot, link, or documentation).  

2. **Location of the infringing content:**  
   - The exact location of the content in our grid, including:  
     - Region name  
     - Coordinates (if possible)  
     - UUID or asset ID of the affected object  
     - Screenshot or description  

3. **Contact information:**  
   - Your name, address, email address, and phone number.  

4. **Statement of infringement:**  
   - A statement that you have a **good faith belief** that the use is not authorized by the copyright owner, 
   their agent, or the law (e.g., fair use).  

5. **Sworn statement:**  
   - A statement that the information in your complaint is accurate and that you are the copyright owner or authorized to act on their behalf.  

6. **Digital or physical signature:**  
   - An electronic or handwritten signature of the copyright owner or their authorized representative.  

---

## **2. Our Response to a DMCA Complaint**  
Upon receiving a valid DMCA complaint, we will:  

- **Temporarily remove or disable access** to the allegedly infringing content.  
- Notify the user who provided the content about the complaint.  
- If the affected user submits a **counter-notice** (see Section 3), inform the copyright owner.  

If the copyright owner does not take legal action within **10 business days** of our notification, 
we may restore the removed content.  

---

## **3. Submitting a Counter-Notice**  
If you believe that the removed content does not infringe copyright or you had permission to use it, 
you can submit a **counter-notice**.  

Please send your counter-notice to **[DEINE EMAIL]** with the following information:  

1. **Identification of the removed content:**  
   - The original location of the content (region, coordinates, UUID, screenshots).  

2. **Statement of good faith:**  
   - A statement that you have a **good faith belief** that the removal was due to a mistake or misidentification.  

3. **Consent to jurisdiction:**  
   - If you are outside the USA, a statement that you consent to the jurisdiction of US federal courts.  

4. **Sworn statement:**  
   - A statement that the information in your counter-notice is accurate and that you are responsible for any legal consequences.  

5. **Digital or physical signature:**  
   - Your signature or that of your authorized representative.  

Upon receiving a valid counter-notice, the removed content may be restored within **10–14 days**, 
unless the original complainant files a lawsuit.  

---

## **4. Consequences for Repeated Violations**  
Users who repeatedly infringe copyright may:  
- Be **warned**,  
- **Temporarily suspended**,  
- Or **permanently banned** from the grid.  

We reserve the right to suspend accounts without prior warning in cases of severe violations.  

---

## **5. No Liability for User-Generated Content**  
Our grid provides a platform for virtual interactions and hosts user-generated content. 
We are not liable for copyrighted materials uploaded by users. 
All users are responsible for the content they upload.  

However, we actively work to **remove infringing content** upon receiving a **valid DMCA complaint**.  

---

## **6. Contact for Further Questions**  
If you have questions about this DMCA policy, you can reach us at **[DEINE KONTAKT-EMAIL]**.  

---

### **Legal Notice:**  
This template is for **informational purposes only** and does **not constitute legal advice**. 
If you need a legally reviewed DMCA policy, please consult an attorney.  
";

// Wähle den Text basierend auf der Sprache
$dmcaText = ($lang == 'de') ? $dmcaTextDE : $dmcaTextEN;

// 1️⃣ Platzhalter ersetzen
$dmcaText = replacePlaceholders($dmcaText, $variables);

// 2️⃣ Markdown in HTML umwandeln
$finalHTML = simpleMarkdownToHTML($dmcaText);

// 3️⃣ HTML ausgeben
echo "<div class='markdown-content'>$finalHTML</div>";
?>
