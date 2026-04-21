# Mapa (referencia)

Página estática para dibujar:

- 6 ubicaciones (pins con número)
- una zona punteada por ubicación (radio configurable)
- una línea punteada conectando los puntos
- exportación a PNG (si el proveedor de tiles permite CORS)

## Uso

1. Abre `http://localhost/segob/mapa-referencia/` (recomendado) para que funcione el resolver de links cortos.
2. Edita `C:\laragon\www\segob\public\mapa-referencia\data.js` y llena tus 6 puntos (ya trae tus 6 links).
3. Recarga la página.

## Modo “idéntico a la imagen” (plantilla)

Si necesitas que la salida sea idéntica a un diseño existente:

1. Guarda la imagen base como `C:\laragon\www\segob\public\mapa-referencia\template.png`.
2. Abre `http://localhost/segob/mapa-referencia/plantilla.html`.
3. Arrastra los 6 puntos para alinearlos con la imagen.
4. Exporta a PNG.

## Cómo obtener coordenadas (lat/lng) desde Google Maps

Opción A (desktop):

1. Abre la ubicación en Google Maps.
2. Click derecho en el punto → **¿Qué hay aquí?**
3. Copia el par `lat, lng` que aparece abajo y pégalo en `data.js`.

Opción B (URL largo):

- Si tu URL contiene `@lat,lng` (ej. `.../@19.123,-98.456,15z`), puedes pegarla en `googleUrl` y dejar `lat/lng` en `null`.
- El script también soporta patrones `!3dlat!4dlng` y `?q=lat,lng`.

## Resolver links cortos (maps.app.goo.gl)

- Si dejas `lat/lng` en `null` y usas un link corto `https://maps.app.goo.gl/...`, la página intentará resolverlo automáticamente con `C:\laragon\www\segob\public\mapa-referencia\resolve.php`.
- Esto requiere abrir el mapa desde `http://localhost/...` (no funciona con `file:///`).

## Exportar a PNG

- Botón **Exportar PNG…** abre un panel para ajustar:
  - rotación
  - recorte (px)
  - escala (1x–3x)
  - desplazamiento X/Y (para cargar a la derecha/arriba/abajo sin re-encuadrar a mano)
- Si falla por CORS, usa una captura de pantalla (o imprime a PDF desde el navegador).

## Separar nombres (tooltips)

- En `data.js` puedes ajustar `labelDirection` y `labelOffset` por punto para evitar que se encimen (por ejemplo en La Purísima / Nuevo Vicencio).
