<?php

namespace App\Http\Controllers;

use App\Models\TemporaryModule;
use App\Models\TemporaryModuleEntry;
use App\Services\TemporaryModules\TemporaryModuleAccessService;
use App\Services\TemporaryModules\TemporaryModuleEntryDataService;
use App\Jobs\GenerateTemporaryModuleAnalysisWordJob;
use App\Services\TemporaryModules\TemporaryModuleAnalysisWordService;
use App\Services\TemporaryModules\TemporaryModuleAdminSeedService;
use App\Services\TemporaryModules\TemporaryModuleSlugService;
use App\Services\TemporaryModules\TemporaryModuleExcelImportService;
use App\Services\TemporaryModules\TemporaryModuleExportService;
use App\Services\TemporaryModules\TemporaryModuleFieldService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Dompdf\Dompdf;

class TemporaryModuleController extends Controller
{
    public function __construct(
        private readonly TemporaryModuleAccessService $accessService,
        private readonly TemporaryModuleFieldService $fieldService,
        private readonly TemporaryModuleEntryDataService $entryDataService,
        private readonly TemporaryModuleExportService $exportService,
        private readonly TemporaryModuleExcelImportService $excelImportService,
        private readonly TemporaryModuleAdminSeedService $adminSeedService,
        private readonly TemporaryModuleSlugService $slugService,
    ) {}

    /** Solo se resuelve al usar preview Word (evita cargar PhpWord en el resto de acciones). */
    private function analysisWordService(): TemporaryModuleAnalysisWordService
    {
        return app(TemporaryModuleAnalysisWordService::class);
    }

    private const FIELD_TYPES = [
        'text' => 'Texto',
        'textarea' => 'Texto largo',
        'number' => 'Número',
        'date' => 'Fecha',
        'datetime' => 'Fecha y hora',
        'select' => 'Lista de opciones',
        'categoria' => 'Categoría',
        'municipio' => 'Municipio',
        'boolean' => 'Sí / No',
        'seccion' => 'Sección',
        'geopoint' => 'Georreferencia',
        'image' => 'Imagen',
    ];

    public function adminIndex(): View
    {
        $modules = TemporaryModule::query()
            ->select(['id', 'name', 'description', 'expires_at', 'applies_to_all', 'is_active'])
            ->withCount(['fields', 'entries', 'targetUsers'])
            ->latest()
            ->paginate(10);

        return view('temporary_modules.admin.index', [
            'pageTitle' => 'Modulos temporales',
            'pageDescription' => 'Configura apartados temporales para captura de informacion por delegados.',
            'topbarNotifications' => [],
            'modules' => $modules,
        ]);
    }

    public function adminRecords(): View
    {
        // Evita 500 si en producción no corrió la migración de exported_at / seed_discard_log
        $select = ['id', 'name', 'description', 'expires_at'];
        if (Schema::hasColumn('temporary_modules', 'exported_at')) {
            $select[] = 'exported_at';
        }
        if (Schema::hasColumn('temporary_modules', 'seed_discard_log')) {
            $select[] = 'seed_discard_log';
        }

        $modules = TemporaryModule::query()
            ->whereHas('entries')
            ->withCount(['fields', 'entries'])
            ->select($select)
            ->latest()
            ->paginate(15);

        return view('temporary_modules.admin.records', [
            'pageTitle' => 'Registros de modulos temporales',
            'pageDescription' => 'Consulta registros de delegados y exporta resultados en Excel.',
            'topbarNotifications' => [],
            'modules' => $modules,
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
            'archivo_excel' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
            'header_row' => ['nullable', 'integer', 'min:1', 'max:200'],
            'auto_detect' => ['nullable', 'boolean'],
        ]);
        $file = $request->file('archivo_excel');
        $autoDetect = $request->boolean('auto_detect', true);
        $headerRow = (int) ($request->input('header_row') ?: 1);
        $detected = null;

        if ($autoDetect) {
            try {
                $detected = $this->adminSeedService->detectTableLayout($file);
                if ($detected !== null) {
                    $headerRow = $detected['header_row'];
                }
            } catch (\Throwable) {
                // sigue con header_row manual o 1
            }
        }

        try {
            $preview = $this->adminSeedService->previewHeaders($file, $headerRow);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $payload = [
            'success' => true,
            'headers' => $preview['headers'],
            'header_row' => $headerRow,
            'data_start_row' => $headerRow + 1,
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
            'archivo_excel' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
            'header_row' => ['nullable', 'integer', 'min:1', 'max:50'],
            'data_start_row' => ['nullable', 'integer', 'min:2', 'max:50000'],
            'col_microrregion' => ['nullable', 'integer', 'min:-1'],
            'col_municipio' => ['nullable', 'integer', 'min:-1'],
            'field_columns' => ['required', 'string'],
        ]);

        $colMr = (int) $request->input('col_microrregion', -1);
        $colMun = (int) $request->input('col_municipio', -1);
        if ($colMun < 0 && $colMr < 0) {
            throw ValidationException::withMessages([
                'col_municipio' => 'Indica columna Municipio o columna Microrregión (o ambas).',
            ]);
        }

        $fieldColumns = json_decode((string) $request->input('field_columns'), true);
        if (!is_array($fieldColumns) || $fieldColumns === []) {
            throw ValidationException::withMessages(['field_columns' => 'Elige al menos una columna como campo del módulo.']);
        }
        $fieldColumns = array_map('intval', $fieldColumns);

        $isIndefinite = (bool) $request->boolean('is_indefinite');
        $expiresAt = null;
        if (!$isIndefinite) {
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
                $stats,
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
                    $lines[] = $name . (count($subs) ? ': ' . implode(', ', $subs) : '');
                }
                $row['options'] = implode("\n", $lines);
            } elseif ($field->type === 'seccion' && is_array($field->options)) {
                $row['options_title'] = (string) ($field->options['title'] ?? '');
                $row['options_subsections'] = implode("\n", (array) ($field->options['subsections'] ?? []));
            } else {
                $row['options'] = is_array($field->options) ? implode(', ', $field->options) : '';
            }
            return $row;
        })->values()->all();

        return response()->json(['fields' => $fields]);
    }

    public function edit(int $module): View
    {
        $temporaryModule = TemporaryModule::query()
            ->select(['id', 'name', 'description', 'expires_at', 'applies_to_all', 'is_active', 'seed_discard_log'])
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

        if (!$this->isIndefiniteMode($validated) && empty($validated['expires_at'])) {
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

            if (!$module->applies_to_all) {
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
            'conflict_action' => ['nullable', Rule::in(['none', 'clear_module', 'clear_field_data', 'normalize_municipio'])],
            'existing_fields' => ['nullable', 'array'],
            'existing_fields.*.id' => ['required_with:existing_fields', 'integer'],
            'existing_fields.*.label' => ['required_with:existing_fields', 'string', 'max:120'],
            'existing_fields.*.key' => ['nullable', 'string', 'max:120'],
            'existing_fields.*.type' => ['required_with:existing_fields', Rule::in(array_keys(self::FIELD_TYPES))],
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

        if (!$this->isIndefiniteMode($validated) && empty($validated['expires_at'])) {
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
                $destructiveKeys[] = $oldKey;
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

        if ($conflictAction === 'normalize_municipio') {
            $adminSeed = app(\App\Services\TemporaryModules\TemporaryModuleAdminSeedService::class);
            $adminSeed->normalizeMunicipioField($temporaryModule, 'municipio');
            // Después de normalizar, ese campo ya no debe contarse como “destructivo”
            $destructiveKeys = array_values(array_diff($destructiveKeys, ['municipio']));
            $conflictAction = 'none';
        }

        if (!empty($destructiveKeys)) {
            if (!in_array($conflictAction, ['clear_module', 'clear_field_data'], true)) {
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
                if (!$field) {
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

            if (!empty($deletedFieldIds)) {
                $temporaryModule->fields()->whereIn('id', $deletedFieldIds)->delete();
            }

            if (!empty($preparedExtraFields)) {
                $temporaryModule->fields()->createMany($preparedExtraFields);
            }

            if (!empty($invalidMainImageKeys)) {
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

    public function delegateIndex(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (Gate::forUser($user)->allows('modulos-temporales-admin-ver')) {
            return $this->adminIndex();
        }

        [, $municipios] = $this->accessService->delegadoMunicipios((int) $user->id);
        $microrregionesAsignadas = $this->accessService->microrregionesConMunicipiosPorUsuario((int) $user->id);
        $microrregionIdsUsuario = $this->accessService->microrregionIdsPorUsuario((int) $user->id);

        $modules = TemporaryModule::query()
            ->select(['id', 'name', 'description', 'expires_at', 'is_active', 'applies_to_all'])
            ->where('is_active', true)
            ->where(function ($query) use ($user) {
                $query->where('applies_to_all', true)
                    ->orWhereHas('targetUsers', fn ($targetQuery) => $targetQuery->where('users.id', $user->id));
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', Carbon::now());
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
            ->paginate(10)
            ->withQueryString();

        if ($modules->isEmpty() && (int) $request->get('page', 1) > 1 && $modules->total() > 0) {
            return redirect()->route($request->route()->getName(), array_merge(
                $request->except('page'),
                ['page' => 1]
            ));
        }
        if ($modules->isEmpty() && $modules->total() === 0 && (int) $request->get('page', 1) > 1) {
            return redirect()->route($request->route()->getName(), $request->except('page'));
        }

        $moduleIds = $modules->getCollection()->pluck('id')->all();
        $entriesByModule = collect();
        if ($moduleIds !== []) {
            $entriesByModule = TemporaryModuleEntry::query()
                ->whereIn('temporary_module_id', $moduleIds)
                ->when(
                    $microrregionIdsUsuario !== [],
                    fn ($q) => $q->whereIn('microrregion_id', $microrregionIdsUsuario),
                    fn ($q) => $q->whereRaw('1 = 0')
                )
                ->with('microrregion:id,microrregion,cabecera')
                ->select(['id', 'temporary_module_id', 'user_id', 'microrregion_id', 'data', 'submitted_at'])
                ->latest('submitted_at')
                ->get()
                ->groupBy('temporary_module_id');
        }
        foreach ($modules->getCollection() as $module) {
            $module->setRelation('myEntries', $entriesByModule->get($module->id, collect()));
        }

        // Solo carga campos y entradas del usuario para el módulo activo
        $requestedModuleId = $request->filled('module')
            ? (int) $request->query('module')
            : null;

        $activeModuleId = $requestedModuleId !== null
            && $modules->contains(fn ($module) => (int) $module->id === $requestedModuleId)
            ? $requestedModuleId
            : (int) ($modules->first()?->id ?? 0);

        $activeModule = $modules->firstWhere('id', $activeModuleId);

        $fields = [];
        $myEntries = collect();
        if ($activeModule) {
            $fields = $activeModule->fields()->select(['id', 'temporary_module_id', 'label', 'key', 'type', 'options', 'is_required'])->get();
            $myEntries = $activeModule->entries()
                ->when(
                    $microrregionIdsUsuario !== [],
                    fn ($q) => $q->whereIn('microrregion_id', $microrregionIdsUsuario),
                    fn ($q) => $q->whereRaw('1 = 0')
                )
                ->with('microrregion:id,microrregion,cabecera')
                ->select(['id', 'temporary_module_id', 'user_id', 'microrregion_id', 'data', 'submitted_at'])
                ->latest('submitted_at')
                ->paginate(10, ['*'], 'entries_page')
                ->appends(array_merge($request->except('entries_page'), ['module' => $activeModuleId]))
                ->withQueryString();
        }

        $activeSection = optional($request->route())->getName() === 'temporary-modules.records'
            ? 'records'
            : 'upload';

        return view('temporary_modules.delegate.index', [
            'pageTitle' => 'Capturas temporales',
            'pageDescription' => 'Registra informacion solicitada para tus municipios en modulos activos.',
            'topbarNotifications' => [],
            'modules' => $modules,
            'fields' => $fields,
            'myEntries' => $myEntries,
            'municipios' => $municipios,
            'microrregionesAsignadas' => $microrregionesAsignadas,
            'activeSection' => $activeSection,
            'activeModuleId' => $activeModuleId,
            'fragmentUploadUrl' => route('temporary-modules.fragment.upload'),
            'fragmentRecordsUrl' => route('temporary-modules.fragment.records'),
        ]);
    }

    /** HTML parcial: grid de módulos + paginación (AJAX). */
    public function delegatePartialUpload(Request $request): View
    {
        $user = $request->user();
        abort_if($user->can('Modulos-Temporales-Admin'), 404);
        $microrregionIdsUsuario = $this->accessService->microrregionIdsPorUsuario((int) $user->id);
        $modules = TemporaryModule::query()
            ->select(['id', 'name', 'description', 'expires_at', 'is_active', 'applies_to_all'])
            ->where('is_active', true)
            ->where(function ($query) use ($user) {
                $query->where('applies_to_all', true)
                    ->orWhereHas('targetUsers', fn ($targetQuery) => $targetQuery->where('users.id', $user->id));
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', Carbon::now());
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
            ->paginate(10)
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
            ->with('microrregion:id,microrregion,cabecera')
            ->select(['id', 'temporary_module_id', 'user_id', 'microrregion_id', 'data', 'submitted_at'])
            ->latest('submitted_at')
            ->paginate(10, ['*'], 'entries_page')
            ->appends(['module' => $moduleId])
            ->withQueryString();
        $municipioField = $temporaryModule->fields->firstWhere('type', 'municipio');

        return view('temporary_modules.delegate.partials.records_entries', [
            'module' => $temporaryModule,
            'entries' => $myEntries,
            'municipioField' => $municipioField,
            'fragmentRecordsUrl' => route('temporary-modules.fragment.records'),
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
        if ($requestedMicrorregionId !== null && !in_array($requestedMicrorregionId, $microrregionIdsPermitidos, true)) {
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
                $hasExistingImage = is_string($existingValue) && trim($existingValue) !== '';
                $removeRequested = filter_var($request->input('remove_images.'.$field->key), FILTER_VALIDATE_BOOLEAN);
                $isRequiredNow = (bool) $field->is_required && (!$hasExistingImage || $removeRequested);

                $rules[$key] = $isRequiredNow
                    ? ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240']
                    : ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'];
                $rules['remove_images.'.$field->key] = ['nullable', 'boolean'];
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

            if (in_array($field->type, ['file', 'image'], true) && $request->hasFile('values.'.$field->key)) {
                $storedPath = $request->file('values.'.$field->key)->store(
                    'temporary-modules/'.$temporaryModule->id.'/'.$request->user()->id,
                    'secure_shared'
                );

                if (is_string($existingValue) && trim($existingValue) !== '' && $existingValue !== $storedPath) {
                    $this->entryDataService->deleteStoredPath($existingValue);
                }

                $value = $storedPath;
            } elseif (in_array($field->type, ['file', 'image'], true)) {
                if ($existingEntry && !$removeRequested && is_string($existingValue) && trim($existingValue) !== '') {
                    $value = $existingValue;
                } else {
                    if ($existingEntry && $removeRequested && is_string($existingValue) && trim($existingValue) !== '') {
                        $this->entryDataService->deleteStoredPath($existingValue);
                    }
                    $value = null;
                }
            }

            if ($field->type === 'boolean') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }

            $values[$field->key] = $value;
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

    /**
     * Paso 1: sube Excel y devuelve columnas detectadas + sugerencia de mapeo a campos del módulo.
     */
    public function importExcelPreview(Request $request, int $module): JsonResponse
    {
        $temporaryModule = TemporaryModule::query()->with('fields')->findOrFail($module);
        abort_unless($temporaryModule->isAvailable(), 404);
        abort_unless($this->accessService->userCanAccessModule($temporaryModule, (int) $request->user()->id), 403);

        $request->validate([
            'archivo_excel' => ['required', 'file', 'mimes:xlsx,xls', 'max:15360'],
            'header_row' => ['nullable', 'integer', 'min:1', 'max:200'],
            'auto_detect' => ['nullable', 'boolean'],
        ]);

        $file = $request->file('archivo_excel');
        $headerRow = (int) ($request->input('header_row') ?: 1);
        $detected = null;
        if ($request->boolean('auto_detect', true)) {
            try {
                $detected = $this->adminSeedService->detectTableLayout($file);
                if ($detected !== null) {
                    $headerRow = $detected['header_row'];
                }
            } catch (\Throwable) {
            }
        }

        $importable = $this->excelImportService->importableFields($temporaryModule->fields);
        if ($importable->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Este módulo no tiene campos importables desde Excel (texto, número, fecha, lista, municipio, etc.).',
            ], 422);
        }

        try {
            $preview = $this->excelImportService->preview($file, $headerRow);
            $preview['suggested_map'] = $this->excelImportService->suggestMap($importable, $preview['headers']);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo leer el Excel: '.$e->getMessage(),
            ], 422);
        }

        $out = [
            'success' => true,
            'header_row' => $headerRow,
            'data_start_row' => $detected['data_start_row'] ?? ($headerRow + 1),
            'headers' => $preview['headers'],
            'suggested_map' => $preview['suggested_map'],
            'fields' => $importable->map(fn ($f) => [
                'key' => $f->key,
                'label' => $f->label,
                'type' => $f->type,
                'is_required' => (bool) $f->is_required,
            ])->values()->all(),
        ];
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
            'archivo_excel' => ['required', 'file', 'mimes:xlsx,xls', 'max:15360'],
            'header_row' => ['nullable', 'integer', 'min:1', 'max:50'],
            'data_start_row' => ['nullable', 'integer', 'min:2', 'max:1000'],
            'mapping' => ['required', 'string'],
            'selected_microrregion_id' => ['nullable', 'integer'],
        ]);

        $mapping = json_decode((string) $request->input('mapping'), true);
        if (!is_array($mapping)) {
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

        $requestedMicrorregionId = $request->filled('selected_microrregion_id')
            ? (int) $request->input('selected_microrregion_id')
            : null;
        $microrregionIdsPermitidos = $this->accessService->microrregionIdsPorUsuario((int) $request->user()->id);
        if ($requestedMicrorregionId !== null && !in_array($requestedMicrorregionId, $microrregionIdsPermitidos, true)) {
            return response()->json(['success' => false, 'message' => 'Microrregión no permitida.'], 403);
        }

        [$microrregionId, $municipios] = $this->accessService->delegadoMunicipios($request->user()->id, $requestedMicrorregionId);
        $allowedMunicipios = array_values($municipios);

        $headerRow = (int) ($request->input('header_row') ?: 1);
        $dataStartRow = (int) ($request->input('data_start_row') ?: $headerRow + 1);
        $fieldsByKey = $temporaryModule->fields->keyBy('key');

        try {
            $result = $this->excelImportService->importRows(
                $temporaryModule,
                (int) $request->user()->id,
                (int) $microrregionId,
                $request->file('archivo_excel'),
                $headerRow,
                $dataStartRow,
                $normalizedMap,
                $fieldsByKey,
                $allowedMunicipios,
            );
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al importar: '.$e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
            'row_errors' => $result['row_errors'],
            'message' => $result['imported'] > 0
                ? "Se importaron {$result['imported']} registro(s)."
                : 'No se importó ninguna fila (revisa mapeo y datos).',
        ]);
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

    public function normalizeMunicipioField(int $module): RedirectResponse
    {
        $temporaryModule = TemporaryModule::query()
            ->with('fields:id,temporary_module_id,key,type')
            ->findOrFail($module);
        abort_unless(auth()->user()?->can('Modulos-Temporales-Admin'), 403);

        $field = $temporaryModule->fields->firstWhere('key', 'municipio');
        if (!$field) {
            return redirect()
                ->back()
                ->with('status', 'El módulo no tiene un campo con clave "municipio".');
        }

        $adminSeed = app(\App\Services\TemporaryModules\TemporaryModuleAdminSeedService::class);
        $result = $adminSeed->normalizeMunicipioField($temporaryModule, 'municipio');

        $msg = 'Normalización de municipio completada. '.$result['updated'].' registro(s) actualizados.';
        if (!empty($result['unmatched'])) {
            $ejemplos = implode(', ', array_slice($result['unmatched'], 0, 5));
            $msg .= ' No se pudo mapear '.count($result['unmatched']).' valor(es), por ejemplo: '.$ejemplos.'.';
        }

        return redirect()
            ->back()
            ->with('status', $msg);
    }

    public function exportExcel(Request $request, int $module)
    {
        $format = (string) $request->query('format', 'excel');
        if (!in_array($format, ['excel', 'word', 'pdf'], true)) {
            $format = 'excel';
        }

        $mode = (string) $request->query('mode', 'single');
        if (!in_array($mode, ['single', 'mr'], true)) {
            $mode = 'single';
        }
        $includeAnalysis = false;

        $rawConfig = $request->query('cfg');
        $exportConfig = null;
        if (is_string($rawConfig) && $rawConfig !== '') {
            try {
                $decoded = json_decode($rawConfig, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $exportConfig = $decoded;
                }
            } catch (\Throwable $e) {
                $exportConfig = null;
            }
        }

        $temporaryModule = TemporaryModule::query()->findOrFail($module);
        $fileName = trim((string) $temporaryModule->name) !== '' ? $temporaryModule->name : 'Módulo '.$module;

        if (in_array($format, ['word', 'pdf'], true)) {
            // Exportación directa a Word o PDF (solo tabla de registros, sin análisis).
            $columnsCfg = is_array($exportConfig) && isset($exportConfig['columns']) && is_array($exportConfig['columns'])
                ? $exportConfig['columns']
                : [];
            if ($columnsCfg === []) {
                $cols = $this->getExportColumnsForPreview($temporaryModule);
                $columnsCfg = array_map(static fn (array $col): array => ['key' => $col['key'], 'label' => $col['label'] ?? $col['key']], $cols);
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
                abort(422, 'No hay columnas seleccionadas para el reporte.');
            }

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

                $table = $section->addTable([
                    'borderSize' => 6,
                    'borderColor' => '444444',
                    'cellMargin' => 80,
                ]);

                // Encabezados
                $table->addRow();
                foreach ($columns as $col) {
                    $table->addCell(null)->addText((string) $col['label'], ['bold' => true]);
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
                        $table->addCell(null)->addText($text);
                    }
                }

                \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007')->save($fullPath);

                $downloadUrl = route('temporary-modules.admin.exports.download', ['file' => $wordFileName]);
                $request->user()->notify(new \App\Notifications\ExcelExportCompleted($wordFileName, $downloadUrl));

                return redirect()->back()->with('toast', 'El documento Word se generó correctamente. Puedes descargarlo desde tus notificaciones.');
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
            ])->render();

            $dompdf = new Dompdf([
                'defaultPaperSize' => 'a4',
            ]);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', $orientationConfig === 'landscape' ? 'landscape' : 'portrait');
            $dompdf->render();
            file_put_contents($fullPdfPath, $dompdf->output());

            $downloadUrl = route('temporary-modules.admin.exports.download', ['file' => $pdfFileName]);
            $request->user()->notify(new \App\Notifications\ExcelExportCompleted($pdfFileName, $downloadUrl));

            return redirect()->back()->with('toast', 'El PDF se generó correctamente. Puedes descargarlo desde tus notificaciones.');
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

        if (!$notification) {
            abort(404);
        }

        $isPending = $notification->type === \App\Notifications\ExcelExportPending::class;
        $data = [
            'status' => $isPending ? 'pending' : (($notification->data['export_status'] ?? 'completed')),
        ];
        if (!$isPending && is_array($notification->data) && ($data['status'] === 'completed')) {
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

        $maxWidths = [];
        $entries = $temporaryModule->entries()->withoutGlobalScopes()->select('data')->limit(500)->get();
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
        $columns[] = ['key' => 'item', 'label' => 'Ítem', 'type' => 'fixed', 'is_image' => false, 'max_width_chars' => 6];
        $columns[] = ['key' => 'microrregion', 'label' => 'Microrregión', 'type' => 'fixed', 'is_image' => false, 'max_width_chars' => 22];
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

        return response()->json([
            'title' => $temporaryModule->name,
            'columns' => $columns,
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
            if ($currentSection !== null && !empty($currentSection['subsections'])) {
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

        $path = $entryModel->data[$fieldKey] ?? null;
        abort_unless(is_string($path) && trim($path) !== '', 404);

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return redirect()->away($path);
        }

        $fullPath = $this->entryDataService->resolveStoredFilePath($path);

        abort_unless(is_string($fullPath) && is_file($fullPath), 404);
        return response()->file($fullPath);
    }

}
