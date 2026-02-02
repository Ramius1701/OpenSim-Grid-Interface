<?php
//////////////////////////////////////////////////////////////////////////////
// register.php                                                             //
//////////////////////////////////////////////////////////////////////////////

include("databaseinfo.php");

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