<?php

namespace App\Http\Controllers;

use App\Models\Delegado;
use App\Models\Microrregione;
use App\Models\Municipio;
use App\Services\Microregiones\MunicipioGeocodeService;
use App\Services\Microregiones\NominatimPlaceSearchService;
use App\Services\Microregiones\PueblaMunicipioBoundaryService;
use App\Services\Microregiones\PueblaStateBounds;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MicroregionesController extends Controller
{
    public function __construct(
        private readonly MunicipioGeocodeService $geocode,
        private readonly PueblaMunicipioBoundaryService $boundary,
        private readonly NominatimPlaceSearchService $placeSearch,
    ) {}

    public function index(Request $request): View|JsonResponse|Response
    {
        $query = trim((string) $request->query('q', ''));
        if ($query !== '' && ($request->expectsJson() || $request->ajax())) {
            return response()->json([
                'results' => $this->buildSearchResults($query),
            ]);
        }

        $municipioBoundaryCount = Municipio::query()
            ->whereNotNull('microrregion_id')
            ->where(function ($q) {
                $q->whereIn('cve_edo', ['21', '021', 21])
                    ->orWhereRaw('CAST(cve_edo AS UNSIGNED) = ?', [21]);
            })
            ->whereNotNull('geojson_boundary')
            ->where('geojson_boundary', '!=', '')
            ->count();

        return view('microregiones.index', [
            'pageTitle' => 'Microrregiones',
            'microregionesBootstrap' => $this->buildMapDataResponse(),
            'municipioBoundaryCount' => $municipioBoundaryCount,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildMapDataResponse(): array
    {
        $out = $this->buildMicrorregionesPayload();
        $boundaries = $this->buildBoundariesPayload();

        $b1 = route('microregiones.boundaries', [], false);
        $b2 = route('microregiones.map-limits', [], false);
        $s1 = route('microregiones.map-search', [], false);
        $s2 = route('microregiones.search', [], false);

        return [
            'microrregiones' => $out,
            'map' => [
                'center' => [19.0, -97.75],
                'zoom' => 8,
                'attribution' => '© OpenStreetMap',
                'puebla_bounds' => PueblaStateBounds::asArray(),
            ],
            'boundaries_url' => $b1,
            'boundaries_urls' => $b1 === $b2 ? [$b1] : [$b1, $b2],
            'boundaries_bootstrap' => $boundaries,
            'search_url' => $s1,
            'search_urls' => $s1 === $s2 ? [$s1] : [$s1, $s2],
        ];
    }

    /**
     * @return array{municipios: array<int, array{id: int, micro_id: int, nombre: string, geometry: array<string, mixed>}>, meta: array{geometry_count: int, hint: ?string}}
     */
    protected function buildBoundariesPayload(): array
    {
        $rows = [];

        $ids = Municipio::query()
            ->whereNotNull('microrregion_id')
            ->where(function ($q) {
                $q->whereIn('cve_edo', ['21', '021', 21])
                    ->orWhereRaw('CAST(cve_edo AS UNSIGNED) = ?', [21]);
            })
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $id) {
            $mun = Municipio::query()->find($id);
            if (! $mun) {
                continue;
            }

            $geom = $this->boundary->getCachedPolygonGeometry($mun);
            if ($geom === null) {
                continue;
            }

            $rows[] = [
                'id' => (int) $mun->id,
                'micro_id' => (int) $mun->microrregion_id,
                'nombre' => (string) $mun->municipio,
                'geometry' => $geom,
            ];
        }

        return [
            'municipios' => $rows,
            'meta' => [
                'geometry_count' => count($rows),
                'hint' => count($rows) === 0
                    ? 'No hay polígonos de municipios en la base de datos. En el servidor ejecute: php artisan microregiones:fetch-boundaries'
                    : null,
            ],
        ];
    }

    /**
     * Datos para mapa y panel lateral (municipios del estado de Puebla en BD).
     */
    public function data(): JsonResponse
    {
        return response()->json($this->buildMapDataResponse());
    }

    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        return response()->json([
            'results' => $this->buildSearchResults($query),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildSearchResults(string $query): array
    {
        if (mb_strlen($query) < 3) {
            return [];
        }

        $placeResults = $this->placeSearch->searchInPuebla($query, 5);
        if ($placeResults === []) {
            return [];
        }

        $micros = collect($this->buildMicrorregionesPayload())->keyBy('id');
        $municipiosById = [];

        foreach ($micros as $micro) {
            foreach ($micro['municipios'] as $municipio) {
                $municipiosById[(int) $municipio['id']] = $municipio + [
                    'micro' => $micro,
                ];
            }
        }

        $results = [];

        foreach ($placeResults as $item) {
            $municipio = $municipiosById[(int) $item['municipio_id']] ?? null;
            if ($municipio === null) {
                continue;
            }

            $micro = $municipio['micro'];

            $results[] = [
                'display_name' => $item['display_name'],
                'label' => $item['label'],
                'lat' => $item['lat'],
                'lng' => $item['lng'],
                'type' => $item['type'],
                'osm_type' => $item['osm_type'],
                'geometry' => $item['geometry'],
                'resolution_source' => $item['resolution_source'],
                'municipio' => [
                    'id' => (int) $municipio['id'],
                    'nombre' => $municipio['nombre'],
                ],
                'micro' => [
                    'id' => (int) $micro['id'],
                    'numero' => $micro['numero'],
                    'micro_label' => $micro['micro_label'],
                    'cabecera' => $micro['cabecera'],
                ],
                'delegado' => $micro['delegado'],
                'enlace' => $micro['enlace'],
            ];
        }

        return $results;
    }

    /**
     * PolÃ­gonos de municipios (Puebla) ya resueltos y guardados en cachÃ© (Nominatim).
     */
    public function boundaries(): JsonResponse
    {
        return response()->json($this->buildBoundariesPayload());
    }

    private function resolveSeccionPngUrl(string $num): ?string
    {
        // Try both padded (MR01) and unpadded (MR1) naming, and both .png/.jpg extensions
        $variants = ['MR'.$num, 'MR'.ltrim($num, '0')];
        $extensions = ['png', 'jpg'];

        foreach (['SeccionesPNG', 'seccionesPNG'] as $dir) {
            foreach ($variants as $name) {
                foreach ($extensions as $ext) {
                    $rel = 'images/'.$dir.'/'.$name.'.'.$ext;
                    if (file_exists(public_path($rel))) {
                        return asset($rel);
                    }
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildMicrorregionesPayload(): array
    {
        $micros = Microrregione::query()
            ->whereHas('municipios', static function ($q) {
                $q->where(function ($q2) {
                    $q2->whereIn('cve_edo', ['21', '021', 21])
                        ->orWhereRaw('CAST(cve_edo AS UNSIGNED) = ?', [21]);
                });
            })
            ->with([
                'municipios' => static function ($q) {
                    $q->select(['id', 'municipio', 'microrregion_id', 'cve_edo', 'cve_inegi', 'lat', 'lng'])
                        ->where(function ($q2) {
                            $q2->whereIn('cve_edo', ['21', '021', 21])
                                ->orWhereRaw('CAST(cve_edo AS UNSIGNED) = ?', [21]);
                        })
                        ->orderBy('municipio');
                },
            ])
            ->orderByRaw('CAST(microrregion AS UNSIGNED)')
            ->get();

        $delegadosByMicro = Delegado::query()
            ->select(['delegados.id', 'delegados.user_id', 'delegados.microrregion_id', 'delegados.nombre', 'delegados.ap_paterno', 'delegados.ap_materno', 'delegados.telefono', 'delegados.email'])
            ->join('users', 'users.id', '=', 'delegados.user_id')
            ->where('users.activo', 1)
            ->where('users.name', 'NOT LIKE', '%GESTION DE ENLACES%')
            ->orderBy('delegados.id')
            ->get()
            ->groupBy('microrregion_id');

        $userRows = DB::table('user_microrregion')
            ->join('users', 'users.id', '=', 'user_microrregion.user_id')
            ->where('users.activo', 1)
            ->where('users.name', 'NOT LIKE', '%GESTION DE ENLACES%')
            ->select(['user_microrregion.microrregion_id', 'users.id as user_id', 'users.name', 'users.email'])
            ->orderBy('users.name')
            ->get()
            ->groupBy('microrregion_id');

        $out = [];

        foreach ($micros as $micro) {
            $num = trim((string) $micro->microrregion);
            $imgUrl = $this->resolveSeccionPngUrl($num);

            $delegadoRow = optional($delegadosByMicro->get($micro->id)?->first());
            $delegadoUserId = $delegadoRow ? (int) $delegadoRow->user_id : null;

            $enlace = null;
            $usersMicro = $userRows->get($micro->id) ?? collect();
            foreach ($usersMicro as $u) {
                if ($delegadoUserId !== null && (int) $u->user_id === $delegadoUserId) {
                    continue;
                }

                $userName = mb_strtoupper((string) $u->name);
                if (str_contains($userName, 'GESTION DE ENLACES')) {
                    continue;
                }

                $enlace = [
                    'nombre' => (string) $u->name,
                    'email' => (string) $u->email,
                ];
                break;
            }

            $municipios = [];
            foreach ($micro->municipios as $mun) {
                $coords = $this->geocode->coordinatesFor($mun);
                $municipios[] = [
                    'id' => (int) $mun->id,
                    'micro_id' => (int) $micro->id,
                    'nombre' => (string) $mun->municipio,
                    'cve_inegi' => $mun->cve_inegi !== null ? (string) $mun->cve_inegi : null,
                    'lat' => $coords['lat'],
                    'lng' => $coords['lng'],
                    'geo_source' => $coords['source'],
                ];
            }

            $out[] = [
                'id' => (int) $micro->id,
                'numero' => $num,
                'micro_label' => 'MR'.$num,
                'cabecera' => (string) ($micro->cabecera ?? ''),
                'image_url' => $imgUrl,
                'delegado' => $delegadoRow ? [
                    'nombre' => trim($delegadoRow->nombre.' '.$delegadoRow->ap_paterno.' '.$delegadoRow->ap_materno),
                    'telefono' => (string) ($delegadoRow->telefono ?? ''),
                    'email' => (string) ($delegadoRow->email ?? ''),
                ] : null,
                'enlace' => $enlace,
                'municipios' => $municipios,
            ];
        }

        return $out;
    }
}
