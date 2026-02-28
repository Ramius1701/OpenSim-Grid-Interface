<?php
$title = "Search";
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/include');
include_once 'include/header.php';
include_once 'include/viewer_context.php';

if (!empty($IS_VIEWER)) {
    echo '<style>
        body { padding-top: 0 !important; padding-bottom: 0 !important; }
        .navbar, .navbar-expand-lg { display: none !important; }
        .footer-modern { display: none !important; }
        .wrap { max-width: 100% !important; margin: 0 !important; padding: 8px !important; }
    </style>';
}

/* ===== DB ===== */
$mysqli = @new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($mysqli->connect_errno) {
    echo '<div class="alert alert-danger my-3">Database connection failed: ' . htmlspecialchars($mysqli->connect_error) . '</div>';
    include_once 'include/footer.php'; exit;
}

$osmain = DB_NAME; // core (People, Groups)
$osmod  = DB_NAME; // search module tables (Parcels, Events, Classifieds, etc.)

/* ===== Helpers ===== */
/* Performance note:
 * Searches use %term% (contains) matching and LOWER() for maximum compatibility.
 * If you ever need more speed, consider switching to prefix matching (term%) and/or
 * disabling LOWER() on case-insensitive collations.
 */

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

function first_existing_table($mysqli,$db,$candidates){
    $candidates = is_array($candidates) ? $candidates : [];
    foreach($candidates as $t){
        $sql="SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?";
        if(!$stmt=$mysqli->prepare($sql)) continue;
        $stmt->bind_param("ss",$db,$t);
        if($stmt->execute()){
            $res=$stmt->get_result();
            if($res && $res->fetch_row()){ $stmt->close(); return $t; }
        }
        $stmt->close();
    }
    return null;
}
function list_columns($mysqli,$db,$table){
    $cols=[]; $sql="SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=?";
    if($stmt=$mysqli->prepare($sql)){
        $stmt->bind_param("ss",$db,$table);
        if($stmt->execute()){
            $res=$stmt->get_result();
            while($row=$res->fetch_assoc()) $cols[]=$row['COLUMN_NAME'];
        }
        $stmt->close();
    }
    return $cols;
}
function pick_first_col($row,$candidates){
    $candidates = is_array($candidates) ? $candidates : [];
    foreach($candidates as $c){ if(isset($row[$c]) && $row[$c]!=='') return $row[$c]; }
    return '';
}
/* Case-insensitive LIKE using LOWER(col) LIKE ? and a lowercased needle */
function build_like_where_ci($colsAvail,$candidates,$needleLower,&$types,&$vals){
    $colsAvail  = is_array($colsAvail)  ? $colsAvail  : [];
    $candidates = is_array($candidates) ? $candidates : [];
    $parts=[];
    foreach($candidates as $c){
        if(in_array($c,$colsAvail,true)){
            $parts[] = "LOWER(`$c`) LIKE ?";
            $types .= 's';
            $vals[] = $needleLower;
        }
    }
    return empty($parts) ? '' : '(' . implode(' OR ',$parts) . ')';
}

/* ===== Inputs ===== */
$q   = isset($_GET['q'])   ? trim($_GET['q'])   : '';
$tab = isset($_GET['tab']) ? trim($_GET['tab']) : '';
if($tab==='') $tab='all'; // default to All
$like_lower  = '%'.mb_strtolower($q,'UTF-8').'%' ;
// Default limits: smaller in viewer, larger on website
$default_limit = (!empty($IS_VIEWER) ? 25 : 50);
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : $default_limit;
$limit = max(1, min(100, $limit));
$hasQuery = ($q !== '');

/* ===== Fetchers ===== */
function fetch_destinations($mysqli,$db,$like_lower,$q,$limit){
    $out=['rows'=>[],'msg'=>null];
    $table=first_existing_table($mysqli,$db,['parcels','allparcels','search_parcels']);
    if(!$table){ $out['msg']='No parcels table found.'; return $out; }
    $cols=list_columns($mysqli,$db,$table);
    $nameCols=['name','parcelName','parcelname'];
    $descCols=['description','parcelDesc','parcelDescription'];
    $regCols =['regionName','regionname','simname','region'];
        $dwellCol=null; foreach(['dwell','traffic'] as $c){ if(in_array($c,$cols,true)){ $dwellCol=$c; break; } }

    $types=''; $vals=[]; $where='1';
    if($q!==''){
        $w1=build_like_where_ci($cols,$nameCols,$like_lower,$types,$vals);
        $types2=''; $vals2=[];
        $w2=build_like_where_ci($cols,$descCols,$like_lower,$types2,$vals2);
        if($w1&&$w2){ $where="($w1 OR $w2)"; $types.=$types2; $vals=array_merge($vals,$vals2); }
        elseif($w1){ $where="($w1)"; }
        elseif($w2){ $where="($w2)"; $types=$types2; $vals=$vals2; }
    } else {
        $where = '0'; // require search term
    }
    $order=$dwellCol? " ORDER BY `$dwellCol` DESC" : (in_array('name',$cols,true)?" ORDER BY `name` ASC":'');
    $sql="SELECT * FROM `$db`.`$table` WHERE $where".$order." LIMIT ?";
    $types.='i'; $vals[]=$limit;
    if(!$stmt=$mysqli->prepare($sql)){ $out['msg']='Failed to prepare Destinations.'; return $out; }
    $stmt->bind_param($types,...$vals);
    if(!$stmt->execute()){ $out['msg']='Destinations query failed.'; $stmt->close(); return $out; }
    $res=$stmt->get_result();
    while($row=$res->fetch_assoc()){
        $out['rows'][]=[
            'name'=>pick_first_col($row,$nameCols)?:'(Unnamed Parcel)',
            'desc'=>pick_first_col($row,$descCols),
            'region'=>pick_first_col($row,$regCols),
            'dwell'=>$dwellCol&&isset($row[$dwellCol])?$row[$dwellCol]:''
        ];
    }
    $stmt->close(); return $out;
}
function fetch_places($mysqli,$db,$like_lower,$q,$limit){
    $out=['rows'=>[],'msg'=>null];
    $table=first_existing_table($mysqli,$db,['popularplaces','search_popularplaces','places','search_places']);
    if(!$table){ $out['msg']='No places table found.'; return $out; }
    $cols=list_columns($mysqli,$db,$table);
    $nameCols=['name','placename','placeName'];
    $descCols=['description','about','details'];
    $regCols =['regionName','region','simname'];
    $types=''; $vals=[]; $where='1';
    if($q!==''){
        $w1=build_like_where_ci($cols,$nameCols,$like_lower,$types,$vals);
        $types2=''; $vals2=[];
        $w2=build_like_where_ci($cols,$descCols,$like_lower,$types2,$vals2);
        if($w1&&$w2){ $where="($w1 OR $w2)"; $types.=$types2; $vals=array_merge($vals,$vals2); }
        elseif($w1){ $where="($w1)"; }
        elseif($w2){ $where="($w2)"; $types=$types2; $vals=$vals2; }
    } else {
        $where='0';
    }
        $dwellCol=null; foreach(['dwell','traffic'] as $c){ if(in_array($c,$cols,true)){ $dwellCol=$c; break; } }
    $order=$dwellCol? " ORDER BY `$dwellCol` DESC" : (in_array('name',$cols,true)?" ORDER BY `name` ASC":'');
$sql="SELECT * FROM `$db`.`$table` WHERE $where".$order." LIMIT ?";
    $types.='i'; $vals[]=$limit;
    if(!$stmt=$mysqli->prepare($sql)){ $out['msg']='Failed to prepare Places.'; return $out; }
    $stmt->bind_param($types,...$vals);
    if(!$stmt->execute()){ $out['msg']='Places query failed.'; $stmt->close(); return $out; }
    $res=$stmt->get_result();
    while($row=$res->fetch_assoc()){
        $out['rows'][]=[
            'name'=>pick_first_col($row,$nameCols)?:'(Place)',
            'desc'=>pick_first_col($row,$descCols),
            'region'=>pick_first_col($row,$regCols),
            'dwell'=>isset($row['dwell'])?$row['dwell']:''
        ];
    }
    $stmt->close(); return $out;
}
function fetch_land($mysqli,$db,$like_lower,$q,$limit){
    $out=['rows'=>[],'msg'=>null];
    $table=first_existing_table($mysqli,$db,['parcelsales','search_parcelsales','landforsale']);
    if(!$table){ $out['msg']='No land/rentals table found.'; return $out; }
    $cols=list_columns($mysqli,$db,$table);
    $nameCols=['name','parcelname','parcelName'];
    $regCols =['regionname','regionName','region','simname'];
    $priceCols=['saleprice','price','rentprice'];
    $areaCols=['area','sqm','parcelarea'];
    $descCols=['description','about','details'];
    $types=''; $vals=[]; $where='1';
    if($q!==''){
        $w1=build_like_where_ci($cols,$nameCols,$like_lower,$types,$vals);
        $types2=''; $vals2=[];
        $w2=build_like_where_ci($cols,$descCols,$like_lower,$types2,$vals2);
        if($w1&&$w2){ $where="($w1 OR $w2)"; $types.=$types2; $vals=array_merge($vals,$vals2); }
        elseif($w1){ $where="($w1)"; }
        elseif($w2){ $where="($w2)"; $types=$types2; $vals=$vals2; }
    } else {
        $where='0';
    }
    $sql="SELECT * FROM `$db`.`$table` WHERE $where LIMIT ?";
    $types.='i'; $vals[]=$limit;
    if(!$stmt=$mysqli->prepare($sql)){ $out['msg']='Failed to prepare Land & Rentals.'; return $out; }
    $stmt->bind_param($types,...$vals);
    if(!$stmt->execute()){ $out['msg']='Land & Rentals query failed.'; $stmt->close(); return $out; }
    $res=$stmt->get_result();
    while($row=$res->fetch_assoc()){
        $out['rows'][]=[
            'name'=>pick_first_col($row,$nameCols)?:'(Parcel)',
            'region'=>pick_first_col($row,$regCols),
            'price'=>pick_first_col($row,$priceCols),
            'area'=>pick_first_col($row,$areaCols),
            'desc'=>pick_first_col($row,$descCols),
        ];
    }
    $stmt->close(); return $out;
}
function fetch_events($mysqli,$db,$like_lower,$q,$limit){
    $out=['rows'=>[],'msg'=>null];
    $table=first_existing_table($mysqli,$db,['events','search_events']);
    if(!$table){ $out['msg']='No events table found.'; return $out; }
    $cols=list_columns($mysqli,$db,$table);
    $titleCols=['name','eventName','title'];
    $descCols=['description','desc','details'];
    $placeCols=['simname','regionName','region','location','parcelName','parcel'];
    $whenCols=['dateUTC','date','dateTime','date_utc','dateUTCtimestamp'];
    $types=''; $vals=[]; $where='1';
    if($q!==''){
        $w1=build_like_where_ci($cols,$titleCols,$like_lower,$types,$vals);
        $types2=''; $vals2=[];
        $w2=build_like_where_ci($cols,$descCols,$like_lower,$types2,$vals2);
        if($w1&&$w2){ $where="($w1 OR $w2)"; $types.=$types2; $vals=array_merge($vals,$vals2); }
        elseif($w1){ $where="($w1)"; }
        elseif($w2){ $where="($w2)"; $types=$types2; $vals=$vals2; }
    } else {
        $where='0';
    }
    $order=''; foreach($whenCols as $c){ if(in_array($c,$cols,true)){ $order=" ORDER BY `$c` ASC"; break; } }
    $sql="SELECT * FROM `$db`.`$table` WHERE $where".$order." LIMIT ?";
    $types.='i'; $vals[]=$limit;
    if(!$stmt=$mysqli->prepare($sql)){ $out['msg']='Failed to prepare Events.'; return $out; }
    $stmt->bind_param($types,...$vals);
    if(!$stmt->execute()){ $out['msg']='Events query failed.'; $stmt->close(); return $out; }
    $res=$stmt->get_result();
    while($row=$res->fetch_assoc()){
        $when = pick_first_col($row,$whenCols);
        // Friendly time formatting: if numeric, treat as Unix epoch (s or ms)
        $whenFmt = $when;
        if($when !== null && $when !== ''){
            if(is_numeric($when)){
                $ts = (int)$when;
                if($ts > 2000000000){ $ts = (int)($ts/1000); }
                $whenFmt = date('Y-m-d H:i', $ts);
            }
        }
        $out['rows'][]=[
            'title'=>pick_first_col($row,$titleCols)?:'(Event)',
            'desc'=>pick_first_col($row,$descCols),
            'place'=>pick_first_col($row,$placeCols),
            'when'=>$whenFmt,
        ];
    }
    $stmt->close(); return $out;
}
function fetch_classifieds($mysqli,$db,$like_lower,$q,$limit){
    $out=['rows'=>[],'msg'=>null];
    $table=first_existing_table($mysqli,$db,['classifieds','search_classifieds']);
    if(!$table){ $out['msg']='No classifieds table found.'; return $out; }
    $cols=list_columns($mysqli,$db,$table);
    $titleCols=['name','classifiedname','title'];
    $descCols=['description','classifiedtext','classifiedDescription','classified'];
    $placeCols=['simname','parcelname','parcelName','regionName','region','location'];
    $priceCols=['price','priceforlisting','priceForListing'];
    $catCols=['category','cat','classifiedcategory'];
    $types=''; $vals=[]; $where='1';
    if($q!==''){
        $w1=build_like_where_ci($cols,$titleCols,$like_lower,$types,$vals);
        $types2=''; $vals2=[];
        $w2=build_like_where_ci($cols,$descCols,$like_lower,$types2,$vals2);
        if($w1&&$w2){ $where="($w1 OR $w2)"; $types.=$types2; $vals=array_merge($vals,$vals2); }
        elseif($w1){ $where="($w1)"; }
        elseif($w2){ $where="($w2)"; $types=$types2; $vals=$vals2; }
    } else {
        $where='0';
    }
    $sql="SELECT * FROM `$db`.`$table` WHERE $where LIMIT ?";
    $types.='i'; $vals[]=$limit;
    if(!$stmt=$mysqli->prepare($sql)){ $out['msg']='Failed to prepare Classifieds.'; return $out; }
    $stmt->bind_param($types,...$vals);
    if(!$stmt->execute()){ $out['msg']='Classifieds query failed.'; $stmt->close(); return $out; }
    $res=$stmt->get_result();
    while($row=$res->fetch_assoc()){
        $out['rows'][]=[
            'title'=>pick_first_col($row,$titleCols)?:'(Listing)',
            'desc'=>pick_first_col($row,$descCols),
            'place'=>pick_first_col($row,$placeCols),
            'price'=>pick_first_col($row,$priceCols),
            'cat'=>pick_first_col($row,$catCols),
        ];
    }
    $stmt->close(); return $out;
}
function fetch_people($mysqli,$db,$like_lower,$q,$limit){
    $out=['rows'=>[],'msg'=>null];
    $table=first_existing_table($mysqli,$db,['UserAccounts','useraccounts','users']);
    if(!$table){ $out['msg']='No people table found.'; return $out; }
    $cols=list_columns($mysqli,$db,$table);
    $firstCols=['FirstName','firstname','first_name'];
    $lastCols =['LastName','lastname','last_name'];
    $nameCols =['Name','name','username','UserName','DisplayName','displayname'];
    $uuidCols =['PrincipalID','UUID','UserID','id'];
    $emailCols=['Email','email'];
    $types=''; $vals=[]; $where='1';
    if($q!==''){
        $w1=build_like_where_ci($cols,array_merge($nameCols,$firstCols,$lastCols),$like_lower,$types,$vals);
        if($w1) $where="($w1)";
    } else {
        $where='0';
    }
    $sql="SELECT * FROM `$db`.`$table` WHERE $where LIMIT ?";
    $types.='i'; $vals[]=$limit;
    if(!$stmt=$mysqli->prepare($sql)){ $out['msg']='Failed to prepare People.'; return $out; }
    $stmt->bind_param($types,...$vals);
    if(!$stmt->execute()){ $out['msg']='People query failed.'; $stmt->close(); return $out; }
    $res=$stmt->get_result();
    while($row=$res->fetch_assoc()){
        $first=pick_first_col($row,$firstCols);
        $last =pick_first_col($row,$lastCols);
        $name =pick_first_col($row,$nameCols);
        $uuid =pick_first_col($row,$uuidCols);
        $email=pick_first_col($row,$emailCols);
        $display = trim(($first.' '.$last)) ?: ($name ?: $uuid);
        $out['rows'][]=[ 'display'=>$display, 'email'=>$email, 'uuid'=>$uuid ];
    }
    $stmt->close(); return $out;
}
function fetch_groups($mysqli,$db,$like_lower,$q,$limit){
    $out=['rows'=>[],'msg'=>null];
    $table=first_existing_table($mysqli,$db,['os_groups_groups','groups','groups_groups']);
    if(!$table){ $out['msg']='No groups table found.'; return $out; }
    $cols=list_columns($mysqli,$db,$table);
    $nameCols=['Name','name','GroupName'];
    $charterCols=['Charter','charter','Description','description'];
    $uuidCols=['GroupID','GroupIDLower','UUID','id'];
    $types=''; $vals=[]; $where='1';
    if($q!==''){
        $w1=build_like_where_ci($cols,$nameCols,$like_lower,$types,$vals);
        $types2=''; $vals2=[];
        $w2=build_like_where_ci($cols,$charterCols,$like_lower,$types2,$vals2);
        if($w1&&$w2){ $where="($w1 OR $w2)"; $types.=$types2; $vals=array_merge($vals,$vals2); }
        elseif($w1){ $where="($w1)"; }
        elseif($w2){ $where="($w2)"; $types=$types2; $vals=$vals2; }
    } else {
        $where='0';
    }
    $sql="SELECT * FROM `$db`.`$table` WHERE $where LIMIT ?";
    $types.='i'; $vals[]=$limit;
    if(!$stmt=$mysqli->prepare($sql)){ $out['msg']='Failed to prepare Groups.'; return $out; }
    $stmt->bind_param($types,...$vals);
    if(!$stmt->execute()){ $out['msg']='Groups query failed.'; $stmt->close(); return $out; }
    $res=$stmt->get_result();
    while($row=$res->fetch_assoc()){
        $name   = pick_first_col($row,$nameCols) ?: '(Group)';
        $charter= pick_first_col($row,$charterCols);
        $uuid   = pick_first_col($row,$uuidCols);
        $out['rows'][]=[ 'name'=>$name, 'desc'=>$charter, 'uuid'=>$uuid ];
    }
    $stmt->close(); return $out;
}

/* ===== Only fetch data if a query is present ===== */
$dest = $places = $land = $events = $clas = $people = $groups = ['rows'=>[], 'msg'=>null];

if($hasQuery){
    $dest = fetch_destinations($mysqli,$osmod,$like_lower,$q,$limit);
    $places= fetch_places($mysqli,$osmod,$like_lower,$q,$limit);
    $land = fetch_land($mysqli,$osmod,$like_lower,$q,$limit);
    $events= fetch_events($mysqli,$osmod,$like_lower,$q,$limit);
    $clas  = fetch_classifieds($mysqli,$osmod,$like_lower,$q,$limit);
    $people= fetch_people($mysqli,$osmain,$like_lower,$q,$limit);
    $groups= fetch_groups($mysqli,$osmain,$like_lower,$q,$limit);
}

$placeholder = '<div class="content-card shadow-sm p-3 p-md-4 mb-3">
  <div class="text-muted">Enter a search term above and press <strong>Search</strong>.</div>
</div>';

/* ===== Search + Tabs (Tabs BELOW the search input) ===== */
$self = h($_SERVER['PHP_SELF']); ?>
<div class="content-card shadow-sm p-3 p-md-4 my-3">
  <form method="get" action="<?php echo $self; ?>" class="row g-2" id="ossearch-form">
    <div class="col-12">
      <div class="input-group">
        <input type="text" class="form-control" name="q" placeholder="Search everything." value="<?php echo h($q); ?>" />
        <input type="hidden" name="tab" value="<?php echo h($tab); ?>" />
        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Search</button>
      </div>
    </div>

    <div class="col-12 d-flex flex-wrap gap-2 pt-1">
      <?php
        $tabs=[
          'all'=>'All',
          'classifieds'=>'Classifieds',
          'destinations'=>'Destinations',
          'events'=>'Events',
          'groups'=>'Groups',
          'land'=>'Land & Rentals',
          'people'=>'People',
          'places'=>'Places',
        ];
        if(!empty($IS_VIEWER)){
          unset($tabs['people'], $tabs['groups']);
        }
        foreach($tabs as $k=>$label){
          $active = ($tab===$k)?'active':'';
          echo '<a class="btn btn-outline-secondary '.$active.'" href="'.$self.'?tab='.h($k).'&amp;q='.urlencode($q).'">'.h($label).'</a>';
        }
      ?>
    </div>
  </form>
</div>

<?php

/* ===== Renderers ===== */
function section($title,$icon,$rows,$msg,$renderer){
    $rows = is_array($rows) ? $rows : []; // guard
    echo '<div class="content-card shadow-sm p-3 p-md-4 mb-3">';
    echo '<h2 class="h5 mb-3"><i class="bi '.$icon.' me-1"></i>'.h($title).'</h2>';
    if($msg) echo '<div class="alert alert-warning">'.h($msg).'</div>';
    if(empty($rows)){ echo '<div class="text-muted">No results.</div>'; }
    else{
        echo '<div class="list-group list-group-flush">';
        foreach($rows as $r){ echo '<div class="list-group-item px-0">'; $renderer($r); echo '</div>'; }
        echo '</div>';
    }
    echo '</div>';
}

$render_classified=function($r){
    echo '<div class="fw-semibold d-flex flex-wrap justify-content-between">';
    echo '<span>'.h($r['title']).'</span>';
    if(!empty($r['price'])) echo '<span class="badge text-bg-primary ms-2">'.h($r['price']).'</span>';
    echo '</div><div class="small text-muted">';
    if(!empty($r['place'])) echo '<i class="bi bi-geo"></i> '.h($r['place']).' ';
    if(!empty($r['cat']))   echo '<span class="ms-2"><i class="bi bi-tag"></i> '.h($r['cat']).'</span>';
    echo '</div>';
    if(!empty($r['desc'])) echo '<div class="small mt-1">'.nl2br(h($r['desc'])).'</div>';
};
$render_event=function($r){
    echo '<div class="fw-semibold">'.h($r['title']).'</div><div class="small text-muted">';
    if(!empty($r['place'])) echo '<i class="bi bi-geo"></i> '.h($r['place']).' ';
    if(!empty($r['when']))  echo '<span class="ms-2"><i class="bi bi-clock"></i> '.h($r['when']).'</span>';
    echo '</div>';
    if(!empty($r['desc'])) echo '<div class="small mt-1">'.nl2br(h($r['desc'])).'</div>';
};
$render_dest=function($r){
    echo '<div class="d-flex justify-content-between align-items-start"><div class="me-2">';
    echo '<div class="fw-semibold">'.h($r['name']).'</div>';
    if(!empty($r['region'])) echo '<div class="small text-muted"><i class="bi bi-map"></i> '.h($r['region']).'</div>';
    if(!empty($r['desc'])) echo '<div class="small mt-1">'.nl2br(h($r['desc'])).'</div>';
    echo '</div>';
    if(!empty($r['dwell'])) echo '<span class="badge text-bg-secondary align-self-start">Traffic: '.h($r['dwell']).'</span>';
    echo '</div>';
};
$render_place=function($r){
    echo '<div class="d-flex justify-content-between align-items-start"><div class="me-2">';
    echo '<div class="fw-semibold">'.h($r['name']).'</div>';
    if(!empty($r['region'])) echo '<div class="small text-muted"><i class="bi bi-map"></i> '.h($r['region']).'</div>';
    if(!empty($r['desc'])) echo '<div class="small mt-1">'.nl2br(h($r['desc'])).'</div>';
    echo '</div>';
    if(!empty($r['dwell'])) echo '<span class="badge text-bg-secondary align-self-start">Traffic: '.h($r['dwell']).'</span>';
    echo '</div>';
};
$render_land=function($r){
    echo '<div class="fw-semibold">'.h($r['name']).'</div><div class="small text-muted">';
    if(!empty($r['region'])) echo '<i class="bi bi-geo"></i> '.h($r['region']).' ';
    if(!empty($r['area']))   echo '<span class="ms-2"><i class="bi bi-aspect-ratio"></i> '.h($r['area']).'</span>';
    if(!empty($r['price']))  echo '<span class="ms-2"><i class="bi bi-cash"></i> '.h($r['price']).'</span>';
    echo '</div>';
    if(!empty($r['desc'])) echo '<div class="small mt-1">'.nl2br(h($r['desc'])).'</div>';
};
$render_person=function($r){
    echo '<div class="fw-semibold">'.h($r['display']).'</div>';
    if(!empty($r['email'])) echo '<div class="small text-muted"><i class="bi bi-envelope"></i> '.h($r['email']).'</div>';
    if(!empty($r['uuid']))  echo '<div class="small text-muted"><i class="bi bi-hash"></i> '.h($r['uuid']).'</div>';
};
$render_group=function($r){
    echo '<div class="fw-semibold">'.h($r['name']).'</div>';
    if(!empty($r['uuid']))  echo '<div class="small text-muted"><i class="bi bi-hash"></i> '.h($r['uuid']).'</div>';
    if(!empty($r['desc']))  echo '<div class="small mt-1">'.nl2br(h($r['desc'])).'</div>';
};

/* ===== Render content ===== */

/* ===== Viewer mode tweaks ===== */
if(!empty($IS_VIEWER) && ($tab==='people' || $tab==='groups')){
    $tab='all';
}

if(!$hasQuery){
    echo $placeholder;
} else if($tab==='all'){
    if(empty($IS_VIEWER)){
        section('Groups','bi-people-fill',$groups['rows'],$groups['msg'],$render_group);
        section('People','bi-people',$people['rows'],$people['msg'],$render_person);
    }
    section('Places','bi-geo',$places['rows'],$places['msg'],$render_place);
    section('Destinations','bi-geo-alt',$dest['rows'],$dest['msg'],$render_dest);
    section('Land & Rentals','bi-house',$land['rows'],$land['msg'],$render_land);
    section('Events','bi-calendar3',$events['rows'],$events['msg'],$render_event);
    section('Classifieds','bi-megaphone',$clas['rows'],$clas['msg'],$render_classified);
} else {
    // SINGLE CARD ONLY
    $map=[
        'groups'       => ['Groups','bi-people-fill',$groups,$render_group],
        'people'       => ['People','bi-people',$people,$render_person],
        'places'       => ['Places','bi-geo',$places,$render_place],
        'destinations' => ['Destinations','bi-geo-alt',$dest,$render_dest],
        'land'         => ['Land & Rentals','bi-house',$land,$render_land],
        'events'       => ['Events','bi-calendar3',$events,$render_event],
        'classifieds'  => ['Classifieds','bi-megaphone',$clas,$render_classified],
    ];
    if(isset($map[$tab])){
        [$t,$i,$data,$renderer] = $map[$tab];
        section($t,$i,$data['rows'],$data['msg'],$renderer);
    } else {
        echo '<div class="content-card shadow-sm p-3 p-md-4 mb-3"><div class="text-muted">Unknown tab.</div></div>';
    }
}

/* ===== Footer ===== */
$mysqli->close();
include_once 'include/footer.php';
?>