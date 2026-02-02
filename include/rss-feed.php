<?php
// include/rss-feed.php
include_once "config.php";

// Grid timezone is controlled by include/config.php (GRID_TIMEZONE / APP_TIMEZONE).
$__NOW_TS = time();

// ===== Load data (holidays + announcements) ========================================
$eventsRaw = @file_get_contents(PATH_EVENTS_JSON);
$eventsArr = json_decode($eventsRaw, true);
if (!is_array($eventsArr)) { $eventsArr = []; }

$annRaw = @file_get_contents(PATH_ANNOUNCEMENTS_JSON);
$annArr = json_decode($annRaw, true);
if (!is_array($annArr)) { $annArr = []; }

// Shared window size (days) for Daily Updates. Used both for HTML widget (unlimited items) and RSS (capped).
$WINDOW_DAYS = defined('DAILY_UPDATE_WINDOW_DAYS') ? max(0, (int)DAILY_UPDATE_WINDOW_DAYS) : 7;

// ===== Normalizers ===========================================================
// NOTE: holiday.json entries are intended to recur annually. Some entries also have a "rule" field (e.g. Easter-based dates)
// which must be recalculated each year. This keeps Daily Updates working past 2025.

function calc_easter_sunday(int $year): DateTimeImmutable {
    // Anonymous Gregorian algorithm
    $a = $year % 19;
    $b = intdiv($year, 100);
    $c = $year % 100;
    $d = intdiv($b, 4);
    $e = $b % 4;
    $f = intdiv($b + 8, 25);
    $g = intdiv($b - $f + 1, 3);
    $h = (19 * $a + $b - $d - $g + 15) % 30;
    $i = intdiv($c, 4);
    $k = $c % 4;
    $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
    $m = intdiv($a + 11 * $h + 22 * $l, 451);
    $month = intdiv($h + $l - 7 * $m + 114, 31); // 3=Mar, 4=Apr
    $day = (($h + $l - 7 * $m + 114) % 31) + 1;
    return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
}

function calc_rule_date(string $rule, int $year): ?DateTimeImmutable {
    $rule = trim(strtolower($rule));
    $tz = new DateTimeZone(date_default_timezone_get());

    if ($rule === 'easter_sunday') return calc_easter_sunday($year)->setTimezone($tz);
    if ($rule === 'good_friday') return calc_easter_sunday($year)->modify('-2 days')->setTimezone($tz);
    if ($rule === 'easter_monday') return calc_easter_sunday($year)->modify('+1 day')->setTimezone($tz);
    if ($rule === 'pentecost_sunday') return calc_easter_sunday($year)->modify('+49 days')->setTimezone($tz);
    if ($rule === 'pentecost_monday') return calc_easter_sunday($year)->modify('+50 days')->setTimezone($tz);

    if ($rule === 'programmers_day_256') {
        return (new DateTimeImmutable(sprintf('%04d-01-01', $year), $tz))->modify('+255 days');
    }
    if ($rule === 'sysadmin_day_last_fri_july') {
        return (new DateTimeImmutable(sprintf('%04d-07-31', $year), $tz))->modify('last friday');
    }
    if ($rule === 'us_thanksgiving') {
        $dt = new DateTimeImmutable(sprintf('%04d-11-01', $year), $tz);
        $w = (int)$dt->format('N');
        $delta = (4 - $w + 7) % 7; // 4 = Thursday
        $firstThu = $dt->modify('+' . $delta . ' days');
        return $firstThu->modify('+21 days'); // 4th Thursday
    }
    if ($rule === 'labour_day_sept_first_monday') {
        $dt = new DateTimeImmutable(sprintf('%04d-09-01', $year), $tz);
        $w = (int)$dt->format('N');
        $delta = (1 - $w + 7) % 7; // 1 = Monday
        return $dt->modify('+' . $delta . ' days');
    }
    if ($rule === 'national_donut_day') {
        $dt = new DateTimeImmutable(sprintf('%04d-06-01', $year), $tz);
        $w = (int)$dt->format('N');
        $delta = (5 - $w + 7) % 7; // 5 = Friday
        return $dt->modify('+' . $delta . ' days');
    }

    return null; // unknown rule
}

function next_holiday_occurrence_date(array $e, string $startDateYmd): string {
    $startYear = (int)substr($startDateYmd, 0, 4);
    $rawDate = (string)($e['date'] ?? '');
    if ($rawDate === '') return '';

    // Rule-based holidays must be recalculated yearly.
    if (!empty($e['rule'])) {
        $dt = calc_rule_date((string)$e['rule'], $startYear);
        if ($dt instanceof DateTimeImmutable) {
            $ymd = $dt->format('Y-m-d');
            if ($ymd < $startDateYmd) {
                $dt2 = calc_rule_date((string)$e['rule'], $startYear + 1);
                if ($dt2 instanceof DateTimeImmutable) return $dt2->format('Y-m-d');
            }
            return $ymd;
        }
        // fall through to fixed-date rolling if rule is unknown
    }

    // Fixed month/day rolling (covers fandom/awareness/etc.)
    $m = (int)substr($rawDate, 5, 2);
    $d = (int)substr($rawDate, 8, 2);

    for ($i = 0; $i < 12; $i++) {
        $y = $startYear + $i;
        if (!checkdate($m, $d, $y)) continue;
        $candidate = sprintf('%04d-%02d-%02d', $y, $m, $d);
        if ($candidate >= $startDateYmd) return $candidate;
    }

    return $rawDate;
}

function norm_event(array $e): array {
    global $__NOW_TS;

    $title = isset($e['title']) && $e['title'] !== '' ? $e['title'] : ($e['texts'][0] ?? 'Update');
    $desc  = isset($e['description']) ? (string)$e['description'] : (string)($e['texts'][1] ?? '');

    $startDateYmd = date('Y-m-d', (int)$__NOW_TS);
    $date = next_holiday_occurrence_date($e, $startDateYmd); // YYYY-MM-DD (rolled forward)

    // Use explicit JSON time when provided, otherwise pick midday for stable sorting (and to avoid "already passed today" issues).
    $hasExplicitTime = isset($e['time']) && $e['time'] !== '';
    $time = $hasExplicitTime ? (string)$e['time'] : '12:00';
    $ts = $date ? (int)strtotime($date . ' ' . $time) : 0;

    $priority = isset($e['priority']) ? (int)$e['priority'] : 0;
    $type  = $e['type'] ?? 'event';

    return [
        'kind'     => 'event',
        'type'     => $type,
        'priority' => $priority,
        'title'    => $title,
        'desc'     => $desc,
        'date'     => $date,
        'time'     => $time,
        'ts'       => $ts,
        'end_ts'   => $ts,
        'link'     => $e['link']  ?? '',
        'image'    => $e['image'] ?? '',
        // Keep original raw structure, but update the rolled date so downstream logic (and debugging) matches what we display.
        'raw'      => array_merge($e, ['date' => $date]),
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

function norm_viewer_event(array $r): array {
    // Events stored in OpenSim search DB (search_events). dateUTC is epoch seconds (UTC).
    $title = $r['Name'] ?? $r['name'] ?? 'Event';
    $desc  = $r['Description'] ?? $r['description'] ?? '';
    $category = (string)($r['Category'] ?? $r['category'] ?? '');
    $sim = (string)($r['SimName'] ?? $r['simname'] ?? '');

    $ts = 0;
    if (isset($r['DateUTC'])) $ts = (int)$r['DateUTC'];
    elseif (isset($r['dateUTC'])) $ts = (int)$r['dateUTC'];

    $durationMin = 0;
    if (isset($r['Duration'])) $durationMin = (int)$r['Duration'];
    elseif (isset($r['duration'])) $durationMin = (int)$r['duration'];

    $endTs = $ts ? ($ts + max(0, $durationMin) * 60) : 0;
    $date = $ts ? date('Y-m-d', $ts) : '';
    $time = $ts ? date('H:i', $ts) : '';

    $where = trim($sim);
    if ($category !== '') {
        $where = $where !== '' ? ($where . ' · ' . $category) : $category;
    }

    $descResolved = trim((string)$desc);
    if ($where !== '') {
        $descResolved = $descResolved !== '' ? ($where . ' — ' . $descResolved) : $where;
    }

    return [
        'kind'     => 'viewer_event',
        'type'     => 'event',
        'priority' => 0,
        'title'    => (string)$title,
        'desc'     => $descResolved,
        'date'     => $date,
        'time'     => $time,
        'ts'       => $ts,
        'end_ts'   => $endTs,
        'link'     => '',
        'image'    => '',
        'raw'      => array_merge($r, ['time' => $time, 'category' => $category, 'simname' => $sim]),
    ];
}

// Normalize and merge
$events = array_map('norm_event', $eventsArr);
$anns   = array_map('norm_announcement', $annArr);

// Viewer/Grid events (from DB) are also part of the events calendar; include them if DB access is available.
$viewerRows = [];
if (function_exists('db') && function_exists('mysqli_connect')) {
    $conn = db();
    if ($conn) {
        $qStart = strtotime('today 00:00');
        if ($qStart === false) { $qStart = $__NOW_TS; }
        $qEnd = strtotime('today 23:59:59');
        if ($qEnd === false) { $qEnd = $__NOW_TS; }
        $qEnd = $qEnd + ($WINDOW_DAYS * 86400);
        // Include events that started yesterday (in case they run overnight into today).
        $qStart = max(0, $qStart - 86400);

	    $sql = "SELECT eventid AS EventID, name AS Name, category AS Category, description AS Description, dateUTC AS DateUTC, duration AS Duration, simname AS SimName " .
	           "FROM search_events " .
	           "WHERE dateUTC >= " . (int)$qStart . " AND dateUTC <= " . (int)$qEnd . " " .
	           "ORDER BY dateUTC ASC LIMIT 2000";
        $res = @mysqli_query($conn, $sql);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) $viewerRows[] = $row;
            mysqli_free_result($res);
        }
    }
}
$viewer = $viewerRows ? array_map('norm_viewer_event', $viewerRows) : [];

$all    = array_merge($events, $anns, $viewer);

// Sort by ts asc with stable tie-break (title) to make selection deterministic
usort($all, function($a,$b){
    if ($a['ts'] === $b['ts']) return strcasecmp($a['title'], $b['title']);
    return $a['ts'] <=> $b['ts'];
});

// ===== Selection logic =======================================================
$nowTs = time();

// If the welcome widget requests HTML, show *all* items for the upcoming calendar window.
// RSS output stays capped (unless overridden via DAILY_UPDATE_MAX_ITEMS).
$isHtml = (isset($_GET['format']) && strtolower((string)$_GET['format']) === 'html');
$MAX_ITEMS = defined('DAILY_UPDATE_MAX_ITEMS') ? max(0, (int)DAILY_UPDATE_MAX_ITEMS) : 10;
if ($isHtml) { $MAX_ITEMS = 0; }

// Windowing rules:
// - Daily Updates (HTML) should show EVERYTHING happening today and over the next N days.
// - RSS uses the same time window but stays capped via DAILY_UPDATE_MAX_ITEMS.

$windowStartTs = strtotime('today 00:00');
if ($windowStartTs === false) { $windowStartTs = $nowTs; }
$windowStartDate = date('Y-m-d', $windowStartTs);

$windowEndTs = strtotime('today 23:59:59');
if ($windowEndTs === false) { $windowEndTs = $nowTs; }
$windowEndTs = $windowEndTs + ($WINDOW_DAYS * 86400);
$windowEndDate = date('Y-m-d', $windowEndTs);

// Upcoming/active within the rolling N-day window.
// - Announcements: include if their active range intersects the window.
// - Viewer events: include if their active range intersects the window.
// - Holidays/observances: include if their DATE falls within the window (date-level, not time-of-day).
$upcoming = array_values(array_filter($all, function($e) use ($windowStartTs, $windowEndTs, $windowStartDate, $windowEndDate) {
    if ($e['kind'] === 'announcement') {
        return ($e['end_ts'] >= $windowStartTs) && ($e['ts'] <= $windowEndTs);
    }
    if ($e['kind'] === 'viewer_event') {
        return ($e['end_ts'] >= $windowStartTs) && ($e['ts'] <= $windowEndTs);
    }
    if (!empty($e['date'])) {
        return ($e['date'] >= $windowStartDate) && ($e['date'] <= $windowEndDate);
    }
    return false;
}));

// Sort upcoming: HTML view prefers chronological; RSS keeps "priority first".
if ($isHtml) {
    usort($upcoming, function($a,$b){
        if ($a['ts'] === $b['ts']) {
            if ($a['priority'] !== $b['priority']) return $b['priority'] <=> $a['priority'];
            return strcasecmp($a['title'], $b['title']);
        }
        return $a['ts'] <=> $b['ts'];
    });
} else {
    usort($upcoming, function($a,$b){
        if ($a['priority'] !== $b['priority']) return $b['priority'] <=> $a['priority'];
        if ($a['ts'] === $b['ts']) return strcasecmp($a['title'], $b['title']);
        return $a['ts'] <=> $b['ts'];
    });
}

// Cap list to MAX_ITEMS (RSS) but allow unlimited for HTML widget (MAX_ITEMS=0).
$items = $upcoming;
if ($MAX_ITEMS > 0) {
    $capped = [];
    foreach ($upcoming as $e) {
        if (count($capped) < $MAX_ITEMS) {
            $capped[] = $e;
        } else {
            $last = $capped[count($capped)-1];
            if ($e['priority'] === $last['priority'] && $e['ts'] === $last['ts']) {
                $capped[] = $e;
            } else {
                break;
            }
        }
    }
    $items = $capped;
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
if ($isHtml) {
    header('Content-Type: text/html; charset=UTF-8');
    if (!$items) { echo '<p>No updates available.</p>'; exit; }

    // Group by date for readability
    $byDate = [];
    foreach ($items as $e) { $byDate[$e['date']][] = $e; }

    // Ensure groups are shown in chronological order
    uksort($byDate, function($a, $b) {
        $at = $a ? strtotime($a) : PHP_INT_MAX;
        $bt = $b ? strtotime($b) : PHP_INT_MAX;
        return $at <=> $bt;
    });

    foreach ($byDate as $day => $list) {
        $prettyDay = $day ? date('M j, Y', strtotime($day)) : '';
        echo '<div class="daily-updates-group">';
        if ($prettyDay) echo '<h4 class="mb-2">'.htmlspecialchars($prettyDay, ENT_QUOTES, 'UTF-8').'</h4>';
        echo '<ul class="daily-updates-list">';
        foreach ($list as $e) {
            $typeLabel = strtoupper($e['type']);
			$hasExplicitTime = ($e['kind']==='event' && !empty($e['raw']['time'])) ||
			                   ($e['kind']==='viewer_event' && !empty($e['time'])) ||
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
						if ($t1ts) {
							// Treat common date-formats as "date-like" even if the year is old (e.g. "April 18, 2025").
							$looksLikeDate = preg_match('/\b\d{4}\b/', $t1) || preg_match('/\b(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec)\b/i', $t1);
							$isDateLike = $looksLikeDate || (!empty($e['date']) && date('Y-m-d', $t1ts) === $e['date']);
						}
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