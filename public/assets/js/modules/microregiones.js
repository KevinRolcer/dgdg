(function () {
    'use strict';

    const root = document.getElementById('microregionesRoot');
    if (!root || typeof L === 'undefined') {
        return;
    }

    const dataUrl = root.getAttribute('data-data-url');
    const mapEl = document.getElementById('microregionesMap');
    const accordionEl = document.getElementById('microregionesAccordion');
    const detailEl = document.getElementById('microregionesDetail');
    const detailContentEl = document.getElementById('microregionesDetailContent');
    const detailCloseBtn = document.getElementById('microregionesDetailClose');
    const searchInput = document.getElementById('microregionesSearchInput');
    const searchGo = document.getElementById('microregionesSearchGo');
    const searchHint = document.getElementById('microregionesSearchHint');
    const layoutEl = document.querySelector('.microregiones-layout');
    const toggleBtn = document.getElementById('microregionesToggleSidebar');

    /** @type {L.Map|null} */
    let map = null;
    const markerByMunicipioId = new Map();
    const markerByMicroId = new Map(); // Pin marker per microregion
    const geometryByMunicipioId = new Map();
    const microById = new Map(); // Fast lookup instead of Array.find
    let highlightedLayer = null;
    let micros = [];
    let flatMunicipios = [];

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

    /* ── Color helpers ── */
    function hueForMicro(id) {
        return (id * 47) % 360;
    }

    function hslColor(id, alpha) {
        var hue = hueForMicro(id);
        return alpha !== undefined
            ? 'hsla(' + hue + ', 62%, 48%, ' + alpha + ')'
            : 'hsl(' + hue + ', 62%, 48%)';
    }

    /* ── Detail panel ── */
    function renderDetail(micro, municipio) {
        detailEl.classList.remove('microregiones-floating-detail--hidden');
        if (detailContentEl) detailContentEl.innerHTML = '';

        var card = document.createElement('div');
        card.className = 'microregiones-detail-card';

        var head = document.createElement('div');
        head.className = 'microregiones-detail-card-head';

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
        head.appendChild(img);

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
            '</p>';

        head.appendChild(headText);
        card.appendChild(head);

        var contact = document.createElement('div');
        contact.className = 'microregiones-contact';

        if (micro.delegado && (micro.delegado.nombre || micro.delegado.telefono)) {
            contact.appendChild(contactBlock('Delegado a cargo', { nombre: micro.delegado.nombre, telefono: micro.delegado.telefono, email: null }));
        } else {
            contact.appendChild(emptyBlock('Delegado a cargo', 'Sin delegado registrado.'));
        }

        if (micro.enlace) {
            contact.appendChild(contactBlock('Enlace', { nombre: micro.enlace.nombre, email: null, telefono: '' }));
        } else {
            contact.appendChild(emptyBlock('Enlace', 'Sin usuario de enlace distinto al delegado.'));
        }

        card.appendChild(contact);
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

    function findMicroById(id) {
        return microById.get(id) || null;
    }

    /* ── Map interaction ── */
    function openMunicipioOnMap(m) {
        if (highlightedLayer && map) {
            map.removeLayer(highlightedLayer);
            highlightedLayer = null;
        }

        var micro = findMicroById(m.micro_id);
        var geom = geometryByMunicipioId.get(m.id);

        if (geom && map) {
            var color = micro ? hslColor(micro.id) : '#f59e0b';
            highlightedLayer = L.geoJSON({ type: 'Feature', geometry: geom }, {
                pane: 'boundaryPane',
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
        var mk = markerByMicroId.get(micro.id);
        if (mk) {
            map.panTo(mk.getLatLng(), { animate: true });
        }
        renderDetail(micro, null);
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

            sum.addEventListener('click', function(e) {
                // Focus map on this microregion
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

    function runSearch() {
        var q = (searchInput && searchInput.value ? searchInput.value : '').trim().toLowerCase();
        searchHint.hidden = true;

        if (!q) {
            clearSearch();
            return;
        }

        var accItems = accordionEl.querySelectorAll('.microregiones-acc-item');
        var anyFound = false;

        micros.forEach(function (micro, idx) {
            var item = accItems[idx];
            if (!item) return;

            var matchLabel = (micro.micro_label || '').toLowerCase();
            var matchNum = 'mr' + micro.numero;
            var matchNum2 = 'mr ' + micro.numero;
            var matchCab = (micro.cabecera || '').toLowerCase();
            var matchMuns = micro.municipios.some(function(m) {
                return m.nombre.toLowerCase().indexOf(q) !== -1;
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

        if (!anyFound) {
            searchHint.hidden = false;
            searchHint.textContent = 'No se encontraron resultados.';
        }
    }

    function clearSearch() {
        if (searchInput) searchInput.value = '';
        if (searchHint) searchHint.hidden = true;

        var accItems = accordionEl.querySelectorAll('.microregiones-acc-item');
        accItems.forEach(function (item) {
            item.style.display = '';
            item.open = false;
        });

        // Hide detail card
        if (detailEl) detailEl.classList.add('microregiones-floating-detail--hidden');

        // Clear map highlight
        if (highlightedLayer && map) {
            map.removeLayer(highlightedLayer);
            highlightedLayer = null;
        }

        // Reset map view to Puebla
        if (map) {
            var b = getPueblaLatLngBounds(null); // Passing null to get defaults or check if needs payload
            map.fitBounds(b, { padding: [28, 28], maxZoom: 9, animate: true });
        }
    }

    /* ── Boundary rendering (chunked via rAF to avoid freezing) ── */
    function applyBoundaries(payload) {
        if (!map || !payload || !payload.municipios) return;

        map.createPane('boundaryPane');
        map.getPane('boundaryPane').style.zIndex = 370;

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
                var color = micro ? hslColor(micro.id) : 'hsl(160, 62%, 48%)';

                // Batch all municipality features into a single GeoJSON layer
                var features = arr.map(function (item) {
                    return { type: 'Feature', geometry: item.geometry, properties: { munId: item.id, microId: item.micro_id } };
                });

                var batchLayer = L.geoJSON({ type: 'FeatureCollection', features: features }, {
                    pane: 'boundaryPane',
                    style: {
                        color: color,
                        weight: 0.6,
                        opacity: 0.5,
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
                            style: { color: color, weight: 1.5, dashArray: '', fillOpacity: 0 },
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

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(map);

        markerByMunicipioId.clear();
        flatMunicipios = [];

        micros.forEach(function (micro) {
            var hue = hueForMicro(micro.id);
            var color = 'hsl(' + hue + ' 62% 48%)';
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

                marker.on('click', function () {
                    renderDetail(micro, null);
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

    /* ── Bootstrap ── */
    fetch(dataUrl, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    })
        .then(function (r) {
            if (!r.ok) throw new Error('No se pudieron cargar los datos.');
            return r.json();
        })
        .then(function (payload) {
            micros = payload.microrregiones || [];

            // Build fast lookup map
            micros.forEach(function (m) { microById.set(m.id, m); });

            buildAccordion();
            initMap(payload);

            // Load Puebla state outline (cached in localStorage)
            loadPueblaBoundary();

            // Load municipality boundaries (chunked rendering)
            var bUrl = payload.boundaries_url;
            if (bUrl) {
                fetch(bUrl, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (bp) { if (bp) applyBoundaries(bp); });
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
        searchGo.addEventListener('click', clearSearch);
    }
    if (searchInput) {
        searchInput.addEventListener('input', runSearch);
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });
    }

    if (toggleBtn && layoutEl) {
        var toggleText = toggleBtn.querySelector('.microregiones-sidebar-toggle-text');
        toggleBtn.addEventListener('click', function () {
            var collapsed = layoutEl.classList.toggle('sidebar-collapsed');
            toggleBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            if (toggleText) {
                toggleText.textContent = collapsed ? 'Mostrar panel' : 'Ocultar panel';
            }
            if (map) {
                setTimeout(function () { map.invalidateSize(); }, 280);
            }
        });
    }
})();
