<?php
include_once 'ssinc.php';
// SStats - Statistiksoftware für OpenSimulator-Server

// Zusätzliche Sicherheitsheader
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
if (session_status() === PHP_SESSION_NONE) {
	session_start([
		'cookie_httponly' => true,
		'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
		'cookie_samesite' => 'Strict',
	]);
}

// CSRF-Basisschutz für POST-Formulare (falls später genutzt)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
		http_response_code(403);
		exit('Ungültiges CSRF-Token.');
	}
}

// Regionen laden
$sql_regions = 'SELECT uuid, regionName, locX, locY, sizeX, sizeY, serverIP, serverPort FROM regions ORDER BY regionName';
$regions = $pdo->query($sql_regions)->fetchAll(PDO::FETCH_ASSOC);

// Online-User (Presence, falls leer: keine online)
$sql_online = 'SELECT p.UserID, ua.FirstName, ua.LastName, p.RegionID, r.regionName, p.LastSeen
FROM Presence p
LEFT JOIN UserAccounts ua ON p.UserID = ua.PrincipalID
LEFT JOIN regions r ON p.RegionID = r.uuid
ORDER BY p.LastSeen DESC';
$online = $pdo->query($sql_online)->fetchAll(PDO::FETCH_ASSOC);

// GridUser mit Status (Online/Offline)
$sql_griduser = 'SELECT gu.UserID, gu.LastRegionID, gu.Login, gu.Logout, gu.Online, ua.FirstName, ua.LastName
FROM GridUser gu
LEFT JOIN UserAccounts ua ON gu.UserID = ua.PrincipalID';
$gridusers = $pdo->query($sql_griduser)->fetchAll(PDO::FETCH_ASSOC);


// MuteList laden
$sql_mute = 'SELECT m.AgentID, m.MuteID, m.MuteName, m.MuteType, ua.FirstName, ua.LastName FROM MuteList m LEFT JOIN UserAccounts ua ON m.MuteID = ua.PrincipalID';
$mutelist = $pdo->query($sql_mute)->fetchAll(PDO::FETCH_ASSOC);


// Gruppen laden
$sql_groups = 'SELECT * FROM os_groups_groups ORDER BY Name ASC, Charter ASC';
$groups = $pdo->query($sql_groups)->fetchAll(PDO::FETCH_ASSOC);

// Benutzerinformationen laden
$sql_userinfo = 'SELECT * FROM userinfo ORDER BY simip ASC, avatar ASC, serverurl DESC';
$userinfo = $pdo->query($sql_userinfo)->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html>
<head>
	<title>OpenSimulator Statistik</title>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
	<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
	<style>
		html,body,h1,h2,h3,h4,h5 {font-family: "Roboto", sans-serif}
		.w3-table-all {font-size: 12px;}
		.w3-container {margin-bottom: 32px;}
		.w3-sidebar {z-index: 3;width:250px;top:0;left:0;}
		.w3-bar-block .w3-bar-item {padding:16px}
		.w3-main {margin-left:250px;}
		th {cursor:pointer;}
		th.sorted-asc:after {content: " \25B2";}
		th.sorted-desc:after {content: " \25BC";}
		@media (max-width:600px) {
			.w3-sidebar {display:none;}
			.w3-main {margin-left:0;}
		}
	</style>
</head>
<body class="w3-light-grey">

<!-- Sidebar -->
<nav class="w3-sidebar w3-bar-block w3-collapse w3-large w3-blue-grey w3-animate-left" id="mySidebar">
	<a class="w3-bar-item w3-button w3-hover-teal w3-center w3-padding-32" href="#">
		<i class="fa fa-bar-chart fa-3x w3-margin-bottom" aria-hidden="true"></i><br>
	</a>
	<a class="w3-bar-item w3-button w3-hover-teal" href="#regionen"><i class="fa fa-map fa-fw w3-margin-right"></i>Regionen</a>
    <a class="w3-bar-item w3-button w3-hover-teal" href="#gruppen"><i class="fa fa-users fa-fw w3-margin-right"></i>Gruppen</a>
	<a class="w3-bar-item w3-button w3-hover-teal" href="#online"><i class="fas fa-user-alt fa-fw w3-margin-right"></i>Online</a>
    <a class="w3-bar-item w3-button w3-hover-teal" href="#userinfo"><i class="fas fa-user-friends fa-fw w3-margin-right"></i>Benutzerinfo</a>
	<a class="w3-bar-item w3-button w3-hover-teal" href="#griduser"><i class="fa fa-address-book fa-fw w3-margin-right"></i>GridUser</a>
	<a class="w3-bar-item w3-button w3-hover-teal" href="#mutelist"><i class="fa fa-volume-off fa-fw w3-margin-right"></i>MuteList</a>
</nav>

<!-- Topbar -->
<header class="w3-bar w3-top w3-blue-grey w3-large" style="z-index:4">
	<button class="w3-bar-item w3-button w3-hide-large w3-hover-none w3-hover-text-light-grey" onclick="w3_open();"><i class="fa fa-bars"></i> Menü</button>
	<span class="w3-bar-item w3-right">OpenSimulator Statistik Dashboard</span>
</header>

<div class="w3-main" style="margin-left:250px;margin-top:43px;">
	<div class="w3-container w3-padding-16">
		<h1 class="w3-xxlarge">OpenSimulator Region Statistik</h1>
		<p>Live-Statistiken zu Regionen und Benutzern</p>
	</div>


	<!-- Regionen Übersicht -->
	<div class="w3-container w3-white w3-card-4 w3-margin-bottom" id="regionen">
		<h2 class="w3-text-grey w3-padding-16"><i class="fa fa-map fa-fw w3-margin-right w3-xxlarge w3-text-teal"></i>Regionen im Grid</h2>
		<div class="w3-responsive">
			<table class="w3-table-all w3-hoverable">
				<thead>
					<tr class="w3-teal">
						<th>Name</th>
						<th>UUID</th>
						<th>Position</th>
						<th>Größe</th>
						<th>Server-IP</th>
						<th>Port</th>
					</tr>
				</thead>
								<tbody>
								<?php if (empty($regions)): ?>
									<tr><td colspan="6" class="w3-center">Zur Zeit sind keine Regionen im Grid.</td></tr>
								<?php else: ?>
									<?php foreach ($regions as $region): ?>
										<tr>
												<td><?= htmlspecialchars($region['regionName']) ?></td>
												<td><?= htmlspecialchars($region['uuid']) ?></td>
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

	<!-- Gruppen Übersicht -->
	<div class="w3-container w3-white w3-card-4 w3-margin-bottom" id="gruppen">
	  <h2 class="w3-text-grey w3-padding-16"><i class="fas fa-users fa-fw w3-margin-right w3-xxlarge w3-text-teal"></i>Gruppen im Grid</h2>
	  <div class="w3-responsive">
		<?php if (empty($groups)): ?>
		  <div class="w3-panel w3-pale-yellow w3-border w3-margin-bottom">Zur Zeit sind keine Gruppen im Grid.</div>
		<?php else: ?>
		<table class="w3-table-all w3-hoverable">
		  <thead>
			<tr class="w3-teal">
			  <th>Name</th>
			  <th>Gruppenbeschreibung (Charter)</th>
			  <th>Gründer (FounderID)</th>
			  <th>Location</th>
			  <th>InsigniaID</th>
			  <th>Mitgliedsbeitrag</th>
			  <th>Offen</th>
			</tr>
		  </thead>
		  <tbody>
		  <?php foreach ($groups as $group): ?>
			<tr>
			  <td><?= htmlspecialchars($group['Name']) ?></td>
			  <td><?= htmlspecialchars($group['Charter']) ?></td>
			  <td><?= htmlspecialchars($group['FounderID']) ?></td>
			  <td><?= htmlspecialchars($group['Location']) ?></td>
			  <td><?= htmlspecialchars($group['InsigniaID']) ?></td>
			  <td><?= htmlspecialchars($group['MembershipFee']) ?></td>
			  <td><?= htmlspecialchars($group['OpenEnrollment']) ?></td>
			</tr>
		  <?php endforeach; ?>
		  </tbody>
		</table>
		<?php endif; ?>
	  </div>
	</div>

	<!-- Online-User Übersicht -->
	<div class="w3-container w3-white w3-card-4 w3-margin-bottom" id="online">
		<h2 class="w3-text-grey w3-padding-16"><i class="fas fa-user-alt fa-fw w3-margin-right w3-xxlarge w3-text-teal"></i>Online-Mitglieder</h2>
		<div class="w3-responsive">
			<?php if (empty($online)): ?>
				<div class="w3-panel w3-pale-yellow w3-border">Zur Zeit befindet sich kein Mitglied im Grid.</div>
			<?php else: ?>
			<table class="w3-table-all w3-hoverable">
				<thead>
					<tr class="w3-teal">
						<th>Name</th>
						<th>UserID</th>
						<th>Region</th>
						<th>Region-Name</th>
						<th>Letzte Aktivität</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($online as $user): ?>
					<tr>
						<td>
						<?php
						$name = trim(($user['FirstName'] ?? '') . ' ' . ($user['LastName'] ?? ''));
						if ($name === '' && !empty($user['UserID'])) {
							// Suche in GridUser nach UserID
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
						<td><?= htmlspecialchars($user['UserID']) ?></td>
						<td><?= htmlspecialchars($user['RegionID']) ?></td>
						<td><?= htmlspecialchars($user['regionName']) ?></td>
						<td><?= htmlspecialchars($user['LastSeen']) ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>
	</div>

	<!-- Benutzerinformationen Übersicht -->
	<div class="w3-container w3-white w3-card-4 w3-margin-bottom" id="userinfo">
	  <h2 class="w3-text-grey w3-padding-16"><i class="fas fa-user-friends fa-fw w3-margin-right w3-xxlarge w3-text-teal"></i>Benutzerinformationen</h2>
	  <div class="w3-responsive">
		<?php if (empty($userinfo)): ?>
		  <div class="w3-panel w3-pale-yellow w3-border w3-margin-bottom">Zur Zeit sind keine Benutzerinformationen im Grid.</div>
		<?php else: ?>
		<table class="w3-table-all w3-hoverable">
		  <thead>
			<tr class="w3-teal">
			  <th>Avatar</th>
			  <th>Server-URL</th>
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
		<?php endif; ?>
	  </div>
	</div>

	<!-- GridUser Übersicht -->
	<div class="w3-container w3-white w3-card-4 w3-margin-bottom" id="griduser">
		<h2 class="w3-text-grey w3-padding-16"><i class="fa fa-address-book fa-fw w3-margin-right w3-xxlarge w3-text-teal"></i>Alle GridUser</h2>
		<div class="w3-responsive">
			<table class="w3-table-all w3-hoverable">
				<thead>
					<tr class="w3-teal">
						<th>Status</th>
						<th>Name</th>
						<th>Benutzerkennung</th>
						<th>Heimatadresse</th>
						<th>Vollständiger Name</th>
						<th>Letzte Region</th>
						<th>Login</th>
						<th>Logout</th>
					</tr>
				</thead>
				<tbody>
								<?php if (empty($gridusers)): ?>
									<tr><td colspan="8" class="w3-center">Zur Zeit gibt es keine GridUser im Grid.</td></tr>
								<?php else: ?>
									<?php foreach ($gridusers as $user): ?>
										<?php
											$kennung = $heimat = $vollname = '';
											if (!empty($user['UserID'])) {
												$parts = explode(';', $user['UserID']);
												$kennung = $parts[0] ?? '';
												$heimat = $parts[1] ?? '';
												$vollname = $parts[2] ?? '';
											}
										?>
										<tr>
												<td style="text-align:center;">
													<?php 
													$isOnline = false;
													$originalOnline = isset($user['Online']) ? $user['Online'] : '';
													if (isset($user['Online'])) {
														$val = strtolower(trim((string)$user['Online']));
														$onlineValues = ['1', 'true', 'yes', 'y'];
														$isOnline = in_array($val, $onlineValues, true);
														// Auch numerisch prüfen (z.B. int 1)
														if (!$isOnline && is_numeric($user['Online'])) {
															$isOnline = ((int)$user['Online']) === 1;
														}
													}
													?>
													<?php if ($isOnline): ?>
														<span title="Online (DB: <?=htmlspecialchars($originalOnline)?>)" style="color:green;font-weight:bold;">Online</span>
													<?php else: ?>
														<span title="Offline (DB: <?=htmlspecialchars($originalOnline)?>)" style="color:red;font-weight:bold;">Offline</span>
													<?php endif; ?>
												</td>
												<td><?= htmlspecialchars(($user['FirstName'] ?? '') . ' ' . ($user['LastName'] ?? '')) ?></td>
												<td><?= htmlspecialchars($kennung) ?></td>
												<td><?= htmlspecialchars($heimat) ?></td>
												<td><?= htmlspecialchars($vollname) ?></td>
												<td><?= htmlspecialchars($user['LastRegionID']) ?></td>
												<td><?= htmlspecialchars($user['Login']) ?></td>
												<td><?= htmlspecialchars($user['Logout']) ?></td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>
				</tbody>
			</table>
		</div>
		</div>

		<!-- MuteList Übersicht -->
		<div class="w3-container w3-white w3-card-4 w3-margin-bottom" id="mutelist">
			<h2 class="w3-text-grey w3-padding-16"><i class="fa fa-volume-off fa-fw w3-margin-right w3-xxlarge w3-text-teal"></i>Stummgeschaltete Nutzer (MuteList)</h2>
	  <div class="w3-responsive">
		<?php if (empty($mutelist)): ?>
		  <div class="w3-panel w3-pale-yellow w3-border w3-margin-bottom">Im Grid ist zur Zeit niemand stummgeschaltet.</div>
		<?php else: ?>
		<table class="w3-table-all w3-hoverable">
		  <thead>
			<tr class="w3-teal">
			  <th>Stummschaltender (AgentID)</th>
			  <th>Stummgeschaltet (MuteID)</th>
			  <th>Name</th>
			  <th>MuteName</th>
			  <th>MuteType</th>
			</tr>
		  </thead>
		  <tbody>
		  <?php foreach ($mutelist as $mute): ?>
			<tr>
			  <td><?= htmlspecialchars($mute['AgentID']) ?></td>
			  <td><?= htmlspecialchars($mute['MuteID']) ?></td>
			  <td><?= htmlspecialchars(trim(($mute['FirstName'] ?? '') . ' ' . ($mute['LastName'] ?? ''))) ?></td>
			  <td><?= htmlspecialchars($mute['MuteName']) ?></td>
			  <td><?= htmlspecialchars($mute['MuteType']) ?></td>
			</tr>
		  <?php endforeach; ?>
		  </tbody>
		</table>
		<?php endif; ?>
	  </div>
	</div>

</div>

	<footer class="w3-container w3-teal w3-center w3-margin-top">
			<p>OpenSimulator Statistiksoftware &copy; 2026</p>
	</footer>
</div>

<script>
function w3_open() {
	document.getElementById("mySidebar").style.display = "block";
}
function w3_close() {
	document.getElementById("mySidebar").style.display = "none";
}

// Tabellen sortierbar machen
document.addEventListener('DOMContentLoaded', function() {
	document.querySelectorAll('table.w3-table-all').forEach(function(table) {
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
					// Versuche numerisch zu sortieren, falls möglich
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

</body>
</html>
