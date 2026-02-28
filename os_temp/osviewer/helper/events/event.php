<?php
// Manual event form (no kiosk prefill).
// Users provide human bits + Region + Local X,Y,Z + Owner UUID.
// submit.php will derive region base and (optionally) parcel UUID from DB.

$SHARED_KEY    = 'CHANGEME';              // <-- MUST match submit.php
$FORM_VERSION  = 'Events ManualForm:1.0';
?>
<!doctype html>
<meta charset="utf-8">
<title>Create / Edit Event (Manual)</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  :root { --fg:#222; --muted:#666; --bg:#fff; --line:#e7e7e7; --w:760px; }
  body{font:16px system-ui,Segoe UI,Roboto,Arial;background:var(--bg);color:var(--fg);margin:2rem}
  .wrap{max-width:var(--w);margin:0 auto}
  form{border:1px solid var(--line);border-radius:12px;padding:1.2rem}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  label{display:block;margin:.6rem 0 .25rem;font-weight:600}
  input[type=text],input[type=number],select,textarea{width:100%;padding:.65rem .7rem;border:1px solid var(--line);border-radius:10px}
  textarea{min-height:120px}
  .muted{color:var(--muted);font-size:.92rem}
  .bar{height:1px;background:var(--line);margin:1rem 0}
  .actions{display:flex;gap:10px;margin-top:1rem}
  button{padding:.7rem 1rem;border:0;border-radius:10px;background:#111;color:#fff;cursor:pointer}
  .light{background:#f2f2f2;color:#111}
</style>

<div class="wrap">
  <h1>Create / Edit Event</h1>
  <p class="muted">Enter time in <strong>Grid Time (America/Los_Angeles)</strong>. We store UTC internally; displays remain Grid Time.</p>

  <form method="post" action="submit.php" onsubmit="return validateForm(this)">

    <label for="evname">Event Title *</label>
    <input id="evname" name="evname" type="text" required maxlength="128" placeholder="Admiral's Banquet">

    <div class="row">
      <div>
        <label for="evdate">Date (YYYY-MM-DD) *</label>
        <input id="evdate" name="evdate" type="text" required placeholder="2025-11-01" pattern="\d{4}-\d{2}-\d{2}">
        <div class="muted">Grid Time</div>
      </div>
      <div>
        <label for="evtime">Start Time (HH:MM 24h) *</label>
        <input id="evtime" name="evtime" type="text" required placeholder="20:00" pattern="^([01]?\d|2[0-3]):[0-5]\d$">
        <div class="muted">Grid Time (e.g., 8pm → 20:00)</div>
      </div>
    </div>

    <div class="row">
      <div>
        <label for="evduration">Duration (minutes) *</label>
        <input id="evduration" name="evduration" type="number" min="1" max="1440" value="60" required>
      </div>
      <div>
        <label for="evcategory">Category *</label>
        <select id="evcategory" name="evcategory" required>
          <option value="0">Any</option><option value="18">Discussion</option><option value="19">Sports</option>
          <option value="20">Live Music</option><option value="22">Commercial</option><option value="23">Nightlife/Entertainment</option>
          <option value="24">Games/Contests</option><option value="25">Pageants</option><option value="26">Education</option>
          <option value="27">Arts and Culture</option><option value="28">Charity/Support Groups</option><option value="29">Miscellaneous</option>
        </select>
      </div>
    </div>

    <div class="row">
      <div>
        <label for="evrating">Rating *</label>
        <select id="evrating" name="evrating" required>
          <option value="0">General</option><option value="1">Mature</option><option value="2">Adult</option>
        </select>
      </div>
      <div>
        <label for="evcover">Cover Amount</label>
        <input id="evcover" name="evcover" type="number" min="0" step="1" value="0">
      </div>
    </div>

    <label for="evdesc">Description *</label>
    <textarea id="evdesc" name="evdesc" required placeholder="Details about your event…"></textarea>

    <label for="evhglink">HG Link (optional)</label>
    <input id="evhglink" name="evhglink" type="text" placeholder="hop://yourgrid.example/Region/128/128/22">

    <div class="bar"></div>
    <h3>World Context (manual)</h3>
    <p class="muted">Enter Region name and local X,Y,Z. The server will compute the rest; var-regions supported.</p>

    <div class="row">
      <div>
        <label for="evownerid">Owner UUID *</label>
        <input id="evownerid" name="evownerid" type="text" required placeholder="8713d37e-1a17-4845-9cc4-362ebf6af1c5"
               pattern="^[0-9a-fA-F]{8}(-[0-9a-fA-F]{4}){3}-[0-9a-fA-F]{12}$">
      </div>
      <div>
        <label for="simname">Region Name *</label>
        <input id="simname" name="simname" type="text" required placeholder="Sandbox">
      </div>
    </div>

    <label for="evobjpos">Local Position X,Y,Z *</label>
    <input id="evobjpos" name="evobjpos" type="text" required placeholder="145,145,25" pattern="^\d{1,5},\d{1,5},\d{1,5}$">

    <!-- Optional: Parcel UUID (leave blank if unknown; server will try to derive) -->
    <label for="evparcelid">Parcel UUID (optional)</label>
    <input id="evparcelid" name="evparcelid" type="text" placeholder="leave blank if unknown"
           pattern="^$|^[0-9a-fA-F]{8}(-[0-9a-fA-F]{4}){3}-[0-9a-fA-F]{12}$">

    <!-- Hidden: regionCorner no longer required -->
    <input type="hidden" name="regionCorner" value="">

    <div class="bar"></div>
    <p class="muted">We store UTC internally for accurate “Ongoing &amp; Upcoming”.</p>

    <!-- Required hidden fields -->
    <input type="hidden" name="evversion" value="<?php echo htmlspecialchars($FORM_VERSION); ?>">
    <input type="hidden" name="me"        value="<?php echo htmlspecialchars($SHARED_KEY); ?>">

    <div class="actions">
      <button type="submit">Submit Event</button>
      <button type="reset" class="light">Reset</button>
    </div>
  </form>
</div>

<script>
function uuidOK(s){ return /^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/i.test(s); }
function dateOK(s){ return /^\d{4}-\d{2}-\d{2}$/.test(s); }
function timeOK(s){ return /^([01]?\d|2[0-3]):[0-5]\d$/.test(s); }
function localOK(s){ return /^\d{1,5},\d{1,5},\d{1,5}$/.test(s); }

function validateForm(f){
  var errs=[];
  if(!f.evname.value.trim()) errs.push('Title is required.');
  if(!dateOK(f.evdate.value)) errs.push('Date must be YYYY-MM-DD (Grid Time).');
  if(!timeOK(f.evtime.value)) errs.push('Time must be HH:MM 24h (Grid Time).');
  if(!f.evduration.value || +f.evduration.value<1) errs.push('Duration must be at least 1 minute.');
  if(!uuidOK(f.evownerid.value)) errs.push('Owner UUID is invalid.');
  if(!f.simname.value.trim()) errs.push('Region name is required.');
  if(!localOK(f.evobjpos.value)) errs.push('Local position must look like "145,145,25".');
  if(errs.length){ alert(errs.join('\\n')); return false; }
  return true;
}
</script>
