<?php
include("databaseinfo.php");

//Supress all Warnings/Errors
//error_reporting(0);

$now = time();

// Attempt to connect to the search database
try {
  $db = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME", $DB_USER, $DB_PASSWORD);
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch(PDOException $e)
{
  echo "Error connecting to the search database\n";
  // Security Fix: Hidden file
  file_put_contents('.PDOErrors.txt', $e->getMessage() . "\n-----\n", FILE_APPEND);
  exit;
}


function GetURL($host, $port, $url)
{
    $url = "http://$host:$port/$url";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $data = curl_exec($ch);
    if (curl_errno($ch) == 0)
    {
        curl_close($ch);
        return $data;
    }

    curl_close($ch);
    return "";
}

function CheckHost($hostname, $port)
{
    global $db, $now;

    $xml = GetURL($hostname, $port, "?method=collector");
    if ($xml == "") //No data was retrieved? (CURL may have timed out)
        $failcounter = "failcounter + 1";
    else
        $failcounter = "0";

    //Update nextcheck to be 10 minutes from now.
    $next = $now + 600;

    $query = $db->prepare("UPDATE search_hostsregister SET nextcheck = ?," .
                          " checked = 1, failcounter = $failcounter" .
                          " WHERE host = ? AND port = ?");
    $query->execute( array($next, $hostname, $port) );

    if ($xml != "")
        parse($hostname, $port, $xml);
}

function parse($hostname, $port, $xml)
{
    global $db, $now;

    $objDOM = new DOMDocument();
    $objDOM->resolveExternals = false;

    if ($objDOM->loadXML($xml) == False)
        return;

    $regiondata = $objDOM->getElementsByTagName("regiondata");

    if ($regiondata->length == 0)
        return;

    $regiondata = $regiondata->item(0);

    $expire = $regiondata->getElementsByTagName("expire")->item(0)->nodeValue;
    $next = $now + $expire;

    $query = $db->prepare("UPDATE search_hostsregister SET nextcheck = ?" .
                          " WHERE host = ? AND port = ?");
    $query->execute( array($next, $hostname, $port) );

    $regionlist = $regiondata->getElementsByTagName("region");

    foreach ($regionlist as $region)
    {
        $regioncategory = $region->getAttributeNode("category")->nodeValue;

        $info = $region->getElementsByTagName("info")->item(0);

        $regionuuid = $info->getElementsByTagName("uuid")->item(0)->nodeValue;
        $regionname = $info->getElementsByTagName("name")->item(0)->nodeValue;
        $regionhandle = $info->getElementsByTagName("handle")->item(0)->nodeValue;
        $url = $info->getElementsByTagName("url")->item(0)->nodeValue;

        $check = $db->prepare("SELECT * FROM search_regions WHERE regionUUID = ?");
        $check->execute( array($regionuuid) );

        if ($check->rowCount() > 0)
        {
            $query = $db->prepare("DELETE FROM search_regions WHERE regionUUID = ?");
            $query->execute( array($regionuuid) );
            $query = $db->prepare("DELETE FROM search_parcels WHERE regionUUID = ?");
            $query->execute( array($regionuuid) );
            $query = $db->prepare("DELETE FROM search_allparcels WHERE regionUUID = ?");
            $query->execute( array($regionuuid) );
            $query = $db->prepare("DELETE FROM search_parcelsales WHERE regionUUID = ?");
            $query->execute( array($regionuuid) );
            $query = $db->prepare("DELETE FROM search_objects WHERE regionuuid = ?");
            $query->execute( array($regionuuid) );
        }

        $data = $region->getElementsByTagName("data")->item(0);
        $estate = $data->getElementsByTagName("estate")->item(0);

        $username = $estate->getElementsByTagName("name")->item(0)->nodeValue;
        $useruuid = $estate->getElementsByTagName("uuid")->item(0)->nodeValue;
        $estateid = $estate->getElementsByTagName("id")->item(0)->nodeValue;

        $query = $db->prepare("INSERT INTO search_regions VALUES(:r_name, :r_uuid, " .
                              ":r_handle, :url, :u_name, :u_uuid)");
        $query->execute( array("r_name" => $regionname, "r_uuid" => $regionuuid,
                                "r_handle" => $regionhandle, "url" => $url,
                                "u_name" => $username, "u_uuid" => $useruuid) );

        $parcel = $data->getElementsByTagName("parcel");

        foreach ($parcel as $value)
        {
            $parcelname = $value->getElementsByTagName("name")->item(0)->nodeValue;
            $parceluuid = $value->getElementsByTagName("uuid")->item(0)->nodeValue;
            $infouuid = $value->getElementsByTagName("infouuid")->item(0)->nodeValue;
            $parcellanding = $value->getElementsByTagName("location")->item(0)->nodeValue;
            $parceldescription = $value->getElementsByTagName("description")->item(0)->nodeValue;
            $parcelarea = $value->getElementsByTagName("area")->item(0)->nodeValue;
            $parcelcategory = $value->getAttributeNode("category")->nodeValue;
            $parcelsaleprice = $value->getAttributeNode("salesprice")->nodeValue;
            $dwell = $value->getElementsByTagName("dwell")->item(0)->nodeValue;

            $has_pic = 0;
            $image = "00000000-0000-0000-0000-000000000000";
            $image_node = $value->getElementsByTagName("image");

            if ($image_node->length > 0)
            {
                $image = $image_node->item(0)->nodeValue;
                if ($image != "00000000-0000-0000-0000-000000000000")
                    $has_pic = 1;
            }

            $owner = $value->getElementsByTagName("owner")->item(0);
            $owneruuid = $owner->getElementsByTagName("uuid")->item(0)->nodeValue;
            $group = $value->getElementsByTagName("group")->item(0);

            if ($group != "")
                $groupuuid = $group->getElementsByTagName("groupuuid")->item(0)->nodeValue;
            else
                $groupuuid = "00000000-0000-0000-0000-000000000000";

            $parcelforsale = $value->getAttributeNode("forsale")->nodeValue;
            $parceldirectory = $value->getAttributeNode("showinsearch")->nodeValue;
            $parcelbuild = $value->getAttributeNode("build")->nodeValue;
            $parcelscript = $value->getAttributeNode("scripts")->nodeValue;
            $parcelpublic = $value->getAttributeNode("public")->nodeValue;

            $query = $db->prepare("DELETE FROM search_popularplaces WHERE parcelUUID = ?");
            $query->execute( array($parceluuid) );

            $query = $db->prepare("INSERT INTO search_allparcels VALUES(" .
                                    ":r_uuid, :p_name, :o_uuid, :g_uuid, " .
                                    ":landing, :p_uuid, :i_uuid, :area)");
            $query->execute( array("r_uuid"  => $regionuuid,
                                   "p_name"  => $parcelname,
                                   "o_uuid"  => $owneruuid,
                                   "g_uuid"  => $groupuuid,
                                   "landing" => $parcellanding,
                                   "p_uuid"  => $parceluuid,
                                   "i_uuid"  => $infouuid,
                                   "area"    => $parcelarea) );

            if ($parceldirectory == "true")
            {
                $query = $db->prepare("INSERT INTO search_parcels VALUES(" .
                                       ":r_uuid, :p_name, :p_uuid, :landing, " .
                                       ":desc, :cat, :build, :script, :public, ".
                                       ":dwell, :i_uuid, :r_cat, :pic_uuid)");
                $query->execute( array("r_uuid"  => $regionuuid,
                                       "p_name"  => $parcelname,
                                       "p_uuid"  => $parceluuid,
                                       "landing" => $parcellanding,
                                       "desc"    => $parceldescription,
                                       "cat"     => $parcelcategory,
                                       "build"   => $parcelbuild,
                                       "script"  => $parcelscript,
                                       "public"  => $parcelpublic,
                                       "dwell"   => $dwell,
                                       "i_uuid"  => $infouuid,
                                       "r_cat"   => $regioncategory,
                                       "pic_uuid"   => $image) );

                $query = $db->prepare("INSERT INTO search_popularplaces VALUES(" .
                                       ":p_uuid, :p_name, :dwell, " .
                                       ":i_uuid, :has_pic, :r_cat)");
                $query->execute( array("p_uuid"  => $parceluuid,
                                       "p_name"  => $parcelname,
                                       "dwell"   => $dwell,
                                       "i_uuid"  => $infouuid,
                                       "has_pic" => $has_pic,
                                       "r_cat"   => $regioncategory) );
            }

            if ($parcelforsale == "true")
            {
                $query = $db->prepare("INSERT INTO search_parcelsales VALUES(" .
                                       ":r_uuid, :p_name, :p_uuid, :area, " .
                                       ":price, :landing, :i_uuid, :dwell, " .
                                       ":e_id, :r_cat)");
                $query->execute( array("r_uuid"  => $regionuuid,
                                       "p_name"  => $parcelname,
                                       "p_uuid"  => $parceluuid,
                                       "area"    => $parcelarea,
                                       "price"   => $parcelsaleprice,
                                       "landing" => $parcellanding,
                                       "i_uuid"  => $infouuid,
                                       "dwell"   => $dwell,
                                       "e_id"    => $estateid,
                                       "r_cat"   => $regioncategory) );
            }
        }

        $objects = $data->getElementsByTagName("object");

        foreach ($objects as $value)
        {
            $uuid = $value->getElementsByTagName("uuid")->item(0)->nodeValue;
            $regionuuid = $value->getElementsByTagName("regionuuid")->item(0)->nodeValue;
            $parceluuid = $value->getElementsByTagName("parceluuid")->item(0)->nodeValue;
            $location = $value->getElementsByTagName("location")->item(0)->nodeValue;
            $title = $value->getElementsByTagName("title")->item(0)->nodeValue;
            $description = $value->getElementsByTagName("description")->item(0)->nodeValue;
            $flags = $value->getElementsByTagName("flags")->item(0)->nodeValue;

            $query = $db->prepare("INSERT INTO search_objects VALUES(" .
                                   ":uuid, :p_uuid, :location, " .
                                   ":title, :desc, :r_uuid)");
            $query->execute( array("uuid"     => $uuid,
                                   "p_uuid"   => $parceluuid,
                                   "location" => $location,
                                   "title"    => $title,
                                   "desc"     => $description,
                                   "r_uuid"   => $regionuuid) );
        }
    }
}

$sql = "SELECT host, port FROM search_hostsregister " .
       "WHERE nextcheck<$now AND checked=0 AND failcounter<10 LIMIT 0,10";
$jobsearch = $db->query($sql);

if ($jobsearch->rowCount() == 0)
{
    $jobsearch = $db->query("UPDATE search_hostsregister SET checked = 0");
    $jobsearch = $db->query($sql);
}

while ($jobs = $jobsearch->fetch(PDO::FETCH_NUM))
    CheckHost($jobs[0], $jobs[1]);

$db = NULL;
?>