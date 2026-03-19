<?php

namespace App\Services\TemporaryModules;

use App\Models\TemporaryModule;
use App\Models\TemporaryModuleEntry;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Crea un módulo temporal + campos + registros precargados desde Excel.
 * Prioridad: columna Municipio → microrregión por BD. Si no hay columna Municipio, solo Microrregión (cabecera como etiqueta).
 */
class TemporaryModuleAdminSeedService
{
    public function __construct(
        private readonly TemporaryModuleExcelImportService $excelReader,
    ) {}

    public function previewHeaders(UploadedFile $file, int $headerRow): array
    {
        return $this->excelReader->preview($file, $headerRow);
    }

    /**
     * Detecta la fila de encabezados de la tabla de ítems (p. ej. N°, MICROREGION, MUNICIPIO, ACCION)
     * ignorando bloques superiores como KPIs o títulos largos en una sola celda.
     *
     * @return array{header_row:int,data_start_row:int,score:int,note:string}|null
     */
    public function detectTableLayout(UploadedFile $file, int $maxScanRow = 80): ?array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $maxScanRow = min($maxScanRow, (int) $sheet->getHighestRow());
        $maxScanRow = max(1, $maxScanRow);

        $bestRow = null;
        $bestScore = 0;

        for ($r = 1; $r <= $maxScanRow; $r++) {
            $cells = $this->rowCellStrings($sheet, $r);
            $nonEmpty = array_filter($cells, fn ($s) => trim((string) $s) !== '');
            if (count($nonEmpty) < 3) {
                continue;
            }

            $longCells = 0;
            foreach ($nonEmpty as $s) {
                if (mb_strlen(trim($s)) > 70) {
                    $longCells++;
                }
            }
            // Fila título tipo "SOLICITUDES DE ACCIONES..." suele ser 1 celda muy larga o pocas celdas con mucho texto
            if ($longCells >= 2 || (count($nonEmpty) <= 2 && $longCells >= 1)) {
                continue;
            }

            $norm = array_map(fn ($s) => $this->normalizeHeaderToken($s), $nonEmpty);
            $joined = ' '.implode(' ', $norm).' ';

            $score = 0;
            if (preg_match('/\b(MUNICIPIO|MUNICIP)\b/u', $joined)) {
                $score += 4;
            }
            if (preg_match('/\b(MICRORREGION|MICROREGION|MICRORREG)\b/u', $joined)) {
                $score += 4;
            }
            if (preg_match('/\bACCION\b/u', $joined) && ! preg_match('/SOLICITUDES DE ACCIONES/u', $joined)) {
                $score += 2;
            }
            if (preg_match('/\bESTATUS\b/u', $joined)) {
                $score += 2;
            }
            if (preg_match('/\b(N°|NO\.|NUMERO|NUM|ITEM|#\b)/u', $joined)) {
                $score += 1;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRow = $r;
            }
        }

        if ($bestRow === null || $bestScore < 6) {
            return null;
        }

        $dataStart = $this->detectFirstDataRow($sheet, $bestRow, $maxScanRow);

        return [
            'header_row' => $bestRow,
            'data_start_row' => $dataStart,
            'score' => $bestScore,
            'note' => "Tabla detectada: encabezados fila {$bestRow}, primer ítem fila {$dataStart}.",
        ];
    }

    /**
     * Primera fila bajo el encabezado con varias celdas con contenido (evita filas vacías por merges).
     */
    private function detectFirstDataRow(Worksheet $sheet, int $headerRow, int $maxScanRow): int
    {
        $highest = min((int) $sheet->getHighestRow(), $maxScanRow + 200);
        for ($r = $headerRow + 1; $r <= $highest; $r++) {
            $cells = $this->rowCellStrings($sheet, $r);
            $filled = 0;
            foreach ($cells as $s) {
                if (trim((string) $s) !== '') {
                    $filled++;
                }
            }
            if ($filled >= 2) {
                return $r;
            }
        }

        return $headerRow + 1;
    }

    /** @return list<string> por columna A.. hasta última con dato en esa fila */
    private function rowCellStrings(Worksheet $sheet, int $row): array
    {
        $lastCol = Coordinate::columnIndexFromString($sheet->getHighestDataColumn($row));
        $lastCol = max(1, min($lastCol, 40));
        $out = [];
        for ($c = 1; $c <= $lastCol; $c++) {
            $letter = Coordinate::stringFromColumnIndex($c);
            $v = $sheet->getCell($letter.$row)->getValue();
            $out[] = trim((string) ($v ?? ''));
        }

        return $out;
    }

    private function normalizeHeaderToken(string $s): string
    {
        $s = mb_strtoupper(preg_replace('/\s+/', ' ', trim($s)), 'UTF-8');

        return strtr($s, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N']);
    }

    /**
     * @param  array<int, true>  $fieldColumnIndices  índices 0-based de columnas que serán campos del módulo
     * @param  array{created:int,skipped:int,unmatched:list<array{row:int,reason:string}>}  $stats  out
     */
    public function createModuleFromExcel(
        int $adminUserId,
        string $name,
        ?string $description,
        ?Carbon $expiresAt,
        bool $isIndefinite,
        UploadedFile $file,
        int $headerRow,
        int $dataStartRow,
        int $colMicrorregion,
        int $colMunicipio,
        array $fieldColumnIndices,
        array &$stats,
    ): TemporaryModule {
        $hasMunCol = $colMunicipio >= 0;
        $hasMrCol = $colMicrorregion >= 0;
        if (! $hasMunCol && ! $hasMrCol) {
            throw new \InvalidArgumentException('Debe indicarse columna Municipio o Microrregión.');
        }

        $headerRow = max(1, $headerRow);
        $dataStartRow = max($headerRow + 1, $dataStartRow);
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = (int) $sheet->getHighestDataRow();
        // Fin real de la tabla por columnas clave (evita timeout: no barrer 12k×40 celdas)
        $scanEnd = max($highestRow, $dataStartRow);
        $colsForExtent = array_values(array_filter(array_unique(array_map('intval', array_merge(
            array_filter([$colMicrorregion, $colMunicipio], fn ($c) => $c >= 0),
            $fieldColumnIndices ?: []
        ))), fn ($ci) => $ci >= 0));
        foreach ($colsForExtent as $ci) {
            if ($ci < 0) {
                continue;
            }
            $letter = Coordinate::stringFromColumnIndex($ci + 1);
            try {
                $scanEnd = max($scanEnd, (int) $sheet->getHighestDataRow($letter));
            } catch (\Throwable) {
            }
        }
        $scanEnd = min($scanEnd + 80, $dataStartRow + 6000);
        $highestColLetter = $sheet->getHighestDataColumn($headerRow);
        $maxCol = Coordinate::columnIndexFromString($highestColLetter);

        $headers = [];
        for ($c = 1; $c <= $maxCol; $c++) {
            $letter = Coordinate::stringFromColumnIndex($c);
            $headers[$c - 1] = trim((string) $sheet->getCell($letter.$headerRow)->getValue());
        }

        $usedKeys = [];
        $preparedFields = [];
        $sort = 1;
        foreach ($fieldColumnIndices as $idx) {
            $idx = (int) $idx;
            if ($idx < 0 || $idx >= count($headers)) {
                continue;
            }
            $label = $headers[$idx] !== '' ? $headers[$idx] : 'Columna_'.($idx + 1);
            $key = Str::slug($label, '_');
            if ($key === '') {
                $key = 'campo_'.$sort;
            }
            $base = $key;
            $n = 2;
            while (in_array($key, $usedKeys, true)) {
                $key = $base.'_'.$n;
                $n++;
            }
            $usedKeys[] = $key;
            $preparedFields[] = [
                'label' => $label,
                'comment' => null,
                'key' => $key,
                'type' => 'text',
                'is_required' => false,
                'options' => null,
                'sort_order' => $sort,
                'subsection_index' => null,
            ];
            $sort++;
        }

        if ($preparedFields === []) {
            throw new \InvalidArgumentException('Selecciona al menos una columna como campo del módulo.');
        }

        $indexToKey = [];
        $fieldCols = array_values($fieldColumnIndices);
        foreach ($fieldCols as $i => $colIdx) {
            if (isset($preparedFields[$i])) {
                $indexToKey[(int) $colIdx] = $preparedFields[$i]['key'];
            }
        }

        $municipiosRows = DB::table('municipios as mu')
            ->join('microrregiones as mr', 'mr.id', '=', 'mu.microrregion_id')
            ->select(['mu.id', 'mu.municipio', 'mu.microrregion_id', 'mr.microrregion as mr_num'])
            ->get();

        /** @var array<int, list<object>> microrregion_id => filas municipio */
        $municipiosByMicro = [];
        foreach ($municipiosRows as $m) {
            $mid = (int) $m->microrregion_id;
            $municipiosByMicro[$mid][] = $m;
        }

        $microPorNumero = [];
        $microByNormToken = [];
        foreach (DB::table('microrregiones')->select(['id', 'microrregion', 'cabecera'])->get() as $mr) {
            $n = trim((string) $mr->microrregion);
            $microPorNumero[$n] = (int) $mr->id;
            $num = (int) preg_replace('/\D/', '', $n) ?: (int) $n;
            if ($num > 0) {
                $microPorNumero[(string) $num] = (int) $mr->id;
                $microPorNumero[str_pad((string) $num, 2, '0', STR_PAD_LEFT)] = (int) $mr->id;
            }
            $cab = $this->normalizeMunicipioName((string) ($mr->cabecera ?? ''));
            if (mb_strlen($cab) >= 4) {
                $microByNormToken[$cab] = (int) $mr->id;
            }
            foreach (preg_split('/\s+/', $cab) ?: [] as $tok) {
                if (mb_strlen((string) $tok) >= 5) {
                    $microByNormToken[(string) $tok] = (int) $mr->id;
                }
            }
        }

        $municipiosGlobalNorm = [];
        foreach ($municipiosRows as $m) {
            $k = $this->normalizeMunicipioName($m->municipio);
            $municipiosGlobalNorm[$k][] = $m;
        }

        $microLabels = [];
        $cabeceraPorMicro = [];
        foreach (DB::table('microrregiones')->select(['id', 'microrregion', 'cabecera'])->get() as $mr) {
            $n = str_pad(trim((string) $mr->microrregion), 2, '0', STR_PAD_LEFT);
            $id = (int) $mr->id;
            $microLabels[$id] = 'MR '.$n.' '.trim((string) ($mr->cabecera ?? ''));
            $cabeceraPorMicro[$id] = trim((string) ($mr->cabecera ?? ''));
        }

        $stats = ['created' => 0, 'skipped' => 0, 'unmatched' => [], 'discarded' => []];

        $slug = Str::slug($name);
        /** @var TemporaryModuleSlugService $slugSvc */
        $slugSvc = app(TemporaryModuleSlugService::class);
        $slugSvc->forcePurgeTrashedBySlug($slug);
        $baseSlug = $slug;
        $suf = 2;
        while (TemporaryModule::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suf;
            $suf++;
            if ($suf > 999) {
                $slug = $baseSlug.'-'.substr(sha1(uniqid((string) mt_rand(), true)), 0, 8);
                break;
            }
        }

        $expires = $isIndefinite ? null : $expiresAt;

        return DB::transaction(function () use (
            $adminUserId,
            $name,
            $slug,
            $description,
            $expires,
            $preparedFields,
            $sheet,
            $dataStartRow,
            $scanEnd,
            $maxCol,
            $colMicrorregion,
            $colMunicipio,
            $hasMrCol,
            $hasMunCol,
            $fieldColumnIndices,
            $indexToKey,
            $municipiosByMicro,
            $microPorNumero,
            $microByNormToken,
            $municipiosGlobalNorm,
            $microLabels,
            $cabeceraPorMicro,
            &$stats,
        ): TemporaryModule {
            $module = TemporaryModule::query()->create([
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'expires_at' => $expires,
                'is_active' => true,
                'applies_to_all' => false,
                'created_by' => $adminUserId,
            ]);

            $module->fields()->createMany($preparedFields);

            $assignedUserIds = [];
            $microrregionIdsUsed = [];
            $lastMicroId = null;
            $lastMunicipioCell = '';
            $lastFieldCarry = [];
            $mc = [$maxCol, max($fieldColumnIndices ?: [0]) + 5];
            if ($hasMrCol) {
                $mc[] = $colMicrorregion + 5;
            }
            if ($hasMunCol) {
                $mc[] = $colMunicipio + 5;
            }
            $maxColScan = min(40, max($mc));
            $lastDataRow = $scanEnd;

            for ($row = $dataStartRow; $row <= $scanEnd; $row++) {
                $cellMr = $hasMrCol ? $this->cellStrMerged($sheet, $colMicrorregion, $row) : '';
                $cellMun = $hasMunCol ? $this->cellStrMerged($sheet, $colMunicipio, $row) : '';

                if ($hasMrCol && $cellMr !== '') {
                    $parsedMr = $this->resolveMicrorregionId($cellMr, $microPorNumero)
                        ?? $this->resolveMicrorregionByName($cellMr, $microByNormToken);
                    if ($parsedMr !== null) {
                        $lastMicroId = $parsedMr;
                    }
                }
                if ($hasMunCol && $cellMun !== '') {
                    $lastMunicipioCell = $cellMun;
                }
                $microId = $lastMicroId ?? ($hasMrCol ? ($this->resolveMicrorregionId($cellMr, $microPorNumero)
                    ?? $this->resolveMicrorregionByName($cellMr, $microByNormToken)) : null);

                $rowHasAny = false;
                for ($c = 1; $c <= $maxColScan; $c++) {
                    if ($this->cellStrMerged($sheet, $c - 1, $row) !== '') {
                        $rowHasAny = true;
                        break;
                    }
                }
                $insideDataBlock = $row <= $lastDataRow && $row >= $dataStartRow;
                $differsFromCarry = false;
                foreach ($fieldColumnIndices as $ci) {
                    $ci = (int) $ci;
                    $now = $this->cellStrMerged($sheet, $ci, $row);
                    $prev = $lastFieldCarry[$ci] ?? '';
                    if ($now !== '' && $now !== $prev) {
                        $differsFromCarry = true;
                        break;
                    }
                }
                $itemColHint = $this->cellStrMerged($sheet, 0, $row);
                $itemColHint2 = $this->cellStrMerged($sheet, 1, $row);
                $looksLikeItemRow = (preg_match('/^\s*\d+\s*$/', $itemColHint) || preg_match('/^\s*\d+\s*$/', $itemColHint2));
                if (! $looksLikeItemRow && preg_match('/^\s*\d{1,4}\s*$/', trim($itemColHint.''))) {
                    $looksLikeItemRow = true;
                }

                $hasGeo = $hasMunCol ? ($lastMunicipioCell !== '' || $cellMun !== '') : ($lastMicroId !== null);
                if (! $rowHasAny && $insideDataBlock && $hasGeo) {
                    if ($looksLikeItemRow || $differsFromCarry) {
                        $rowHasAny = true;
                    }
                }
                if (! $rowHasAny && $hasGeo && $row <= $scanEnd && $looksLikeItemRow) {
                    $rowHasAny = true;
                }
                if (! $rowHasAny) {
                    continue;
                }

                if ($hasMunCol) {
                    $municipioSearch = $cellMun !== '' ? $cellMun : $lastMunicipioCell;
                    if ($municipioSearch === '') {
                        $stats['skipped']++;
                        $this->pushDiscarded($stats, $sheet, $row, 'Sin municipio en fila', $colMicrorregion, $colMunicipio, $fieldColumnIndices, $cellMr, '', $hasMrCol, $hasMunCol, null, null);

                        continue;
                    }
                    // Prioridad: municipio → MR por BD (sin filtrar primero por celda MR)
                    $municipioDB = $this->resolveMunicipioGlobal($municipioSearch, null, $municipiosGlobalNorm, $municipiosByMicro);
                    if (! $municipioDB && $microId !== null) {
                        $municipioDB = $this->resolveMunicipioInMicrorregion($municipioSearch, $microId, $municipiosByMicro)
                            ?: $this->resolveMunicipioGlobal($municipioSearch, $microId, $municipiosGlobalNorm, $municipiosByMicro);
                    }
                    if (! $municipioDB) {
                        $stats['skipped']++;
                        if (count($stats['unmatched']) < 150) {
                            $stats['unmatched'][] = ['row' => $row, 'reason' => 'Municipio: '.$municipioSearch];
                        }
                        $entryPayload = $this->buildSeedRowFieldPayload($sheet, $row, $fieldColumnIndices, $indexToKey, $lastFieldCarry);
                        $suggestions = $this->suggestMunicipiosForDiscard($municipioSearch, $microId, $municipiosGlobalNorm, $microLabels);
                        $this->pushDiscarded(
                            $stats,
                            $sheet,
                            $row,
                            'Municipio no resuelto: '.$municipioSearch,
                            $colMicrorregion,
                            $colMunicipio,
                            $fieldColumnIndices,
                            $cellMr,
                            $municipioSearch,
                            $hasMrCol,
                            $hasMunCol,
                            $entryPayload,
                            $suggestions,
                        );

                        continue;
                    }
                    $microrregionId = (int) $municipioDB->microrregion_id;
                    $userId = $this->resolveUserIdForMicrorregion($microrregionId) ?? $adminUserId;
                    $data = [
                        '_microrregion_reporte' => $microLabels[$microrregionId] ?? ('MR '.$microrregionId),
                        '_municipio_reporte' => (string) $municipioDB->municipio,
                    ];
                } else {
                    // Solo microrregión (sin columna municipio)
                    if ($microId === null) {
                        $stats['skipped']++;
                        $this->pushDiscarded($stats, $sheet, $row, 'Sin microrregión en fila', $colMicrorregion, $colMunicipio, $fieldColumnIndices, $cellMr, '', $hasMrCol, $hasMunCol, null, null);

                        continue;
                    }
                    $microrregionId = $microId;
                    $userId = $this->resolveUserIdForMicrorregion($microrregionId) ?? $adminUserId;
                    $cab = $cabeceraPorMicro[$microrregionId] ?? '';
                    $data = [
                        '_microrregion_reporte' => $microLabels[$microrregionId] ?? ('MR '.$microrregionId),
                        '_municipio_reporte' => $cab !== '' ? $cab.' (MR)' : 'MR '.$microrregionId,
                    ];
                }

                foreach ($fieldColumnIndices as $colIdx) {
                    $colIdx = (int) $colIdx;
                    $key = $indexToKey[$colIdx] ?? null;
                    if (! $key) {
                        continue;
                    }
                    $raw = $this->cellStrMerged($sheet, $colIdx, $row);
                    if ($raw !== '') {
                        $lastFieldCarry[$colIdx] = $raw;
                    }
                    $data[$key] = $raw !== '' ? $raw : ($lastFieldCarry[$colIdx] ?? '');
                }

                $data['_fila_excel'] = (string) $row;

                TemporaryModuleEntry::query()->create([
                    'temporary_module_id' => $module->id,
                    'user_id' => $userId,
                    'microrregion_id' => $microrregionId,
                    'data' => $data,
                    'main_image_field_key' => null,
                    'submitted_at' => Carbon::now(),
                ]);
                $microrregionIdsUsed[$microrregionId] = true;
                $assignedUserIds[$userId] = true;
                $stats['created']++;
            }

            /** Todos los delegados/enlaces de cada MR con registros pueden ver el módulo y dar seguimiento */
            $accessService = app(TemporaryModuleAccessService::class);
            $allTargetUserIds = $accessService->userIdsForMicrorregionIds(array_keys($microrregionIdsUsed));
            if ($allTargetUserIds === []) {
                $allTargetUserIds = array_keys($assignedUserIds);
            }
            $module->targetUsers()->sync($allTargetUserIds);

            $module->seed_discard_log = array_values($stats['discarded'] ?? []);
            $module->save();

            return $module;
        });
    }

    /**
     * @param  array<int>  $fieldColumnIndices
     */
    /**
     * @param  array<int, string|null>  $entryPayload  clave de campo => valor (para reintentar registro desde el log)
     * @param  list<array{id:int, municipio:string, microrregion_id:int, label:string}>|null  $suggestions
     */
    private function pushDiscarded(
        array &$stats,
        Worksheet $sheet,
        int $row,
        string $reason,
        int $colMicrorregion,
        int $colMunicipio,
        array $fieldColumnIndices,
        string $cellMr,
        string $municipioCell,
        bool $hasMrCol = true,
        bool $hasMunCol = true,
        ?array $entryPayload = null,
        ?array $suggestions = null,
    ): void {
        if (count($stats['discarded'] ?? []) >= 250) {
            return;
        }
        $accion = '';
        foreach ($fieldColumnIndices as $ci) {
            $t = $this->cellStrMerged($sheet, (int) $ci, $row);
            if (mb_strlen($t) > mb_strlen($accion)) {
                $accion = $t;
            }
        }
        if ($accion === '') {
            foreach ($fieldColumnIndices as $ci) {
                $t = $this->cellStrMerged($sheet, (int) $ci, $row);
                if ($t !== '') {
                    $accion = $t;
                    break;
                }
            }
        }
        if (mb_strlen($accion) > 220) {
            $accion = mb_substr($accion, 0, 217).'…';
        }
        $entry = [
            'discard_uid' => (string) Str::uuid(),
            'row' => $row,
            'reason' => $reason,
            'microrregion' => ($hasMrCol && $cellMr !== '') ? $cellMr : null,
            'municipio' => ($hasMunCol && $municipioCell !== '') ? $municipioCell : null,
            'accion' => $accion !== '' ? $accion : null,
        ];
        if (is_array($entryPayload) && $entryPayload !== []) {
            $entry['entry_payload'] = $entryPayload;
        }
        if (is_array($suggestions) && $suggestions !== []) {
            $entry['municipio_suggestions'] = $suggestions;
        }
        $stats['discarded'][] = $entry;
    }

    /**
     * Datos de campos del módulo para una fila (misma lógica que al crear el registro), sin tocar lastFieldCarry global.
     *
     * @param  array<int, mixed>  $lastFieldCarrySnapshot
     * @return array<string, string>
     */
    private function buildSeedRowFieldPayload(
        Worksheet $sheet,
        int $row,
        array $fieldColumnIndices,
        array $indexToKey,
        array $lastFieldCarrySnapshot,
    ): array {
        $carry = $lastFieldCarrySnapshot;
        $data = [];
        foreach ($fieldColumnIndices as $colIdx) {
            $colIdx = (int) $colIdx;
            $key = $indexToKey[$colIdx] ?? null;
            if (! $key) {
                continue;
            }
            $raw = $this->cellStrMerged($sheet, $colIdx, $row);
            if ($raw !== '') {
                $carry[$colIdx] = $raw;
            }
            $data[$key] = $raw !== '' ? $raw : ($carry[$colIdx] ?? '');
        }
        $data['_fila_excel'] = (string) $row;

        return $data;
    }

    /**
     * Sugerencias de municipio del catálogo (para el log de descartes).
     *
     * @param  array<string, list<object>>  $municipiosGlobalNorm
     * @param  array<int, string>  $microLabels
     * @return list<array{id:int, municipio:string, microrregion_id:int, label:string}>
     */
    private function suggestMunicipiosForDiscard(
        string $municipioSearch,
        ?int $hintMicroId,
        array $municipiosGlobalNorm,
        array $microLabels,
    ): array {
        $raw = trim($municipioSearch);
        // Delimitador # para no chocar con / dentro de la clase; comillas dobles para que \s sea espacio en regex.
        $variants = preg_split("#\s*[/\\\\|]+\s*|\s*,\s*#u", $raw) ?: [];
        $variants = array_values(array_filter(array_map('trim', $variants)));
        if ($variants === []) {
            $variants = [$raw];
        }
        $bestById = [];
        foreach ($variants as $variant) {
            $normV = $this->normalizeMunicipioName($variant);
            if ($normV === '') {
                continue;
            }
            foreach ($municipiosGlobalNorm as $rows) {
                foreach ($rows as $m) {
                    $id = (int) $m->id;
                    $dbN = $this->normalizeMunicipioName($m->municipio);
                    $cCell = $this->compactAlnum($normV);
                    $cDb = $this->compactAlnum($dbN);
                    $score = 0.0;
                    if ($dbN === $normV) {
                        $score = 100;
                    } elseif ($cCell !== '' && $cCell === $cDb) {
                        $score = 98;
                    } elseif (str_contains($dbN, $normV) && mb_strlen($normV) >= 4) {
                        $score = 88;
                    } elseif (str_contains($normV, $dbN) && mb_strlen($dbN) >= 5) {
                        $score = 82;
                    } else {
                        similar_text($cCell, $cDb, $pct);
                        if ($pct >= 42) {
                            $score = min(78.0, $pct * 0.82);
                        }
                    }
                    if ($hintMicroId !== null && (int) $m->microrregion_id === $hintMicroId) {
                        $score += 4;
                    }
                    if ($score < 40) {
                        continue;
                    }
                    if (! isset($bestById[$id]) || $score > $bestById[$id]['_s']) {
                        $mid = (int) $m->microrregion_id;
                        $bestById[$id] = [
                            '_s' => $score,
                            'id' => $id,
                            'municipio' => (string) $m->municipio,
                            'microrregion_id' => $mid,
                            'label' => $microLabels[$mid] ?? ('MR '.$mid),
                        ];
                    }
                }
            }
        }
        $list = array_values($bestById);
        usort($list, fn ($a, $b) => $b['_s'] <=> $a['_s']);
        $list = array_slice($list, 0, 10);
        foreach ($list as &$item) {
            unset($item['_s']);
        }
        unset($item);

        return $list;
    }

    /**
     * Crea el registro a partir de una fila del log y actualiza el JSON del módulo.
     *
     * @return array{entry: TemporaryModuleEntry, seed_discard_log: array<int, mixed>}
     */
    public function registerDiscardedSeedRow(
        TemporaryModule $module,
        string $discardUid,
        int $municipioId,
        int $actingUserId,
    ): array {
        $discardUid = trim($discardUid);
        if ($discardUid === '') {
            throw ValidationException::withMessages(['discard_uid' => 'Identificador de fila no válido.']);
        }

        $module->loadMissing('fields');
        $log = $module->seed_discard_log;
        if (! is_array($log)) {
            $log = [];
        }

        $foundIndex = null;
        $foundItem = null;
        foreach ($log as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            if (($item['discard_uid'] ?? '') === $discardUid) {
                $foundIndex = $i;
                $foundItem = $item;
                break;
            }
        }
        if ($foundItem === null || $foundIndex === null) {
            throw ValidationException::withMessages(['discard_uid' => 'Esta fila ya no está en el log. Recarga la página.']);
        }

        $reason = (string) ($foundItem['reason'] ?? '');
        if ($reason === '' || ! str_starts_with($reason, 'Municipio no resuelto')) {
            throw ValidationException::withMessages(['discard_uid' => 'Esta fila no admite registro manual desde el log.']);
        }

        $payload = $foundItem['entry_payload'] ?? null;
        if (! is_array($payload) || $payload === []) {
            throw ValidationException::withMessages(['discard_uid' => 'No hay datos guardados para esta fila (módulo creado con una versión anterior).']);
        }

        $munRow = DB::table('municipios as mu')
            ->join('microrregiones as mr', 'mr.id', '=', 'mu.microrregion_id')
            ->where('mu.id', $municipioId)
            ->select(['mu.id', 'mu.municipio', 'mu.microrregion_id'])
            ->first();
        if (! $munRow) {
            throw ValidationException::withMessages(['municipio_id' => 'El municipio no existe o no tiene microrregión asignada en el catálogo.']);
        }

        $microrregionId = (int) $munRow->microrregion_id;
        $allowedKeys = $module->fields->pluck('key')->all();
        $data = [];
        foreach ($payload as $k => $v) {
            if ($k === '_fila_excel') {
                $data[$k] = is_scalar($v) ? (string) $v : '';

                continue;
            }
            if (in_array($k, $allowedKeys, true)) {
                $data[$k] = is_scalar($v) ? (string) $v : '';
            }
        }

        $microLabels = [];
        foreach (DB::table('microrregiones')->select(['id', 'microrregion', 'cabecera'])->get() as $mr) {
            $n = str_pad(trim((string) $mr->microrregion), 2, '0', STR_PAD_LEFT);
            $id = (int) $mr->id;
            $microLabels[$id] = 'MR '.$n.' '.trim((string) ($mr->cabecera ?? ''));
        }

        $data['_microrregion_reporte'] = $microLabels[$microrregionId] ?? ('MR '.$microrregionId);
        $data['_municipio_reporte'] = (string) $munRow->municipio;

        $userId = $this->resolveUserIdForMicrorregion($microrregionId) ?? $actingUserId;

        return DB::transaction(function () use ($module, $data, $userId, $microrregionId, $log, $foundIndex, $actingUserId) {
            $entry = TemporaryModuleEntry::query()->create([
                'temporary_module_id' => $module->id,
                'user_id' => $userId,
                'microrregion_id' => $microrregionId,
                'data' => $data,
                'main_image_field_key' => null,
                'submitted_at' => Carbon::now(),
            ]);

            unset($log[$foundIndex]);
            $newLog = array_values($log);
            $module->seed_discard_log = $newLog;
            $module->save();

            $accessService = app(TemporaryModuleAccessService::class);
            $microrregionIdsUsed = TemporaryModuleEntry::query()
                ->where('temporary_module_id', $module->id)
                ->pluck('microrregion_id')
                ->unique()
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
            $allTargetUserIds = $accessService->userIdsForMicrorregionIds($microrregionIdsUsed);
            if ($allTargetUserIds === []) {
                $allTargetUserIds = [$actingUserId];
            }
            $module->targetUsers()->sync($allTargetUserIds);

            return [
                'entry' => $entry,
                'seed_discard_log' => $newLog,
            ];
        });
    }

    private function cellStr($sheet, int $colZeroBased, int $row): string
    {
        $col = $colZeroBased + 1;
        if ($col < 1) {
            return '';
        }
        $letter = Coordinate::stringFromColumnIndex($col);
        $v = $sheet->getCell($letter.$row)->getValue();

        return trim((string) ($v ?? ''));
    }

    /**
     * Valor visible como en Excel: si la celda pertenece a un merge, se lee la celda maestra (arriba-izquierda).
     * Así cada fila recupera N°, ACCIÓN, etc. aunque el municipio vaya combinado en bloque.
     */
    private function cellStrMerged($sheet, int $colZeroBased, int $row): string
    {
        $col = $colZeroBased + 1;
        if ($col < 1 || $row < 1) {
            return '';
        }
        $letter = Coordinate::stringFromColumnIndex($col);
        $coord = $letter.$row;
        try {
            foreach ($sheet->getMergeCells() as $range) {
                if (Coordinate::coordinateIsInsideRange($range, $coord)) {
                    $parts = explode(':', $range);
                    $master = $parts[0] ?? $coord;

                    return $this->cellStrFromCoord($sheet, $master);
                }
            }
        } catch (\Throwable) {
        }

        return $this->cellStr($sheet, $colZeroBased, $row);
    }

    private function cellStrFromCoord($sheet, string $coord): string
    {
        try {
            $v = $sheet->getCell($coord)->getValue();
        } catch (\Throwable) {
            return '';
        }

        return trim((string) ($v ?? ''));
    }

    private function normalize(string $s): string
    {
        $s = mb_strtoupper(preg_replace('/\s+/', ' ', trim($s)), 'UTF-8');

        return strtr($s, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N']);
    }

    /**
     * @param  array<string, int>  $microPorNumero  microrregion number string => id
     */
    private function resolveMicrorregionId(string $cellMr, array $microPorNumero): ?int
    {
        $cellMr = trim($cellMr);
        if ($cellMr === '') {
            return null;
        }
        if (preg_match('/\b(\d{1,2})\b/', $cellMr, $m)) {
            $num = (int) $m[1];
            $padded = str_pad((string) $num, 2, '0', STR_PAD_LEFT);

            return (int) ($microPorNumero[$padded] ?? $microPorNumero[(string) $num] ?? 0) ?: null;
        }

        return null;
    }

    /**
     * Fragmentos que aparecen en Excel pero en BD el nombre oficial es distinto o más largo
     * (lista 105 acciones: IXITLAN→San Miguel Ixitlán, Coxcatlan, etc.).
     *
     * @var array<string, list<string>> fragmento normalizado => subcadenas que debe contener municipio en BD
     */
    private const MUNICIPIO_SUBSTRING_IN_MR = [
        'IXITLAN' => ['IXITLAN'],
        'COXCATLAN' => ['COXCATLAN'],
        'ZIHUATEUTLA' => ['ZIHUATEUTLA'],
        'XICOTEPEC' => ['XICOTEPEC'],
        'SAN JOSE MIAHUATLAN' => ['MIAHUATLAN'],
        'VICENTE GUERRERO' => ['VICENTE GUERRERO', 'GUERRERO'],
        'CHIAUTLA DE TAPIA' => ['CHIAUTLA'],
        'COHETZALA' => ['COHETZALA'],
        'XAYACATLAN DE BRAVO' => ['XAYACATLAN'],
        'HUEHUETLAN EL CHICO' => ['HUEHUETLAN'],
        'TEPEYAHUALCO DE CUAUHTEMOC' => ['TEPEYAHUALCO'],
        'YEHUALTEPEC' => ['YEHUALTEPEC'],
        'TLALTENANGO' => ['TLALTENANGO'],
        'CORONANGO' => ['CORONANGO'],
        'NAUZONTLA' => ['NAUZONTLA'],
        'ATLEQUIZAYAN' => ['ATLEQUIZAYAN'],
        'JONOTLA' => ['JONOTLA'],
        'AMIXTLAN' => ['AMIXTLAN'],
        'CAMOCUAUTLA' => ['CAMOCUAUTLA'],
        'TEPETZINTLA' => ['TEPETZINTLA'],
        'ZACATLAN' => ['ZACATLAN'],
        'CHIGNAHUAPAN' => ['CHIGNAHUAPAN'],
        'HERMENEGILDO GALEANA' => ['GALEANA', 'HERMENEGILDO'],
        'GUADALUPE VICTORIA' => ['GUADALUPE VICTORIA'],
        'OCOTEPEC' => ['OCOTEPEC'],
        'ESPERANZA' => ['ESPERANZA'],
        'CHILA' => ['CHILA'],
        'CHALCHICOMULA DE SESMA' => ['CHALCHICOMULA', 'SESMA'],
        'JUAN GALINDO' => ['JUAN GALINDO', 'GALINDO'],
        'TLAOLA' => ['TLAOLA'],
    ];

    /**
     * Coincide municipio solo dentro de la microrregión ya resuelta (evita homónimos en otra MR).
     */
    public function resolveMunicipioInMicrorregion(string $cellMun, int $microId, array $municipiosByMicro): ?object
    {
        $candidates = $municipiosByMicro[$microId] ?? [];
        if ($candidates === []) {
            return null;
        }

        $norm = $this->normalizeMunicipioName($cellMun);
        if ($norm === '') {
            return null;
        }

        foreach (self::MUNICIPIO_SUBSTRING_IN_MR as $frag => $needles) {
            if (! str_contains($norm, $frag) && $norm !== $frag) {
                continue;
            }
            foreach ($candidates as $m) {
                $dbN = $this->normalizeMunicipioName($m->municipio);
                foreach ($needles as $nd) {
                    $nd = $this->normalizeMunicipioName($nd);
                    if ($nd !== '' && str_contains($dbN, $nd)) {
                        if ($frag === 'VICENTE GUERRERO' && ! str_contains($dbN, 'VICENTE')) {
                            continue;
                        }

                        return $m;
                    }
                }
            }
        }

        foreach ($candidates as $m) {
            if ($this->normalizeMunicipioName($m->municipio) === $norm) {
                return $m;
            }
        }

        $normCompact = $this->compactAlnum($norm);
        foreach ($candidates as $m) {
            if ($this->compactAlnum($this->normalizeMunicipioName($m->municipio)) === $normCompact) {
                return $m;
            }
        }

        foreach ($candidates as $m) {
            $dbN = $this->normalizeMunicipioName($m->municipio);
            if (str_contains($dbN, $norm) || str_contains($norm, $dbN)) {
                if (mb_strlen($norm) >= 5 || mb_strlen($dbN) >= 8) {
                    return $m;
                }
            }
        }

        $best = null;
        $bestScore = 0;
        foreach ($candidates as $m) {
            $dbN = $this->compactAlnum($this->normalizeMunicipioName($m->municipio));
            similar_text($normCompact, $dbN, $pct);
            if ($pct > $bestScore && $pct >= 80) {
                $bestScore = $pct;
                $best = $m;
            }
        }

        // Misma regla primera palabra solo entre candidatos de esta MR
        $byFirst = $this->resolveMunicipioByFirstWordInCandidates($norm, $candidates);
        if ($byFirst !== null) {
            return $byFirst;
        }

        return $best;
    }

    /**
     * @param  list<object>  $candidates
     */
    private function resolveMunicipioByFirstWordInCandidates(string $norm, array $candidates): ?object
    {
        $parts = preg_split('/\s+/', $norm, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) < 2 || mb_strlen($parts[0]) < 4) {
            return null;
        }
        $first = $this->normalizeMunicipioName($parts[0]);
        $second = $parts[1] ?? '';
        if (! in_array($second, ['DE', 'DEL', 'Y'], true) && mb_strlen($first) < 7) {
            return null;
        }
        $fc = $this->compactAlnum($first);
        $hits = [];
        foreach ($candidates as $m) {
            $dbN = $this->normalizeMunicipioName($m->municipio);
            if ($dbN === $first || $this->compactAlnum($dbN) === $fc) {
                $hits[] = $m;
            }
        }
        if (count($hits) === 1) {
            return $hits[0];
        }

        return null;
    }

    /** MR por nombre cabecera (ej. celda "14 AJALPAN" → token AJALPAN) */
    private function resolveMicrorregionByName(string $cellMr, array $microByNormToken): ?int
    {
        $norm = $this->normalizeMunicipioName($cellMr);
        if ($norm === '') {
            return null;
        }
        foreach ($microByNormToken as $token => $id) {
            if ($token !== '' && str_contains($norm, $token)) {
                return $id;
            }
        }

        return null;
    }

    /**
     * @param  array<string, list<object>>  $municipiosGlobalNorm
     */
    public function resolveMunicipioGlobal(
        string $cellMun,
        ?int $preferredMicroId,
        array $municipiosGlobalNorm,
        array $municipiosByMicro,
    ): ?object {
        $norm = $this->normalizeMunicipioName($cellMun);
        if ($norm === '') {
            return null;
        }
        $compact = $this->compactAlnum($norm);
        if ($preferredMicroId !== null && str_contains($norm, 'MIAHUATLAN')) {
            foreach ($municipiosByMicro[$preferredMicroId] ?? [] as $m) {
                if (str_contains($this->normalizeMunicipioName($m->municipio), 'MIAHUATLAN')) {
                    return $m;
                }
            }
        }
        if ($preferredMicroId !== null && (str_contains($norm, 'COXCATLAN') || str_contains($norm, 'COXCATL'))) {
            foreach ($municipiosByMicro[$preferredMicroId] ?? [] as $m) {
                if (str_contains($this->compactAlnum($this->normalizeMunicipioName($m->municipio)), 'COXCATLAN')) {
                    return $m;
                }
            }
        }
        $list = $municipiosGlobalNorm[$norm] ?? [];
        if ($list === []) {
            foreach ($municipiosGlobalNorm as $rows) {
                foreach ($rows as $m) {
                    if ($this->compactAlnum($this->normalizeMunicipioName($m->municipio)) === $compact) {
                        $list[] = $m;
                    }
                }
            }
        }
        if ($preferredMicroId !== null) {
            foreach ($list as $m) {
                if ((int) $m->microrregion_id === $preferredMicroId) {
                    return $m;
                }
            }
        }
        if (count($list) === 1) {
            return $list[0];
        }

        // Excel "CHIAUTLA DE TAPIA" → BD municipio "Chiautla" (solo primera palabra)
        $byFirst = $this->resolveMunicipioByFirstWord($norm, $preferredMicroId, $municipiosGlobalNorm);
        if ($byFirst !== null) {
            return $byFirst;
        }

        $best = null;
        $bestScore = 0;
        foreach ($municipiosByMicro as $microId => $candidates) {
            foreach ($candidates as $m) {
                $dbN = $this->compactAlnum($this->normalizeMunicipioName($m->municipio));
                similar_text($compact ?? $this->compactAlnum($norm), $dbN, $pct);
                if ($pct > $bestScore && $pct >= 76) {
                    $bestScore = $pct;
                    $best = $m;
                }
            }
        }

        return $best;
    }

    /**
     * Si el texto viene compuesto (p. ej. "CHIAUTLA DE TAPIA") y en BD el municipio es la primera palabra ("CHIAUTLA").
     */
    private function resolveMunicipioByFirstWord(
        string $norm,
        ?int $preferredMicroId,
        array $municipiosGlobalNorm,
    ): ?object {
        $parts = preg_split('/\s+/', $norm, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) < 2) {
            return null;
        }
        $first = $parts[0];
        if (mb_strlen($first) < 4) {
            return null;
        }
        // Disparar si lleva "DE", "DEL", "Y" como segunda palabra (composición típica)
        $second = $parts[1] ?? '';
        $compound = in_array($second, ['DE', 'DEL', 'Y'], true)
            || (count($parts) >= 3 && $second === 'JOSE'); // SAN JOSE ...
        if (! $compound && count($parts) < 3) {
            return null;
        }
        if (! $compound && ! in_array($second, ['DE', 'DEL', 'Y'], true)) {
            // Varias palabras sin "DE": solo si primera palabra es larga (evita "SAN PEDRO" → SAN)
            if (mb_strlen($first) < 7) {
                return null;
            }
        }

        $candidates = $municipiosGlobalNorm[$first] ?? [];
        if ($candidates === []) {
            $fc = $this->compactAlnum($first);
            foreach ($municipiosGlobalNorm as $rows) {
                foreach ($rows as $m) {
                    if ($this->compactAlnum($this->normalizeMunicipioName($m->municipio)) === $fc) {
                        $candidates[] = $m;
                    }
                }
            }
        }
        $seen = [];
        $uniq = [];
        foreach ($candidates as $m) {
            $id = (int) $m->id;
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $uniq[] = $m;
        }
        $candidates = $uniq;
        if ($candidates === []) {
            return null;
        }
        if ($preferredMicroId !== null) {
            foreach ($candidates as $m) {
                if ((int) $m->microrregion_id === $preferredMicroId) {
                    return $m;
                }
            }
        }
        if (count($candidates) === 1) {
            return $candidates[0];
        }

        return null;
    }

    private function normalizeMunicipioName(string $s): string
    {
        $s = mb_strtoupper(preg_replace('/\s+/', ' ', trim($s)), 'UTF-8');
        $map = ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N', 'À' => 'A'];

        return strtr($s, $map);
    }

    private function compactAlnum(string $s): string
    {
        return preg_replace('/[^A-Z0-9]/', '', $s) ?? '';
    }

    private function resolveUserIdForMicrorregion(int $microrregionId): ?int
    {
        $delegado = DB::table('delegados')->where('microrregion_id', $microrregionId)->value('user_id');
        if ($delegado) {
            return (int) $delegado;
        }

        $uid = DB::table('user_microrregion')
            ->where('microrregion_id', $microrregionId)
            ->orderBy('user_id')
            ->value('user_id');

        return $uid ? (int) $uid : null;
    }

    /**
     * Normaliza los valores de un campo municipio ya existente (guardado como texto)
     * contra el catálogo oficial de municipios.
     *
     * @return array{updated:int,skipped:int,unmatched:list<string>}
     */
    public function normalizeMunicipioField(TemporaryModule $module, string $fieldKey = 'municipio'): array
    {
        $entries = TemporaryModuleEntry::query()
            ->where('temporary_module_id', $module->id)
            ->select(['id', 'microrregion_id', 'data'])
            ->get();

        if ($entries->isEmpty()) {
            return ['updated' => 0, 'skipped' => 0, 'unmatched' => []];
        }

        $municipiosRows = DB::table('municipios as mu')
            ->join('microrregiones as mr', 'mr.id', '=', 'mu.microrregion_id')
            ->select(['mu.id', 'mu.municipio', 'mu.microrregion_id', 'mr.microrregion as mr_num'])
            ->get();

        $municipiosByMicro = [];
        foreach ($municipiosRows as $m) {
            $mid = (int) $m->microrregion_id;
            $municipiosByMicro[$mid][] = $m;
        }

        $municipiosGlobalNorm = [];
        foreach ($municipiosRows as $m) {
            $k = $this->normalizeMunicipioName($m->municipio);
            $municipiosGlobalNorm[$k][] = $m;
        }

        $updated = 0;
        $skipped = 0;
        $unmatched = [];

        foreach ($entries as $entry) {
            $data = $entry->data ?? [];
            $raw = $data[$fieldKey] ?? null;
            if (! is_string($raw) || trim($raw) === '') {
                $skipped++;

                continue;
            }

            $municipioSearch = trim($raw);
            $municipioDB = $this->resolveMunicipioGlobal($municipioSearch, null, $municipiosGlobalNorm, $municipiosByMicro);

            $microId = (int) ($entry->microrregion_id ?? 0);
            if (! $municipioDB && $microId > 0) {
                $municipioDB = $this->resolveMunicipioInMicrorregion($municipioSearch, $microId, $municipiosByMicro)
                    ?: $this->resolveMunicipioGlobal($municipioSearch, $microId, $municipiosGlobalNorm, $municipiosByMicro);
            }

            if (! $municipioDB) {
                $skipped++;
                if (count($unmatched) < 100) {
                    $unmatched[] = $municipioSearch;
                }

                continue;
            }

            $nombre = (string) $municipioDB->municipio;
            if (($data[$fieldKey] ?? null) === $nombre) {
                continue;
            }

            $data[$fieldKey] = $nombre;
            TemporaryModuleEntry::query()->whereKey($entry->id)->update(['data' => $data]);
            $updated++;
        }

        $unmatched = array_values(array_unique($unmatched));

        return ['updated' => $updated, 'skipped' => $skipped, 'unmatched' => $unmatched];
    }
}
