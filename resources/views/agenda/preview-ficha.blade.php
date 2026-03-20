@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/temporary-modules.css') }}?v={{ @filemtime(public_path('assets/css/modules/temporary-modules.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/modules/agenda.css') }}?v={{ @filemtime(public_path('assets/css/modules/agenda.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/modules/agenda-preview-ficha.css') }}?v={{ @filemtime(public_path('assets/css/modules/agenda-preview-ficha.css')) ?: time() }}">
@endpush

@php
    $pageTitle = 'Ficha: ' . \Illuminate\Support\Str::limit($agenda->asunto, 48);
    $hidePageHeader = true;
    $baseBackUrl = $returnUrl ?? route('agenda.calendar');
    $backUrl = str_contains($baseBackUrl, 'vista=')
        ? $baseBackUrl
        : $baseBackUrl . (str_contains($baseBackUrl, '?') ? '&' : '?') . 'vista=fichas';

    $kind = (string) ($card['kind'] ?? 'agenda');
    $kindLabel = match ($kind) {
        'pre-gira' => 'Pre-gira',
        'pre_gira' => 'Pre-gira',
        'gira' => 'Gira',
        default => 'Agenda',
    };
    $logoVersion = ($kind === 'pre_gira') ? '1' : '2';
    $logoFile = "Gobierno de Puebla_{$logoVersion}-Versión vertical.png";

    $textBulk = mb_strlen(trim((string) ($card['title'] ?? '')))
                + mb_strlen(trim((string) ($card['lugar'] ?? '')))
                + mb_strlen(trim((string) ($card['descripcion'] ?? '')));
    $sparseContent = $textBulk < 220;
@endphp

@section('content')
<section class="agenda-ficha-preview-page agenda-shell app-density-compact">
    <div class="agenda-ficha-preview-main">
        <header class="agenda-ficha-preview-head">
            <a href="{{ $backUrl }}" class="agenda-btn agenda-btn-secondary">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Volver
            </a>
            <div class="agenda-ficha-preview-head-text">
                <h1 class="agenda-shell-title">Vista previa de ficha</h1>
            </div>
            <div class="agenda-ficha-preview-head-actions">
                <div class="agenda-ficha-zoom-controls" role="group" aria-label="Controles de zoom de ficha">
                    <button type="button" class="agenda-btn agenda-btn-secondary" data-ficha-zoom-out aria-label="Alejar vista">
                        <i class="fa-solid fa-magnifying-glass-minus" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="agenda-btn agenda-btn-secondary" data-ficha-zoom-reset aria-label="Restablecer zoom">
                        100%
                    </button>
                    <button type="button" class="agenda-btn agenda-btn-secondary" data-ficha-zoom-in aria-label="Acercar vista">
                        <i class="fa-solid fa-magnifying-glass-plus" aria-hidden="true"></i>
                    </button>
                </div>
                <form method="POST" action="{{ $queueUrl }}" data-ficha-queue-form>
                    @csrf
                    <button type="submit" class="agenda-btn agenda-btn-primary" data-ficha-queue-submit>
                        <i class="fa-solid fa-download" aria-hidden="true"></i> Descargar ficha
                    </button>
                </form>
            </div>
        </header>

        <div class="agenda-ficha-stage">
            <article class="agenda-ficha-card agenda-ficha-card--{{ $kind }}{{ $sparseContent ? ' agenda-ficha-card--sparse' : '' }}" data-ficha-preview-card>
                <div class="agenda-ficha-card-body">
                    <p class="agenda-ficha-eyebrow">{{ $kindLabel }}</p>
                    <h2 class="agenda-ficha-title">{{ $card['title'] }}</h2>

                    @if (!empty($card['lugar']))
                        <div class="agenda-ficha-detail">
                            <p class="agenda-ficha-label">Ubicación</p>
                            <p class="agenda-ficha-value">{{ $card['lugar'] }}</p>
                        </div>
                    @endif

                    <div class="agenda-ficha-date-box">
                        <p>{{ $card['badge_day'] }} DE {{ strtoupper((string) ($card['month_year_label'] ?? '')) }}</p>
                    </div>

                    @if (!empty($card['descripcion']))
                        <p class="agenda-ficha-description">{{ $card['descripcion'] }}</p>
                    @endif

                    @if (!empty($card['aforo_label']))
                        <p class="agenda-ficha-aforo">{{ $card['aforo_label'] }}</p>
                    @endif
                </div>

                <div class="agenda-ficha-logo">
                    <img src="{{ asset('images/' . $logoFile) }}" alt="Gobierno de Puebla">
                </div>
            </article>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
    (function () {
        'use strict';

        var card = document.querySelector('[data-ficha-preview-card]');
        if (!card) {
            return;
        }

        var zoomInBtn = document.querySelector('[data-ficha-zoom-in]');
        var zoomOutBtn = document.querySelector('[data-ficha-zoom-out]');
        var zoomResetBtn = document.querySelector('[data-ficha-zoom-reset]');
        var queueForm = document.querySelector('[data-ficha-queue-form]');
        var queueSubmitBtn = document.querySelector('[data-ficha-queue-submit]');

        var MIN_ZOOM = 0.35;
        var MAX_ZOOM = 1.6;
        var STEP = 0.1;
        var scale = 1;

        function showFichaToast(message, durationMs) {
            var ms = durationMs == null ? 4200 : durationMs;
            var el = document.createElement('div');
            el.className = 'app-toast';
            el.setAttribute('role', 'status');
            var span = document.createElement('span');
            span.textContent = message;
            el.appendChild(span);
            document.body.appendChild(el);
            requestAnimationFrame(function () {
                el.classList.add('is-visible');
            });
            setTimeout(function () {
                el.classList.remove('is-visible');
                setTimeout(function () {
                    if (el.parentNode) {
                        el.parentNode.removeChild(el);
                    }
                }, 280);
            }, ms);
        }

        function clamp(value) {
            return Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, value));
        }

        function applyScale() {
            card.style.transform = 'scale(' + scale + ')';
            if (zoomResetBtn) {
                zoomResetBtn.textContent = Math.round(scale * 100) + '%';
            }
            if (zoomOutBtn) {
                zoomOutBtn.disabled = scale <= MIN_ZOOM;
            }
            if (zoomInBtn) {
                zoomInBtn.disabled = scale >= MAX_ZOOM;
            }
        }

        if (zoomInBtn) {
            zoomInBtn.addEventListener('click', function () {
                scale = clamp(scale + STEP);
                applyScale();
            });
        }

        if (zoomOutBtn) {
            zoomOutBtn.addEventListener('click', function () {
                scale = clamp(scale - STEP);
                applyScale();
            });
        }

        if (zoomResetBtn) {
            zoomResetBtn.addEventListener('click', function () {
                scale = 1;
                applyScale();
            });
        }

        if (queueForm) {
            queueForm.addEventListener('submit', function (e) {
                e.preventDefault();

                if (queueSubmitBtn) {
                    queueSubmitBtn.disabled = true;
                }

                showFichaToast('Generando la ficha… Revisa notificaciones cuando esté lista.', 5200);

                fetch(queueForm.action, {
                    method: 'POST',
                    body: new FormData(queueForm),
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json'
                    }
                })
                    .then(function (res) {
                        var ct = (res.headers.get('Content-Type') || '').split(';')[0].trim();
                        if (!res.ok) {
                            if (ct === 'application/json') {
                                return res.json().then(function (data) {
                                    throw new Error((data && data.message) || 'No se pudo generar la ficha.');
                                });
                            }
                            throw new Error('No se pudo generar la ficha.');
                        }
                        if (ct !== 'application/json') {
                            throw new Error('Respuesta inesperada del servidor.');
                        }
                        return res.json();
                    })
                    .then(function (data) {
                        if (!data || !data.queued) {
                            throw new Error('No se pudo iniciar la generación de la ficha.');
                        }
                        if (typeof window.refreshSegobNotifications === 'function') {
                            setTimeout(function () {
                                window.refreshSegobNotifications();
                            }, 350);
                        }
                    })
                    .catch(function (err) {
                        showFichaToast(err.message || 'No se pudo generar la ficha.', 5200);
                    })
                    .finally(function () {
                        if (queueSubmitBtn) {
                            queueSubmitBtn.disabled = false;
                        }
                    });
            });
        }

        // Zoom con mousepad (pinch o Ctrl+Scroll)
        var stage = document.querySelector('.agenda-ficha-stage');
        if (stage) {
            stage.addEventListener('wheel', function (e) {
                if (e.ctrlKey) {
                    e.preventDefault();
                    var delta = e.deltaY < 0 ? STEP : -STEP;
                    scale = clamp(scale + delta);
                    applyScale();
                }
            }, { passive: false });
        }

        applyScale();
    })();
</script>
@endpush
