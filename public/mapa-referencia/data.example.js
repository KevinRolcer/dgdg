// Renombra este archivo a `data.js` y edítalo.
// Tip: si sólo tienes coordenadas, usa lat/lng directamente.
// Tip: si pegas un URL largo de Google Maps, el script intenta extraer coordenadas.

window.MAPA_CONFIG = {
  // Ajusta a tu zona para encuadre inicial.
  view: {
    center: [19.0436, -98.1980], // Puebla (ejemplo)
    zoom: 9,
  },

  // Tiles (deben permitir CORS para exportación a PNG).
  tiles: {
    baseUrl: "https://{s}.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}{r}.png",
    labelsUrl: "https://{s}.basemaps.cartocdn.com/light_only_labels/{z}/{x}/{y}{r}.png",
    labelsOpacity: 0.98,
    attribution:
      '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
  },

  // Tus 6 puntos (edita).
  // - id: número visible
  // - name: etiqueta
  // - label: texto del tooltip (si quieres distinto a name)
  // - labelDirection: top|right|bottom|left
  // - labelOffset: [x, y] en px (para separar etiquetas)
  // - lat/lng o googleUrl (URL largo)
  points: [
    {
      id: 1,
      name: "Ubicación 1",
      label: "Ubicación 1",
      labelDirection: "top",
      labelOffset: [0, -58],
      googleUrl: "",
      lat: null,
      lng: null,
      zone: { radiusMeters: 300 },
    },
    {
      id: 2,
      name: "Ubicación 2",
      googleUrl: "",
      lat: null,
      lng: null,
      photo: "",
    },
    {
      id: 3,
      name: "Ubicación 3",
      googleUrl: "",
      lat: null,
      lng: null,
      photo: "",
    },
    {
      id: 4,
      name: "Ubicación 4",
      googleUrl: "",
      lat: null,
      lng: null,
      photo: "",
    },
    {
      id: 5,
      name: "Ubicación 5",
      googleUrl: "",
      lat: null,
      lng: null,
      photo: "",
    },
    {
      id: 6,
      name: "Ubicación 6",
      label: "Ubicación 6",
      labelDirection: "right",
      labelOffset: [12, -26],
      googleUrl: "",
      lat: null,
      lng: null,
      zone: { radiusMeters: 300 },
    },
  ],

  // Zonas punteadas alrededor de cada punto (tipo "colonia/área").
  zones: {
    enabled: true,
    defaultRadiusMeters: 300,
    dashArray: "6 8",
    opacity: 0.55,
    weight: 2,
  },

  // Evita que se encimen etiquetas (ajusta offsets automáticamente).
  labels: {
    autoAvoid: true,
    margin: 10,
  },

  // Línea punteada: por defecto conecta 1→2→3→4→5→6.
  // Si quieres otra secuencia, edita `order`.
  path: {
    order: [1, 2, 3, 4, 5, 6],
    color: "#7a1f4b",
    weight: 3,
    dashArray: "8 8",
    opacity: 0.9,
  },
};
