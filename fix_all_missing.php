<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$muns = App\Models\Municipio::where('cve_edo', '21')
    ->whereNotNull('microrregion_id')
    ->where('municipio', '!=', 'PUEBLA')
    ->get();

$geocode = app('App\Services\Microregiones\MunicipioGeocodeService');
$boundary = app('App\Services\Microregiones\PueblaMunicipioBoundaryService');

echo "Total a procesar: " . $muns->count() . "\n";

foreach ($muns as $m) {
    echo $m->municipio . "\n";
    $geoRes = $geocode->coordinatesFor($m);
    if (!isset($geoRes['source']) || $geoRes['source'] === 'fallback') {
        $geocode->fetchAndStoreFromNominatim($m);
        sleep(1);
    }

    $bRes = $boundary->getCachedPolygonGeometry($m);
    if ($bRes === null) {
        $boundary->fetchAndStoreFromNominatim($m);
        sleep(1);
    }
}
echo "Done\n";
