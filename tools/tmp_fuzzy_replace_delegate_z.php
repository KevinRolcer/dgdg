<?php
require dirname(__DIR__) . '/vendor/autoload.php';
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

$excelPath = 'C:/Users/kevin/Downloads/aspirantes_todos_20260408_105403.xlsx';
if (!is_file($excelPath)) {
    fwrite(STDERR, "No se encontro el archivo: {$excelPath}\n");
    exit(1);
}

$normalize = static function (?string $value): string {
    $value = trim((string) $value);
    if ($value === '') return '';
    $value = mb_strtoupper($value, 'UTF-8');
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    $value = preg_replace('/[^A-Z0-9 ]+/', ' ', $value) ?? $value;
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    return trim($value);
};

$isProbablyMr = static function (string $value): bool {
    $v = trim($value);
    return preg_match('/^\d{1,3}$/', $v) === 1;
};

$delegados = DB::table('delegados as d')
    ->join('users as u', 'u.id', '=', 'd.user_id')
    ->leftJoin('microrregiones as m', 'm.id', '=', 'd.microrregion_id')
    ->where('u.activo', 1)
    ->select([
        'u.name as user_name',
        'd.nombre',
        'd.ap_paterno',
        'd.ap_materno',
        'm.microrregion as mr_number',
    ])
    ->get();

$candidates = [];
foreach ($delegados as $d) {
    $mr = trim((string) ($d->mr_number ?? ''));
    if ($mr === '') continue;

    $full = trim(implode(' ', array_filter([
        (string) ($d->nombre ?? ''),
        (string) ($d->ap_paterno ?? ''),
        (string) ($d->ap_materno ?? ''),
    ], fn ($x) => trim((string) $x) !== '')));

    foreach ([(string) ($d->user_name ?? ''), $full] as $rawName) {
        $norm = $normalize($rawName);
        if ($norm === '') continue;
        $candidates[] = [
            'norm' => $norm,
            'raw' => trim($rawName),
            'mr' => $mr,
        ];
    }
}

$spreadsheet = IOFactory::load($excelPath);
$sheet = $spreadsheet->getActiveSheet();
$highestRow = $sheet->getHighestDataRow('Z');

$applied = 0;
$review = [];

for ($row = 1; $row <= $highestRow; $row++) {
    $cellRaw = $sheet->getCell('Z' . $row)->getValue();
    $value = trim((string) ($cellRaw ?? ''));
    if ($value === '' || $isProbablyMr($value)) continue;

    $normInput = $normalize($value);
    if ($normInput === '' || $normInput === 'REGISTRADO POR') continue;

    $bestScore = -1.0;
    $secondScore = -1.0;
    $best = null;

    foreach ($candidates as $cand) {
        similar_text($normInput, $cand['norm'], $pct);
        if ($pct > $bestScore) {
            $secondScore = $bestScore;
            $bestScore = $pct;
            $best = $cand;
        } elseif ($pct > $secondScore) {
            $secondScore = $pct;
        }
    }

    // Umbral conservador y margen entre 1er y 2do para reducir falsos positivos
    if ($best !== null && $bestScore >= 76.0 && ($bestScore - $secondScore) >= 3.0) {
        $sheet->setCellValueExplicit('Z' . $row, (string) $best['mr'], DataType::TYPE_STRING);
        $applied++;
        $review[] = sprintf('Fila %d: "%s" -> "%s" (MR %s, %.2f%%)', $row, $value, $best['raw'], $best['mr'], $bestScore);
    }
}

if ($applied > 0) {
    $backupPath = preg_replace('/\.xlsx$/i', '', $excelPath) . '.backup_fuzzy_' . date('Ymd_His') . '.xlsx';
    copy($excelPath, $backupPath);
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($excelPath);
    echo "Respaldo fuzzy: {$backupPath}\n";
}

$spreadsheet->disconnectWorksheets();
unset($spreadsheet);

echo "Filas actualizadas por similitud: {$applied}\n";
if (!empty($review)) {
    echo "Cambios aplicados:\n";
    foreach ($review as $line) echo " - {$line}\n";
}
