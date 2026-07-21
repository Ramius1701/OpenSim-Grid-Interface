/**
 * Casperia Prime World Map
 * Version: 1.0.0
 * Requires Leaflet 1.9.x
 */
(function () {
    'use strict';

    const CONFIG = {
        defaultCenter: [1000.5, 1000.5],
        defaultZoom: 6,
        minZoom: 2,
        maxZoom: 8,
        mapTileZoom: 1,
        tileMeters: 256
    };

    const state = {
        map: null,
        regions: [],
        layers: [],
        regionBounds: null,
        apiUrl: '',
        tileUrl: '',
        resizeTimer: null,
        tileLoadToken: 0
    };

    function byId(id) {
        return document.getElementById(id);
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function setText(id, value) {
        const element = byId(id);
        if (element) {
            element.textContent = String(value);
        }
    }

    function formatInt(value) {
        const number = Number(value);
        return Number.isFinite(number) ? Math.trunc(number).toLocaleString() : '0';
    }

    function setLoading(visible, message = 'Loading map…') {
        const overlay = byId('loadingOverlay');
        if (!overlay) return;

        const text = overlay.querySelector('.cp-map-loading-text');
        if (text) text.textContent = message;
        overlay.hidden = !visible;
    }

    function showStatus(message, type = 'info') {
        const status = byId('mapStatus');
        if (!status) return;

        status.textContent = message;
        status.dataset.type = type;
        status.hidden = false;
    }

    function hideStatus() {
        const status = byId('mapStatus');
        if (status) status.hidden = true;
    }

    async function fetchJson(url) {
        const response = await fetch(url, {
            cache: 'no-store',
            headers: { Accept: 'application/json' }
        });

        const text = await response.text();
        let json;

        try {
            json = JSON.parse(text);
        } catch (error) {
            throw new Error(`Invalid JSON from ${url} (HTTP ${response.status})`);
        }

        if (!response.ok) {
            throw new Error(json.error || `HTTP ${response.status}`);
        }

        if (!json || json.success !== true) {
            throw new Error(json && json.error ? json.error : 'API request failed');
        }

        return json.data || {};
    }

    function buildTileUrl(x, y) {
        const url = new URL(state.tileUrl, window.location.href);
        url.searchParams.set('x', String(x));
        url.searchParams.set('y', String(y));
        url.searchParams.set('z', String(CONFIG.mapTileZoom));
        return url.toString();
    }

    function createPopup(region) {
        const sizeX = Number(region.sizeX) || CONFIG.tileMeters;
        const sizeY = Number(region.sizeY) || CONFIG.tileMeters;
        const gridX = Number(region.gridX) || 0;
        const gridY = Number(region.gridY) || 0;
        const teleportLink = region.teleportLink || '#';

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
                        <span>${sizeX}m × ${sizeY}m</span>
                    </div>
                    <div class="region-info-row">
                        <span><strong>Location:</strong></span>
                        <span>(${gridX}, ${gridY})</span>
                    </div>
                </div>
                <div class="region-popup-footer">
                    <a href="${escapeHtml(teleportLink)}" class="btn-teleport">Visit Now</a>
                </div>
            </div>`;
    }

    function sizeMapCanvas() {
        const canvas = byId('cpMapCanvas');
        if (!canvas) return;

        const rect = canvas.getBoundingClientRect();
        let height = Math.floor(window.innerHeight - rect.top - 16);

        if (!Number.isFinite(height)) height = 620;
        height = Math.max(420, Math.min(1200, height));
        canvas.style.height = `${height}px`;

        if (state.map) {
            window.requestAnimationFrame(() => {
                state.map.invalidateSize({ pan: false });
            });
        }
    }

    function scheduleResize() {
        if (state.resizeTimer !== null) {
            window.clearTimeout(state.resizeTimer);
        }

        state.resizeTimer = window.setTimeout(() => {
            state.resizeTimer = null;
            sizeMapCanvas();
        }, 80);
    }

    function fitToRegions() {
        if (!state.map) return;

        if (state.regionBounds && state.regionBounds.isValid()) {
            state.map.fitBounds(state.regionBounds, {
                padding: [24, 24],
                animate: false
            });
        } else {
            state.map.setView(CONFIG.defaultCenter, CONFIG.defaultZoom, { animate: false });
        }
    }

    function clearRegionLayers() {
        if (!state.map) return;

        state.layers.forEach((layer) => state.map.removeLayer(layer));
        state.layers = [];
        state.regionBounds = null;
    }

    function addRegionTiles() {
        clearRegionLayers();

        if (!Array.isArray(state.regions) || state.regions.length === 0) {
            setLoading(false);
            showStatus('No regions were returned by map-data.php.', 'error');
            return;
        }

        const bounds = L.latLngBounds([]);
        const loadToken = ++state.tileLoadToken;
        let expected = 0;
        let settled = 0;
        let loaded = 0;
        let failed = 0;

        function settleTile(ok, tileUrl, regionName) {
            if (loadToken !== state.tileLoadToken) return;

            settled += 1;
            if (ok) {
                loaded += 1;
            } else {
                failed += 1;
                console.error('Map tile failed:', regionName, tileUrl);
            }

            if (settled >= expected) {
                setLoading(false);
                if (failed > 0) {
                    showStatus(`${failed} of ${expected} map tiles failed to load.`, 'error');
                } else {
                    hideStatus();
                }
            }
        }

        state.regions.forEach((region) => {
            const gridX = Number(region.gridX);
            const gridY = Number(region.gridY);
            if (!Number.isFinite(gridX) || !Number.isFinite(gridY)) return;

            const sizeX = Number(region.sizeX) || CONFIG.tileMeters;
            const sizeY = Number(region.sizeY) || CONFIG.tileMeters;
            const tilesX = Math.max(1, Math.ceil(sizeX / CONFIG.tileMeters));
            const tilesY = Math.max(1, Math.ceil(sizeY / CONFIG.tileMeters));

            for (let tileY = 0; tileY < tilesY; tileY += 1) {
                for (let tileX = 0; tileX < tilesX; tileX += 1) {
                    const x = gridX + tileX;
                    const y = gridY + tileY;
                    const imageBounds = [[y, x], [y + 1, x + 1]];
                    const tileUrl = buildTileUrl(x, y);

                    expected += 1;
                    bounds.extend(imageBounds[0]);
                    bounds.extend(imageBounds[1]);

                    const layer = L.imageOverlay(tileUrl, imageBounds, {
                        opacity: 1,
                        interactive: true
                    });

                    layer._regionUuid = String(region.uuid || '');
                    layer.once('load', () => settleTile(true, tileUrl, region.regionName));
                    layer.once('error', () => settleTile(false, tileUrl, region.regionName));
                    layer.bindPopup(() => createPopup(region), {
                        maxWidth: 350,
                        className: 'region-popup-container',
                        closeButton: false
                    });

                    layer.addTo(state.map);
                    state.layers.push(layer);
                }
            }
        });

        state.regionBounds = bounds;
        fitToRegions();

        if (expected === 0) {
            setLoading(false);
            showStatus('Region data contained no usable map coordinates.', 'error');
            return;
        }

        window.setTimeout(() => {
            if (loadToken !== state.tileLoadToken || settled >= expected) return;
            setLoading(false);
            showStatus(`Map tile loading timed out: ${loaded} loaded, ${failed} failed, ${expected - settled} still pending.`, 'error');
        }, 12000);
    }

    async function loadRegions() {
        setLoading(true, 'Loading regions…');

        try {
            const data = await fetchJson(`${state.apiUrl}?action=regions`);
            state.regions = Array.isArray(data.regions) ? data.regions : [];
            addRegionTiles();
        } catch (error) {
            console.error('Failed to load regions:', error);
            setLoading(false);
            showStatus(`Unable to load region data: ${error.message}`, 'error');
        }
    }

    async function loadStats() {
        try {
            const stats = await fetchJson(`${state.apiUrl}?action=stats`);
            const totalRegions = Number(stats.totalRegions) || 0;
            const usersOnline = Number(stats.usersOnline) || 0;

            setText('headerRegionCount', formatInt(totalRegions));
            setText('statOnlineNow', formatInt(usersOnline));

            if (stats.gridName && state.map && state.map.attributionControl) {
                state.map.attributionControl.setPrefix(escapeHtml(stats.gridName));
            }
        } catch (error) {
            console.error('Failed to load map stats:', error);
        }
    }

    function clearSearch() {
        const input = byId('searchInput');
        const results = byId('searchResults');
        const clearButton = byId('clearSearch');

        if (input) input.value = '';
        if (results) {
            results.replaceChildren();
            results.hidden = true;
        }
        if (clearButton) clearButton.classList.add('d-none');
    }

    function findRegionLayer(uuid) {
        const wanted = String(uuid || '');
        return state.layers.find((layer) => layer._regionUuid === wanted) || null;
    }

    function flyToRegion(region) {
        if (!state.map) return;

        const gridX = Number(region.gridX) || 0;
        const gridY = Number(region.gridY) || 0;
        state.map.flyTo([gridY + 0.5, gridX + 0.5], Math.min(8, CONFIG.maxZoom), {
            duration: 0.8
        });

        window.setTimeout(() => {
            const layer = findRegionLayer(region.uuid);
            if (layer) layer.openPopup();
        }, 850);
    }

    function renderSearchResults(regions) {
        const results = byId('searchResults');
        const clearButton = byId('clearSearch');
        if (!results || !clearButton) return;

        results.replaceChildren();
        results.hidden = false;
        clearButton.classList.remove('d-none');

        if (!Array.isArray(regions) || regions.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'search-result-item';
            empty.textContent = 'No regions found';
            results.appendChild(empty);
            return;
        }

        regions.forEach((region) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'search-result-item';

            const title = document.createElement('strong');
            title.textContent = `${region.isOnline ? '🟢' : '⚪'} ${region.regionName || 'Unnamed Region'}`;

            const detail = document.createElement('small');
            detail.textContent = `Region at (${Number(region.gridX) || 0}, ${Number(region.gridY) || 0})`;

            item.append(title, detail);
            item.addEventListener('click', () => flyToRegion(region));
            results.appendChild(item);
        });
    }

    async function performSearch() {
        const input = byId('searchInput');
        if (!input) return;

        const query = input.value.trim();
        if (query.length < 2) return;

        try {
            const data = await fetchJson(`${state.apiUrl}?action=search&query=${encodeURIComponent(query)}`);
            renderSearchResults(Array.isArray(data.results) ? data.results : []);
        } catch (error) {
            console.error('Map search failed:', error);
            renderSearchResults([]);
        }
    }

    function setupControls() {
        const searchButton = byId('searchBtn');
        const searchInput = byId('searchInput');
        const clearButton = byId('clearSearch');
        const resetButton = byId('cpMapResetBtn');

        if (searchButton) searchButton.addEventListener('click', performSearch);
        if (clearButton) clearButton.addEventListener('click', clearSearch);
        if (resetButton) {
            resetButton.addEventListener('click', () => {
                clearSearch();
                state.map.closePopup();
                fitToRegions();
            });
        }

        if (searchInput) {
            searchInput.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') performSearch();
            });
            searchInput.addEventListener('input', () => {
                if (searchInput.value.trim() === '') clearSearch();
            });
        }
    }

    function showDebug() {
        const params = new URLSearchParams(window.location.search);
        if (params.get('debug') !== '1') return;

        const debug = byId('cpMapDebug');
        const canvas = byId('cpMapCanvas');
        if (!debug) return;

        debug.hidden = false;
        const lines = [
            'Casperia World Map v1.0.0',
            `Leaflet: ${window.L ? L.version : 'MISSING'}`,
            `API: ${state.apiUrl}`,
            `Tile proxy: ${state.tileUrl}`,
            `Canvas: ${canvas ? `${canvas.clientWidth}x${canvas.clientHeight}` : 'MISSING'}`
        ];

        debug.textContent = lines.join('\n');
    }

    function init() {
        const mapElement = byId('map');
        if (!mapElement) return;

        if (!window.L) {
            setLoading(false);
            showStatus('Leaflet failed to load.', 'error');
            return;
        }

        state.apiUrl = mapElement.dataset.apiUrl || 'map-data.php';
        state.tileUrl = mapElement.dataset.tileUrl || 'map-tile.php';

        sizeMapCanvas();

        state.map = L.map(mapElement, {
            crs: L.CRS.Simple,
            minZoom: CONFIG.minZoom,
            maxZoom: CONFIG.maxZoom,
            zoomControl: true,
            attributionControl: true
        });

        state.map.setView(CONFIG.defaultCenter, CONFIG.defaultZoom);
        state.map.attributionControl.setPrefix('Casperia Prime');

        setupControls();
        loadStats();
        loadRegions();
        showDebug();

        window.addEventListener('resize', scheduleResize, { passive: true });
        window.setTimeout(sizeMapCanvas, 250);
        window.setTimeout(sizeMapCanvas, 1000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
}());
