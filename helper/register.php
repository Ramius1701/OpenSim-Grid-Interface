<?php
//////////////////////////////////////////////////////////////////////////////
// register.php                                                             //
//////////////////////////////////////////////////////////////////////////////

include("databaseinfo.php");

// --- Access control (Casperia Prime addition) ---
// This endpoint previously had zero authentication: anyone on the internet
// could inject arbitrary host/port entries into search_hostsregister (which
// parser.php then dutifully tries to connect to - a potential SSRF vector if
// an internal/private IP were registered), or silently delete a real
// region's registration via service=offline just by knowing its host/port.
// Restrict to known, trusted caller IPs - same pattern used for
// MoneyServer's AddBankerMoney endpoint. Defaults to localhost-only; add
// your actual region server IPs below if regions run on separate machines
// from this web server.
$allowedRegisterIPs = array('127.0.0.1', '::1');

$callerIP = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($callerIP, $allowedRegisterIPs, true)) {
    error_log("[search/register] Rejected call from disallowed address {$callerIP} - add it to \$allowedRegisterIPs in register.php if this caller should be trusted");
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

$hostname = "casperia.ddns.net";
$port = "8002";
$service = "";

if (isset($_GET['host']))    $hostname = $_GET['host'];
if (isset($_GET['port']))    $port = $_GET['port'];
if (isset($_GET['service'])) $service = $_GET['service'];

if ($hostname == "" || $port == "")
{
    echo "Missing host name and/or port address\n";
    exit;
}

// Attempt to connect to the database
try {
  $db = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME", $DB_USER, $DB_PASSWORD);
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch(PDOException $e)
{
  echo "Error connecting to database\n";
  // Security Fix: Hidden file
  file_put_contents('.PDOErrors.txt', $e->getMessage() . "\n-----\n", FILE_APPEND);
  exit;
}

if ($service == "online")
{
    // Check if there is already a database row for this host
    $query = $db->prepare("SELECT register FROM search_hostsregister WHERE host = ? AND port = ?");
    $query->execute( array($hostname, $port) );

    // Get the request time as a timestamp for later
    $timestamp = $_SERVER['REQUEST_TIME'];

    // If a database row was returned check the nextcheck date
    if ($query->rowCount() > 0)
    {
        $query = $db->prepare("UPDATE search_hostsregister SET " .
                     "register = ?, " .
                     "nextcheck = 0, checked = 0, failcounter = 0 " .
                     "WHERE host = ? AND port = ?");
        $query->execute( array($timestamp, $hostname, $port) );
    }
    else
    {
        // The SELECT did not return a result. Insert a new record.
        $query = $db->prepare("INSERT INTO search_hostsregister VALUES (?, ?, ?, 0, 0, 0)");
        $query->execute( array($hostname, $port, $timestamp) );
    }
}

if ($service == "offline")
{
    $query = $db->prepare("DELETE FROM search_hostsregister WHERE host = ? AND port = ?");
    $query->execute( array($hostname, $port) );
}

$db = NULL;
?>