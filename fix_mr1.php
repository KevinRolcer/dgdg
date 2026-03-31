<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$names = ['FRANCISCO Z. MENA', 'HONEY', 'JALPAN', 'JOPALA', 'NAUPAN', 'PAHUATLÁN', 'PANTEPEC', 'TLACUILOTEPEC', 'TLAPACOYA', 'TLAXCO', 'VENUSTIANO CARRANZA', 'XICOTEPEC', 'ZIHUATEUTLA'];

$muns = App\Models\Municipio::where('cve_edo', '21')->whereIn('municipio', $names)->get();

$geocode = app('App\Services\Microregiones\MunicipioGeocodeService');
$boundary = app('App\Services\Microregiones\PueblaMunicipioBoundaryService');

foreach ($muns as $m) {
    echo "Processing " . $m->municipio . " (ID: " . $m->id . ")\n";
    $m->microrregion_id = 1;
    $m->save();

    echo "  - Geocoding...\n";
    $geoRes = $geocode->fetchAndStoreFromNominatim($m);
    if ($geoRes) {
        echo "    lat/lng updated: {$geoRes['lat']}, {$geoRes['lng']} (source: {$geoRes['source']})\n";
    }

    echo "  - Fetching boundaries...\n";
    $bRes = $boundary->fetchAndStoreBoundary($m);
    if ($bRes) {
        $data = json_decode($bRes, true);
        echo "    boundary updated. Features: " . (is_array($data) ? count($data['features'] ?? []) : 0) . "\n";
    } else {
        echo "    failed to fetch boundary.\n";
    }

    sleep(1);
}

echo "Done!\n";
