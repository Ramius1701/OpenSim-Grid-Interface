<?php
// include/rss-feed.php
include_once "config.php";

// Grid timezone
date_default_timezone_set('Pacific/Auckland');

// ===== Load data =============================================================
$eventsRaw = @file_get_contents(PATH_EVENTS_JSON);
$eventsArr = json_decode($eventsRaw, true);
if (!is_array($eventsArr)) { $eventsArr = []; }

$annRaw = @file_get_contents(PATH_ANNOUNCEMENTS_JSON);
$annArr = json_decode($annRaw, true);
if (!is_array($annArr)) { $annArr = []; }

// ===== Normalizers ===========================================================
function norm_event(array $e): array {
    $title = isset($e['title']) && $e['title'] !== '' ? $e['title'] : ($e['texts'][0] ?? 'Update');
    $desc  = isset($e['description']) ? $e['description'] : ($e['texts'][1] ?? '');
    $date  = $e['date'] ?? ''; // YYYY-MM-DD
    $time  = isset($e['time']) && $e['time'] !== '' ? $e['time'] : '00:00'; // default for sorting
    $ts    = $date ? strtotime($date . ' ' . $time) : 0;
    $priority = isset($e['priority']) ? (int)$e['priority'] : 0;
    $type  = $e['type'] ?? 'event';

    return [
        'kind'     => 'event',
        'type'     => $type,
        'priority' => $priority,
        'title'    => $title,
        'desc'     => $desc,
        'date'     => $date,
        'time'     => $time,      // used for display only if explicitly present in JSON
        'ts'       => $ts,        // start timestamp
        'end_ts'   => $ts,        // events are point-in-time by default
        'link'     => $e['link']  ?? '',
        'image'    => $e['image'] ?? '',
        'raw'      => $e,
    ];
}

function norm_announcement(array $a): array {
    // Minimal schema: title, message, start, (optional) end, (optional) start_time/end_time
    $title = $a['title']   ?? 'Announcement';
    $desc  = $a['message'] ?? '';
    $start = $a['start']   ?? ''; // YYYY-MM-DD
    $end   = $a['end']     ?? $start;

    $stime = isset($a['start_time']) && $a['start_time'] !== '' ? $a['start_time'] : '00:00';
    $etime = isset($a['end_time'])   && $a['end_time']   !== '' ? $a['end_time']   : '23:59';

    $start_ts = $start ? strtotime($start . ' ' . $stime) : 0;
    $end_ts   = $end   ? strtotime($end   . ' ' . $etime) : $start_ts;

    $priority = isset($a['priority']) ? (int)$a['priority'] : 0;
    $type     = $a['type'] ?? 'news';

    return [
        'kind'     => 'announcement',
        'type'     => $type,      // e.g., news|maintenance|sale
        'priority' => $priority,  // higher shows before lower when dates tie
        'title'    => $title,
        'desc'     => $desc,
        'date'     => $start,     // group under start date for HTML view
        'time'     => $stime,     // only show if explicitly provided
        'ts'       => $start_ts,  // window start
        'end_ts'   => $end_ts,    // window end
        'link'     => $a['link']  ?? '',
        'image'    => '',
        'raw'      => $a,
    ];
}

// Normalize and merge
$events = array_map('norm_event', $eventsArr);
$anns   = array_map('norm_announcement', $annArr);
$all    = array_merge($events, $anns);

// Sort by ts asc with stable tie-break (title) to make selection deterministic
usort($all, function($a,$b){
    if ($a['ts'] === $b['ts']) return strcasecmp($a['title'], $b['title']);
    return $a['ts'] <=> $b['ts'];
});

// ===== Selection logic =======================================================
$nowTs = time();
$MAX_ITEMS = 10;

// Upcoming/active: Events with ts >= now, OR announcements whose window includes now or starts in future
$upcoming = array_values(array_filter($all, function($e) use ($nowTs) {
    if ($e['kind'] === 'announcement') {
        // show if active now or starting in the future
        return ($e['ts'] >= $nowTs) || ($e['ts'] <= $nowTs && $nowTs <= $e['end_ts']);
    } else {
        return $e['ts'] >= $nowTs;
    }
}));

// Sort upcoming by priority(desc), then ts, then title
usort($upcoming, function($a,$b){
    if ($a['priority'] !== $b['priority']) return $b['priority'] <=> $a['priority'];
    if ($a['ts'] === $b['ts']) return strcasecmp($a['title'], $b['title']);
    return $a['ts'] <=> $b['ts'];
});

// Cap list to MAX_ITEMS, but keep ties on both priority+ts together
$items = [];
foreach ($upcoming as $e) {
    if (count($items) < $MAX_ITEMS) {
        $items[] = $e;
    } else {
        $last = $items[count($items)-1];
        if ($e['priority'] === $last['priority'] && $e['ts'] === $last['ts']) {
            $items[] = $e;
        } else {
            break;
        }
    }
}

// Fallback if no upcoming/active: show most recent past DAY’s items (events or announcements by start date)
if (!$items) {
    $past = array_values(array_filter($all, fn($e) => $e['ts'] && $e['ts'] <= $nowTs));
    if ($past) {
        usort($past, fn($a,$b)=> $b['ts'] <=> $a['ts']); // newest first
        $lastDay = date('Y-m-d', $past[0]['ts']);
        $items = array_values(array_filter($past, fn($e)=> $e['date'] === $lastDay));
        // order within the day by priority desc, then time asc/title
        usort($items, function($a,$b){
            if ($a['priority'] !== $b['priority']) return $b['priority'] <=> $a['priority'];
            if ($a['ts'] === $b['ts']) return strcasecmp($a['title'], $b['title']);
            return $a['ts'] <=> $b['ts'];
        });
    }
}

// ===== HTML mode (for welcome widget) =======================================
// Use in config.php: define('RSS_FEED_URL', '/osviewer/include/rss-feed.php?format=html');
if (isset($_GET['format']) && strtolower($_GET['format']) === 'html') {
    header('Content-Type: text/html; charset=UTF-8');
    if (!$items) { echo '<p>No updates available.</p>'; exit; }

    // Group by date for readability
    $byDate = [];
    foreach ($items as $e) { $byDate[$e['date']][] = $e; }

    foreach ($byDate as $day => $list) {
        $prettyDay = $day ? date('M j, Y', strtotime($day)) : '';
        echo '<div class="daily-updates-group">';
        if ($prettyDay) echo '<h4 class="mb-2">'.htmlspecialchars($prettyDay, ENT_QUOTES, 'UTF-8').'</h4>';
        echo '<ul class="daily-updates-list">';
        foreach ($list as $e) {
            $typeLabel = strtoupper($e['type']);
            $hasExplicitTime = ($e['kind']==='event' && !empty($e['raw']['time'])) ||
                               ($e['kind']==='announcement' && !empty($e['raw']['start_time']));

            // For events: prefer description or texts[1] (skip if date-like), else texts[2]
            // For announcements: use message as-is
            $descResolved = $e['desc'];
            if ($e['kind'] === 'event') {
                if ($descResolved === '') {
                    $t1 = $e['raw']['texts'][1] ?? '';
                    $t2 = $e['raw']['texts'][2] ?? '';
                    $isDateLike = false;
                    if ($t1 !== '') {
                        if (preg_match('/^\d{1,2}\.\d{1,2}\.\d{4}$/', $t1)) {
                            $isDateLike = true;
                        } else {
                            $t1ts = strtotime($t1);
                            $isDateLike = $t1ts && !empty($e['date']) && date('Y-m-d', $t1ts) === $e['date'];
                        }
                    }
                    $descResolved = $isDateLike ? $t2 : $t1;
                }
            }

            echo '<li class="daily-update-item">';
            echo '<span class="type-badge">'.htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8').'</span> ';
            echo '<strong>' . htmlspecialchars($e['title'], ENT_QUOTES, 'UTF-8') . '</strong>';
            if ($hasExplicitTime) {
                echo ' — <span class="time">' . htmlspecialchars($e['time'], ENT_QUOTES, 'UTF-8') . '</span>';
            }
            if ($descResolved !== '') {
                echo '<div class="desc">' . htmlspecialchars($descResolved, ENT_QUOTES, 'UTF-8') . '</div>';
            }
            if (!empty($e['link'])) {
                echo '<div><a href="' . htmlspecialchars($e['link'], ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">Read more</a></div>';
            }
            echo '</li>';
        }
        echo '</ul></div>';
    }
    exit;
}

// ===== RSS mode (default) ====================================================
$channelTitle = defined('CALENDAR_TITLE') ? CALENDAR_TITLE : 'Calendar';
$baseUrl = defined('BASE_URL') ? BASE_URL : '/';

header('Content-Type: application/rss+xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0">
  <channel>
    <title><?= htmlspecialchars($channelTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link><?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?></link>
    <description>RSS feed with calendar events & announcements</description>
    <language>en-us</language>

<?php foreach ($items as $e): ?>
    <item>
      <title><?= htmlspecialchars($e['title'], ENT_QUOTES, 'UTF-8') ?></title>
<?php if (!empty($e['link'])): ?>
      <link><?= htmlspecialchars($e['link'], ENT_QUOTES, 'UTF-8') ?></link>
<?php endif; ?>
      <description><?= htmlspecialchars($e['desc'], ENT_QUOTES, 'UTF-8') ?></description>
<?php if (!empty($e['ts'])): ?>
      <pubDate><?= date('r', $e['ts']) ?></pubDate>
<?php endif; ?>
      <category><?= htmlspecialchars($e['type'], ENT_QUOTES, 'UTF-8') ?></category>
<?php if (!empty($e['image'])): ?>
      <enclosure url="<?= htmlspecialchars(rtrim($baseUrl, '/') . '/' . ltrim($e['image'], '/'), ENT_QUOTES, 'UTF-8') ?>" type="image/jpeg" />
<?php endif; ?>
    </item>
<?php endforeach; ?>

  </channel>
</rss>
