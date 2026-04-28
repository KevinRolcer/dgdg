<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateAgendaFichasPdfJob;
use App\Jobs\GenerateAgendaSingleFichaPdfJob;
use App\Models\Agenda;
use App\Notifications\ExcelExportPending;
use App\Services\Agenda\AgendaDirectivaCalendarService;
use App\Services\Agenda\AgendaFichasPdfBuilderService;
use App\Services\Agenda\AgendaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AgendaController extends Controller
{
    public function __construct(
        private readonly AgendaService $agendaService,
        private readonly AgendaDirectivaCalendarService $agendaDirectivaCalendar
    ) {}

    public function calendar(Request $request)
    {
        $user = $request->user();
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);
        if ($month < 1 || $month > 12) {
            $month = (int) now()->month;
        }
        if ($year < 2000 || $year > 2100) {
            $year = (int) now()->year;
        }

        $clasificacionRaw = (string) $request->query('clasificacion', '');
        $clasificacion = in_array($clasificacionRaw, ['', 'gira', 'pre_gira', 'agenda', 'personalizada'], true)
            ? $clasificacionRaw
            : '';
        $buscar = trim((string) $request->query('buscar', ''));

        $previewReturn = route('agenda.calendar', array_filter(array_merge(
            ['year' => $year, 'month' => $month],
            ['clasificacion' => $clasificacion !== '' ? $clasificacion : null, 'buscar' => $buscar !== '' ? $buscar : null]
        )));

        $partialRaw = $request->query('partial');
        $wantsPartial = $partialRaw === '1'
            || $partialRaw === 1
            || $partialRaw === 'true'
            || filter_var($partialRaw, FILTER_VALIDATE_BOOLEAN);

        $fichasOnlyRaw = $request->query('fichas_only');
        $fichasOnly = $fichasOnlyRaw === '1'
            || $fichasOnlyRaw === 1
            || $fichasOnlyRaw === 'true'
            || filter_var($fichasOnlyRaw, FILTER_VALIDATE_BOOLEAN);

        if ($wantsPartial && $fichasOnly) {
            $cards = $this->agendaDirectivaCalendar->buildFichasCardsForMonth($user, $year, $month, [
                'clasificacion' => $clasificacion,
                'buscar' => $buscar,
            ]);

            return response()
                ->view('agenda.partials.calendar-fichas-fragment', [
                    'cards' => $cards,
                    'previewReturn' => $previewReturn,
                ])
                ->header('Content-Type', 'text/html; charset=UTF-8');
        }

        $includeFichasCards = $request->boolean('fichas_cards');

        $payload = $this->agendaDirectivaCalendar->buildMonthPayload($user, $year, $month, [
            'clasificacion' => $clasificacion,
            'buscar' => $buscar,
        ], $includeFichasCards);

        $qBase = array_filter([
            'clasificacion' => $clasificacion,
            'buscar' => $buscar,
        ]);
        $prevUrl = route('agenda.calendar', array_merge($qBase, ['year' => $payload['prev']['y'], 'month' => $payload['prev']['m']]));
        $nextUrl = route('agenda.calendar', array_merge($qBase, ['year' => $payload['next']['y'], 'month' => $payload['next']['m']]));

        if ($wantsPartial) {
            return response()
                ->view('agenda.partials.calendar-month-inner', [
                    'p' => $payload,
                    'clasificacion' => $clasificacion,
                    'buscar' => $buscar,
                    'prevUrl' => $prevUrl,
                    'nextUrl' => $nextUrl,
                    'previewReturn' => $previewReturn,
                ])
                ->header('Content-Type', 'text/html; charset=UTF-8');
        }

        return view('agenda.calendar', [
            'payload' => $payload,
            'clasificacion' => $clasificacion,
            'buscar' => $buscar,
            'puedeEditarAgenda' => $this->agendaService->puedeEditarAgendaCompleta($user),
            'soloAsignaciones' => $this->agendaService->usuarioVeSoloSusAsignaciones($user),
        ]);
    }

    /**
     * Encola la generación del PDF de fichas; el archivo queda en notificaciones al terminar.
     */
    public function calendarFichasPdf(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'scope' => ['required', 'string', 'in:all,current_month,custom_months'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'custom_months_json' => ['nullable', 'string', 'max:8000'],
            'orientation' => ['required', 'string', 'in:portrait,landscape'],
            'clasificacion' => ['nullable', 'string', 'max:32'],
            'buscar' => ['nullable', 'string', 'max:500'],
            'template' => ['nullable', 'string', 'in:summary,individual,calendar'],
            'pdf_title' => ['nullable', 'string', 'max:120'],
            'pdf_subtitle' => ['nullable', 'string', 'max:180'],
            'personalizada_label' => ['nullable', 'string', 'max:80'],
        ]);

        $clasificacionRaw = (string) ($validated['clasificacion'] ?? '');
        $clasificacion = in_array($clasificacionRaw, ['', 'gira', 'pre_gira', 'agenda', 'personalizada'], true)
            ? $clasificacionRaw
            : '';
        $buscar = trim((string) ($validated['buscar'] ?? ''));

        $kindGira = $request->boolean('kind_gira');
        $kindPreGira = $request->boolean('kind_pre_gira');
        $kindAgenda = $request->boolean('kind_agenda');
        $kindPersonalizada = $request->boolean('kind_personalizada');
        if (! $kindGira && ! $kindPreGira && ! $kindAgenda && ! $kindPersonalizada) {
            return response()->json(['message' => 'Selecciona al menos un tipo: Gira, Pre-gira, Agenda o Fichas personalizadas.'], 422);
        }

        $scope = $validated['scope'];
        $template = (string) ($validated['template'] ?? 'summary');
        $year = isset($validated['year']) ? (int) $validated['year'] : null;
        $month = isset($validated['month']) ? (int) $validated['month'] : null;
        $customMonths = null;

        if ($template === 'calendar' && $scope === 'all') {
            return response()->json(['message' => 'El calendario mensual se genera por mes. Elige el mes actual o varios meses.'], 422);
        }

        if ($scope === 'current_month') {
            if ($year === null || $month === null || $year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
                return response()->json(['message' => 'No se pudo determinar el mes del calendario. Recarga e inténtalo de nuevo.'], 422);
            }
        }

        if ($scope === 'custom_months') {
            $raw = trim((string) ($validated['custom_months_json'] ?? ''));
            $decoded = json_decode($raw, true);
            if (! is_array($decoded) || $decoded === []) {
                return response()->json(['message' => 'Agrega al menos un mes a la lista.'], 422);
            }
            $seen = [];
            $customMonths = [];
            foreach ($decoded as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $y = (int) ($item[0] ?? 0);
                $m = (int) ($item[1] ?? 0);
                if ($y < 2000 || $y > 2100 || $m < 1 || $m > 12) {
                    continue;
                }
                $key = $y.'-'.$m;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $customMonths[] = [$y, $m];
            }
            if ($customMonths === []) {
                return response()->json(['message' => 'Ningún mes de la lista es válido.'], 422);
            }
            if (count($customMonths) > 60) {
                return response()->json(['message' => 'Como máximo 60 meses por exportación.'], 422);
            }
        }

        $downloadFileName = AgendaFichasPdfBuilderService::buildDownloadFileName(
            $scope,
            $year,
            $month,
            $customMonths,
            $clasificacion,
            $kindGira,
            $kindPreGira,
            $kindAgenda,
            $kindPersonalizada,
            $template,
            $buscar
        );

        $exportRequestId = (string) Str::uuid();
        $storageBaseName = 'agenda-fichas-'.$user->id.'-'.str_replace('-', '', $exportRequestId).'.pdf';

        $params = [
            'scope' => $scope,
            'year' => $year,
            'month' => $month,
            'custom_months' => $customMonths,
            'orientation' => $validated['orientation'],
            'clasificacion' => $clasificacion,
            'buscar' => $buscar,
            'kind_gira' => $kindGira,
            'kind_pre_gira' => $kindPreGira,
            'kind_agenda' => $kindAgenda,
            'kind_personalizada' => $kindPersonalizada,
            'template' => $template,
            'pdf_title' => trim((string) ($validated['pdf_title'] ?? '')),
            'pdf_subtitle' => trim((string) ($validated['pdf_subtitle'] ?? '')),
            'personalizada_label' => trim((string) ($validated['personalizada_label'] ?? '')),
        ];

        $user->notify(new ExcelExportPending($exportRequestId, 'Fichas agenda', 'pdf_fichas'));

        GenerateAgendaFichasPdfJob::dispatchAfterResponse(
            $user->id,
            $exportRequestId,
            $params,
            $storageBaseName,
            $downloadFileName
        );

        return response()->json([
            'queued' => true,
            'message' => 'Se está generando el PDF. Te avisaremos en notificaciones cuando esté listo.',
        ]);
    }

    public function downloadFichasExport(Request $request, string $file): BinaryFileResponse
    {
        $file = trim($file);
        abort_unless(preg_match('/\Aagenda-fichas-\d+-[a-f0-9]{32}\.pdf\z/i', $file) === 1, 404);
        if (preg_match('/\Aagenda-fichas-(\d+)-/i', $file, $m) !== 1) {
            abort(404);
        }
        abort_unless((int) $m[1] === (int) $request->user()->id, 403);

        $path = storage_path('app/agenda-fichas-exports'.DIRECTORY_SEPARATOR.$file);
        abort_unless(is_file($path), 404);

        $dlNamePath = $path.'.dlname';
        $downloadName = is_file($dlNamePath) ? trim((string) file_get_contents($dlNamePath)) : $file;
        if ($downloadName === '') {
            $downloadName = $file;
        }

        return response()->download($path, $downloadName);
    }

    public function index(Request $request)
    {
        $filters = [
            'clasificacion' => $request->query('clasificacion', ''),
            'buscar' => $request->query('buscar', ''),
            'fecha' => $request->query('fecha'),
            'per_page' => (int) $request->query('per_page', 20),
        ];

        $agendas = $this->agendaService->paginateAgendas($filters, $request->user());

        if ($request->boolean('fragment')) {
            return response()->view('agenda.partials.list-fragment', [
                'agendas' => $agendas,
                'puedeEditarAgenda' => $this->agendaService->puedeEditarAgendaCompleta($request->user()),
            ]);
        }

        $clasificacion = in_array($filters['clasificacion'], ['', 'gira', 'pre_gira', 'agenda', 'personalizada'], true)
            ? $filters['clasificacion']
            : '';
        $buscar = trim((string) $filters['buscar']);
        $fechaDia = $filters['fecha'];
        $perPage = min(max((int) $filters['per_page'], 5), 100);

        return view('agenda.index', [
            'agendas' => $agendas,
            'users' => $this->agendaService->usuariosEnlacesDelegados(),
            'microrregiones' => $this->agendaService->microrregionesParaFormulario(),
            'municipios' => $this->agendaService->municipiosParaFormulario(),
            'puedeAsignarModuloAgenda' => $this->agendaService->usuarioPuedeAsignarModuloAgenda($request->user()),
            'puedeEditarAgenda' => $this->agendaService->puedeEditarAgendaCompleta($request->user()),
            'soloAsignaciones' => $this->agendaService->usuarioVeSoloSusAsignaciones($request->user()),
            'clasificacion' => $clasificacion,
            'buscar' => $buscar,
            'fechaDia' => $fechaDia,
            'perPage' => $perPage,
        ]);
    }

    public function moduloEnlaces()
    {
        abort_unless($this->agendaService->usuarioPuedeAsignarModuloAgenda(auth()->user()), 403);

        return response()->json(['enlaces' => $this->agendaService->listarEnlacesParaModuloAgenda()]);
    }

    public function moduloAsignar(Request $request)
    {
        abort_unless($this->agendaService->usuarioPuedeAsignarModuloAgenda(auth()->user()), 403);

        $validated = $request->validate(['user_id' => 'required|exists:users,id']);
        $result = $this->agendaService->asignarModuloAgendaDirectiva((int) $validated['user_id']);

        if (! $result['ok']) {
            return response()->json(
                ['ok' => false, 'message' => $result['message']],
                $result['http_code'] ?? 422
            );
        }

        return response()->json(['ok' => true, 'message' => $result['message']]);
    }

    public function moduloQuitar(Request $request)
    {
        abort_unless($this->agendaService->usuarioPuedeAsignarModuloAgenda(auth()->user()), 403);

        $validated = $request->validate(['user_id' => 'required|exists:users,id']);
        $this->agendaService->quitarModuloAgendaDirectiva((int) $validated['user_id']);

        return response()->json(['ok' => true, 'message' => 'Acceso a Agenda Directiva retirado.']);
    }

    public function store(Request $request)
    {
        abort_unless($this->agendaService->puedeEditarAgendaCompleta($request->user()), 403);
        $validated = $request->validate($this->reglasAgenda());
        $this->agendaService->crear($validated, $request);

        return redirect()->route('agenda.index')->with('toast', 'Asunto creado correctamente en la agenda');
    }

    public function update(Request $request, Agenda $agenda)
    {
        abort_unless($this->agendaService->puedeEditarAgendaCompleta($request->user()), 403);
        $validated = $request->validate($this->reglasAgenda());
        $this->agendaService->actualizar($agenda, $validated, $request);

        return redirect()->route('agenda.index')->with('toast', 'Agenda actualizada correctamente');
    }

    public function destroy(Agenda $agenda)
    {
        abort_unless($this->agendaService->puedeEditarAgendaCompleta(auth()->user()), 403);
        $this->agendaService->eliminar($agenda);

        return redirect()->route('agenda.index')->with('toast', 'Entrada eliminada de la agenda');
    }

    /**
     * Vista previa de la actividad (misma idea que el detalle en Asignaciones / popover de semana).
     */
    public function show(Request $request, Agenda $agenda): View
    {
        $agenda->load(['usuariosAsignados', 'creador']);
        abort_unless($this->agendaService->puedeVerAgenda($request->user(), $agenda), 403);

        $return = $request->query('return');
        $returnUrl = is_string($return) && str_starts_with($return, url('/'))
            ? $return
            : null;

        $previewMode = trim((string) $request->query('preview', ''));
        if ($previewMode === 'ficha') {
            $fichaCard = $this->agendaDirectivaCalendar->buildSingleFichaCardForAgenda($agenda);

            return view('agenda.preview-ficha', [
                'agenda' => $agenda,
                'card' => $fichaCard,
                'returnUrl' => $returnUrl,
                'queueUrl' => route('agenda.ficha.queue', ['agenda' => $agenda->id]),
            ]);
        }

        return view('agenda.preview', [
            'agenda' => $agenda,
            'returnUrl' => $returnUrl,
            'puedeEditarAgenda' => $this->agendaService->puedeEditarAgendaCompleta($request->user()),
        ]);
    }

    public function downloadSingleFichaPdf(Request $request, Agenda $agenda, AgendaFichasPdfBuilderService $builder): \Symfony\Component\HttpFoundation\Response
    {
        abort_unless($this->agendaService->puedeVerAgenda($request->user(), $agenda), 403);

        $cacheDir = storage_path('app/agenda-ficha-single-cache');
        File::ensureDirectoryExists($cacheDir);

        $templateVersion = (string) (@filemtime(resource_path('views/agenda/pdf/ficha-individual.blade.php')) ?: 0);
        $versionBase = $agenda->updated_at ? (string) $agenda->updated_at->timestamp : (string) now()->timestamp;
        $version = $versionBase.'-'.$templateVersion;
        $cacheFile = 'agenda-ficha-'.$agenda->id.'-'.$version.'.pdf';
        $cachePath = $cacheDir.DIRECTORY_SEPARATOR.$cacheFile;

        $safe = (string) preg_replace('/[^A-Za-z0-9\-]+/', '-', (string) $agenda->id.'-'.$agenda->asunto);
        $safe = trim($safe, '-');
        $downloadName = 'ficha-'.($safe === '' ? 'agenda-'.$agenda->id : $safe).'.pdf';

        if (! is_file($cachePath)) {
            $binary = $builder->renderSingleFichaPdfBinary($request->user(), $agenda);
            File::put($cachePath, $binary);
        }

        return response()->download($cachePath, $downloadName, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    public function queueSingleFichaPdf(Request $request, Agenda $agenda): \Illuminate\Http\JsonResponse|RedirectResponse
    {
        abort_unless($this->agendaService->puedeVerAgenda($request->user(), $agenda), 403);

        $user = $request->user();
        $safe = (string) preg_replace('/[^A-Za-z0-9\-]+/', '-', (string) $agenda->id.'-'.$agenda->asunto);
        $safe = trim($safe, '-');
        if ($safe === '') {
            $safe = 'agenda-'.$agenda->id;
        }

        $downloadFileName = 'ficha-'.$safe.'.pdf';
        $exportRequestId = (string) Str::uuid();
        $storageBaseName = 'agenda-fichas-'.$user->id.'-'.str_replace('-', '', $exportRequestId).'.pdf';

        $user->notify(new ExcelExportPending($exportRequestId, 'Ficha agenda', 'pdf_fichas'));

        GenerateAgendaSingleFichaPdfJob::dispatchAfterResponse(
            (int) $user->id,
            (int) $agenda->id,
            $exportRequestId,
            $storageBaseName,
            $downloadFileName
        );

        $message = 'Se está generando la ficha. Te avisaremos en notificaciones cuando esté lista.';
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'queued' => true,
                'message' => $message,
            ]);
        }

        return back()->with('toast', $message);
    }

    /**
     * La edición se hace con el modal en el listado; redirige allí para buscar el asunto.
     */
    public function edit(Request $request, Agenda $agenda): RedirectResponse
    {
        abort_unless($this->agendaService->puedeEditarAgendaCompleta($request->user()), 403);

        return redirect()
            ->route('agenda.index', ['buscar' => $agenda->asunto])
            ->with('toast', 'Busca el asunto en la tabla y usa el botón de editar (lápiz).');
    }

    /**
     * @return array<string, mixed>
     */
    private function reglasAgenda(): array
    {
        return [
            'asunto' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'tipo' => 'nullable|string|in:asunto,gira,personalizado',
            'subtipo' => 'nullable|string|in:gira,pre-gira',
            'ficha_titulo' => 'nullable|string|max:80|required_if:tipo,personalizado',
            'ficha_fondo' => 'nullable|string|in:tlaloc_a_beige,tlaloc_a_rojo,tlaloc_a_verde,beige,blanco,rojo,verde|required_if:tipo,personalizado',
            'microrregion' => 'nullable|string|max:255',
            'municipio' => 'nullable|string|max:255',
            'lugar' => 'nullable|string',
            'semaforo' => 'nullable|string|in:rojo,amarillo,verde',
            'seguimiento' => 'nullable|string',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'habilitar_hora' => 'boolean',
            'hora' => 'nullable|required_if:habilitar_hora,1',
            'repite' => 'boolean',
            'dias_repeticion' => 'nullable|array',
            'recordatorio_minutos' => 'nullable|integer|min:30',
            'direcciones_adicionales' => 'nullable|array',
            'direcciones_adicionales.*' => 'nullable|string|max:25|regex:/^[0-9\s\-+()]*$/',
            'usuarios_asignados' => 'nullable|array',
            'usuarios_asignados.*' => 'exists:users,id',
        ];
    }
}
