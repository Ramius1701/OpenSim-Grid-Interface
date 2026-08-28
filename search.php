<?php
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');

$title = "Web Search";
require_once 'include/config.php';
include 'include/' . HEADER_FILE;

function gs_param($keys, $default = '')
{
    foreach ($keys as $key) {
        if (isset($_GET[$key])) {
            return trim((string) $_GET[$key]);
        }
    }
    return $default;
}

function gs_escape_like($value)
{
    return str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $value);
}

function gs_table_exists($mysqli, $tableName)
{
    $sql = 'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1';
    $stmt = mysqli_prepare($mysqli, $sql);
    if ($stmt === false) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 's', $tableName);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $exists = ($res !== false && mysqli_num_rows($res) > 0);
    if ($res !== false) {
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);
    return $exists;
}

$queryText = gs_param(array('q', 'query', 'search', 'term', 's', 'text'), '');
$queryType = strtolower(gs_param(array('type', 'category', 'scope', 't'), 'all'));

$typeAlias = array(
    'person' => 'people',
    'avatars' => 'people',
    'avatar' => 'people',
    'group' => 'groups',
    'place' => 'places',
    'regions' => 'places',
    'region' => 'places',
    'classified' => 'classifieds',
    'classifieds' => 'classifieds',
);
if (isset($typeAlias[$queryType])) {
    $queryType = $typeAlias[$queryType];
}

$allowedTypes = array('all', 'people', 'groups', 'places', 'classifieds');
if (!in_array($queryType, $allowedTypes, true)) {
    $queryType = 'all';
}

$mysqli = @mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($mysqli === false) {
    http_response_code(500);
    echo 'Database connection failed.';
    exit;
}

$rows = array();
$warnings = array();

if ($queryText !== '') {
    $needle = '%' . gs_escape_like($queryText) . '%';

    if ($queryType === 'all' || $queryType === 'people') {
        $sql = 'SELECT FirstName, LastName FROM UserAccounts WHERE FirstName LIKE ? OR LastName LIKE ? ORDER BY FirstName, LastName LIMIT 100';
        $stmt = mysqli_prepare($mysqli, $sql);
        if ($stmt !== false) {
            mysqli_stmt_bind_param($stmt, 'ss', $needle, $needle);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res !== false) {
                while ($r = mysqli_fetch_assoc($res)) {
                    $rows[] = array(
                        'type' => 'People',
                        'name' => trim(($r['FirstName'] ?? '') . ' ' . ($r['LastName'] ?? '')),
                        'extra' => '',
                    );
                }
                mysqli_free_result($res);
            }
            mysqli_stmt_close($stmt);
        }
    }

    if ($queryType === 'all' || $queryType === 'groups') {
        $sql = 'SELECT Name, Charter FROM os_groups_groups WHERE Name LIKE ? OR Charter LIKE ? ORDER BY Name LIMIT 100';
        $stmt = mysqli_prepare($mysqli, $sql);
        if ($stmt !== false) {
            mysqli_stmt_bind_param($stmt, 'ss', $needle, $needle);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res !== false) {
                while ($r = mysqli_fetch_assoc($res)) {
                    $rows[] = array(
                        'type' => 'Groups',
                        'name' => (string) ($r['Name'] ?? ''),
                        'extra' => (string) ($r['Charter'] ?? ''),
                    );
                }
                mysqli_free_result($res);
            }
            mysqli_stmt_close($stmt);
        }
    }

    if ($queryType === 'all' || $queryType === 'places') {
        $sql = 'SELECT regionName, serverIP, serverPort FROM regions WHERE regionName LIKE ? ORDER BY regionName LIMIT 100';
        $stmt = mysqli_prepare($mysqli, $sql);
        if ($stmt !== false) {
            mysqli_stmt_bind_param($stmt, 's', $needle);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res !== false) {
                while ($r = mysqli_fetch_assoc($res)) {
                    $extra = (string) ($r['serverIP'] ?? '');
                    if (isset($r['serverPort']) && $r['serverPort'] !== '') {
                        $extra .= ':' . $r['serverPort'];
                    }
                    $rows[] = array(
                        'type' => 'Places',
                        'name' => (string) ($r['regionName'] ?? ''),
                        'extra' => $extra,
                    );
                }
                mysqli_free_result($res);
            }
            mysqli_stmt_close($stmt);
        }
    }

    if ($queryType === 'all' || $queryType === 'classifieds') {
        if (gs_table_exists($mysqli, 'classifieds')) {
            $sql = 'SELECT name, simname, parcelname FROM classifieds WHERE name LIKE ? OR description LIKE ? OR simname LIKE ? ORDER BY expirationdate DESC LIMIT 100';
            $stmt = mysqli_prepare($mysqli, $sql);
            if ($stmt !== false) {
                mysqli_stmt_bind_param($stmt, 'sss', $needle, $needle, $needle);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                if ($res !== false) {
                    while ($r = mysqli_fetch_assoc($res)) {
                        $rows[] = array(
                            'type' => 'Classifieds',
                            'name' => (string) ($r['name'] ?? ''),
                            'extra' => trim((string) ($r['simname'] ?? '') . ' / ' . (string) ($r['parcelname'] ?? ''), ' /'),
                        );
                    }
                    mysqli_free_result($res);
                }
                mysqli_stmt_close($stmt);
            }
        } elseif ($queryType === 'classifieds') {
            $warnings[] = 'Classifieds table not found in this database.';
        }
    }

}

mysqli_close($mysqli);
?>

<div class="content-card" style="max-width: 700px; margin: 0 auto;">
    <h2 class="mb-3"><i class="bi bi-search"></i> Grid Search</h2>
    <form method="get" class="d-flex gap-2 flex-wrap mb-3">
        <input type="text" name="q" class="form-control" style="flex: 1 1 220px;"
               value="<?php echo htmlspecialchars($queryText); ?>" placeholder="Search term..." required>
        <select name="type" class="form-select" style="flex: 0 1 160px;">
            <option value="all" <?php echo $queryType === 'all' ? 'selected' : ''; ?>>All</option>
            <option value="people" <?php echo $queryType === 'people' ? 'selected' : ''; ?>>People</option>
            <option value="groups" <?php echo $queryType === 'groups' ? 'selected' : ''; ?>>Groups</option>
            <option value="places" <?php echo $queryType === 'places' ? 'selected' : ''; ?>>Places</option>
            <option value="classifieds" <?php echo $queryType === 'classifieds' ? 'selected' : ''; ?>>Classifieds</option>
        </select>
        <button type="submit" class="btn btn-theme">Search</button>
    </form>

    <?php if ($queryText === ''): ?>
        <p class="text-muted">Search parameter can be passed by viewer as `q`, `query`, `search` or `term`.</p>
    <?php else: ?>
        <p class="text-muted">Results: <?php echo count($rows); ?></p>
        <?php foreach ($warnings as $warning): ?>
            <p class="text-muted"><?php echo htmlspecialchars($warning); ?></p>
        <?php endforeach; ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Name</th>
                        <th>Extra</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="3">No results found.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['type']); ?></td>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['extra']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include 'include/' . FOOTER_FILE; ?>
