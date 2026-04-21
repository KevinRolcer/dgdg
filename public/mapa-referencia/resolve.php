<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function respond(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function extractLatLng(string $url): ?array {
    if (preg_match('/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $url, $m)) {
        return ['lat' => (float)$m[1], 'lng' => (float)$m[2]];
    }
    if (preg_match('/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/', $url, $m)) {
        return ['lat' => (float)$m[1], 'lng' => (float)$m[2]];
    }
    if (preg_match('/[?&]q=(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $url, $m)) {
        return ['lat' => (float)$m[1], 'lng' => (float)$m[2]];
    }
    return null;
}

$raw = isset($_GET['url']) ? trim((string)$_GET['url']) : '';
if ($raw === '') {
    respond(400, ['ok' => false, 'error' => 'Falta parámetro url.']);
}

if (!filter_var($raw, FILTER_VALIDATE_URL)) {
    respond(400, ['ok' => false, 'error' => 'URL inválida.']);
}

$parts = parse_url($raw);
$scheme = strtolower((string)($parts['scheme'] ?? ''));
$host = strtolower((string)($parts['host'] ?? ''));

if ($scheme !== 'https') {
    respond(400, ['ok' => false, 'error' => 'Sólo se permite https.']);
}

// Restringe destinos permitidos (evita SSRF a hosts internos).
$allowedHosts = [
    'maps.app.goo.gl',
    'goo.gl',
    'www.google.com',
    'google.com',
    'maps.google.com',
];
if (!in_array($host, $allowedHosts, true)) {
    respond(400, ['ok' => false, 'error' => 'Host no permitido.']);
}

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $raw,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 8,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => false,
    CURLOPT_TIMEOUT => 12,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_USERAGENT => 'segob-mapa-referencia/1.0',
    // No necesitamos el body completo; pero algunas respuestas no redirigen bien con HEAD.
]);

$body = curl_exec($ch);
$errNo = curl_errno($ch);
$err = curl_error($ch);
$effectiveUrl = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
$httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

if ($errNo !== 0) {
    respond(502, ['ok' => false, 'error' => 'No se pudo resolver redirección.', 'detail' => $err]);
}

if ($httpCode < 200 || $httpCode >= 400) {
    respond(502, ['ok' => false, 'error' => 'Respuesta inesperada al resolver.', 'http' => $httpCode]);
}

// Valida que el destino final también sea Google.
$finalParts = parse_url($effectiveUrl);
$finalHost = strtolower((string)($finalParts['host'] ?? ''));
$finalAllowedSuffixes = ['google.com', 'goo.gl'];
$finalOk = false;
foreach ($finalAllowedSuffixes as $suffix) {
    if ($finalHost === $suffix || str_ends_with($finalHost, '.' . $suffix)) {
        $finalOk = true;
        break;
    }
}
if (!$finalOk) {
    respond(400, ['ok' => false, 'error' => 'Destino final no permitido.']);
}

$coords = extractLatLng($effectiveUrl);
if ($coords === null) {
    // A veces las coordenadas sólo aparecen en el HTML (meta/JS). Intentamos extraer también del body.
    if (is_string($body) && $body !== '') {
        $coords = extractLatLng($body);
    }
}

respond(200, [
    'ok' => true,
    'finalUrl' => $effectiveUrl,
    'lat' => $coords['lat'] ?? null,
    'lng' => $coords['lng'] ?? null,
]);

