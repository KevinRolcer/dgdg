<?php

namespace App\Jobs;

use App\Models\Municipio;
use App\Services\Microregiones\MunicipioGeocodeService;
use App\Services\Microregiones\PueblaMunicipioBoundaryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncMunicipioGeographyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var int
     */
    public $tries = 3;

    /**
     * @var int
     */
    public $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(protected int $municipioId)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(MunicipioGeocodeService $geocode, PueblaMunicipioBoundaryService $boundary): void
    {
        $m = Municipio::find($this->municipioId);
        if (!$m) {
            return;
        }

        // 1. Geocode if missing from DB source
        $geoRes = $geocode->coordinatesFor($m);
        if (!isset($geoRes['source']) || $geoRes['source'] !== 'db') {
            Log::info("Geocoding municipio: {$m->municipio}");
            $geocode->fetchAndStoreFromNominatim($m);
            sleep(1); // Nominatim policy: max 1 request/s
        }

        // 2. Boundary if missing from DB
        if (empty($m->geojson_boundary)) {
            Log::info("Fetching boundary for municipio: {$m->municipio}");
            $boundary->fetchAndStoreFromNominatim($m);
            sleep(1); // Nominatim policy: max 1 request/s
        }
    }
}
