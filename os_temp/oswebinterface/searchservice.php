<?php
// Titel und Header einbinden
$title = "SearchService";
include_once 'include/header.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

// Datenbankverbindung
$mysqli = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($mysqli->connect_errno) {
    die("Database connection failed: " . $mysqli->connect_error);
}

// Sicherer Eingabeschutz
function sanitize_input($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Funktionen
function oscat($catid) {
    switch ($catid) {
        case "0": return "Any";
        case "18": return "Discussion";
        case "19": return "Sports";
        case "20": return "Live Music";
        case "22": return "Commercial";
        case "23": return "Nightlife/Entertainment";
        case "24": return "Games/Contests";
        case "25": return "Pageants";
        case "26": return "Education";
        case "27": return "Arts and Culture";
        case "28": return "Charity/Support Groups";
        case "29": return "Miscellaneous";
        default: return "Unknown";
    }
}

function getosuser($FirstName, $LastName, $mysqli) {
    $query = "SELECT * FROM userinfo WHERE FirstName = ? AND LastName = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("ss", $FirstName, $LastName);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function uuid2name($uuid, $mysqli) {
    $query = "SELECT * FROM userinfo WHERE PrincipalID = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("s", $uuid);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function regionname($ruuid, $mysqli) {
    $query = "SELECT regionName FROM regions WHERE uuid = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("s", $ruuid);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['regionName'] ?? null;
}

// Suche ausführen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $search_type = sanitize_input($_POST['search_type'] ?? '');
    $search_query = sanitize_input($_POST['search_query'] ?? '');

    if ($search_query !== '') {
        switch ($search_type) {
            case 'userinfo':
                $query = "SELECT * FROM userinfo WHERE user LIKE ? OR avatar LIKE ?";
                break;
            case 'os_groups_groups':
                $query = "SELECT * FROM os_groups_groups WHERE Name LIKE ? OR Location LIKE ?";
                break;
            case 'regions':
                $query = "SELECT * FROM regions WHERE regionName LIKE ? OR serverIP LIKE ?";
                break;
            default:
                $query = '';
        }

        if ($query) {
            $stmt = $mysqli->prepare($query);
            $search_param = "%$search_query%";
            $stmt->bind_param("ss", $search_param, $search_param);
            $stmt->execute();
            $result = $stmt->get_result();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(SITE_NAME . ' ' . $title, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; color: black; margin: 0; padding: 0; }
        main { width: 75%; margin: 2em auto; padding: 2em; background-color: #ffffff; color: black; border: 1px solid #ccc; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }
        h2 { color: #333; }
        form label { display: block; margin-bottom: 0.5em; color: #333; }
        form input[type="text"], form select { width: 100%; padding: 5px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; }
        form input[type="submit"] { padding: 5px 20px; background-color: #007BFF; color: #ffffff; border: none; border-radius: 4px; cursor: pointer; }
        form input[type="submit"]:hover { background-color: #0056b3; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85em; }
        table, th, td { border: 1px solid #ccc; }
        th, td { padding: 8px; text-align: left; }
    </style>
</head>
<body>
<main>
    <h2>Search Service</h2>
    <form method="post" action="">
        <label for="search_type">Search Type:</label>
        <select id="search_type" name="search_type">
            <option value="userinfo">People</option>
            <option value="os_groups_groups">Groups</option>
            <option value="regions">Regions</option>
        </select>
        <label for="search_query">Search Query:</label>
        <input type="text" id="search_query" name="search_query" required>
        <input type="submit" value="Search">
    </form>

    <?php if (!empty($result) && $result->num_rows > 0): ?>
        <h3>Search Results:</h3>
        <table>
            <thead>
                <tr>
                    <?php foreach ($result->fetch_fields() as $field): ?>
                        <th><?= htmlspecialchars($field->name) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <?php foreach ($row as $value): ?>
                            <td><?= htmlspecialchars($value) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <p>No results found.</p>
    <?php endif; ?>
</main>
</body>
</html>
<?php
$mysqli->close();
?>
