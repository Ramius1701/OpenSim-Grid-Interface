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
      ? (strtolower($v) !== 'none' && strtolower($v) !== 'no' && strtolower($v) !== 'disabled' && strtolower($v) !== 'false')
      : (bool)$v;
    return $on
      ? '<span class="badge bg-success rounded-pill px-3 py-2">Yes</span>'
      : '<span class="badge bg-secondary rounded-pill px-3 py-2">No</span>';
  }
}
if (!function_exists('text_or_dash')) {
  function text_or_dash($v): string {
    $s = trim((string)$v);
    return $s !== '' ? htmlspecialchars($s) : '—';
  }
}

/* ---------- config values ---------- */
$osNameMain = cval('OS_NAME_MAIN', 'OpenSimulator');
$osVerMain  = cval('OS_VERSION_MAIN', '0.9.3.1 (Build 821)');
$betaEnabled = (bool)cval('BETA_ENABLED', true);
$betaLabel   = (string)cval('BETA_LABEL', 'Beta (NGC)');
$osNameBeta  = cval('OS_NAME_BETA', 'OpenSim NGC (Tranquillity)');
$osVerBeta   = cval('OS_VERSION_BETA', '0.9.3.9441');
$viewers  = cval('VIEWERS_SUPPORTED','Firestorm, Cool VL Viewer');
$hg       = (bool)cval('FEATURE_HYPERGRID',true);
$varr     = (bool)cval('FEATURE_VARREGIONS',true);
$search   = (bool)cval('SEARCH_ENABLED',true);
$mesh     = (bool)cval('MESH_ENABLED',true);
$npcs     = (bool)cval('NPC_ENABLED',true);
$offline  = (bool)cval('OFFLINE_IM_ENABLED',true);
$script   = (string)cval('FEATURE_SCRIPT_ENGINE','YEngine');
$physics  = (string)cval('PHYSICS_ENGINES','Bullet, ubODE');
$expBeta  = (bool)cval('EXPERIENCES_BETA', true);
$voice     = (string)cval('FEATURE_VOICE','Available'); 
$voiceNote = (string)cval('VOICE_NOTE','Vivox active while we evaluate WebRTC voice.');
$ecoGlo   = (bool)cval('ECONOMY_GLOEBIT',true);
$gName    = (string)cval('CURRENCY_NAME_GLOEBIT','Gloebit');
$gRate    = (string)cval('CURRENCY_RATE_GLOEBIT','≈ 200 Gloebit = 1 USD');
$ecoLoc   = (bool)cval('ECONOMY_LOCAL',true);
$lName    = (string)cval('LOCAL_MONEY_NAME','MoneyServer');
$lCap     = (string)cval('LOCAL_WALLET_CAP','20,000');
$freeOffers = array_filter(array_map('trim', explode(',', (string)cval('FREE_OFFERS','Free groups, Free classifieds advertising, Free mesh uploads, Free texture uploads, Free events listings, Free apartments & homes with land (350 prims), Free land lots (435 prims), Free shops for creators (250 prims)'))));
$otherPerks = array_filter(array_map('trim', explode(',', (string)cval('OTHER_PERKS','No region setup fees, Region referral program, Partnerships, Hypergrid traveling, Offline messaging, Offline IM, Offline group notices, Members area, Weekly OAR/Database backups, NPCs enabled, Mesh enabled, Second Inventory (Stored Inventory) enabled, Monthly grid meetings, Forums area, Mentors program, Support ticket system (Members area)'))));
$registerUrl = cval('URL_REGISTER', 'register.php');
$helpUrl     = cval('URL_HELP',     'help.php');
?>

<style>
/* --- COMFORT SPACING & LAYOUT --- */

/* 1. Hero Section */
.features-hero {
    background: linear-gradient(135deg, 
        color-mix(in srgb, var(--header-color), black 30%), 
        color-mix(in srgb, var(--header-color), black 60%)
    );
    border-radius: 15px;
    padding: 4rem 2rem; /* Taller hero */
    margin-bottom: 1.5rem; /* More space below hero */
    text-align: center;
    color: white;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}
.features-hero h1 { font-size: 1.5rem; font-weight: 800; margin-bottom: 1rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.3); }
.features-hero p.muted { color: rgba(255,255,255,0.9) !important; max-width: 800px; margin: 0 auto; font-size: 1.25rem; line-height: 1.6; }

/* 2. Main Content Cards */
.content-card {
    /* Increased padding from 2rem to 1.5rem for "Airy" feel */
    padding: 1.5rem !important; 
    margin-bottom: 1.5rem !important; /* Space between sections */
    border-radius: 16px !important;
}

/* 3. Section Titles */
.section-title {
    font-size: 1.75rem;
    margin-bottom: 2rem; /* More space below title */
    padding-bottom: 1rem;
    border-bottom: 1px solid color-mix(in srgb, var(--primary-color), transparent 90%);
}

/* 4. Grids */
.features-grid-3 {
    display: grid;
    /* Min width 300px prevents squashing on small screens */
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem; /* Wider gap between grid items */
}

.features-grid-2 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 2rem;
}

/* 5. Inner Cards (Nested) */
.inner-feature-card {
    background: color-mix(in srgb, var(--card-bg), var(--primary-color) 3%);
    border: 1px solid color-mix(in srgb, var(--primary-color), transparent 90%);
    border-radius: 12px;
    padding: 2rem; /* More internal breathing room */
    height: 100%; /* Ensure equal height in grids */
}
.inner-feature-card h4 {
    color: var(--accent-color);
    font-weight: 700;
    margin-bottom: 1.5rem;
    font-size: 1.1.5rem;
}

/* 6. Region Cards */
.region-cards {
    display: flex;
    flex-direction: column;
    gap: 2rem; /* Space between region types */
}

.region-card {
    background-color: color-mix(in srgb, var(--card-bg), var(--primary-color) 3%);
    border: 1px solid color-mix(in srgb, var(--primary-color), transparent 90%);
    border-radius: 12px;
    padding: 2.5rem; /* Spacious padding */
    transition: transform 0.2s;
}
.region-card:hover {
    transform: translateY(-5px);
    border-left: 5px solid var(--accent-color);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}
.region-card h3 {
    color: var(--accent-color);
    font-weight: 800;
    font-size: 1.5rem;
    margin-top: 0;
    margin-bottom: 1rem;
}

/* 7. Tables */
.table {
    --bs-table-bg: transparent;
    --bs-table-color: var(--primary-color);
    margin-bottom: 0;
}
.table td, .table th {
    padding: 1rem 0.5rem; /* Taller rows so text doesn't feel squashed */
    vertical-align: middle;
    border-color: color-mix(in srgb, var(--primary-color), transparent 90%);
}
.table th { width: 40%; font-weight: 600; opacity: 0.8; }

/* 8. Lists */
.features-list { list-style: none; padding-left: 0; margin-bottom: 0; }
.features-list li {
    margin-bottom: 0.75rem; /* Space between list items */
    font-size: 1.05rem;
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.features-list li::before {
    content: "\F26A"; /* Check circle icon */
    font-family: 'bootstrap-icons';
    color: var(--accent-color);
    margin-right: 0.75rem;
    font-size: 1.1rem;
    line-height: 1.5;
}

/* 9. Badges/Pills */
.badge { font-weight: 600; letter-spacing: 0.5px; }


/* 10. Powered By (grouped tiles) */
.powered-by-grid{
    display:flex;
    flex-wrap:wrap;
    justify-content:center;
    gap:1.25rem;
    margin-top:1rem;
}
.powered-group{
    flex: 0 0 100%;
    display:flex;
    justify-content:center;
}
.powered-group span{
    font-size:.72rem;
    letter-spacing:.10em;
    text-transform:uppercase;
    padding:.35rem .85rem;
    border-radius:999px;
    background: color-mix(in srgb, var(--card-bg), var(--primary-color) 5%);
    border: 1px solid color-mix(in srgb, var(--primary-color), transparent 88%);
    color: color-mix(in srgb, var(--primary-color), transparent 15%);
}
.powered-by-item{
    flex: 0 1 170px;
    max-width: 170px;
    min-width: 140px;
    background: color-mix(in srgb, var(--card-bg), var(--primary-color) 3%);
    border: 1px solid color-mix(in srgb, var(--primary-color), transparent 90%);
    border-radius: 12px;
    padding: 1.15rem .8rem;
    text-align: center;
}
.powered-by-item i{
    font-size: 2.1rem;
    color: var(--accent-color);
    margin-bottom: .45rem;
    display:block;
}
.powered-title{ font-weight: 700; color: var(--primary-color); }
.powered-sub{
    font-size: .75rem;
    color: color-mix(in srgb, var(--primary-color), transparent 35%);
}

</style>

<section class="features-hero">
  <h1><i class="bi bi-stars me-2"></i> Grid Features</h1>
  <p class="muted">
    Experience a modern OpenSimulator grid built for creators. 
    We offer robust tools, seamless Hypergrid connectivity, and a thriving economy.
  </p>
</section>

<div class="content-card">
  <h3 class="section-title"><i class="bi bi-activity"></i> Platform Overview</h3>
  <div class="table-responsive">
      <table class="table">
        <tbody>
          <tr><th>Supported Viewers</th><td><?= htmlspecialchars($viewers) ?></td></tr>
          <tr><th>Main Simulator</th><td><?= htmlspecialchars($osNameMain) ?></td></tr>
          <tr><th>Main Version</th><td><?= htmlspecialchars($osVerMain) ?></td></tr>
          <?php if ($betaEnabled): ?>
            <tr><th><?= htmlspecialchars($betaLabel) ?> Simulator</th><td><?= htmlspecialchars($osNameBeta) ?></td></tr>
            <tr><th><?= htmlspecialchars($betaLabel) ?> Version</th><td><?= htmlspecialchars($osVerBeta) ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
  </div>

</div>

<div class="content-card">
  <h3 class="section-title"><i class="bi bi-lightning-charge"></i> Powered By</h3>
  <div class="powered-by-grid">
    <div class="powered-group"><span>Infrastructure</span></div>

    <div class="powered-by-item">
      <i class="bi bi-windows" aria-hidden="true"></i>
      <div class="powered-title">Windows</div>
      <div class="powered-sub">Host OS</div>
    </div>
    <div class="powered-by-item">
      <i class="bi bi-box" aria-hidden="true"></i>
      <div class="powered-title">VirtualBox</div>
      <div class="powered-sub">VM Layer</div>
    </div>
    <div class="powered-by-item">
      <i class="bi bi-terminal" aria-hidden="true"></i>
      <div class="powered-title">Linux</div>
      <div class="powered-sub">Guest OS</div>
    </div>

    <div class="powered-group"><span>Grid Backend</span></div>

    <div class="powered-by-item">
      <i class="bi bi-code-slash" aria-hidden="true"></i>
      <div class="powered-title">.NET / Mono</div>
      <div class="powered-sub">Runtime</div>
    </div>
    <div class="powered-by-item">
      <i class="bi bi-database" aria-hidden="true"></i>
      <div class="powered-title">MariaDB</div>
      <div class="powered-sub">Database</div>
    </div>
    <div class="powered-by-item">
      <i class="bi bi-hdd" aria-hidden="true"></i>
      <div class="powered-title">Robust</div>
      <div class="powered-sub">Grid Services</div>
    </div>
    <div class="powered-by-item">
      <i class="bi bi-server" aria-hidden="true"></i>
      <div class="powered-title">OpenSimulator</div>
      <div class="powered-sub">Core Platform</div>
    </div>

    <div class="powered-group"><span>Web Portal</span></div>

    <div class="powered-by-item">
      <i class="bi bi-diagram-3" aria-hidden="true"></i>
      <div class="powered-title">Nginx</div>
      <div class="powered-sub">Reverse Proxy</div>
    </div>
    <div class="powered-by-item">
      <i class="bi bi-globe" aria-hidden="true"></i>
      <div class="powered-title">Apache</div>
      <div class="powered-sub">Web Server</div>
    </div>
    <div class="powered-by-item">
      <i class="bi bi-file-earmark-code" aria-hidden="true"></i>
      <div class="powered-title">PHP</div>
      <div class="powered-sub">Web App</div>
    </div>

    <div class="powered-group"><span>Public Access</span></div>

    <div class="powered-by-item">
      <i class="bi bi-link-45deg" aria-hidden="true"></i>
      <div class="powered-title">DDNS</div>
      <div class="powered-sub">Dynu + No-IP</div>
    </div>
    <div class="powered-by-item">
      <i class="bi bi-shield-lock" aria-hidden="true"></i>
      <div class="powered-title">Let’s Encrypt</div>
      <div class="powered-sub">Certify The Web</div>
    </div>
  </div>

</div>

<div class="content-card">
  <h3 class="section-title"><i class="bi bi-sliders"></i> Technical Capabilities</h3>
  <div class="features-grid-3">
    
    <div class="inner-feature-card">
      <h4><i class="bi bi-globe-americas me-2"></i> World</h4>
      <table class="table table-sm">
        <tbody>
          <tr><td>Hypergrid</td><td class="text-end"><?= yes_no_pill($hg) ?></td></tr>
          <tr><td>VarRegions</td><td class="text-end"><?= yes_no_pill($varr) ?></td></tr>
          <tr><td>Search</td><td class="text-end"><?= yes_no_pill($search) ?></td></tr>
          <tr><td>Mesh Support</td><td class="text-end"><?= yes_no_pill($mesh) ?></td></tr>
          <tr><td>NPCs</td><td class="text-end"><?= yes_no_pill($npcs) ?></td></tr>
          <tr><td>Offline IMs</td><td class="text-end"><?= yes_no_pill($offline) ?></td></tr>
        </tbody>
      </table>
    </div>

    <div class="inner-feature-card">
      <h4><i class="bi bi-mic-fill me-2"></i> Voice</h4>
      <table class="table table-sm">
        <tbody>
          <tr><td>Provider</td><td class="text-end fw-bold"><?= text_or_dash($voice) ?></td></tr>
          <?php if ($voiceNote): ?>
            <tr><td colspan="2" class="text-muted small pt-3 fst-italic"><?= htmlspecialchars($voiceNote) ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="inner-feature-card">
      <h4><i class="bi bi-code-square me-2"></i> Scripts & Physics</h4>
      <table class="table table-sm">
        <tbody>
          <tr><td>Script Engine</td><td class="text-end fw-bold"><?= htmlspecialchars($script) ?></td></tr>
          <tr><td>Physics Engine</td><td class="text-end fw-bold"><?= htmlspecialchars($physics) ?></td></tr>
          <tr><td>LSL / OSSL</td><td class="text-end"><span class="badge bg-success rounded-pill px-3">Supported</span></td></tr>
        </tbody>
      </table>
    </div>

  </div>
</div>

<?php if ($betaEnabled): ?>
<div class="content-card">
  <h3 class="section-title"><i class="bi bi-rocket-takeoff"></i> <?= htmlspecialchars($betaLabel) ?> Lane</h3>
  <div class="features-grid-2">
    <div>
      <table class="table">
        <tbody>
          <tr><th>Display Names</th><td class="text-end"><?= yes_no_pill((bool)cval('FEATURE_DISPLAY_NAMES', true)) ?></td></tr>
          <tr><th>Trusted Hypergrid</th><td class="text-end"><?= yes_no_pill((bool)cval('FEATURE_TRUSTED_HG', true)) ?></td></tr>
          <tr><th>Experiences</th><td class="text-end"><?= yes_no_pill($expBeta) ?></td></tr>
        </tbody>
      </table>
    </div>
    <div class="d-flex align-items-center">
      <div class="alert alert-info w-100 m-0 border-0 p-4" style="background-color: color-mix(in srgb, var(--accent-color), transparent 85%); color: var(--primary-color);">
        <h5 class="alert-heading"><i class="bi bi-info-circle me-2"></i> Note</h5>
        <p class="mb-0">The beta grid is our testing ground for the latest OpenSimulator features. Functionality and performance may vary as we apply updates.</p>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="content-card">
  <h3 class="section-title"><i class="bi bi-grid-3x3-gap"></i> Region Configuration Types</h3>

  <div class="region-cards">
    <div class="region-card">
      <h3><i class="bi bi-arrows-fullscreen me-2"></i> VarRegions (Large Regions)</h3>
      <ul class="features-list">
        <li><strong>Layout:</strong> One region with a larger footprint than a standard 256×256 (e.g. 512×512 or 1024×1024) with no internal border crossings.</li>
        <li><strong>Max prims:</strong> Typically configured to scale with area (e.g. ~4× prim allowance for 512×512 when using the same prim density as a standard region).</li>
        <li><strong>Use case:</strong> Sailing, aviation, road networks, or large landscapes.</li>
        <li><strong>Experience:</strong> No sim crossing stutter — avatars and vehicles move smoothly.</li>
      </ul>
    </div>

    <div class="region-card">
      <h3><i class="bi bi-square-fill me-2"></i> Full Regions</h3>
      <ul class="features-list">
        <li><strong>Performance:</strong> Highest capacity tier (avatar limit is configurable per region/grid policy).</li>
        <li><strong>Max prims:</strong> Configurable; many grids set full regions around 15,000–20,000+ prims depending on hardware and policy.</li>
        <li><strong>Use case:</strong> Events, clubs, large communities, roleplay hubs.</li>
      </ul>
    </div>

    <div class="region-card">
      <h3><i class="bi bi-house-door-fill me-2"></i> Homestead Regions</h3>
      <ul class="features-list">
        <li><strong>Performance:</strong> Lower-capacity tier than Full (avatar limit is configurable; typically intended for light traffic).</li>
        <li><strong>Max prims:</strong> Configurable; commonly ~5,000 prims on grids that mirror SL-style tiering.</li>
        <li><strong>Use case:</strong> Quiet residential areas, scenic or park-style regions.</li>
      </ul>
    </div>

    <div class="region-card">
      <h3><i class="bi bi-droplet-fill me-2"></i> Openspace Regions</h3>
      <ul class="features-list">
        <li><strong>Performance:</strong> Lowest-capacity tier (avatar limit is configurable; tuned for low traffic).</li>
        <li><strong>Max prims:</strong> Configurable; commonly ~750 prims on grids that mirror SL-style tiering.</li>
        <li><strong>Use case:</strong> Oceans, forests, sky buffer areas.</li>
      </ul>
    </div>
  </div>
</div>

<div class="content-card">
  <h3 class="section-title"><i class="bi bi-currency-dollar"></i> Economy & Currency</h3>
  <div class="features-grid-2">
    <?php if ($ecoGlo): ?>
      <div class="inner-feature-card">
        <h4>Paid Currency (<?= htmlspecialchars($gName) ?>)</h4>
        <ul class="features-list">
          <li><strong>Provider:</strong> Gloebit (Web-based Wallet)</li>
          <li><strong>Exchange Rate:</strong> <?= text_or_dash($gRate) ?></li>
          <li><strong>Usage:</strong> Buy/Sell Land, Items, and Services across the grid.</li>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($ecoLoc): ?>
      <div class="inner-feature-card">
        <h4>Free Currency (<?= htmlspecialchars($lName) ?>)</h4>
        <ul class="features-list">
          <li><strong>Provider:</strong> In-World MoneyServer</li>
          <li><strong>Wallet Cap:</strong> <?= text_or_dash($lCap) ?></li>
          <li><strong>Usage:</strong> Roleplay, rewards, and in-game mechanics.</li>
        </ul>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="content-card">
  <h3 class="section-title"><i class="bi bi-gift"></i> Membership Perks</h3>
  <div class="features-grid-2">
    <div>
      <h4 class="h5 mb-3 text-primary">Included Free</h4>
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

    <div>
      <h4 class="h5 mb-3 text-primary">Community Extras</h4>
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
</div>

<div class="content-card text-center pb-5 pt-5">
  <h3 class="mb-3">Ready to explore?</h3>
  <p class="mb-5 text-muted" style="font-size: 1.1rem;">Join our community today and start building your world.</p>
  <div class="d-flex gap-4 justify-content-center flex-wrap">
    <a class="btn btn-lg btn-outline-primary rounded-pill px-5 shadow" href="<?= htmlspecialchars($registerUrl) ?>">Create Free Account</a>
    <a class="btn btn-lg btn-outline-primary rounded-pill px-5 shadow" href="<?= htmlspecialchars($helpUrl) ?>">Visit Help Center</a>
  </div>
</div>

<?php require_once __DIR__ . "/include/" . FOOTER_FILE; ?>