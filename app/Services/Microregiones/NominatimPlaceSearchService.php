<?php

namespace App\Services\Microregiones;

use App\Models\Municipio;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NominatimPlaceSearchService
{
    private const CACHE_PREFIX = 'microregiones_place_search_v1_';
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
            $rows = $this->requestPlaces($query, max($limit * 2, 8));
            if ($rows === []) {
                return [];
            }

            $municipios = $this->loadMunicipiosPuebla();
            $results = [];
            $seen = [];

            foreach ($rows as $row) {
                $resolved = $this->resolveMunicipio($row, $municipios);
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
                    'geometry' => $this->resolveResultGeometry($row),
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
     * @return array<int, array<string, mixed>>
     */
    private function requestPlaces(string $query, int $limit): array
    {
        $queries = array_slice($this->buildSearchQueries($query), 0, 4);
        $results = [];
        $seen = [];

        foreach ($queries as $variant) {
            try {
                $response = Http::connectTimeout(3)
                    ->timeout(8)
                    ->retry(1, 250)
                    ->withHeaders([
                        'User-Agent' => 'SegobMicroregiones/1.0 (busqueda territorial interna)',
                        'Accept-Language' => 'es',
                    ])
                    ->get('https://nominatim.openstreetmap.org/search', [
                        'q' => $variant.', Puebla, México',
                        'format' => 'jsonv2',
                        'limit' => $limit,
                        'countrycodes' => 'mx',
                        'addressdetails' => 1,
                        'polygon_geojson' => 1,
                        'viewbox' => self::PUEBLA_VIEWBOX,
                        'bounded' => 1,
                    ]);

                if (! $response->successful()) {
                    continue;
                }

                $rows = $response->json();
                if (! is_array($rows)) {
                    continue;
                }

                foreach ($rows as $row) {
                    if (! is_array($row) || ! $this->looksLikePuebla($row)) {
                        continue;
                    }

                    $uniqueKey = (string) ($row['place_id'] ?? $row['osm_id'] ?? md5((string) ($row['display_name'] ?? '')));
                    if (isset($seen[$uniqueKey])) {
                        continue;
                    }

                    $seen[$uniqueKey] = true;
                    $results[] = $row;

                    if (count($results) >= $limit) {
                        return $results;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Nominatim place search: '.$e->getMessage());
            }
        }

        return $results;
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
    private function resolveMunicipio(array $row, array $municipios): ?array
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

        foreach (['municipality', 'county', 'city', 'town', 'village', 'state_district'] as $key) {
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
            $normalizedCandidate = $this->normalizeMunicipioName($candidate);
            foreach ($municipios as $municipio) {
                $normalizedMunicipio = (string) $municipio['normalized'];
                if ($normalizedCandidate === $normalizedMunicipio || str_contains($normalizedCandidate, $normalizedMunicipio)) {
                    return [
                        'municipio_id' => (int) $municipio['id'],
                        'municipio_nombre' => (string) $municipio['municipio'],
                        'source' => 'address',
                    ];
                }
            }
        }

        return null;
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
    private function buildPlaceLabel(array $row, string $municipio): string
    {
        $address = is_array($row['address'] ?? null) ? $row['address'] : [];

        $main = $address['road']
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
            '/^\b(calle|colonia|avenida|privada|prolongacion|fraccionamiento|barrio|junta auxiliar|unidad habitacional|referencia|lugar|parque|mercado)\b\s+/ui',
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

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function resolveResultGeometry(array $row): ?array
    {
        $geometry = $this->extractResultGeometry($row);
        if ($geometry !== null) {
            return $geometry;
        }

        return $this->searchFallbackGeometry($row);
    }

    private function looksLikeJuntaAuxiliarSearch(string $query): bool
    {
        $normalized = mb_strtolower($query, 'UTF-8');

        return str_contains($normalized, 'junta auxiliar')
            || preg_match('/\b(auxiliar|jta|j a)\b/u', $normalized) === 1
            || preg_match('/\b(san|santa|santo)\b/u', $normalized) === 1;
    }

    private function ensureJuntaAuxiliarPrefix(string $query): string
    {
        if (preg_match('/\bjunta auxiliar\b/ui', $query) === 1) {
            return trim($query);
        }

        return 'junta auxiliar '.trim($query);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function searchFallbackGeometry(array $row): ?array
    {
        $address = is_array($row['address'] ?? null) ? $row['address'] : [];
        $name = trim((string) (
            $address['suburb']
            ?? $address['neighbourhood']
            ?? $address['quarter']
            ?? $address['city_district']
            ?? $address['hamlet']
            ?? $address['village']
            ?? $row['name']
            ?? ''
        ));

        if ($name === '') {
            return null;
        }

        $municipio = trim((string) (
            $address['city']
            ?? $address['town']
            ?? $address['municipality']
            ?? $address['county']
            ?? 'Puebla'
        ));

        $queries = [
            $name.', '.$municipio,
            'colonia '.$name.', '.$municipio,
            'barrio '.$name.', '.$municipio,
            'fraccionamiento '.$name.', '.$municipio,
            'junta auxiliar '.$name.', '.$municipio,
        ];

        $seen = [];

        foreach ($queries as $query) {
            $query = trim($query);
            if ($query === '' || isset($seen[$query])) {
                continue;
            }

            $seen[$query] = true;

            try {
                $response = Http::timeout(20)
                    ->withHeaders([
                        'User-Agent' => 'SegobMicroregiones/1.0 (busqueda territorial interna)',
                        'Accept-Language' => 'es',
                    ])
                    ->get('https://nominatim.openstreetmap.org/search', [
                        'q' => $query.', Puebla, México',
                        'format' => 'jsonv2',
                        'limit' => 6,
                        'countrycodes' => 'mx',
                        'addressdetails' => 1,
                        'polygon_geojson' => 1,
                        'viewbox' => self::PUEBLA_VIEWBOX,
                        'bounded' => 1,
                    ]);

                if (! $response->successful()) {
                    continue;
                }

                $rows = $response->json();
                if (! is_array($rows)) {
                    continue;
                }

                foreach ($rows as $candidate) {
                    if (! is_array($candidate) || ! $this->looksLikePuebla($candidate)) {
                        continue;
                    }

                    $candidateGeometry = $this->extractResultGeometry($candidate);
                    if ($candidateGeometry === null) {
                        continue;
                    }

                    if (! $this->candidateMatchesPlaceName($candidate, $name)) {
                        continue;
                    }

                    return $candidateGeometry;
                }
            } catch (\Throwable $e) {
                Log::warning('Nominatim fallback geometry: '.$e->getMessage());
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function candidateMatchesPlaceName(array $candidate, string $expectedName): bool
    {
        $normalizedExpected = $this->normalizeLooseText($expectedName);
        if ($normalizedExpected === '') {
            return false;
        }

        $address = is_array($candidate['address'] ?? null) ? $candidate['address'] : [];
        $names = array_filter([
            $candidate['name'] ?? null,
            $candidate['display_name'] ?? null,
            $address['suburb'] ?? null,
            $address['neighbourhood'] ?? null,
            $address['quarter'] ?? null,
            $address['city_district'] ?? null,
            $address['hamlet'] ?? null,
            $address['village'] ?? null,
        ], fn ($value) => is_string($value) && trim($value) !== '');

        foreach ($names as $name) {
            $normalizedCandidate = $this->normalizeLooseText((string) $name);
            if ($normalizedCandidate === $normalizedExpected || str_contains($normalizedCandidate, $normalizedExpected) || str_contains($normalizedExpected, $normalizedCandidate)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeLooseText(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
        $value = preg_replace('/[^a-z0-9 ]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\b(colonia|barrio|fraccionamiento|junta auxiliar|unidad habitacional)\b/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
