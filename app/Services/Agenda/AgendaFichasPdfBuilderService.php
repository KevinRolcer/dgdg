<?php

namespace App\Services\Agenda;

use App\Models\Agenda;
use App\Models\User;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Illuminate\Support\Facades\File;

/**
 * Genera el PDF de fichas del calendario directiva (Dompdf).
 */
class AgendaFichasPdfBuilderService
{
    public function __construct(
        private readonly AgendaDirectivaCalendarService $agendaDirectivaCalendar
    ) {}

    /**
     * @param  array{
     *   scope: string,
     *   year: int|null,
     *   month: int|null,
     *   custom_months: list<array{0:int,1:int}>|null,
     *   orientation: string,
     *   clasificacion: string,
     *   buscar: string,
     *   kind_gira: bool,
     *   kind_pre_gira: bool,
     *   kind_agenda: bool
     * }  $params
     */
    public function renderPdfToFile(User $user, array $params, string $absolutePath): void
    {
        $params = $this->normalizeQueuedFichasPdfParams($params);

        File::ensureDirectoryExists(storage_path('fonts'));
        File::ensureDirectoryExists(dirname($absolutePath));

        $filters = [
            'clasificacion' => $params['clasificacion'],
            'buscar' => $params['buscar'],
        ];

        $cards = $this->agendaDirectivaCalendar->buildCardsForFichasPdf(
            $user,
            $filters,
            $params['scope'],
            $params['year'],
            $params['month'],
            $params['custom_months']
        );

        $allowedKinds = [];
        if ($params['kind_gira']) {
            $allowedKinds[] = 'gira';
        }
        if ($params['kind_pre_gira']) {
            $allowedKinds[] = 'pre_gira';
        }
        if ($params['kind_agenda']) {
            $allowedKinds[] = 'agenda';
        }

        $cards = array_values(array_filter($cards, function (array $c) use ($allowedKinds): bool {
            $k = $c['kind'] ?? 'agenda';

            return in_array($k, $allowedKinds, true);
        }));

        $template = $params['template'] ?? 'summary';
        $view = $template === 'individual' ? 'agenda.pdf.ficha-individual' : 'agenda.pdf.fichas-calendario';

        // Individual cards are always portrait, summary can be either
        $orientation = $template === 'individual' ? 'portrait' : ($params['orientation'] === 'landscape' ? 'landscape' : 'portrait');
        $cols = ($template === 'individual') ? 1 : ($orientation === 'landscape' ? 3 : 2);

        $rows = [];
        if ($template === 'individual') {
            // Un card por "fila" (página)
            foreach ($cards as $card) {
                $rows[] = [$card];
            }
        } else {
            for ($i = 0, $n = count($cards); $i < $n; $i += $cols) {
                $rows[] = array_slice($cards, $i, $cols);
            }
        }

        $monthLabel = $this->fichasPdfPeriodLabel(
            $params['scope'],
            $params['year'],
            $params['month'],
            $params['custom_months']
        );
        $allKindsSelected = $params['kind_gira'] && $params['kind_pre_gira'] && $params['kind_agenda'];
        $filtersNote = $this->fichasPdfFiltersNote(
            $params['clasificacion'],
            $params['buscar'],
            $allKindsSelected
        );
        $kindsNote = $this->fichasPdfSelectedKindsLabel(
            $params['kind_gira'],
            $params['kind_pre_gira'],
            $params['kind_agenda']
        );
        $documentTitle = $this->fichasPdfDocumentTitle($monthLabel, $kindsNote);

        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = app('dompdf.wrapper');
        $pdfFontFamily = $this->registerDompdfAgendaFichasFonts($pdf->getDomPDF());

        $binary = $pdf
            ->loadView($view, [
                'documentTitle' => $documentTitle,
                'rows' => $rows,
                'cols' => $cols,
                'filtersNote' => $filtersNote,
                'pdfFontFamily' => $pdfFontFamily,
            ])
            ->setPaper('a4', $orientation)
            ->output();

        File::put($absolutePath, $binary);
    }

    public function renderSingleFichaPdfBinary(User $user, Agenda $agenda): string
    {
        $card = $this->agendaDirectivaCalendar->buildSingleFichaCardForAgenda($agenda);
        $rows = [[$card]];
        $documentTitle = 'Ficha de agenda - '.$card['title'];

        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = app('dompdf.wrapper');
        $pdf->getDomPDF()->setBasePath(public_path());
        
        // Registrar fuentes institucionales (Gilroy, etc.) para que coincida con el export masivo
        $pdfFontFamily = $this->registerDompdfAgendaFichasFonts($pdf->getDomPDF());

        return $pdf
            ->loadView('agenda.pdf.ficha-individual', [
                'documentTitle' => $documentTitle,
                'rows' => $rows,
                'pdfFontFamily' => $pdfFontFamily,
            ])
            ->setPaper('a4', 'portrait')
            ->output();
    }

    public function renderSingleFichaToFile(User $user, Agenda $agenda, string $absolutePath): void
    {
        $binary = $this->renderSingleFichaPdfBinary($user, $agenda);
        File::put($absolutePath, $binary);
    }

    /**
     * Cola serializa booleans de forma inconsistente; normalizar antes de filtrar tipos de ficha.
     *
     * @param  array<string, mixed>  $params
     * @return array{
     *   scope: string,
     *   year: int|null,
     *   month: int|null,
     *   custom_months: list<array{0:int,1:int}>|null,
     *   orientation: string,
     *   clasificacion: string,
     *   buscar: string,
     *   kind_gira: bool,
     *   kind_pre_gira: bool,
     *   kind_agenda: bool
     * }
     */
    private function normalizeQueuedFichasPdfParams(array $params): array
    {
        $clas = (string) ($params['clasificacion'] ?? '');
        if (! in_array($clas, ['', 'gira', 'pre_gira', 'agenda'], true)) {
            $clas = '';
        }

        return [
            'scope' => (string) ($params['scope'] ?? 'current_month'),
            'year' => isset($params['year']) ? (int) $params['year'] : null,
            'month' => isset($params['month']) ? (int) $params['month'] : null,
            'custom_months' => isset($params['custom_months']) && is_array($params['custom_months']) ? $params['custom_months'] : null,
            'orientation' => (($params['orientation'] ?? '') === 'landscape') ? 'landscape' : 'portrait',
            'clasificacion' => $clas,
            'buscar' => trim((string) ($params['buscar'] ?? '')),
            'kind_gira' => filter_var($params['kind_gira'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'kind_pre_gira' => filter_var($params['kind_pre_gira'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'kind_agenda' => filter_var($params['kind_agenda'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'template' => (string) ($params['template'] ?? 'summary'),
        ];
    }

    /**
     * @param  list<array{0: int, 1: int}>|null  $customMonths
     */
    private function fichasPdfPeriodLabel(string $scope, ?int $year, ?int $month, ?array $customMonths): string
    {
        $tz = config('app.timezone', 'UTC');

        if ($scope === 'all') {
            return 'Todos los periodos';
        }

        if ($scope === 'current_month' && $year !== null && $month !== null) {
            $anchor = Carbon::create($year, $month, 1, 0, 0, 0, $tz)->locale('es');

            return mb_convert_case($anchor->translatedFormat('F Y'), MB_CASE_TITLE, 'UTF-8');
        }

        if ($scope === 'custom_months' && is_array($customMonths) && $customMonths !== []) {
            $labels = [];
            foreach ($customMonths as $pair) {
                $y = (int) ($pair[0] ?? 0);
                $m = (int) ($pair[1] ?? 0);
                if ($y < 2000 || $m < 1 || $m > 12) {
                    continue;
                }
                $labels[] = mb_convert_case(
                    Carbon::create($y, $m, 1, 0, 0, 0, $tz)->locale('es')->translatedFormat('F Y'),
                    MB_CASE_TITLE,
                    'UTF-8'
                );
            }

            return $labels === [] ? 'Meses personalizados' : 'Meses: '.implode(' · ', $labels);
        }

        return '';
    }

    private function registerDompdfAgendaFichasFonts(Dompdf $dompdf): string
    {
        $fm = $dompdf->getFontMetrics();
        $gilroyOtfDir = resource_path('fonts/Fuente Gilroy');
        $agendaPdfDir = public_path('fonts/agenda-pdf');

        $gilroyWeights = [
            400 => 'Gilroy-Regular',
            500 => 'Gilroy-Medium',
            600 => 'Gilroy-SemiBold',
            700 => 'Gilroy-Bold',
            800 => 'Gilroy-ExtraBold',
        ];

        $gilroyTtfFiles = [];
        foreach ($gilroyWeights as $weight => $base) {
            $gilroyTtfFiles[$weight] = $base.'.ttf';
        }
        // En preview web solo existen pesos 400..800; 900 se resuelve como 800.
        // Registrar primero TTF mantiene el mismo trazo en vista previa y PDF.
        $gilroyTtfFiles[900] = 'Gilroy-ExtraBold.ttf';
        if ($this->registerDompdfFontFamily($fm, 'Gilroy', $agendaPdfDir, $gilroyTtfFiles)) {
            return 'Gilroy';
        }

        if ($this->registerDompdfFontFamily($fm, 'Gilroy', $gilroyOtfDir, [
            400 => 'Gilroy-Regular.otf',
            500 => 'Gilroy-Medium.otf',
            600 => 'Gilroy-SemiBold.otf',
            700 => 'Gilroy-Bold.otf',
            800 => 'Gilroy-ExtraBold.otf',
            // Mantener 900 en ExtraBold para que coincida con preview (sin Black).
            900 => 'Gilroy-ExtraBold.otf',
        ])) {
            return 'Gilroy';
        }

        if ($this->registerDompdfFontFamily($fm, 'Montserrat', $agendaPdfDir, [
            400 => 'Montserrat-Regular.ttf',
            500 => 'Montserrat-Medium.ttf',
            600 => 'Montserrat-SemiBold.ttf',
            700 => 'Montserrat-Bold.ttf',
            800 => 'Montserrat-ExtraBold.ttf',
        ])) {
            return 'Montserrat';
        }

        return 'DejaVu Sans';
    }

    /**
     * @param  array<int, string>  $weightToFile
     */
    private function registerDompdfFontFamily(FontMetrics $fm, string $family, string $directory, array $weightToFile): bool
    {
        foreach ($weightToFile as $weight => $filename) {
            $path = realpath($directory.DIRECTORY_SEPARATOR.$filename);
            if ($path === false || ! is_readable($path)) {
                return false;
            }

            $uri = $this->absoluteLocalPathForDompdf($path);
            $ok = $fm->registerFont([
                'family' => $family,
                'weight' => $weight,
                'style' => 'normal',
            ], $uri);
            if (! $ok) {
                return false;
            }
        }

        return true;
    }

    /**
     * Nombre sugerido para descarga (.pdf), ASCII, coherente con periodo y filtros.
     *
     * @param  list<array{0:int,1:int}>|null  $customMonths
     */
    public static function buildDownloadFileName(
        string $scope,
        ?int $year,
        ?int $month,
        ?array $customMonths,
        string $clasificacion,
        bool $kindGira,
        bool $kindPreGira,
        bool $kindAgenda,
        string $template = 'summary',
        string $buscar = '',
    ): string {
        $slugPeriod = match ($scope) {
            'all' => 'todos',
            'current_month' => sprintf('%d-%02d', (int) ($year ?? 0), (int) ($month ?? 0)),
            default => self::slugCustomMonthsPeriod(is_array($customMonths) ? $customMonths : []),
        };

        $slugList = match ($clasificacion) {
            'gira' => 'list-giras',
            'pre_gira' => 'list-pregiras',
            'agenda' => 'list-agenda',
            default => 'list-todos',
        };

        $slugKinds = self::slugKindsForFileName($kindGira, $kindPreGira, $kindAgenda);

        $parts = ['fichas', $template, $slugPeriod, $slugList, $slugKinds];
        if (trim($buscar) !== '') {
            $parts[] = 'con-busqueda';
        }

        $name = implode('-', $parts);
        $name = preg_replace('/-+/', '-', $name);

        return $name.'.pdf';
    }

    /**
     * @param  list<array{0:int,1:int}>  $pairs
     */
    private static function slugCustomMonthsPeriod(array $pairs): string
    {
        if ($pairs === []) {
            return 'meses-personalizados';
        }
        $sorted = $pairs;
        usort($sorted, function (array $a, array $b): int {
            $ka = ((int) ($a[0] ?? 0)) * 100 + (int) ($a[1] ?? 0);
            $kb = ((int) ($b[0] ?? 0)) * 100 + (int) ($b[1] ?? 0);

            return $ka <=> $kb;
        });
        $first = $sorted[0];
        $last = $sorted[count($sorted) - 1];
        $n = count($sorted);
        $from = sprintf('%04d%02d', (int) ($first[0] ?? 0), (int) ($first[1] ?? 0));
        $to = sprintf('%04d%02d', (int) ($last[0] ?? 0), (int) ($last[1] ?? 0));

        if ($from === $to) {
            return 'mes-'.$from;
        }

        return $n.'meses-'.$from.'-a-'.$to;
    }

    private static function slugKindsForFileName(bool $kindGira, bool $kindPreGira, bool $kindAgenda): string
    {
        $bits = [];
        if ($kindGira) {
            $bits[] = 'gira';
        }
        if ($kindPreGira) {
            $bits[] = 'pregira';
        }
        if ($kindAgenda) {
            $bits[] = 'agenda';
        }

        return $bits === [] ? 'inc-ninguno' : 'inc-'.implode('-', $bits);
    }

    private function fichasPdfDocumentTitle(string $periodLine, string $kindsLine): string
    {
        $parts = ['Fichas de agenda'];
        if ($periodLine !== '') {
            $parts[] = $periodLine;
        }
        if ($kindsLine !== '') {
            $parts[] = $kindsLine;
        }

        return implode(' — ', $parts);
    }

    /**
     * Sufijo corto para el título del PDF (sin texto cuando están los tres tipos).
     */
    private function fichasPdfSelectedKindsLabel(bool $kindGira, bool $kindPreGira, bool $kindAgenda): string
    {
        if ($kindGira && $kindPreGira && $kindAgenda) {
            return '';
        }

        $labels = [];
        if ($kindGira) {
            $labels[] = 'Giras';
        }
        if ($kindPreGira) {
            $labels[] = 'Pre-giras';
        }
        if ($kindAgenda) {
            $labels[] = 'Agenda';
        }

        if (count($labels) === 1) {
            return $labels[0];
        }

        if (count($labels) === 2) {
            return $labels[0].' y '.$labels[1];
        }

        return '';
    }

    private function absoluteLocalPathForDompdf(string $absolutePath): string
    {
        $normalized = str_replace('\\', '/', $absolutePath);

        return 'file://'.$normalized;
    }

    private function fichasPdfFiltersNote(string $clasificacion, string $buscar, bool $allKindsSelected): string
    {
        $parts = [];
        if ($clasificacion !== '') {
            $parts[] = 'Listado filtrado: '.match ($clasificacion) {
                'gira' => 'solo giras',
                'pre_gira' => 'solo pre-giras',
                'agenda' => 'solo agenda (asuntos)',
                default => $clasificacion,
            };
        } elseif (! $allKindsSelected) {
            $parts[] = 'Listado: todos los tipos de evento';
        }
        if ($buscar !== '') {
            $parts[] = 'Búsqueda: «'.$buscar.'»';
        }

        return implode(' · ', $parts);
    }
}
