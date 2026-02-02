/* maps/map-embed-v8.js
   - Fixes "top alignment" by sizing the map canvas from its actual page position.
   - Adds Reset View button (safe reset = reload, no touching map-script internals).
   - Optional debug panel: ?debug=1
*/
(function(){
  'use strict';

  function byId(id){ return document.getElementById(id); }

  function isDebug(){
    return /(?:\?|&)debug=1(?:&|$)/.test(window.location.search);
  }

  function setCanvasHeight(){
    var canvas = byId('cpMapCanvas');
    var map = byId('map');
    if(!canvas || !map) return;

    // How much space is above the map canvas in the viewport?
    var r = canvas.getBoundingClientRect();
    var paddingBottom = 16; // breathing room
    var h = Math.floor(window.innerHeight - r.top - paddingBottom);

    // Clamp to sane bounds so footer still exists and mobile doesn't collapse
    if (!isFinite(h) || h < 420) h = 420;
    if (h > 1200) h = 1200;

    canvas.style.height = h + 'px';
    map.style.height = '100%';

    // Trigger Leaflet resize without needing access to the map variable
    window.dispatchEvent(new Event('resize'));
  }

  function wireReset(){
    var btn = byId('cpMapResetBtn');
    if(!btn) return;
    btn.addEventListener('click', function(){
      // Safe reset: reload clears selections/search + returns to default view.
      // (This avoids touching the internal `map` variable in map-script.js.)
      window.location.href = window.location.pathname + window.location.search.replace(/([?&])debug=1(&?)/, '$1').replace(/[?&]$/, '');
    });
  }

  function showDebug(){
    if(!isDebug()) return;
    var box = byId('cpMapDebug');
    if(!box) return;
    box.style.display = 'block';

    var lines = [];
    var canvas = byId('cpMapCanvas');
    var map = byId('map');

    lines.push('debug=1');
    if (canvas) {
      var r = canvas.getBoundingClientRect();
      lines.push('canvas.top=' + Math.round(r.top) + 'px  height(style)=' + (canvas.style.height || '(none)'));
    }
    if (map) {
      lines.push('map.size=' + map.clientWidth + 'x' + map.clientHeight);
    }
    lines.push('leaflet.L=' + (window.L ? 'OK' : 'MISSING'));

    // Probe stats endpoint (won't throw if blocked)
    try {
      fetch('map-data.php?action=stats', { cache: 'no-store' })
        .then(function(res){ return res.text().then(function(t){ return {status:res.status, text:t}; }); })
        .then(function(r){
          lines.push('stats.http=' + r.status);
          box.textContent = lines.join('\n') + '\n\n' + r.text.slice(0, 500);
        })
        .catch(function(e){
          lines.push('stats.error=' + (e && e.message ? e.message : String(e)));
          box.textContent = lines.join('\n');
        });
    } catch(e){
      lines.push('fetch.error=' + (e && e.message ? e.message : String(e)));
      box.textContent = lines.join('\n');
    }
  }

  function init(){
    wireReset();
    setCanvasHeight();
    // Re-run after late-loading header fonts/images settle
    setTimeout(setCanvasHeight, 250);
    setTimeout(setCanvasHeight, 1000);
    showDebug();
    window.addEventListener('resize', function(){ setCanvasHeight(); }, { passive:true });
  }

  if (document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();