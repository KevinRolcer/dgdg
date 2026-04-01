<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$muns = App\Models\Municipio::where('cve_edo', '21')->where('municipio', 'PUEBLA')->get();

$geocode = app('App\Services\Microregiones\MunicipioGeocodeService');
$boundary = app('App\Services\Microregiones\PueblaMunicipioBoundaryService');

echo "Encontrados PUEBLA Muns: " . $muns->count() . "\n";

foreach ($muns as $m) {
    echo "Processing " . $m->id . " - " . $m->municipio . " (MR " . $m->microrregion_id . ")...\n";
    
    // Coordinates
    $geoRes = $geocode->fetchAndStoreFromNominatim($m);
    echo "  - Coords: " . ($geoRes ? 'OK' : 'FAIL') . "\n";
    sleep(1);

    // Boundary
    $bRes = $boundary->fetchAndStoreFromNominatim($m);
    echo "  - Boundary: " . ($bRes !== null ? 'OK' : 'FAIL') . "\n";
    sleep(1);
}

echo "Done\n";
