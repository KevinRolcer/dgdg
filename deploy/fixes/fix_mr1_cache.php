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
    echo "Processing " . $m->municipio . "...\n";
    $geoRes = $geocode->fetchAndStoreFromNominatim($m);
    echo "  Geocode: " . ($geoRes ? "OK ({$geoRes['lat']}, {$geoRes['lng']})" : "FAIL") . "\n";
    
    $bRes = $boundary->fetchAndStoreFromNominatim($m);
    echo "  Boundary: " . ($bRes !== null ? "OK" : "FAIL") . "\n";
    
    sleep(1);
}
echo "Done\n";
