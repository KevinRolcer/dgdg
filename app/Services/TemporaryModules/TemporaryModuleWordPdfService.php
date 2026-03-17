<?php

namespace App\Services\TemporaryModules;

use App\Models\TemporaryModule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Section as SectionStyle;
use Dompdf\Dompdf;

class TemporaryModuleWordPdfService
{
    /**
     * @param string $format 'word' or 'pdf'
     * @param array|null $exportConfig
     * @return array{name: string, url: string}
     * @throws \Exception
     */
    public function export(int $moduleId, string $format, ?array $exportConfig = null): array
    {
        $temporaryModule = TemporaryModule::query()->findOrFail($moduleId);
        $fileName = trim((string) $temporaryModule->name) !== '' ? $temporaryModule->name : 'Módulo '.$moduleId;
        
        $columnsCfg = is_array($exportConfig) && isset($exportConfig['columns']) && is_array($exportConfig['columns'])
            ? $exportConfig['columns']
            : [];
            
        // Si no hay configuracion de columnas, tomar todas como es el caso por defecto.
        if ($columnsCfg === []) {
             $cols = [];
             foreach ($temporaryModule->fields as $field) {
                 $cols[] = ['key' => $field->key, 'label' => (string) ($field->label ?? $field->key)];
             }
             if(count($cols) > 0) {
                 $columnsCfg = $cols;
             }
        }

        $columnMap = [];
        foreach ($columnsCfg as $col) {
            if (!is_array($col)) {
                continue;
            }
            $key = (string) ($col['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $columnMap[$key] = [
                'key' => $key,
                'label' => (string) ($col['label'] ?? $key),
            ];
        }
        $columns = array_values($columnMap);
        if ($columns === []) {
            throw new \Exception('No hay columnas seleccionadas para el reporte.');
        }

        $totalCols = count($columns);
        $stretch = ($exportConfig['table_align'] ?? 'left') === 'stretch';

        $microrregionIds = $temporaryModule->entries()
            ->withoutGlobalScopes()
            ->reorder()
            ->select('microrregion_id')
            ->distinct()
            ->pluck('microrregion_id')
            ->filter()
            ->values()
            ->all();

        $microrregionMeta = DB::table('microrregiones')
            ->select(['id', 'cabecera', 'microrregion'])
            ->whereIn('id', $microrregionIds)
            ->get()
            ->mapWithKeys(function ($row) {
                $number = trim((string) ($row->microrregion ?? ''));
                $name = trim((string) ($row->cabecera ?? ''));

                $label = $number !== ''
                    ? ('MR '.str_pad($number, 2, '0', STR_PAD_LEFT).($name !== '' ? ' — '.$name : ''))
                    : ($name !== '' ? $name : 'Sin microrregión');

                return [(int) $row->id => [
                    'number' => $number,
                    'name' => $name,
                    'label' => $label,
                ]];
            });

        $entries = $temporaryModule->entries()
            ->withoutGlobalScopes()
            ->orderBy('submitted_at')
            ->get(['microrregion_id', 'data', 'submitted_at']);

        $baseSlug = Str::slug($fileName, '_') ?: 'modulo_temporal_'.$temporaryModule->id;
        $exportDir = storage_path('app/public/temporary-exports');
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $title = (string) ($exportConfig['title'] ?? $fileName);
        $orientationConfig = ($exportConfig['orientation'] ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';

        if ($format === 'word') {
            $wordFileName = $baseSlug.'_'.now()->format('Ymd_His').'.docx';
            $fullPath = $exportDir.'/'.$wordFileName;

            $phpWord = new \PhpOffice\PhpWord\PhpWord();
            $phpWord->setDefaultFontName('Calibri');
            $phpWord->setDefaultFontSize(10);
            $orientation = $orientationConfig === 'landscape'
                ? \PhpOffice\PhpWord\Style\Section::ORIENTATION_LANDSCAPE
                : \PhpOffice\PhpWord\Style\Section::ORIENTATION_PORTRAIT;
            $section = $phpWord->addSection([
                'orientation' => $orientation,
                'marginTop' => 1134,
                'marginBottom' => 1134,
                'marginLeft' => 1134,
                'marginRight' => 1134,
            ]);

            $align = (string) ($exportConfig['title_align'] ?? 'center');
            $jc = match ($align) {
                'left' => \PhpOffice\PhpWord\SimpleType\Jc::START,
                'right' => \PhpOffice\PhpWord\SimpleType\Jc::END,
                default => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
            };
            $section->addText($title, ['bold' => true, 'size' => 14, 'color' => '861E34'], ['alignment' => $jc, 'spaceAfter' => 200]);
            $section->addTextBreak(1);

            $tblStyle = [
                'borderSize' => 6,
                'borderColor' => '444444',
                'cellMargin' => 80,
            ];
            
            if ($stretch) {
                $tblStyle['width'] = 100;
                $tblStyle['unit'] = 'pct';
            }
            
            $table = $section->addTable($tblStyle);

            $dynTwips = null;
            if ($stretch && $totalCols > 0) {
                // Aprox ancho total disponible en horizontal A4 son 9000 twips (depende de márgenes)
                $dynTwips = (int) round(9000 / $totalCols);
            }

            // Encabezados
            $table->addRow();
            foreach ($columns as $col) {
                $table->addCell($dynTwips)->addText((string) $col['label'], ['bold' => true]);
            }

            // Filas
            $itemNumber = 1;
            foreach ($entries as $entry) {
                $table->addRow();
                foreach ($columns as $col) {
                    $key = $col['key'];
                    if ($key === 'item') {
                        $text = (string) $itemNumber;
                        $itemNumber++;
                    } elseif ($key === 'microrregion') {
                        $meta = $microrregionMeta->get((int) ($entry->microrregion_id ?? 0));
                        $text = (string) ($meta['label'] ?? $meta->label ?? '');
                    } else {
                        $val = $entry->data[$key] ?? null;
                        if (is_bool($val)) {
                            $text = $val ? 'Sí' : 'No';
                        } elseif (is_array($val)) {
                            $text = implode(', ', array_map(static fn ($v) => is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE), $val));
                        } elseif (is_scalar($val)) {
                            $text = (string) $val;
                        } else {
                            $text = '';
                        }
                    }
                    $table->addCell($dynTwips)->addText($text);
                }
            }

            \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007')->save($fullPath);

            return [
                'name' => $wordFileName,
                'url' => route('temporary-modules.admin.exports.download', ['file' => $wordFileName])
            ];
        }

        // PDF
        $pdfFileName = $baseSlug.'_'.now()->format('Ymd_His').'.pdf';
        $fullPdfPath = $exportDir.'/'.$pdfFileName;

        $html = view('temporary_modules.admin.partials.export_pdf_table', [
            'title' => $title,
            'orientation' => $orientationConfig,
            'columns' => $columns,
            'entries' => $entries,
            'microrregionMeta' => $microrregionMeta,
            'stretch' => $stretch,
        ])->render();

        $dompdf = new Dompdf([
            'defaultPaperSize' => 'a4',
        ]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', $orientationConfig === 'landscape' ? 'landscape' : 'portrait');
        $dompdf->render();
        file_put_contents($fullPdfPath, $dompdf->output());

        return [
            'name' => $pdfFileName,
            'url' => route('temporary-modules.admin.exports.download', ['file' => $pdfFileName])
        ];
    }
}
