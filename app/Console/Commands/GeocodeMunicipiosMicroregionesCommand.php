<?php

namespace App\Console\Commands;

use App\Models\Municipio;
use App\Services\Microregiones\MunicipioGeocodeService;
use Illuminate\Console\Command;

class GeocodeMunicipiosMicroregionesCommand extends Command
{
    protected $signature = 'microregiones:geocode-municipios {--sleep=1 : Segundos entre peticiones a Nominatim}';

    protected $description = 'Geocodifica municipios (Nominatim) y guarda coordenadas en caché para el mapa de microrregiones.';

    public function handle(MunicipioGeocodeService $geocode): int
    {
        $sleep = max(1, (float) $this->option('sleep'));
        $ids = Municipio::query()
            ->whereNotNull('microrregion_id')
            ->orderBy('id')
            ->pluck('id');

        $this->info('Municipios a procesar: '.$ids->count());

        $ok = 0;
        $fail = 0;

        foreach ($ids as $id) {
            $m = Municipio::query()->find($id);
            if (! $m) {
                continue;
            }
            $res = $geocode->fetchAndStoreFromNominatim($m);
            if ($res !== null) {
                $this->line('OK '.$m->municipio.' → '.$res['lat'].', '.$res['lng']);
                $ok++;
            } else {
                $this->warn('Fallo: '.$m->municipio);
                $fail++;
            }
            usleep((int) ($sleep * 1_000_000));
        }

        $this->info("Listo. OK: {$ok}, fallos: {$fail}.");

        return self::SUCCESS;
    }
}
