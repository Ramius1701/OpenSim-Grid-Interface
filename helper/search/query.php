<?php
// --- XML-RPC Polyfill for PHP 8+ ---
require __DIR__ . '/vendor/autoload.php'; 
// -----------------------------------

//The description of the flags used in this file are being based on the
//DirFindFlags enum which is defined in OpenMetaverse/DirectoryManager.cs
//of the libopenmetaverse library.

include("databaseinfo.php");

// Attempt to connect to the database
try {
  $db = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME", $DB_USER, $DB_PASSWORD);
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch(PDOException $e)
{
  echo "Error connecting to database\n";
  // Security Fix: Hidden file with dot prefix
  file_put_contents('.PDOErrors.txt', $e->getMessage() . "\n-----\n", FILE_APPEND);
  exit;
}

#
#  Copyright (c)Melanie Thielker (http://opensimulator.org/)
#

###################### No user serviceable parts below #####################

function join_terms($glue, $terms, $add_paren)
{
    if (count($terms) > 1)
    {
        $type = join($glue, $terms);
        if ($add_paren == True)
            $type = "(" . $type . ")";
    }
    else
    {
        if (count($terms) == 1)
            $type = $terms[0];
        else
            $type = "";
    }

    return $type;
}


function process_region_type_flags($flags)
{
    $terms = array();

    if ($flags & 16777216)  //IncludePG (1 << 24)
        $terms[] = "mature = 'PG'";
    if ($flags & 33554432)  //IncludeMature (1 << 25)
        $terms[] = "mature = 'Mature'";
    if ($flags & 67108864)  //IncludeAdult (1 << 26)
        $terms[] = "mature = 'Adult'";

    return join_terms(" OR ", $terms, True);
}


#
# The XMLRPC server object
#

$xmlrpc_server = xmlrpc_server_create();

#
# Places Query
#

xmlrpc_server_register_method($xmlrpc_server, "dir_places_query", "dir_places_query");

function dir_places_query($method_name, $params, $app_data)
{
    global $db;

    $req = isset($params[0]) ? $params[0] : array();

    // PHP 8 Fix: Check if keys exist
    $flags       = isset($req['flags']) ? (int)$req['flags'] : 0;
    $text        = isset($req['text']) ? $req['text'] : "%%%";
    $category    = isset($req['category']) ? (int)$req['category'] : 0;
    $query_start = isset($req['query_start']) ? (int)$req['query_start'] : 0;

    $pieces = explode(" ", $text);
    $text = join("%", $pieces);

    if ($text != "%%%")
        $text = "%$text%";
    else
    {
        $response_xml = xmlrpc_encode(array(
                'success'      => False,
                'errorMessage' => "Invalid search terms"
        ));

        print $response_xml;
        return;
    }

    $terms = array();
    $sqldata = array();

    $type = process_region_type_flags($flags);
    if ($type != "")
        $type = " AND " . $type;

    $order = "";
    if ($flags & 1024)
        $order = "dwell DESC,";

    if ($category <= 0)
        $cat_where = "";
    else
    {
        $cat_where = "searchcategory = :cat AND ";
        $sqldata['cat'] = $category;
    }

    $sqldata['text1'] = $text;
    $sqldata['text2'] = $text;

    //Prevent SQL injection by checking that $query_start is a number
    if ($query_start != 0 && ($query_start%100 != 0))
        $query_start = 0;

    $query_end = 101;

    $sql = "SELECT * FROM search_parcels WHERE $cat_where" .
           " (parcelname LIKE :text1" .
           " OR description LIKE :text2)" .
           $type . " ORDER BY $order parcelname" .
           " LIMIT ".$query_start.",".$query_end.";";
    $query = $db->prepare($sql);
    $result = $query->execute($sqldata);

    $data = array();
    while ($row = $query->fetch(PDO::FETCH_ASSOC))
    {
        $data[] = array(
                "parcel_id" => $row["infouuid"],
                "name" => $row["parcelname"],
                "for_sale" => "False",
                "auction" => "False",
                "dwell" => $row["dwell"]);
    }
    $response_xml = xmlrpc_encode(array(
        'success'      => True,
        'errorMessage' => "",
        'data' => $data
    ));

    print $response_xml;
}

#
# Popular Places Query
#

xmlrpc_server_register_method($xmlrpc_server, "dir_popular_query", "dir_popular_query");

function dir_popular_query($method_name, $params, $app_data)
{
    global $db;

    $req = isset($params[0]) ? $params[0] : array();

    // PHP 8 Fix: Check if keys exist
    $text        = isset($req['text']) ? $req['text'] : "";
    $flags       = isset($req['flags']) ? (int)$req['flags'] : 0;
    $query_start = isset($req['query_start']) ? (int)$req['query_start'] : 0;

    $terms = array();
    $sqldata = array();

    if ($flags & 0x1000)    //PicturesOnly (1 << 12)
        $terms[] = "has_picture = 1";

    if ($flags & 0x0800)    //PgSimsOnly (1 << 11)
        $terms[] = "mature = 0";

    if ($text != "")
    {
        $terms[] = "(name LIKE :text)";
        $text = "%$text%";
        $sqldata['text'] = $text;
    }

    if (count($terms) > 0)
        $where = " WHERE " . join_terms(" AND ", $terms, False);
    else
        $where = "";

    //Prevent SQL injection
    if (!is_int($query_start))
         $query_start = 0;

    $query = $db->prepare("SELECT * FROM search_popularplaces" . $where .
                          " LIMIT $query_start,101");
    $result = $query->execute($sqldata);

    $data = array();
    while ($row = $query->fetch(PDO::FETCH_ASSOC))
    {
        $data[] = array(
                "parcel_id" => $row["infoUUID"],
                "name" => $row["name"],
                "dwell" => $row["dwell"]);
    }

    $response_xml = xmlrpc_encode(array(
            'success'      => True,
            'errorMessage' => "",
            'data' => $data));

    print $response_xml;
}

#
# Land Query
#

xmlrpc_server_register_method($xmlrpc_server, "dir_land_query", "dir_land_query");

function dir_land_query($method_name, $params, $app_data)
{
    global $db;

    $req = isset($params[0]) ? $params[0] : array();

    // PHP 8 Fix: Check if keys exist
    $flags       = isset($req['flags']) ? (int)$req['flags'] : 0;
    $type        = isset($req['type']) ? (int)$req['type'] : 4294967295;
    $price       = isset($req['price']) ? (int)$req['price'] : 0;
    $area        = isset($req['area']) ? (int)$req['area'] : 0;
    $query_start = isset($req['query_start']) ? (int)$req['query_start'] : 0;

    $terms = array();
    $sqldata = array();

    if ($type != 4294967295)    //Include all types of land?
    {
        if (($type & 26) == 2)  // Auction
        {
            $response_xml = xmlrpc_encode(array(
                    'success' => False,
                    'errorMessage' => "No auctions listed"));
            print $response_xml;
            return;
        }

        if (($type & 24) == 8)  //Mainland
            $terms[] = "parentestate = 1";
        if (($type & 24) == 16) //Estate
            $terms[] = "parentestate <> 1";
    }

    $s = process_region_type_flags($flags);
    if ($s != "")
        $terms[] = $s;

    if ($flags & 0x100000)  //LimitByPrice
    {
        $terms[] = "saleprice <= :price";
        $sqldata['price'] = $price;
    }
    if ($flags & 0x200000)  //LimitByArea
    {
        $terms[] = "area >= :area";
        $sqldata['area'] = $area;
    }

    $order = "lsq";     //PerMeterSort

    if ($flags & 0x80000)   //NameSort
        $order = "parcelname";
    if ($flags & 0x10000)   //PriceSort
        $order = "saleprice";
    if ($flags & 0x40000)   //AreaSort
        $order = "area";
    if (!($flags & 0x8000)) //SortAsc
        $order .= " DESC";

    if (count($terms) > 0)
        $where = " WHERE " . join_terms(" AND ", $terms, False);
    else
        $where = "";

    if (!is_int($query_start))
         $query_start = 0;

    $sql = "SELECT *,saleprice/area AS lsq FROM search_parcelsales" . $where .
           " ORDER BY " . $order . " LIMIT $query_start,101";
    $query = $db->prepare($sql);
    $result = $query->execute($sqldata);

    $data = array();
    while ($row = $query->fetch(PDO::FETCH_ASSOC))
    {
        $data[] = array(
                "parcel_id" => $row["infoUUID"],
                "name" => $row["parcelname"],
                "auction" => "false",
                "for_sale" => "true",
                "sale_price" => $row["saleprice"],
                "landing_point" => $row["landingpoint"],
                "region_UUID" => $row["regionUUID"],
                "area" => $row["area"]);
    }

    $response_xml = xmlrpc_encode(array(
            'success'      => True,
            'errorMessage' => "",
            'data' => $data));

    print $response_xml;
}

#
# Events Query
#
xmlrpc_server_register_method($xmlrpc_server, "dir_events_query", "dir_events_query");

function dir_events_query($method_name, $params, $app_data)
{
    global $db;

    $req = isset($params[0]) && is_array($params[0]) ? $params[0] : array();
    $text = isset($req['text']) ? $req['text'] : '';
    $flags = isset($req['flags']) ? (int)$req['flags'] : 0;
    $query_start = isset($req['query_start']) ? $req['query_start'] : 0;

    if ($text === "%%%") {
        print xmlrpc_encode(array('success' => False, 'errorMessage' => "Invalid search terms"));
        return;
    }

    $pieces = explode("|", $text);
    $dayRaw = isset($pieces[0]) ? $pieces[0] : 0;
    $category = isset($pieces[1]) ? (int)$pieces[1] : 0;
    $search_text = (count($pieces) < 3) ? "" : $pieces[2];

    $dr = strtolower(trim((string)$dayRaw));
    if ($dr === '' || $dr === 'bydate' || $dr === 'by' || $dr === 'date' || $dr === 'd') {
        $day = 0;           // Today (SLT)
    } elseif ($dr === 'u') {
        $day = 'u';         // Upcoming/Ongoing
    } elseif ($dr === 'y' || $dr === 'yesterday') {
        $day = -1;          // Yesterday
    } elseif ($dr === 't' || $dr === 'today') {
        $day = 0;           // Today
    } elseif ($dr === 'tom' || $dr === 'tm' || $dr === 'tomorrow' || $dr === 'm') {
        $day = 1;           // Tomorrow
    } elseif (preg_match('/^-?\d+$/', $dr)) {
        // Fix for "Next Day" scrolling: Allow any integer (-5, 30, etc.)
        $day = (int)$dr;
    } else {
        $day = 0;
    }

    $terms = array();
    $sqldata = array();

    $now = time();
    $tz  = new DateTimeZone('America/Los_Angeles');

    if ($day === 'u') {
        $terms[] = "dateUTC + duration * 60 >= :now";
        $sqldata['now'] = (int)$now;
    } else {
        $base       = new DateTimeImmutable('now', $tz);
        // This line handles the math for -5 days or +30 days automatically
        $startLocal = $base->setTime(0, 0, 0)->modify(((int)$day).' day');
        $endLocal   = $startLocal->modify('+1 day');

        $utcStart   = $startLocal->setTimezone(new DateTimeZone('UTC'))->getTimestamp();
        $utcEnd     = $endLocal  ->setTimezone(new DateTimeZone('UTC'))->getTimestamp();

        $terms[]             = "(dateUTC >= :utcStart AND dateUTC < :utcEnd)";
        $sqldata['utcStart'] = (int)$utcStart;
        $sqldata['utcEnd']   = (int)$utcEnd;
    }

    if ($category > 0) {
        $terms[] = "category = :category";
        $sqldata['category'] = $category;
    }

    $type = array();
    if ($flags & 16777216) $type[] = "eventflags = 0";
    if ($flags & 33554432) $type[] = "eventflags = 1";
    if ($flags & 67108864) $type[] = "eventflags = 2";
    if (count($type) > 0) $terms[] = join_terms(" OR ", $type, True);

    if ($search_text !== "") {
        $terms[] = "(name LIKE :text1 OR description LIKE :text2)";
        $sqldata['text1'] = "%" . $search_text . "%";
        $sqldata['text2'] = "%" . $search_text . "%";
    }

    $where = (count($terms) > 0) ? " WHERE " . join_terms(" AND ", $terms, False) : "";

    $offset = (int)$query_start;
    if ($offset < 0) $offset = 0;
    $limit = 101;

    $sql = "SELECT owneruuid,name,eventid,dateUTC,duration,eventflags,simname,globalPos FROM search_events"
         . $where . " ORDER BY dateUTC ASC, eventid ASC LIMIT " . $offset . "," . $limit;

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($sqldata);

        $data = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dateStr = (new DateTimeImmutable('@' . (int)$row['dateUTC']))
                ->setTimezone($tz)
                ->format('m/d h:i A');

            $raw = isset($row['globalPos']) ? (string)$row['globalPos'] : '';
            $landing_point  = '';
            $globalposition = '';
            if ($raw !== '') {
                $parts = preg_split('/[,\s]+/', trim($raw));
                $parts = array_slice(array_pad($parts, 3, '0'), 0, 3);
                $x = (int)round($parts[0]);
                $y = (int)round($parts[1]);
                $z = (int)round($parts[2]);
                $landing_point  = $x.' '.$y.' '.$z; 
                $globalposition = $x.','.$y.','.$z;
            }
            $simname = isset($row['simname']) ? (string)$row['simname'] : '';

            $data[] = array(
                "owner_id"       => $row["owneruuid"],
                "name"           => $row["name"],
                "event_id"       => (int)$row["eventid"],
                "date"           => $dateStr,
                "dateUTC"        => (int)$row["dateUTC"],
                "unix_time"      => (int)$row["dateUTC"],
                "duration"       => (int)$row["duration"],
                "event_flags"    => (int)$row["eventflags"],
                "landing_point"  => $landing_point,
                "globalposition" => $globalposition,
                "posglobal"      => $globalposition,
                "globalPos"      => $landing_point,
                "simname"        => $simname
            );
        }

        print xmlrpc_encode(array('success' => True, 'errorMessage' => "", 'data' => $data));
    } catch (PDOException $e) {
        file_put_contents('.PDOErrors.txt', $e->getMessage() . "\n-----\n", FILE_APPEND);
        print xmlrpc_encode(array('success' => False, 'errorMessage' => "Query failed"));
    }
}

#
# Classifieds Query
#

xmlrpc_server_register_method($xmlrpc_server, "dir_classified_query", "dir_classified_query");

function dir_classified_query ($method_name, $params, $app_data)
{
    global $db;

    $req = isset($params[0]) ? $params[0] : array();

    // PHP 8 Fix: Check if keys exist
    $text           = isset($req['text']) ? $req['text'] : "%%%";
    $flags          = isset($req['flags']) ? (int)$req['flags'] : 0;
    $category       = isset($req['category']) ? (int)$req['category'] : 0;
    $query_start    = isset($req['query_start']) ? (int)$req['query_start'] : 0;

    if ($text == "%%%")
    {
        $response_xml = xmlrpc_encode(array(
                'success'      => False,
                'errorMessage' => "Invalid search terms"
        ));
        print $response_xml;
        return;
    }

    $terms = array();
    $sqldata = array();

    $maturity = 0;
    if ($flags & 5)     //Legacy or current PG bit?
        $maturity |= 5;
    if ($flags & 10)    //Legacy or current Mature bit?
        $maturity |= 8;
    if ($flags & 64)    //Adult bit (1 << 6)
        $maturity |= 64;

    if ($maturity)
        $terms[] = "classifiedflags & $maturity";

    if ($category > 0)
    {
        $terms[] = "category = :category";
        $sqldata['category'] = $category;
    }

    if ($text != "")
    {
        $terms[] = "(name LIKE :text1" .
                   " OR description LIKE :text2)";

        $text = "%$text%";
        $sqldata['text1'] = $text;
        $sqldata['text2'] = $text;
    }

    if (count($terms) > 0)
        $where = " WHERE " . join_terms(" AND ", $terms, False);
    else
        $where = "";

    if (!is_int($query_start))
         $query_start = 0;

    $sql = "SELECT * FROM classifieds" . $where .
           " ORDER BY priceforlisting DESC" .
           " LIMIT $query_start,101";
    $query = $db->prepare($sql);

    $result = $query->execute($sqldata);

    $data = array();
    while ($row = $query->fetch(PDO::FETCH_ASSOC))
    {
        $flags = $row["classifiedflags"];
        if ($flags & 1)
            $flags |= 4;
        if ($flags & 2)
            $flags |= 8;

        $data[] = array(
                "classifiedid" => $row["classifieduuid"],
                "name" => $row["name"],
                "classifiedflags" => $flags,
                "creation_date"   => $row["creationdate"],
                "expiration_date" => $row["expirationdate"],
                "priceforlisting" => $row["priceforlisting"]);
    }

    $response_xml = xmlrpc_encode(array(
            'success'      => True,
            'errorMessage' => "",
            'data' => $data));

    print $response_xml;
}

#
# Events Info Query
#
xmlrpc_server_register_method($xmlrpc_server, "event_info_query", "event_info_query");

function event_info_query($method_name, $params, $app_data)
{
    global $db;

    $req = isset($params[0]) && is_array($params[0]) ? $params[0] : array();
    $eventID = isset($req['eventID']) ? (int)$req['eventID'] : 0;

    try {
        $stmt = $db->prepare("SELECT * FROM search_events WHERE eventid = ? LIMIT 1");
        $stmt->execute(array($eventID));

        $data = array();
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $date = (new DateTimeImmutable('@' . (int)$row['dateUTC']))
                ->setTimezone(new DateTimeZone('America/Los_Angeles'))
                ->format('Y-m-d H:i:s');

            $category = "*Unspecified*";

            $raw = isset($row['globalPos']) ? (string)$row['globalPos'] : '';
            $landing_point  = '';
            $globalposition = '';
            if ($raw !== '') {
                $parts = preg_split('/[,\s]+/', trim($raw));
                $parts = array_slice(array_pad($parts, 3, '0'), 0, 3);
                $x = (int)round($parts[0]);
                $y = (int)round($parts[1]);
                $z = (int)round($parts[2]);
                $landing_point  = $x.' '.$y.' '.$z;
                $globalposition = $x.','.$y.','.$z;
            }
            $simname = isset($row['simname']) ? (string)$row['simname'] : '';

            $data[] = array(
                "event_id"       => (int)$row["eventid"],
                "creator"        => $row["creatoruuid"],
                "name"           => $row["name"],
                "category"       => $category,
                "description"    => $row["description"],
                "date"           => $date,
                "dateUTC"        => (int)$row["dateUTC"],
                "unix_time"      => (int)$row["dateUTC"],
                "duration"       => (int)$row["duration"],
                "covercharge"    => (int)$row["covercharge"],
                "coveramount"    => (int)$row["coveramount"],
                "simname"        => $simname,
                "globalposition" => $globalposition,
                "posglobal"      => $globalposition,
                "globalPos"      => $landing_point,
                "eventflags"     => (int)$row["eventflags"],
                "landing_point"  => $landing_point
            );
        }

        print xmlrpc_encode(array('success' => True, 'errorMessage' => "", 'data' => $data));
    } catch (PDOException $e) {
        file_put_contents('.PDOErrors.txt', $e->getMessage() . "\n-----\n", FILE_APPEND);
        print xmlrpc_encode(array('success' => False, 'errorMessage' => "Query failed"));
    }
}

#
# Classifieds Info Query
#

xmlrpc_server_register_method($xmlrpc_server, "classifieds_info_query", "classifieds_info_query");

function classifieds_info_query($method_name, $params, $app_data)
{
    global $db;

    $req = isset($params[0]) ? $params[0] : array();
    // PHP 8 Fix
    $classifiedID = isset($req['classifiedID']) ? $req['classifiedID'] : "";

    $query = $db->prepare("SELECT * FROM classifieds WHERE classifieduuid = ?");
    $result = $query->execute( array($classifiedID) );

    $data = array();
    while ($row = $query->fetch(PDO::FETCH_ASSOC))
    {
        $data[] = array(
                "classifieduuid" => $row["classifieduuid"],
                "creatoruuid" => $row["creatoruuid"],
                "creationdate" => $row["creationdate"],
                "expirationdate" => $row["expirationdate"],
                "category" => $row["category"],
                "name" => $row["name"],
                "description" => $row["description"],
                "parceluuid" => $row["parceluuid"],
                "parentestate" => $row["parentestate"],
                "snapshotuuid" => $row["snapshotuuid"],
                "simname" => $row["simname"],
                "posglobal" => $row["posglobal"],
                "parcelname" => $row["parcelname"],
                "classifiedflags" => $row["classifiedflags"],
                "priceforlisting" => $row["priceforlisting"]);
    }

    $response_xml = xmlrpc_encode(array(
            'success'      => True,
            'errorMessage' => "",
            'data' => $data));

    print $response_xml;
}

#
# Process the request
#

$request_xml = file_get_contents("php://input");

xmlrpc_server_call_method($xmlrpc_server, $request_xml, '');
xmlrpc_server_destroy($xmlrpc_server);

$db = NULL;
?>