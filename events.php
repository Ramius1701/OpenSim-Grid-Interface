<?php
// events.php — Grid Event Calendar (Hybrid Modern: Stacked Layout + Theme Engine)
require_once __DIR__ . '/include/config.php';

$title = defined('CALENDAR_TITLE') ? CALENDAR_TITLE : 'Event Calendar';

// 1. Data Loading
function load_json_candidates(array $paths){
    foreach ($paths as $p){
        if (is_readable($p)){
            $raw = file_get_contents($p);
            if ($raw !== false){
                $data = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($data)) return $data;
            }
        }
    }
    return [];
}

$eventsData = load_json_candidates([PATH_EVENTS_JSON]);
$announcementsData = load_json_candidates([PATH_ANNOUNCEMENTS_JSON]);

// 2. Filters
$show = $_GET['show'] ?? 'all';
$show = in_array($show, ['all', 'holidays', 'announcements', 'events'], true) ? $show : 'all';
$gridTz = defined('GRID_TIMEZONE') ? GRID_TIMEZONE : date_default_timezone_get();

// 3. Database (Viewer Events)
$viewerEventsData = [];
if (function_exists('db')) {
    $conn = db();
    if ($conn) {
        $sql = "SELECT eventid AS EventID, owneruuid, creatoruuid AS CreatorUUID, name AS Name, category AS Category, description AS Description, dateUTC AS DateUTC, duration AS Duration, simname AS SimName, parcelUUID AS ParcelUUID, globalPos AS GlobalPos, covercharge, coveramount, eventflags AS EventFlags FROM search_events ORDER BY dateUTC ASC LIMIT 500";
        $res = @mysqli_query($conn, $sql);
        if (!$res) $res = @mysqli_query($conn, "SELECT * FROM search_events LIMIT 500");
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) $viewerEventsData[] = $row;
            mysqli_free_result($res);
        }
    }
}

// Apply Logic
if (!($show === 'all' || $show === 'holidays')) $eventsData = [];
if (!($show === 'all' || $show === 'announcements')) $announcementsData = [];
if (!($show === 'all' || $show === 'events')) $viewerEventsData = [];

$selfPath = $_SERVER['PHP_SELF'] ?? 'events.php';
$filterLinks = [
    'all' => "$selfPath?show=all", 'holidays' => "$selfPath?show=holidays",
    'announcements' => "$selfPath?show=announcements", 'events' => "$selfPath?show=events"
];

include_once __DIR__ . '/include/header.php';
?>

<style>
    /* --- CALENDAR THEME VARIABLES --- */
    :root {
        /* Inherit Global Theme */
        --cal-bg: var(--card-bg);
        --cal-text: var(--primary-color);
        --cal-accent: var(--accent-color);
        
        /* Auto-shades */
        --cal-border: color-mix(in srgb, var(--primary-color), transparent 85%);
        --cal-header-bg: color-mix(in srgb, var(--card-bg), var(--primary-color) 4%);
        --cal-hover: color-mix(in srgb, var(--primary-color), transparent 96%);
        --cal-today-bg: color-mix(in srgb, var(--accent-color), transparent 92%);
        --cal-muted: color-mix(in srgb, var(--primary-color), transparent 40%);
        --cal-pill: color-mix(in srgb, var(--card-bg), var(--primary-color) 8%);
    }

    /* --- 1. HERO SECTION --- */
    .events-hero {
        background: linear-gradient(135deg, 
            color-mix(in srgb, var(--header-color), black 30%), 
            color-mix(in srgb, var(--header-color), black 60%)
        );
        border-radius: 15px;
        padding: 4rem 2rem;
        margin-bottom: 1.5rem;
        text-align: center;
        color: white;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }
    .events-hero h1 { font-size: 3rem; font-weight: 800; margin-bottom: 0.5rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.3); }
    .events-hero p { color: rgba(255,255,255,0.9); max-width: 700px; margin: 0 auto; font-size: 1.2rem; }

    /* --- 2. CONTROL BAR --- */
    .control-card {
        background: var(--card-bg);
        border: 1px solid var(--cal-border);
        border-radius: 16px;
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1.5rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    
    .filter-group { display: flex; gap: 0.5rem; flex-wrap: wrap; }
    .nav-group { display: flex; gap: 0.5rem; align-items: center; }
    .cal-title { font-size: 1.5rem; font-weight: 800; color: var(--cal-text); margin: 0 1.5rem; min-width: 200px; text-align: center; }

    /* Buttons */
    .cal-btn {
        background: transparent;
        border: 1px solid var(--cal-border);
        color: var(--cal-text);
        padding: 0.5rem 1.25rem;
        border-radius: 50px;
        font-weight: 600;
        transition: all 0.2s;
        text-decoration: none;
        display: inline-block;
    }
    .cal-btn:hover { background: var(--cal-hover); border-color: var(--cal-accent); color: var(--cal-accent); }
    .cal-btn.active { background: var(--cal-accent); border-color: var(--cal-accent); color: white; }

    /* --- 3. CALENDAR GRID --- */
    .cal-container {
        background: var(--card-bg);
        border-radius: 16px;
        padding: 2rem;
        border: 1px solid var(--cal-border);
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }

    .cal-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr); /* Forces equal width columns */
        border: 1px solid var(--cal-border);
        border-radius: 12px;
        overflow: hidden;
        background: var(--cal-bg);
    }
    
    .cal-dow {
        background: var(--cal-header-bg);
        padding: 1rem;
        text-align: center;
        font-weight: 700;
        color: var(--cal-muted);
        text-transform: uppercase;
        font-size: 0.85rem;
        border-bottom: 1px solid var(--cal-border);
    }
    
    .cal-cell {
        height: 150px; /* STRICT FIXED HEIGHT */
        background: var(--cal-bg);
        border-right: 1px solid var(--cal-border);
        border-bottom: 1px solid var(--cal-border);
        padding: 0.75rem;
        cursor: pointer;
        transition: background 0.1s;
        position: relative;
        overflow: hidden; /* Hides anything that spills out */
        display: flex;
        flex-direction: column;
    }
    .cal-cell:hover { background-color: var(--cal-hover); }
    .cal-cell:nth-child(7n) { border-right: none; }
    
    .cal-day {
        font-weight: 700; font-size: 1.1rem;
        color: var(--cal-text); opacity: 0.6;
        margin-bottom: 0.5rem;
        flex-shrink: 0; /* Prevents date from being squashed */
    }
    .cal-outmonth { background: color-mix(in srgb, var(--cal-bg), black 2%); }
    .cal-today-cell { background: var(--cal-today-bg); }
    .cal-today-cell .cal-day { color: var(--cal-accent); opacity: 1; }

    /* Events */
    .cal-events { 
        display: flex; flex-direction: column; gap: 4px; 
        flex-grow: 1; /* Fills remaining space */
        min-width: 0; /* Allows text truncation to work inside flex items */
    }
    .cal-event {
        font-size: 0.75rem; padding: 4px 8px; border-radius: 4px;
        background: color-mix(in srgb, var(--cal-bg), var(--primary-color) 6%);
        display: flex; align-items: center; gap: 6px;
        
        /* STRICT TRUNCATION RULES */
        white-space: nowrap; 
        overflow: hidden; 
        text-overflow: ellipsis;
        max-width: 100%;
        
        transition: transform 0.1s;
    }
    .cal-event:hover { transform: translateX(2px); background: color-mix(in srgb, var(--cal-bg), var(--primary-color) 10%); }
    .dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }

    /* Legend */
    .cal-legend {
        display: flex; gap: 1.5rem; flex-wrap: wrap; margin-top: 2rem; justify-content: center;
    }
    .legend-item { 
        display: flex; align-items: center; gap: 0.5rem; 
        font-size: 0.9rem; color: var(--cal-muted);
        background: var(--cal-header-bg);
        padding: 0.5rem 1rem;
        border-radius: 50px;
        border: 1px solid var(--cal-border);
    }

    /* Modal */
    .cal-modal-backdrop {
        display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7);
        backdrop-filter: blur(5px); z-index: 10000; align-items: center; justify-content: center; padding: 1rem;
    }
    .cal-modal {
        width: min(600px, 100%); background: var(--card-bg); border-radius: 16px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); border: 1px solid var(--cal-border); overflow: hidden;
    }
    .cal-modal header {
        background: linear-gradient(135deg, color-mix(in srgb, var(--header-color), black 10%), color-mix(in srgb, var(--header-color), black 40%));
        padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; color: white;
    }
    .cal-modal h3 { margin: 0; font-size: 1.5rem; font-weight: 700; color: white !important; }
    .cal-modal .close { background: rgba(255,255,255,0.2); border: none; color: white; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; }
    .cal-modal .content { padding: 2rem; max-height: 60vh; overflow-y: auto; }
    
    .item { background: var(--cal-header-bg); border-radius: 12px; padding: 1.25rem; margin-bottom: 1rem; border: 1px solid var(--cal-border); }
    .item-title { font-weight: 700; font-size: 1.1rem; color: var(--cal-accent); display: block; margin-bottom: 0.25rem; }

    @media (max-width: 992px) {
        .control-card { flex-direction: column; align-items: stretch; text-align: center; }
        .filter-group, .nav-group { justify-content: center; }
        .cal-title { margin: 1rem 0; }
        .cal-cell { height: 100px; }
    }
</style>

<section class="events-hero">
  <h1><i class="bi bi-calendar2-week me-2"></i> Event Calendar</h1>
  <p>Track holidays, community announcements, and in-world events in Grid Time.</p>
</section>

<div class="container-fluid mb-5">
    
    <div class="control-card">
        <div class="filter-group">
            <a href="<?= $filterLinks['all'] ?>" class="cal-btn <?= $show === 'all' ? 'active' : '' ?>">All</a>
            <a href="<?= $filterLinks['events'] ?>" class="cal-btn <?= $show === 'events' ? 'active' : '' ?>">Grid Events</a>
            <a href="<?= $filterLinks['holidays'] ?>" class="cal-btn <?= $show === 'holidays' ? 'active' : '' ?>">Holidays</a>
            <a href="<?= $filterLinks['announcements'] ?>" class="cal-btn <?= $show === 'announcements' ? 'active' : '' ?>">Notices</a>
        </div>

        <div class="nav-group">
            <button class="cal-btn" id="prevBtn"><i class="bi bi-chevron-left"></i></button>
            <h2 class="cal-title" id="calTitle">Loading...</h2>
            <button class="cal-btn" id="nextBtn"><i class="bi bi-chevron-right"></i></button>
            <button class="cal-btn" id="todayBtn">Today</button>
        </div>
    </div>

    <div class="cal-container shadow-sm">
        <div class="cal-grid">
            <div class="cal-dow">Sun</div><div class="cal-dow">Mon</div><div class="cal-dow">Tue</div>
            <div class="cal-dow">Wed</div><div class="cal-dow">Thu</div><div class="cal-dow">Fri</div><div class="cal-dow">Sat</div>
            </div>

        <div class="cal-legend" id="calLegend"></div>
    </div>

</div>

<div class="cal-modal-backdrop" id="detailModal">
    <div class="cal-modal">
        <header>
            <h3 id="detailTitle">Details</h3>
            <button class="close" id="detailClose"><i class="bi bi-x-lg"></i></button>
        </header>
        <div class="content" id="detailContent"></div>
    </div>
</div>

<script>
(function(){
    const RAW_EVENTS = <?php echo json_encode($eventsData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const RAW_ANN   = <?php echo json_encode($announcementsData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const RAW_VIEWER = <?php echo json_encode($viewerEventsData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const GRID_TZ    = <?php echo json_encode($gridTz, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    const TYPE_LABELS = {
        holiday: 'Holiday', religious: 'Religious', awareness: 'Awareness',
        fandom: 'Fandom', fun: 'Fun', math_science: 'Sci/Tech',
        remembrance: 'Remembrance', announcement: 'News', event: 'Event'
    };

    const grid=document.querySelector('.cal-grid'), titleEl=document.getElementById('calTitle'),
          legendEl=document.getElementById('calLegend'), prevBtn=document.getElementById('prevBtn'),
          nextBtn=document.getElementById('nextBtn'), todayBtn=document.getElementById('todayBtn'),
          modal=document.getElementById('detailModal'), modalClose=document.getElementById('detailClose'),
          modalContent=document.getElementById('detailContent');

    const today=new Date(); today.setHours(0,0,0,0);
    let view=new Date(today.getTime()); view.setDate(1);
    let currentByDate=new Map();

    const pad=n=>String(n).padStart(2,'0'), ymd=d=>`${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
    const gridDateFmt=new Intl.DateTimeFormat('en-CA',{timeZone:GRID_TZ,year:'numeric',month:'2-digit',day:'2-digit'});
    const gridTimeFmt=new Intl.DateTimeFormat('en-CA',{timeZone:GRID_TZ,hour:'2-digit',minute:'2-digit'});

    function gridDateObjFromTs(ts){
        const parts=gridDateFmt.formatToParts(new Date(ts*1000));
        const get=t=>(parts.find(p=>p.type===t)||{}).value;
        const d=new Date(parseInt(get('year')), parseInt(get('month'))-1, parseInt(get('day')));
        d.setHours(0,0,0,0); return d;
    }

    function parseYMD(s){
        if(!s)return null; const p=s.split('-'); 
        const d=new Date(parseInt(p[0]),parseInt(p[1])-1,parseInt(p[2]));
        d.setHours(0,0,0,0); return d;
    }

    function addDays(d,n){const x=new Date(d);x.setDate(x.getDate()+n);return x;}
    function calcEaster(y){
        const a=y%19,b=Math.floor(y/100),c=y%100,d=Math.floor(b/4),e=b%4,f=Math.floor((b+8)/25),g=Math.floor((b-f+1)/3),h=(19*a+b-d-g+15)%30,i=Math.floor(c/4),k=c%4,l=(32+2*e+2*i-h-k)%7,m=Math.floor((a+11*h+22*l)/451),mo=Math.floor((h+l-7*m+114)/31),da=((h+l-7*m+114)%31)+1;
        const dt=new Date(y,mo-1,da); dt.setHours(0,0,0,0); return dt;
    }
    function dateFromRule(r,y){
        const e=calcEaster(y);
        if(r==='easter_sunday')return e;
        if(r==='good_friday')return addDays(e,-2);
        if(r==='easter_monday')return addDays(e,1);
        return null;
    }

    function normRecurring(e){
        return { kind:'event', type:e.type||'event', rule:e.rule||'', title:e.title||'Event', desc:e.description||'', color:e.color, month:parseInt((e.date||'').split('-')[1]), day:parseInt((e.date||'').split('-')[2]) };
    }
    function normAnn(a){
        return { kind:'announcement', type:a.type, title:a.title, desc:a.message, color:a.color, startDate:parseYMD(a.start), endDate:parseYMD(a.end||a.start) };
    }
    function normViewer(v){
        const ts=parseInt(v.DateUTC||0); if(!ts)return null;
        return { kind:'event', type:'event', title:v.Name, desc:v.Description, ts, dateObj:gridDateObjFromTs(ts), time: gridTimeFmt.format(new Date(ts*1000)) };
    }

    const recEvents=Array.isArray(RAW_EVENTS)?RAW_EVENTS.map(normRecurring):[];
    const annData=Array.isArray(RAW_ANN)?RAW_ANN.map(normAnn):[];
    const viewData=Array.isArray(RAW_VIEWER)?RAW_VIEWER.map(normViewer).filter(Boolean):[];

    function buildData(){
        const y=view.getFullYear(), m=view.getMonth();
        const map=new Map();

        recEvents.forEach(e=>{
            let d=null;
            if(e.rule) d=dateFromRule(e.rule,y);
            else if(e.month && e.day) d=new Date(y,e.month-1,e.day);
            if(d && d.getMonth()===m){
                const k=ymd(d); if(!map.has(k))map.set(k,[]);
                map.get(k).push({...e, dateObj:d});
            }
        });

        annData.forEach(a=>{
            if(!a.startDate)return;
            let cur=new Date(a.startDate); const end=new Date(a.endDate);
            while(cur<=end){
                if(cur.getMonth()===m && cur.getFullYear()===y){
                    const k=ymd(cur); if(!map.has(k))map.set(k,[]);
                    map.get(k).push({...a, dateObj:new Date(cur)});
                }
                cur.setDate(cur.getDate()+1);
            }
        });

        viewData.forEach(v=>{
            if(v.dateObj.getMonth()===m && v.dateObj.getFullYear()===y){
                const k=ymd(v.dateObj); if(!map.has(k))map.set(k,[]);
                map.get(k).push(v);
            }
        });

        return map;
    }

    function render(){
        currentByDate=buildData();
        titleEl.textContent = new Intl.DateTimeFormat('en-NZ',{month:'long',year:'numeric'}).format(view);
        grid.querySelectorAll('.cal-cell').forEach(e=>e.remove());

        const y=view.getFullYear(), m=view.getMonth();
        const first=new Date(y,m,1).getDay(), days=new Date(y,m+1,0).getDate();
        
        const prevMonthDays = new Date(y, m, 0).getDate();
        let dayNumPrev = prevMonthDays - first + 1;
        for(let i=0;i<first;i++) grid.appendChild(mkCell(new Date(y,m-1,dayNumPrev++), true));
        
        for(let d=1;d<=days;d++) grid.appendChild(mkCell(new Date(y,m,d), false));
        
        const used=first+days; const rem=(7-(used%7))%7;
        for(let i=1;i<=rem;i++) grid.appendChild(mkCell(new Date(y,m+1,i), true));

        renderLegend();
    }

    function mkCell(d, isOut){
        const cell=document.createElement('div');
        cell.className='cal-cell'+(isOut?' cal-outmonth':'');
        if(!isOut && d.getTime()===today.getTime()) cell.classList.add('cal-today-cell');
        
        cell.innerHTML=`<div class="cal-day">${d.getDate()}</div><div class="cal-events"></div>`;
        const wrap=cell.querySelector('.cal-events');
        const evs=currentByDate.get(ymd(d))||[];
        
        evs.slice(0,3).forEach(e=>{
            const div=document.createElement('div');
            div.className='cal-event';
            div.innerHTML=`<span class="dot" style="background:${e.color||'var(--cal-accent)'}"></span><span>${e.title}</span>`;
            wrap.appendChild(div);
        });
        if(evs.length>3){
            const more=document.createElement('div');
            more.className='cal-event text-muted';
            more.innerText=`+${evs.length-3} more`;
            wrap.appendChild(more);
        }

        cell.onclick=()=>openModal(d, evs);
        return cell;
    }

    function renderLegend(){
        legendEl.innerHTML='';
        const seen=new Set();
        currentByDate.forEach(list=>{
            list.forEach(e=>{
                const t=e.type||'event';
                if(!seen.has(t)){
                    seen.add(t);
                    const div=document.createElement('div');
                    div.className='legend-item';
                    div.innerHTML=`<span class="dot" style="background:${e.color||'var(--cal-accent)'}"></span>${TYPE_LABELS[t]||t}`;
                    legendEl.appendChild(div);
                }
            });
        });
    }

    function openModal(d, items){
        document.getElementById('detailTitle').innerText = d.toLocaleDateString('en-NZ',{weekday:'long', month:'long', day:'numeric'});
        if(!items.length){
            modalContent.innerHTML='<div class="text-center p-5 opacity-50"><i class="bi bi-calendar-x fs-1"></i><p>No events today.</p></div>';
        } else {
            modalContent.innerHTML = items.map(e=>`
                <div class="item">
                    <span class="item-title">${e.title} <span class="badge bg-secondary" style="font-size:0.65rem; vertical-align:middle;">${TYPE_LABELS[e.type]||'Event'}</span></span>
                    <div class="d-flex gap-3 small opacity-75 mb-2">
                        ${e.time ? `<span><i class="bi bi-clock"></i> ${e.time}</span>` : ''}
                        <span>${e.kind==='announcement'?'Announcement':'Event'}</span>
                    </div>
                    ${e.desc ? `<div class="item-desc">${e.desc}</div>` : ''}
                </div>
            `).join('');
        }
        modal.style.display='flex';
    }

    modalClose.onclick=()=>modal.style.display='none';
    prevBtn.onclick=()=>{view.setMonth(view.getMonth()-1); render();};
    nextBtn.onclick=()=>{view.setMonth(view.getMonth()+1); render();};
    todayBtn.onclick=()=>{view=new Date(today.getTime()); view.setDate(1); render();};
    
    render();
})();
</script>

<?php include_once __DIR__ . "/include/" . FOOTER_FILE; ?>