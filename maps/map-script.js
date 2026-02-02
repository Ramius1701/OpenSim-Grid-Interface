/**
 * OpenSim Grid - Interactive Map Script (Leaflet)
 * - No hard-coded grid branding
 * - Portable paths (relative to this script's directory)
 * - Requires: map-data.php (JSON) + map-tile.php (tile proxy)
 */

let map;
let regionsData = [];
let regionLayers = [];   // Leaflet layers for region tiles (image overlays)
let currentSearchResults = [];

// Derive base URLs from the script location so this works in any folder
const SCRIPT_URL = new URL(document.currentScript.src);
const BASE_URL = new URL('.', SCRIPT_URL);           // directory/
const TILE_BASE_URL = new URL('data/', BASE_URL);

// Configuration
// Configuration
const CONFIG = {
    // Point to your new map-data.php (handles stats, regions, search)
    apiUrl: new URL('map-data.php', BASE_URL).toString(), 
    
    // Point to the Proxy File
    proxyUrl: new URL('map-tile.php', BASE_URL).toString(),

    defaultCenter: [1000, 1000], 
    defaultZoom: 6,
    minZoom: 2,
    maxZoom: 8,
    tileSize: 256
};

function safeSetText(id, value) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = value;
}

function formatInt(n) {
    const v = Number.isFinite(n) ? Math.trunc(n) : 0;
    return v.toLocaleString();
}

function escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/**
 * Initialize map
 */
function initMap() {
    // Create custom CRS for OpenSim grid (simple planar)
    const gridCRS = L.extend({}, L.CRS.Simple, {
        transformation: new L.Transformation(1, 0, -1, 0)
    });

    map = L.map('map', {
        crs: gridCRS,
        minZoom: CONFIG.minZoom,
        maxZoom: CONFIG.maxZoom,
        zoomControl: true,
        attributionControl: true
    });

    map.setView(CONFIG.defaultCenter, CONFIG.defaultZoom);

    // attribution prefix will be replaced once stats are loaded (if provided)
    map.attributionControl.setPrefix('OpenSim Map');

    loadGridStats();
    loadRegions();
    setupSearch();
}

/**
 * Load grid statistics (and update sidebar counts)
 */
async function loadGridStats() {
    try {
        const response = await fetch(`${CONFIG.apiUrl}?action=stats`, { cache: 'no-store' });
        const data = await response.json();

        if (!data || !data.success) return;

        const stats = data.data || {};

        // If API provides gridName, use it
        if (stats.gridName) {
            map.attributionControl.setPrefix(escapeHtml(stats.gridName));
        }

        const totalRegions = Number(stats.totalRegions || 0);
        const onlineRegions = Number(stats.onlineRegions || 0);
        const totalUsers = Number(stats.totalUsers || 0);
        const usersOnline = Number(stats.usersOnline || 0);
        const newUsersToday = Number(stats.newUsersToday || 0);

        safeSetText('statOnlineNow', formatInt(usersOnline));
        safeSetText('statTotalUsers', formatInt(totalUsers));
        safeSetText('statRegions', formatInt(totalRegions));
        safeSetText('statTransactions', formatInt(onlineRegions));

        // header line under title
        const header = totalRegions > 0 ? `${formatInt(totalRegions)} regions` : 'No region data';
        safeSetText('headerRegionCount', header);

        // sublabels
        const newUsersEl = document.getElementById('statNewUsersToday');
        if (newUsersEl) newUsersEl.textContent = newUsersToday > 0 ? `+${formatInt(newUsersToday)} today` : '';

        const regionsSub = document.getElementById('statRegionsSub');
        if (regionsSub) {
            const available = Number(stats.availableRegions || 0);
            regionsSub.textContent = available > 0 ? `${formatInt(available)} available to claim` : '';
        }

        const transVol = document.getElementById('transVolume');
        if (transVol) {
            const ts = stats.timestamp ? new Date(stats.timestamp * 1000) : null;
            transVol.textContent = ts ? `Updated ${ts.toLocaleString()}` : '';
        }
    } catch (error) {
        console.error('Failed to load stats:', error);
    }
}

/**
 * Load all regions
 */
async function loadRegions() {
    try {
        const response = await fetch(`${CONFIG.apiUrl}?action=regions`, { cache: 'no-store' });
        const data = await response.json();

        if (data && data.success) {
            regionsData = (data.data && data.data.regions) ? data.data.regions : [];
            addRegionTiles();
        }
    } catch (error) {
        console.error('Failed to load regions:', error);
    } finally {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) overlay.style.display = 'none';
    }
}

/**
 * Add image overlays for region tiles (supports varregions by splitting into 256m tiles)
 */
function addRegionTiles() {
    // remove previous layers
    regionLayers.forEach(layer => map.removeLayer(layer));
    regionLayers = [];

    if (!Array.isArray(regionsData) || regionsData.length === 0) {
        return;
    }

    let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;

    regionsData.forEach(region => {
        const tilesX = Math.max(1, Math.ceil((Number(region.sizeX) || 256) / 256));
        const tilesY = Math.max(1, Math.ceil((Number(region.sizeY) || 256) / 256));

        for (let ty = 0; ty < tilesY; ty++) {
            for (let tx = 0; tx < tilesX; tx++) {
                const tileGridX = Number(region.gridX) + tx;
                const tileGridY = Number(region.gridY) + ty;

                if (!Number.isFinite(tileGridX) || !Number.isFinite(tileGridY)) continue;

                // Bounds are in Leaflet "tile units": 1 unit = 256m
                const imageBounds = [
                    [tileGridY, tileGridX],
                    [tileGridY + 1, tileGridX + 1]
                ];

                // OLD: const tileUrl = `${CONFIG.tileBaseUrl}map-1-${tileGridX}-${tileGridY}-objects.jpg`;
                // NEW: Use the proxy URL with query parameters
                const tileUrl = `${CONFIG.proxyUrl}?x=${tileGridX}&y=${tileGridY}`;

                const layer = L.imageOverlay(tileUrl, imageBounds, {
                    opacity: 1.0,
                    interactive: true
                }).addTo(map);

                // tag layer for search jump
                layer._regionUuid = region.uuid;

                layer.bindPopup(() => createRegionPopup(region), {
                    maxWidth: 350,
                    className: 'region-popup-container',
                    closeButton: false,
                });

                regionLayers.push(layer);

                // track extents
                minX = Math.min(minX, tileGridX);
                minY = Math.min(minY, tileGridY);
                maxX = Math.max(maxX, tileGridX + 1);
                maxY = Math.max(maxY, tileGridY + 1);
            }
        }
    });

    // Fit view to available region extents (with padding)
    if (Number.isFinite(minX) && Number.isFinite(minY) && Number.isFinite(maxX) && Number.isFinite(maxY)) {
        const bounds = [[minY, minX], [maxY, maxX]];
        map.fitBounds(bounds, { padding: [20, 20] });
    }
}

function createRegionPopup(region) {
    return `
        <div class="region-popup-content">
            <div class="region-popup-header">
                <h3>${escapeHtml(region.regionName)}</h3>
                <small>${region.isOnline ? '🟢 Online' : '⚪ Offline'}</small>
            </div>
            <div class="region-popup-body">
                <div class="region-info-row">
                    <span><strong>Owner:</strong></span>
                    <span>${escapeHtml(region.ownerName || '—')}</span>
                </div>
                <div class="region-info-row">
                    <span><strong>Size:</strong></span>
                    <span>${Number(region.sizeX) || 256}m × ${Number(region.sizeY) || 256}m</span>
                </div>
                <div class="region-info-row">
                    <span><strong>Location:</strong></span>
                    <span>(${Number(region.gridX) || 0}, ${Number(region.gridY) || 0})</span>
                </div>
            </div>
            <div class="region-popup-footer">
                <a href="${escapeHtml(region.teleportLink || '#')}" class="btn-teleport">Visit Now</a>
            </div>
        </div>
    `;
}

/**
 * Search
 */
function setupSearch() {
    const searchInput = document.getElementById('searchInput');
    const searchBtn = document.getElementById('searchBtn');
    const resultsDiv = document.getElementById('searchResults');
    const clearBtn = document.getElementById('clearSearch');

    if (!searchInput || !searchBtn || !resultsDiv || !clearBtn) return;

    const setClearVisible = (visible) => {
        clearBtn.classList.toggle('d-none', !visible);
    };

    setClearVisible(false);

    const doSearch = () => {
        const query = searchInput.value.trim();
        if (query.length >= 2) performSearch(query);
    };

    searchBtn.addEventListener('click', doSearch);
    searchInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') doSearch();
    });

    searchInput.addEventListener('input', () => {
        if (searchInput.value.trim().length === 0) {
            resultsDiv.style.display = 'none';
            resultsDiv.innerHTML = '';
            currentSearchResults = [];
            setClearVisible(false);
        }
    });

    clearBtn.addEventListener('click', () => {
        searchInput.value = '';
        resultsDiv.style.display = 'none';
        resultsDiv.innerHTML = '';
        currentSearchResults = [];
        setClearVisible(false);
    });
}

async function performSearch(query) {
    try {
        const [regionsResponse, landsResponse] = await Promise.all([
            fetch(`${CONFIG.apiUrl}?action=search&query=${encodeURIComponent(query)}`),
            fetch(`${CONFIG.apiUrl}?action=search_lands&query=${encodeURIComponent(query)}`)
        ]);

        const regionsJson = await regionsResponse.json().catch(() => null);
        const landsJson = await landsResponse.json().catch(() => null);

        const results = {
            regions: (regionsJson && regionsJson.success && regionsJson.data && regionsJson.data.results) ? regionsJson.data.results : [],
            lands: (landsJson && landsJson.success && landsJson.data && landsJson.data.results) ? landsJson.data.results : []
        };

        currentSearchResults = results.regions.concat(results.lands);
        displaySearchResults(results);
    } catch (error) {
        console.error('Search failed:', error);
    }
}

function displaySearchResults(results) {
    const resultsDiv = document.getElementById('searchResults');
    const clearBtn = document.getElementById('clearSearch');
    if (!resultsDiv || !clearBtn) return;

    const total = (results.regions?.length || 0) + (results.lands?.length || 0);

    if (total === 0) {
        resultsDiv.innerHTML = '<div class="search-result-item">No results found</div>';
        resultsDiv.style.display = 'block';
        clearBtn.classList.remove('d-none');
        return;
    }

    let html = '';

    if (results.regions && results.regions.length > 0) {
        html += `<div style="padding:10px;font-weight:bold;color:#667eea;border-bottom:1px solid rgba(102,126,234,0.3);">📍 REGIONS (${results.regions.length})</div>`;
        results.regions.forEach(region => {
            const statusIcon = region.isOnline ? '🟢' : '⚪';
            html += `
                <div class="search-result-item" onclick="flyToRegion(${Number(region.gridX)||0}, ${Number(region.gridY)||0}, '${escapeHtml(region.uuid)}')">
                    <strong>${statusIcon} ${escapeHtml(region.regionName)}</strong>
                    <small>Region at (${Number(region.gridX)||0}, ${Number(region.gridY)||0})</small>
                </div>
            `;
        });
    }

    if (results.lands && results.lands.length > 0) {
        html += `<div style="padding:10px;font-weight:bold;color:#4ecdc4;border-bottom:1px solid rgba(78,205,196,0.3);">🏡 LANDS (${results.lands.length})</div>`;
        results.lands.forEach(land => {
            html += `
                <div class="search-result-item" onclick="flyToLand(${Number(land.gridX)||0}, ${Number(land.gridY)||0}, '${escapeHtml(land.parceluuid)}', '${escapeHtml(land.teleportLink)}')">
                    <strong>🏡 ${escapeHtml(land.landname)}</strong>
                    <small>${escapeHtml(land.regionname)}</small>
                </div>
            `;
        });
    }

    resultsDiv.innerHTML = html;
    resultsDiv.style.display = 'block';
    clearBtn.classList.remove('d-none');
}

/**
 * Jump to a region and open its popup
 */
function flyToRegion(gridX, gridY, regionUuid) {
    map.flyTo([gridY + 0.5, gridX + 0.5], 9, { duration: 1.2 });

    setTimeout(() => {
        const layer = regionLayers.find(l => l._regionUuid === regionUuid);
        if (layer && typeof layer.openPopup === 'function') {
            layer.openPopup();
        }
    }, 900);
}

// Stub: lands are optional. Keep this so the UI doesn't break if you add lands later.
function flyToLand(gridX, gridY, parcelUuid, teleportLink) {
    map.flyTo([gridY + 0.5, gridX + 0.5], 9, { duration: 1.2 });
    // You can implement parcel popups later if you want.
}

document.addEventListener('DOMContentLoaded', () => {
    initMap();
});