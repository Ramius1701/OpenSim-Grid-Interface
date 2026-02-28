<?php
// /test/features.php — Casperia Prime • Features
declare(strict_types=1);
require_once __DIR__ . '/include/config.php';
$title = 'Features';
require_once __DIR__ . '/include/' . HEADER_FILE;

/* ---------- helpers (safe to define; guarded) ---------- */
if (!function_exists('cval')) {
  function cval(string $name, $fallback = null) {
    return defined($name) ? constant($name) : $fallback;
  }
}
if (!function_exists('yes_no_pill')) {
  function yes_no_pill($v): string {
    $on = is_string($v)
      ? (strtolower($v) !== 'none'
         && strtolower($v) !== 'no'
         && strtolower($v) !== 'disabled'
         && strtolower($v) !== 'false')
      : (bool)$v;
    return $on
      ? '<span class="pill ok">Yes</span>'
      : '<span class="pill off">No</span>';
  }
}
if (!function_exists('text_or_dash')) {
  function text_or_dash($v): string {
    $s = trim((string)$v);
    return $s !== '' ? htmlspecialchars($s) : '—';
  }
}

/* ---------- config values ---------- */
// Platform names & versions
$osNameMain = cval('OS_NAME_MAIN', 'OpenSimulator');
$osVerMain  = cval('OS_VERSION_MAIN', '0.9.3.1 (Build 789)');

$betaEnabled = (bool)cval('BETA_ENABLED', true);
$betaLabel   = (string)cval('BETA_LABEL', 'Beta (NGC)');
$osNameBeta  = cval('OS_NAME_BETA', 'OpenSim NGC (Tranquillity)');
$osVerBeta   = cval('OS_VERSION_BETA', '0.9.3.9333');

// Capabilities
$viewers  = cval('VIEWERS_SUPPORTED','Firestorm, Cool VL Viewer');
$hg       = (bool)cval('FEATURE_HYPERGRID',true);
$varr     = (bool)cval('FEATURE_VARREGIONS',true);
$search   = (bool)cval('SEARCH_ENABLED',true);
$mesh     = (bool)cval('MESH_ENABLED',true);
$npcs     = (bool)cval('NPC_ENABLED',true);
$offline  = (bool)cval('OFFLINE_IM_ENABLED',true);
$script   = (string)cval('FEATURE_SCRIPT_ENGINE','YEngine');
$physics  = (string)cval('PHYSICS_ENGINES','Bullet, ODE, ubODE');

// Experiences (beta only, used in beta card)
$expBeta  = (bool)cval('EXPERIENCES_BETA', true);

// Voice
$voice     = (string)cval('FEATURE_VOICE','Available'); // Vivox/WebRTC/etc
$voiceNote = (string)cval('VOICE_NOTE','Vivox active while we evaluate WebRTC voice.');

// Economy
$ecoGlo   = (bool)cval('ECONOMY_GLOEBIT',true);
$gName    = (string)cval('CURRENCY_NAME_GLOEBIT','Gloebit');
$gRate    = (string)cval('CURRENCY_RATE_GLOEBIT','≈ 200 Gloebit = 1 USD');

$ecoLoc   = (bool)cval('ECONOMY_LOCAL',true);
$lName    = (string)cval('LOCAL_MONEY_NAME','MoneyServer');
$lCap     = (string)cval('LOCAL_WALLET_CAP','20,000');

// Free & perks lists
$freeOffers = array_filter(array_map('trim', explode(',', (string)cval(
  'FREE_OFFERS',
  'Free groups, Free classifieds advertising, Free mesh uploads, Free texture uploads, ' .
  'Free events listings, Free apartments & homes with land (350 prims), ' .
  'Free land lots (435 prims), Free shops for creators (250 prims)'
))));
$otherPerks = array_filter(array_map('trim', explode(',', (string)cval(
  'OTHER_PERKS',
  'No region setup fees, Region referral program, Partnerships, Hypergrid traveling,' .
  'Offline messaging, Offline IM, Offline group notices, Members area, ' .
  'Weekly OAR/Database backups, NPCs enabled, Mesh enabled, Second Inventory (Stored Inventory) enabled, ' .
  'Monthly grid meetings, Forums area, Mentors program, Support ticket system (Members area)'
))));

// Routes
$registerUrl = cval('URL_REGISTER', 'createavatar.php');
$helpUrl     = cval('URL_HELP',     'help.php');
?>

<style>
/* Features page layout helpers adapted to the modern theme */
.features-hero {
  background: linear-gradient(135deg, rgba(0,0,0,0.7), rgba(0,0,0,0.4));
  border-radius: 15px;
  padding: 3rem;
  margin-bottom: 2rem;
  text-align: center;
  color: white;
  position: relative;
  overflow: hidden;
}

.features-hero h1 {
  font-size: 2.25rem;
  font-weight: 700;
  margin-bottom: .5rem;
}

.features-hero p.muted {
  color: rgba(255,255,255,0.8);
  max-width: 720px;
  margin: 0 auto 1.5rem;
}

.features-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: .75rem;
}

.features-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: .5rem 1.35rem;
  border-radius: 999px;
  border: 1px solid var(--primary-color);
  background: transparent;
  color: var(--primary-color);
  font-weight: 500;
  text-decoration: none;
}

.features-btn:hover {
  background: var(--primary-color);
  color: #fff;
  text-decoration: none;
}

.features-card {
  background: rgba(255,255,255,0.95);
  border-radius: 12px;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 4px 18px rgba(0,0,0,0.08);
}

.features-card .card-title {
  font-size: 1.15rem;
  font-weight: 600;
  margin-bottom: 1rem;
}

.features-grid-3 {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1.25rem;
}

.features-grid-2 {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 1.25rem;
}

.pill {
  display: inline-block;
  padding: .15rem .6rem;
  border-radius: 999px;
  font-size: .8rem;
  font-weight: 600;
}

.pill.ok {
  background: #198754;
  color: #fff;
}

.pill.off {
  background: #6c757d;
  color: #fff;
}

.muted {
  color: #6c757d;
}

.features-list {
  list-style: none;
  padding-left: 0;
  margin-bottom: 0;
}

.features-list li::before {
  content: "• ";
  color: var(--primary-color);
  margin-right: .25rem;
}

/* Region types: individual cards stacked vertically */
.region-cards {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.region-card {
  background: rgba(255,255,255,0.95);
  border-radius: 12px;
  padding: 1.25rem;
  box-shadow: 0 2px 10px rgba(0,0,0,0.06);
}

.region-card h3 {
  margin-top: 0;
  margin-bottom: .35rem;
}

.region-card .features-list {
  margin-bottom: 0;
}
</style>

<section class="hero features-hero">
  <h1>Features</h1>
  <p class="muted">
    Modern OpenSimulator with creator-first tools, Hypergrid connectivity,
    flexible economy options, and an NGC beta lane for cutting-edge features.
  </p>
</section>

<section class="features-card">
  <h2 class="card-title">At a Glance</h2>
  <table class="table">
    <tbody>
      <tr><th>Viewers</th><td><?= htmlspecialchars($viewers) ?></td></tr>
      <tr><th>Main Simulator</th><td><?= htmlspecialchars($osNameMain) ?></td></tr>
      <tr><th>Main Version</th><td><?= htmlspecialchars($osVerMain) ?></td></tr>
      <?php if ($betaEnabled): ?>
        <tr><th><?= htmlspecialchars($betaLabel) ?> Simulator</th><td><?= htmlspecialchars($osNameBeta) ?></td></tr>
        <tr><th><?= htmlspecialchars($betaLabel) ?> Version</th><td><?= htmlspecialchars($osVerBeta) ?></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</section>

<section class="features-card">
  <h2 class="card-title">Capabilities</h2>
  <div class="features-grid-3">
    <div class="features-card">
      <h3 style="margin-top:0">World</h3>
      <table class="table">
        <tbody>
          <tr><th>Hypergrid</th><td><?= yes_no_pill($hg) ?></td></tr>
          <tr><th>VarRegions</th><td><?= yes_no_pill($varr) ?></td></tr>
          <tr><th>Search</th><td><?= yes_no_pill($search) ?></td></tr>
          <tr><th>Mesh</th><td><?= yes_no_pill($mesh) ?></td></tr>
          <tr><th>NPCs</th><td><?= yes_no_pill($npcs) ?></td></tr>
          <tr><th>Offline IM &amp; Notices</th><td><?= yes_no_pill($offline) ?></td></tr>
        </tbody>
      </table>
    </div>

    <div class="features-card">
      <h3 style="margin-top:0">Voice</h3>
      <table class="table">
        <tbody>
          <tr><th>Provider</th><td><?= text_or_dash($voice) ?></td></tr>
          <?php if ($voiceNote): ?>
            <tr><th>Note</th><td class="muted"><?= htmlspecialchars($voiceNote) ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="features-card">
      <h3 style="margin-top:0">Scripting &amp; Physics</h3>
      <table class="table">
        <tbody>
          <tr><th>Script Engine</th><td><?= htmlspecialchars($script) ?></td></tr>
          <tr><th>Physics</th><td><?= htmlspecialchars($physics) ?></td></tr>
          <tr><th>LSL / OSSL</th><td>Supported</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</section>

<?php if ($betaEnabled): ?>
<section class="features-card">
  <h2 class="card-title"><?= htmlspecialchars($betaLabel) ?></h2>
  <div class="features-grid-2">
    <div class="features-card">
      <h3 style="margin-top:0">Enhancements</h3>
      <table class="table">
        <tbody>
          <tr><th>Display Names</th><td><?= yes_no_pill((bool)cval('FEATURE_DISPLAY_NAMES', true)) ?></td></tr>
          <tr><th>Trusted Hypergrid</th><td><?= yes_no_pill((bool)cval('FEATURE_TRUSTED_HG', true)) ?></td></tr>
          <tr><th>Experiences</th><td><?= yes_no_pill($expBeta) ?></td></tr>
        </tbody>
      </table>
    </div>
    <div class="features-card">
      <h3 style="margin-top:0">Notes</h3>
      <p class="muted">The beta grid may change as we test new features and performance updates.</p>
    </div>
  </div>
</section>

<section class="features-card">
  <h2 class="card-title">Region Types</h2>

  <div class="region-cards">
    <div class="region-card">
      <h3>Full Regions</h3>
      <ul class="features-list">
        <li><strong>Performance:</strong> Highest capacity — supports up to 100 avatars and heavy scripting/content.</li>
        <li><strong>Max prims:</strong> Around 20,000+ prims (depending on configuration).</li>
        <li><strong>Use case:</strong> Events, clubs, large communities, roleplay hubs, or busy commercial builds.</li>
        <li><strong>Flexibility:</strong> Full estate tools, terraforming, custom environment, and advanced scripting.</li>
        <li><strong>Best for:</strong> Owners who need maximum headroom and control.</li>
      </ul>
    </div>

    <div class="region-card">
      <h3>Homestead Regions</h3>
      <ul class="features-list">
        <li><strong>Performance:</strong> Moderate — ideal for light traffic and lighter scripting loads.</li>
        <li><strong>Max prims:</strong> Up to 5,000 prims.</li>
        <li><strong>Use case:</strong> Quiet residential areas, light commercial builds, scenic or park-style regions.</li>
        <li><strong>Ownership:</strong> Modeled after SL — typically offered as an add-on to a Full Region.</li>
        <li><strong>Best for:</strong> Personal homes, themed builds, or low-key hangout spaces.</li>
      </ul>
    </div>

    <div class="region-card">
      <h3>Openspace / Void Regions</h3>
      <ul class="features-list">
        <li><strong>Performance:</strong> Lightest — tuned for low avatar counts and simple content.</li>
        <li><strong>Max prims:</strong> Up to 750 prims.</li>
        <li><strong>Use case:</strong> Oceans, forests, sky or buffer areas, sailing corridors, or scenic backdrops.</li>
        <li><strong>Ownership:</strong> Typically attached to a Full Region for navigational / scenic purposes.</li>
        <li><strong>Best for:</strong> Water, void, and landscape regions that don’t need heavy scripts or traffic.</li>
      </ul>
    </div>

    <div class="region-card">
      <h3>VarRegions (Mega Regions)</h3>
      <ul class="features-list">
        <li><strong>Layout:</strong> Single large region made of multiple standard regions (e.g. 2×2 or 4×4) with no border crossings.</li>
        <li><strong>Max prims:</strong> Scales with layout (for example, a 2×2 VarRegion can offer roughly 4× the prims of a single Full Region, depending on your config).</li>
        <li><strong>Use case:</strong> Sailing, aviation, road networks, large landscapes, or any build where seamless travel matters.</li>
        <li><strong>Experience:</strong> No sim crossing stutter — avatars and vehicles move smoothly across the whole VarRegion.</li>
        <li><strong>Best for:</strong> Big projects that need lots of continuous space rather than many small parcels.</li>
      </ul>
    </div>
  </div>
</section>

<section class="features-card">
  <h2 class="card-title">Economy</h2>
  <div class="features-grid-3">
    <?php if ($ecoGlo): ?>
      <div class="features-card">
        <h3 style="margin-top:0">Paid Currency</h3>
        <ul class="features-list">
          <li>Name: <?= htmlspecialchars($gName) ?></li>
          <li>Wallet: Purchase from Gloebit website</li>
          <li>Exchange: <?= text_or_dash($gRate) ?></li>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($ecoLoc): ?>
      <div class="features-card">
        <h3 style="margin-top:0">Free Currency</h3>
        <ul class="features-list">
          <li>Name: <?= htmlspecialchars($lName) ?></li>
          <li>Wallet: Purchase in the viewer</li>
          <li>Limit: <?= text_or_dash($lCap) ?></li>
        </ul>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="features-card">
  <h2 class="card-title">Included &amp; Extras</h2>
  <div class="features-grid-2">
    <div class="features-card">
      <h3 style="margin-top:0">Included with your account</h3>
      <ul class="features-list">
        <?php if ($freeOffers): ?>
          <?php foreach ($freeOffers as $s): ?>
            <li><?= htmlspecialchars($s) ?></li>
          <?php endforeach; ?>
        <?php else: ?>
          <li class="muted">Details coming soon.</li>
        <?php endif; ?>
      </ul>
    </div>

    <div class="features-card">
      <h3 style="margin-top:0">Community &amp; extras</h3>
      <ul class="features-list">
        <?php if ($otherPerks): ?>
          <?php foreach ($otherPerks as $p): ?>
            <li><?= htmlspecialchars($p) ?></li>
          <?php endforeach; ?>
        <?php else: ?>
          <li class="muted">Additional perks will be announced soon.</li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="features-card text-center">
  <p class="muted">Questions about features, land, or pricing? We’re happy to help.</p>
  <div class="features-actions" style="flex-wrap:wrap;">
    <a class="features-btn" href="<?= htmlspecialchars($registerUrl) ?>">Create free account</a>
    <a class="features-btn" href="<?= htmlspecialchars($helpUrl) ?>">Visit Help Center</a>
  </div>
</section>

<?php require_once __DIR__ . '/include/footer.php'; ?>
