<?php

namespace App\Services\Microregiones;

use App\Models\Municipio;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MunicipioGeocodeService
{

    /**
     * Coordenadas para el mapa: primero caché (rellenada por comando artisan o geocodificación online),
     * si no hay, posición determinística para que el mapa no quede vacío.
     *
     * @return array{lat: float, lng: float, source: string}
     */
    public function coordinatesFor(Municipio $m): array
    {
        // 1. Primary: Database columns (permanence)
        if ($m->lat !== null && $m->lng !== null) {
            return [
                'lat' => (float) $m->lat,
                'lng' => (float) $m->lng,
                'source' => 'db',
            ];
        }

        // 2. Secondary: Legacy Cache
        $key = 'municipio_geo_v1_'.$m->id;
        $cached = Cache::get($key);
        if (is_array($cached) && isset($cached['lat'], $cached['lng'])) {
            return [
                'lat' => (float) $cached['lat'],
                'lng' => (float) $cached['lng'],
                'source' => 'cache',
            ];
        }

        return $this->deterministicFallback($m) + ['source' => 'fallback'];
    }

    /**
     * Geocodificación online (Nominatim). Respetar políticas de uso; usar desde comando o tareas batch.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function fetchAndStoreFromNominatim(Municipio $m): ?array
    {
        $coords = $this->fetchFromNominatim($m);
        if ($coords === null) {
            return null;
        }

        // Save to DB (permanence)
        $m->lat = $coords['lat'];
        $m->lng = $coords['lng'];
        $m->save();

        $key = 'municipio_geo_v1_'.$m->id;
        Cache::forever($key, $coords);

        return $coords;
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function fetchFromNominatim(Municipio $m): ?array
    {
        $estado = $this->estadoLabel((string) ($m->cve_edo ?? ''));
        $q = trim((string) $m->municipio).', '.$estado.', México';

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'User-Agent' => 'SegobMicroregiones/1.0 (uso interno)',
                    'Accept-Language' => 'es',
                ])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $q,
                    'format' => 'json',
                    'limit' => 1,
                    'addressdetails' => 0,
                ]);

            if (! $response->successful()) {
                return null;
            }

            $rows = $response->json();
            if (! is_array($rows) || $rows === []) {
                return null;
            }

            $lat = isset($rows[0]['lat']) ? (float) $rows[0]['lat'] : null;
            $lon = isset($rows[0]['lon']) ? (float) $rows[0]['lon'] : null;
            if ($lat === null || $lon === null) {
                return null;
            }

            return ['lat' => $lat, 'lng' => $lon];
        } catch (\Throwable $e) {
            Log::warning('Nominatim geocode falló: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Posición determinística repartida en todo el rectángulo del estado (evita amontonar
     * todos los municipios en la zona metropolana cuando aún no hay caché de Nominatim).
     *
     * @return array{lat: float, lng: float}
     */
    private function deterministicFallback(Municipio $m): array
    {
        $id = (int) $m->id;
        $u1 = (($id * 1103515245 + 12345) & 0x7fffffff) / 2147483647.0;
        $u2 = (($id * 49231823 + 54321) & 0x7fffffff) / 2147483647.0;

        $lat = PueblaStateBounds::SOUTH + $u1 * (PueblaStateBounds::NORTH - PueblaStateBounds::SOUTH);
        $lng = PueblaStateBounds::WEST + $u2 * (PueblaStateBounds::EAST - PueblaStateBounds::WEST);

        return ['lat' => $lat, 'lng' => $lng];
    }

    private function estadoLabel(string $cve): string
    {
        $map = [
            '21' => 'Puebla',
        ];

        return $map[$cve] ?? 'Puebla';
    }
}
