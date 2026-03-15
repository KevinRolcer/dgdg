@extends('settings.layout')

@section('settings_panel')
    <link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ @filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/modules/settings.css') }}?v={{ @filemtime(public_path('assets/css/modules/settings.css')) ?: time() }}">

    @if (session('distribuir_result'))
        @php
            $hasDistribuirErrors = session('distribuir_errors');
            $distribuirStatus = session('status');
        @endphp
        <div class="inline-alert {{ $hasDistribuirErrors ? 'inline-alert-error' : 'inline-alert-success' }} settings-alert settings-alert-with-log" role="alert">
            @if ($hasDistribuirErrors)
                <span>
                    @foreach ((array) session('distribuir_errors') as $err)
                        <span>{{ $err }}</span>@if (!$loop->last)<br>@endif
                    @endforeach
                </span>
            @else
                <span>{{ $distribuirStatus }}</span>
            @endif
            <button type="button" class="settings-log-btn" id="btnAbrirLogDistribucion" aria-haspopup="dialog" aria-controls="modalLogDistribucion">
                <i class="fa-solid fa-list-ul" aria-hidden="true"></i> Ver detalle / Logs
            </button>
        </div>
    @elseif (session('status'))
        <div class="inline-alert inline-alert-success settings-alert" role="alert">{{ session('status') }}</div>
    @endif

    @if (session('distribuir_errors') && !session('distribuir_result'))
        <div class="inline-alert inline-alert-error settings-alert" role="alert">
            @foreach ((array) session('distribuir_errors') as $err)
                <div>{{ $err }}</div>
            @endforeach
        </div>
    @endif

    {{-- Microrregiones: distribuir municipios desde Excel --}}
    <section class="settings-panel-block settings-micro-block">
        <div class="settings-micro-heading-row">
            <h2 class="settings-panel-heading">Microrregiones</h2>
            <button type="button" class="settings-help-btn" id="btnMicroHelp" aria-label="Ayuda" aria-expanded="false" aria-controls="microHelpPopover" title="Ver ayuda">
                <i class="fa-solid fa-circle-question" aria-hidden="true"></i>
            </button>
            <div class="settings-help-popover" id="microHelpPopover" role="tooltip" aria-hidden="true">
                <p class="settings-help-popover-text">Distribuir municipios con base de datos según un Excel. El archivo debe tener columnas <strong>Microrregión</strong>, <strong>NO</strong> (opcional) y <strong>Municipio</strong> o <strong>NOMBRE DE MUNICIPIO</strong> (se buscan sin importar mayúsculas ni acentos). Los datos van agrupados por microrregión; se actualiza la asignación de cada municipio a su microrregión.</p>
                <button type="button" class="settings-help-popover-close" aria-label="Cerrar">×</button>
            </div>
        </div>

        <form method="POST" action="{{ route('settings.microrregiones.distribuir-excel') }}" enctype="multipart/form-data" class="settings-micro-form">
            @csrf
            <div class="settings-micro-row">
                <div class="settings-micro-upload-zone" id="microUploadZone">
                    <input type="file" name="archivo_excel" accept=".xlsx,.xls" required class="settings-micro-file-input" id="distribuir_excel_file" aria-describedby="micro-file-hint">
                    <label for="distribuir_excel_file" class="settings-micro-upload-label">
                        <i class="fa-solid fa-file-excel" aria-hidden="true"></i>
                        <span class="settings-micro-upload-text">Arrastra o clic para elegir</span>
                        <span class="settings-micro-file-name" id="microFileName">—</span>
                        <span class="settings-micro-hint" id="micro-file-hint">(.xlsx, .xls · máx. 20 MB)</span>
                    </label>
                </div>
                <div class="settings-micro-actions">
                    <button type="submit" class="tm-btn tm-btn-primary settings-micro-btn-primary">
                        <i class="fa-solid fa-upload" aria-hidden="true"></i> Distribuir
                    </button>
                    <button type="button" class="tm-btn tm-btn-outline settings-micro-btn-secondary" id="btnAbrirListaMicrorregiones" aria-haspopup="dialog" aria-controls="modalListaMicrorregiones">
                        <i class="fa-solid fa-list" aria-hidden="true"></i> Ver lista
                    </button>
                </div>
            </div>
        </form>
    </section>

    <section class="settings-panel-block settings-migrate-block">
        <div class="settings-micro-heading-row">
            <h2 class="settings-panel-heading">Migración de imágenes</h2>
            <button type="button" class="settings-help-btn" id="btnMigrateHelp" aria-label="Ayuda" aria-expanded="false" aria-controls="migrateHelpPopover" title="Ver ayuda">
                <i class="fa-solid fa-circle-question" aria-hidden="true"></i>
            </button>
            <div class="settings-help-popover" id="migrateHelpPopover" role="tooltip" aria-hidden="true">
                <p class="settings-help-popover-text">Copia desde almacenamiento local hacia la ruta privada compartida (Mesas de Paz y módulos temporales).</p>
                <button type="button" class="settings-help-popover-close" aria-label="Cerrar">×</button>
            </div>
        </div>

        <form method="POST" action="{{ route('settings.images.migrate') }}" class="settings-migrate-form">
            @csrf
            <div class="settings-migrate-row">
                <label class="settings-migrate-check">
                    <input type="checkbox" name="delete_originals" value="1">
                    <span>Eliminar originales tras copiar (solo cuando hayas validado).</span>
                </label>
                <button type="submit" class="tm-btn tm-btn-primary settings-migrate-btn">
                    <i class="fa-solid fa-folder-arrow-up" aria-hidden="true"></i> Ejecutar migración
                </button>
            </div>
        </form>

        @if (is_array($migrationReport ?? null))
            <div class="settings-report">
                <h3 class="settings-report-title">Última ejecución</h3>
                <div class="settings-report-grid">
                    <span><strong>Copiados:</strong> {{ $migrationReport['files_copied'] ?? 0 }}</span>
                    <span><strong>Omitidos:</strong> {{ $migrationReport['files_skipped_existing'] ?? 0 }}</span>
                    <span><strong>Errores:</strong> {{ $migrationReport['files_failed'] ?? 0 }}</span>
                </div>
                @php $errors = $migrationReport['errors'] ?? []; @endphp
                @if (is_array($errors) && count($errors) > 0)
                    <div class="inline-alert inline-alert-error settings-alert" role="alert">
                        <ul class="settings-report-errors">
                            @foreach (array_slice($errors, 0, 15) as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif
    </section>

    {{-- Modal: logs de distribución Excel --}}
    @if (session('distribuir_result'))
    @php $dr = session('distribuir_result'); @endphp
    <div class="tm-modal settings-modal-log" id="modalLogDistribucion" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="modalLogDistribucionTitle">
        <div class="tm-modal-backdrop" data-close-log-modal></div>
        <div class="tm-modal-dialog settings-modal-dialog-log" style="max-width: 640px;">
            <div class="tm-modal-head">
                <h3 id="modalLogDistribucionTitle">Detalle de distribución desde Excel</h3>
                <button type="button" class="tm-modal-close" data-close-log-modal aria-label="Cerrar">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <div class="tm-modal-body tm-modal-body-scroll settings-log-body">
                <dl class="settings-log-dl">
                    <dt>Municipios actualizados</dt>
                    <dd>{{ $dr['updated'] ?? 0 }}</dd>

                    @if (!empty($dr['columnas']['microrregion']) || !empty($dr['columnas']['municipio']))
                    <dt>Columnas usadas</dt>
                    <dd>
                        Microrregión: <strong>{{ $dr['columnas']['microrregion'] ?? '—' }}</strong><br>
                        Municipio: <strong>{{ $dr['columnas']['municipio'] ?? '—' }}</strong>
                    </dd>
                    @endif

                    @if (!empty($dr['info']))
                    <dt>Información</dt>
                    <dd class="settings-log-info">{{ $dr['info'] }}</dd>
                    @endif

                    @if (!empty($dr['errors']))
                    <dt>Errores</dt>
                    <dd class="settings-log-errors">
                        <ul>
                            @foreach ($dr['errors'] as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </dd>
                    @endif

                    @if (!empty($dr['missing_microrregiones']))
                    <dt>Microrregiones no encontradas en BD ({{ count($dr['missing_microrregiones']) }})</dt>
                    <dd class="settings-log-list-wrap">
                        <ul class="settings-log-list">
                            @foreach ($dr['missing_microrregiones'] as $mr)
                                <li>{{ $mr }}</li>
                            @endforeach
                        </ul>
                    </dd>
                    @endif

                    @if (!empty($dr['missing_municipios']))
                    <dt>Municipios no encontrados en BD ({{ count($dr['missing_municipios']) }})</dt>
                    <dd class="settings-log-list-wrap">
                        <ul class="settings-log-list">
                            @foreach ($dr['missing_municipios'] as $m)
                                <li><strong>{{ $m['municipio'] }}</strong> — microrregión en Excel: {{ $m['microrregion'] ?? '—' }}</li>
                            @endforeach
                        </ul>
                    </dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal: lista de microrregiones y municipios --}}
    <div class="tm-modal settings-modal-microrregiones" id="modalListaMicrorregiones" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="modalListaMicrorregionesTitle">
        <div class="tm-modal-backdrop" data-close-microrregiones-modal></div>
        <div class="tm-modal-dialog settings-modal-dialog-list" style="max-width: 560px;">
            <div class="tm-modal-head">
                <h3 id="modalListaMicrorregionesTitle">Microrregiones y municipios</h3>
                <button type="button" class="tm-modal-close" data-close-microrregiones-modal aria-label="Cerrar">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <div class="tm-modal-body tm-modal-body-scroll">
                @if (isset($microrregionesConMunicipios) && $microrregionesConMunicipios->isNotEmpty())
                    <ul class="settings-microrregiones-list">
                        @foreach ($microrregionesConMunicipios as $micro)
                            <li>
                                <strong class="settings-microrregiones-nombre">MR {{ $micro->microrregion }} — {{ $micro->cabecera ?? 'Sin cabecera' }}</strong>
                                <ul class="settings-municipios-sublist">
                                    @foreach ($micro->municipios as $mun)
                                        <li>{{ $mun->municipio }}</li>
                                    @endforeach
                                </ul>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="settings-microrregiones-empty">No hay microrregiones cargadas.</p>
                @endif
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('btnAbrirListaMicrorregiones');
        var modal = document.getElementById('modalListaMicrorregiones');
        if (!btn || !modal) return;
        function openModal() {
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
        }
        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        }
        btn.addEventListener('click', openModal);
        modal.querySelectorAll('[data-close-microrregiones-modal]').forEach(function (el) {
            el.addEventListener('click', closeModal);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
        });

        var btnLog = document.getElementById('btnAbrirLogDistribucion');
        var modalLog = document.getElementById('modalLogDistribucion');
        if (btnLog && modalLog) {
            btnLog.addEventListener('click', function () {
                modalLog.classList.add('is-open');
                modalLog.setAttribute('aria-hidden', 'false');
            });
            modalLog.querySelectorAll('[data-close-log-modal]').forEach(function (el) {
                el.addEventListener('click', function () {
                    modalLog.classList.remove('is-open');
                    modalLog.setAttribute('aria-hidden', 'true');
                });
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modalLog.classList.contains('is-open')) {
                    modalLog.classList.remove('is-open');
                    modalLog.setAttribute('aria-hidden', 'true');
                }
            });
        }

        var btnMicroHelp = document.getElementById('btnMicroHelp');
        var microHelpPopover = document.getElementById('microHelpPopover');
        if (btnMicroHelp && microHelpPopover) {
            btnMicroHelp.addEventListener('click', function (e) {
                e.preventDefault();
                var isOpen = microHelpPopover.getAttribute('aria-hidden') !== 'true';
                microHelpPopover.setAttribute('aria-hidden', isOpen ? 'true' : 'false');
                btnMicroHelp.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
                microHelpPopover.classList.toggle('is-open', !isOpen);
            });
            microHelpPopover.querySelector('.settings-help-popover-close').addEventListener('click', function () {
                microHelpPopover.setAttribute('aria-hidden', 'true');
                btnMicroHelp.setAttribute('aria-expanded', 'false');
                microHelpPopover.classList.remove('is-open');
            });
        }
        var btnMigrateHelp = document.getElementById('btnMigrateHelp');
        var migrateHelpPopover = document.getElementById('migrateHelpPopover');
        if (btnMigrateHelp && migrateHelpPopover) {
            btnMigrateHelp.addEventListener('click', function (e) {
                e.preventDefault();
                var isOpen = migrateHelpPopover.getAttribute('aria-hidden') !== 'true';
                migrateHelpPopover.setAttribute('aria-hidden', isOpen ? 'true' : 'false');
                btnMigrateHelp.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
                migrateHelpPopover.classList.toggle('is-open', !isOpen);
            });
            migrateHelpPopover.querySelector('.settings-help-popover-close').addEventListener('click', function () {
                migrateHelpPopover.setAttribute('aria-hidden', 'true');
                btnMigrateHelp.setAttribute('aria-expanded', 'false');
                migrateHelpPopover.classList.remove('is-open');
            });
        }

        var fileInput = document.getElementById('distribuir_excel_file');
        var fileNameEl = document.getElementById('microFileName');
        var uploadZone = document.getElementById('microUploadZone');
        if (fileInput && fileNameEl && uploadZone) {
            fileInput.addEventListener('change', function () {
                fileNameEl.textContent = this.files && this.files[0] ? this.files[0].name : '—';
                uploadZone.classList.toggle('has-file', !!(this.files && this.files[0]));
            });
            uploadZone.addEventListener('dragover', function (e) { e.preventDefault(); uploadZone.classList.add('is-dragover'); });
            uploadZone.addEventListener('dragleave', function () { uploadZone.classList.remove('is-dragover'); });
            uploadZone.addEventListener('drop', function (e) {
                e.preventDefault();
                uploadZone.classList.remove('is-dragover');
                if (e.dataTransfer.files.length) {
                    fileInput.files = e.dataTransfer.files;
                    fileNameEl.textContent = e.dataTransfer.files[0].name;
                    uploadZone.classList.add('has-file');
                }
            });
        }
    });
    </script>
@endsection
