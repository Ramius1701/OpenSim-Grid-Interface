<?php
// events.php — Grid Event Calendar (modern layout)
// Uses config.php + header.php/footer.php from the site root
// Loads events + announcements JSON on the server side and passes them into JS.

require_once __DIR__ . '/include/config.php';

$title = defined('CALENDAR_TITLE') ? CALENDAR_TITLE : 'Event Calendar';

// Try to load JSON files from a few common locations relative to this file.
function load_json_candidates(array $paths){
    foreach ($paths as $p){
        if (is_readable($p)){
            $raw = file_get_contents($p);
            if ($raw === false) continue;
            $data = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)){
                return $data;
            }
        }
    }
    return [];
}

$eventsData = load_json_candidates([
    PATH_EVENTS_JSON
]);

$announcementsData = load_json_candidates([
    PATH_ANNOUNCEMENTS_JSON
]);

include_once __DIR__ . '/include/header.php';
?>

<div class="content-card">
  <h1 class="mb-3"><i class="bi bi-calendar3"></i> <?php echo htmlspecialchars($title, ENT_QUOTES); ?></h1>
  <p class="text-muted mb-3">
    A combined view of recurring holidays, fandom days, and grid announcements.
    Click on any day to see its details.
  </p>

  <style>
    :root{
      --cal-border:#d1d5db;
      --cal-bg:#ffffff;
      --cal-text:#111827;
      --cal-muted:#6b7280;
      --cal-accent:#0f62fe;
      --cal-pill:#f3f4f6;
    }

    .cal-container{
      margin-top:1rem;
    }

    .cal-header{display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-bottom:1rem;flex-wrap:wrap;}
    .cal-title{font-size:1.25rem;font-weight:600;margin:0;}
    .cal-nav{display:flex;gap:.5rem;flex-wrap:wrap;}
    .cal-btn{
      border:1px solid var(--cal-border);
      background:var(--cal-bg);
      padding:.4rem .6rem;
      border-radius:.5rem;
      cursor:pointer;
      font-size:.9rem;
      color:var(--cal-text);
    }
    .cal-btn:hover{background:#f9fafb;}
    .cal-today{border-color:var(--cal-accent);color:var(--cal-accent);}

    .cal-grid{
      display:grid;
      grid-template-columns:repeat(7,1fr);
      border:1px solid var(--cal-border);
      border-radius:.75rem;
      overflow:hidden;
      background:var(--cal-bg);
    }
    .cal-dow{
      background:#f9fafb;
      padding:.5rem .6rem;
      font-size:.85rem;
      color:var(--cal-muted);
      border-bottom:1px solid var(--cal-border);
      text-align:left;
    }
    .cal-cell{
      height:110px;
      position:relative;
      overflow:hidden;
      background:var(--cal-bg);
      border-right:1px solid var(--cal-border);
      border-bottom:1px solid var(--cal-border);
      padding:.45rem .5rem .35rem;
      display:flex;
      flex-direction:column;
      gap:.3rem;
      cursor:pointer;
    }
    .cal-cell:nth-child(7n){border-right:none;}
    .cal-cell::after{
      content:"";
      position:absolute;
      left:0;right:0;bottom:0;height:1.3rem;
      background:linear-gradient(to bottom,rgba(255,255,255,0),var(--cal-bg));
      pointer-events:none;
    }
    .cal-day{font-size:.9rem;font-weight:600;color:var(--cal-text);}
    .cal-outmonth .cal-day{opacity:.35;}

    .cal-today-cell{
      background:#eff6ff;
      box-shadow:inset 0 0 0 2px var(--cal-accent);
    }
    .cal-today-cell .cal-day{
      color:var(--cal-accent);
    }

    .cal-events{display:flex;flex-direction:column;gap:.25rem;margin-top:.1rem;}
    .cal-event{display:flex;align-items:center;gap:.4rem;font-size:.8rem;line-height:1.15;}
    .dot{
      width:.55rem;height:.55rem;
      border-radius:999px;
      flex:0 0 auto;
      border:1px solid rgba(0,0,0,.08);
    }
    .ev-time{color:var(--cal-muted);font-size:.78rem;flex:0 0 auto;}
    .ev-title{flex:1 1 auto;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--cal-text);}
    .ev-more{font-size:.78rem;color:var(--cal-accent);flex:0 0 auto;}

    .cal-legend{
      display:flex;
      gap:.5rem;
      flex-wrap:wrap;
      margin-top:.75rem;
      color:var(--cal-muted);
      font-size:.85rem;
    }
    .legend-item{
      display:flex;
      align-items:center;
      gap:.35rem;
      padding:.25rem .5rem;
      border-radius:.5rem;
      background:var(--cal-pill);
    }
    .legend-count{opacity:.75;}

    .cal-modal-backdrop{
      display:none;
      position:fixed;
      inset:0;
      background:rgba(15,23,42,.55);
      z-index:9999;
      align-items:center;
      justify-content:center;
      padding:1rem;
    }
    .cal-modal{
      width:min(720px,95vw);
      max-height:85vh;
      overflow:auto;
      background:var(--cal-bg);
      color:var(--cal-text);
      border-radius:.75rem;
      box-shadow:0 10px 30px rgba(0,0,0,.25);
      border:1px solid var(--cal-border);
    }
    .cal-modal header{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:.5rem;
      padding:1rem 1.1rem;
      border-bottom:1px solid var(--cal-border);
      background:#f9fafb;
    }
    .cal-modal h3{margin:0;font-size:1.1rem;color:var(--cal-text);}
    .cal-modal .close{
      background:var(--cal-bg);
      border:1px solid var(--cal-border);
      border-radius:.5rem;
      padding:.35rem .6rem;
      cursor:pointer;
      font-size:.85rem;
      color:var(--cal-text);
    }
    .cal-modal .close:hover{background:#f3f4f6;}
    .cal-modal .content{
      padding:1rem 1.1rem;
      display:flex;
      flex-direction:column;
      gap:.75rem;
      background:var(--cal-bg);
    }
    .item{
      border-radius:.6rem;
      border:1px solid var(--cal-border);
      padding:.6rem .7rem;
      background:#f9fafb;
      color:var(--cal-text);
    }
    .item-title{font-weight:600;}
    .item-badge{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:.1rem .45rem;
      margin-left:.25rem;
      border-radius:999px;
      background:var(--cal-pill);
      font-size:.7rem;
      color:var(--cal-muted);
    }
    .item-time{font-size:.8rem;color:var(--cal-muted);display:block;margin-top:.1rem;}
    .item-desc{font-size:.85rem;margin-top:.25rem;color:var(--cal-text);}
    .item-link a{font-size:.8rem;color:var(--cal-accent);text-decoration:none;}
    .item-link a:hover{text-decoration:underline;}
    .item-img{margin-top:.4rem;}
    .item-img img{max-width:100%;border-radius:.5rem;display:block;}

    @media (max-width:640px){
      .cal-cell{height:90px;}
      .cal-title{font-size:1.1rem;}
      .cal-header{align-items:flex-start;}
    }
  </style>

  <div class="cal-container">
    <div class="cal-header">
      <h2 class="cal-title" id="calTitle"><?php echo htmlspecialchars($title, ENT_QUOTES); ?></h2>
      <div class="cal-nav">
        <button class="cal-btn" id="prevBtn" type="button" aria-label="Previous month">&#8249; Prev</button>
        <button class="cal-btn cal-today" id="todayBtn" type="button" aria-label="Jump to current month">Today</button>
        <button class="cal-btn" id="nextBtn" type="button" aria-label="Next month">Next &#8250;</button>
      </div>
    </div>

    <div class="cal-grid" role="grid" aria-labelledby="calTitle">
      <div class="cal-dow" role="columnheader">Sun</div>
      <div class="cal-dow" role="columnheader">Mon</div>
      <div class="cal-dow" role="columnheader">Tue</div>
      <div class="cal-dow" role="columnheader">Wed</div>
      <div class="cal-dow" role="columnheader">Thu</div>
      <div class="cal-dow" role="columnheader">Fri</div>
      <div class="cal-dow" role="columnheader">Sat</div>
      <!-- cells injected by JS -->
    </div>

    <div class="cal-legend" id="calLegend" aria-hidden="true"></div>
  </div>
</div>

<div class="cal-modal-backdrop" id="detailModal" role="dialog" aria-modal="true" aria-labelledby="detailTitle">
  <div class="cal-modal">
    <header>
      <h3 id="detailTitle">Day details</h3>
      <button class="close" id="detailClose" type="button">Close</button>
    </header>
    <div class="content" id="detailContent"></div>
  </div>
</div>

<script>
(function(){
  // Raw data injected by PHP from JSON files
  const RAW_EVENTS = <?php echo json_encode($eventsData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  const RAW_ANN   = <?php echo json_encode($announcementsData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

  const TYPE_LABELS = {
    holiday:      'Holiday',
    religious:    'Religious holiday',
    awareness:    'Awareness / UN day',
    fandom:       'Pop-culture / fandom',
    fun:          'Fun / novelty',
    math_science: 'Math / science / tech',
    remembrance:  'Remembrance',
    announcement: 'Announcement',
    event:        'Event'
  };

  const grid        = document.querySelector('.cal-grid');
  const titleEl     = document.getElementById('calTitle');
  const legendEl    = document.getElementById('calLegend');
  const prevBtn     = document.getElementById('prevBtn');
  const nextBtn     = document.getElementById('nextBtn');
  const todayBtn    = document.getElementById('todayBtn');
  const modal       = document.getElementById('detailModal');
  const modalClose  = document.getElementById('detailClose');
  const modalContent= document.getElementById('detailContent');

  const today = new Date();
  today.setHours(0,0,0,0);

  let view = new Date(today.getTime());
  view.setDate(1);

  let currentByDate = new Map();

  const pad = n => String(n).padStart(2,'0');
  const ymd = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;

  function parseYMD(s){
    if (!s) return null;
    const parts = s.split('-');
    if (parts.length !== 3) return null;
    const y = parseInt(parts[0], 10);
    const m = parseInt(parts[1], 10);
    const da= parseInt(parts[2], 10);
    if (!y || !m || !da) return null;
    const d = new Date(y, m-1, da);
    d.setHours(0,0,0,0);
    return d;
  }

  // Moving holiday helpers (used only if a rule is present in the JSON)
  function calcEasterSunday(year){
    const a = year % 19;
    const b = Math.floor(year / 100);
    const c = year % 100;
    const d = Math.floor(b / 4);
    const e = b % 4;
    const f = Math.floor((b + 8) / 25);
    const g = Math.floor((b - f + 1) / 3);
    const h = (19 * a + b - d - g + 15) % 30;
    const i = Math.floor(c / 4);
    const k = c % 4;
    const l = (32 + 2*e + 2*i - h - k) % 7;
    const m2 = Math.floor((a + 11*h + 22*l) / 451);
    const month = Math.floor((h + l - 7*m2 + 114) / 31);
    const day   = ((h + l - 7*m2 + 114) % 31) + 1;
    const dte = new Date(year, month-1, day);
    dte.setHours(0,0,0,0);
    return dte;
  }

  function addDays(base, days){
    const d = new Date(base.getTime());
    d.setDate(d.getDate() + days);
    d.setHours(0,0,0,0);
    return d;
  }

  function nthWeekdayOfMonth(year, monthIndex, weekday, n){
    const first = new Date(year, monthIndex, 1);
    const shift = (weekday - first.getDay() + 7) % 7;
    const day   = 1 + shift + 7*(n-1);
    const d = new Date(year, monthIndex, day);
    d.setHours(0,0,0,0);
    return d;
  }

  function dayOfYear(year, dayIndex){
    const d = new Date(year, 0, 1);
    d.setDate(d.getDate() + dayIndex - 1);
    d.setHours(0,0,0,0);
    return d;
  }

  function lastWeekdayOfMonth(year, monthIndex, weekday){
    const last = new Date(year, monthIndex + 1, 0);
    const diff = (last.getDay() - weekday + 7) % 7;
    last.setDate(last.getDate() - diff);
    last.setHours(0,0,0,0);
    return last;
  }

  function dateFromRule(rule, year){
    if (!rule) return null;
    const easter = calcEasterSunday(year);
    switch (rule){
      case 'easter_sunday': return easter;
      case 'good_friday': return addDays(easter, -2);
      case 'easter_monday': return addDays(easter, 1);
      case 'pentecost_sunday': return addDays(easter, 49);
      case 'pentecost_monday': return addDays(easter, 50);
      case 'national_donut_day': return nthWeekdayOfMonth(year, 5, 5, 1); // June, 1st Friday
      case 'labour_day_sept_first_monday': return nthWeekdayOfMonth(year, 8, 1, 1); // Sept, 1st Monday
      case 'us_thanksgiving': return nthWeekdayOfMonth(year, 10, 4, 4); // Nov, 4th Thursday
      case 'programmers_day_256': return dayOfYear(year, 256);
      case 'sysadmin_day_last_fri_july': return lastWeekdayOfMonth(year, 6, 5); // July, last Friday
      default: return null;
    }
  }

  function normRecurring(ev){
    const texts = Array.isArray(ev.texts) ? ev.texts : [];
    const title = ev.title || texts[0] || 'Event';
    const desc  = ev.description || texts[2] || texts[1] || '';
    const parts = (ev.date || '').split('-');
    const month = parseInt(parts[1] || '0', 10);
    const day   = parseInt(parts[2] || '0', 10);

    return {
      kind:'event',
      type: ev.type || 'event',
      rule: ev.rule || '',
      priority: ev.priority || 0,
      title,
      desc,
      link: ev.link || '',
      image: ev.image || '',
      color: ev.color || '',
      txtcolor: ev.txtcolor || '',
      month,
      day,
      time: ev.time || '',
      raw: ev
    };
  }

  function normAnnouncement(a){
    const start = parseYMD(a.start || '');
    const end   = parseYMD(a.end || a.start || '');
    return {
      kind:'announcement',
      type:a.type || 'announcement',
      priority:typeof a.priority === 'number' ? a.priority : 0,
      title:a.title || 'Announcement',
      desc:a.message || a.description || '',
      link:a.link || '',
      image:'',
      color:a.color || '#0f766e',
      txtcolor:'',
      startDate:start,
      endDate:end,
      startTime:a.start_time || '',
      endTime:a.end_time || '',
      raw:a
    };
  }

  let recurring = Array.isArray(RAW_EVENTS) ? RAW_EVENTS.map(normRecurring) : [];
  let announcements = Array.isArray(RAW_ANN) ? RAW_ANN.map(normAnnouncement) : [];

  function buildByDate(){
    const y = view.getFullYear();
    const m = view.getMonth();
    const byDate = new Map();

    for (const ev of recurring){
      let dObj = null;
      if (ev.rule){
        dObj = dateFromRule(ev.rule, y);
      }
      if (!dObj && ev.month && ev.day){
        dObj = new Date(y, ev.month - 1, ev.day);
      }
      if (!dObj) continue;

      dObj.setHours(0,0,0,0);
      if (dObj.getMonth() !== m) continue;

      const key = ymd(dObj);
      const list = byDate.get(key) || [];
      list.push({
        ...ev,
        dateObj: dObj,
        dateStr: key
      });
      byDate.set(key, list);
    }

    for (const an of announcements){
      if (!an.startDate) continue;
      const start = an.startDate;
      const end   = an.endDate || an.startDate;
      let d = new Date(start.getTime());
      d.setHours(0,0,0,0);
      const endTime = new Date(end.getTime());
      endTime.setHours(0,0,0,0);

      while (d <= endTime){
        if (d.getFullYear() === y && d.getMonth() === m){
          const key = ymd(d);
          const list = byDate.get(key) || [];
          const timeLabel = an.startTime
            ? (an.endTime ? `${an.startTime}–${an.endTime}` : an.startTime)
            : '';
          list.push({
            kind:'announcement',
            type:an.type,
            priority:an.priority,
            title:an.title,
            desc:an.desc,
            link:an.link,
            image:an.image,
            color:an.color,
            txtcolor:an.txtcolor,
            dateObj:new Date(d.getTime()),
            dateStr:key,
            time:timeLabel,
            raw:an.raw
          });
          byDate.set(key, list);
        }
        d.setDate(d.getDate() + 1);
      }
    }

    for (const [key, list] of byDate){
      list.sort((a,b) => {
        if ((b.priority || 0) !== (a.priority || 0))
          return (b.priority || 0) - (a.priority || 0);
        if (a.kind !== b.kind){
          if (a.kind === 'announcement') return -1;
          if (b.kind === 'announcement') return 1;
        }
        return (a.title || '').localeCompare(b.title || '');
      });
    }

    return byDate;
  }

  function render(){
    currentByDate = buildByDate();

    const monthLabel = new Intl.DateTimeFormat('en-NZ',{
      month:'long',
      year:'numeric'
    }).format(view);
    titleEl.textContent = '<?php echo htmlspecialchars($title, ENT_QUOTES); ?> – ' + monthLabel;

    grid.querySelectorAll('.cal-cell').forEach(n => n.remove());

    const y = view.getFullYear();
    const m = view.getMonth();
    const first = new Date(y, m, 1);
    const startDay = first.getDay();
    const daysInMonth = new Date(y, m + 1, 0).getDate();

    const prevDays = startDay;
    const prevMonthDays = new Date(y, m, 0).getDate();
    let dayNumPrev = prevMonthDays - prevDays + 1;
    for (let i = 0; i < prevDays; i++){
      grid.appendChild(createCell(new Date(y, m - 1, dayNumPrev++), true));
    }

    for (let d = 1; d <= daysInMonth; d++){
      grid.appendChild(createCell(new Date(y, m, d), false));
    }

    const filled = prevDays + daysInMonth;
    const trailing = (7 - (filled % 7)) % 7;
    for (let t = 1; t <= trailing; t++){
      grid.appendChild(createCell(new Date(y, m + 1, t), true));
    }

    const typeCounts = new Map();
    const typeColor  = new Map();

    for (const [ds, list] of currentByDate){
      for (const it of list){
        const d = it.dateObj;
        if (!d || d.getFullYear() !== y || d.getMonth() !== m) continue;

        const type = it.kind === 'announcement'
          ? 'announcement'
          : (it.type || 'event');

        typeCounts.set(type, (typeCounts.get(type) || 0) + 1);

        if (!typeColor.has(type) && it.color){
          typeColor.set(type, it.color);
        }
      }
    }

    legendEl.innerHTML = '';
    if (typeCounts.size){
      legendEl.setAttribute('aria-hidden','false');
      for (const [type, count] of typeCounts){
        const label = TYPE_LABELS[type] || (type.charAt(0).toUpperCase() + type.slice(1));
        const col   = typeColor.get(type) || '#64748b';

        const div = document.createElement('div');
        div.className = 'legend-item';
        div.innerHTML = `
          <span class="dot" style="background:${col};"></span>
          <span>${label}</span>
          <span class="legend-count">(${count})</span>
        `;
        legendEl.appendChild(div);
      }
    } else {
      legendEl.setAttribute('aria-hidden','true');
    }
  }

  function createCell(dateObj, outOfMonth){
    const cell   = document.createElement('div');
    const dateStr= ymd(dateObj);
    const dayNum = dateObj.getDate();
    cell.className = 'cal-cell' + (outOfMonth ? ' cal-outmonth' : '');

    if (!outOfMonth &&
        dateObj.getFullYear() === today.getFullYear() &&
        dateObj.getMonth() === today.getMonth() &&
        dateObj.getDate() === today.getDate()){
      cell.classList.add('cal-today-cell');
    }

    const daySpan = document.createElement('div');
    daySpan.className = 'cal-day';
    daySpan.textContent = dayNum;
    cell.appendChild(daySpan);

    const eventsWrap = document.createElement('div');
    eventsWrap.className = 'cal-events';
    cell.appendChild(eventsWrap);

    const todays = (currentByDate.get(dateStr) || []).slice();
    todays.forEach((e, idx) => {
      const row = document.createElement('div');
      row.className = 'cal-event';
      row.innerHTML = `
        <span class="dot" style="background:${e.color || '#64748b'};"></span>
        <span class="ev-time">${e.time || ''}</span>
        <span class="ev-title">${e.title}</span>
      `;
      row.addEventListener('click', ev => {
        ev.stopPropagation();
        openDetails(dateObj);
      });
      eventsWrap.appendChild(row);
      if (idx === 2 && todays.length > 3){
        const more = document.createElement('div');
        more.className = 'cal-event';
        more.innerHTML = `<span class="ev-more">+${todays.length - 3} more…</span>`;
        more.addEventListener('click', ev => {
          ev.stopPropagation();
          openDetails(dateObj);
        });
        eventsWrap.appendChild(more);
        return;
      }
    });

    cell.addEventListener('click', () => openDetails(dateObj));
    return cell;
  }

  function openDetails(dateObj){
    const dateStr = ymd(dateObj);
    const nice = new Intl.DateTimeFormat('en-NZ',{
      year:'numeric',month:'short',day:'numeric'
    }).format(dateObj);
    const items = (currentByDate.get(dateStr) || []).slice();

    document.getElementById('detailTitle').textContent = nice;

    if (!items.length){
      modalContent.innerHTML = '<p>No items for this day.</p>';
    } else {
      modalContent.innerHTML = items.map(e => {
        const badge = `<span class="item-badge">${
          e.kind === 'announcement'
            ? (e.type ? e.type.charAt(0).toUpperCase()+e.type.slice(1) : 'Announcement')
            : (e.type ? e.type.charAt(0).toUpperCase()+e.type.slice(1) : 'Event')
        }</span>`;
        const time = e.time ? `<span class="item-time">${e.time}</span>` : '';
        const desc = e.desc ? `<div class="item-desc">${e.desc}</div>` : '';
        const link = e.link
          ? `<div class="item-link"><a href="${e.link}" target="_blank" rel="noopener">Read more</a></div>`
          : '';
        const img  = e.image
          ? `<div class="item-img"><img src="${e.image}" alt=""></div>`
          : '';
        return `
          <article class="item">
            <div><span class="item-title">${e.title}</span> ${badge}</div>
            ${time}
            ${desc}
            ${link}
            ${img}
          </article>
        `;
      }).join('');
    }

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }

  function closeModal(){
    modal.style.display = 'none';
    document.body.style.overflow = '';
  }

  prevBtn.addEventListener('click', () => {
    view.setMonth(view.getMonth() - 1);
    render();
  });
  nextBtn.addEventListener('click', () => {
    view.setMonth(view.getMonth() + 1);
    render();
  });
  todayBtn.addEventListener('click', () => {
    view = new Date(today.getFullYear(), today.getMonth(), 1);
    render();
  });

  modalClose.addEventListener('click', closeModal);
  modal.addEventListener('click', e => {
    if (e.target === modal) closeModal();
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && modal.style.display === 'flex') {
      closeModal();
    }
  });

  // Initial render
  render();
})();
</script>

<?php
include_once __DIR__ . '/include/footer.php';
?>
