<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateTemporaryModuleAnalysisWordJob;
use App\Models\TemporaryModule;
use App\Models\TemporaryModuleEntry;
use App\Services\TemporaryModules\TemporaryModuleAccessService;
use App\Services\TemporaryModules\TemporaryModuleAdminSeedService;
use App\Services\TemporaryModules\TemporaryModuleAnalysisWordService;
use App\Services\TemporaryModules\TemporaryModuleEntryDataService;
use App\Services\TemporaryModules\TemporaryModuleExcelImportService;
use App\Services\TemporaryModules\TemporaryModulePdfImportService;
use App\Services\TemporaryModules\TemporaryModuleExportService;
use App\Services\TemporaryModules\TemporaryModuleFieldService;
use App\Services\TemporaryModules\TemporaryModuleSlugService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;

class TemporaryModuleController extends Controller
{
    public function __construct(
        private readonly TemporaryModuleAccessService $accessService,
        private readonly TemporaryModuleFieldService $fieldService,
        private readonly TemporaryModuleEntryDataService $entryDataService,
        private readonly TemporaryModuleExportService $exportService,
        private readonly TemporaryModuleExcelImportService $excelImportService,
        private readonly TemporaryModulePdfImportService $pdfImportService,
        private readonly TemporaryModuleAdminSeedService $adminSeedService,
        private readonly TemporaryModuleSlugService $slugService,
    ) {}

    /** Solo se resuelve al usar preview Word (evita cargar PhpWord en el resto de acciones). */
    private function analysisWordService(): TemporaryModuleAnalysisWordService
    {
        return app(TemporaryModuleAnalysisWordService::class);
    }

    /**
     * Decodifica cfg de exportación: GET trae JSON en query; POST puede traer Base64 (UTF-8) para evitar corrupción en campos largos.
     *
     * @return array<string, mixed>|null
     */
    private function decodeTemporaryModuleExportConfig(Request $request): ?array
    {
        $raw = $request->input('cfg', $request->query('cfg'));
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $trim = ltrim($raw);
        $jsonString = $raw;
        if ($trim !== '' && $trim[0] !== '{' && $trim[0] !== '[') {
            $binary = base64_decode($raw, true);
            if ($binary !== false && $binary !== '') {
                $jsonString = $binary;
            }
        }

        try {
            $data = json_decode($jsonString, true, 1024, JSON_THROW_ON_ERROR);

            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            Log::warning('temporary_module.export.cfg_decode_failed', [
                'message' => $e->getMessage(),
                'prefix' => substr($raw, 0, 120),
            ]);

            return null;
        }
    }

    private function resolveMicrorregionSortDirection(?string $direction): string
    {
        $normalized = strtolower(trim((string) $direction));

        return in_array($normalized, ['asc', 'desc'], true) ? $normalized : 'asc';
    }

    private const FIELD_TYPES = [
        'text' => 'Texto',
        'textarea' => 'Texto largo',
        'number' => 'Número',
        'date' => 'Fecha',
        'datetime' => 'Fecha y hora',
        'select' => 'Lista de opciones',
        'multiselect' => 'Selección múltiple',
        'linked' => 'Campo vinculado (principal + dependiente)',
        'categoria' => 'Categoría',
        'municipio' => 'Municipio',
        'boolean' => 'Sí / No',
        'semaforo' => 'Semáforo (Verde / Amarillo / Rojo)',
        'seccion' => 'Sección',
        'geopoint' => 'Georreferencia',
        'image' => 'Imagen',
        'delegado' => 'Lista de Delegados (Personal activo)',
    ];

    private function findDuplicateEntryId(TemporaryModule $temporaryModule, int $microrregionId, array $values, ?int $excludeEntryId = null): ?int
    {
        $targetSignature = $this->excelImportService->buildDuplicateSignature($values);
        $duplicateId = null;

        $temporaryModule->entries()
            ->select(['id', 'data'])
            ->where('microrregion_id', $microrregionId)
            ->when($excludeEntryId !== null, fn ($q) => $q->where('id', '!=', $excludeEntryId))
            ->orderBy('id')
            ->chunk(500, function ($entries) use ($targetSignature, &$duplicateId) {
                foreach ($entries as $entry) {
                    $entrySignature = $this->excelImportService->buildDuplicateSignature((array) ($entry->data ?? []));
                    if (hash_equals($targetSignature, $entrySignature)) {
                        $duplicateId = (int) $entry->id;

                        return false;
                    }
                }

                return true;
            });

        return $duplicateId;
    }

    public function adminIndex(Request $request): View
    {
        $searchQuery = trim((string) $request->input('q', ''));
        if (mb_strlen($searchQuery) > 200) {
            $searchQuery = mb_substr($searchQuery, 0, 200);
        }

        $modulesQuery = TemporaryModule::query()
            ->select(['id', 'name', 'description', 'expires_at', 'applies_to_all', 'is_active'])
            ->withCount(['fields', 'entries', 'targetUsers']);

        if ($searchQuery !== '') {
            $like = '%'.addcslashes($searchQuery, '%_\\').'%';
            $modulesQuery->where(function ($q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        }

        $modules = $modulesQuery->latest()->paginate(15)->withQueryString();

        return view('temporary_modules.admin.index', [
            'pageTitle' => 'Modulos temporales',
            'pageDescription' => 'Configura apartados temporales para captura de informacion por delegados.',
            'topbarNotifications' => [],
            'modules' => $modules,
            'searchQuery' => $searchQuery,
        ]);
    }

    public function downloadTemplate(Request $request, int $module): RedirectResponse
    {
        $temporaryModule = TemporaryModule::query()->findOrFail($module);
        $user = $request->user();
        $isAdmin = Gate::forUser($user)->allows('modulos-temporales-admin-ver');

        abort_unless(
            $isAdmin || $this->accessService->userCanAccessModule($temporaryModule, (int) $user->id),
            403
        );

        $scope = (string) $request->query('scope', 'blank');
        if (! in_array($scope, ['blank', 'microrregion', 'all'], true)) {
            $scope = 'blank';
        }

        if ($scope === 'blank') {
            $result = $this->exportService->generateTemplate($temporaryModule);

            return redirect($result['url']);
        }

        $allowedMicrorregionIds = $isAdmin
            ? []
            : $this->accessService->microrregionIdsPorUsuario((int) $user->id);

        if ($scope === 'microrregion') {
            $requestedMicrorregionId = $request->filled('selected_microrregion_id')
                ? (int) $request->query('selected_microrregion_id')
                : 0;

            if ($requestedMicrorregionId < 1 && ! $isAdmin && count($allowedMicrorregionIds) === 1) {
                $requestedMicrorregionId = (int) $allowedMicrorregionIds[0];
            }

            abort_unless($requestedMicrorregionId > 0, 422, 'Selecciona una microrregión válida.');

            if (! $isAdmin) {
                abort_unless(in_array($requestedMicrorregionId, $allowedMicrorregionIds, true), 403);
            }

            $result = $this->exportService->generateTemplate($temporaryModule, [
                'with_data' => true,
                'microrregion_id' => $requestedMicrorregionId,
            ]);

            return redirect($result['url']);
        }

        return redirect()->route('temporary-modules.download-template', [
            'module' => $temporaryModule->id,
            'scope' => 'blank',
        ]);
    }

    public function downloadTemplateFile(Request $request, string $file): BinaryFileResponse
    {
        $file = trim($file);
        abort_unless(preg_match('/\A[A-Za-z0-9._\-]+\.xlsx\z/', $file) === 1, 404);

        $path = storage_path('app/public/temporary-exports/'.$file);
        abort_unless(is_file($path), 404);

        return response()->download($path, $file);
    }

    public function adminRecords(Request $request): View
    {
        // Evita 500 si en producción no corrió la migración de exported_at / seed_discard_log
        $select = ['id', 'name', 'description', 'expires_at'];
        if (Schema::hasColumn('temporary_modules', 'exported_at')) {
            $select[] = 'exported_at';
        }
        if (Schema::hasColumn('temporary_modules', 'seed_discard_log')) {
            $select[] = 'seed_discard_log';
        }

        $searchQuery = trim((string) $request->input('q', ''));
        if (mb_strlen($searchQuery) > 200) {
            $searchQuery = mb_substr($searchQuery, 0, 200);
        }

        $modulesQuery = TemporaryModule::query()
            ->select($select)
            ->with(['entries.user', 'entries.microrregion', 'fields'])
            ->whereHas('entries')
            ->withCount(['fields', 'entries']);

        if ($searchQuery !== '') {
            $like = '%'.addcslashes($searchQuery, '%_\\').'%';
            $modulesQuery->where(function ($q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        }

        $modules = $modulesQuery->latest()->paginate(15)->withQueryString();

        return view('temporary_modules.admin.records', [
            'pageTitle' => 'Registros de modulos temporales',
            'pageDescription' => 'Consulta registros de delegados y exporta resultados en Excel.',
            'topbarNotifications' => [],
            'modules' => $modules,
            'searchQuery' => $searchQuery,
        ]);
    }

    public function create(): View
    {
        $modulesForCopy = TemporaryModule::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        return view('temporary_modules.admin.create', [
            'pageTitle' => 'Crear modulo temporal',
            'pageDescription' => 'Define nombre, campos requeridos y fecha limite de visualizacion.',
            'topbarNotifications' => [],
            'fieldTypes' => self::FIELD_TYPES,
            'delegates' => $this->accessService->delegates(),
            'modulesForCopy' => $modulesForCopy,
        ]);
    }

    public function createFromExcel(): View
    {
        return view('temporary_modules.admin.create_from_excel', [
            'pageTitle' => 'Módulo desde base (Excel)',
            'pageDescription' => 'Carga un archivo con microrregión, municipio y columnas de datos; se crean los campos y un registro por fila para cada enlace.',
            'topbarNotifications' => [],
        ]);
    }

    public function seedPreview(Request $request): JsonResponse
    {
        $request->validate([
            'archivo_excel' => ['required', 'file', 'mimes:xlsx,xls', 'max:153600'],
            'header_row' => ['nullable', 'integer', 'min:1', 'max:200'],
            'auto_detect' => ['nullable', 'boolean'],
            'sheet_index' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);
        $file = $request->file('archivo_excel');
        $autoDetect = $request->boolean('auto_detect', true);
        $headerRow = (int) ($request->input('header_row') ?: 1);
        $sheetIndex = (int) ($request->input('sheet_index') ?: 0);
        $detected = null;

        if ($autoDetect) {
            try {
                $detected = $this->adminSeedService->detectTableLayout($file, 80, $sheetIndex);
                if ($detected !== null) {
                    $headerRow = $detected['header_row'];
                }
            } catch (\Throwable) {
                // sigue con header_row manual o 1
            }
        }

        try {
            $preview = $this->adminSeedService->previewHeaders($file, $headerRow, $sheetIndex);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $payload = [
            'success' => true,
            'headers' => $preview['headers'],
            'preview_rows' => $preview['preview_rows'] ?? [],
            'column_suggestions' => $preview['column_suggestions'] ?? [],
            'header_row' => $headerRow,
            'data_start_row' => $headerRow + 1,
            'sheet_names' => $preview['sheet_names'] ?? [],
            'sheet_index' => $preview['sheet_index'] ?? $sheetIndex,
        ];
        if ($autoDetect && isset($detected)) {
            $payload['auto_detected'] = true;
            $payload['header_row'] = $detected['header_row'];
            $payload['data_start_row'] = $detected['data_start_row'];
            $payload['detection_note'] = $detected['note'];
        } elseif ($autoDetect) {
            $payload['auto_detected'] = false;
            $payload['detection_note'] = 'No se detectó tabla con MUNICIPIO + MICRORREGIÓN; usa fila de encabezados manual.';
        }

        return response()->json($payload);
    }

    public function seedStore(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:today'],
            'is_indefinite' => ['nullable', 'boolean'],
            'archivo_excel' => ['required', 'file', 'mimes:xlsx,xls', 'max:153600'],
            'header_row' => ['nullable', 'integer', 'min:1', 'max:50'],
            'data_start_row' => ['nullable', 'integer', 'min:2', 'max:50000'],
            'col_microrregion' => ['nullable', 'integer', 'min:-1'],
            'col_municipio' => ['nullable', 'integer', 'min:-1'],
            'field_columns' => ['required', 'string'],
            'field_types' => ['nullable', 'string'],
            'field_options' => ['nullable', 'string'],
            'field_unifications' => ['nullable', 'string'],
            'sheet_index' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $colMr = (int) $request->input('col_microrregion', -1);
        $colMun = (int) $request->input('col_municipio', -1);
        if ($colMun < 0 && $colMr < 0) {
            throw ValidationException::withMessages([
                'col_municipio' => 'Indica columna Municipio o columna Microrregión (o ambas).',
            ]);
        }

        $fieldColumns = json_decode((string) $request->input('field_columns'), true);
        if (! is_array($fieldColumns) || $fieldColumns === []) {
            throw ValidationException::withMessages(['field_columns' => 'Elige al menos una columna como campo del módulo.']);
        }
        $fieldColumns = array_map('intval', $fieldColumns);

        $fieldTypes = json_decode((string) $request->input('field_types', '{}'), true);
        if (! is_array($fieldTypes)) {
            $fieldTypes = [];
        }
        $allowedFieldTypes = ['text', 'textarea', 'number', 'date', 'datetime', 'select', 'multiselect', 'municipio', 'boolean', 'semaforo'];
        $fieldTypes = collect($fieldTypes)
            ->mapWithKeys(function ($type, $idx) use ($allowedFieldTypes) {
                $i = (int) $idx;
                $t = strtolower(trim((string) $type));

                return [$i => in_array($t, $allowedFieldTypes, true) ? $t : 'text'];
            })
            ->all();

        $fieldOptions = json_decode((string) $request->input('field_options', '{}'), true);
        if (! is_array($fieldOptions)) {
            $fieldOptions = [];
        }
        $fieldOptions = collect($fieldOptions)
            ->mapWithKeys(function ($value, $idx) {
                return [(int) $idx => is_scalar($value) ? trim((string) $value) : ''];
            })
            ->all();

        $fieldUnifications = json_decode((string) $request->input('field_unifications', '{}'), true);
        if (! is_array($fieldUnifications)) {
            $fieldUnifications = [];
        }
        $fieldUnifications = collect($fieldUnifications)
            ->mapWithKeys(function ($value, $idx) {
                return [(int) $idx => is_scalar($value) ? trim((string) $value) : ''];
            })
            ->all();

        $isIndefinite = (bool) $request->boolean('is_indefinite');
        $expiresAt = null;
        if (! $isIndefinite) {
            $request->validate(['expires_at' => ['required', 'date']]);
            $expiresAt = Carbon::parse($request->input('expires_at'));
        }

        $stats = [];
        try {
            $module = $this->adminSeedService->createModuleFromExcel(
                (int) $request->user()->id,
                $request->input('name'),
                $request->input('description'),
                $expiresAt,
                $isIndefinite,
                $request->file('archivo_excel'),
                (int) ($request->input('header_row') ?: 1),
                (int) ($request->input('data_start_row') ?: ((int) ($request->input('header_row') ?: 1) + 1)),
                $colMr,
                $colMun,
                $fieldColumns,
                $fieldTypes,
                $fieldOptions,
                $fieldUnifications,
                $stats,
                (int) ($request->input('sheet_index') ?: 0),
            );
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['archivo_excel' => $e->getMessage()]);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['archivo_excel' => 'Error: '.$e->getMessage()]);
        }

        if (($stats['created'] ?? 0) < 1) {
            $module->forceDelete();

            return redirect()
                ->route('temporary-modules.admin.create-from-excel')
                ->withErrors(['archivo_excel' => 'No se creó ningún registro. Revisa MR/municipio y que exista delegado o enlace por microrregión. Filas no coincidentes: '.count($stats['unmatched'] ?? []).'.']);
        }

        $msg = "Módulo creado con {$stats['created']} registro(s) precargado(s). Los enlaces pueden completar campos adicionales desde Editar módulo.";
        if (($stats['skipped'] ?? 0) > 0) {
            $msg .= ' Revisa el botón Log si hubo filas omitidas.';
        }

        return redirect()
            ->route('temporary-modules.admin.edit', $module->id)
            ->with('status', $msg)
            ->with('show_seed_log', true);
    }

    public function fieldsJson(int $module): JsonResponse
    {
        $temporaryModule = TemporaryModule::query()->with('fields')->findOrFail($module);
        $fields = $temporaryModule->fields->map(function ($field) {
            $row = [
                'label' => $field->label,
                'key' => $field->key,
                'type' => $field->type,
                'required' => (bool) $field->is_required,
                'comment' => $field->comment ?? '',
            ];
            if ($field->type === 'select' && is_array($field->options)) {
                $row['options'] = implode("\n", $field->options);
            } elseif ($field->type === 'categoria' && is_array($field->options)) {
                $lines = [];
                foreach ($field->options as $c) {
                    $name = $c['name'] ?? '';
                    $subs = $c['sub'] ?? [];
                    $lines[] = $name.(count($subs) ? ': '.implode(', ', $subs) : '');
                }
                $row['options'] = implode("\n", $lines);
            } elseif ($field->type === 'seccion' && is_array($field->options)) {
                $row['options_title'] = (string) ($field->options['title'] ?? '');
                $row['options_subsections'] = implode("\n", (array) ($field->options['subsections'] ?? []));
            } elseif ($field->type === 'delegado') {
                $row['options'] = '';
            } else {
                $row['options'] = is_array($field->options) ? implode(', ', $field->options) : '';
            }

            return $row;
        })->values()->all();

        return response()->json(['fields' => $fields]);
    }

    public function edit(int $module): View
    {
        $select = ['id', 'name', 'description', 'expires_at', 'applies_to_all', 'is_active'];
        if (Schema::hasColumn('temporary_modules', 'seed_discard_log')) {
            $select[] = 'seed_discard_log';
        }

        $temporaryModule = TemporaryModule::query()
            ->select($select)
            ->with(['fields', 'targetUsers:id'])
            ->findOrFail($module);
        $fieldUsage = $this->fieldService->countFieldDataUsage((int) $temporaryModule->id);

        return view('temporary_modules.admin.edit', [
            'pageTitle' => 'Editar modulo temporal',
            'pageDescription' => 'Actualiza vigencia y agrega nuevos campos requeridos.',
            'topbarNotifications' => [],
            'fieldTypes' => self::FIELD_TYPES,
            'delegates' => $this->accessService->delegates(),
            'temporaryModule' => $temporaryModule,
            'selectedDelegates' => $temporaryModule->targetUsers->pluck('id')->all(),
            'fieldUsage' => $fieldUsage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:today'],
            'is_indefinite' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'applies_to' => ['required', Rule::in(['all', 'selected'])],
            'delegate_ids' => ['nullable', 'array'],
            'delegate_ids.*' => ['integer', Rule::exists('users', 'id')],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.label' => ['required', 'string', 'max:120'],
            'fields.*.key' => ['nullable', 'string', 'max:120'],
            'fields.*.type' => ['required', Rule::in(array_keys(self::FIELD_TYPES))],
            'fields.*.required' => ['nullable', 'boolean'],
            'fields.*.options' => ['nullable', 'string', 'max:2000'],
            'fields.*.options_title' => ['nullable', 'string', 'max:255'],
            'fields.*.options_subsections' => ['nullable', 'string', 'max:2000'],
        ];

        if ($this->fieldService->supportsFieldComment()) {
            $rules['fields.*.comment'] = ['nullable', 'string', 'max:500'];
        }

        $validated = $request->validate($rules);

        if (! $this->isIndefiniteMode($validated) && empty($validated['expires_at'])) {
            throw ValidationException::withMessages([
                'expires_at' => 'Selecciona una fecha límite o activa la opción indefinido.',
            ]);
        }

        $selectedDelegateIds = collect($validated['delegate_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();
        if (($validated['applies_to'] ?? 'all') === 'selected' && empty($selectedDelegateIds)) {
            throw ValidationException::withMessages([
                'delegate_ids' => 'Selecciona al menos un delegado o cambia el alcance a todos.',
            ]);
        }

        $preparedFields = $this->fieldService->prepareFields($validated['fields']);

        DB::transaction(function () use ($request, $validated, $preparedFields, $selectedDelegateIds): void {
            $slug = Str::slug($validated['name']);
            $this->slugService->forcePurgeTrashedBySlug($slug);
            $baseSlug = $slug;
            $suffix = 2;
            while (TemporaryModule::query()->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$suffix;
                $suffix++;
                if ($suffix > 999) {
                    $slug = $baseSlug.'-'.substr(sha1(uniqid((string) mt_rand(), true)), 0, 8);
                    break;
                }
            }

            $module = TemporaryModule::query()->create([
                'name' => $validated['name'],
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'expires_at' => $this->resolveExpiresAt($validated),
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'applies_to_all' => ($validated['applies_to'] ?? 'all') === 'all',
                'created_by' => $request->user()->id,
            ]);

            $module->fields()->createMany($preparedFields);

            if (! $module->applies_to_all) {
                $module->targetUsers()->sync($selectedDelegateIds);
            }
        });

        return redirect()
            ->route('temporary-modules.admin.index')
            ->with('status', 'Módulo temporal creado correctamente.');
    }

    public function update(Request $request, int $module): RedirectResponse
    {
        $temporaryModule = TemporaryModule::query()->with('fields')->findOrFail($module);

        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:today'],
            'is_indefinite' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'applies_to' => ['required', Rule::in(['all', 'selected'])],
            'delegate_ids' => ['nullable', 'array'],
            'delegate_ids.*' => ['integer', Rule::exists('users', 'id')],
            'conflict_action' => ['nullable', Rule::in(['none', 'clear_module', 'clear_field_data', 'normalize_municipio', 'migrate_to_multiselect'])],
            'existing_fields' => ['nullable', 'array'],
            'existing_fields.*.id' => ['required_with:existing_fields', 'integer'],
            'existing_fields.*.label' => ['nullable', 'required_unless:existing_fields.*.delete,1', 'string', 'max:120'],
            'existing_fields.*.key' => ['nullable', 'string', 'max:120'],
            'existing_fields.*.type' => ['nullable', 'required_unless:existing_fields.*.delete,1', Rule::in(array_keys(self::FIELD_TYPES))],
            'existing_fields.*.required' => ['nullable', 'boolean'],
            'existing_fields.*.delete' => ['nullable', 'boolean'],
            'existing_fields.*.options' => ['nullable', 'string', 'max:2000'],
            'existing_fields.*.options_title' => ['nullable', 'string', 'max:255'],
            'existing_fields.*.options_subsections' => ['nullable', 'string', 'max:2000'],
            'existing_fields.*.subsection_index' => ['nullable', 'integer', 'min:0'],
            'extra_fields' => ['nullable', 'array'],
            'extra_fields.*.label' => ['required_with:extra_fields', 'string', 'max:120'],
            'extra_fields.*.key' => ['nullable', 'string', 'max:120'],
            'extra_fields.*.type' => ['required_with:extra_fields', Rule::in(array_keys(self::FIELD_TYPES))],
            'extra_fields.*.required' => ['nullable', 'boolean'],
            'extra_fields.*.options' => ['nullable', 'string', 'max:2000'],
            'extra_fields.*.options_title' => ['nullable', 'string', 'max:255'],
            'extra_fields.*.options_subsections' => ['nullable', 'string', 'max:2000'],
        ];

        if ($this->fieldService->supportsFieldComment()) {
            $rules['existing_fields.*.comment'] = ['nullable', 'string', 'max:500'];
            $rules['extra_fields.*.comment'] = ['nullable', 'string', 'max:500'];
        }

        $validated = $request->validate($rules);

        if (! $this->isIndefiniteMode($validated) && empty($validated['expires_at'])) {
            throw ValidationException::withMessages([
                'expires_at' => 'Selecciona una fecha límite o activa la opción indefinido.',
            ]);
        }

        $selectedDelegateIds = collect($validated['delegate_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();
        if (($validated['applies_to'] ?? 'all') === 'selected' && empty($selectedDelegateIds)) {
            throw ValidationException::withMessages([
                'delegate_ids' => 'Selecciona al menos un delegado o cambia el alcance a todos.',
            ]);
        }

        $currentFieldsById = $temporaryModule->fields->keyBy('id');
        $submittedExisting = collect($validated['existing_fields'] ?? [])
            ->mapWithKeys(function ($row) {
                $id = isset($row['id']) ? (int) $row['id'] : 0;

                return $id > 0 ? [$id => $row] : [];
            });

        $existingDefinitions = [];
        $deletedFieldIds = [];
        $destructiveKeys = [];
        $invalidMainImageKeys = [];
        $usedKeys = [];
        $nextSortOrder = 1;
        $fieldUsage = $this->fieldService->countFieldDataUsage((int) $temporaryModule->id);

        foreach ($temporaryModule->fields as $field) {
            $defaultOptions = $field->options;
            if (is_array($defaultOptions) && ($field->type === 'seccion')) {
                $defaultRow = [
                    'id' => (int) $field->id,
                    'label' => $field->label,
                    'comment' => $this->fieldService->supportsFieldComment() ? ($field->comment ?? null) : null,
                    'key' => $field->key,
                    'type' => $field->type,
                    'required' => $field->is_required,
                    'options_title' => $defaultOptions['title'] ?? '',
                    'options_subsections' => implode("\n", (array) ($defaultOptions['subsections'] ?? [])),
                    'options' => $defaultOptions,
                    'subsection_index' => $field->subsection_index,
                    'delete' => false,
                ];
            } elseif (is_array($defaultOptions) && ($field->type === 'categoria')) {
                $defaultRow = [
                    'id' => (int) $field->id,
                    'label' => $field->label,
                    'comment' => $this->fieldService->supportsFieldComment() ? ($field->comment ?? null) : null,
                    'key' => $field->key,
                    'type' => $field->type,
                    'required' => $field->is_required,
                    'options' => $defaultOptions,
                    'subsection_index' => $field->subsection_index,
                    'delete' => false,
                ];
            } else {
                $defaultRow = [
                    'id' => (int) $field->id,
                    'label' => $field->label,
                    'comment' => $this->fieldService->supportsFieldComment() ? ($field->comment ?? null) : null,
                    'key' => $field->key,
                    'type' => $field->type,
                    'required' => $field->is_required,
                    'options' => is_array($defaultOptions) ? implode(', ', $defaultOptions) : $defaultOptions,
                    'subsection_index' => $field->subsection_index,
                    'delete' => false,
                ];
            }
            $row = $submittedExisting->get((int) $field->id, $defaultRow);

            $isDeleted = (bool) ($row['delete'] ?? false);
            $oldKey = (string) $field->key;
            $oldType = $this->fieldService->canonicalFieldType((string) $field->type);
            $hasData = (($fieldUsage[$oldKey] ?? 0) > 0);

            if ($isDeleted) {
                $deletedFieldIds[] = (int) $field->id;
                $invalidMainImageKeys[] = $oldKey;
                if ($hasData) {
                    $destructiveKeys[] = $oldKey;
                }

                continue;
            }

            $normalized = $this->fieldService->normalizeFieldRow(
                $row,
                $nextSortOrder,
                $usedKeys,
                'existing_fields'
            );

            if ($hasData && ($normalized['key'] !== $oldKey || $this->fieldService->canonicalFieldType((string) $normalized['type']) !== $oldType)) {
                // select → multiselect is a safe migration, not a destructive change
                $isSelectToMultiselect = $oldType === 'select'
                    && $this->fieldService->canonicalFieldType((string) $normalized['type']) === 'multiselect'
                    && $normalized['key'] === $oldKey;
                // text → textarea: mismo valor en JSON (string); solo cambia límite de validación (255 → 5000)
                $isTextToTextarea = $oldType === 'text'
                    && $this->fieldService->canonicalFieldType((string) $normalized['type']) === 'textarea'
                    && $normalized['key'] === $oldKey;
                if (! $isSelectToMultiselect && ! $isTextToTextarea) {
                    $destructiveKeys[] = $oldKey;
                }
            }

            if ($normalized['key'] !== $oldKey) {
                $invalidMainImageKeys[] = $oldKey;
            }

            $existingDefinitions[] = [
                'id' => (int) $field->id,
                ...$normalized,
            ];
            $nextSortOrder++;
        }

        $extraFields = collect($validated['extra_fields'] ?? [])
            ->filter(fn ($row) => trim((string) ($row['label'] ?? '')) !== '')
            ->values()
            ->all();

        $preparedExtraFields = $this->fieldService->prepareFields($extraFields, $usedKeys, $nextSortOrder);

        $destructiveKeys = array_values(array_unique($destructiveKeys));
        $invalidMainImageKeys = array_values(array_unique($invalidMainImageKeys));
        $conflictAction = (string) ($validated['conflict_action'] ?? 'none');

        // --- Migrate select → multiselect (wrap existing strings in arrays) ---
        if ($conflictAction === 'migrate_to_multiselect') {
            foreach ($temporaryModule->fields as $field) {
                if ($this->fieldService->canonicalFieldType((string) $field->type) !== 'select') {
                    continue;
                }
                $fieldId = (int) $field->id;
                $submittedRow = $submittedExisting->get($fieldId);
                if (! $submittedRow) {
                    continue;
                }
                $newType = $this->fieldService->canonicalFieldType((string) ($submittedRow['type'] ?? $field->type));
                if ($newType !== 'multiselect') {
                    continue;
                }
                // Wrap each single-string value into a single-item array
                $fieldKey = (string) $field->key;
                $temporaryModule->entries()->chunkById(200, function ($entries) use ($fieldKey) {
                    foreach ($entries as $entry) {
                        $data = is_array($entry->data) ? $entry->data : [];
                        if (! array_key_exists($fieldKey, $data)) {
                            continue;
                        }
                        $val = $data[$fieldKey];
                        if (is_string($val) && $val !== '') {
                            $data[$fieldKey] = [$val];
                            $entry->update(['data' => $data]);
                        } elseif ($val === '') {
                            $data[$fieldKey] = null;
                            $entry->update(['data' => $data]);
                        }
                        // null stays null, existing arrays stay as-is
                    }
                });
            }
            $conflictAction = 'none';
        }

        if ($conflictAction === 'normalize_municipio') {
            $adminSeed = app(\App\Services\TemporaryModules\TemporaryModuleAdminSeedService::class);
            $adminSeed->normalizeMunicipioField($temporaryModule, 'municipio');
            // Después de normalizar, ese campo ya no debe contarse como “destructivo”
            $destructiveKeys = array_values(array_diff($destructiveKeys, ['municipio']));
            $conflictAction = 'none';
        }

        if (! empty($destructiveKeys)) {
            if (! in_array($conflictAction, ['clear_module', 'clear_field_data'], true)) {
                throw ValidationException::withMessages([
                    'conflict_action' => 'Hay datos capturados en los campos que deseas modificar/eliminar. Elige cómo resolver el conflicto antes de guardar.',
                ]);
            }

            if ($conflictAction === 'clear_module') {
                $this->entryDataService->clearModuleEntriesData($temporaryModule);
            } else {
                $imageLikeKeys = $temporaryModule->fields
                    ->whereIn('type', ['image', 'file'])
                    ->pluck('key')
                    ->intersect($destructiveKeys)
                    ->values()
                    ->all();

                $this->entryDataService->clearFieldDataFromEntries($temporaryModule, $destructiveKeys, $imageLikeKeys);
            }
        }

        DB::transaction(function () use ($temporaryModule, $validated, $selectedDelegateIds, $existingDefinitions, $deletedFieldIds, $preparedExtraFields, $invalidMainImageKeys): void {
            $slug = Str::slug($validated['name']);
            $this->slugService->forcePurgeTrashedBySlug($slug);
            $baseSlug = $slug;
            $suffix = 2;
            while (TemporaryModule::query()->where('slug', $slug)->where('id', '!=', $temporaryModule->id)->exists()) {
                $slug = $baseSlug.'-'.$suffix;
                $suffix++;
            }

            $temporaryModule->update([
                'name' => $validated['name'],
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'expires_at' => $this->resolveExpiresAt($validated),
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'applies_to_all' => ($validated['applies_to'] ?? 'all') === 'all',
            ]);

            if ($temporaryModule->applies_to_all) {
                $temporaryModule->targetUsers()->sync([]);
            } else {
                $temporaryModule->targetUsers()->sync($selectedDelegateIds);
            }

            foreach ($existingDefinitions as $definition) {
                $field = $temporaryModule->fields()->find($definition['id']);
                if (! $field) {
                    continue;
                }

                $field->update([
                    'label' => $definition['label'],
                    ...($this->fieldService->supportsFieldComment() ? ['comment' => $definition['comment']] : []),
                    'key' => $definition['key'],
                    'type' => $definition['type'],
                    'is_required' => $definition['is_required'],
                    'options' => $definition['options'],
                    'sort_order' => $definition['sort_order'],
                ]);
            }

            if (! empty($deletedFieldIds)) {
                $temporaryModule->fields()->whereIn('id', $deletedFieldIds)->delete();
            }

            if (! empty($preparedExtraFields)) {
                $temporaryModule->fields()->createMany($preparedExtraFields);
            }

            if (! empty($invalidMainImageKeys)) {
                $temporaryModule->entries()
                    ->whereIn('main_image_field_key', $invalidMainImageKeys)
                    ->update(['main_image_field_key' => null]);
            }
        });

        return redirect()
            ->route('temporary-modules.admin.index')
            ->with('status', 'Módulo temporal actualizado correctamente.');
    }

    private function isIndefiniteMode(array $validated): bool
    {
        return (bool) ($validated['is_indefinite'] ?? false);
    }

    private function resolveExpiresAt(array $validated): ?Carbon
    {
        if ($this->isIndefiniteMode($validated)) {
            return null;
        }

        $date = $validated['expires_at'] ?? null;
        if (empty($date)) {
            return null;
        }

        return Carbon::parse($date)->endOfDay();
    }

    /**
     * Filtros del delegado en listados de registros (fragmento AJAX y vista índice).
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    private function applyDelegateMyRecordsFilters($query, Request $request, array $allowedMicrorregionIds): void
    {
        if ($request->filled('microrregion_id')) {
            $mrId = (int) $request->query('microrregion_id');
            if ($mrId > 0 && $allowedMicrorregionIds !== [] && in_array($mrId, $allowedMicrorregionIds, true)) {
                $query->where('microrregion_id', $mrId);
            }
        }

        $buscar = trim((string) $request->query('buscar', ''));
        if ($buscar === '') {
            return;
        }

        $like = '%'.addcslashes($buscar, '%_\\').'%';
        $driver = $query->getConnection()->getDriverName();

        match ($driver) {
            'sqlite' => $query->where('data', 'like', $like),
            'pgsql' => $query->whereRaw('CAST(data AS TEXT) ILIKE ?', [$like]),
            default => $query->whereRaw('LOWER(CAST(data AS CHAR(10000))) LIKE LOWER(?)', [$like]),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function delegateRecordsAppends(Request $request, int $moduleId): array
    {
        $appends = ['module' => $moduleId];
        $buscar = trim((string) $request->query('buscar', ''));
        if ($buscar !== '') {
            $appends['buscar'] = $buscar;
        }
        if ($request->filled('microrregion_id')) {
            $appends['microrregion_id'] = (int) $request->query('microrregion_id');
        }

        return $appends;
    }

    public function delegateIndex(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (Gate::forUser($user)->allows('modulos-temporales-admin-ver')) {
            return $this->adminIndex($request);
        }

        [, $municipios] = $this->accessService->delegadoMunicipios((int) $user->id);
        $microrregionesAsignadas = $this->accessService->microrregionesConMunicipiosPorUsuario((int) $user->id);
        $microrregionIdsUsuario = $this->accessService->microrregionIdsPorUsuario((int) $user->id);

        $microrregionesMunicipios = $microrregionesAsignadas->pluck('municipios', 'id')
            ->map(fn ($m) => collect($m)->pluck('municipio')->toArray())
            ->toArray();

        $allModulesQuery = TemporaryModule::query()
            ->select(['id', 'name', 'description', 'expires_at', 'is_active', 'applies_to_all'])
            ->available()
            ->where(function ($query) use ($user) {
                $query->where('applies_to_all', true)
                    ->orWhereHas('targetUsers', fn ($targetQuery) => $targetQuery->where('users.id', $user->id));
            })
            ->with([
                'fields' => fn ($q) => $q->select('id', 'temporary_module_id', 'label', 'key', 'type', 'options', 'is_required', 'comment')->orderBy('sort_order'),
            ])
            ->withCount([
                'entries as my_entries_count' => fn ($query) => $query->when(
                    $microrregionIdsUsuario !== [],
                    fn ($q) => $q->whereIn('microrregion_id', $microrregionIdsUsuario),
                    fn ($q) => $q->whereRaw('1 = 0')
                ),
            ])
            ->latest();

        $searchVal = trim((string) $request->query('buscar_modulo', ''));
        if ($searchVal !== '') {
            $allModulesQuery->where(function ($q) use ($searchVal) {
                $q->where('name', 'like', "%{$searchVal}%")
                    ->orWhere('description', 'like', "%{$searchVal}%");
            });
        }

        $allModules = $allModulesQuery->get();
        $modules = $allModules;

        $requestedModuleId = $request->filled('module') ? (int) $request->query('module') : null;
        $activeModuleId = ($requestedModuleId !== null && $allModules->contains('id', $requestedModuleId))
            ? $requestedModuleId
            : (int) ($allModules->first()?->id ?? 0);

        $activeModule = $allModules->firstWhere('id', $activeModuleId);
        $fields = $activeModule ? $activeModule->fields : collect();

        $activeSection = optional($request->route())->getName() === 'temporary-modules.records' ? 'records' : 'upload';

        $delegateScriptPayload = [
            'csrfToken' => csrf_token(),
            'csrfRefreshUrl' => route('csrf.refresh'),
            'loginUrl' => route('login'),
            'microrregionesMunicipios' => $microrregionesAsignadas->mapWithKeys(function ($micro) {
                return [(string) $micro->id => array_values($micro->municipios ?? [])];
            })->all(),
            'microrregionesMeta' => $microrregionesAsignadas->map(function ($m) {
                return [
                    'id' => (int) $m->id,
                    'label' => 'MR '.$m->microrregion.' — '.$m->cabecera,
                ];
            })->values()->all(),
            'semaforoLabels' => TemporaryModuleFieldService::semaforoLabels(),
        ];

        return view('temporary_modules.delegate.index', [
            'pageTitle' => 'Capturas temporales',
            'pageDescription' => 'Registra informacion solicitada para tus municipios en modulos activos.',
            'topbarNotifications' => [],
            'modules' => $modules,
            'allModules' => $allModules,
            'fields' => $fields,
            'municipios' => $municipios,
            'microrregionesAsignadas' => $microrregionesAsignadas,
            'microrregionesMunicipios' => $microrregionesMunicipios,
            'activeSection' => $activeSection,
            'activeModuleId' => $activeModuleId,
            'fragmentUploadUrl' => route('temporary-modules.fragment.upload'),
            'fragmentRecordsUrl' => route('temporary-modules.fragment.records'),
            'delegateScriptPayload' => $delegateScriptPayload,
            'delegados' => $this->accessService->onlyDelegates(),
        ]);
    }

    /** Devuelve el conteo de registros del usuario para un módulo (AJAX). */
    public function moduleStatus(Request $request, int $module): JsonResponse
    {
        $user = $request->user();
        abort_if($user->can('Modulos-Temporales-Admin'), 404);

        $temporaryModule = TemporaryModule::query()
            ->select(['id', 'is_active', 'applies_to_all', 'expires_at'])
            ->findOrFail($module);

        abort_unless($temporaryModule->isAvailable(), 404);
        abort_unless($this->accessService->userCanAccessModule($temporaryModule, (int) $user->id), 403);

        $microrregionIdsUsuario = $this->accessService->microrregionIdsPorUsuario((int) $user->id);

        $count = $temporaryModule->entries()
            ->when(
                $microrregionIdsUsuario !== [],
                fn ($q) => $q->whereIn('microrregion_id', $microrregionIdsUsuario),
                fn ($q) => $q->whereRaw('1 = 0')
            )
            ->count();

        return response()->json(['my_entries_count' => $count]);
    }

    /** HTML parcial: grid de módulos + paginación (AJAX). */
    public function delegatePartialUpload(Request $request): View
    {
        $user = $request->user();
        abort_if($user->can('Modulos-Temporales-Admin'), 404);
        $microrregionIdsUsuario = $this->accessService->microrregionIdsPorUsuario((int) $user->id);
        $modules = TemporaryModule::query()
            ->select(['id', 'name', 'description', 'expires_at', 'is_active', 'applies_to_all'])
            ->available()
            ->where(function ($query) use ($user) {
                $query->where('applies_to_all', true)
                    ->orWhereHas('targetUsers', fn ($targetQuery) => $targetQuery->where('users.id', $user->id));
            })
            ->with([
                'fields' => fn ($q) => $q->select('id', 'temporary_module_id', 'label', 'key', 'type', 'options', 'is_required', 'comment')->orderBy('sort_order'),
            ])
            ->withCount([
                'entries as my_entries_count' => fn ($query) => $query->when(
                    $microrregionIdsUsuario !== [],
                    fn ($q) => $q->whereIn('microrregion_id', $microrregionIdsUsuario),
                    fn ($q) => $q->whereRaw('1 = 0')
                ),
            ])
            ->latest()
            ->paginate(6)
            ->withQueryString();

        return view('temporary_modules.delegate.partials.upload_modules', [
            'modules' => $modules,
            'fragmentUploadUrl' => route('temporary-modules.fragment.upload'),
        ]);
    }

    /** HTML parcial: tabla/cards de registros + paginación (AJAX). */
    public function delegatePartialRecords(Request $request): View
    {
        $user = $request->user();
        abort_if($user->can('Modulos-Temporales-Admin'), 404);
        $moduleId = (int) $request->query('module', 0);
        abort_unless($moduleId > 0, 404);
        $temporaryModule = TemporaryModule::query()->with('fields')->findOrFail($moduleId);
        abort_unless($temporaryModule->isAvailable(), 404);
        abort_unless($this->accessService->userCanAccessModule($temporaryModule, (int) $user->id), 403);
        $microrregionIdsUsuario = $this->accessService->microrregionIdsPorUsuario((int) $user->id);
        $myEntries = $temporaryModule->entries()
            ->when(
                $microrregionIdsUsuario !== [],
                fn ($q) => $q->whereIn('microrregion_id', $microrregionIdsUsuario),
                fn ($q) => $q->whereRaw('1 = 0')
            )
            ->tap(fn ($q) => $this->applyDelegateMyRecordsFilters($q, $request, $microrregionIdsUsuario))
            ->with('microrregion:id,microrregion,cabecera')
            ->select(['id', 'temporary_module_id', 'user_id', 'microrregion_id', 'data', 'submitted_at'])
            ->latest('submitted_at')
            ->paginate(10, ['*'], 'entries_page')
            ->appends($this->delegateRecordsAppends($request, $moduleId))
            ->withQueryString();
        $municipioField = $temporaryModule->fields->firstWhere('type', 'municipio');

        $microrregionesAsignadas = $this->accessService->microrregionesConMunicipiosPorUsuario((int) $user->id);
        $tmImportable = $temporaryModule->fields->filter(fn ($f) => in_array($f->type, \App\Services\TemporaryModules\TemporaryModuleExcelImportService::IMPORTABLE_TYPES, true));

        return view('temporary_modules.delegate.partials.records_entries', [
            'module' => $temporaryModule,
            'entries' => $myEntries,
            'municipioField' => $municipioField,
            'fragmentRecordsUrl' => route('temporary-modules.fragment.records'),
            'microrregionesAsignadas' => $microrregionesAsignadas,
            'tmImportable' => $tmImportable,
        ]);
    }

    /**
     * JSON para edición múltiple (mismos filtros que el fragmento de registros, máx. 500 filas).
     */
    public function delegateBulkEditData(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null || $user->can('Modulos-Temporales-Admin'), 404);
        $moduleId = (int) $request->query('module', 0);
        abort_unless($moduleId > 0, 404);
        $temporaryModule = TemporaryModule::query()->with('fields')->findOrFail($moduleId);
        abort_unless($temporaryModule->isAvailable(), 404);
        abort_unless($this->accessService->userCanAccessModule($temporaryModule, (int) $user->id), 403);

        $microrregionIdsUsuario = $this->accessService->microrregionIdsPorUsuario((int) $user->id);
        $municipioField = $temporaryModule->fields->firstWhere('type', 'municipio');

        $entries = $temporaryModule->entries()
            ->when(
                $microrregionIdsUsuario !== [],
                fn ($q) => $q->whereIn('microrregion_id', $microrregionIdsUsuario),
                fn ($q) => $q->whereRaw('1 = 0')
            )
            ->tap(fn ($q) => $this->applyDelegateMyRecordsFilters($q, $request, $microrregionIdsUsuario))
            ->with('microrregion:id,microrregion,cabecera')
            ->select(['id', 'temporary_module_id', 'user_id', 'microrregion_id', 'data', 'submitted_at'])
            ->latest('submitted_at')
            ->limit(500)
            ->get();

        $delegadosList = $this->accessService->onlyDelegates()->map(function ($del) {
            $label = $del->name . (!empty($del->microrregion) ? ' (MR ' . str_pad((string) $del->microrregion, 2, '0', STR_PAD_LEFT) . ')' : '');
            return ['value' => $del->name, 'label' => $label];
        })->values()->all();

        $fieldsPayload = $temporaryModule->fields->filter(fn ($f) => $f->type !== 'seccion')->values()->map(function ($f) use ($delegadosList) {
            return [
                'key' => $f->key,
                'label' => $f->label,
                'type' => $f->type,
                'options' => $f->type === 'delegado' ? $delegadosList : $f->options,
                'is_required' => (bool) $f->is_required,
                'comment' => $f->comment,
            ];
        });

        $entriesPayload = $entries->values()->map(function ($e, $idx) use ($municipioField) {
            $data = (array) ($e->data ?? []);
            $municipioValue = $municipioField ? ($data[$municipioField->key] ?? null) : null;
            $title = (is_string($municipioValue) && trim($municipioValue) !== '')
                ? $municipioValue
                : 'Registro '.($idx + 1);

            return [
                'id' => (int) $e->id,
                'microrregion_id' => (int) $e->microrregion_id,
                'microrregion_label' => $e->microrregion ? ('MR '.$e->microrregion->microrregion) : '?',
                'data' => $data,
                'title' => $title,
            ];
        });

        return response()->json([
            'success' => true,
            'module_id' => (int) $temporaryModule->id,
            'module_name' => $temporaryModule->name,
            'fields' => $fieldsPayload,
            'entries' => $entriesPayload,
        ]);
    }

    public function show(Request $request, int $module): View
    {
        $temporaryModule = TemporaryModule::query()
            ->with('fields')
            ->findOrFail($module);

        abort_unless($temporaryModule->isAvailable(), 404);
        abort_unless($this->accessService->userCanAccessModule($temporaryModule, (int) $request->user()->id), 403);

        $requestedMicrorregionId = $request->filled('microrregion_id')
            ? (int) $request->input('microrregion_id')
            : null;

        [$microrregionId, $municipios] = $this->accessService->delegadoMunicipios($request->user()->id, $requestedMicrorregionId);
        $microrregionesAsignadas = $this->accessService->microrregionesConMunicipiosPorUsuario((int) $request->user()->id);

        $microrregionIdsUsuario = $this->accessService->microrregionIdsPorUsuario((int) $request->user()->id);
        $entries = $temporaryModule->entries()
            ->when(
                $microrregionIdsUsuario !== [],
                fn ($q) => $q->whereIn('microrregion_id', $microrregionIdsUsuario),
                fn ($q) => $q->whereRaw('1 = 0')
            )
            ->latest('submitted_at')
            ->take(200)
            ->get();

        $editingEntry = null;
        $editId = (int) $request->query('entry', 0);
        if ($editId > 0) {
            $editingEntry = $temporaryModule->entries()
                ->whereKey($editId)
                ->first();
            if ($editingEntry && ! $this->accessService->userCanAccessEntryByMicrorregion((int) $request->user()->id, (int) $editingEntry->microrregion_id)) {
                $editingEntry = null;
            }
        }

        return view('temporary_modules.delegate.show', [
            'pageTitle' => $temporaryModule->name,
            'pageDescription' => 'Captura de informacion para este modulo temporal.',
            'topbarNotifications' => [],
            'temporaryModule' => $temporaryModule,
            'fields' => $temporaryModule->fields,
            'municipios' => $municipios,
            'microrregionId' => $editingEntry ? (int) $editingEntry->microrregion_id : $microrregionId,
            'microrregionesAsignadas' => $microrregionesAsignadas,
            'entries' => $entries,
            'fieldTypes' => self::FIELD_TYPES,
            'editingEntry' => $editingEntry,
            'delegados' => $this->accessService->onlyDelegates(),
        ]);
    }

    public function submit(Request $request, int $module)
    {
        $temporaryModule = TemporaryModule::query()->with('fields')->findOrFail($module);
        abort_unless($temporaryModule->isAvailable(), 404);
        abort_unless($this->accessService->userCanAccessModule($temporaryModule, (int) $request->user()->id), 403);

        $requestedMicrorregionId = $request->filled('selected_microrregion_id')
            ? (int) $request->input('selected_microrregion_id')
            : null;

        $microrregionIdsPermitidos = $this->accessService->microrregionIdsPorUsuario((int) $request->user()->id);
        if ($requestedMicrorregionId !== null && ! in_array($requestedMicrorregionId, $microrregionIdsPermitidos, true)) {
            throw ValidationException::withMessages([
                'selected_microrregion_id' => 'La microrregión seleccionada no está dentro de tus asignaciones.',
            ]);
        }

        [$microrregionId, $municipios] = $this->accessService->delegadoMunicipios($request->user()->id, $requestedMicrorregionId);

        $entryId = (int) ($request->input('entry_id') ?? 0);
        $existingEntry = null;
        if ($entryId > 0) {
            $existingEntry = TemporaryModuleEntry::query()
                ->where('id', $entryId)
                ->where('temporary_module_id', $temporaryModule->id)
                ->first();
            abort_unless(
                $existingEntry !== null
                && $this->accessService->userCanAccessEntryByMicrorregion(
                    (int) $request->user()->id,
                    (int) $existingEntry->microrregion_id
                ),
                403
            );
        }

        $rules = [];
        $attributes = [];

        foreach ($temporaryModule->fields as $field) {
            if ($field->type === 'seccion') {
                continue;
            }

            $key = 'values.'.$field->key;
            $attributes[$key] = $field->label;

            if (in_array($field->type, ['file', 'image'], true)) {
                $existingValue = $existingEntry?->data[$field->key] ?? null;
                $hasExistingImage = (is_string($existingValue) && trim((string)$existingValue) !== '') || (is_array($existingValue) && count($existingValue) > 0);
                $removeRequested = filter_var($request->input('remove_images.'.$field->key), FILTER_VALIDATE_BOOLEAN);
                $isRequiredNow = (bool) $field->is_required && (! $hasExistingImage || $removeRequested);

                $rules[$key] = $isRequiredNow ? ['required'] : ['nullable'];
                if ($request->hasFile($key)) {
                    $rules[$key][] = 'array';
                    $rules[$key][] = 'max:2';
                    $rules[$key.'.*'] = ['file', $field->type === 'image' ? 'image' : 'nullable', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,txt,csv', 'max:20480'];
                }
                $rules['remove_images.'.$field->key] = ['nullable', 'boolean'];
            } elseif ($field->type === 'multiselect') {
                $opts = is_array($field->options) ? $field->options : [];
                $rules[$key] = $field->is_required ? ['required', 'array'] : ['nullable', 'array'];
                $rules[$key.'.*'] = ['string', 'max:255', Rule::in($opts)];
            } elseif ($field->type === 'linked') {
                $opts = is_array($field->options) ? $field->options : [];
                $primaryType = (string) ($opts['primary_type'] ?? 'text');
                $secondaryType = (string) ($opts['secondary_type'] ?? 'text');
                $primaryOpts = (array) ($opts['primary_options'] ?? []);
                $secondaryOpts = (array) ($opts['secondary_options'] ?? []);
                $secondaryRequired = (bool) ($opts['secondary_required'] ?? true);
                // Primary field: use the outer is_required on the primary
                $rules['values.'.$field->key.'__primary'] = $this->fieldService->rulesForField($primaryType, (bool) $field->is_required, $primaryOpts, $municipios);
                // Secondary: only required when primary has a value (validated later in value processing)
                $rules['values.'.$field->key.'__secondary'] = $this->fieldService->rulesForField($secondaryType, false, $secondaryOpts, $municipios);
                unset($rules[$key]); // remove the top-level rule, sub-rules replace it
            } else {
                $rules[$key] = $this->fieldService->rulesForField($field->type, (bool) $field->is_required, $field->options ?? [], $municipios);
            }
        }

        $rules['entry_id'] = ['nullable', 'integer'];
        $rules['selected_microrregion_id'] = ['nullable', 'integer'];

        $validated = $request->validate($rules, [], $attributes);
        $values = Arr::get($validated, 'values', []);

        $seccionKeys = $temporaryModule->fields->where('type', 'seccion')->pluck('key')->all();
        foreach ($seccionKeys as $sk) {
            Arr::forget($values, $sk);
        }

        foreach ($temporaryModule->fields as $field) {
            if ($field->type === 'seccion') {
                continue;
            }

            $value = $values[$field->key] ?? null;
            $existingValue = $existingEntry?->data[$field->key] ?? null;
            $removeRequested = filter_var(Arr::get($validated, 'remove_images.'.$field->key), FILTER_VALIDATE_BOOLEAN);

            if (in_array($field->type, ['file', 'image'], true)) {
                $uploadedFiles = $request->file('values.'.$field->key);
                if ($uploadedFiles) {
                    if (!is_array($uploadedFiles)) { $uploadedFiles = [$uploadedFiles]; }
                    $directory = 'temporary-modules/'.$temporaryModule->id.'/'.$request->user()->id;
                    $storedPaths = [];
                    foreach ($uploadedFiles as $uploadedFile) {
                        if ($field->type === 'image' && in_array($uploadedFile->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'])) {
                            $storedPaths[] = $this->compressAndStoreImage($uploadedFile, $directory);
                        } else { $storedPaths[] = $uploadedFile->store($directory, 'secure_shared'); }
                    }
                    // Limpieza opcional de previos no incluidos (reemplace total)
                    $oldPaths = is_array($existingValue) ? $existingValue : ($existingValue ? [(string)$existingValue] : []);
                    foreach ($oldPaths as $oldPath) {
                        if (!in_array($oldPath, $storedPaths, true)) { $this->entryDataService->deleteStoredPath($oldPath); }
                    }
                    $value = $storedPaths;
                } elseif ($removeRequested) {
                    $oldPaths = is_array($existingValue) ? $existingValue : ($existingValue ? [(string)$existingValue] : []);
                    foreach ($oldPaths as $oldPath) { $this->entryDataService->deleteStoredPath($oldPath); }
                    $value = null;
                } else {
                    $value = $existingValue;
                }
            }

            if ($field->type === 'boolean') {
                if ($value === null || (is_string($value) && trim($value) === '')) {
                    $value = null;
                } else {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                }
            }

            if ($field->type === 'multiselect') {
                $value = is_array($value) ? array_values(array_filter(array_map('trim', $value))) : [];
                if (empty($value) && $field->is_required) {
                    $value = null; // let required validation catch it
                }
                if (empty($value)) {
                    $value = null;
                }
            }

            if ($field->type === 'linked') {
                $opts = is_array($field->options) ? $field->options : [];
                $primaryKey = $field->key.'__primary';
                $secondaryKey = $field->key.'__secondary';
                $primaryRaw = $values[$primaryKey] ?? null;
                $secondaryRaw = $values[$secondaryKey] ?? null;
                // Remove helper keys from storage
                unset($values[$primaryKey], $values[$secondaryKey]);
                $primaryLabel = (string) ($opts['primary_label'] ?? 'Principal');
                $primaryEmpty = $primaryRaw === null || $primaryRaw === '' || (is_array($primaryRaw) && empty($primaryRaw));
                $secondaryLabel = (string) ($opts['secondary_label'] ?? 'Secundario');
                $value = [
                    'primary' => $primaryEmpty ? null : $primaryRaw,
                    'secondary' => $primaryEmpty ? ('Sin '.$primaryLabel) : $secondaryRaw,
                ];
            }

            $values[$field->key] = $value;
        }

        $duplicateEntryId = $this->findDuplicateEntryId(
            $temporaryModule,
            (int) $microrregionId,
            $values,
            $existingEntry ? (int) $existingEntry->id : null
        );
        if ($duplicateEntryId !== null) {
            throw ValidationException::withMessages([
                'values' => 'No se guardó el registro: ya existe uno idéntico en este módulo para la misma microrregión.',
            ]);
        }

        if ($existingEntry) {
            $existingEntry->update([
                'microrregion_id' => $microrregionId,
                'data' => $values,
                'submitted_at' => Carbon::now(),
            ]);
        } else {
            $temporaryModule->entries()->create([
                'user_id' => $request->user()->id,
                'microrregion_id' => $microrregionId,
                'data' => $values,
                'main_image_field_key' => null,
                'submitted_at' => Carbon::now(),
            ]);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Registro guardado correctamente.',
            ]);
        }

        return redirect()
            ->route('temporary-modules.records', ['module' => $temporaryModule->id])
            ->with('status', 'Registro guardado correctamente.');
    }

    /** El delegado elimina uno de sus registros (solo si tiene acceso por microrregión). */
    public function destroyEntry(Request $request, int $module, int $entry): RedirectResponse
    {
        $temporaryModule = TemporaryModule::query()->with('fields')->findOrFail($module);
        abort_unless($temporaryModule->isAvailable(), 404);
        abort_unless($this->accessService->userCanAccessModule($temporaryModule, (int) $request->user()->id), 403);

        $entryModel = TemporaryModuleEntry::query()
            ->where('id', $entry)
            ->where('temporary_module_id', $temporaryModule->id)
            ->firstOrFail();

        abort_unless(
            $this->accessService->userCanAccessEntryByMicrorregion(
                (int) $request->user()->id,
                (int) $entryModel->microrregion_id
            ),
            403
        );

        $this->entryDataService->deleteEntryAndFiles($entryModel, $temporaryModule);

        return redirect()
            ->route('temporary-modules.records', ['module' => $temporaryModule->id])
            ->with('status', 'Registro eliminado correctamente.');
    }

    /** El delegado elimina varios de sus registros de forma masiva. */
    public function bulkDestroyEntries(Request $request, int $module): JsonResponse
    {
        $temporaryModule = TemporaryModule::query()->with('fields')->findOrFail($module);
        abort_unless($temporaryModule->isAvailable(), 403, 'Módulo no disponible o fuera de vigencia.');
        abort_unless($this->accessService->userCanAccessModule($temporaryModule, (int) $request->user()->id), 403);

        $request->validate([
            'entry_ids' => ['required', 'array'],
            'entry_ids.*' => ['integer'],
        ]);

        $entryIds = (array) $request->input('entry_ids');
        $microrregionIdsPermitidos = (array) $this->accessService->microrregionIdsPorUsuario((int) $request->user()->id);

        $entries = TemporaryModuleEntry::whereIn('id', $entryIds)
            ->where('temporary_module_id', (int) $temporaryModule->id)
            ->whereIn('microrregion_id', $microrregionIdsPermitidos)
            ->get();

        $count = 0;
        foreach ($entries as $entry) {
            $this->entryDataService->deleteEntryAndFiles($entry, $temporaryModule);
            $count++;
        }

        return response()->json([
            'success' => true,
            'message' => "Se eliminaron {$count} registros correctamente.",
            'count' => $count,
        ]);
    }

    /**
     * Paso 1: sube Excel y devuelve columnas detectadas + sugerencia de mapeo a campos del módulo.
     */
    public function importExcelPreview(Request $request, int $module): JsonResponse
    {
        $temporaryModule = TemporaryModule::query()->with('fields')->findOrFail($module);
        abort_unless($temporaryModule->isAvailable(), 404);
        abort_unless($this->accessService->userCanAccessModule($temporaryModule, (int) $request->user()->id), 403);

        $request->validate([
            'archivo_excel' => ['required', 'file', 'mimes:xlsx,xls,pdf', 'max:153600'],
            'header_row' => ['nullable', 'integer', 'min:1', 'max:200'],
            'auto_detect' => ['nullable', 'boolean'],
            'sheet_index' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $file = $request->file('archivo_excel');
        $isPdf = strtolower($file->getClientOriginalExtension()) === 'pdf';
        $headerRow = (int) ($request->input('header_row') ?: 1);
        $sheetIndex = (int) ($request->input('sheet_index') ?: 0);
        $detected = null;
        if ($request->boolean('auto_detect', true) && ! $isPdf) {
            try {
                $detected = $this->adminSeedService->detectTableLayout($file, 80, $sheetIndex);
                if ($detected !== null) {
                    $headerRow = $detected['header_row'];
                }
            } catch (\Throwable) {
            }
        }

        $importable = $this->excelImportService->importableFields($temporaryModule->fields);
        $hasMediaImportFields = $importable->contains(fn ($f) => in_array((string) $f->type, ['image', 'file'], true));
        if ($importable->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Este módulo no tiene campos importables desde Excel (texto, número, fecha, lista, municipio, etc.).',
            ], 422);
        }

        try {
            if ($isPdf) {
                // Subir memoria/tiempo para PDFs pesados
                $sizeMb = $file->getSize() / 1_048_576;
                if ($sizeMb > 20) {
                    @ini_set('memory_limit', $sizeMb > 80 ? '1G' : '512M');
                    @set_time_limit($sizeMb > 80 ? 300 : 180);
                }
                $preview = $this->pdfImportService->preview($temporaryModule, $file->getRealPath(), $headerRow);
            } else {
                // Lectura ligera: sin dibujos (SheetJS ya muestra la tabla en el cliente)
                $preview = $this->excelImportService->preview($file, $headerRow, false, $sheetIndex);
            }

            if (!$preview['success']) {
                return response()->json($preview, 422);
            }

            $preview['suggested_map'] = $this->excelImportService->suggestMap($importable, $preview['headers'] ?? []);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo leer el archivo: '.$e->getMessage(),
            ], 422);
        }

        $out = [
            'success' => true,
            'header_row' => $headerRow,
            'data_start_row' => ($detected['data_start_row'] ?? null) ?: ($headerRow + 1),
            'headers' => $preview['headers'] ?? [],
            'suggested_map' => $preview['suggested_map'] ?? [],
            'preview_thumbnails' => $preview['preview_thumbnails'] ?? [],
            'fields' => $importable->map(fn ($f) => [
                'key' => $f->key,
                'label' => $f->label,
                'type' => $f->type,
                'is_required' => (bool) $f->is_required,
            ])->values()->all(),
        ];
        if (!empty($preview['is_pdf'])) {
            $out['is_pdf'] = true;
        }
        $pdfThumbs = is_array($preview['preview_thumbnails'] ?? null) ? $preview['preview_thumbnails'] : [];
        if ($isPdf && $hasMediaImportFields && count($pdfThumbs) === 0) {
            $out['warnings'] = [
                'No se detectaron imágenes embebidas por fila en este PDF. Si las fotos están como enlace/texto o fuera de la tabla, no se podrán importar automáticamente.',
            ];
        }
        if (!empty($preview['preview_rows'])) {
            $out['preview_rows'] = $preview['preview_rows'];
        }
        if ($detected) {
            $out['detection_note'] = $detected['note'];
        }

        return response()->json($out);
    }

    /**
     * Paso 2: importa filas según mapeo campo → índice de columna (0 = A).
     */
    public function importExcel(Request $request, int $module): JsonResponse
    {
        $temporaryModule = TemporaryModule::query()->with('fields')->findOrFail($module);
        abort_unless($temporaryModule->isAvailable(), 404);
        abort_unless($this->accessService->userCanAccessModule($temporaryModule, (int) $request->user()->id), 403);

        $request->validate([
            'archivo_excel' => ['required', 'file', 'mimes:xlsx,xls,pdf', 'max:153600'],
            'header_row' => ['nullable', 'integer', 'min:1', 'max:50'],
            'data_start_row' => ['nullable', 'integer', 'min:2', 'max:1000'],
            'mapping' => ['required', 'string'],
            'selected_microrregion_id' => ['nullable', 'integer'],
            'selected_municipio' => ['nullable', 'string', 'max:255'],
            'auto_identify_municipio' => ['nullable', 'boolean'],
            'all_microrregions' => ['nullable', 'boolean'],
            'sheet_index' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $mapping = json_decode((string) $request->input('mapping'), true);
        if (! is_array($mapping)) {
            return response()->json(['success' => false, 'message' => 'Mapeo inválido.'], 422);
        }
        $normalizedMap = [];
        foreach ($mapping as $key => $idx) {
            if ($idx === '' || $idx === null) {
                $normalizedMap[(string) $key] = null;
            } elseif (is_numeric($idx)) {
                $normalizedMap[(string) $key] = (int) $idx;
            }
        }

        $allMicrorregions = $request->boolean('all_microrregions', false);
        $municipioToMrMap = [];
        $allowedMunicipioNames = [];
        $suggestionMunicipioNames = [];
        $microrregionId = null;

        $microrregionesInfo = $this->accessService->microrregionesConMunicipiosPorUsuario((int) $request->user()->id);
        foreach ($microrregionesInfo as $micro) {
            foreach ($micro->municipios as $m) {
                $suggestionMunicipioNames[] = $m;
                $municipioToMrMap[$this->excelImportService->normalizeLabel($m)] = (int) $micro->id;
            }
        }
        $suggestionMunicipioNames = array_values(array_unique($suggestionMunicipioNames));

        if ($allMicrorregions) {
            $allowedMunicipioNames = $suggestionMunicipioNames;
        } else {
            $requestedMicrorregionId = $request->filled('selected_microrregion_id')
                ? (int) $request->input('selected_microrregion_id')
                : null;
            $microrregionIdsPermitidos = $this->accessService->microrregionIdsPorUsuario((int) $request->user()->id);
            if ($requestedMicrorregionId !== null && ! in_array($requestedMicrorregionId, $microrregionIdsPermitidos, true)) {
                return response()->json(['success' => false, 'message' => 'Microrregión no permitida.'], 403);
            }

            [$microrregionId, $municipios] = $this->accessService->delegadoMunicipios($request->user()->id, $requestedMicrorregionId);
            $allowedMunicipioNames = array_values($municipios);
        }

        $selectedMunicipio = trim((string) $request->input('selected_municipio', ''));
        if ($selectedMunicipio !== '') {
            $allowedNorm = array_map(fn ($m) => $this->excelImportService->normalizeLabel((string) $m), $allowedMunicipioNames);
            if (! in_array($this->excelImportService->normalizeLabel($selectedMunicipio), $allowedNorm, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El municipio seleccionado no pertenece al alcance permitido para esta importación.',
                ], 422);
            }
        }

        $headerRow = (int) ($request->input('header_row') ?: 1);
        $dataStartRow = (int) ($request->input('data_start_row') ?: $headerRow + 1);
        $sheetIndex = (int) ($request->input('sheet_index') ?: 0);
        $file = $request->file('archivo_excel');
        $isPdf = $file->getClientOriginalExtension() === 'pdf';

        try {
            $options = [
                'mapping' => $normalizedMap,
                'header_row' => $headerRow,
                'data_start_row' => $dataStartRow,
                'selected_microrregion_id' => $microrregionId,
                'selected_municipio' => $selectedMunicipio !== '' ? $selectedMunicipio : null,
                'auto_identify_municipio' => $request->boolean('auto_identify_municipio', true),
                'all_microrregions' => $allMicrorregions,
                'sheet_index' => $sheetIndex,
                'file_path' => $file->getRealPath(),
            ];

            if ($isPdf) {
                $result = $this->pdfImportService->import($temporaryModule, $options);
            } else {
                $result = $this->excelImportService->importRows($temporaryModule, $file, $options);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al importar: '.$e->getMessage(),
            ], 422);
        }

        $importable = $this->excelImportService->importableFields($temporaryModule->fields);
        $hasMediaImportFields = $importable->contains(fn ($f) => in_array((string) $f->type, ['image', 'file'], true));

        $payload = [
            'success' => true,
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
            'row_errors' => $result['row_errors'],
            'message' => $result['imported'] > 0
                ? "Se importaron {$result['imported']} registro(s)."
                : 'No se importó ninguna fila (revisa mapeo y datos).',
        ];

        $pdfImagesSaved = (int) ($result['pdf_images_saved'] ?? 0);
        if ($isPdf && $hasMediaImportFields && $pdfImagesSaved === 0) {
            $payload['warnings'] = [
                'No se pudieron extraer imágenes por fila desde este PDF. Verifica que sean imágenes embebidas en celdas de la tabla.',
            ];
        }

        return response()->json($payload);
    }

    /**
     * Actualizar registros existentes con datos faltantes (ej: imágenes) desde Excel.
     */
    public function updateExistingFromExcel(Request $request, int $module): JsonResponse
    {
        $temporaryModule = TemporaryModule::query()->with('fields')->findOrFail($module);
        abort_unless($temporaryModule->isAvailable(), 404);
        abort_unless($this->accessService->userCanAccessModule($temporaryModule, (int) $request->user()->id), 403);

        $request->validate([
            'archivo_excel' => ['required', 'file', 'mimes:xlsx,xls', 'max:153600'],
            'header_row' => ['nullable', 'integer', 'min:1', 'max:50'],
            'data_start_row' => ['nullable', 'integer', 'min:2', 'max:1000'],
            'mapping' => ['required', 'string'],
            'selected_microrregion_id' => ['nullable', 'integer'],
            'all_microrregions' => ['nullable', 'boolean'],
            'sheet_index' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $mapping = json_decode((string) $request->input('mapping'), true);
        if (! is_array($mapping)) {
            return response()->json(['success' => false, 'message' => 'Mapeo inválido.'], 422);
        }
        $normalizedMap = [];
        foreach ($mapping as $key => $idx) {
            if ($idx === '' || $idx === null) {
                $normalizedMap[(string) $key] = null;
            } elseif (is_numeric($idx)) {
                $normalizedMap[(string) $key] = (int) $idx;
            }
        }

        $allMicrorregions = $request->boolean('all_microrregions', false);
        $microrregionId = null;

        $microrregionesInfo = $this->accessService->microrregionesConMunicipiosPorUsuario((int) $request->user()->id);

        if (! $allMicrorregions) {
            $requestedMicrorregionId = $request->filled('selected_microrregion_id')
                ? (int) $request->input('selected_microrregion_id')
                : null;
            $microrregionIdsPermitidos = $this->accessService->microrregionIdsPorUsuario((int) $request->user()->id);
            if ($requestedMicrorregionId !== null && ! in_array($requestedMicrorregionId, $microrregionIdsPermitidos, true)) {
                return response()->json(['success' => false, 'message' => 'Microrregión no permitida.'], 403);
            }
            [$microrregionId] = $this->accessService->delegadoMunicipios($request->user()->id, $requestedMicrorregionId);
        }

        $headerRow = (int) ($request->input('header_row') ?: 1);
        $dataStartRow = (int) ($request->input('data_start_row') ?: $headerRow + 1);
        $sheetIndex = (int) ($request->input('sheet_index') ?: 0);

        try {
            $result = $this->excelImportService->updateExistingEntries(
                $temporaryModule,
                $request->file('archivo_excel'),
                [
                    'mapping' => $normalizedMap,
                    'header_row' => $headerRow,
                    'data_start_row' => $dataStartRow,
                    'selected_microrregion_id' => $microrregionId,
                    'all_microrregions' => $allMicrorregions,
                    'sheet_index' => $sheetIndex,
                ]
            );
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar: '.$e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
            'row_errors' => $result['row_errors'],
            'message' => $result['updated'] > 0
                ? "Se actualizaron {$result['updated']} registro(s) existente(s)."
                : 'No se actualizó ningún registro (no se encontraron coincidencias o todos están completos).',
        ]);
    }

    /**
     * Importación manual de una sola fila desde el log de errores.
     */
    public function importSingleRow(Request $request, int $module): JsonResponse
    {
        $temporaryModule = TemporaryModule::query()->findOrFail($module);
        abort_unless($temporaryModule->isAvailable(), 404);
        if (! $this->accessService->userCanAccessModule($temporaryModule, (int) $request->user()->id)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes acceso a este módulo temporal.',
            ], 403);
        }

        $validated = $request->validate([
            'data' => ['required', 'array'],
            'microrregion_id' => ['nullable', 'integer'],
        ]);

        $microrregionId = ! empty($validated['microrregion_id']) ? (int) $validated['microrregion_id'] : null;
        $userId = (int) $request->user()->id;

        $municipioField = $temporaryModule->fields()->where('type', 'municipio')->first();

        // Si no se proporcionó microrregion_id, resolverlo desde el municipio en los datos
        if ($microrregionId === null) {
            if ($municipioField) {
                $munVal = trim((string) ($validated['data'][$municipioField->key] ?? ''));
                if ($munVal !== '') {
                    // Intento directo por nombre
                    $microrregionId = \App\Models\Municipio::where('municipio', $munVal)->value('microrregion_id');

                    // Fallback: usar la misma normalización que el flujo de importación
                    if ($microrregionId === null) {
                        $micros = $this->accessService->microrregionesConMunicipiosPorUsuario($userId);
                        $normVal = $this->excelImportService->normalizeLabel($munVal);
                        foreach ($micros as $m) {
                            foreach ($m->municipios ?? [] as $mun) {
                                if ($this->excelImportService->normalizeLabel($mun) === $normVal) {
                                    $microrregionId = $m->id;
                                    break 2;
                                }
                            }
                        }
                    }
                }
            } else {
                // Sin campo municipio: los datos son solo de la microrregión elegida en el import (o la única asignada).
                $assignedIds = $this->accessService->microrregionIdsPorUsuario($userId);
                if (count($assignedIds) === 1) {
                    $microrregionId = (int) $assignedIds[0];
                }
            }
        }

        // No usar una microrregión (explícita o resuelta por catálogo) si el usuario no la tiene asignada.
        if ($microrregionId !== null && ! $this->accessService->userCanAccessEntryByMicrorregion($userId, $microrregionId)) {
            $microrregionId = null;
        }

        // Normalizar valores de multiselect: convertir strings a arrays
        $fields = $temporaryModule->fields;
        $data = $validated['data'];
        foreach ($fields as $f) {
            if ($f->type === 'multiselect' && isset($data[$f->key]) && is_string($data[$f->key])) {
                $strVal = trim($data[$f->key]);
                if ($strVal === '') {
                    $data[$f->key] = null;
                } else {
                    $opts = array_map('strval', $f->options ?? []);
                    // Buscar coincidencia exacta o normalizada en opciones
                    $matchIdx = null;
                    if (in_array($strVal, $opts, true)) {
                        $matchIdx = array_search($strVal, $opts, true);
                    } else {
                        $normVal = $this->excelImportService->normalizeLabel($strVal);
                        foreach ($opts as $i => $o) {
                            if ($this->excelImportService->normalizeLabel($o) === $normVal) {
                                $matchIdx = $i;
                                break;
                            }
                        }
                    }
                    if ($matchIdx !== null) {
                        $data[$f->key] = [$opts[$matchIdx]];
                    } else {
                        // Intentar split por coma/punto y coma
                        $items = array_values(array_filter(array_map('trim', preg_split('/[,;]+/', $strVal)), fn($s) => $s !== ''));
                        $data[$f->key] = $items ?: [$strVal];
                    }
                }
            }
        }

        // Validar todos los campos (select, requeridos, microrregión) y devolver errores con sugerencias
        $error = $this->excelImportService->validateSingleRow($temporaryModule, $data, $microrregionId, $userId);
        if ($error !== null) {
            return response()->json([
                'success' => false,
                'message' => $error['message'],
                'error' => $error,
            ], 422);
        }

        if ($microrregionId === null || $microrregionId < 1) {
            return response()->json([
                'success' => false,
                'message' => $municipioField
                    ? 'No se pudo determinar la microrregión. Revisa el municipio o que el import indique la microrregión seleccionada.'
                    : 'No se pudo determinar la microrregión. Abre de nuevo el import de Excel y elige la microrregión, o vuelve a cargar los errores desde la importación.',
            ], 422);
        }

        if (! $this->accessService->userCanAccessEntryByMicrorregion($userId, $microrregionId)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes asignada la microrregión indicada para este registro.',
            ], 422);
        }

        $temporaryModule->entries()->create([
            'user_id' => $request->user()->id,
            'microrregion_id' => $microrregionId,
            'data' => $data,
            'submitted_at' => Carbon::now(),
        ]);

        return response()->json(['success' => true]);
    }

    public function destroy(int $module): RedirectResponse
    {
        $temporaryModule = TemporaryModule::query()->findOrFail($module);
        $temporaryModule->delete();

        return redirect()
            ->route('temporary-modules.admin.index')
            ->with('status', 'Módulo temporal eliminado. Los registros capturados se conservaron.');
    }

    public function clearEntries(int $module): RedirectResponse
    {
        $temporaryModule = TemporaryModule::query()
            ->with('fields:id,temporary_module_id,key,type')
            ->findOrFail($module);
        $this->entryDataService->clearModuleEntriesData($temporaryModule);

        return redirect()
            ->route('temporary-modules.admin.records')
            ->with('status', 'Se vaciaron los registros del módulo correctamente.');
    }

    public function registerSeedDiscardRow(Request $request, int $module): JsonResponse
    {
        abort_unless(auth()->user()?->can('Modulos-Temporales-Admin'), 403);

        $temporaryModule = TemporaryModule::query()->findOrFail($module);
        $validated = $request->validate([
            'discard_uid' => ['required', 'string', 'max:120'],
            'municipio_id' => ['required', 'integer', 'exists:municipios,id'],
        ]);

        try {
            $result = $this->adminSeedService->registerDiscardedSeedRow(
                $temporaryModule,
                $validated['discard_uid'],
                (int) $validated['municipio_id'],
                (int) $request->user()->id,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'No se pudo registrar la fila.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Registro creado correctamente.',
            'entry_id' => $result['entry']->id,
            'seed_discard_log' => $result['seed_discard_log'],
        ]);
    }

    public function searchSeedDiscardMunicipios(Request $request, int $module): JsonResponse
    {
        abort_unless(auth()->user()?->can('Modulos-Temporales-Admin'), 403);
        TemporaryModule::query()->findOrFail($module);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $q = trim((string) ($validated['q'] ?? ''));
        $query = DB::table('municipios as mu')
            ->join('microrregiones as mr', 'mr.id', '=', 'mu.microrregion_id')
            ->select([
                'mu.id',
                'mu.municipio',
                'mu.microrregion_id',
                'mr.microrregion',
                'mr.cabecera',
            ]);

        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function ($w) use ($like): void {
                $w->where('mu.municipio', 'like', $like)
                    ->orWhere('mr.cabecera', 'like', $like)
                    ->orWhere('mr.microrregion', 'like', $like);
            });
        }

        $items = $query
            ->orderBy('mu.municipio')
            ->limit(30)
            ->get()
            ->map(function ($row) {
                $mr = str_pad(trim((string) $row->microrregion), 2, '0', STR_PAD_LEFT);

                return [
                    'id' => (int) $row->id,
                    'municipio' => (string) $row->municipio,
                    'microrregion_id' => (int) $row->microrregion_id,
                    'label' => 'MR '.$mr.' '.trim((string) ($row->cabecera ?? '')),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'ok' => true,
            'items' => $items,
        ]);
    }

    public function normalizeMunicipioField(int $module): RedirectResponse
    {
        $temporaryModule = TemporaryModule::query()
            ->with('fields:id,temporary_module_id,key,type')
            ->findOrFail($module);
        abort_unless(auth()->user()?->can('Modulos-Temporales-Admin'), 403);

        $field = $temporaryModule->fields->firstWhere('key', 'municipio');
        if (! $field) {
            return redirect()
                ->back()
                ->with('status', 'El módulo no tiene un campo con clave "municipio".');
        }

        $adminSeed = app(\App\Services\TemporaryModules\TemporaryModuleAdminSeedService::class);
        $result = $adminSeed->normalizeMunicipioField($temporaryModule, 'municipio');

        $msg = 'Normalización de municipio completada. '.$result['updated'].' registro(s) actualizados.';
        if (! empty($result['unmatched'])) {
            $ejemplos = implode(', ', array_slice($result['unmatched'], 0, 5));
            $msg .= ' No se pudo mapear '.count($result['unmatched']).' valor(es), por ejemplo: '.$ejemplos.'.';
        }

        return redirect()
            ->back()
            ->with('status', $msg);
    }

    public function exportExcel(Request $request, int $module)
    {
        // POST: format/cfg en cuerpo (personalizar envía JSON grande; en GET la URL puede truncarse y perder format=pdf → cae en excel).
        $format = (string) $request->input('format', $request->query('format', 'excel'));
        if (! in_array($format, ['excel', 'word', 'pdf'], true)) {
            $format = 'excel';
        }

        $mode = (string) $request->input('mode', $request->query('mode', 'single'));
        if (! in_array($mode, ['single', 'mr'], true)) {
            $mode = 'single';
        }
        $includeAnalysis = false;

        $exportConfig = $this->decodeTemporaryModuleExportConfig($request);

        $temporaryModule = TemporaryModule::query()->findOrFail($module);
        $fileName = trim((string) $temporaryModule->name) !== '' ? $temporaryModule->name : 'Módulo '.$module;

        if (in_array($format, ['word', 'pdf'], true)) {
            $exportRequestId = Str::uuid()->toString();
            $request->user()->notify(new \App\Notifications\ExcelExportPending($exportRequestId, $fileName, $format));

            \App\Jobs\GenerateTemporaryModuleWordPdfJob::dispatchAfterResponse(
                $module,
                $format,
                $request->user()->id,
                $exportRequestId,
                $exportConfig
            );

            $docType = $format === 'word' ? 'Word' : 'PDF';

            return redirect()->back()->with('toast', 'La generación del archivo '.$docType.' se ha enviado a segundo plano. Revisa tus notificaciones para ver el estado.');
        }

        $exportRequestId = Str::uuid()->toString();
        $request->user()->notify(new \App\Notifications\ExcelExportPending($exportRequestId, $fileName, 'excel'));

        \App\Jobs\GenerateTemporaryModuleExcelJob::dispatchAfterResponse(
            $module,
            $mode,
            $request->user()->id,
            $includeAnalysis,
            $exportRequestId,
            $exportConfig
        );

        return redirect()->back()->with('toast', 'La generación del archivo Excel se ha enviado a segundo plano. Revisa tus notificaciones para ver el estado.');
    }

    public function exportStatus(Request $request, string $exportRequest): \Illuminate\Http\JsonResponse
    {
        $notification = $request->user()->notifications()
            ->where('data->export_request_id', $exportRequest)
            ->first();

        // Fallback: en algunos MySQL/MariaDB la condición JSON no coincide; leer data ya casteado.
        if (! $notification) {
            $notification = $request->user()->notifications()
                ->orderByDesc('created_at')
                ->limit(400)
                ->get()
                ->first(function ($n) use ($exportRequest) {
                    $d = $n->data;

                    return is_array($d)
                        && (string) ($d['export_request_id'] ?? '') === (string) $exportRequest;
                });
        }

        // Sin 404: el front hace polling; UUIDs viejos o notificaciones borradas no deben llenar la consola.
        if (! $notification) {
            return response()->json(['status' => 'gone']);
        }

        $isPending = $notification->type === \App\Notifications\ExcelExportPending::class;
        $data = [
            'status' => $isPending ? 'pending' : (($notification->data['export_status'] ?? 'completed')),
        ];
        if (! $isPending && is_array($notification->data) && ($data['status'] === 'completed')) {
            $data['url'] = $notification->data['url'] ?? null;
            $data['file_name'] = $notification->data['file_name'] ?? null;
        }

        return response()->json($data);
    }

    public function exportPreviewStructure(Request $request, int $module): \Illuminate\Http\JsonResponse
    {
        $temporaryModule = TemporaryModule::query()->with('fields')->findOrFail($module);
        abort_unless($request->user()->can('Modulos-Temporales-Admin'), 403);

        $exportColumns = $this->getExportColumnsForPreview($temporaryModule);
        $sortDirection = $this->resolveMicrorregionSortDirection((string) $request->query('microrregion_sort', 'asc'));

        $maxWidths = [];
        $entries = $temporaryModule->entries()
            ->withoutGlobalScopes()
            ->leftJoin('microrregiones', 'microrregiones.id', '=', 'temporary_module_entries.microrregion_id')
            ->orderByRaw(
                'CASE WHEN temporary_module_entries.microrregion_id IS NULL THEN 1 ELSE 0 END, '.
                'CAST(COALESCE(microrregiones.microrregion, 0) AS UNSIGNED) '.strtoupper($sortDirection).', '.
                'temporary_module_entries.submitted_at DESC'
            )
            ->select(['data', 'microrregion_id'])
            ->limit(500)
            ->get();
        foreach ($exportColumns as $col) {
            if (($col['is_image'] ?? false)) {
                $maxWidths[$col['key']] = ['chars' => 12, 'image_height' => 80];

                continue;
            }
            $key = $col['key'];
            $max = 8;
            foreach ($entries as $entry) {
                $val = $entry->data[$key] ?? null;
                if (is_bool($val)) {
                    $max = max($max, 3);
                } elseif (is_scalar($val)) {
                    $max = max($max, mb_strlen((string) $val));
                }
            }
            $maxWidths[$col['key']] = ['chars' => min($max, 60)];
        }

        $columns = [];
        $columns[] = ['key' => 'item', 'label' => 'Ítem', 'type' => 'fixed', 'is_image' => false, 'max_width_chars' => 4];
        $columns[] = ['key' => 'microrregion', 'label' => 'Microrregión', 'type' => 'fixed', 'is_image' => false, 'max_width_chars' => 18];
        foreach ($exportColumns as $col) {
            $info = $maxWidths[$col['key']] ?? ['chars' => 15];
            $columns[] = [
                'key' => $col['key'],
                'label' => $col['label'],
                'type' => $col['type'],
                'is_image' => $col['is_image'],
                'max_width_chars' => $info['chars'] ?? 15,
                'image_height' => $info['image_height'] ?? 80,
            ];
        }

        $microrregionIds = $entries->pluck('microrregion_id')->filter()->unique()->values()->all();
        $microrregionMeta = [];
        if ($microrregionIds !== []) {
            $rows = \Illuminate\Support\Facades\DB::table('microrregiones')
                ->select(['id', 'cabecera', 'microrregion'])
                ->whereIn('id', $microrregionIds)
                ->get();
            foreach ($rows as $row) {
                $number = trim((string) ($row->microrregion ?? ''));
                $name = trim((string) ($row->cabecera ?? ''));
                $label = $number !== ''
                    ? ('MR '.str_pad($number, 2, '0', STR_PAD_LEFT).($name !== '' ? ' — '.$name : ''))
                    : ($name !== '' ? $name : 'Sin microrregión');
                $microrregionMeta[(int) $row->id] = [
                    'label' => $label,
                    'number' => $number,
                ];
            }
        }

        return response()->json([
            'title' => $temporaryModule->name,
            'columns' => $columns,
            'entries' => $entries->map(fn ($e) => ['data' => $e->data, 'microrregion_id' => $e->microrregion_id])->values()->all(),
            'microrregion_meta' => $microrregionMeta,
            'microrregion_sort' => $sortDirection,
        ]);
    }

    /** @return list<array{key: string, label: string, type: string, is_image: bool}> */
    private function getExportColumnsForPreview(TemporaryModule $temporaryModule): array
    {
        $cols = [];
        $currentSection = null;
        foreach ($temporaryModule->fields as $field) {
            if ($field->type === 'seccion') {
                $opts = is_array($field->options) ? $field->options : [];
                $currentSection = [
                    'title' => (string) ($opts['title'] ?? $field->label),
                    'subsections' => array_values((array) ($opts['subsections'] ?? [])),
                ];

                continue;
            }
            $label = $field->label;
            if ($currentSection !== null && ! empty($currentSection['subsections'])) {
                $idx = (int) ($field->subsection_index ?? 0);
                $label = $currentSection['subsections'][$idx] ?? $field->label;
            }
            $cols[] = [
                'key' => $field->key,
                'label' => $label,
                'type' => $field->type,
                'is_image' => in_array($field->type, ['image', 'file'], true),
            ];
            $currentSection = null;
        }

        return $cols;
    }

    public function analysisPreviewJson(Request $request, int $module): JsonResponse
    {
        abort_unless($request->user()->can('Modulos-Temporales-Admin'), 403);
        $config = [
            'include_summary' => $request->boolean('include_summary', true),
            'include_mr_table' => $request->boolean('include_mr_table', true),
            'doc_title' => (string) $request->query('doc_title', ''),
            'title_align' => (string) $request->query('title_align', 'center'),
            'subtitle' => (string) $request->query('subtitle', ''),
            'orientation' => strtolower(trim((string) $request->query('orientation', 'portrait'))) === 'landscape' ? 'landscape' : 'portrait',
            'column_keys' => $request->query('column_keys', '[]'),
            'table_font_pt' => $request->query('table_font_pt'),
            'table_cell_pad' => $request->query('table_cell_pad'),
            'table_cell_max_px' => $request->query('table_cell_max_px'),
            'include_dynamic_table' => $request->boolean('include_dynamic_table', true),
            'table_align' => (string) $request->query('table_align', 'left'),
            'summary_kpi_keys' => $request->query('summary_kpi_keys', '[]'),
            'totals_column_keys' => $request->query('totals_column_keys', '[]'),
        ];

        return response()->json($this->analysisWordService()->buildPreviewPayload($module, $config));
    }

    public function exportAnalysisWord(Request $request, int $module): RedirectResponse
    {
        abort_unless($request->user()->can('Modulos-Temporales-Admin'), 403);
        $validated = $request->validate([
            'include_summary' => 'nullable|in:0,1',
            'include_mr_table' => 'nullable|in:0,1',
            'doc_title' => 'nullable|string|max:240',
            'title_align' => 'nullable|in:left,center,right',
            'subtitle' => 'nullable|string|max:500',
            'orientation' => 'nullable|in:portrait,landscape',
            'column_keys' => 'nullable|string|max:8000',
            'table_font_pt' => 'nullable|integer|min:7|max:12',
            'table_cell_pad' => 'nullable|integer|min:2|max:16',
            'table_cell_max_px' => 'nullable|integer|min:72|max:280',
            'include_dynamic_table' => 'nullable|in:0,1',
            'table_align' => 'nullable|in:left,center,right,stretch',
            'summary_kpi_keys' => 'nullable|string|max:4000',
            'totals_column_keys' => 'nullable|string|max:4000',
            'title_font_size_px' => 'nullable|integer|min:10|max:36',
            'microrregion_sort' => 'nullable|in:asc,desc',
        ]);
        $config = [
            'include_summary' => ($validated['include_summary'] ?? '1') === '1',
            'include_mr_table' => ($validated['include_mr_table'] ?? '1') === '1',
            'include_dynamic_table' => ($validated['include_dynamic_table'] ?? '1') === '1',
            'doc_title' => trim((string) ($validated['doc_title'] ?? '')),
            'title_align' => $validated['title_align'] ?? 'center',
            'subtitle' => trim((string) ($validated['subtitle'] ?? '')),
            'orientation' => $validated['orientation'] ?? 'portrait',
            'column_keys' => $validated['column_keys'] ?? '[]',
            'table_font_pt' => $validated['table_font_pt'] ?? 9,
            'table_cell_pad' => $validated['table_cell_pad'] ?? 6,
            'table_cell_max_px' => $validated['table_cell_max_px'] ?? 140,
            'table_align' => $validated['table_align'] ?? 'left',
            'summary_kpi_keys' => $validated['summary_kpi_keys'] ?? '[]',
            'totals_column_keys' => $validated['totals_column_keys'] ?? '[]',
            'title_font_size_px' => $validated['title_font_size_px'] ?? 18,
            'microrregion_sort' => $validated['microrregion_sort'] ?? 'asc',
        ];
        $temporaryModule = TemporaryModule::query()->findOrFail($module);
        $fileName = trim((string) $temporaryModule->name) !== '' ? $temporaryModule->name : 'Módulo '.$module;
        $exportRequestId = Str::uuid()->toString();
        $request->user()->notify(new \App\Notifications\ExcelExportPending($exportRequestId, $fileName, 'word'));
        GenerateTemporaryModuleAnalysisWordJob::dispatchAfterResponse(
            $module,
            $request->user()->id,
            $exportRequestId,
            $config
        );

        return redirect()->back()->with('toast', 'Generación del informe Word en segundo plano. Revisa notificaciones.');
    }

    public function downloadExport(Request $request, string $file): BinaryFileResponse
    {
        abort_unless($request->user()->can('Modulos-Temporales-Admin'), 403);

        $file = trim($file);
        abort_unless($file !== '' && preg_match('/\A[A-Za-z0-9_\-]+\.(xlsx|docx|pdf)\z/', $file) === 1, 404);

        $path = storage_path('app/public/temporary-exports/'.$file);
        abort_unless(is_file($path), 404);

        return response()->download($path, $file);
    }

    public function previewEntryFile(Request $request, int $module, int $entry, string $fieldKey)
    {
        $entryModel = TemporaryModuleEntry::query()->with('module')->findOrFail($entry);
        abort_unless((int) $entryModel->temporary_module_id === $module, 404);

        if (! Gate::forUser($request->user())->allows('modulos-temporales-admin-ver')) {
            abort_unless($this->accessService->userCanAccessModule($entryModel->module, (int) $request->user()->id), 403);
            abort_unless(
                $this->accessService->userCanAccessEntryByMicrorregion((int) $request->user()->id, (int) $entryModel->microrregion_id),
                403
            );
        }

        $dataValue = $entryModel->data[$fieldKey] ?? null;
        $index = (int) $request->query('i', 0);
        $path = is_array($dataValue) ? ($dataValue[$index] ?? null) : $dataValue;

        abort_unless(is_string($path) && trim($path) !== '', 404);

        $fullPath = $this->entryDataService->resolveStoredFilePath($path);

        abort_unless(is_string($fullPath) && is_file($fullPath), 404);

        if (! $this->entryDataService->pathCanBePreviewedInline($path)) {
            return response()->download($fullPath, basename($fullPath), [
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return response()->file($fullPath);
    }

    public function toggleActive(Request $request, int $module): RedirectResponse
    {
        $temporaryModule = TemporaryModule::query()->findOrFail($module);
        $temporaryModule->update([
            'is_active' => ! (bool) $temporaryModule->is_active,
        ]);

        $status = $temporaryModule->is_active ? 'activado' : 'desactivado';

        return redirect()
            ->route('temporary-modules.admin.index')
            ->with('status', 'Módulo temporal '.$status.' correctamente.');
    }

    private function compressAndStoreImage(\Illuminate\Http\UploadedFile $file, string $directory): string
    {
        $mime = $file->getMimeType();
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file->getRealPath()),
            'image/png'  => @imagecreatefrompng($file->getRealPath()),
            'image/webp' => @imagecreatefromwebp($file->getRealPath()),
            default      => false,
        };

        if (!$image) {
            return $file->store($directory, 'secure_shared');
        }

        $width  = imagesx($image);
        $height = imagesy($image);
        $maxDim = 1920;

        if ($width > $maxDim || $height > $maxDim) {
            $ratio = min($maxDim / $width, $maxDim / $height);
            $newW  = (int) round($width * $ratio);
            $newH  = (int) round($height * $ratio);
            $resized = imagecreatetruecolor($newW, $newH);

            if ($mime === 'image/png' || $mime === 'image/webp') {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
            }

            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'img_');

        match ($mime) {
            'image/png'  => imagepng($image, $tmpPath, 8),
            'image/webp' => imagewebp($image, $tmpPath, 75),
            default      => imagejpeg($image, $tmpPath, 75),
        };

        imagedestroy($image);

        $filename = Str::random(40).match ($mime) {
            'image/png'  => '.png',
            'image/webp' => '.webp',
            default      => '.jpg',
        };

        $storedPath = $directory.'/'.$filename;
        Storage::disk('secure_shared')->put($storedPath, file_get_contents($tmpPath));

        @unlink($tmpPath);

        return $storedPath;
    }
}
