<?php

namespace App\Services\Settings;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class DistribuirMunicipiosExcelService
{
    /** Nombres de columna aceptados (cualquiera coincide sin importar mayúsculas/acentos) */
    private const COL_MICRORREGION = [
        'microrregion', 'microrregión', 'microregion', 'microregión',
        'micro región', 'microrregiones',
    ];
    private const COL_NUMERO = ['no', 'no.', 'número', 'numero', 'num'];
    private const COL_MUNICIPIO = [
        'municipio', 'nombre de municipio', 'nombre municipio', 'municipios',
        'municipio nombre', 'nombre del municipio', 'municipios nombre',
    ];

    private const MAX_DATA_ROWS = 10000;

    private const MAX_HEADER_ROW = 15;

    /**
     * Distribuye municipios por microrregión según el Excel cargado.
     * Busca columnas Microregión, NO, Municipio/NOMBRE DE MUNICIPIO (insensible a mayúsculas y acentos).
     *
     * @return array{ok: bool, updated: int, missing_municipios: list<array>, missing_microrregiones: list<string>, columnas: array, errors: list<string>}
     */
    public function distribuirDesdeExcel(UploadedFile $file): array
    {
        $result = [
            'ok' => false,
            'updated' => 0,
            'missing_municipios' => [],
            'missing_microrregiones' => [],
            'columnas' => [],
            'errors' => [],
            'info' => null,
        ];

        try {
            $this->validateUploadedFile($file, $result);
            if (! empty($result['errors'])) {
                return $result;
            }

            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();
            $highestCol = $sheet->getHighestColumn();
            $colCount = Coordinate::columnIndexFromString($highestCol);

            if ($highestRow < 2) {
                $result['errors'][] = 'El archivo no tiene suficientes filas. Se necesita al menos una fila de encabezado y una de datos.';

                return $result;
            }

            if ($colCount < 2) {
                $result['errors'][] = 'El archivo debe tener al menos 2 columnas (Microrregión y Municipio).';

                return $result;
            }

            // Detectar fila de encabezados (1 a MAX_HEADER_ROW): en el Directorio de Delegados suele ser la fila 4
            $headerRow = null;
            $headers = [];
            $maxHeaderRow = min(self::MAX_HEADER_ROW, $highestRow);
            for ($tryRow = 1; $tryRow <= $maxHeaderRow; $tryRow++) {
                $rowHeaders = [];
                for ($c = 1; $c <= $colCount; $c++) {
                    $coord = $this->getMasterCellCoordinate($sheet, $c, $tryRow);
                    $val = $sheet->getCell($coord)->getValue();
                    $rowHeaders[$c] = is_scalar($val) ? trim((string) $val) : '';
                }
                $idxMicro = $this->findColumnIndex($rowHeaders, self::COL_MICRORREGION, 'micro', null);
                $idxMunicipio = $this->findColumnIndex($rowHeaders, self::COL_MUNICIPIO, null, ['municipio', 'nombre']);
                if ($idxMicro !== null && $idxMunicipio !== null) {
                    $headerRow = $tryRow;
                    $headers = $rowHeaders;
                    break;
                }
            }

            if ($headerRow === null) {
                $headers = [];
                for ($c = 1; $c <= $colCount; $c++) {
                    $coord = $this->getMasterCellCoordinate($sheet, $c, 1);
                    $val = $sheet->getCell($coord)->getValue();
                    $headers[$c] = is_scalar($val) ? trim((string) $val) : '';
                }
            }

            $idxMicro = $this->findColumnIndex($headers, self::COL_MICRORREGION, 'micro', null);
            $idxMunicipio = $this->findColumnIndex($headers, self::COL_MUNICIPIO, null, ['municipio', 'nombre']);

            if ($idxMicro === null) {
                $result['errors'][] = 'No se encontró la columna de Microrregión. Buscamos: ' . implode(', ', self::COL_MICRORREGION);
            }
            if ($idxMunicipio === null) {
                $result['errors'][] = 'No se encontró la columna de Municipio. Buscamos: Municipio o NOMBRE DE MUNICIPIO (insensible a mayúsculas y acentos).';
            }

            $result['columnas'] = [
                'microrregion' => $idxMicro !== null ? $headers[$idxMicro] : null,
                'municipio' => $idxMunicipio !== null ? $headers[$idxMunicipio] : null,
            ];

            if ($idxMicro === null || $idxMunicipio === null) {
                return $result;
            }

            $firstDataRow = ($headerRow !== null) ? $headerRow + 1 : 2;

            if ($firstDataRow > $highestRow) {
                $result['errors'][] = 'No hay filas de datos después de la fila de encabezados (fila ' . $firstDataRow . ').';

                return $result;
            }

            $lastDataRow = min($highestRow, $firstDataRow + self::MAX_DATA_ROWS - 1);
            if ($lastDataRow < $highestRow) {
                $result['info'] = 'Se procesaron solo las primeras ' . self::MAX_DATA_ROWS . ' filas de datos (el archivo tiene más).';
            }

            $microrregionesDb = DB::table('microrregiones')
                ->select('id', 'microrregion', 'cabecera')
                ->get();

            $microrregionByCode = $microrregionesDb->keyBy(function ($row) {
                return trim((string) $row->microrregion);
            });

            $municipiosDb = DB::table('municipios')
                ->select('id', 'municipio')
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'nombre' => (string) $row->municipio,
                    'norm' => $this->normalize((string) $row->municipio),
                ])
                ->values()
                ->all();

            $municipiosByNorm = [];
            foreach ($municipiosDb as $row) {
                $municipiosByNorm[$row['norm']] ??= [];
                $municipiosByNorm[$row['norm']][] = $row['id'];
            }

            $currentMicro = null;
            $currentMicroId = null;
            $updated = 0;
            $missingMunicipios = [];
            $missingMicrorregiones = [];

            for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
                $valMicro = $this->getCellValue($sheet, $idxMicro, $r);
                $valMunicipio = $this->getCellValue($sheet, $idxMunicipio, $r);

                $valMicro = trim((string) $valMicro);
                $valMunicipio = trim((string) $valMunicipio);

                if ($valMicro !== '') {
                    $resolved = $this->resolveMicrorregionId($valMicro, $microrregionesDb, $microrregionByCode);
                    if ($resolved !== null) {
                        $currentMicro = $valMicro;
                        $currentMicroId = $resolved;
                    } else {
                        $missingMicrorregiones[$valMicro] = true;
                    }
                }

                if ($valMunicipio === '' || $currentMicroId === null) {
                    continue;
                }

                $municipioId = $this->resolveMunicipioId($valMunicipio, $municipiosByNorm, $municipiosDb);
                if ($municipioId === null) {
                    $missingMunicipios[] = [
                        'microrregion' => $currentMicro,
                        'municipio' => $valMunicipio,
                    ];
                    continue;
                }

                $n = DB::table('municipios')
                    ->where('id', $municipioId)
                    ->where('microrregion_id', '!=', $currentMicroId)
                    ->update(['microrregion_id' => $currentMicroId, 'updated_at' => now()]);
                $updated += $n;
            }

            if (count($missingMicrorregiones) > 0) {
                $result['missing_microrregiones'] = array_keys($missingMicrorregiones);
            }

            $result['missing_municipios'] = $missingMunicipios;
            $result['updated'] = $updated;
            $result['ok'] = true;

            if (
                $updated === 0
                && count($missingMunicipios) === 0
                && count($missingMicrorregiones) === 0
                && ($result['info'] ?? null) === null
            ) {
                $result['info'] = 'No se encontraron filas con microrregión y municipio válidos para actualizar. Compruebe que las columnas y los datos coinciden con el catálogo.';
            }

            return $result;
        } catch (\Throwable $e) {
            $result['errors'][] = 'Error al procesar el Excel: ' . $e->getMessage();

            return $result;
        }
    }

    /**
     * Lee el valor de una celda. Resuelve celdas fusionadas (toma el valor de la celda maestra)
     * y usa el valor calculado para fórmulas.
     */
    private function getCellValue($sheet, int $colIndex, int $row): ?string
    {
        $coord = $this->getMasterCellCoordinate($sheet, $colIndex, $row);
        $cell = $sheet->getCell($coord);

        try {
            $val = $cell->getCalculatedValue();
        } catch (\Throwable) {
            $val = $cell->getValue();
        }

        if ($val === null || $val === '') {
            return '';
        }
        if (is_object($val) && method_exists($val, 'getPlainText')) {
            return trim((string) $val->getPlainText());
        }
        if (is_numeric($val) && (is_int($val) || is_float($val))) {
            return (string) $val;
        }

        return trim((string) $val);
    }

    /**
     * Si la celda (colIndex, row) está en un rango fusionado, devuelve la coordenada
     * de la celda maestra (superior izquierda); si no, la coordenada de la propia celda.
     */
    private function getMasterCellCoordinate($sheet, int $colIndex, int $row): string
    {
        $colLetter = Coordinate::stringFromColumnIndex($colIndex);
        $coord = $colLetter . $row;

        $mergeCells = $sheet->getMergeCells();
        foreach ($mergeCells as $key => $range) {
            $rangeStr = is_string($key) && str_contains($key, ':') ? $key : (is_string($range) ? $range : null);
            if ($rangeStr === null || ! str_contains($rangeStr, ':')) {
                continue;
            }
            if (! Coordinate::coordinateIsInsideRange($rangeStr, $coord)) {
                continue;
            }
            $parts = explode(':', $rangeStr);
            $master = $parts[0] ?? $coord;

            return $master;
        }

        return $coord;
    }

    private function validateUploadedFile(UploadedFile $file, array &$result): void
    {
        if (! $file->isValid()) {
            $result['errors'][] = 'Archivo no válido: ' . ($file->getErrorMessage() ?: 'error desconocido.');

            return;
        }
        if ($file->getSize() === 0) {
            $result['errors'][] = 'El archivo está vacío.';
        }
        $allowed = ['xlsx', 'xls'];
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, $allowed, true)) {
            $result['errors'][] = 'Solo se permiten archivos Excel (.xlsx o .xls).';
        }
    }

    /**
     * @param array $headers  [colIndex => header text]
     * @param array $candidates  List of accepted column names (normalized for comparison)
     * @param string|null $headerMustContain  If set, normalized header must contain this substring (e.g. 'micro' to avoid matching "REGIÓN" for "Microrregión")
     * @param array|null $headerMustContainOneOf  If set, normalized header must contain at least one of these (e.g. ['municipio','nombre'] to avoid matching col "NO" as Municipio)
     */
    private function findColumnIndex(array $headers, array $candidates, ?string $headerMustContain = null, ?array $headerMustContainOneOf = null): ?int
    {
        $normCandidates = array_map(fn ($c) => $this->normalize($c), $candidates);
        $required = $headerMustContain !== null ? $this->normalize($headerMustContain) : null;
        $requiredOneOf = $headerMustContainOneOf !== null
            ? array_map(fn ($s) => $this->normalize($s), $headerMustContainOneOf)
            : null;

        foreach ($headers as $colIndex => $header) {
            $normHeader = $this->normalize($header);
            if ($normHeader === '') {
                continue;
            }
            if ($required !== '' && ! str_contains($normHeader, $required)) {
                continue;
            }
            if ($requiredOneOf !== null) {
                $hasOne = false;
                foreach ($requiredOneOf as $sub) {
                    if ($sub !== '' && str_contains($normHeader, $sub)) {
                        $hasOne = true;
                        break;
                    }
                }
                if (! $hasOne) {
                    continue;
                }
            }
            foreach ($normCandidates as $nc) {
                if ($normHeader === $nc || str_contains($normHeader, $nc) || str_contains($nc, $normHeader)) {
                    return $colIndex;
                }
            }
        }

        return null;
    }

    private function resolveMicrorregionId(string $excelValue, $microrregionesDb, $microrregionByCode): ?int
    {
        $excelValue = trim($excelValue);
        $code = $this->extractMicrorregionCode($excelValue);
        $textPart = $this->extractMicrorregionTextPart($excelValue);

        if ($code !== '') {
            $row = $microrregionByCode->get($code);
            if ($row !== null) {
                return (int) $row->id;
            }
            $codeWithoutLeadingZeros = ltrim($code, '0') ?: '0';
            $row = $microrregionByCode->get($codeWithoutLeadingZeros);
            if ($row !== null) {
                return (int) $row->id;
            }
            $codePadded = str_pad((string) (int) $code, 2, '0', STR_PAD_LEFT);
            if ($codePadded !== $code) {
                $row = $microrregionByCode->get($codePadded);
                if ($row !== null) {
                    return (int) $row->id;
                }
            }
        }

        if ($textPart !== '') {
            $textNorm = $this->normalize($textPart);
            foreach ($microrregionesDb as $row) {
                $cabeceraNorm = $this->normalize((string) $row->cabecera);
                $microNorm = $this->normalize((string) $row->microrregion);
                if ($textNorm === $cabeceraNorm || $textNorm === $microNorm) {
                    return (int) $row->id;
                }
                if (str_contains($textNorm, $cabeceraNorm) || str_contains($cabeceraNorm, $textNorm)) {
                    return (int) $row->id;
                }
            }
        }

        $excelNorm = $this->normalize($excelValue);
        foreach ($microrregionesDb as $row) {
            $cabeceraNorm = $this->normalize((string) $row->cabecera);
            $microNorm = $this->normalize((string) $row->microrregion);
            if ($excelNorm === $cabeceraNorm || $excelNorm === $microNorm) {
                return (int) $row->id;
            }
            if (str_contains($excelNorm, $cabeceraNorm) || str_contains($excelNorm, $microNorm)) {
                return (int) $row->id;
            }
        }

        return null;
    }

    private function extractMicrorregionCode(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\s*(\d+)/', $value, $m)) {
            return $m[1];
        }
        $parts = preg_split('/\s+/', $value, 2);
        if (isset($parts[0]) && is_numeric(trim($parts[0]))) {
            return trim($parts[0]);
        }

        return '';
    }

    /**
     * Extrae la parte de texto después del número (ej. "01 XICOTEPEC" → "XICOTEPEC").
     * Sirve para emparejar por cabecera cuando el Excel trae "número + nombre".
     */
    private function extractMicrorregionTextPart(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\s*\d+\s+(.+)$/s', $value, $m)) {
            return trim($m[1]);
        }
        $parts = preg_split('/\s+/', $value, 2);
        if (isset($parts[1])) {
            return trim($parts[1]);
        }

        return '';
    }

    private function resolveMunicipioId(string $municipio, array $municipiosByNorm, array $municipiosDb): ?int
    {
        $norm = $this->normalize($municipio);
        if (isset($municipiosByNorm[$norm][0])) {
            return (int) $municipiosByNorm[$norm][0];
        }

        foreach ($this->municipioAliases($norm) as $alias) {
            if (isset($municipiosByNorm[$alias][0])) {
                return (int) $municipiosByNorm[$alias][0];
            }
        }

        $matches = [];
        foreach ($municipiosDb as $row) {
            if (str_contains($row['norm'], $norm) || str_contains($norm, $row['norm'])) {
                $matches[] = (int) $row['id'];
            }
        }
        if (count($matches) === 1) {
            return $matches[0];
        }

        return null;
    }

    private function normalize(string $value): string
    {
        $value = Str::upper(Str::ascii($value));
        $value = preg_replace('/[^A-Z0-9 ]+/', ' ', $value) ?: '';
        $value = preg_replace('/\s+/', ' ', $value) ?: '';

        return trim($value);
    }

    private function municipioAliases(string $normalized): array
    {
        return match ($normalized) {
            'XOCHIAPUILCO' => ['XOCHIAPULCO'],
            'XOCHITLAN TODOS' => ['XOCHITLAN TODOS SANTOS'],
            'ALBINO ZERTUCHE MORENA' => ['ALBINO ZERTUCHE'],
            'TLACOTEPEC DE BENITO' => ['TLACOTEPEC DE BENITO JUAREZ'],
            'TEPATALXCO DE HIDALGO' => ['TEPATLAXCO DE HIDALGO'],
            'SAN JOSE MIAHUATLAN' => ['SAN JOSE MIAHUATLAN'],
            'SAN MARTI TEXMELUCAN' => ['SAN MARTIN TEXMELUCAN'],
            default => [],
        };
    }
}
