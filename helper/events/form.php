<?php
// form.php

function uuid_from_hex32($hex) {
    $hex = strtolower(preg_replace('/[^0-9a-f]/i','', $hex));
    if (strlen($hex) !== 32) return '';
    return substr($hex,0,8) . '-' . substr($hex,8,4) . '-' . substr($hex,12,4) . '-' .
           substr($hex,16,4) . '-' . substr($hex,20,12);
}

// ---- read GET (and fall back to raw query string if $_GET is empty/sanitized) ----
$ownerHex     = $_GET['ownerUUIDhex'] ?? '';
$parcelUUID   = $_GET['parcelUUID']   ?? '';
$region       = $_GET['region']       ?? '';
$regionCorner = $_GET['regionCorner'] ?? '';
$localPos     = $_GET['localPos']     ?? '';

if (($ownerHex === '' || $parcelUUID === '' || $region === '' || $regionCorner === '' || $localPos === '')
    && !empty($_SERVER['QUERY_STRING'])) {
    // Parse the raw query string as a fallback (handles odd server configs)
    parse_str($_SERVER['QUERY_STRING'], $qs);
    $ownerHex     = $ownerHex     ?: ($qs['ownerUUIDhex'] ?? '');
    $parcelUUID   = $parcelUUID   ?: ($qs['parcelUUID']   ?? '');
    $region       = $region       ?: ($qs['region']       ?? '');
    $regionCorner = $regionCorner ?: ($qs['regionCorner'] ?? '');
    $localPos     = $localPos     ?: ($qs['localPos']     ?? '');
}

$ownerUUID = uuid_from_hex32($ownerHex);

// (Optional) quick debug: lengths so you can confirm everything arrived
$debug = sprintf(
    'uuidhex_len=%d, uuid_len=%d, parcel_len=%d, regionCorner_len=%d, localPos_len=%d',
    strlen($ownerHex), strlen($ownerUUID), strlen($parcelUUID), strlen($regionCorner), strlen($localPos)
);
?>
<!doctype html>
<meta charset="utf-8">
<title>Create / Edit Event</title>
<style>
  body{font:16px system-ui,Segoe UI,Roboto,Arial;margin:2rem}
  form{max-width:700px}
  label{display:block;margin:.6rem 0}
  input[type=text], input[type=number], textarea, select{width:100%;padding:.55rem}
  button{padding:.6rem 1rem;cursor:pointer}
  .muted{color:#666}
</style>

<h1>Create / Edit Event</h1>
<p class="muted">
  Submitting as: <strong><?php echo htmlspecialchars($ownerUUID ?: 'Unknown'); ?></strong>
  <small>(<?php echo htmlspecialchars($debug); ?>)</small><br>
  Region: <strong><?php echo htmlspecialchars($region ?: 'Unknown'); ?></strong>
</p>

<form method="post" action="submit.php">
  <label>Title
    <input name="evname" type="text" required>
  </label>

  <label>Date (YYYY-MM-DD)
    <input name="evdate" type="text" placeholder="2025-11-01" required>
  </label>

  <label>Time (HH:MM, 24h)
    <input name="evtime" type="text" placeholder="07:00" required>
  </label>

  <label>Duration (minutes)
    <input name="evduration" type="number" min="1" value="60">
  </label>

  <label>Category
    <select name="evcategory">
      <option value="0">Any</option>
      <option value="18">Discussion</option>
      <option value="19">Sports</option>
      <option value="20">Live Music</option>
      <option value="22">Commercial</option>
      <option value="23">Nightlife/Entertainment</option>
      <option value="24">Games/Contests</option>
      <option value="25">Pageants</option>
      <option value="26">Education</option>
      <option value="27">Arts and Culture</option>
      <option value="28">Charity/Support Groups</option>
      <option value="29">Miscellaneous</option>
    </select>
  </label>

  <label>Rating
    <select name="evrating">
      <option value="0">General</option>
      <option value="1">Mature</option>
      <option value="2">Adult</option>
    </select>
  </label>

  <label>Cover amount
    <input name="evcover" type="number" min="0" value="0">
  </label>

  <label>Description
    <textarea name="evdesc" rows="6" placeholder="Details about your event…"></textarea>
  </label>

  <p class="muted">Times are in <strong>Grid Time (PST/PDT)</strong>; we’ll convert to UTC for search.</p>

  <!-- Hidden context forwarded to submit.php -->
  <input type="hidden" name="evownerid"     value="<?php echo htmlspecialchars($ownerUUID); ?>">
  <input type="hidden" name="evparcelid"    value="<?php echo htmlspecialchars($parcelUUID); ?>">
  <input type="hidden" name="simname"       value="<?php echo htmlspecialchars($region); ?>">
  <input type="hidden" name="regionCorner"  value="<?php echo htmlspecialchars($regionCorner); ?>">
  <input type="hidden" name="evobjpos"      value="<?php echo htmlspecialchars($localPos); ?>">
  <input type="hidden" name="evhglink"      value="">
  <input type="hidden" name="evversion"     value="Events Form:0.31b">
  <input type="hidden" name="me"            value="CHANGEME"><!-- must match PHP -->

  <button type="submit">Save Event</button>
</form>

<!-- View-source helper: -->
<!-- QS=<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? ''); ?> -->
