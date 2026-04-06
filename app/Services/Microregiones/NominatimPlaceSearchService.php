<?php

namespace App\Services\Microregiones;

use App\Models\Municipio;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NominatimPlaceSearchService
{
    private const CACHE_PREFIX = 'microregiones_place_search_v4_';
    private const MUNICIPIOS_CACHE_KEY = 'microregiones_municipios_puebla_v1';
    private const PUEBLA_VIEWBOX = '-98.765,20.895,-96.708,17.862';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchInPuebla(string $query, int $limit = 5): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $cacheKey = self::CACHE_PREFIX.md5(mb_strtolower($query, 'UTF-8').'|'.$limit);

        return Cache::remember($cacheKey, now()->addHours(12), function () use ($query, $limit) {
            [$placeQuery, $municipioHint] = $this->extractSearchContext($query);
            $placeQuery = trim($placeQuery);
            if ($placeQuery === '') {
                return [];
            }

            $commaHint = $this->parseMunicipioHintFromQuery($placeQuery);
            if ($commaHint !== null && ($municipioHint === null || mb_strlen($commaHint, 'UTF-8') > mb_strlen($municipioHint, 'UTF-8'))) {
                $municipioHint = $commaHint;
            }

            $rows = $this->requestPlaces($placeQuery, max($limit * 10, 45));
            if ($rows === []) {
                return [];
            }

            $municipios = $this->loadMunicipiosPuebla();
            $results = [];
            $seen = [];

            foreach ($rows as $row) {
                $resolved = $this->resolveMunicipio($row, $municipios, $municipioHint);
                if ($resolved === null) {
                    continue;
                }

                $lat = isset($row['lat']) ? (float) $row['lat'] : null;
                $lng = isset($row['lon']) ? (float) $row['lon'] : null;
                if ($lat === null || $lng === null) {
                    continue;
                }

                $uniqueKey = $resolved['municipio_id'].'|'.mb_strtolower((string) ($row['display_name'] ?? ''), 'UTF-8');
                if (isset($seen[$uniqueKey])) {
                    continue;
                }

                $seen[$uniqueKey] = true;
                $results[] = [
                    'display_name' => (string) ($row['display_name'] ?? ''),
                    'label' => $this->buildPlaceLabel($row, $resolved['municipio_nombre']),
                    'lat' => $lat,
                    'lng' => $lng,
                    'type' => (string) ($row['type'] ?? ''),
                    'osm_type' => (string) ($row['osm_type'] ?? ''),
                    'geometry' => $this->extractResultGeometry($row),
                    'municipio_id' => $resolved['municipio_id'],
                    'municipio_nombre' => $resolved['municipio_nombre'],
                    'resolution_source' => $resolved['source'],
                ];

                if (count($results) >= $limit) {
                    break;
                }
            }

            return $results;
        });
    }

    /**
     * Nominatim exige ~1 solicitud por segundo; ráfagas grandes devuelven 429 o cuerpo HTML y la búsqueda queda vacía.
     *
     * @return array<int, array<string, mixed>>
     */
    private function requestPlaces(string $query, int $rawTarget): array
    {
        $variants = array_values(array_unique(array_slice($this->buildSearchQueries($query), 0, 3)));
        if ($variants === []) {
            return [];
        }

        $limit = min(50, max(25, $rawTarget));
        $merged = [];
        $seen = [];

        $steps = [
            ['q' => $variants[0], 'viewbox' => self::PUEBLA_VIEWBOX, 'bounded' => false],
            ['q' => $variants[0], 'viewbox' => null, 'bounded' => false],
        ];

        if (isset($variants[1]) && $variants[1] !== $variants[0]) {
            $steps[] = ['q' => $variants[1], 'viewbox' => self::PUEBLA_VIEWBOX, 'bounded' => false];
        }

        foreach ($steps as $idx => $step) {
            if (count($merged) >= $rawTarget) {
                break;
            }
            if ($idx > 0) {
                usleep(1_100_000);
            }

            $rows = $this->nominatimSearchOnce($step['q'], $limit, $step['viewbox'], $step['bounded']);
            foreach ($rows as $row) {
                if (! is_array($row) || ! $this->looksLikePuebla($row)) {
                    continue;
                }

                $uniqueKey = (string) ($row['place_id'] ?? $row['osm_id'] ?? md5((string) ($row['display_name'] ?? '')));
                if (isset($seen[$uniqueKey])) {
                    continue;
                }

                $seen[$uniqueKey] = true;
                $merged[] = $row;

                if (count($merged) >= $rawTarget) {
                    return $merged;
                }
            }
        }

        if ($merged === [] || count($merged) < 8) {
            usleep(1_100_000);
            $rows = $this->nominatimSearchOnce($variants[0], $limit, self::PUEBLA_VIEWBOX, true);
            foreach ($rows as $row) {
                if (! is_array($row) || ! $this->looksLikePuebla($row)) {
                    continue;
                }

                $uniqueKey = (string) ($row['place_id'] ?? $row['osm_id'] ?? md5((string) ($row['display_name'] ?? '')));
                if (isset($seen[$uniqueKey])) {
                    continue;
                }

                $seen[$uniqueKey] = true;
                $merged[] = $row;

                if (count($merged) >= $rawTarget) {
                    break;
                }
            }
        }

        return $merged;
    }

    /**
     * Una sola petición de búsqueda a Nominatim.
     *
     * @return array<int, array<string, mixed>>
     */
    private function nominatimSearchOnce(string $variant, int $limit, ?string $viewbox, bool $bounded): array
    {
        $params = [
            'q' => $variant.', Puebla, México',
            'format' => 'jsonv2',
            'limit' => $limit,
            'countrycodes' => 'mx',
            'addressdetails' => 1,
            'polygon_geojson' => 1,
        ];
        if ($viewbox !== null) {
            $params['viewbox'] = $viewbox;
        }
        if ($bounded) {
            $params['bounded'] = 1;
        }

        $headers = [
            'User-Agent' => 'SegobMicroregiones/1.0 (busqueda territorial interna)',
            'Accept-Language' => 'es',
        ];

        try {
            $response = Http::connectTimeout(5)
                ->timeout(20)
                ->withHeaders($headers)
                ->get('https://nominatim.openstreetmap.org/search', $params);

            if ($response->status() === 429) {
                Log::warning('Nominatim 429 (límite de uso); reintento tras pausa.');
                sleep(2);
                $response = Http::connectTimeout(5)
                    ->timeout(20)
                    ->withHeaders($headers)
                    ->get('https://nominatim.openstreetmap.org/search', $params);
            }

            if (! $response->successful()) {
                Log::warning('Nominatim HTTP '.$response->status().' en búsqueda microregiones');

                return [];
            }

            $rows = $response->json();
            if (! is_array($rows)) {
                Log::warning('Nominatim devolvió cuerpo no JSON (bloqueo, captcha o error).');

                return [];
            }

            return $rows;
        } catch (\Throwable $e) {
            Log::warning('Nominatim place search: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Quita pista entre paréntesis al final, p. ej. "Los Encinos (Huejotzingo)".
     *
     * @return array{0: string, 1: string|null}
     */
    private function extractSearchContext(string $query): array
    {
        $query = trim($query);
        $hint = null;

        if (preg_match('/\(\s*([^)]+)\s*\)\s*$/u', $query, $m)) {
            $hint = trim($m[1]);
            $query = trim(preg_replace('/\s*\(\s*[^)]+\s*\)\s*$/u', '', $query) ?? '');
        }

        return [$query, $hint !== '' ? $hint : null];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadMunicipiosPuebla(): array
    {
        return Cache::remember(self::MUNICIPIOS_CACHE_KEY, now()->addHours(12), function () {
            return Municipio::query()
                ->whereNotNull('microrregion_id')
                ->where(function ($q) {
                    $q->whereIn('cve_edo', ['21', '021', 21])
                        ->orWhereRaw('CAST(cve_edo AS UNSIGNED) = ?', [21]);
                })
                ->orderBy('municipio')
                ->get(['id', 'municipio', 'microrregion_id', 'geojson_boundary'])
                ->map(function (Municipio $municipio) {
                    return [
                        'id' => (int) $municipio->id,
                        'municipio' => (string) $municipio->municipio,
                        'normalized' => $this->normalizeMunicipioName((string) $municipio->municipio),
                        'geometry' => ! empty($municipio->geojson_boundary)
                            ? json_decode((string) $municipio->geojson_boundary, true)
                            : null,
                    ];
                })
                ->all();
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, array<string, mixed>>  $municipios
     * @return array{municipio_id:int, municipio_nombre:string, source:string}|null
     */
    private function resolveMunicipio(array $row, array $municipios, ?string $municipioHint = null): ?array
    {
        $lat = isset($row['lat']) ? (float) $row['lat'] : null;
        $lng = isset($row['lon']) ? (float) $row['lon'] : null;

        if ($lat !== null && $lng !== null) {
            foreach ($municipios as $municipio) {
                if (! is_array($municipio['geometry'] ?? null)) {
                    continue;
                }

                if ($this->pointInGeometry($lng, $lat, $municipio['geometry'])) {
                    return [
                        'municipio_id' => (int) $municipio['id'],
                        'municipio_nombre' => (string) $municipio['municipio'],
                        'source' => 'boundary',
                    ];
                }
            }
        }

        $address = $row['address'] ?? [];
        $candidates = [];

        foreach (['municipality', 'county', 'city', 'town', 'village', 'locality', 'state_district', 'city_district', 'region'] as $key) {
            $value = is_array($address) ? ($address[$key] ?? null) : null;
            if (is_string($value) && trim($value) !== '') {
                $candidates[] = $value;
            }
        }

        $displayName = isset($row['display_name']) ? (string) $row['display_name'] : '';
        if ($displayName !== '') {
            $candidates[] = $displayName;
        }

        foreach ($candidates as $candidate) {
            $match = $this->bestMunicipioMatchInText($this->normalizeMunicipioName($candidate), $municipios);
            if ($match !== null) {
                return $match + ['source' => 'address'];
            }
        }

        if (is_string($municipioHint) && trim($municipioHint) !== '') {
            $hintNorm = $this->normalizeMunicipioName($municipioHint);
            $fromHint = $this->bestMunicipioMatchInText($hintNorm, $municipios, true);
            if ($fromHint !== null) {
                return $fromHint + ['source' => 'query_hint'];
            }
        }

        return null;
    }

    /**
     * Prefiere igualdad exacta; si no, la coincidencia por subcadena más larga (evita asignar "CHOLULA" antes que "SAN PEDRO CHOLULA").
     *
     * @param  array<int, array<string, mixed>>  $municipios
     * @return array{municipio_id:int, municipio_nombre:string}|null
     */
    private function bestMunicipioMatchInText(string $normalizedHaystack, array $municipios, bool $allowFuzzyHint = false): ?array
    {
        if ($normalizedHaystack === '') {
            return null;
        }

        foreach ($municipios as $municipio) {
            $normalizedMunicipio = (string) $municipio['normalized'];
            if ($normalizedMunicipio !== '' && $normalizedHaystack === $normalizedMunicipio) {
                return [
                    'municipio_id' => (int) $municipio['id'],
                    'municipio_nombre' => (string) $municipio['municipio'],
                ];
            }
        }

        $best = null;
        $bestLen = 0;
        $minLen = $allowFuzzyHint ? 4 : 5;

        foreach ($municipios as $municipio) {
            $normalizedMunicipio = (string) $municipio['normalized'];
            $len = mb_strlen($normalizedMunicipio, 'UTF-8');
            if ($len < $minLen) {
                continue;
            }

            if (! str_contains($normalizedHaystack, $normalizedMunicipio)) {
                continue;
            }

            if ($len > $bestLen) {
                $bestLen = $len;
                $best = $municipio;
            }
        }

        if ($best !== null) {
            return [
                'municipio_id' => (int) $best['id'],
                'municipio_nombre' => (string) $best['municipio'],
            ];
        }

        if ($allowFuzzyHint && mb_strlen($normalizedHaystack, 'UTF-8') >= 4) {
            $bestContain = null;
            $bestContainLen = 0;
            foreach ($municipios as $municipio) {
                $n = (string) $municipio['normalized'];
                if ($n === '' || ! str_contains($n, $normalizedHaystack)) {
                    continue;
                }
                $len = mb_strlen($n, 'UTF-8');
                if ($len > $bestContainLen) {
                    $bestContainLen = $len;
                    $bestContain = $municipio;
                }
            }
            if ($bestContain !== null) {
                return [
                    'municipio_id' => (int) $bestContain['id'],
                    'municipio_nombre' => (string) $bestContain['municipio'],
                ];
            }

            return $this->fuzzyMunicipioMatch($normalizedHaystack, $municipios);
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $municipios
     * @return array{municipio_id:int, municipio_nombre:string}|null
     */
    private function fuzzyMunicipioMatch(string $normalizedHint, array $municipios): ?array
    {
        if (mb_strlen($normalizedHint, 'UTF-8') < 4) {
            return null;
        }

        $best = null;
        $bestPct = 0.0;

        foreach ($municipios as $municipio) {
            $n = (string) $municipio['normalized'];
            if ($n === '' || mb_strlen($n, 'UTF-8') < 4) {
                continue;
            }

            similar_text($normalizedHint, $n, $pct);
            if ($pct < 70.0) {
                continue;
            }

            if ($pct > $bestPct) {
                $bestPct = $pct;
                $best = $municipio;
            } elseif ($pct === $bestPct && $best !== null && mb_strlen($n, 'UTF-8') > mb_strlen((string) $best['normalized'], 'UTF-8')) {
                $best = $municipio;
            }
        }

        if ($best === null) {
            return null;
        }

        return [
            'municipio_id' => (int) $best['id'],
            'municipio_nombre' => (string) $best['municipio'],
        ];
    }

    /**
     * Si el usuario escribe "calle X, Atlixco" o "colonia Y — Tehuacán", usa el último segmento como pista de municipio.
     */
    private function parseMunicipioHintFromQuery(string $query): ?string
    {
        $query = trim($query);
        if ($query === '' || ! preg_match('/[,;|]/u', $query)) {
            return null;
        }

        $parts = preg_split('/\s*[,;|]\s*/u', $query) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), fn ($p) => $p !== ''));
        if (count($parts) < 2) {
            return null;
        }

        $hint = $parts[count($parts) - 1];
        if (mb_strlen($hint, 'UTF-8') < 3) {
            return null;
        }

        $hintLower = mb_strtolower($hint, 'UTF-8');
        if (preg_match('/^(puebla|mexico|méxico|mx)$/u', $hintLower)) {
            return null;
        }

        return $hint;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function looksLikePuebla(array $row): bool
    {
        $address = $row['address'] ?? null;
        if (! is_array($address)) {
            $displayName = mb_strtoupper((string) ($row['display_name'] ?? ''), 'UTF-8');

            return $displayName === '' || str_contains($displayName, 'PUEBLA');
        }

        $state = mb_strtoupper((string) ($address['state'] ?? ''), 'UTF-8');

        return $state === '' || str_contains($state, 'PUEBLA');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function buildPlaceLabel(array $row, string $municipioNombreResuelto): string
    {
        $address = is_array($row['address'] ?? null) ? $row['address'] : [];

        $main = $address['road']
            ?? $address['pedestrian']
            ?? $address['path']
            ?? $address['footway']
            ?? $address['suburb']
            ?? $address['neighbourhood']
            ?? $address['village']
            ?? $address['hamlet']
            ?? $row['name']
            ?? $row['display_name']
            ?? 'Resultado';

        $secondary = $address['suburb']
            ?? $address['neighbourhood']
            ?? $address['city_district']
            ?? $address['hamlet']
            ?? null;

        $parts = [trim((string) $main)];
        if (is_string($secondary) && trim($secondary) !== '' && mb_strtolower($secondary, 'UTF-8') !== mb_strtolower((string) $main, 'UTF-8')) {
            $parts[] = trim($secondary);
        }

        $mun = trim($municipioNombreResuelto);
        if ($mun !== '' && ! in_array(mb_strtolower($mun, 'UTF-8'), array_map(fn ($p) => mb_strtolower($p, 'UTF-8'), $parts), true)) {
            $parts[] = $mun;
        }

        return implode(' · ', array_filter($parts));
    }

    /**
     * @param  array<string, mixed>  $geometry
     */
    private function pointInGeometry(float $lng, float $lat, array $geometry): bool
    {
        $type = $geometry['type'] ?? null;
        $coordinates = $geometry['coordinates'] ?? null;

        if (! is_string($type) || ! is_array($coordinates)) {
            return false;
        }

        if ($type === 'Polygon') {
            return $this->pointInPolygon($lng, $lat, $coordinates);
        }

        if ($type === 'MultiPolygon') {
            foreach ($coordinates as $polygon) {
                if (is_array($polygon) && $this->pointInPolygon($lng, $lat, $polygon)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $rings
     */
    private function pointInPolygon(float $lng, float $lat, array $rings): bool
    {
        if (! isset($rings[0]) || ! is_array($rings[0]) || ! $this->pointInRing($lng, $lat, $rings[0])) {
            return false;
        }

        foreach (array_slice($rings, 1) as $hole) {
            if (is_array($hole) && $this->pointInRing($lng, $lat, $hole)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, mixed>  $ring
     */
    private function pointInRing(float $lng, float $lat, array $ring): bool
    {
        $inside = false;
        $count = count($ring);

        if ($count < 3) {
            return false;
        }

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $a = $ring[$i] ?? null;
            $b = $ring[$j] ?? null;
            if (! is_array($a) || ! is_array($b) || ! isset($a[0], $a[1], $b[0], $b[1])) {
                continue;
            }

            $xi = (float) $a[0];
            $yi = (float) $a[1];
            $xj = (float) $b[0];
            $yj = (float) $b[1];

            $intersects = (($yi > $lat) !== ($yj > $lat))
                && ($lng < (($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 0.0000001) + $xi));

            if ($intersects) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    private function normalizeMunicipioName(string $value): string
    {
        $value = mb_strtoupper(trim($value), 'UTF-8');
        $value = strtr($value, [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'Ü' => 'U',
            'Ñ' => 'N',
        ]);
        $value = preg_replace('/\b(MUNICIPIO|MUNICIPIO DE|H\. AYUNTAMIENTO DE)\b/u', ' ', $value) ?? $value;
        $value = preg_replace('/[^A-Z0-9 ]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * @return array<int, string>
     */
    private function buildSearchQueries(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $variants = [];
        $variants[] = $query;

        $expanded = $this->expandAbbreviations($query);
        if ($expanded !== $query) {
            $variants[] = $expanded;
        }

        if ($this->looksLikeJuntaAuxiliarSearch($expanded)) {
            $juntaVariant = $this->ensureJuntaAuxiliarPrefix($expanded);
            if (! in_array($juntaVariant, $variants, true)) {
                $variants[] = $juntaVariant;
            }
        }

        $commaParts = preg_split('/\s*,\s*/u', $query, 2);
        if (count($commaParts) === 2) {
            $left = trim($commaParts[0]);
            $right = trim($commaParts[1]);
            if ($left !== '' && $right !== '' && mb_strlen($right, 'UTF-8') >= 3) {
                $structured = $left.', '.$right;
                if (! in_array($structured, $variants, true)) {
                    $variants[] = $structured;
                }
                $structuredSwapped = $right.' '.$left;
                if (mb_strlen($left, 'UTF-8') >= 8 && ! in_array($structuredSwapped, $variants, true)) {
                    $variants[] = $structuredSwapped;
                }
            }
        }

        $withoutPunctuation = preg_replace('/[.,;:#\-]+/u', ' ', $expanded) ?? $expanded;
        $withoutPunctuation = preg_replace('/\s+/u', ' ', trim($withoutPunctuation)) ?? trim($withoutPunctuation);
        if ($withoutPunctuation !== '' && ! in_array($withoutPunctuation, $variants, true)) {
            $variants[] = $withoutPunctuation;
        }

        $referenceVariant = $this->stripLeadingPlaceType($withoutPunctuation);
        if ($referenceVariant !== '' && ! in_array($referenceVariant, $variants, true)) {
            $variants[] = $referenceVariant;
        }

        $tokenCollapsed = $this->collapseStopWords($withoutPunctuation);
        if ($tokenCollapsed !== '' && ! in_array($tokenCollapsed, $variants, true)) {
            $variants[] = $tokenCollapsed;
        }

        return array_values(array_filter($variants, fn ($value) => mb_strlen(trim($value), 'UTF-8') >= 2));
    }

    private function expandAbbreviations(string $query): string
    {
        $patterns = [
            '/\bC\.?\b/ui' => 'calle',
            '/\bCLL\.?\b/ui' => 'calle',
            '/\bCOL\.?\b/ui' => 'colonia',
            '/\bAV\.?\b/ui' => 'avenida',
            '/\bAVDA\.?\b/ui' => 'avenida',
            '/\bPROL\.?\b/ui' => 'prolongacion',
            '/\bPRIV\.?\b/ui' => 'privada',
            '/\bFRACC\.?\b/ui' => 'fraccionamiento',
            '/\bFRAC\.?\b/ui' => 'fraccionamiento',
            '/\bBARR\.?\b/ui' => 'barrio',
            '/\bBO\.?\b/ui' => 'barrio',
            '/\bJ\.?\s*A\.?\b/ui' => 'junta auxiliar',
            '/\bJTA\.?\b/ui' => 'junta auxiliar',
            '/\bHDA\.?\b/ui' => 'hacienda',
            '/\bEXHDA\.?\b/ui' => 'ex hacienda',
            '/\bEX[-\s]?HAC\.?\b/ui' => 'ex hacienda',
            '/\bU\.?\s*H\.?\b/ui' => 'unidad habitacional',
            '/\bCARR\.?\b/ui' => 'carretera',
            '/\bCTRA\.?\b/ui' => 'carretera',
            '/\bCTTE\.?\b/ui' => 'carretera',
            '/\bCARRET\.?\b/ui' => 'carretera',
            '/\bLIBR\.?\b/ui' => 'libramiento',
            '/\bKM\.?\b/ui' => 'kilometro',
        ];

        $expanded = $query;
        foreach ($patterns as $pattern => $replacement) {
            $expanded = preg_replace($pattern, $replacement, $expanded) ?? $expanded;
        }

        $expanded = preg_replace('/\s+/u', ' ', trim($expanded)) ?? trim($expanded);

        return $expanded;
    }

    private function stripLeadingPlaceType(string $query): string
    {
        $stripped = preg_replace(
            '/^\b(calle|colonia|avenida|privada|prolongacion|fraccionamiento|barrio|junta auxiliar|unidad habitacional|carretera|libramiento|referencia|lugar|parque|mercado)\b\s+/ui',
            '',
            $query
        ) ?? $query;

        return trim($stripped);
    }

    private function collapseStopWords(string $query): string
    {
        $collapsed = preg_replace('/\b(de|del|la|las|el|los|y)\b/ui', ' ', $query) ?? $query;
        $collapsed = preg_replace('/\s+/u', ' ', trim($collapsed)) ?? trim($collapsed);

        return $collapsed;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function extractResultGeometry(array $row): ?array
    {
        $geometry = $row['geojson'] ?? null;
        if (! is_array($geometry)) {
            return null;
        }

        $type = $geometry['type'] ?? null;
        if (! in_array($type, ['Polygon', 'MultiPolygon'], true)) {
            return null;
        }

        return $geometry;
    }

    private function looksLikeJuntaAuxiliarSearch(string $query): bool
    {
        $normalized = mb_strtolower($query, 'UTF-8');

        return str_contains($normalized, 'junta auxiliar')
            || preg_match('/\bjta\.?\b/u', $normalized) === 1
            || preg_match('/\bj\.?\s*a\.?\b/u', $normalized) === 1;
    }

    private function ensureJuntaAuxiliarPrefix(string $query): string
    {
        if (preg_match('/\bjunta auxiliar\b/ui', $query) === 1) {
            return trim($query);
        }

        return 'junta auxiliar '.trim($query);
    }
}
