<?php
require dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;

$path = 'C:/Users/kevin/Downloads/aspirantes_todos_20260408_105403_presidente_municipal_desglose_v2.xlsx';
if (!is_file($path)) {
    fwrite(STDERR, "No se encontro archivo: {$path}\n");
    exit(1);
}

$spreadsheet = IOFactory::load($path);
$sheet = $spreadsheet->getActiveSheet();

$highestColIndex = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
$highestRow = $sheet->getHighestDataRow();

$findHeaderCol = static function (string $target) use ($sheet, $highestColIndex): ?string {
    $target = mb_strtoupper(trim($target), 'UTF-8');
    for ($i = 1; $i <= $highestColIndex; $i++) {
        $col = Coordinate::stringFromColumnIndex($i);
        $header = mb_strtoupper(trim((string) $sheet->getCell($col.'1')->getValue()), 'UTF-8');
        if ($header === $target) {
            return $col;
        }
    }

    return null;
};

$nombresCol = $findHeaderCol('NOMBRES');
if ($nombresCol === null) {
    fwrite(STDERR, "No se encontro columna NOMBRES\n");
    exit(1);
}

$normalize = static function (string $v): string {
    $v = mb_strtoupper(trim($v), 'UTF-8');
    $v = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v) ?: $v;
    $v = preg_replace('/[^A-Z ]+/', ' ', $v) ?? $v;
    $v = preg_replace('/\s+/', ' ', $v) ?? $v;
    return trim($v);
};

$femaleNames = [
    'MARIA','MA','ANA','JOSEFINA','GUADALUPE','LUPITA','LUZ','SOFIA','SOFIA','FERNANDA','FATIMA','YARELI','YARELY',
    'PATRICIA','MARGARITA','NANCY','KARLA','CARLA','LAURA','MONICA','LETICIA','LIZETH','LIZBETH','LIZ','IRENE',
    'ROSA','ROSARIO','JULIETA','PAOLA','VALERIA','JESSICA','ERIKA','SANDRA','ADRIANA','CLAUDIA','MIRIAM','SUSANA',
    'TERESA','REYNA','REINA','GABRIELA','NAYELI','NAYELY','ARACELI','DOLORES','ALMA','YOLANDA','NIDIA','MAYRA',
    'MAYRA','MAYTE','BEATRIZ','BLANCA','SOCORRO','AURORA','ELIZABETH','LILIA','LILIANA','VERONICA','MERCEDES'
];
$femaleSet = array_fill_keys($femaleNames, true);

$generoColIndex = $highestColIndex + 1;
$generoCol = Coordinate::stringFromColumnIndex($generoColIndex);
$sheet->setCellValueExplicit($generoCol.'1', 'GENERO', DataType::TYPE_STRING);
$sheet->getStyle($generoCol.'1')->getFont()->setBold(true);
$sheet->getColumnDimension($generoCol)->setWidth(14);

$hCount = 0;
$mCount = 0;

for ($r = 2; $r <= $highestRow; $r++) {
    $raw = trim((string) $sheet->getCell($nombresCol.$r)->getValue());
    $norm = $normalize($raw);
    $tokens = $norm === '' ? [] : explode(' ', $norm);
    $first = $tokens[0] ?? '';

    $genero = isset($femaleSet[$first]) ? 'MUJER' : 'HOMBRE';
    if ($genero === 'MUJER') {
        $mCount++;
    } else {
        $hCount++;
    }

    $sheet->setCellValueExplicit($generoCol.$r, $genero, DataType::TYPE_STRING);
}

$backup = preg_replace('/\.xlsx$/i', '', $path) . '.backup_genero_' . date('Ymd_His') . '.xlsx';
copy($path, $backup);

IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);
$spreadsheet->disconnectWorksheets();
unset($spreadsheet);

echo "Archivo actualizado: {$path}\n";
echo "Respaldo: {$backup}\n";
echo "Totales -> HOMBRE: {$hCount}, MUJER: {$mCount}\n";
