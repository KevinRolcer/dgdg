(function () {
    'use strict';

    const root = document.getElementById('microregionesRoot');
    if (!root || typeof L === 'undefined') {
        return;
    }

    const dataUrl = root.getAttribute('data-data-url');
    var searchUrl = root.getAttribute('data-search-url');
    var searchUrls = [];
    var searchPostUrl = root.getAttribute('data-search-post-url');
    var searchPostDisabled = false;

    function pushUniqueUrl(list, url) {
        if (!url || typeof url !== 'string') return;
        if (list.indexOf(url) === -1) {
            list.push(url);
        }
    }

    /** Solo si la página actual cargó vía /index.php/… (evita 404 al forzar index.php en hosts con URLs limpias). */
    function toIndexPhpVariant(url) {
        if (!url || typeof url !== 'string') return null;
        try {
            var abs = new URL(url, window.location.href);
            var p = abs.pathname;
            if (p.indexOf('/index.php/') === 0) {
                return p + abs.search + abs.hash;
            }
            return '/index.php' + p + abs.search + abs.hash;
        } catch (e) {
            if (url.indexOf('/index.php/') === 0) return url;
            if (url.indexOf('/') === 0) return '/index.php' + url;
            return null;
        }
    }

    function appendIndexPhpSearchVariantsIfNeeded() {
        if (window.location.pathname.indexOf('/index.php/') === -1) {
            return;
        }
        var snap = searchUrls.slice();
        snap.forEach(function (u) {
            var v = toIndexPhpVariant(u);
            if (v) pushUniqueUrl(searchUrls, v);
        });
    }

    function initSearchUrlsFromDom() {
        searchUrls = [];
        var multi = root.getAttribute('data-search-urls');
        if (multi) {
            try {
                var parsed = JSON.parse(multi);
                if (Array.isArray(parsed)) {
                    parsed.forEach(function (u) { pushUniqueUrl(searchUrls, u); });
                }
            } catch (eDom) { /* ignore */ }
        }
        pushUniqueUrl(searchUrls, searchUrl);
        // Final fallback: same page endpoint (usually /microregiones), known to be reachable.
        pushUniqueUrl(searchUrls, window.location.pathname || '/microregiones');
        pushUniqueUrl(searchUrls, '/microregiones');
        appendIndexPhpSearchVariantsIfNeeded();
    }

    initSearchUrlsFromDom();

    /** Evita peticiones al host de APP_URL si la página se abrió con otro origen (cookies no iban → 401). */
    function sameOriginFetchUrl(pathOrUrl) {
        if (!pathOrUrl) return pathOrUrl;
        try {
            var abs = new URL(pathOrUrl, window.location.href);
            return window.location.origin + abs.pathname + abs.search + abs.hash;
        } catch (e) {
            return pathOrUrl;
        }
    }
    const mapEl = document.getElementById('microregionesMap');
    const accordionEl = document.getElementById('microregionesAccordion');
    const detailEl = document.getElementById('microregionesDetail');
    const detailContentEl = document.getElementById('microregionesDetailContent');
    const detailCloseBtn = document.getElementById('microregionesDetailClose');
    const searchInput = document.getElementById('microregionesSearchInput');
    const searchGo = document.getElementById('microregionesSearchGo');
    const searchHint = document.getElementById('microregionesSearchHint');
    const searchResultsEl = document.getElementById('microregionesSearchResults');
    const pinnedBarEl = document.getElementById('microregionesSearchPinned');
    const pinnedBarLabelEl = document.getElementById('microregionesSearchPinnedLabel');
    const pinnedFocusBtn = document.getElementById('microregionesSearchPinnedFocus');
    const pinnedClearBtn = document.getElementById('microregionesSearchPinnedClear');
    const layoutEl = document.getElementById('microregionesLayout') || document.querySelector('.microregiones-layout');
    const toggleBtn = document.getElementById('microregionesToggleSidebar');
    const sidebarEl = document.getElementById('microregionesSidebar');
    const mobileDrawerTab = document.getElementById('microregionesMobileDrawerTab');
    const mobileBackdrop = document.getElementById('microregionesMobileBackdrop');
    const sidebarMobileClose = document.getElementById('microregionesSidebarMobileClose');
    /** Móvil + tablet: panel tipo cajón (mismo breakpoint que CSS). */
    const microMobileMq = window.matchMedia ? window.matchMedia('(max-width: 1024px)') : { matches: false, addEventListener: null, addListener: null };

    function isMicroMobileViewport() {
        return microMobileMq.matches;
    }

    function openMicroMobileDrawer() {
        if (!sidebarEl || !mobileBackdrop) {
            return;
        }
        sidebarEl.classList.add('is-open');
        mobileBackdrop.classList.add('is-visible');
        if (mobileDrawerTab) {
            mobileDrawerTab.classList.add('is-hidden');
            mobileDrawerTab.setAttribute('aria-expanded', 'true');
        }
        if (map) {
            setTimeout(function () {
                map.invalidateSize();
            }, 280);
        }
    }

    function closeMicroMobileDrawer() {
        if (!sidebarEl || !mobileBackdrop) {
            return;
        }
        sidebarEl.classList.remove('is-open');
        mobileBackdrop.classList.remove('is-visible');
        if (mobileDrawerTab) {
            mobileDrawerTab.classList.remove('is-hidden');
            mobileDrawerTab.setAttribute('aria-expanded', 'false');
        }
        if (map) {
            setTimeout(function () {
                map.invalidateSize();
            }, 280);
        }
    }

    function syncMicroMobileOnViewportChange() {
        if (!isMicroMobileViewport()) {
            closeMicroMobileDrawer();
        }
    }

    /** @type {L.Map|null} */
    let map = null;
    const markerByMunicipioId = new Map();
    const markerByMicroId = new Map(); // Pin marker per microregion
    const geometryByMunicipioId = new Map();
    const microById = new Map(); // Fast lookup instead of Array.find
    let highlightedLayer = null;
    let searchAreaLayer = null;
    let searchMarker = null;
    let micros = [];
    let flatMunicipios = [];
    let remoteSearchDebounce = null;
    let remoteSearchAbortController = null;
    let remoteSearchResults = [];
    let lastAccordionHadMatch = false;
    let remoteSearchLoading = false;
    let lastRemoteSearchKey = '';
    /** @type {object|null} copia del último resultado de búsqueda en mapa (panel fijado) */
    let pinnedSearchResult = null;

    /* ── Geometry simplification ── */
    function simplifyCoords(coords, tolerance) {
        if (!coords || coords.length < 3) return coords;
        // Douglas-Peucker simplification
        var maxDist = 0, index = 0;
        var end = coords.length - 1;
        for (var i = 1; i < end; i++) {
            var d = perpendicularDist(coords[i], coords[0], coords[end]);
            if (d > maxDist) { maxDist = d; index = i; }
        }
        if (maxDist > tolerance) {
            var left = simplifyCoords(coords.slice(0, index + 1), tolerance);
            var right = simplifyCoords(coords.slice(index), tolerance);
            return left.slice(0, left.length - 1).concat(right);
        }
        return [coords[0], coords[end]];
    }

    function perpendicularDist(point, lineStart, lineEnd) {
        var dx = lineEnd[0] - lineStart[0];
        var dy = lineEnd[1] - lineStart[1];
        var norm = Math.sqrt(dx * dx + dy * dy);
        if (norm === 0) return Math.sqrt(Math.pow(point[0] - lineStart[0], 2) + Math.pow(point[1] - lineStart[1], 2));
        return Math.abs(dy * point[0] - dx * point[1] + lineEnd[0] * lineStart[1] - lineEnd[1] * lineStart[0]) / norm;
    }

    function simplifyGeometry(geometry, tolerance) {
        if (!geometry) return geometry;
        tolerance = tolerance || 0.001; // ~100m at equator
        try {
            var type = geometry.type;
            if (type === 'Polygon') {
                return {
                    type: type,
                    coordinates: geometry.coordinates.map(function (ring) {
                        return simplifyCoords(ring, tolerance);
                    })
                };
            }
            if (type === 'MultiPolygon') {
                return {
                    type: type,
                    coordinates: geometry.coordinates.map(function (polygon) {
                        return polygon.map(function (ring) {
                            return simplifyCoords(ring, tolerance);
                        });
                    })
                };
            }
        } catch (e) { /* return original */ }
        return geometry;
    }

    /* ── Color helpers (Matte/Muted Image Palette) ── */
    const MR_COLORS = {
        1: '#db9e9e', 2: '#86b8ad', 3: '#d9d375', 4: '#95c7db', 5: '#cc90be',
        6: '#4ea898', 7: '#d6978f', 8: '#b9d6c4', 9: '#d4b192', 10: '#cc7a74',
        11: '#cfa186', 12: '#decb8a', 13: '#db9e9e', 14: '#5a9c78', 15: '#d6a863',
        16: '#4d8ab3', 17: '#855d91', 18: '#b9d6c4', 19: '#2d5470', 20: '#d9bf64',
        21: '#b199ba', 22: '#61a87e', 23: '#db9e9e', 24: '#7da88e', 25: '#97b7cf',
        26: '#b59324', 27: '#9a7ab3', 28: '#d6cdb2', 29: '#88a6ba', 30: '#5e3a6e',
        31: '#cc7a74'
    };

    function getMicroColor(micro, alpha) {
        if (!micro) return '#94a3b8';
        const num = parseInt(micro.numero, 10);
        const hex = MR_COLORS[num] || '#94a3b8';
        if (alpha !== undefined) {
            // Simple hex to rgba conversion
            const r = parseInt(hex.slice(1, 3), 16);
            const g = parseInt(hex.slice(3, 5), 16);
            const b = parseInt(hex.slice(5, 7), 16);
            return `rgba(${r}, ${g}, ${b}, ${alpha})`;
        }
        return hex;
    }

    function darkenColor(hex, amount) {
        if (!hex || hex[0] !== '#') return hex;
        amount = amount || 0.3;
        const r = parseInt(hex.slice(1, 3), 16);
        const g = parseInt(hex.slice(3, 5), 16);
        const b = parseInt(hex.slice(5, 7), 16);
        const f = (val) => Math.max(0, Math.floor(val * (1 - amount)));
        const r2 = f(r).toString(16).padStart(2, '0');
        const g2 = f(g).toString(16).padStart(2, '0');
        const b2 = f(b).toString(16).padStart(2, '0');
        return `#${r2}${g2}${b2}`;
    }

    /* ── Detail panel ── */
    function renderDetail(micro, municipio, searchResult) {
        detailEl.classList.remove('microregiones-floating-detail--hidden');
        if (detailContentEl) detailContentEl.innerHTML = '';

        var card = document.createElement('div');
        card.className = 'microregiones-detail-card';

        var top = document.createElement('div');
        top.className = 'microregiones-detail-top';

        var main = document.createElement('div');
        main.className = 'microregiones-detail-main';

        var aside = document.createElement('div');
        aside.className = 'microregiones-detail-aside';

        var img = document.createElement('img');
        img.className = 'microregiones-detail-mr-img';
        img.alt = micro.micro_label || 'MR ' + micro.numero;
        img.width = 56;
        img.height = 56;
        img.loading = 'lazy';
        if (micro.image_url) {
            img.src = micro.image_url;
        } else {
            img.src = placeholderMrSvg(micro.numero);
        }
        img.onerror = function () {
            img.onerror = null;
            img.src = placeholderMrSvg(micro.numero);
        };
        main.appendChild(img);

        var headText = document.createElement('div');
        headText.className = 'microregiones-detail-card-head-text';
        var label = micro.micro_label || 'MR ' + micro.numero;
        headText.innerHTML =
            '<h3>' +
            escapeHtml(label) +
            '</h3>' +
            (micro.cabecera ? '<p class="microregiones-detail-cabecera">' + escapeHtml(micro.cabecera) + '</p>' : '') +
            '<p class="micro-muni-line">' +
            (municipio
                ? 'Municipio: <strong>' + escapeHtml(municipio.nombre) + '</strong>'
                : 'Microrregión seleccionada') +
            '</p>' +
            (searchResult && searchResult.label
                ? '<p class="microregiones-detail-place">Coincidencia: <strong>' + escapeHtml(searchResult.label) + '</strong></p>'
                : '');

        main.appendChild(headText);
        top.appendChild(main);

        if (micro.delegado && (micro.delegado.nombre || micro.delegado.telefono)) {
            aside.appendChild(contactBlock('Delegado a cargo', {
                nombre: micro.delegado.nombre,
                telefono: micro.delegado.telefono,
                email: null
            }));
        } else {
            aside.appendChild(emptyBlock('Delegado a cargo', 'Sin delegado registrado.'));
        }

        if (micro.enlace) {
            aside.appendChild(contactBlock('Enlace', { nombre: micro.enlace.nombre, email: null, telefono: '' }));
        } else {
            aside.appendChild(emptyBlock('Enlace', 'Sin usuario de enlace distinto al delegado.'));
        }

        top.appendChild(aside);
        card.appendChild(top);
        if (detailContentEl) detailContentEl.appendChild(card);
    }

    function contactBlock(title, d) {
        var wrap = document.createElement('div');
        wrap.className = 'microregiones-contact-block';
        var h = document.createElement('h4');
        h.textContent = title;
        var p = document.createElement('p');
        var parts = [];
        if (d.nombre) parts.push(escapeHtml(d.nombre.trim()));
        if (d.telefono) parts.push('Tel. ' + escapeHtml(d.telefono));
        if (d.email) {
            var a = document.createElement('a');
            a.href = 'mailto:' + encodeURIComponent(d.email);
            a.textContent = d.email;
            p.innerHTML = parts.length ? parts.join(' · ') + '<br>' : '';
            p.appendChild(a);
        } else {
            p.textContent = parts.join(' · ');
        }
        wrap.appendChild(h);
        wrap.appendChild(p);
        return wrap;
    }

    function emptyBlock(title, msg) {
        var wrap = document.createElement('div');
        wrap.className = 'microregiones-contact-block';
        wrap.innerHTML = '<h4>' + escapeHtml(title) + '</h4><p>' + escapeHtml(msg) + '</p>';
        return wrap;
    }

    function escapeHtml(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function normalizeText(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim();
    }

    function findMicroById(id) {
        return microById.get(id) || null;
    }

    function findMunicipioById(id) {
        for (var i = 0; i < flatMunicipios.length; i++) {
            if (flatMunicipios[i].id === id) {
                return flatMunicipios[i];
            }
        }
        return null;
    }

    function findMunicipioByName(name) {
        var normalized = normalizeText(name);
        for (var i = 0; i < flatMunicipios.length; i++) {
            if (normalizeText(flatMunicipios[i].nombre) === normalized) {
                return flatMunicipios[i];
            }
        }
        return null;
    }

    function syncSearchStatus() {
        if (!searchHint || !searchInput) {
            return;
        }
        var q = normalizeText(searchInput.value || '');
        if (!q || q.length < 3) {
            searchHint.hidden = true;
            return;
        }
        if (lastAccordionHadMatch) {
            searchHint.hidden = true;
            return;
        }
        if (remoteSearchLoading) {
            searchHint.hidden = false;
            searchHint.textContent = 'Buscando en el mapa…';
            return;
        }
        var qNow = normalizeText(searchInput.value || '');
        if (remoteSearchResults.length > 0 && qNow === lastRemoteSearchKey) {
            searchHint.hidden = true;
            return;
        }
        searchHint.hidden = false;
        searchHint.textContent =
            'Sin coincidencia en la lista de microrregiones. Use el desplegable arriba o pruebe “lugar, municipio” o “lugar (municipio)”.';
    }

    function clearSearchResults() {
        remoteSearchResults = [];
        lastRemoteSearchKey = '';
        if (searchResultsEl) {
            searchResultsEl.hidden = true;
            searchResultsEl.innerHTML = '';
        }
        syncSearchStatus();
    }

    function clearSearchArea() {
        if (searchAreaLayer && map) {
            map.removeLayer(searchAreaLayer);
            searchAreaLayer = null;
        }
    }

    function showAllAccordionItems() {
        if (!accordionEl) {
            return;
        }
        accordionEl.querySelectorAll('.microregiones-acc-item').forEach(function (item) {
            item.style.display = '';
        });
    }

    var OVERLAY_NO_HIT_PANE = 'microOverlayNoHit';

    function ensureMicroMapPanes() {
        if (!map) return;
        if (!map.getPane('boundaryPane')) {
            map.createPane('boundaryPane');
            map.getPane('boundaryPane').style.zIndex = 370;
        }
        if (!map.getPane(OVERLAY_NO_HIT_PANE)) {
            map.createPane(OVERLAY_NO_HIT_PANE);
            var noHit = map.getPane(OVERLAY_NO_HIT_PANE);
            noHit.style.zIndex = 450;
            noHit.style.pointerEvents = 'none';
        }
    }

    /* ── Map interaction ── */
    function openMunicipioOnMap(m) {
        showAllAccordionItems();
        clearSearchMarker();
        clearSearchArea();
        if (highlightedLayer && map) {
            map.removeLayer(highlightedLayer);
            highlightedLayer = null;
        }

        var micro = findMicroById(m.micro_id);
        var geom = geometryByMunicipioId.get(m.id);

        if (geom && map) {
            ensureMicroMapPanes();
            var color = micro ? getMicroColor(micro) : '#f59e0b';
            highlightedLayer = L.geoJSON({ type: 'Feature', geometry: geom }, {
                pane: OVERLAY_NO_HIT_PANE,
                style: {
                    color: '#ffffff',
                    weight: 2.5,
                    dashArray: '',
                    fillColor: color,
                    fillOpacity: 0.65
                },
                interactive: false
            }).addTo(map);

            map.fitBounds(highlightedLayer.getBounds(), { padding: [40, 40], maxZoom: 11, animate: true });
        } else {
            var mk = markerByMunicipioId.get(m.id);
            if (mk && map) {
                map.setView(mk.getLatLng(), Math.max(map.getZoom(), 11), { animate: true });
            }
        }

        if (micro) {
            renderDetail(micro, m);
        }
    }

    function openMicroOnMap(micro) {
        if (!micro || !map) return;
        showAllAccordionItems();
        clearSearchMarker();
        clearSearchArea();
        if (highlightedLayer && map) {
            map.removeLayer(highlightedLayer);
            highlightedLayer = null;
        }
        var mk = markerByMicroId.get(micro.id);
        if (mk) {
            map.panTo(mk.getLatLng(), { animate: true });
        }
        renderDetail(micro, null);
    }

    function clearSearchMarker() {
        if (searchMarker && map) {
            map.removeLayer(searchMarker);
            searchMarker = null;
        }
    }

    function placeSearchMarker(lat, lng) {
        if (!map) return;

        clearSearchMarker();

        ensureMicroMapPanes();
        searchMarker = L.circleMarker([lat, lng], {
            radius: 8,
            color: '#0f172a',
            weight: 2,
            fillColor: '#f97316',
            fillOpacity: 0.95,
            interactive: false,
            bubblingMouseEvents: true,
            pane: OVERLAY_NO_HIT_PANE,
        }).addTo(map);
    }

    function drawSearchArea(geometry) {
        if (!map || !geometry) return;

        clearSearchArea();

        ensureMicroMapPanes();

        searchAreaLayer = L.geoJSON({ type: 'Feature', geometry: geometry }, {
            pane: OVERLAY_NO_HIT_PANE,
            style: {
                color: '#f97316',
                weight: 3,
                dashArray: '10 8',
                fillColor: '#f97316',
                fillOpacity: 0.08
            },
            interactive: false
        }).addTo(map);
    }

    function dismissSearchDropdownAfterPick() {
        remoteSearchResults = [];
        lastRemoteSearchKey = '';
        if (searchResultsEl) {
            searchResultsEl.hidden = true;
            searchResultsEl.innerHTML = '';
        }
        if (searchHint) {
            searchHint.hidden = true;
        }
    }

    function cloneSearchResultForPin(result) {
        try {
            return JSON.parse(JSON.stringify(result));
        } catch (e) {
            return result;
        }
    }

    function syncMapResetBtnVisibility() {
        if (!searchGo) return;
        var hasQuery = !!(searchInput && String(searchInput.value || '').trim() !== '');
        searchGo.hidden = !(hasQuery || !!pinnedSearchResult);
    }

    function setPinnedSearchResult(result) {
        if (!result) return;
        pinnedSearchResult = cloneSearchResultForPin(result);
        if (pinnedBarEl) pinnedBarEl.hidden = false;
        if (pinnedBarLabelEl) {
            pinnedBarLabelEl.textContent =
                pinnedSearchResult.label || pinnedSearchResult.display_name || 'Coincidencia en mapa';
        }
        syncMapResetBtnVisibility();
    }

    function clearPinnedSearchResult() {
        pinnedSearchResult = null;
        clearSearchMarker();
        clearSearchArea();
        if (pinnedBarEl) pinnedBarEl.hidden = true;
        if (pinnedBarLabelEl) pinnedBarLabelEl.textContent = '';
        syncMapResetBtnVisibility();
    }

    function applyAdvancedResultToMapAndDetail(result) {
        if (!result) return;

        var municipio = result.municipio && result.municipio.id
            ? findMunicipioById(result.municipio.id)
            : findMunicipioByName(result.municipio ? result.municipio.nombre : '');
        var micro = result.micro && result.micro.id ? findMicroById(result.micro.id) : null;

        if (municipio && micro) {
            openMunicipioOnMap(municipio);
            renderDetail(micro, municipio, result);
        } else if (micro) {
            openMicroOnMap(micro);
            renderDetail(micro, null, result);
        }

        if (map && typeof result.lat === 'number' && typeof result.lng === 'number') {
            placeSearchMarker(result.lat, result.lng);
            if (result.geometry) {
                drawSearchArea(result.geometry);
                if (searchAreaLayer) {
                    map.fitBounds(searchAreaLayer.getBounds(), { padding: [34, 34], maxZoom: 14, animate: true });
                }
            } else {
                map.setView([result.lat, result.lng], Math.max(map.getZoom(), 14), { animate: true });
            }
        }
    }

    function refocusPinnedSearch() {
        if (!pinnedSearchResult || !map) return;
        applyAdvancedResultToMapAndDetail(pinnedSearchResult);
    }

    function openAdvancedResult(result) {
        if (!result) return;

        dismissSearchDropdownAfterPick();
        applyAdvancedResultToMapAndDetail(result);
        setPinnedSearchResult(result);
    }

    function placeholderMrSvg(num) {
        var label = 'MR' + String(num);
        return (
            'data:image/svg+xml,' +
            encodeURIComponent(
                '<svg xmlns="http://www.w3.org/2000/svg" width="56" height="56"><rect width="56" height="56" rx="12" fill="%231e293b"/><text x="28" y="34" text-anchor="middle" fill="%23e2e8f0" font-size="14" font-family="system-ui,sans-serif" font-weight="600">' +
                label.replace(/&/g, '&amp;').replace(/</g, '&lt;') +
                '</text></svg>'
            )
        );
    }

    /* ── Accordion (uses DocumentFragment for batch DOM insert) ── */
    function buildAccordion() {
        var fragment = document.createDocumentFragment();

        micros.forEach(function (micro) {
            var det = document.createElement('details');
            det.className = 'microregiones-acc-item';
            var sum = document.createElement('summary');
            sum.className = 'microregiones-acc-summary';

            var img = document.createElement('img');
            img.className = 'microregiones-acc-thumb';
            img.alt = micro.micro_label || 'MR ' + micro.numero;
            img.width = 56;
            img.height = 56;
            if (micro.image_url) {
                img.src = micro.image_url;
            } else {
                img.src = placeholderMrSvg(micro.numero);
            }
            img.onerror = function () {
                img.onerror = null;
                img.src = placeholderMrSvg(micro.numero);
            };

            var title = document.createElement('span');
            title.className = 'microregiones-acc-title';
            var lbl = micro.micro_label || 'MR ' + micro.numero;
            title.textContent = lbl + (micro.cabecera ? ' — ' + micro.cabecera : '');

            sum.appendChild(img);
            sum.appendChild(title);

            sum.addEventListener('click', function (e) {
                openMicroOnMap(micro);
            });

            var body = document.createElement('div');
            body.className = 'microregiones-acc-body';
            var info = document.createElement('p');
            info.style.cssText = 'margin-top:0;opacity:.85;font-size:.8rem';
            info.textContent = (micro.delegado && micro.delegado.nombre)
                ? 'Delegado: ' + micro.delegado.nombre
                : 'Sin delegado registrado.';
            body.appendChild(info);

            micro.municipios.forEach(function (mun) {
                var row = document.createElement('div');
                row.className = 'microregiones-acc-muni';
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = mun.nombre;
                btn.addEventListener('click', function () {
                    openMunicipioOnMap(mun);
                });
                row.appendChild(btn);
                body.appendChild(row);
            });

            det.appendChild(sum);
            det.appendChild(body);
            fragment.appendChild(det);
        });

        accordionEl.innerHTML = '';
        accordionEl.appendChild(fragment); // Single DOM write
    }

    function renderRemoteSearchResults(results) {
        if (!searchResultsEl) return;

        searchResultsEl.innerHTML = '';
        // Limit to max 5 results as requested
        remoteSearchResults = (results || []).slice(0, 5);

        if (!remoteSearchResults.length) {
            searchResultsEl.hidden = true;
            syncSearchStatus();
            return;
        }

        searchResultsEl.hidden = false;

        var fragment = document.createDocumentFragment();

        remoteSearchResults.forEach(function (result, idx) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'microregiones-search-result';
            btn.setAttribute('data-index', String(idx));
            btn.innerHTML =
                '<span class="microregiones-search-result-label">' + escapeHtml(result.label || result.display_name || 'Resultado') + '</span>' +
                '<span class="microregiones-search-result-meta">' +
                escapeHtml((result.micro ? result.micro.micro_label : '') || '') +
                (result.municipio && result.municipio.nombre ? ' · ' + escapeHtml(result.municipio.nombre) : '') +
                '</span>';
            btn.addEventListener('click', function () {
                openAdvancedResult(result);
            });
            fragment.appendChild(btn);
        });

        searchResultsEl.appendChild(fragment);
        searchResultsEl.hidden = false;
        syncSearchStatus();
    }

    function queueAdvancedSearch() {
        if (!searchInput) return;

        var rawQuery = searchInput.value || '';
        var query = rawQuery.trim();

        if (remoteSearchDebounce) {
            clearTimeout(remoteSearchDebounce);
            remoteSearchDebounce = null;
        }

        if (remoteSearchAbortController) {
            remoteSearchAbortController.abort();
            remoteSearchAbortController = null;
        }

        if (query.length < 3) {
            remoteSearchLoading = false;
            clearSearchResults();
            return;
        }

        var searchKeyNorm = normalizeText(query);

        remoteSearchDebounce = setTimeout(function () {
            remoteSearchAbortController = new AbortController();
            remoteSearchLoading = true;
            remoteSearchResults = [];
            lastRemoteSearchKey = '';
            if (searchResultsEl) {
                searchResultsEl.hidden = true;
                searchResultsEl.innerHTML = '';
            }
            syncSearchStatus();

            function fetchSearchPostJson() {
                var postUrl = searchPostUrl;
                if (!postUrl || searchPostDisabled) {
                    return Promise.reject(new Error('no search post url'));
                }
                var csrfMeta = document.querySelector('meta[name="csrf-token"]');
                var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';
                return fetch(sameOriginFetchUrl(postUrl), {
                    method: 'POST',
                    credentials: 'same-origin',
                    signal: remoteSearchAbortController.signal,
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({ q: query }),
                }).then(function (r) {
                    if (r.status === 404 || r.status === 405) {
                        searchPostDisabled = true;
                    }
                    if (!r.ok) throw new Error('Advanced search POST failed');
                    return r.json();
                });
            }

            function fetchSearchAt(index) {
                if (index >= searchUrls.length) {
                    return fetchSearchPostJson();
                }

                var baseUrl = sameOriginFetchUrl(searchUrls[index]);
                var queryJoiner = baseUrl.indexOf('?') === -1 ? '?' : '&';

                return fetch(baseUrl + queryJoiner + 'q=' + encodeURIComponent(query), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    signal: remoteSearchAbortController.signal,
                }).then(function (r) {
                    if (r.status === 404) {
                        return fetchSearchAt(index + 1);
                    }
                    if (!r.ok) throw new Error('Advanced search failed');
                    return r.json();
                });
            }

            fetchSearchAt(0)
                .then(function (payload) {
                    if (normalizeText(searchInput.value || '') !== searchKeyNorm) {
                        return;
                    }
                    var results = payload && Array.isArray(payload.results) ? payload.results : [];
                    lastRemoteSearchKey = searchKeyNorm;
                    renderRemoteSearchResults(results);
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') return;
                    lastRemoteSearchKey = '';
                    clearSearchResults();
                })
                .finally(function () {
                    remoteSearchAbortController = null;
                    remoteSearchLoading = false;
                    syncSearchStatus();
                });
        }, 260);
    }

    function runSearch() {
        var q = normalizeText(searchInput && searchInput.value ? searchInput.value : '');

        if (!q) {
            clearSearchFilteringOnly();
            return;
        }

        var accItems = accordionEl.querySelectorAll('.microregiones-acc-item');
        var anyFound = false;

        micros.forEach(function (micro, idx) {
            var item = accItems[idx];
            if (!item) return;

            var matchLabel = normalizeText(micro.micro_label || '');
            var matchNum = 'mr' + micro.numero;
            var matchNum2 = 'mr ' + micro.numero;
            var matchCab = normalizeText(micro.cabecera || '');
            var matchMuns = micro.municipios.some(function (m) {
                return normalizeText(m.nombre).indexOf(q) !== -1;
            });

            var isMatch = matchLabel.indexOf(q) !== -1 ||
                String(micro.numero) === q ||
                matchNum.indexOf(q) !== -1 ||
                matchNum2.indexOf(q) !== -1 ||
                matchCab.indexOf(q) !== -1 ||
                matchMuns;

            if (isMatch) {
                item.style.display = '';
                anyFound = true;
            } else {
                item.style.display = 'none';
            }
        });

        lastAccordionHadMatch = anyFound;
        syncSearchStatus();
    }

    /** Solo quita filtro de búsqueda y capas de resultado Nominatim; no aleja el mapa ni cierra el detalle. */
    function clearSearchFilteringOnly() {
        if (searchHint) searchHint.hidden = true;
        lastAccordionHadMatch = false;
        remoteSearchLoading = false;
        clearSearchResults();
        if (!pinnedSearchResult) {
            clearSearchMarker();
            clearSearchArea();
        }
        showAllAccordionItems();
        syncSearchStatus();
        syncMapResetBtnVisibility();
    }

    /** Vista completa del estado: limpia input, resaltado, detalle y encuadre a Puebla. */
    function resetMapToPueblaView() {
        if (searchInput) searchInput.value = '';
        if (searchHint) searchHint.hidden = true;
        lastAccordionHadMatch = false;
        remoteSearchLoading = false;
        clearSearchResults();
        clearPinnedSearchResult();

        var accItems = accordionEl.querySelectorAll('.microregiones-acc-item');
        accItems.forEach(function (item) {
            item.style.display = '';
            item.open = false;
        });

        if (detailEl) detailEl.classList.add('microregiones-floating-detail--hidden');

        if (highlightedLayer && map) {
            map.removeLayer(highlightedLayer);
            highlightedLayer = null;
        }

        if (map) {
            var b = getPueblaLatLngBounds(null);
            map.fitBounds(b, { padding: [28, 28], maxZoom: 9, animate: true });
        }
        syncSearchStatus();
        syncMapResetBtnVisibility();
    }

    /* ── Boundary rendering (chunked via rAF to avoid freezing) ── */
    function applyBoundaries(payload) {
        if (!map || !payload || !payload.municipios) return;

        ensureMicroMapPanes();

        var turfLib = typeof window !== 'undefined' ? window.turf : null;

        // Index geometries and group by microregion
        var byMicro = {};
        payload.municipios.forEach(function (item) {
            // Simplify heavy geometries before storing
            item.geometry = simplifyGeometry(item.geometry, 0.001);
            geometryByMunicipioId.set(item.id, item.geometry);
            if (!byMicro[item.micro_id]) byMicro[item.micro_id] = [];
            byMicro[item.micro_id].push(item);
        });

        var microKeys = Object.keys(byMicro);
        var chunkIdx = 0;
        var CHUNK_SIZE = 3; // Process 3 microregions per animation frame

        function processChunk() {
            var end = Math.min(chunkIdx + CHUNK_SIZE, microKeys.length);

            for (var k = chunkIdx; k < end; k++) {
                var mId = microKeys[k];
                var arr = byMicro[mId];
                var microId = parseInt(mId, 10);
                var micro = findMicroById(microId);
                var color = micro ? getMicroColor(micro) : '#94a3b8';

                // Batch all municipality features into a single GeoJSON layer
                var features = arr.map(function (item) {
                    return { type: 'Feature', geometry: item.geometry, properties: { munId: item.id, microId: item.micro_id } };
                });

                var boundaryColor = darkenColor(color, 0.4);

                var batchLayer = L.geoJSON({ type: 'FeatureCollection', features: features }, {
                    pane: 'boundaryPane',
                    style: {
                        color: boundaryColor,
                        weight: 0.8,
                        opacity: 0.6,
                        fillColor: color,
                        fillOpacity: 0.15,
                    },
                    interactive: true,
                    onEachFeature: function (feature, layer) {
                        var item = null;
                        for (var x = 0; x < arr.length; x++) {
                            if (arr[x].id === feature.properties.munId) { item = arr[x]; break; }
                        }
                        if (item) {
                            layer.on('click', function (e) {
                                L.DomEvent.stopPropagation(e);
                                openMunicipioOnMap(item);
                            });
                        }
                    }
                }).addTo(map);

                // Unified outline (turf union) — only if turf is available
                if (turfLib && turfLib.union && arr.length > 0) {
                    try {
                        var unifiedGeo = { type: 'Feature', geometry: arr[0].geometry, properties: {} };
                        for (var i = 1; i < arr.length; i++) {
                            unifiedGeo = turfLib.union(unifiedGeo, { type: 'Feature', geometry: arr[i].geometry, properties: {} });
                        }
                        L.geoJSON(unifiedGeo, {
                            pane: 'boundaryPane',
                            style: { color: darkenColor(color, 0.6), weight: 2.5, dashArray: '', fillOpacity: 0 },
                            interactive: false
                        }).addTo(map);
                    } catch (e) {
                        console.error('Error al unir geometrías para MR', mId, e);
                    }
                }
            }

            chunkIdx = end;
            if (chunkIdx < microKeys.length) {
                requestAnimationFrame(processChunk);
            }
        }

        // Start chunked processing on next frame
        requestAnimationFrame(processChunk);

        // After all chunks are done, reposition pins to polygon centroids
        function repositionPins() {
            if (chunkIdx < microKeys.length) {
                requestAnimationFrame(repositionPins);
                return;
            }
            var turfCentroid = turfLib && turfLib.centroid;
            if (!turfCentroid) return;

            microKeys.forEach(function (mId) {
                var arr = byMicro[mId];
                var microId = parseInt(mId, 10);
                var pin = markerByMicroId.get(microId);
                if (!pin || arr.length === 0) return;

                try {
                    // Build the unified geometry for centroid calculation
                    var unified = { type: 'Feature', geometry: arr[0].geometry, properties: {} };
                    for (var i = 1; i < arr.length; i++) {
                        unified = turfLib.union(unified, { type: 'Feature', geometry: arr[i].geometry, properties: {} });
                    }
                    var center = turfCentroid(unified);
                    if (center && center.geometry && center.geometry.coordinates) {
                        var lng = center.geometry.coordinates[0];
                        var lat = center.geometry.coordinates[1];
                        pin.setLatLng([lat, lng]);
                    }
                } catch (e) {
                    // Keep original position on error
                }
            });
        }
        requestAnimationFrame(repositionPins);
    }

    /* ── Puebla state boundary (cached in localStorage) ── */
    function loadPueblaBoundary() {
        if (!map) return;

        var CACHE_KEY = 'puebla_state_boundary_v1';
        var cached = null;

        try {
            var raw = localStorage.getItem(CACHE_KEY);
            if (raw) cached = JSON.parse(raw);
        } catch (e) { /* ignore */ }

        if (cached) {
            drawPueblaBoundary(cached);
            return;
        }

        fetch('https://nominatim.openstreetmap.org/search?state=Puebla&country=Mexico&polygon_geojson=1&format=json')
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.length > 0) {
                    var geojson = data[0].geojson;
                    try { localStorage.setItem(CACHE_KEY, JSON.stringify(geojson)); } catch (e) { /* quota */ }
                    drawPueblaBoundary(geojson);
                }
            })
            .catch(function (err) { console.error('Error fetch límite Puebla:', err); });
    }

    function drawPueblaBoundary(geojson) {
        if (!map) return;
        map.createPane('pueblaBoundaryPane');
        map.getPane('pueblaBoundaryPane').style.zIndex = 360;
        L.geoJSON(geojson, {
            pane: 'pueblaBoundaryPane',
            style: {
                color: '#0f172a',
                weight: 3,
                dashArray: '10, 8',
                fillOpacity: 0.08,
                fillColor: '#475569'
            },
            interactive: false
        }).addTo(map);
    }

    /* ── Map bounds helper ── */
    function getPueblaLatLngBounds(payload) {
        var b = payload && payload.map && payload.map.puebla_bounds;
        if (b && typeof b.south === 'number' && typeof b.north === 'number' && typeof b.west === 'number' && typeof b.east === 'number') {
            return L.latLngBounds(L.latLng(b.south, b.west), L.latLng(b.north, b.east));
        }
        return L.latLngBounds(L.latLng(17.862, -98.765), L.latLng(20.895, -96.708));
    }

    /* ── Map init (Canvas renderer for performance) ── */
    function initMap(payload) {
        // Use Canvas renderer for much better performance with many polygons
        var canvasRenderer = L.canvas({ padding: 0.5 });

        map = L.map(mapEl, {
            zoomControl: true,
            renderer: canvasRenderer,
            preferCanvas: true
        }).setView([19.05, -97.95], 8);

        ensureMicroMapPanes();

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(map);

        markerByMunicipioId.clear();
        flatMunicipios = [];

        micros.forEach(function (micro) {
            var color = getMicroColor(micro);
            var sumLat = 0, sumLng = 0, count = 0;

            var validMuns = micro.municipios.filter(function (m) {
                return m.geo_source !== 'fallback';
            });
            if (validMuns.length === 0) validMuns = micro.municipios;

            micro.municipios.forEach(function (mun) { flatMunicipios.push(mun); });
            validMuns.forEach(function (mun) { sumLat += mun.lat; sumLng += mun.lng; count++; });

            if (count > 0) {
                var avgLat = sumLat / count;
                var avgLng = sumLng / count;

                var html = '<div class="microregiones-map-pin" style="background-color: ' + color + ';">' +
                    '<span>' + micro.numero + '</span>' +
                    '</div>';

                var customIcon = L.divIcon({
                    html: html,
                    className: '',
                    iconSize: [28, 28],
                    iconAnchor: [14, 38]
                });

                var marker = L.marker([avgLat, avgLng], { icon: customIcon }).addTo(map);

                marker.on('click', function (e) {
                    L.DomEvent.stopPropagation(e);
                    openMicroOnMap(micro);
                });

                markerByMicroId.set(micro.id, marker);
                micro.municipios.forEach(function (mun) {
                    markerByMunicipioId.set(mun.id, marker);
                });
            }
        });

        try {
            var stateBounds = getPueblaLatLngBounds(payload);
            map.fitBounds(stateBounds, { padding: [28, 28], maxZoom: 9 });
        } catch (e) { /* ignore */ }
    }

    /* ── Bootstrap (prioridad: JSON en la página; respaldo: fetch) ── */
    function tryReadMicroregionesBootstrap() {
        var el = document.getElementById('microregionesMapBootstrap');
        if (!el || !el.textContent) return null;
        try {
            var p = JSON.parse(el.textContent);
            if (p && Array.isArray(p.microrregiones)) {
                return p;
            }
        } catch (e) { /* ignore */ }
        return null;
    }

    function loadMicroregionesPayload() {
        var fromPage = tryReadMicroregionesBootstrap();
        if (fromPage) {
            return Promise.resolve(fromPage);
        }
        return fetch(sameOriginFetchUrl(dataUrl), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        }).then(function (r) {
            if (!r.ok) throw new Error('No se pudieron cargar los datos.');
            return r.json();
        });
    }

    loadMicroregionesPayload()
        .then(function (payload) {
            if (payload.search_urls && Array.isArray(payload.search_urls) && payload.search_urls.length) {
                searchUrls = [];
                payload.search_urls.forEach(function (u) {
                    if (u && typeof u === 'string') pushUniqueUrl(searchUrls, u);
                });
            }
            if (payload.search_url) {
                searchUrl = payload.search_url;
                pushUniqueUrl(searchUrls, payload.search_url);
            }
            pushUniqueUrl(searchUrls, window.location.pathname || '/microregiones');
            pushUniqueUrl(searchUrls, '/microregiones');
            if (payload.search_post_url) {
                searchPostUrl = payload.search_post_url;
            }
            appendIndexPhpSearchVariantsIfNeeded();
            micros = payload.microrregiones || [];

            // Build fast lookup map
            micros.forEach(function (m) { microById.set(m.id, m); });

            buildAccordion();
            initMap(payload);

            // Load Puebla state outline (cached in localStorage)
            loadPueblaBoundary();

            // Prefer embedded boundaries from bootstrap to avoid failures when endpoints are blocked.
            if (payload.boundaries_bootstrap && Array.isArray(payload.boundaries_bootstrap.municipios)) {
                if (payload.boundaries_bootstrap.municipios.length > 0) {
                    applyBoundaries(payload.boundaries_bootstrap);
                    return;
                }
            }

            // Límites municipales (varias URLs por si el hosting bloquea una ruta)
            var boundaryUrls = [];
            if (payload.boundaries_urls && payload.boundaries_urls.length) {
                payload.boundaries_urls.forEach(function (u) {
                    if (u && boundaryUrls.indexOf(u) === -1) boundaryUrls.push(u);
                });
            } else if (payload.boundaries_url) {
                boundaryUrls.push(payload.boundaries_url);
            }

            function fetchBoundariesAt(index) {
                if (index >= boundaryUrls.length) {
                    return Promise.resolve(null);
                }
                return fetch(sameOriginFetchUrl(boundaryUrls[index]), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                }).then(function (r) {
                    if (r.ok) return r.json();
                    return fetchBoundariesAt(index + 1);
                });
            }

            if (boundaryUrls.length) {
                fetchBoundariesAt(0).then(function (bp) {
                    if (bp) applyBoundaries(bp);
                });
            }
        })
        .catch(function () {
            accordionEl.innerHTML = '<p class="microregiones-detail-placeholder">Error al cargar microrregiones. Intenta de nuevo más tarde.</p>';
        });

    /* ── Event listeners ── */
    if (detailCloseBtn) {
        detailCloseBtn.addEventListener('click', function () {
            detailEl.classList.add('microregiones-floating-detail--hidden');
        });
    }

    if (searchGo) {
        searchGo.addEventListener('click', resetMapToPueblaView);
    }

    if (pinnedClearBtn) {
        pinnedClearBtn.addEventListener('mousedown', function (e) {
            e.preventDefault();
        });
        pinnedClearBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            clearPinnedSearchResult();
        });
    }
    if (pinnedFocusBtn) {
        pinnedFocusBtn.addEventListener('click', function () {
            refocusPinnedSearch();
        });
    }
    if (searchInput) {
        var searchLabel = document.querySelector('.microregiones-search-label[for="microregionesSearchInput"]');
        if (searchLabel) {
            searchLabel.textContent = 'Buscar municipio, calle, colonia, junta auxiliar o microrregion';
        }
        searchInput.placeholder = 'Ej. Los Encinos, Huejotzingo · Av. Juárez, Puebla · MR3';
        if (searchResultsEl) {
            searchResultsEl.addEventListener('mousedown', function (e) {
                e.preventDefault();
            });
        }
        searchInput.addEventListener('input', function () {
            runSearch();
            queueAdvancedSearch();
            syncMapResetBtnVisibility();
        });
        syncMapResetBtnVisibility();

        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (remoteSearchResults.length > 0) {
                    openAdvancedResult(remoteSearchResults[0]);
                }
            }
        });
        searchInput.addEventListener('blur', function () {
            setTimeout(function () {
                if (searchResultsEl) {
                    searchResultsEl.hidden = true;
                }
            }, 380);
        });
        searchInput.addEventListener('focus', function () {
            if (searchResultsEl && remoteSearchResults.length > 0) {
                searchResultsEl.hidden = false;
            }
        });
    }

    if (toggleBtn && layoutEl) {
        var toggleText = toggleBtn.querySelector('.microregiones-sidebar-toggle-text');
        toggleBtn.addEventListener('click', function () {
            if (isMicroMobileViewport()) {
                return;
            }
            var collapsed = layoutEl.classList.toggle('sidebar-collapsed');
            toggleBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            if (toggleText) {
                toggleText.textContent = collapsed ? 'Mostrar panel' : 'Ocultar panel';
            }
            if (map) {
                setTimeout(function () {
                    map.invalidateSize();
                }, 280);
            }
        });
    }

    if (mobileDrawerTab && sidebarEl) {
        mobileDrawerTab.addEventListener('click', function () {
            if (!isMicroMobileViewport()) {
                return;
            }
            if (sidebarEl.classList.contains('is-open')) {
                closeMicroMobileDrawer();
            } else {
                openMicroMobileDrawer();
            }
        });
    }

    if (mobileBackdrop) {
        mobileBackdrop.addEventListener('click', function () {
            closeMicroMobileDrawer();
        });
    }

    document.addEventListener(
        'pointerdown',
        function (e) {
            if (!isMicroMobileViewport() || !sidebarEl || !sidebarEl.classList.contains('is-open')) {
                return;
            }
            if (sidebarEl.contains(e.target)) {
                return;
            }
            if (mobileDrawerTab && mobileDrawerTab.contains(e.target)) {
                return;
            }
            closeMicroMobileDrawer();
        },
        true
    );

    if (sidebarMobileClose) {
        sidebarMobileClose.addEventListener('click', function () {
            closeMicroMobileDrawer();
        });
    }

    if (microMobileMq.addEventListener) {
        microMobileMq.addEventListener('change', syncMicroMobileOnViewportChange);
    } else if (microMobileMq.addListener) {
        microMobileMq.addListener(syncMicroMobileOnViewportChange);
    }

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape' || !isMicroMobileViewport()) {
            return;
        }
        if (sidebarEl && sidebarEl.classList.contains('is-open')) {
            closeMicroMobileDrawer();
        }
    });

})();
