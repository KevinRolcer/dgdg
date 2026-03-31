<?php

namespace App\Services\Microregiones;

use App\Models\Municipio;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Límites administrativos de municipios de Puebla vía Nominatim (OSM).
 * Los resultados se guardan en caché para no repetir peticiones.
 *
 * @see https://operations.osmfoundation.org/policies/nominatim/
 */
class PueblaMunicipioBoundaryService
{
    private const CACHE_PREFIX = 'municipio_geojson_puebla_v1_';

    public function isPueblaMunicipio(Municipio $m): bool
    {
        $cve = trim((string) ($m->cve_edo ?? ''));

        return in_array($cve, ['21', '021', '21.0'], true) || (int) $cve === 21;
    }

    /**
     * GeoJSON geometry (Polygon o MultiPolygon) o null si no hay caché.
     *
     * @return array<string, mixed>|null
     */
    public function getCachedPolygonGeometry(Municipio $m): ?array
    {
        // 1. Primary: Database (permanence)
        if (!empty($m->geojson_boundary)) {
            $data = json_decode($m->geojson_boundary, true);
            return is_array($data) ? $data : null;
        }

        // 2. Secondary: Legacy Cache
        $raw = Cache::get(self::CACHE_PREFIX.$m->id);
        if (! is_array($raw) || empty($raw['geometry'])) {
            return null;
        }

        return $raw['geometry'];
    }

    /**
     * @return array{geometry: array, osm_id: int|string|null, display_name: string|null}|null
     */
    public function fetchAndStoreFromNominatim(Municipio $m): ?array
    {
        if (! $this->isPueblaMunicipio($m)) {
            return null;
        }

        $result = $this->requestFromNominatim($m);
        if ($result === null) {
            return null;
        }

        // Save to DB
        $m->geojson_boundary = json_encode($result['geometry']);
        $m->save();

        // Also save to cache for safety
        Cache::forever(self::CACHE_PREFIX.$m->id, $result);

        return $result;
    }

    /**
     * @return array{geometry: array, osm_id: int|string|null, display_name: string|null}|null
     */
    private function requestFromNominatim(Municipio $m): ?array
    {
        $name = trim((string) $m->municipio);
        if ($name === '') {
            return null;
        }

        $queries = [
            $name.', Puebla, México',
            $name.', Puebla, Mexico',
        ];

        foreach ($queries as $q) {
            try {
                $response = Http::timeout(28)
                    ->withHeaders([
                        'User-Agent' => 'SegobMicroregiones/1.0 (uso interno)',
                        'Accept-Language' => 'es',
                    ])
                    ->get('https://nominatim.openstreetmap.org/search', [
                        'q' => $q,
                        'format' => 'json',
                        'polygon_geojson' => 1,
                        'limit' => 3,
                        'countrycodes' => 'mx',
                        'addressdetails' => 1,
                    ]);

                if (! $response->successful()) {
                    continue;
                }

                $rows = $response->json();
                if (! is_array($rows)) {
                    continue;
                }

                foreach ($rows as $row) {
                    $geojson = $row['geojson'] ?? null;
                    if (! is_array($geojson)) {
                        continue;
                    }

                    $type = $geojson['type'] ?? '';
                    if (! in_array($type, ['Polygon', 'MultiPolygon'], true)) {
                        continue;
                    }

                    if (! $this->rowLooksLikePuebla($row)) {
                        continue;
                    }

                    return [
                        'geometry' => $geojson,
                        'osm_id' => $row['osm_id'] ?? ($row['place_id'] ?? null),
                        'display_name' => isset($row['display_name']) ? (string) $row['display_name'] : null,
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('Nominatim boundary: '.$e->getMessage());
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowLooksLikePuebla(array $row): bool
    {
        $addr = $row['address'] ?? null;
        if (! is_array($addr)) {
            return true;
        }

        $state = mb_strtoupper((string) ($addr['state'] ?? ''), 'UTF-8');

        return $state === '' || str_contains($state, 'PUEBLA');
    }
}
