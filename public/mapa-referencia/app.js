/* global L, leafletImage, MAPA_CONFIG */

function setStatus(message) {
  const el = document.getElementById("status");
  if (!el) return;
  el.textContent = message || "";
}

function normalizePoint(rawPoint) {
  const point = { ...rawPoint };
  if (
    (point.lat == null || point.lng == null) &&
    typeof point.googleUrl === "string" &&
    point.googleUrl.trim()
  ) {
    const parsed = extractLatLngFromGoogleUrl(point.googleUrl.trim());
    if (parsed) {
      point.lat = parsed.lat;
      point.lng = parsed.lng;
    }
  }
  return point;
}

function extractLatLngFromGoogleUrl(url) {
  // Casos comunes:
  // - .../@19.123,-98.456,15z
  // - ...!3d19.123!4d-98.456
  const atMatch = url.match(/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/);
  if (atMatch) return { lat: Number(atMatch[1]), lng: Number(atMatch[2]) };

  const bangMatch = url.match(/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/);
  if (bangMatch) return { lat: Number(bangMatch[1]), lng: Number(bangMatch[2]) };

  const queryMatch = url.match(/[?&]q=(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/);
  if (queryMatch) return { lat: Number(queryMatch[1]), lng: Number(queryMatch[2]) };

  return null;
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function roundRect(ctx, x, y, w, h, r) {
  const radius = Math.max(0, Math.min(r, Math.min(w, h) / 2));
  ctx.beginPath();
  ctx.moveTo(x + radius, y);
  ctx.arcTo(x + w, y, x + w, y + h, radius);
  ctx.arcTo(x + w, y + h, x, y + h, radius);
  ctx.arcTo(x, y + h, x, y, radius);
  ctx.arcTo(x, y, x + w, y, radius);
  ctx.closePath();
}

function drawPin(ctx, x, y, color) {
  // x,y: punta inferior del pin
  const w = 18;
  const h = 26;
  const cx = x;
  const topY = y - h;

  ctx.save();
  ctx.fillStyle = color;
  ctx.shadowColor = "rgba(0,0,0,0.25)";
  ctx.shadowBlur = 10;
  ctx.shadowOffsetY = 6;

  ctx.beginPath();
  ctx.moveTo(cx, y);
  ctx.bezierCurveTo(cx - w, y - h * 0.35, cx - w, topY, cx, topY);
  ctx.bezierCurveTo(cx + w, topY, cx + w, y - h * 0.35, cx, y);
  ctx.closePath();
  ctx.fill();

  // Circulito interior
  ctx.shadowBlur = 0;
  ctx.shadowOffsetY = 0;
  ctx.fillStyle = "rgba(255,255,255,0.92)";
  ctx.beginPath();
  ctx.arc(cx, topY + h * 0.42, 6.5, 0, Math.PI * 2);
  ctx.fill();

  ctx.restore();
}

function drawLabel(ctx, x, y, num, text, options) {
  const paddingX = 0;
  const gap = 8;
  const badge = 18;
  const font =
    (options && options.font) ||
    "800 14px system-ui, -apple-system, Segoe UI, Roboto, Arial";
  const smallFont =
    (options && options.smallFont) ||
    "900 12px system-ui, -apple-system, Segoe UI, Roboto, Arial";
  const ink = (options && options.ink) || "rgba(25,25,25,0.95)";
  const muted = (options && options.muted) || "#b08a2e";

  ctx.save();
  ctx.font = font;
  const textW = Math.ceil(ctx.measureText(text).width);
  const h = 24;
  const w = paddingX + badge + gap + textW + paddingX;

  // Anchor centrado
  let left = x - w / 2;
  let top = y - h / 2;

  const margin = 10;
  if (options && Array.isArray(options.clampSize)) {
    const [cw, ch] = options.clampSize;
    left = Math.max(margin, Math.min(left, cw - w - margin));
    top = Math.max(margin, Math.min(top, ch - h - margin));
  }

  // Badge num (mantiene círculo blanco)
  const bx = left;
  const by = top + (h - badge) / 2;
  ctx.fillStyle = "rgba(255,255,255,0.98)";
  ctx.strokeStyle = "rgba(0,0,0,0.18)";
  ctx.lineWidth = 2;
  ctx.beginPath();
  ctx.arc(bx + badge / 2, by + badge / 2, badge / 2, 0, Math.PI * 2);
  ctx.fill();
  ctx.stroke();

  ctx.fillStyle = muted;
  ctx.font = smallFont;
  ctx.textAlign = "center";
  ctx.textBaseline = "middle";
  ctx.fillText(String(num), bx + badge / 2, by + badge / 2 + 0.5);

  // Texto sin fondo, con sombra/contorno para legibilidad.
  const tx = bx + badge + gap;
  const ty = top + h / 2 + 0.5;
  ctx.font = font;
  ctx.textAlign = "left";
  ctx.textBaseline = "middle";
  ctx.lineWidth = 5;
  ctx.strokeStyle = "rgba(255,255,255,0.75)";
  ctx.strokeText(text, tx, ty);
  ctx.shadowColor = "rgba(0,0,0,0.25)";
  ctx.shadowBlur = 10;
  ctx.shadowOffsetY = 2;
  ctx.fillStyle = ink;
  ctx.fillText(text, tx, ty);

  ctx.restore();
}

function drawRoadLabel(ctx, x, y, text, angleDeg, options) {
  const fontSize = (options && options.fontSize) || 20;
  const font =
    (options && options.font) ||
    `900 ${fontSize}px system-ui, -apple-system, Segoe UI, Roboto, Arial`;
  const ink = (options && options.ink) || "rgba(20,20,20,0.85)";
  const halo = (options && options.halo) || "rgba(255,255,255,0.82)";
  const angle = (Number(angleDeg) || 0) * (Math.PI / 180);

  ctx.save();
  ctx.translate(x, y);
  ctx.rotate(angle);
  ctx.font = font;
  ctx.textAlign = "center";
  ctx.textBaseline = "middle";
  ctx.lineWidth = Math.max(6, Math.round(fontSize * 0.35));
  ctx.strokeStyle = halo;
  ctx.strokeText(text, 0, 0);
  ctx.shadowColor = "rgba(0,0,0,0.16)";
  ctx.shadowBlur = 10;
  ctx.shadowOffsetY = 2;
  ctx.fillStyle = ink;
  ctx.fillText(text, 0, 0);
  ctx.restore();
}

function measureLabelWidth(ctx, num, text) {
  // Debe coincidir con drawLabel (aprox).
  const paddingX = 0;
  const gap = 8;
  const badge = 18;
  const font = "800 14px system-ui, -apple-system, Segoe UI, Roboto, Arial";
  ctx.save();
  ctx.font = font;
  const textW = Math.ceil(ctx.measureText(String(text)).width);
  ctx.restore();
  return paddingX + badge + gap + textW + paddingX;
}

function parseNumber(value, fallback) {
  const n = Number(value);
  return Number.isFinite(n) ? n : fallback;
}

function applyScale(canvas, scale) {
  const s = Math.max(0.1, Number(scale) || 1);
  if (Math.abs(s - 1) < 1e-6) return canvas;
  const out = document.createElement("canvas");
  out.width = Math.round(canvas.width * s);
  out.height = Math.round(canvas.height * s);
  const ctx = out.getContext("2d");
  ctx.imageSmoothingEnabled = true;
  ctx.imageSmoothingQuality = "high";
  ctx.drawImage(canvas, 0, 0, out.width, out.height);
  return out;
}

function applyCrop(canvas, crop) {
  const top = Math.max(0, Math.floor(crop.top || 0));
  const right = Math.max(0, Math.floor(crop.right || 0));
  const bottom = Math.max(0, Math.floor(crop.bottom || 0));
  const left = Math.max(0, Math.floor(crop.left || 0));
  const w = Math.max(1, canvas.width - left - right);
  const h = Math.max(1, canvas.height - top - bottom);

  const out = document.createElement("canvas");
  out.width = w;
  out.height = h;
  const ctx = out.getContext("2d");
  ctx.drawImage(canvas, left, top, w, h, 0, 0, w, h);
  return out;
}

function applyRotation(canvas, deg, bgColor) {
  const angle = (Number(deg) || 0) * (Math.PI / 180);
  if (Math.abs(angle) < 1e-6) return canvas;

  const w = canvas.width;
  const h = canvas.height;
  const cos = Math.cos(angle);
  const sin = Math.sin(angle);
  // Agrega un padding extra para evitar cortes por redondeo/antialias.
  const newW = Math.ceil(Math.abs(w * cos) + Math.abs(h * sin)) + 6;
  const newH = Math.ceil(Math.abs(w * sin) + Math.abs(h * cos)) + 6;

  const out = document.createElement("canvas");
  out.width = newW;
  out.height = newH;
  const ctx = out.getContext("2d");
  ctx.fillStyle = bgColor || "#ffffff";
  ctx.fillRect(0, 0, newW, newH);

  ctx.translate(newW / 2, newH / 2);
  ctx.rotate(angle);
  ctx.drawImage(canvas, -w / 2, -h / 2);
  return out;
}

async function resolveGoogleUrl(url) {
  const qs = new URLSearchParams({ url });
  const res = await fetch(`./resolve.php?${qs.toString()}`, {
    method: "GET",
    cache: "no-store",
    credentials: "same-origin",
  });
  const data = await res.json().catch(() => null);
  if (!res.ok || !data || !data.ok) {
    const msg = data && data.error ? data.error : `HTTP ${res.status}`;
    throw new Error(msg);
  }
  return data;
}

function createMapPinIcon(point, accentColor) {
  const safeId = String(point.id ?? "").replace(/[^\w-]/g, "");
  const pinColor = accentColor || "#7a1f4b";

  const html = `
    <div class="map-pin" data-id="${safeId}">
      <svg viewBox="0 0 64 88" aria-hidden="true" focusable="false">
        <path d="M32 0C19 0 8.5 10.3 8.5 23.2c0 17.3 21.5 39.1 21.5 39.1S55.5 40.5 55.5 23.2C55.5 10.3 45 0 32 0z" fill="${pinColor}" />
        <path d="M32 12.5c-6.1 0-11 4.9-11 11s4.9 11 11 11 11-4.9 11-11-4.9-11-11-11z" fill="rgba(255,255,255,0.92)" />
      </svg>
    </div>
  `;

  return L.divIcon({
    className: "",
    html,
    iconSize: [38, 52],
    iconAnchor: [19, 52],
    tooltipAnchor: [0, -44],
  });
}

function createLabelIconHtml(num, text) {
  const n = escapeHtml(num);
  const t = escapeHtml(text);
  return `<div class="label-marker"><span class="lbl-num">${n}</span><span class="lbl-text">${t}</span></div>`;
}

function createLabelIcon(num, text) {
  return L.divIcon({
    className: "",
    html: createLabelIconHtml(num, text),
    iconSize: [10, 10],
    iconAnchor: [5, 5],
  });
}

function createRoadLabelIcon(text, angleDeg) {
  const t = escapeHtml(text);
  const a = Number(angleDeg) || 0;
  return L.divIcon({
    className: "",
    html: `<div class="road-label" style="transform: rotate(${a}deg)">${t}</div>`,
    iconSize: [10, 10],
    iconAnchor: [5, 5],
  });
}

function main() {
  const cfg = window.MAPA_CONFIG;
  if (!cfg) {
    setStatus("No existe window.MAPA_CONFIG. Crea `data.js` (copia `data.example.js`).");
    return;
  }

  const map = L.map("map", {
    zoomControl: true,
    preferCanvas: true,
  });

  const tilesCfg = cfg.tiles || {};
  const tileAttribution = tilesCfg.attribution || "";
  const tileCommon = {
    attribution: tileAttribution,
    maxZoom: 20,
    crossOrigin: "anonymous",
    detectRetina: true,
  };

  let baseTiles = null;
  let labelTiles = null;
  if (tilesCfg.baseUrl && tilesCfg.labelsUrl) {
    baseTiles = L.tileLayer(tilesCfg.baseUrl, tileCommon).addTo(map);
    labelTiles = L.tileLayer(tilesCfg.labelsUrl, {
      ...tileCommon,
      opacity: typeof tilesCfg.labelsOpacity === "number" ? tilesCfg.labelsOpacity : 0.95,
    }).addTo(map);
  } else {
    baseTiles = L.tileLayer(tilesCfg.url, tileCommon).addTo(map);
  }

  const points = (cfg.points || []).map(normalizePoint);
  const markerLayer = L.layerGroup().addTo(map);
  const zoneLayer = L.layerGroup().addTo(map);
  const markerById = new Map();
  const zoneById = new Map();
  const pointById = new Map();
  const labelMarkerById = new Map();
  let polyline = null;

  const pathCfg = cfg.path || {};
  const order = Array.isArray(pathCfg.order) ? pathCfg.order : [];
  const zonesCfg = cfg.zones || {};
  const fitCfg = cfg.fit || {};
  const labelCfg = cfg.labels || {};
  const roadLabels = [];

  function redrawPath() {
    const orderedLatLngs = [];
    for (const id of order) {
      const marker = markerById.get(Number(id));
      if (!marker) continue;
      orderedLatLngs.push(marker.getLatLng());
    }

    if (polyline) {
      polyline.remove();
      polyline = null;
    }

    if (orderedLatLngs.length >= 2) {
      polyline = L.polyline(orderedLatLngs, {
        color: pathCfg.color || "#7a1f4b",
        weight: pathCfg.weight ?? 3,
        opacity: pathCfg.opacity ?? 0.9,
        dashArray: pathCfg.dashArray || "8 8",
        lineCap: "round",
      }).addTo(map);
    }
  }

  function fitToMarkers() {
    const latlngs = [];
    for (const marker of markerById.values()) latlngs.push(marker.getLatLng());
    if (latlngs.length) {
      const bounds = L.latLngBounds(latlngs).pad(0.22);
      const ptl = Array.isArray(fitCfg.paddingTopLeft) ? fitCfg.paddingTopLeft : [40, 40];
      const pbr = Array.isArray(fitCfg.paddingBottomRight) ? fitCfg.paddingBottomRight : [140, 40];
      map.fitBounds(bounds, { paddingTopLeft: ptl, paddingBottomRight: pbr });
      return true;
    }
    return false;
  }

  function upsertMarker(point) {
    if (point.lat == null || point.lng == null) return false;
    const id = Number(point.id);
    const latlng = L.latLng(Number(point.lat), Number(point.lng));
    const existing = markerById.get(id);

    if (existing) {
      existing.setLatLng(latlng);
      const existingZone = zoneById.get(id);
      if (existingZone) existingZone.setLatLng(latlng);
      pointById.set(id, point);
      return true;
    }

    const accent = (pathCfg && pathCfg.color) || "#7a1f4b";
    const marker = L.marker(latlng, {
      icon: createMapPinIcon(point, accent),
      keyboard: false,
      riseOnHover: true,
    }).addTo(markerLayer);

    const label = point.label ?? point.name;
    if (label) {
      const direction = point.labelDirection || "top";
      const offset = Array.isArray(point.labelOffset)
        ? point.labelOffset
        : [0, -54];
      const num = escapeHtml(point.id ?? "");
      const text = escapeHtml(label);
      const html = `<span class="lbl"><span class="lbl-num">${num}</span><span class="lbl-text">${text}</span></span>`;
      marker.bindTooltip(html, {
        permanent: true,
        direction,
        offset,
        className: "label",
      });
    }
    markerById.set(id, marker);
    pointById.set(id, point);

    // Zona punteada alrededor del punto (útil para "colonia" / área).
    const zonesEnabled = zonesCfg.enabled !== false;
    const radius =
      (point.zone && typeof point.zone.radiusMeters === "number" && point.zone.radiusMeters) ||
      (typeof zonesCfg.defaultRadiusMeters === "number" && zonesCfg.defaultRadiusMeters) ||
      260;
    if (zonesEnabled && radius > 0) {
      const zone = L.circle(latlng, {
        radius,
        color: zonesCfg.color || accent,
        weight: zonesCfg.weight ?? 2,
        opacity: zonesCfg.opacity ?? 0.55,
        fill: false,
        dashArray: zonesCfg.dashArray || "6 8",
      }).addTo(zoneLayer);
      zoneById.set(id, zone);
    }

    return true;
  }

  function computeLabelLatLngForPoint(point, marker) {
    const labelText = String(point.label ?? point.name ?? "").trim();
    if (!labelText) return null;
    const baseOffset = Array.isArray(point.labelOffset) ? point.labelOffset : [0, -54];
    const p = map.latLngToContainerPoint(marker.getLatLng());
    const target = L.point(p.x + (baseOffset[0] || 0), p.y + (baseOffset[1] || 0));
    return map.containerPointToLatLng(target);
  }

  function updateTooltipOffsetForPoint(point, marker) {
    const tooltip = marker.getTooltip();
    if (!tooltip) return;
    const off = Array.isArray(point.labelOffset) ? point.labelOffset : [0, -54];
    tooltip.options.offset = L.point(off[0] || 0, off[1] || 0);
    marker.setTooltipContent(tooltip.getContent());
  }

  function enableLabelEditing() {
    document.body.classList.add("editing-labels");
    for (const [id, marker] of markerById.entries()) {
      const point = pointById.get(id);
      if (!point || !marker) continue;
      const labelText = String(point.label ?? point.name ?? "").trim();
      if (!labelText) continue;
      const ll = computeLabelLatLngForPoint(point, marker);
      if (!ll) continue;
      const labelMarker = L.marker(ll, {
        icon: createLabelIcon(point.id ?? "", labelText),
        draggable: true,
        keyboard: false,
      }).addTo(markerLayer);
      labelMarker.on("drag", () => {
        const p0 = map.latLngToContainerPoint(marker.getLatLng());
        const p1 = map.latLngToContainerPoint(labelMarker.getLatLng());
        point.labelOffset = [Math.round(p1.x - p0.x), Math.round(p1.y - p0.y)];
        point.labelLock = true;
      });
      labelMarker.on("dragend", () => {
        updateTooltipOffsetForPoint(point, marker);
      });
      labelMarkerById.set(id, labelMarker);
    }
  }

  function disableLabelEditing() {
    document.body.classList.remove("editing-labels");
    for (const lm of labelMarkerById.values()) lm.remove();
    labelMarkerById.clear();
    for (const [id, marker] of markerById.entries()) {
      const point = pointById.get(id);
      if (!point || !marker) continue;
      updateTooltipOffsetForPoint(point, marker);
    }
  }

  function layoutLabels() {
    if (labelCfg.autoAvoid === false) return;
    const canvas = document.createElement("canvas");
    const ctx = canvas.getContext("2d");

    const placed = [];
    const bounds = map.getSize();
    const margin = typeof labelCfg.margin === "number" ? labelCfg.margin : 8;
    const labelH = 34;

    const ids = Array.from(markerById.keys()).sort((a, b) => a - b);
    for (const id of ids) {
      const marker = markerById.get(id);
      const point = pointById.get(id);
      if (!marker || !point) continue;
      if (point.labelLock) continue;
      const labelText = String(point.label ?? point.name ?? "").trim();
      if (!labelText) continue;

      const baseOffset = Array.isArray(point.labelOffset) ? point.labelOffset : [0, -54];
      const w = measureLabelWidth(ctx, point.id ?? "", labelText);

      const candidates = [];
      const baseX = Number(baseOffset[0] || 0);
      const baseY = Number(baseOffset[1] || 0);

      // Genera offsets alrededor del base para evitar encimes.
      const shifts = [
        [0, 0],
        [18, 0],
        [-18, 0],
        [0, -18],
        [0, 18],
        [26, -12],
        [-26, -12],
        [36, 0],
        [-36, 0],
        [18, -18],
        [-18, -18],
        [18, 18],
        [-18, 18],
        [52, -8],
        [-52, -8],
        [0, -36],
        [0, 36],
      ];
      for (const [sx, sy] of shifts) candidates.push([baseX + sx, baseY + sy]);

      const p = map.latLngToContainerPoint(marker.getLatLng());
      let chosen = [baseX, baseY];

      function rectForOffset(off) {
        const ax = p.x + off[0];
        const ay = p.y + off[1];
        const left = ax - w / 2;
        const top = ay - labelH / 2;
        return { left, top, right: left + w, bottom: top + labelH };
      }

      function inBounds(r) {
        return (
          r.left >= margin &&
          r.top >= margin &&
          r.right <= bounds.x - margin &&
          r.bottom <= bounds.y - margin
        );
      }

      function intersects(a, b) {
        return !(a.right < b.left || a.left > b.right || a.bottom < b.top || a.top > b.bottom);
      }

      for (const off of candidates) {
        const r = rectForOffset(off);
        if (!inBounds(r)) continue;
        let ok = true;
        for (const prev of placed) {
          if (intersects(r, prev)) {
            ok = false;
            break;
          }
        }
        if (ok) {
          chosen = off;
          placed.push(r);
          break;
        }
      }

      point._resolvedLabelOffset = chosen;
      const tooltip = marker.getTooltip();
      if (tooltip) {
        tooltip.options.offset = L.point(chosen[0], chosen[1]);
        marker.setTooltipContent(tooltip.getContent());
      }
    }
  }

  // Render inmediato de lo que ya tenga coordenadas.
  for (const point of points) upsertMarker(point);

  // Ajusta vista inicial.
  if (!fitToMarkers()) {
    if (cfg.view && Array.isArray(cfg.view.center)) map.setView(cfg.view.center, cfg.view.zoom ?? 10);
    else map.setView([19.0436, -98.198], 9);
  }
  redrawPath();
  layoutLabels();

  // (Sin roadLabels manuales)

  const unresolved = points.filter((p) => p.lat == null || p.lng == null);
  if (!unresolved.length) {
    setStatus("Listo. Puedes exportar a PNG.");
  } else {
    const canResolve = window.location.protocol !== "file:";
    setStatus(
      canResolve
        ? `Resolviendo ${unresolved.length} link(s) de Google…`
        : `Faltan coordenadas. Abre esta página desde Laragon (http://localhost/...) para resolver links cortos.`
    );
  }

  // Resolver links cortos/redirects vía PHP (mismo origen).
  (async () => {
    if (window.location.protocol === "file:") return;
    const toResolve = points.filter(
      (p) =>
        (p.lat == null || p.lng == null) &&
        typeof p.googleUrl === "string" &&
        p.googleUrl.trim()
    );
    if (!toResolve.length) return;

    let resolvedCount = 0;
    let failedCount = 0;

    for (const point of toResolve) {
      try {
        const url = point.googleUrl.trim();
        const data = await resolveGoogleUrl(url);
        if (typeof data.lat === "number" && typeof data.lng === "number") {
          point.lat = data.lat;
          point.lng = data.lng;
          upsertMarker(point);
          redrawPath();
          fitToMarkers();
          layoutLabels();
          resolvedCount += 1;
        } else {
          failedCount += 1;
        }
      } catch (e) {
        failedCount += 1;
      }
      setStatus(
        `Resueltos: ${resolvedCount}/${toResolve.length}. Fallidos: ${failedCount}.`
      );
    }

    if (failedCount === 0) setStatus("Listo. Puedes exportar a PNG.");
    else setStatus(`Listo con advertencias: ${failedCount} link(s) no se pudieron resolver.`);
  })();

  let layoutTimer = null;
  function scheduleLayout() {
    if (layoutTimer) clearTimeout(layoutTimer);
    layoutTimer = setTimeout(() => layoutLabels(), 80);
  }
  map.on("moveend zoomend", scheduleLayout);

  // Exportación a PNG.
  const btnExport = document.getElementById("btnExport");
  const btnEditLabels = document.getElementById("btnEditLabels");
  const btnCopyLabels = document.getElementById("btnCopyLabels");
  const exportDialog = document.getElementById("exportDialog");
  const previewCanvas = document.getElementById("previewCanvas");
  const previewNote = document.getElementById("previewNote");
  const expZoom = document.getElementById("expZoom");
  const expZoomNum = document.getElementById("expZoomNum");
  const expRotate = document.getElementById("expRotate");
  const expRotateNum = document.getElementById("expRotateNum");
  const cropTop = document.getElementById("cropTop");
  const cropRight = document.getElementById("cropRight");
  const cropBottom = document.getElementById("cropBottom");
  const cropLeft = document.getElementById("cropLeft");
  const panX = document.getElementById("panX");
  const panY = document.getElementById("panY");
  const btnCapture = document.getElementById("btnCapture");
  const btnDownload = document.getElementById("btnDownload");

  let baseCapture = null;
  let editing = false;

  if (btnEditLabels) {
    btnEditLabels.addEventListener("click", () => {
      editing = !editing;
      btnEditLabels.textContent = editing ? "Terminar edición" : "Editar textos";
      if (editing) {
        enableLabelEditing();
        setStatus("Edición activa: arrastra los textos. Luego exporta o copia offsets.");
      } else {
        disableLabelEditing();
        setStatus("Edición terminada.");
        layoutLabels();
      }
    });
  }

  if (btnCopyLabels) {
    btnCopyLabels.addEventListener("click", async () => {
      const out = {};
      for (const point of points) {
        const id = Number(point.id);
        if (!id) continue;
        out[id] = {
          labelDirection: point.labelDirection || "top",
          labelOffset: Array.isArray(point.labelOffset) ? point.labelOffset : [0, -54],
          labelLock: Boolean(point.labelLock),
        };
      }
      const txt =
        "// Pega esto en data.js y aplica a cada punto (labelOffset/labelLock)\n" +
        JSON.stringify(out, null, 2);
      try {
        await navigator.clipboard.writeText(txt);
        setStatus("Offsets copiados al portapapeles.");
      } catch {
        setStatus("No se pudo copiar. Abre consola y copia manualmente.");
        // eslint-disable-next-line no-console
        console.log(txt);
      }
    });
  }

  function getExportOptions() {
    return {
      rotateDeg: parseNumber(expRotateNum && expRotateNum.value, 0),
      zoom: parseNumber(expZoomNum && expZoomNum.value, map.getZoom()),
      crop: {
        top: parseNumber(cropTop && cropTop.value, 0),
        right: parseNumber(cropRight && cropRight.value, 0),
        bottom: parseNumber(cropBottom && cropBottom.value, 0),
        left: parseNumber(cropLeft && cropLeft.value, 0),
      },
      pan: {
        x: parseNumber(panX && panX.value, 0),
        y: parseNumber(panY && panY.value, 0),
      },
      bg: "#ffffff",
    };
  }

  function renderPreview() {
    if (!baseCapture || !previewCanvas) return;
    const opt = getExportOptions();
    let c = baseCapture;
    c = applyCrop(c, opt.crop);
    c = applyRotation(c, opt.rotateDeg, opt.bg);

    previewCanvas.width = c.width;
    previewCanvas.height = c.height;
    const ctx = previewCanvas.getContext("2d");
    ctx.drawImage(c, 0, 0);
    if (previewNote) {
      previewNote.textContent = `${c.width}×${c.height}px · rotación ${opt.rotateDeg}° · zoom ${opt.zoom.toFixed(1)}`;
    }
  }

  function captureBaseCanvas() {
    return new Promise((resolve, reject) => {
      setStatus("Capturando mapa…");

      const opt = getExportOptions();
      const hadMarkers = map.hasLayer(markerLayer);
      const pan = [opt.pan.x || 0, opt.pan.y || 0];
      const prevZoom = map.getZoom();

      if (pan[0] || pan[1]) map.panBy(pan, { animate: false });
      if (typeof opt.zoom === "number" && Number.isFinite(opt.zoom) && opt.zoom !== prevZoom) {
        map.setZoom(opt.zoom, { animate: false });
      }
      if (hadMarkers) map.removeLayer(markerLayer);

      leafletImage(map, (err, canvas) => {
        if (hadMarkers) markerLayer.addTo(map);
        if (typeof opt.zoom === "number" && Number.isFinite(opt.zoom) && opt.zoom !== prevZoom) {
          map.setZoom(prevZoom, { animate: false });
        }
        if (pan[0] || pan[1]) map.panBy([-pan[0], -pan[1]], { animate: false });

        if (err) {
          setStatus(`No se pudo capturar: ${String(err.message || err)}`);
          reject(err);
          return;
        }

        try {
          const ctx = canvas.getContext("2d");
          const w = canvas.width;
          const h = canvas.height;
          const accent = (pathCfg && pathCfg.color) || "#7a1f4b";

          // Fondo blanco (para que la exportación sea consistente).
          ctx.save();
          ctx.globalCompositeOperation = "destination-over";
          ctx.fillStyle = "#ffffff";
          ctx.fillRect(0, 0, w, h);
          ctx.restore();

          for (const point of points) {
            if (point.lat == null || point.lng == null) continue;
            const latlng = L.latLng(Number(point.lat), Number(point.lng));
            const p = map.latLngToContainerPoint(latlng);

            drawPin(ctx, p.x, p.y, accent);

            const labelText = String(point.label ?? point.name ?? "").trim();
            if (!labelText) continue;

            const resolved = Array.isArray(point._resolvedLabelOffset)
              ? point._resolvedLabelOffset
              : null;
            const offset = resolved || (Array.isArray(point.labelOffset) ? point.labelOffset : [0, -54]);
            const anchorX = p.x + Number(offset[0] || 0);
            const anchorY = p.y + Number(offset[1] || 0);
            drawLabel(ctx, anchorX, anchorY, point.id ?? "", labelText, {
              clampSize: [w, h],
            });
          }

          // (Sin roadLabels manuales)

          setStatus("Captura lista. Ajusta opciones y descarga.");
          resolve(canvas);
        } catch (e) {
          setStatus(`No se pudo dibujar overlay: ${String(e.message || e)}`);
          reject(e);
        }
      });
    });
  }

  function downloadCanvas(canvas) {
    const link = document.createElement("a");
    link.download = "mapa.png";
    link.href = canvas.toDataURL("image/png");
    document.body.appendChild(link);
    link.click();
    link.remove();
  }

  if (btnExport) {
    btnExport.addEventListener("click", () => {
      if (!exportDialog) return;
      exportDialog.showModal();
      if (previewNote) previewNote.textContent = "Generando captura…";

      // Inicializa zoom según vista actual.
      if (expZoom && expZoomNum) {
        const z = map.getZoom();
        expZoom.value = String(z);
        expZoomNum.value = String(z);
      }

      captureBaseCanvas()
        .then((canvas) => {
          baseCapture = canvas;
          renderPreview();
        })
        .catch(() => {
          baseCapture = null;
          if (previewNote) previewNote.textContent = "No se pudo generar la captura.";
        });
    });
  }

  // Sync inputs
  function syncZoomFromRange() {
    if (!expZoom || !expZoomNum) return;
    expZoomNum.value = expZoom.value;
    // Actualiza mapa en tiempo real y vuelve a capturar (debounce).
    map.setZoom(parseNumber(expZoom.value, map.getZoom()), { animate: false });
    scheduleCapture();
  }
  function syncZoomFromNum() {
    if (!expZoom || !expZoomNum) return;
    expZoom.value = String(parseNumber(expZoomNum.value, map.getZoom()));
    map.setZoom(parseNumber(expZoomNum.value, map.getZoom()), { animate: false });
    scheduleCapture();
  }

  function syncRotateFromRange() {
    if (!expRotate || !expRotateNum) return;
    expRotateNum.value = expRotate.value;
    renderPreview();
  }
  function syncRotateFromNum() {
    if (!expRotate || !expRotateNum) return;
    expRotate.value = String(parseNumber(expRotateNum.value, 0));
    renderPreview();
  }

  let captureTimer = null;
  function scheduleCapture() {
    if (captureTimer) clearTimeout(captureTimer);
    captureTimer = setTimeout(() => {
      if (previewNote) previewNote.textContent = "Actualizando captura…";
      captureBaseCanvas()
        .then((canvas) => {
          baseCapture = canvas;
          renderPreview();
        })
        .catch(() => {
          baseCapture = null;
          if (previewNote) previewNote.textContent = "No se pudo generar la captura.";
        });
    }, 220);
  }

  if (expZoom) expZoom.addEventListener("input", syncZoomFromRange);
  if (expZoomNum) expZoomNum.addEventListener("input", syncZoomFromNum);
  if (expRotate) expRotate.addEventListener("input", syncRotateFromRange);
  if (expRotateNum) expRotateNum.addEventListener("input", syncRotateFromNum);

  const reactiveInputs = [cropTop, cropRight, cropBottom, cropLeft];
  for (const el of reactiveInputs) {
    if (!el) continue;
    el.addEventListener("input", () => renderPreview());
  }

  if (btnCapture) {
    btnCapture.addEventListener("click", () => {
      if (previewNote) previewNote.textContent = "Actualizando captura…";
      captureBaseCanvas()
        .then((canvas) => {
          baseCapture = canvas;
          renderPreview();
        })
        .catch(() => {
          baseCapture = null;
          if (previewNote) previewNote.textContent = "No se pudo generar la captura.";
        });
    });
  }

  if (btnDownload) {
    btnDownload.addEventListener("click", () => {
      if (!baseCapture) return;
      const opt = getExportOptions();
      let c = baseCapture;
      c = applyCrop(c, opt.crop);
      c = applyRotation(c, opt.rotateDeg, opt.bg);
      downloadCanvas(c);
      setStatus("PNG generado.");
    });
  }
}

main();
