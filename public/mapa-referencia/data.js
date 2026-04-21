// Configuración del mapa.
// Edita este archivo con tus coordenadas y/o URLs largos de Google Maps.
// Si prefieres conservar un template, usa `data.example.js`.

window.MAPA_CONFIG = {
  view: {
    center: [19.0436, -98.1980],
    zoom: 9,
  },
  tiles: {
    // Base sin etiquetas + overlay de etiquetas para que se lean mejor nombres de calles/carreteras.
    baseUrl: "https://{s}.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}{r}.png",
    labelsUrl: "https://{s}.basemaps.cartocdn.com/light_only_labels/{z}/{x}/{y}{r}.png",
    labelsOpacity: 0.98,
    attribution:
      '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
  },
  points: [
    {
      id: 1,
      name: "San José Ozumba",
      googleUrl:
        "https://www.google.com.mx/maps/place/19%C2%B014'01.3%22N+97%C2%B043'36.9%22W/@19.233698,-97.726926,17z/data=!3m1!4b1!4m4!3m3!8m2!3d19.233698!4d-97.726926?entry=ttu&g_ep=EgoyMDI2MDQxNS4wIKXMDSoASAFQAw%3D%3D",
      lat: 19.233698,
      lng: -97.726926,
      label: "San José Ozumba",
      labelDirection: "top",
      labelOffset: [0, -44],
      zone: { radiusMeters: 320 },
      photo: "",
    },
    {
      id: 2,
      name: "San José Morelos",
      googleUrl:
        "https://www.google.com/maps/place/3+Ote.,+San+Jos%C3%A9+Morelos,+75016+San+Jos%C3%A9+Morelos,+Pue./@19.2670457,-97.7588825,678m/data=!3m2!1e3!4b1!4m6!3m5!1s0x85c5564be2684e6f:0x3f57b3773ae3ca1!8m2!3d19.2670457!4d-97.7588825!16s%2Fg%2F11fzv9s_4_!18m1!1e1?entry=ttu&g_ep=EgoyMDI2MDQxNS4wIKXMDSoASAFQAw%3D%3D",
      lat: 19.2670457,
      lng: -97.7588825,
      label: "San José Morelos",
      labelDirection: "right",
      labelOffset: [18, -22],
      zone: { radiusMeters: 320 },
      photo: "",
    },
    {
      id: 3,
      name: "San Isidro Ovando",
      googleUrl:
        "https://www.google.com/maps/place/Iglesia+de+San+Isidro+Ovando/@19.2960378,-97.7533183,678m/data=!3m1!1e3!4m6!3m5!1s0x85c55720e83f0e5f:0xd0218bac5bad1f9e!8m2!3d19.2958423!4d-97.7503066!16s%2Fg%2F11st_g58vl!18m1!1e1?entry=ttu&g_ep=EgoyMDI2MDQxNS4wIKXMDSoASAFQAw%3D%3D",
      lat: 19.2958423,
      lng: -97.7503066,
      label: "San Isidro Ovando",
      labelDirection: "right",
      labelOffset: [18, -22],
      zone: { radiusMeters: 380 },
      photo: "",
    },
    {
      id: 4,
      name: "Ojo de Agua",
      googleUrl:
        "https://www.google.com/maps/place/19%C2%B016'55.7%22N+97%C2%B041'41.9%22W/@19.2825219,-97.698626,1356m/data=!3m1!1e3!4m4!3m3!8m2!3d19.2821389!4d-97.6949722!18m1!1e1?entry=ttu&g_ep=EgoyMDI2MDQxNS4wIKXMDSoASAFQAw%3D%3D",
      lat: 19.2821389,
      lng: -97.6949722,
      label: "Ojo de Agua",
      labelDirection: "top",
      labelOffset: [0, -44],
      zone: { radiusMeters: 420 },
      photo: "",
    },
    {
      id: 5,
      name: "La Purísima",
      googleUrl:
        "https://www.google.com/maps/place/La+Purisima,+San+Jos%C3%A9+Chiapa,+Pue./@19.2838496,-97.6907807,339m/data=!3m1!1e3!4m6!3m5!1s0x85c551e197297849:0x4f7562be6255f03b!8m2!3d19.2837594!4d-97.6899883!16s%2Fg%2F11v0mm9xxg!18m1!1e1?entry=ttu&g_ep=EgoyMDI2MDQxNS4wIKXMDSoASAFQAw%3D%3D",
      lat: 19.2837594,
      lng: -97.6899883,
      label: "La Purísima",
      labelDirection: "bottom",
      labelOffset: [0, 26],
      labelLock: true,
      zone: { radiusMeters: 520 },
      photo: "",
    },
    {
      id: 6,
      name: "Nuevo Vicencio",
      googleUrl:
        "https://www.google.com/maps/place/75014+Nuevo+Vicencio,+Pue./@19.2863437,-97.6884127,678m/data=!3m1!1e3!4m6!3m5!1s0x85c551387a191603:0x4750cd9989f70959!8m2!3d19.2875604!4d-97.6852648!16s%2Fg%2F11c5qsblpz!18m1!1e1?entry=ttu&g_ep=EgoyMDI2MDQxNS4wIKXMDSoASAFQAw%3D%3D",
      lat: 19.2875604,
      lng: -97.6852648,
      label: "Nuevo Vicencio",
      labelDirection: "right",
      labelOffset: [18, -22],
      zone: { radiusMeters: 520 },
      photo: "",
    },
  ],
  fit: {
    // Da más margen del lado derecho para que no se recorten etiquetas.
    paddingTopLeft: [40, 40],
    paddingBottomRight: [220, 40],
  },
  labels: {
    autoAvoid: true,
    margin: 10,
  },
  template: {
    // Para reproducir un diseño idéntico usando una imagen base:
    // 1) Guarda la imagen como `public/mapa-referencia/template.png`
    // 2) Abre `plantilla.html` y arrastra los puntos
    image: "./template.png",
    ringColor: "#7a1f4b",
    photoRadiusPx: 46,
    // Posiciones normalizadas (0..1) sobre la plantilla.
    byId: {
      1: { x: 0.55, y: 0.88 },
      2: { x: 0.24, y: 0.57 },
      3: { x: 0.26, y: 0.22 },
      4: { x: 0.70, y: 0.22 },
      5: { x: 0.78, y: 0.48 },
      6: { x: 0.82, y: 0.16 },
    },
    // Ajuste fino de label cerca de la foto.
    labelById: {
      1: { dx: 18, dy: -12 },
      2: { dx: 18, dy: -12 },
      3: { dx: 18, dy: -12 },
      4: { dx: 18, dy: -12 },
      5: { dx: 18, dy: -12 },
      6: { dx: 18, dy: -12 },
    },
  },
  zones: {
    enabled: true,
    defaultRadiusMeters: 300,
    dashArray: "6 8",
    opacity: 0.55,
    weight: 2,
  },
  path: {
    order: [1, 2, 3, 4, 5, 6],
    color: "#7a1f4b",
    weight: 3,
    dashArray: "8 8",
    opacity: 0.9,
  },
};
