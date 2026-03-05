@extends('layouts.app')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/modules/profile.css') }}">
@endpush

@push('scripts')
<script src="{{ asset('assets/js/modules/profile-theme.js') }}" defer></script>
@endpush

@section('content')
    @php
        $hidePageHeader = true;
        $phoneNumber = $delegado->telefono ?? ($user->telefono ?? 'No registrado');
        $textureOptions = [
            ['id' => 'blanco', 'tone' => 'blanco', 'label' => 'Blanco', 'url' => asset('images/Texturas_1C-Tlaloc_blanco.png')],
            ['id' => 'rojo-a', 'tone' => 'rojo', 'label' => 'Rojo A', 'url' => asset('images/Texturas_1A-Tlaloc_rojo.png')],
            ['id' => 'verde-a', 'tone' => 'verde', 'label' => 'Verde A', 'url' => asset('images/Texturas_1A-Tlaloc_verde.png')],
            ['id' => 'beige-c', 'tone' => 'amarillo', 'label' => 'Beige C', 'url' => asset('images/Texturas_1C-Tlaloc_beige.png')],
            ['id' => 'rojo-c', 'tone' => 'rojo', 'label' => 'Rojo C', 'url' => asset('images/Texturas_1C-Tlaloc_rojo.png')],
            ['id' => 'verde-c', 'tone' => 'verde', 'label' => 'Verde C', 'url' => asset('images/Texturas_1C-Tlaloc_verde.png')],
        ];
    @endphp

    <section class="profile-layout profile-page-layout">
        <article class="content-card profile-main-card">
            @if (session('status'))
                <div class="inline-alert inline-alert-success" role="alert">{{ session('status') }}</div>
            @endif

            @if (session('error'))
                <div class="inline-alert inline-alert-error" role="alert">{{ session('error') }}</div>
            @endif

            @if ($errors->any())
                <div class="inline-alert inline-alert-error" role="alert">{{ $errors->first() }}</div>
            @endif

            <section
                class="profile-hero-card"
                id="profileHeroCard"
                data-storage-key="profile_texture_preference"
                data-default-texture="{{ $textureOptions[0]['url'] }}"
            >
                <div class="profile-hero-top">
                    <div class="profile-header-block">
                        <div class="profile-avatar-wrap profile-avatar-wrap-lg {{ empty($profilePhoto) ? 'no-photo' : '' }}">
                            @if (!empty($profilePhoto))
                                <img
                                    src="{{ $profilePhoto }}"
                                    alt="Foto de {{ $user->name }}"
                                    class="profile-photo profile-photo-lg"
                                    onerror="this.closest('.profile-avatar-wrap').classList.add('has-fallback'); this.remove();"
                                >
                            @endif
                            <span class="profile-photo profile-photo-fallback profile-photo-lg">{{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}</span>
                        </div>

                        <div class="profile-hero-main">
                            <h2>{{ $user->name }}</h2>
                            <p>{{ $user->email }}</p>
                            <div class="profile-meta-line">
                                <span class="profile-role-badge">{{ $roleName }}</span>
                                <span class="profile-phone-pill">{{ $phoneNumber }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="profile-texture-control">
                        <button type="button" class="profile-open-texture-panel" data-open-texture-panel>
                            Editar fondo
                        </button>

                        <div class="profile-texture-panel" id="profileTexturePanel" aria-hidden="true">
                            <div class="profile-texture-picker" aria-label="Seleccionar textura de fondo">
                                @foreach ($textureOptions as $texture)
                                    <button
                                        type="button"
                                        class="texture-option"
                                        data-texture-id="{{ $texture['id'] }}"
                                        data-texture-tone="{{ $texture['tone'] }}"
                                        data-texture-url="{{ $texture['url'] }}"
                                        title="{{ $texture['label'] }}"
                                        aria-label="{{ $texture['label'] }}"
                                    ></button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="profile-info-grid profile-info-grid-single">
                <article class="profile-info-card profile-info-card-micro">
                    <div class="profile-section-head">
                        <h3>Microrregiones y municipios</h3>
                        <button type="button" class="profile-open-password-modal" data-open-password-modal>
                            Cambiar contraseña
                        </button>
                    </div>

                    @php
                        $micros = ($microrregionesAsignadas ?? collect())->values();
                        if ($micros->isEmpty() && $microrregion) {
                            $micros = collect([
                                (object) [
                                    'id' => (int) $microrregion->id,
                                    'microrregion' => str_pad((string) ($microrregion->microrregion ?? ''), 2, '0', STR_PAD_LEFT),
                                    'cabecera' => (string) ($microrregion->cabecera ?? ''),
                                    'municipios' => ($municipios ?? collect())->values()->all(),
                                ],
                            ]);
                        }
                    @endphp

                    @if ($micros->isNotEmpty())
                        <div class="profile-micro-chips" role="tablist" aria-label="Microrregiones asignadas">
                            @foreach ($micros as $micro)
                                @php $microMunicipios = collect($micro->municipios ?? []); @endphp
                                <button
                                    type="button"
                                    class="profile-micro-chip @if($loop->first) is-active @endif"
                                    data-profile-micro-chip
                                    data-target="profileMicroPane{{ $loop->index }}"
                                    role="tab"
                                    aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                                >
                                    <span class="profile-micro-chip-text">MR {{ $micro->microrregion }} · {{ $micro->cabecera ?: 'Sin cabecera' }}</span>
                                </button>
                            @endforeach
                        </div>

                        <div class="profile-micro-panes">
                            @foreach ($micros as $micro)
                                @php $microMunicipios = collect($micro->municipios ?? []); @endphp
                                <section
                                    id="profileMicroPane{{ $loop->index }}"
                                    class="profile-micro-pane @if($loop->first) is-active @endif"
                                    data-profile-micro-pane
                                    role="tabpanel"
                                >
                                    <ul class="profile-municipios-list profile-municipios-list-fit">
                                        @forelse ($microMunicipios as $municipio)
                                            <li>
                                                <span class="profile-municipio-dot" aria-hidden="true"></span>
                                                <span>{{ $municipio }}</span>
                                            </li>
                                        @empty
                                            <li>Sin municipios registrados para esta microrregión.</li>
                                        @endforelse
                                    </ul>
                                </section>
                            @endforeach
                        </div>
                    @else
                        <div class="text-muted">Sin microrregiones asignadas.</div>
                    @endif
                </article>
            </section>
        </article>
    </section>

    <div class="profile-password-modal" id="profilePasswordModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="profilePasswordModalTitle">
        <div class="profile-password-modal__backdrop" data-close-password-modal></div>
        <div class="profile-password-modal__dialog">
            <div class="profile-password-modal__head">
                <h3 id="profilePasswordModalTitle">Cambiar contraseña</h3>
                <button type="button" class="profile-password-modal__close" aria-label="Cerrar" data-close-password-modal>&times;</button>
            </div>

            <form method="POST" action="{{ route('profile.password.update') }}" class="profile-password-form">
                @csrf
                <label>
                    Contraseña actual
                    <input type="password" name="current_password" required>
                </label>
                <label>
                    Nueva contraseña
                    <input type="password" name="password" required>
                </label>
                <label>
                    Confirmar nueva contraseña
                    <input type="password" name="password_confirmation" required>
                </label>

                <button type="submit" class="profile-save-btn">Cambiar contraseña</button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const chipsWrap = document.querySelector('.profile-micro-chips');
        const chips = Array.from(document.querySelectorAll('[data-profile-micro-chip]'));
        const panes = Array.from(document.querySelectorAll('[data-profile-micro-pane]'));

        if (!chips.length || !panes.length) {
            return;
        }

        const activate = function (targetId) {
            chips.forEach(function (chip) {
                const isActive = chip.getAttribute('data-target') === targetId;
                chip.classList.toggle('is-active', isActive);
                chip.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });

            panes.forEach(function (pane) {
                pane.classList.toggle('is-active', pane.id === targetId);
            });
        };

        const chipActivoInicial = chips.find(function (chip) {
            return chip.classList.contains('is-active');
        }) || chips[0];

        if (chipActivoInicial) {
            activate(chipActivoInicial.getAttribute('data-target'));
        }

        if (chipsWrap) {
            chipsWrap.addEventListener('click', function (event) {
                const chip = event.target.closest('[data-profile-micro-chip]');
                if (!chip) {
                    return;
                }
                event.preventDefault();
                activate(chip.getAttribute('data-target'));
            });
        }
    });
</script>
@endpush
