/* global MAPA_CONFIG */

function $(id) {
  return document.getElementById(id);
}

function setStatus(msg) {
  const el = $("tplStatus");
  if (el) el.textContent = msg || "";
}

function clamp(n, a, b) {
  return Math.max(a, Math.min(b, n));
}

function loadImage(src) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = () => resolve(img);
    img.onerror = () => reject(new Error(`No se pudo cargar imagen: ${src}`));
    img.src = src;
  });
}

function loadPhoto(src) {
  if (!src) return Promise.resolve(null);
  return loadImage(src).catch(() => null);
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

function drawCirclePhoto(ctx, x, y, r, photoImg, ringColor) {
  // foto recortada en círculo + borde guinda
  ctx.save();
  ctx.beginPath();
  ctx.arc(x, y, r, 0, Math.PI * 2);
  ctx.clip();
  if (photoImg) {
    // cover
    const s = Math.max((r * 2) / photoImg.width, (r * 2) / photoImg.height);
    const dw = photoImg.width * s;
    const dh = photoImg.height * s;
    ctx.drawImage(photoImg, x - dw / 2, y - dh / 2, dw, dh);
  } else {
    const g = ctx.createLinearGradient(x - r, y - r, x + r, y + r);
    g.addColorStop(0, "#f0f0f0");
    g.addColorStop(1, "#cfcfcf");
    ctx.fillStyle = g;
    ctx.fillRect(x - r, y - r, r * 2, r * 2);
  }
  ctx.restore();

  ctx.save();
  ctx.strokeStyle = ringColor;
  ctx.lineWidth = Math.max(2, Math.round(r * 0.18));
  ctx.beginPath();
  ctx.arc(x, y, r - ctx.lineWidth / 2, 0, Math.PI * 2);
  ctx.stroke();
  ctx.restore();
}

function drawGoldNumber(ctx, x, y, text) {
  ctx.save();
  ctx.font = "900 42px system-ui, -apple-system, Segoe UI, Roboto, Arial";
  ctx.fillStyle = "#b08a2e";
  ctx.textAlign = "center";
  ctx.textBaseline = "middle";
  ctx.shadowColor = "rgba(0,0,0,0.15)";
  ctx.shadowBlur = 10;
  ctx.shadowOffsetY = 4;
  ctx.fillText(String(text), x, y);
  ctx.restore();
}

function drawPlaceLabel(ctx, x, y, text) {
  // texto negro con halo blanco, sin caja
  ctx.save();
  ctx.font = "800 20px system-ui, -apple-system, Segoe UI, Roboto, Arial";
  ctx.textAlign = "left";
  ctx.textBaseline = "middle";
  ctx.lineWidth = 7;
  ctx.strokeStyle = "rgba(255,255,255,0.88)";
  ctx.strokeText(text, x, y);
  ctx.shadowColor = "rgba(0,0,0,0.18)";
  ctx.shadowBlur = 8;
  ctx.shadowOffsetY = 2;
  ctx.fillStyle = "rgba(20,20,20,0.95)";
  ctx.fillText(text, x, y);
  ctx.restore();
}

async function main() {
  const cfg = window.MAPA_CONFIG || {};
  const tpl = cfg.template || {};
  const canvas = $("tplCanvas");
  if (!canvas) return;

  const ctx = canvas.getContext("2d");
  const imageSrc = tpl.image || "./template.png";
  let baseImg = null;

  try {
    baseImg = await loadImage(imageSrc);
  } catch (e) {
    setStatus(
      `No se encontró la plantilla (${imageSrc}). Guarda tu imagen como public/mapa-referencia/template.png`
    );
    // Canvas mínimo para que se vea el mensaje.
    canvas.width = 900;
    canvas.height = 520;
    ctx.fillStyle = "#ffffff";
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    return;
  }

  canvas.width = baseImg.naturalWidth || baseImg.width;
  canvas.height = baseImg.naturalHeight || baseImg.height;

  const points = (cfg.points || []).map((p) => ({ ...p }));
  const photos = new Map();
  await Promise.all(
    points.map(async (p) => {
      const img = await loadPhoto(p.photo);
      photos.set(Number(p.id), img);
    })
  );

  // Posiciones por defecto (si no existen), en coordenadas normalizadas.
  const defaults = tpl.defaults || {
    // aproximación para 1024x768 (ajusta arrastrando)
    byId: {
      1: { x: 0.55, y: 0.88 },
      2: { x: 0.24, y: 0.57 },
      3: { x: 0.26, y: 0.22 },
      4: { x: 0.70, y: 0.22 },
      5: { x: 0.78, y: 0.48 },
      6: { x: 0.82, y: 0.16 },
    },
  };

  const posById = new Map();
  for (const p of points) {
    const id = Number(p.id);
    const existing = tpl.byId && tpl.byId[id];
    const d = defaults.byId && defaults.byId[id];
    const x = existing && typeof existing.x === "number" ? existing.x : d ? d.x : 0.5;
    const y = existing && typeof existing.y === "number" ? existing.y : d ? d.y : 0.5;
    posById.set(id, { x, y });
  }

  function draw() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(baseImg, 0, 0, canvas.width, canvas.height);

    const ring = tpl.ringColor || "#7a1f4b";
    const r = typeof tpl.photoRadiusPx === "number" ? tpl.photoRadiusPx : 46;

    // Dibuja fotos + números + nombres, como en referencia.
    for (const p of points) {
      const id = Number(p.id);
      const pos = posById.get(id);
      if (!pos) continue;
      const x = pos.x * canvas.width;
      const y = pos.y * canvas.height;

      drawCirclePhoto(ctx, x, y, r, photos.get(id), ring);

      const numY = y - r - 34;
      drawGoldNumber(ctx, x, numY, id);

      const name = String(p.label ?? p.name ?? "").trim();
      if (name) {
        const label = (tpl.labelById && tpl.labelById[id]) || {};
        const dx = typeof label.dx === "number" ? label.dx : 18;
        const dy = typeof label.dy === "number" ? label.dy : -10;
        drawPlaceLabel(ctx, x + dx, y + dy, name);
      }
    }
  }

  draw();
  setStatus("Arrastra los puntos para alinearlos con la plantilla. Exporta cuando esté idéntico.");

  // Drag simple
  let draggingId = null;
  function hitTest(px, py) {
    const r = typeof tpl.photoRadiusPx === "number" ? tpl.photoRadiusPx : 46;
    for (const p of points) {
      const id = Number(p.id);
      const pos = posById.get(id);
      if (!pos) continue;
      const x = pos.x * canvas.width;
      const y = pos.y * canvas.height;
      const dx = px - x;
      const dy = py - y;
      if (dx * dx + dy * dy <= r * r) return id;
    }
    return null;
  }

  function toLocal(ev) {
    const rect = canvas.getBoundingClientRect();
    const x = ((ev.clientX - rect.left) / rect.width) * canvas.width;
    const y = ((ev.clientY - rect.top) / rect.height) * canvas.height;
    return { x, y };
  }

  canvas.addEventListener("mousedown", (ev) => {
    const p = toLocal(ev);
    const id = hitTest(p.x, p.y);
    if (id == null) return;
    draggingId = id;
    ev.preventDefault();
  });
  window.addEventListener("mouseup", () => {
    if (draggingId == null) return;
    const id = draggingId;
    draggingId = null;
    const pos = posById.get(id);
    if (pos) {
      setStatus(
        `Punto ${id}: x=${pos.x.toFixed(4)}, y=${pos.y.toFixed(4)}. Copia a MAPA_CONFIG.template.byId[${id}].`
      );
    }
  });
  window.addEventListener("mousemove", (ev) => {
    if (draggingId == null) return;
    const p = toLocal(ev);
    const nx = clamp(p.x / canvas.width, 0, 1);
    const ny = clamp(p.y / canvas.height, 0, 1);
    posById.set(draggingId, { x: nx, y: ny });
    draw();
  });

  const btn = $("btnExportTemplate");
  if (btn) {
    btn.addEventListener("click", () => {
      const link = document.createElement("a");
      link.download = "mapa-plantilla.png";
      link.href = canvas.toDataURL("image/png");
      document.body.appendChild(link);
      link.click();
      link.remove();
    });
  }
}

main();

