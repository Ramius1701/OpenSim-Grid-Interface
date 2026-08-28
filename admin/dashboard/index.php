<?php
// Hardened cookie params must be set before the FIRST session_start() of the
// request - ssinc.php (included below) pulls in include/auth.php, which
// starts a session with PHP's defaults if nothing has started one yet. That
// used to happen first, silently making this block's session_start() call a
// no-op (session_status() was already PHP_SESSION_ACTIVE by the time it
// ran), so cookie_httponly/cookie_secure/cookie_samesite were never applied.
if (session_status() === PHP_SESSION_NONE) {
	session_start([
		'cookie_httponly' => true,
		'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
		'cookie_samesite' => 'Strict',
	]);
}

include_once 'ssinc.php';
// Statistics dashboard for the OpenSimulator grid database

// Additional security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

// Basic CSRF protection for POST forms (in case one is added later)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
		http_response_code(403);
		exit('Invalid CSRF token.');
	}
}

// Load regions
$sql_regions = 'SELECT uuid, regionName, locX, locY, sizeX, sizeY, serverIP, serverPort FROM regions ORDER BY regionName';
$regions = $pdo->query($sql_regions)->fetchAll(PDO::FETCH_ASSOC);

// Online users (Presence, empty if nobody's online)
// Windowed to the last 5 minutes, matching admin/analytics.php's
// convention - without this, a Presence row OpenSim never cleaned up
// after an ungraceful disconnect would show that user as "online"
// indefinitely. LastSeen is a real TIMESTAMP column, not a raw unix int -
// comparing it against UNIX_TIMESTAMP()-N (as admin/analytics.php used to,
// also now fixed) silently coerces the timestamp to a string and very
// nearly always evaluates true, regardless of actual recency. Compare
// against another TIMESTAMP/DATETIME expression instead.
$sql_online = "SELECT p.UserID, ua.FirstName, ua.LastName, p.RegionID, r.regionName, p.LastSeen
FROM Presence p
LEFT JOIN UserAccounts ua ON p.UserID = ua.PrincipalID
LEFT JOIN regions r ON p.RegionID = r.uuid
WHERE p.LastSeen >= (NOW() - INTERVAL 300 SECOND)
ORDER BY p.LastSeen DESC";
$online = $pdo->query($sql_online)->fetchAll(PDO::FETCH_ASSOC);

// GridUser with status (Online/Offline)
$sql_griduser = 'SELECT gu.UserID, gu.LastRegionID, gu.Login, gu.Logout, gu.Online, ua.FirstName, ua.LastName
FROM GridUser gu
LEFT JOIN UserAccounts ua ON gu.UserID = ua.PrincipalID';
$gridusers = $pdo->query($sql_griduser)->fetchAll(PDO::FETCH_ASSOC);


// Load MuteList
$sql_mute = 'SELECT m.AgentID, m.MuteID, m.MuteName, m.MuteType, ua.FirstName, ua.LastName FROM MuteList m LEFT JOIN UserAccounts ua ON m.MuteID = ua.PrincipalID';
$mutelist = $pdo->query($sql_mute)->fetchAll(PDO::FETCH_ASSOC);


// Load groups
$sql_groups = 'SELECT * FROM os_groups_groups ORDER BY Name ASC, Charter ASC';
$groups = $pdo->query($sql_groups)->fetchAll(PDO::FETCH_ASSOC);

// Load user information
$sql_userinfo = 'SELECT * FROM userinfo ORDER BY simip ASC, avatar ASC, serverurl DESC';
$userinfo = $pdo->query($sql_userinfo)->fetchAll(PDO::FETCH_ASSOC);

$title = "Statistics Dashboard";
require_once __DIR__ . '/../../include/header.php';
?>

<style>
	.stats-table th { cursor: pointer; user-select: none; }
	.stats-table th.sorted-asc:after { content: " \25B2"; }
	.stats-table th.sorted-desc:after { content: " \25BC"; }
	.stats-table td, .stats-table th { font-size: 0.85rem; }
</style>

<section class="page-hero">
	<div class="d-flex justify-content-center align-items-center gap-3 flex-wrap">
		<div>
			<h1><i class="bi bi-bar-chart-line me-2"></i> Statistics Dashboard</h1>
			<p class="mb-0">Live statistics for regions and users, read directly from the grid database.</p>
		</div>
	</div>
</section>

<div class="container-fluid mt-4 mb-4">
	<div class="row">
		<div class="col-md-3">

			<div class="card mb-3">
				<div class="card-header">
					<h5 class="mb-0"><i class="bi bi-info-circle me-1"></i> Jump to section</h5>
				</div>
				<div class="card-body small">
					<div class="list-group list-group-flush mb-3">
						<a href="#regions" class="list-group-item list-group-item-action"><i class="bi bi-map me-1"></i> Regions (<?php echo count($regions); ?>)</a>
						<a href="#groups" class="list-group-item list-group-item-action"><i class="bi bi-people me-1"></i> Groups (<?php echo count($groups); ?>)</a>
						<a href="#online" class="list-group-item list-group-item-action"><i class="bi bi-person-check me-1"></i> Online members (<?php echo count($online); ?>)</a>
						<a href="#userinfo" class="list-group-item list-group-item-action"><i class="bi bi-person-lines-fill me-1"></i> User information (<?php echo count($userinfo); ?>)</a>
						<a href="#griduser" class="list-group-item list-group-item-action"><i class="bi bi-person-vcard me-1"></i> GridUser (<?php echo count($gridusers); ?>)</a>
						<a href="#mutelist" class="list-group-item list-group-item-action"><i class="bi bi-volume-mute me-1"></i> MuteList (<?php echo count($mutelist); ?>)</a>
					</div>
					<div class="alert alert-info py-2 px-2 mb-3">
						<strong>Tip:</strong> click a column header in any table below to sort by that column.
					</div>
					<details>
						<?php include __DIR__ . '/../_admin_shortcuts.php'; ?>
					</details>
				</div>
			</div>
		</div>

		<div class="col-md-9">

			<div class="card mb-3" id="regions">
				<div class="card-header">
					<h5 class="mb-0"><i class="bi bi-map me-1"></i> Regions in grid</h5>
				</div>
				<div class="card-body">
					<div class="table-responsive">
						<table class="table table-sm table-striped align-middle stats-table">
							<thead>
								<tr>
									<th>Name</th>
									<th>UUID</th>
									<th>Position</th>
									<th>Size</th>
									<th>Server IP</th>
									<th>Port</th>
								</tr>
							</thead>
							<tbody>
							<?php if (empty($regions)): ?>
								<tr><td colspan="6" class="text-center">There are currently no regions in the grid.</td></tr>
							<?php else: ?>
								<?php foreach ($regions as $region): ?>
									<tr>
										<td><?= htmlspecialchars($region['regionName']) ?></td>
										<td class="small"><?= htmlspecialchars($region['uuid']) ?></td>
										<td><?= htmlspecialchars($region['locX']) ?>, <?= htmlspecialchars($region['locY']) ?></td>
										<td><?= htmlspecialchars($region['sizeX']) ?> x <?= htmlspecialchars($region['sizeY']) ?></td>
										<td><?= htmlspecialchars($region['serverIP']) ?></td>
										<td><?= htmlspecialchars($region['serverPort']) ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<div class="card mb-3" id="groups">
				<div class="card-header">
					<h5 class="mb-0"><i class="bi bi-people me-1"></i> Groups in grid</h5>
				</div>
				<div class="card-body">
					<?php if (empty($groups)): ?>
						<div class="alert alert-warning mb-0">There are currently no groups in the grid.</div>
					<?php else: ?>
					<div class="table-responsive">
						<table class="table table-sm table-striped align-middle stats-table">
							<thead>
								<tr>
									<th>Name</th>
									<th>Charter</th>
									<th>Founder (FounderID)</th>
									<th>Location</th>
									<th>InsigniaID</th>
									<th>Membership fee</th>
									<th>Open</th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ($groups as $group): ?>
								<tr>
									<td><?= htmlspecialchars($group['Name']) ?></td>
									<td><?= htmlspecialchars($group['Charter']) ?></td>
									<td class="small"><?= htmlspecialchars($group['FounderID']) ?></td>
									<td><?= htmlspecialchars($group['Location']) ?></td>
									<td class="small"><?= htmlspecialchars($group['InsigniaID']) ?></td>
									<td><?= htmlspecialchars($group['MembershipFee']) ?></td>
									<td><?= htmlspecialchars($group['OpenEnrollment']) ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="card mb-3" id="online">
				<div class="card-header">
					<h5 class="mb-0"><i class="bi bi-person-check me-1"></i> Online members</h5>
				</div>
				<div class="card-body">
					<?php if (empty($online)): ?>
						<div class="alert alert-warning mb-0">Nobody is currently in the grid.</div>
					<?php else: ?>
					<div class="table-responsive">
						<table class="table table-sm table-striped align-middle stats-table">
							<thead>
								<tr>
									<th>Name</th>
									<th>UserID</th>
									<th>Region</th>
									<th>Region name</th>
									<th>Last activity</th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ($online as $user): ?>
								<tr>
									<td>
									<?php
									$name = trim(($user['FirstName'] ?? '') . ' ' . ($user['LastName'] ?? ''));
									if ($name === '' && !empty($user['UserID'])) {
										// Look up the name in GridUser by UserID
										$gridName = '';
										foreach ($gridusers as $gu) {
											if (isset($gu['UserID'])) {
												$parts = explode(';', $gu['UserID']);
												if ($parts[0] === $user['UserID'] && isset($parts[2])) {
													$gridName = $parts[2];
													break;
												}
											}
										}
										echo htmlspecialchars($gridName !== '' ? $gridName : $user['UserID']);
									} else {
										echo htmlspecialchars($name);
									}
									?>
									</td>
									<td class="small"><?= htmlspecialchars($user['UserID']) ?></td>
									<td class="small"><?= htmlspecialchars($user['RegionID']) ?></td>
									<td><?= htmlspecialchars($user['regionName']) ?></td>
									<td><?= htmlspecialchars($user['LastSeen']) ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="card mb-3" id="userinfo">
				<div class="card-header">
					<h5 class="mb-0"><i class="bi bi-person-lines-fill me-1"></i> User information</h5>
				</div>
				<div class="card-body">
					<?php if (empty($userinfo)): ?>
						<div class="alert alert-warning mb-0">There is currently no user information in the grid.</div>
					<?php else: ?>
					<div class="table-responsive">
						<table class="table table-sm table-striped align-middle stats-table">
							<thead>
								<tr>
									<th>Avatar</th>
									<th>Server URL</th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ($userinfo as $info): ?>
								<tr>
									<td><?= htmlspecialchars($info['avatar']) ?></td>
									<td><?= htmlspecialchars($info['serverurl']) ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="card mb-3" id="griduser">
				<div class="card-header">
					<h5 class="mb-0"><i class="bi bi-person-vcard me-1"></i> All GridUser records</h5>
				</div>
				<div class="card-body">
					<div class="table-responsive">
						<table class="table table-sm table-striped align-middle stats-table">
							<thead>
								<tr>
									<th>Status</th>
									<th>Name</th>
									<th>User ID</th>
									<th>Home address</th>
									<th>Full name</th>
									<th>Last region</th>
									<th>Login</th>
									<th>Logout</th>
								</tr>
							</thead>
							<tbody>
							<?php if (empty($gridusers)): ?>
								<tr><td colspan="8" class="text-center">There are currently no GridUser records in the grid.</td></tr>
							<?php else: ?>
								<?php foreach ($gridusers as $user): ?>
									<?php
										$userId = $home = $fullName = '';
										if (!empty($user['UserID'])) {
											$parts = explode(';', $user['UserID']);
											$userId = $parts[0] ?? '';
											$home = $parts[1] ?? '';
											$fullName = $parts[2] ?? '';
										}
									?>
									<tr>
										<td class="text-center">
											<?php
											$isOnline = false;
											$originalOnline = isset($user['Online']) ? $user['Online'] : '';
											if (isset($user['Online'])) {
												$val = strtolower(trim((string)$user['Online']));
												$onlineValues = ['1', 'true', 'yes', 'y'];
												$isOnline = in_array($val, $onlineValues, true);
												// Also check numerically (e.g. int 1)
												if (!$isOnline && is_numeric($user['Online'])) {
													$isOnline = ((int)$user['Online']) === 1;
												}
											}
											?>
											<?php if ($isOnline): ?>
												<span class="badge bg-success" title="Online (DB: <?=htmlspecialchars($originalOnline)?>)">Online</span>
											<?php else: ?>
												<span class="badge bg-secondary" title="Offline (DB: <?=htmlspecialchars($originalOnline)?>)">Offline</span>
											<?php endif; ?>
										</td>
										<td><?= htmlspecialchars(($user['FirstName'] ?? '') . ' ' . ($user['LastName'] ?? '')) ?></td>
										<td class="small"><?= htmlspecialchars($userId) ?></td>
										<td class="small"><?= htmlspecialchars($home) ?></td>
										<td><?= htmlspecialchars($fullName) ?></td>
										<td class="small"><?= htmlspecialchars($user['LastRegionID']) ?></td>
										<td class="small"><?= htmlspecialchars($user['Login']) ?></td>
										<td class="small"><?= htmlspecialchars($user['Logout']) ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<div class="card mb-3" id="mutelist">
				<div class="card-header">
					<h5 class="mb-0"><i class="bi bi-volume-mute me-1"></i> Muted users (MuteList)</h5>
				</div>
				<div class="card-body">
					<?php if (empty($mutelist)): ?>
						<div class="alert alert-warning mb-0">Nobody in the grid is currently muted.</div>
					<?php else: ?>
					<div class="table-responsive">
						<table class="table table-sm table-striped align-middle stats-table">
							<thead>
								<tr>
									<th>Muted by (AgentID)</th>
									<th>Muted (MuteID)</th>
									<th>Name</th>
									<th>MuteName</th>
									<th>MuteType</th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ($mutelist as $mute): ?>
								<tr>
									<td class="small"><?= htmlspecialchars($mute['AgentID']) ?></td>
									<td class="small"><?= htmlspecialchars($mute['MuteID']) ?></td>
									<td><?= htmlspecialchars(trim(($mute['FirstName'] ?? '') . ' ' . ($mute['LastName'] ?? ''))) ?></td>
									<td><?= htmlspecialchars($mute['MuteName']) ?></td>
									<td><?= htmlspecialchars($mute['MuteType']) ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<?php endif; ?>
				</div>
			</div>

		</div>
	</div>
</div>

<script>
// Make tables sortable by clicking a column header
document.addEventListener('DOMContentLoaded', function() {
	document.querySelectorAll('table.stats-table').forEach(function(table) {
		let headers = table.querySelectorAll('th');
		headers.forEach(function(th, idx) {
			th.addEventListener('click', function() {
				let rows = Array.from(table.querySelectorAll('tbody > tr'));
				let asc = !th.classList.contains('sorted-asc');
				headers.forEach(h => h.classList.remove('sorted-asc', 'sorted-desc'));
				th.classList.add(asc ? 'sorted-asc' : 'sorted-desc');
				rows.sort(function(a, b) {
					let va = a.children[idx].textContent.trim().toLowerCase();
					let vb = b.children[idx].textContent.trim().toLowerCase();
					// Try to sort numerically where possible
					let na = parseFloat(va.replace(/,/g, '.'));
					let nb = parseFloat(vb.replace(/,/g, '.'));
					if (!isNaN(na) && !isNaN(nb)) {
						return asc ? na - nb : nb - na;
					}
					return asc ? va.localeCompare(vb) : vb.localeCompare(va);
				});
				let tbody = table.querySelector('tbody');
				rows.forEach(row => tbody.appendChild(row));
			});
		});
	});
});
</script>

<?php
require_once __DIR__ . '/../../include/' . FOOTER_FILE;
