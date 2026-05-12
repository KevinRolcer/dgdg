<?php

namespace App\Services\TemporaryModules;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class TemporaryModulePythonExcelService
{
    private ?bool $available = null;

    public function supports(UploadedFile|string $file): bool
    {
        $extension = '';
        if ($file instanceof UploadedFile) {
            $extension = strtolower($file->getClientOriginalExtension() ?: pathinfo((string) $file->getClientOriginalName(), PATHINFO_EXTENSION));
        } else {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        }

        return $extension === 'xlsx';
    }

    /**
     * @return array{success:bool,headers:list<array{index:int,letter:string,label:string}>,suggested_map:array,header_row:int,sheet_names:list<string>,sheet_index:int,preview_rows:list<array{row:int,cells:list<string>}>}|null
     */
    public function previewHeaders(UploadedFile|string $file, int $headerRow, int $sheetIndex = 0): ?array
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        if (! $path || ! $this->supports($file) || ! $this->isAvailable()) {
            return null;
        }

        return $this->runJson([
            'preview',
            '--file', $path,
            '--header-row', (string) max(1, $headerRow),
            '--sheet-index', (string) max(0, $sheetIndex),
            '--max-preview-rows', '12',
        ], 120);
    }

    public function exportSheetToCsv(string $xlsxAbsolutePath, int $sheetIndex, int $dataStartRow, string $csvAbsolutePath): ?int
    {
        if (! $this->supports($xlsxAbsolutePath) || ! $this->isAvailable()) {
            return null;
        }

        $payload = $this->runJson([
            'export-csv',
            '--file', $xlsxAbsolutePath,
            '--sheet-index', (string) max(0, $sheetIndex),
            '--data-start-row', (string) max(1, $dataStartRow),
            '--output', $csvAbsolutePath,
        ], 0);

        if (! is_array($payload) || ($payload['success'] ?? false) !== true) {
            return null;
        }

        return (int) ($payload['rows'] ?? 0);
    }

    private function isAvailable(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }

        if (! is_file($this->scriptPath())) {
            return $this->available = false;
        }

        try {
            $process = new Process([$this->pythonBinary(), '-c', 'import openpyxl']);
            $process->setEnv(['PYTHONIOENCODING' => 'utf-8']);
            $process->setTimeout(10);
            $process->run();

            return $this->available = $process->isSuccessful();
        } catch (\Throwable $e) {
            Log::info('Python Excel no disponible: '.$e->getMessage());

            return $this->available = false;
        }
    }

    /**
     * @param  list<string>  $arguments
     */
    private function runJson(array $arguments, int $timeoutSeconds): ?array
    {
        $process = new Process(array_merge([$this->pythonBinary(), $this->scriptPath()], $arguments), base_path());
        $process->setEnv(['PYTHONIOENCODING' => 'utf-8']);
        $process->setTimeout($timeoutSeconds > 0 ? $timeoutSeconds : null);
        $process->setIdleTimeout($timeoutSeconds > 0 ? $timeoutSeconds : null);
        $process->run();

        if (! $process->isSuccessful()) {
            Log::warning('Python Excel falló: '.trim($process->getErrorOutput() ?: $process->getOutput()));

            return null;
        }

        $payload = json_decode(trim($process->getOutput()), true);
        if (! is_array($payload) || ($payload['success'] ?? false) !== true) {
            Log::warning('Python Excel devolvió una respuesta inválida.');

            return null;
        }

        return $payload;
    }

    private function pythonBinary(): string
    {
        return (string) (env('TEMPORARY_MODULE_PYTHON') ?: 'python');
    }

    private function scriptPath(): string
    {
        return base_path('scripts/temporary_modules/xlsx_seed.py');
    }
}
