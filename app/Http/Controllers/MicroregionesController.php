<?php

namespace App\Http\Controllers;

use App\Models\Delegado;
use App\Models\Microrregione;
use App\Models\Municipio;
use App\Services\Microregiones\MunicipioGeocodeService;
use App\Services\Microregiones\PueblaMunicipioBoundaryService;
use App\Services\Microregiones\PueblaStateBounds;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MicroregionesController extends Controller
{
    public function __construct(
        private readonly MunicipioGeocodeService $geocode,
        private readonly PueblaMunicipioBoundaryService $boundary,
    ) {
    }

    public function index(): View
    {
        return view('microregiones.index', [
            'pageTitle' => 'Microrregiones',
        ]);
    }

    /**
     * Datos para mapa y panel lateral (municipios del estado de Puebla en BD).
     */
    public function data(): JsonResponse
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
                    $q->select(['id', 'municipio', 'microrregion_id', 'cve_edo', 'cve_inegi'])
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
                // Filter out special admin/system users by name if needed
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

        return response()->json([
            'microrregiones' => $out,
            'map' => [
                'center' => [19.0, -97.75],
                'zoom' => 8,
                'attribution' => '© OpenStreetMap',
                'puebla_bounds' => PueblaStateBounds::asArray(),
            ],
            'boundaries_url' => route('microregiones.boundaries'),
        ]);
    }

    /**
     * Polígonos de municipios (Puebla) ya resueltos y guardados en caché (Nominatim).
     */
    public function boundaries(): JsonResponse
    {
        $rows = [];

        $municipios = Municipio::query()
            ->whereNotNull('microrregion_id')
            ->where(function ($q) {
                $q->whereIn('cve_edo', ['21', '021', 21])
                    ->orWhereRaw('CAST(cve_edo AS UNSIGNED) = ?', [21]);
            })
            ->orderBy('id')
            ->get(['id', 'microrregion_id', 'municipio']);

        foreach ($municipios as $mun) {
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

        return response()->json([
            'municipios' => $rows,
        ]);
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
}
