<?php

namespace App\Http\Controllers;

use App\Models\TemporaryModule;
use App\Models\TemporaryModuleEntry;
use App\Services\TemporaryModules\TemporaryModuleAccessService;
use App\Services\TemporaryModules\TemporaryModuleEntryDataService;
use App\Services\TemporaryModules\TemporaryModuleExportService;
use App\Services\TemporaryModules\TemporaryModuleFieldService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TemporaryModuleController extends Controller
{
    public function __construct(
        private readonly TemporaryModuleAccessService $accessService,
        private readonly TemporaryModuleFieldService $fieldService,
        private readonly TemporaryModuleEntryDataService $entryDataService,
        private readonly TemporaryModuleExportService $exportService,
    )
    {
    }

    private const FIELD_TYPES = [
        'text' => 'Texto',
        'textarea' => 'Texto largo',
        'number' => 'Número',
        'date' => 'Fecha',
        'datetime' => 'Fecha y hora',
        'select' => 'Lista de opciones',
        'municipio' => 'Municipio',
        'boolean' => 'Sí / No',
        'geopoint' => 'Georreferencia',
        'image' => 'Imagen',
    ];

    public function adminIndex(): View
    {
        $modules = TemporaryModule::query()
            ->withCount(['fields', 'entries', 'targetUsers'])
            ->latest()
            ->get();

        return view('temporary_modules.admin.index', [
            'pageTitle' => 'Modulos temporales',
            'pageDescription' => 'Configura apartados temporales para captura de informacion por delegados.',
            'topbarNotifications' => [],
            'modules' => $modules,
        ]);
    }

    public function adminRecords(): View
    {
        $modules = TemporaryModule::query()
            ->with([
                'fields:id,temporary_module_id,label,key,type',
                'entries' => function ($query) {
                    $query->with('user:id,name,email')
                        ->latest('submitted_at');
                },
            ])
            ->whereHas('entries')
            ->withCount(['fields', 'entries', 'targetUsers'])
            ->latest()
            ->get();

        return view('temporary_modules.admin.records', [
            'pageTitle' => 'Registros de modulos temporales',
            'pageDescription' => 'Consulta registros de delegados y exporta resultados en Excel.',
            'topbarNotifications' => [],
            'modules' => $modules,
        ]);
    }

    public function create(): View
    {
        return view('temporary_modules.admin.create', [
            'pageTitle' => 'Crear modulo temporal',
            'pageDescription' => 'Define nombre, campos requeridos y fecha limite de visualizacion.',
            'topbarNotifications' => [],
            'fieldTypes' => self::FIELD_TYPES,
            'delegates' => $this->accessService->delegates(),
        ]);
    }

    public function edit(int $module): View
    {
        $temporaryModule = TemporaryModule::query()->with('fields', 'targetUsers:id')->findOrFail($module);
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
            $baseSlug = $slug;
            $suffix = 2;
            while (TemporaryModule::withTrashed()->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$suffix;
                $suffix++;
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
            'conflict_action' => ['nullable', Rule::in(['none', 'clear_module', 'clear_field_data'])],
            'existing_fields' => ['nullable', 'array'],
            'existing_fields.*.id' => ['required_with:existing_fields', 'integer'],
            'existing_fields.*.label' => ['required_with:existing_fields', 'string', 'max:120'],
            'existing_fields.*.key' => ['nullable', 'string', 'max:120'],
            'existing_fields.*.type' => ['required_with:existing_fields', Rule::in(array_keys(self::FIELD_TYPES))],
            'existing_fields.*.required' => ['nullable', 'boolean'],
            'existing_fields.*.delete' => ['nullable', 'boolean'],
            'existing_fields.*.options' => ['nullable', 'string', 'max:2000'],
            'extra_fields' => ['nullable', 'array'],
            'extra_fields.*.label' => ['required_with:extra_fields', 'string', 'max:120'],
            'extra_fields.*.key' => ['nullable', 'string', 'max:120'],
            'extra_fields.*.type' => ['required_with:extra_fields', Rule::in(array_keys(self::FIELD_TYPES))],
            'extra_fields.*.required' => ['nullable', 'boolean'],
            'extra_fields.*.options' => ['nullable', 'string', 'max:2000'],
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
            $row = $submittedExisting->get((int) $field->id, [
                'id' => (int) $field->id,
                'label' => $field->label,
                'comment' => $this->fieldService->supportsFieldComment() ? ($field->comment ?? null) : null,
                'key' => $field->key,
                'type' => $field->type,
                'required' => $field->is_required,
                'options' => is_array($field->options) ? implode(', ', $field->options) : null,
                'delete' => false,
            ]);

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
            $baseSlug = $slug;
            $suffix = 2;
            while (TemporaryModule::withTrashed()->where('slug', $slug)->where('id', '!=', $temporaryModule->id)->exists()) {
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

    public function delegateIndex(Request $request): View
    {
        $user = $request->user();

        if ($user->can('Modulos-Temporales-Admin')) {
            return $this->adminIndex();
        }

        [, $municipios] = $this->accessService->delegadoMunicipios((int) $user->id);
        $microrregionesAsignadas = $this->accessService->microrregionesConMunicipiosPorUsuario((int) $user->id);

        $modules = TemporaryModule::query()
            ->with(['fields' => function ($query) {
                $columns = ['id', 'temporary_module_id', 'label', 'key', 'type', 'options', 'is_required'];
                if ($this->fieldService->supportsFieldComment()) {
                    $columns[] = 'comment';
                }
                $query->select($columns);
            }])
            ->where('is_active', true)
            ->where(function ($query) use ($user) {
                $query->where('applies_to_all', true)
                    ->orWhereHas('targetUsers', fn ($targetQuery) => $targetQuery->where('users.id', $user->id));
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', Carbon::now());
            })
            ->withCount([
                'entries as my_entries_count' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->latest()
            ->get();

        $modules->each(function (TemporaryModule $module) use ($user): void {
            $module->setRelation('myLatestEntry', $module->entries()
                ->where('user_id', $user->id)
                ->latest('submitted_at')
                ->first());

            $module->setRelation('myEntries', $module->entries()
                ->with('microrregion')
                ->where('user_id', $user->id)
                ->latest('submitted_at')
                ->take(50)
                ->get());
        });

        $requestedModuleId = $request->filled('module')
            ? (int) $request->query('module')
            : null;

        $activeModuleId = $requestedModuleId !== null
            && $modules->contains(fn (TemporaryModule $module) => (int) $module->id === $requestedModuleId)
            ? $requestedModuleId
            : (int) ($modules->first()->id ?? 0);

        $activeSection = optional($request->route())->getName() === 'temporary-modules.records'
            ? 'records'
            : 'upload';

        return view('temporary_modules.delegate.index', [
            'pageTitle' => 'Capturas temporales',
            'pageDescription' => 'Registra informacion solicitada para tus municipios en modulos activos.',
            'topbarNotifications' => [],
            'modules' => $modules,
            'municipios' => $municipios,
            'microrregionesAsignadas' => $microrregionesAsignadas,
            'activeSection' => $activeSection,
            'activeModuleId' => $activeModuleId,
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

        $entries = $temporaryModule->entries()
            ->where('user_id', $request->user()->id)
            ->take(20)
            ->get();

        return view('temporary_modules.delegate.show', [
            'pageTitle' => $temporaryModule->name,
            'pageDescription' => 'Captura de informacion para este modulo temporal.',
            'topbarNotifications' => [],
            'temporaryModule' => $temporaryModule,
            'fields' => $temporaryModule->fields,
            'municipios' => $municipios,
            'microrregionId' => $microrregionId,
            'microrregionesAsignadas' => $microrregionesAsignadas,
            'entries' => $entries,
            'fieldTypes' => self::FIELD_TYPES,
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
                ->where('user_id', $request->user()->id)
                ->first();

            abort_unless($existingEntry !== null, 403);
        }

        $rules = [];
        $attributes = [];

        foreach ($temporaryModule->fields as $field) {
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

        foreach ($temporaryModule->fields as $field) {
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

    public function exportExcel(Request $request, int $module)
    {
        $mode = (string) $request->query('mode', 'single');
        if (!in_array($mode, ['single', 'mr'], true)) {
            $mode = 'single';
        }

        // Enviar la generación del archivo a segundo plano mediante un Job
        \App\Jobs\GenerateTemporaryModuleExcelJob::dispatch($module, $mode, $request->user()->id);

        return redirect()->back()->with('status', 'La generación del archivo Excel se ha enviado a segundo plano. Te notificaremos cuando esté listo para descargar.');
    }

    public function downloadExport(Request $request, string $file): BinaryFileResponse
    {
        abort_unless($request->user()->can('Modulos-Temporales-Admin'), 403);

        $file = trim($file);
        abort_unless($file !== '' && preg_match('/\A[A-Za-z0-9_\-]+\.xlsx\z/', $file) === 1, 404);

        $path = storage_path('app/public/temporary-exports/'.$file);
        abort_unless(is_file($path), 404);

        return response()->download($path, $file);
    }

    public function previewEntryFile(Request $request, int $entry, string $fieldKey)
    {
        $entryModel = TemporaryModuleEntry::query()->with('module')->findOrFail($entry);

        if (!$request->user()->can('Modulos-Temporales-Admin')) {
            abort_unless((int) $entryModel->user_id === (int) $request->user()->id, 403);
            abort_unless($this->accessService->userCanAccessModule($entryModel->module, (int) $request->user()->id), 403);
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
