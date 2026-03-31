<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$names = [
    'SAN SALVADOR EL SECO',
    'RAFAEL LARA GRAJALES',
    'SOLTEPEC',
    'SAN JOSÉ CHIAPA',
    'SAN NICOLÁS BUENOS AIRES',
    'SAN JUAN ATENCO'
];

$muns = App\Models\Municipio::where('cve_edo', '21')->whereIn('municipio', $names)->get();

echo "Encontrados: " . $muns->count() . "\n";

$geocode = app('App\Services\Microregiones\MunicipioGeocodeService');
$boundary = app('App\Services\Microregiones\PueblaMunicipioBoundaryService');

foreach ($muns as $m) {
    echo "Actualizando {$m->municipio}...\n";
    
    // Assign to MR 14
    $m->microrregion_id = 14;
    $m->save();

    // Clear caches
    Illuminate\Support\Facades\Cache::forget('municipio_geo_v1_'.$m->id);
    Illuminate\Support\Facades\Cache::forget('municipio_boundary_v1_'.$m->id);

    // Re-fetch Geocode
    $geoRes = $geocode->fetchAndStoreFromNominatim($m);
    echo "  - Coords: " . ($geoRes ? 'OK' : 'FAIL') . "\n";
    sleep(1);

    // Re-fetch Boundary
    // If it's incomplete, sometimes using specific OSM relations helps, but Nominatim usually gets it right if we just query it fresh. 
    $bRes = $boundary->fetchAndStoreFromNominatim($m);
    echo "  - Boundary: " . ($bRes !== null ? 'OK' : 'FAIL') . "\n";
    sleep(1);
}

echo "Done\n";
