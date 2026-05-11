<div class="tm-module-grid" id="tmFragmentUpload">
    @forelse ($modules as $module)
        <article class="content-card tm-card tm-module-card tm-upload-card" 
                 data-module-card 
                 data-name="{{ strtolower($module->name) }}" 
                 data-desc="{{ strtolower($module->description ?: '') }}" 
                 data-expiry="{{ $module->expires_at ? $module->expires_at->format('Y-m-d') : 'none' }}">
            <div class="tm-upload-card-head">
                <div class="tm-card-title-row">
                    <h2>{{ $module->name }}</h2>
                    <button type="button"
                            class="tm-btn-refresh"
                            data-refresh-module="{{ $module->id }}"
                            data-refresh-url="{{ route('temporary-modules.module-status', $module->id) }}"
                            title="Actualizar estado del módulo"
                            aria-label="Actualizar estado del módulo">
                        <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
                    </button>
                </div>
                <p>{{ $module->description ?: 'Sin descripcion adicional.' }}</p>
            </div>
            <div class="tm-upload-meta-row">
                <span class="tm-upload-meta-pill"><strong>Vence:</strong> {{ optional($module->expires_at)->format('d/m/Y H:i') ?? 'Sin limite' }}</span>
                <span class="tm-upload-meta-pill"><strong>Mis registros:</strong> {{ $module->my_entries_count }}</span>
            </div>
            @php
                $tmUpEncrypted = (bool) ($module->is_encrypted_event ?? false);
                $tmUpHasCreatePerm = true;
                $tmUpHasCreatePending = false;
                if ($tmUpEncrypted) {
                    $tmUpUserId = (int) auth()->id();
                    $tmUpNow = \Illuminate\Support\Carbon::now();
                    $tmUpHasCreatePerm = \App\Models\TemporaryModuleActionAuthorization::query()
                        ->where('temporary_module_id', (int) $module->id)
                        ->where('requested_by', $tmUpUserId)
                        ->where('action', \App\Models\TemporaryModuleActionAuthorization::ACTION_CREATE)
                        ->where('status', \App\Models\TemporaryModuleActionAuthorization::STATUS_APPROVED)
                        ->where('expires_at', '>', $tmUpNow)
                        ->exists();
                    $tmUpHasCreatePending = ! $tmUpHasCreatePerm && \App\Models\TemporaryModuleActionAuthorization::query()
                        ->where('temporary_module_id', (int) $module->id)
                        ->where('requested_by', $tmUpUserId)
                        ->where('action', \App\Models\TemporaryModuleActionAuthorization::ACTION_CREATE)
                        ->where('status', \App\Models\TemporaryModuleActionAuthorization::STATUS_PENDING)
                        ->exists();
                }
                $tmUpCanCreate = ! $tmUpEncrypted || $tmUpHasCreatePerm;
            @endphp
            <div class="tm-upload-card-foot">
                <span class="tm-permission-slot"
                      data-tm-permission-slot="upload-create"
                      data-tm-permission-module="{{ $module->id }}"
                      data-tm-permission-encrypted="{{ $tmUpEncrypted ? '1' : '0' }}"
                      data-tm-permission-request-url="{{ route('temporary-modules.action-permission.request', ['module' => $module->id, 'action' => 'create']) }}"
                      data-tm-preview-target="delegate-preview-{{ $module->id }}"
                      data-tm-bulk-insert-target="tmBulkInsertModal-{{ $module->id }}"
                      style="display:inline-flex; gap:8px; flex-wrap:wrap; align-items:center;">
                    @if ($tmUpCanCreate)
                        @if ($tmUpEncrypted)
                            <span class="tm-btn tm-btn-success tm-btn-sm" title="Permiso vigente para registrar" style="cursor:default;">
                                <i class="fa-solid fa-check" aria-hidden="true"></i> Permiso activo
                            </span>
                        @endif
                        <button type="button" class="tm-btn tm-btn-primary tm-btn-sm" data-open-module-preview="delegate-preview-{{ $module->id }}">Registrar</button>
                        <button type="button"
                                class="tm-btn tm-btn-outline tm-btn-sm tm-btn-excel-inline"
                                data-open-bulk-insert="tmBulkInsertModal-{{ $module->id }}"
                                title="Registrar en hoja de calculo"
                                aria-label="Registrar en hoja de calculo">
                            <i class="fa-regular fa-file-excel" aria-hidden="true"></i>
                        </button>
                    @elseif ($tmUpHasCreatePending)
                        <button type="button" class="tm-btn tm-btn-outline tm-btn-sm" disabled title="Solicitud enviada al administrador">
                            <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i> Permiso (Registrar) pendiente
                        </button>
                    @else
                        <form action="{{ route('temporary-modules.action-permission.request', ['module' => $module->id, 'action' => 'create']) }}" method="POST" class="tm-inline-form">
                            @csrf
                            <button type="submit" class="tm-btn tm-btn-outline tm-btn-sm" title="Pedir permiso para registrar en este modulo cifrado">
                                <i class="fa-solid fa-key" aria-hidden="true"></i> Pedir Acceso (Registrar)
                            </button>
                        </form>
                    @endif
                </span>
                <a href="{{ route('temporary-modules.records') }}?module={{ $module->id }}" class="tm-btn tm-btn-outline tm-btn-sm" title="Historial de registros">
                    <i class="fa-solid fa-clock" aria-hidden="true"></i>
                </a>
                <button type="button" class="tm-btn tm-btn-outline tm-btn-sm tm-btn-session-errors tm-hidden" 
                        id="tmBtnSessionErrors-{{ $module->id }}" 
                        data-session-errors-module="{{ $module->id }}"
                        title="Errores pendientes de importación">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span class="tm-error-count">0</span>
                </button>
            </div>
        </article>
    @empty
        <article class="content-card tm-card">
            <p>No hay modulos temporales activos en este momento.</p>
        </article>
    @endforelse
</div>
