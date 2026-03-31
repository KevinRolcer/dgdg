<?php

namespace App\Console\Commands;

use App\Models\Municipio;
use App\Services\Microregiones\PueblaMunicipioBoundaryService;
use Illuminate\Console\Command;

class FetchMicroregionMunicipioBoundariesCommand extends Command
{
    protected $signature = 'microregiones:fetch-boundaries {--sleep=1 : Segundos entre peticiones a Nominatim}';

    protected $description = 'Descarga polígonos de municipios de Puebla (Nominatim) y los guarda en caché para el mapa.';

    public function handle(PueblaMunicipioBoundaryService $boundary): int
    {
        $sleep = max(1, (float) $this->option('sleep'));

        $ids = Municipio::query()
            ->whereNotNull('microrregion_id')
            ->where(function ($q) {
                $q->whereIn('cve_edo', ['21', '021', 21])
                    ->orWhereRaw('CAST(cve_edo AS UNSIGNED) = ?', [21]);
            })
            ->orderBy('id')
            ->pluck('id');

        $this->info('Municipios Puebla a procesar: '.$ids->count());

        $ok = 0;
        $fail = 0;

        foreach ($ids as $id) {
            $m = Municipio::query()->find($id);
            if (! $m || ! $boundary->isPueblaMunicipio($m)) {
                continue;
            }
            $res = $boundary->fetchAndStoreFromNominatim($m);
            if ($res !== null) {
                $this->line('OK '.$m->municipio);
                $ok++;
            } else {
                $this->warn('Sin polígono: '.$m->municipio);
                $fail++;
            }
            usleep((int) ($sleep * 1_000_000));
        }

        $this->info("Listo. Polígonos guardados: {$ok}, sin resultado: {$fail}.");

        return self::SUCCESS;
    }
}
