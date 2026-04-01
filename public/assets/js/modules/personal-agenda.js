/**
 * Agenda Personal - JS Refinado y Localizado (Español)
 */
// Global functions first to avoid reference errors
const swalAlert = window.Swal || Swal;

function paBuildUrl(path, params = {}) {
    // Rutas absolutas (http/https) o relativas al origen; evitar solo "/ruta" sin prefijo de app en subcarpetas.
    const url = /^https?:\/\//i.test(path)
        ? new URL(path)
        : new URL(path, window.location.origin);

    Object.entries(params).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            url.searchParams.set(key, value);
        }
    });

    return url.toString();
}

function paGetSearchQuery() {
    const el = document.getElementById('pa-search-notes');
    if (!el) return '';
    let s = String(el.value || '').trim();
    if (s.length > 200) s = s.slice(0, 200);
    return s;
}

function paSyncSearchClearUi() {
    const input = document.getElementById('pa-search-notes');
    const wrap = document.getElementById('pa-search-wrapper');
    const clearBtn = document.getElementById('pa-search-clear');
    if (!input || !wrap || !clearBtn) return;
    const has = (input.value || '').trim().length > 0;
    wrap.classList.toggle('has-search-text', has);
    clearBtn.hidden = !has;
}

async function paFetch(url, options = {}) {
    const headers = {
        'X-Requested-With': 'XMLHttpRequest',
        ...(options.headers || {}),
    };

    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers,
    });

    if (response.status === 401) {
        window.location.href = url;
        throw new Error('Unauthenticated');
    }

    return response;
}

/**
 * Lee data-note-data del DOM (puede fallar JSON.parse directo por entidades o UTF-8 raro).
 */
function paParseNoteData(raw) {
    if (raw == null || raw === '') return null;
    let s = String(raw).trim();
    const tryParse = (x) => {
        try {
            return JSON.parse(x);
        } catch {
            return null;
        }
    };
    let out = tryParse(s);
    if (out !== null) return out;
    const ta = document.createElement('textarea');
    ta.innerHTML = s;
    s = (ta.value || '').trim();
    out = tryParse(s);
    if (out !== null) return out;
    s = s.replace(/^\uFEFF/, '').replace(/[\u200B-\u200D\uFEFF]/g, '').trim();
    return tryParse(s);
}

function paParseNoteDataFromEl(el) {
    if (!el) return null;
    const raw = el.getAttribute('data-note-data');
    return paParseNoteData(raw);
}

/** Lista de carpetas desde #pa-folders-json (evita JSON.parse('') → Unexpected end of input). */
function paReadFoldersJson() {
    const el = document.getElementById('pa-folders-json');
    if (!el) return [];
    const raw = (el.textContent || '').trim();
    if (!raw) return [];
    try {
        const data = JSON.parse(raw);
        return Array.isArray(data) ? data : [];
    } catch (e) {
        console.warn('pa-folders-json inválido', e);
        return [];
    }
}

/** Actualiza el desplegable "Carpeta" del filtro tras crear/editar/archivar carpetas (sin recargar la página). */
function paSyncFolderFilterSelect(folders) {
    const sel = document.getElementById('filter-folder');
    if (!sel || !Array.isArray(folders)) return;
    const current = sel.value;
    sel.replaceChildren();
    const optAll = document.createElement('option');
    optAll.value = '';
    optAll.textContent = 'Todas';
    sel.appendChild(optAll);
    folders.forEach((f) => {
        if (f == null || f.id == null) return;
        const o = document.createElement('option');
        o.value = String(f.id);
        o.textContent = f.name != null ? String(f.name) : '';
        sel.appendChild(o);
    });
    if (current && [...sel.options].some((opt) => opt.value === current)) {
        sel.value = current;
    }
}

async function paParseFetchJson(response) {
    const text = await response.text();
    const trimmed = (text || '').trim();
    if (!trimmed) {
        return { ok: response.ok, data: {}, emptyBody: true };
    }
    try {
        return { ok: response.ok, data: JSON.parse(trimmed), emptyBody: false };
    } catch (e) {
        return { ok: response.ok, data: {}, parseError: true, bodySnippet: trimmed.slice(0, 160) };
    }
}

window.loadFolders = function() {
    return fetch(paBuildUrl(window.paRoutes.index, { filter: 'folders_json', search: paGetSearchQuery() }), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(paParseFetchJson)
    .then(({ ok, data }) => {
        if (!ok || !data) return;
        const container = document.getElementById('pa-folders-container');
        if (container && data.html) {
            container.innerHTML = data.html;
        }
        const scriptEl = document.getElementById('pa-folders-json');
        if (scriptEl && data.folders) {
            scriptEl.textContent = JSON.stringify(data.folders);
            // Sync filter dropdown
            if (typeof paSyncFolderFilterSelect === 'function') {
                paSyncFolderFilterSelect(data.folders);
            }
        }
    });
};

window.openPersonalNoteModal = function(noteData = null) {
    window._pendingAttachments = [];
    const folders = paReadFoldersJson();
    const activeFolderId = noteData?.folder_id || window.paCurrentFolderId || null;
    const folderOptions = folders.map(f => `
        <option value="${f.id}" ${activeFolderId == f.id ? 'selected' : ''}>${f.name}</option>
    `).join('');

    // Random color for new notes
    const defaultColors = ['#f8fbff', '#f1fcf1', '#fffcf1', '#fff1f1', '#fcf1ff'];
    const randomDefault = defaultColors[Math.floor(Math.random() * defaultColors.length)];
    const initialColor = noteData?.color || randomDefault;
    swalAlert.fire({
        title: `<div>${noteData ? 'Editar Nota' : 'Nueva Nota Personal'}</div><i class="fa-solid fa-xmark" style="cursor:pointer; font-size: 1rem; opacity: 0.5;" onclick="Swal.close()"></i>`,
        html: `
            <div class="swal-form-container">
                <div class="modal-grid" style="grid-template-columns: 1.5fr 1fr; gap: 8px; margin-bottom: 0;">
                    <div>
                        <label class="swal2-label">Título de la nota</label>
                        <input id="note-title" class="swal2-input" placeholder="Ej. Compras..." value="${noteData?.title || ''}">
                    </div>
                    <div>
                        <label class="swal2-label">Carpeta</label>
                        <select id="note-folder" class="swal2-select">
                            <option value="">Sin carpeta</option>
                            ${folderOptions}
                        </select>
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 4px; margin-bottom: 12px; flex-wrap: wrap;">
                    <button type="button" id="btn-toggle-reminder" class="pa-toggle-btn ${noteData?.scheduled_date ? 'is-active' : ''}" style="margin: 0; padding: 6px 10px; font-size: 0.75rem;">
                        <i class="fa-solid fa-bell"></i> Recordarme
                    </button>
                    <button type="button" id="btn-toggle-priority" class="pa-toggle-btn ${noteData?.priority && noteData.priority !== 'none' ? 'is-active' : ''}" style="margin: 0; padding: 6px 10px; font-size: 0.75rem;">
                        <i class="fa-solid fa-flag"></i> Prioridad
                    </button>
                    <button type="button" id="btn-toggle-encrypt" class="pa-toggle-btn ${noteData?.is_encrypted ? 'is-active' : ''}" style="margin: 0; padding: 6px 10px; font-size: 0.75rem;">
                        <i class="fa-solid fa-lock"></i> Cifrar
                    </button>

                    <div style="margin-left: auto; display: flex; align-items: center; gap: 6px;">
                        <div class="pa-color-circle" id="note-color-preview" style="background: ${initialColor}; border: 1.5px solid #ddd; cursor: pointer; width: 28px; height: 28px; border-radius: 50%; position: relative !important;">
                            <input type="color" id="note-color" value="${initialColor}"
                                   style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; cursor: pointer; border: none; padding: 0; background: transparent; opacity: 0; -webkit-appearance: none; appearance: none; display: block !important;">
                        </div>
                    </div>
                </div>

                <div class="modal-grid" style="grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-top: 0; min-height: 40px;">
                    <div id="wrapper-priority" style="${noteData?.priority && noteData.priority !== 'none' ? '' : 'display:none;'}">
                        <label class="swal2-label" style="font-size: 0.75rem;">Prioridad</label>
                        <select id="note-priority" class="swal2-select" style="height: 34px !important; font-size: 0.8rem !important;">
                            <option value="none" ${noteData?.priority === 'none' || !noteData?.priority ? 'selected' : ''}>Sin marca / Ninguna</option>
                            <option value="none" ${noteData?.priority === 'none' || !noteData?.priority ? 'selected' : ''}>Sin marca / Ninguna</option>
                            <option value="low" ${noteData?.priority === 'low' ? 'selected' : ''}>Baja</option>
                            <option value="medium" ${noteData?.priority === 'medium' ? 'selected' : ''}>Media</option>
                            <option value="high" ${noteData?.priority === 'high' ? 'selected' : ''}>Alta</option>
                        </select>
                    </div>
                    <div id="wrapper-date" style="${noteData?.scheduled_date ? '' : 'display:none;'}">
                        <label class="swal2-label" style="font-size: 0.75rem;">Fecha del evento</label>
                        <input type="date" id="note-date" class="swal2-input" value="${noteData?.scheduled_date || ''}" style="height: 34px !important; font-size: 0.8rem !important; margin-bottom: 8px !important;">
                        <label class="swal2-label" style="font-size: 0.72rem; display:flex; align-items:center; gap:8px; cursor:pointer; margin-bottom:6px;">
                            <input type="checkbox" id="note-all-day" ${noteData?.scheduled_date && !noteData?.scheduled_time ? 'checked' : ''}>
                            Todo el día (sin hora)
                        </label>
                        <div id="wrapper-time-row" style="${noteData?.scheduled_date && !noteData?.scheduled_time ? 'display:none;' : ''}">
                            <label class="swal2-label" style="font-size: 0.75rem;">Hora</label>
                            <input type="time" id="note-time" class="swal2-input" value="${noteData?.scheduled_time || ''}" style="height: 34px !important; font-size: 0.8rem !important; margin-bottom: 0 !important;">
                        </div>
                    </div>
                        <div id="password-container" style="${noteData?.is_encrypted ? '' : 'display:none;'}">
                            <label class="swal2-label" style="font-size: 0.75rem;">Contraseña</label>
                            <input type="password" id="note-password" class="swal2-input" placeholder="Clave..." value="${noteData?.password || ''}" style="height: 34px !important; font-size: 0.8rem !important; margin-bottom: 0 !important; max-width: 150px;">
                        </div>
                </div>

                <div style="margin-top: 4px;">
                    <label class="swal2-label">Contenido de la nota</label>
                    <textarea id="note-content" class="swal2-textarea" placeholder="Escribe aquí los detalles..." style="height: 100px;">${noteData?.content || ''}</textarea>
                </div>

                <div class="pa-att-zone">
                    <label class="pa-att-trigger" onclick="document.getElementById('note-attachments').click()">
                        <i class="fa-solid fa-paperclip"></i> Adjuntar archivo
                    </label>
                    <span id="att-counter" style="font-size:0.7rem;color:#888;margin-left:6px;"></span>
                    <input type="file" id="note-attachments" multiple style="display:none;" onchange="previewNewAttachments(this)">
                    <div class="pa-att-list" id="attachments-preview">
                        ${(noteData?.attachments || []).map(att => `
                            <div class="pa-att-item" data-id="${att.id}">
                                ${att.file_type === 'image'
                                    ? `<img src="${att.file_path}" alt="${att.file_name}">`
                                    : `<div class="pa-att-file-icon"><i class="fa-solid fa-file-lines"></i></div>`}
                                <div class="pa-att-name" title="${att.file_name}">${att.file_name}</div>
                                <button class="pa-att-remove" onclick="removeAttachment(${att.id}, this.closest('.pa-att-item')); updateAttCounter();"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                        `).join('')}
                    </div>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: noteData ? 'Actualizar' : 'Guardar',
        cancelButtonText: 'Cancelar',
        customClass: {
            popup: 'pa-swal-popup tm-swal-popup',
            title: 'tm-swal-title',
            htmlContainer: 'tm-swal-text',
            confirmButton: 'tm-swal-confirm',
            cancelButton: 'tm-swal-cancel'
        },
        didOpen: () => {
            updateAttCounter();
            // Color preview update
            const colorInput = document.getElementById('note-color');
            const colorPreview = document.getElementById('note-color-preview');
            if (colorInput && colorPreview) {
                colorInput.addEventListener('input', (e) => {
                    colorPreview.style.background = e.target.value;
                });
            }

            // Enter key support
            const mainInputs = ['note-title', 'note-date', 'note-password'];
            mainInputs.forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            swalAlert.clickConfirm();
                        }
                    });
                }
            });

            // Toggles
            const setups = [
                { btnId: 'btn-toggle-reminder', wrapperId: 'wrapper-date', inputId: 'note-date', resetValue: '' },
                { btnId: 'btn-toggle-priority', wrapperId: 'wrapper-priority', inputId: 'note-priority', resetValue: 'none' },
                { btnId: 'btn-toggle-encrypt', wrapperId: 'password-container', inputId: 'note-password', resetValue: '' }
            ];

            setups.forEach(s => {
                const btn = document.getElementById(s.btnId);
                const wrapper = document.getElementById(s.wrapperId);
                if (!btn || !wrapper) return;
                btn.addEventListener('click', () => {
                    const isActive = btn.classList.toggle('is-active');
                    wrapper.style.display = isActive ? 'block' : 'none';
                    if (!isActive && s.btnId === 'btn-toggle-reminder') {
                        const d = document.getElementById('note-date');
                        const t = document.getElementById('note-time');
                        const ad = document.getElementById('note-all-day');
                        if (d) d.value = '';
                        if (t) t.value = '';
                        if (ad) ad.checked = false;
                    }
                });
            });

            const allDayEl = document.getElementById('note-all-day');
            const timeRow = document.getElementById('wrapper-time-row');
            const timeInput = document.getElementById('note-time');
            if (allDayEl && timeRow && timeInput) {
                allDayEl.addEventListener('change', () => {
                    const on = allDayEl.checked;
                    timeRow.style.display = on ? 'none' : 'block';
                    if (on) timeInput.value = '';
                });
            }
        },
        preConfirm: () => {
            const isReminderActive = document.getElementById('btn-toggle-reminder').classList.contains('is-active');
            const isPriorityActive = document.getElementById('btn-toggle-priority').classList.contains('is-active');
            const isEncryptActive = document.getElementById('btn-toggle-encrypt').classList.contains('is-active');
            const allDay = document.getElementById('note-all-day')?.checked;
            const rawPassword = document.getElementById('note-password').value || '';
            const trimmedPassword = rawPassword.trim();
            const shouldEncrypt = isEncryptActive && trimmedPassword.length > 0;

            return {
                id: noteData?.id,
                title: document.getElementById('note-title').value,
                content: document.getElementById('note-content').value,
                folder_id: document.getElementById('note-folder').value,
                color: document.getElementById('note-color').value,
                priority: isPriorityActive ? document.getElementById('note-priority').value : 'none',
                scheduled_date: isReminderActive ? document.getElementById('note-date').value : null,
                scheduled_time: isReminderActive && !allDay ? (document.getElementById('note-time')?.value || null) : null,
                is_encrypted: shouldEncrypt ? 1 : 0,
                password: shouldEncrypt ? trimmedPassword : '',
                attachments: (window._pendingAttachments || []).filter(f => f !== null)
            };
        }
    }).then((result) => {
        if (result.isConfirmed) saveNote(result.value);
    });
};

window.openFolderModal = function(folderId) {
    let folder = null;
    if (folderId != null && folderId !== '') {
        const list = paReadFoldersJson();
        folder = list.find(f => Number(f.id) === Number(folderId)) || null;
    }
    const isEdit = !!folder;
    const titleHead = isEdit ? 'Editar carpeta' : 'Nueva Carpeta';
    const confirmBtn = isEdit ? 'Guardar' : 'Crear';
    const defaultColor = folder?.color || '#e3f2fd';
    const defaultIcon = folder?.icon || 'fa-folder';

    swalAlert.fire({
        title: `<div>${titleHead}</div><i class="fa-solid fa-xmark" style="cursor:pointer; font-size: 1rem; opacity: 0.5;" onclick="Swal.close()"></i>`,
        html: `
            <div class="swal-form-container">
                <input type="hidden" id="folder-record-id" value="">
                <label class="swal2-label">Nombre de la carpeta</label>
                <input id="folder-name" class="swal2-input" placeholder="Ej. Proyectos...">

                <div style="display: flex; align-items: center; gap: 15px; margin-top: 10px;">
                    <label class="swal2-label" style="margin: 0;">Identificador visual</label>
                    <div style="display: flex; gap: 8px;">
                        <div class="pa-color-circle" id="folder-color-preview" style="background: ${defaultColor}; border: 1.5px solid #ddd; cursor: pointer; width: 32px; height: 32px; border-radius: 50%; position: relative !important;">
                            <input type="color" id="folder-color" value="${defaultColor}"
                                   style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; cursor: pointer; border: none; padding: 0; background: transparent; opacity: 0; -webkit-appearance: none; appearance: none; display: block !important;">
                        </div>
                        <div class="pa-icon-selector-trigger" id="folder-icon-preview" style="width: 32px; height: 32px; border-radius: 8px; border: 1.5px solid #ddd; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.9rem;">
                            <i class="fa-solid ${defaultIcon}"></i>
                            <input type="hidden" id="folder-icon" value="${defaultIcon}">
                        </div>
                    </div>
                </div>
                <div id="icon-picker-container">
                    ${[
                        'fa-folder', 'fa-phone', 'fa-mobile-screen-button', 'fa-video', 'fa-camera', 'fa-microphone', 'fa-envelope',
                        'fa-briefcase', 'fa-kit-medical', 'fa-stethoscope', 'fa-graduation-cap', 'fa-heart', 'fa-star', 'fa-bell',
                        'fa-calendar-days', 'fa-clock', 'fa-user', 'fa-users', 'fa-house', 'fa-car', 'fa-plane', 'fa-shopping-cart', 'fa-tag',
                        'fa-code', 'fa-database', 'fa-file-invoice-dollar', 'fa-piggy-bank', 'fa-chart-line', 'fa-lightbulb', 'fa-gear',
                        'fa-mug-hot', 'fa-utensils', 'fa-child', 'fa-dumbbell', 'fa-tree', 'fa-building-columns', 'fa-handshake',
                        'fa-headset', 'fa-book', 'fa-bookmark', 'fa-paperclip', 'fa-scissors', 'fa-pen-nib', 'fa-paintbrush', 'fa-wrench',
                        'fa-hammer', 'fa-screwdriver-wrench', 'fa-microchip', 'fa-wifi', 'fa-bluetooth', 'fa-battery-full', 'fa-plug',
                        'fa-bolt', 'fa-fire', 'fa-snowflake', 'fa-droplet', 'fa-cloud', 'fa-sun', 'fa-moon', 'fa-earth-americas', 'fa-leaf',
                        'fa-bug', 'fa-paw', 'fa-fish', 'fa-horse', 'fa-mountain', 'fa-bicycle', 'fa-bus', 'fa-truck', 'fa-train',
                        'fa-anchor', 'fa-rocket', 'fa-map-location-dot', 'fa-compass', 'fa-flag', 'fa-key', 'fa-shield-halved', 'fa-lock',
                        'fa-unlock', 'fa-eye', 'fa-comment', 'fa-comments', 'fa-share-nodes', 'fa-thumbs-up', 'fa-music', 'fa-headphones',
                        'fa-tv', 'fa-gamepad', 'fa-gift', 'fa-cake-candles', 'fa-pizza-slice', 'fa-burger', 'fa-coffee', 'fa-credit-card',
                        'fa-wallet', 'fa-money-bill-wave'
                    ].map(icon => `
                        <div class="pa-icon-option ${icon === defaultIcon ? 'is-active' : ''}" data-icon="${icon}">
                            <i class="fa-solid ${icon}"></i>
                        </div>
                    `).join('')}
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: confirmBtn,
        cancelButtonText: 'Cancelar',
        customClass: {
            popup: 'pa-swal-popup tm-swal-popup',
            title: 'tm-swal-title',
            htmlContainer: 'tm-swal-text',
            confirmButton: 'tm-swal-confirm',
            cancelButton: 'tm-swal-cancel'
        },
        didOpen: () => {
            const rid = document.getElementById('folder-record-id');
            if (rid) rid.value = isEdit && folder ? String(folder.id) : '';
            const nameInput = document.getElementById('folder-name');
            if (nameInput) {
                if (isEdit && folder) nameInput.value = folder.name || '';
                nameInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        swalAlert.clickConfirm();
                    }
                });
            }
            const colorInput = document.getElementById('folder-color');
            const colorPreview = document.getElementById('folder-color-preview');
            if (colorInput && colorPreview) {
                if (isEdit && folder?.color) {
                    colorInput.value = folder.color;
                    colorPreview.style.background = folder.color;
                }
                colorInput.addEventListener('input', (e) => {
                    colorPreview.style.background = e.target.value;
                });
            }

            const iconPreview = document.getElementById('folder-icon-preview');
            const iconPicker = document.getElementById('icon-picker-container');
            const iconInput = document.getElementById('folder-icon');
            if (iconPreview && iconPicker) {
                iconPreview.addEventListener('click', () => {
                    iconPicker.style.display = iconPicker.style.display === 'none' ? 'grid' : 'none';
                    if (iconPicker.style.display === 'grid') {
                        // Scroll to active icon
                        const active = iconPicker.querySelector('.pa-icon-option.is-active');
                        if (active) active.scrollIntoView({ block: 'center' });
                    }
                });
                iconPicker.querySelectorAll('.pa-icon-option').forEach(opt => {
                    opt.addEventListener('click', () => {
                        const iconClass = opt.dataset.icon;
                        iconInput.value = iconClass;
                        iconPreview.querySelector('i').className = `fa-solid ${iconClass}`;
                        iconPicker.style.display = 'none';
                        iconPicker.querySelectorAll('.pa-icon-option').forEach(o => o.classList.remove('is-active'));
                        opt.classList.add('is-active');
                    });
                });
            }
        },
        preConfirm: () => {
            const name = (document.getElementById('folder-name')?.value || '').trim();
            if (!name) {
                if (typeof segobToast === 'function') segobToast('warning', 'Indica un nombre para la carpeta');
                return false;
            }
            const rid = document.getElementById('folder-record-id')?.value;
            return {
                id: rid || null,
                name,
                color: document.getElementById('folder-color').value,
                icon: document.getElementById('folder-icon').value
            };
        }
    }).then((result) => {
        if (result.isConfirmed) saveFolder(result.value);
    });
};

async function saveFolder(data) {
    const isEdit = data.id != null && String(data.id).length > 0;
    const url = isEdit
        ? window.paRoutes.foldersUpdate.replace(':id', data.id)
        : window.paRoutes.foldersStore;
    const method = isEdit ? 'PUT' : 'POST';
    const payload = {
        name: data.name,
        color: data.color,
        icon: data.icon
    };
    try {
        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });
        if (response.ok) {
            segobToast('success', isEdit ? 'Carpeta actualizada' : 'Carpeta creada');
            await loadFolders();
            const activeNav = document.querySelector('.pa-nav-item.is-active');
            const navFilter = activeNav?.dataset?.filter || 'all';
            if (typeof window.loadNotes === 'function') {
                window.loadNotes(navFilter);
            }
        } else {
            const parsed = await paParseFetchJson(response);
            const msg = (parsed.data && (parsed.data.message || parsed.data.error)) || 'No se pudo guardar la carpeta';
            segobToast('error', typeof msg === 'string' ? msg : 'No se pudo guardar la carpeta');
        }
    } catch (error) {
        segobToast('error', 'Fallo de conexión');
    }
}

window.toggleFolderPin = function(id) {
    fetch(window.paRoutes.foldersPin.replace(':id', id), {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(paParseFetchJson)
        .then(({ ok, data }) => {
            if (!ok || !data.success) {
                if (typeof segobToast === 'function') {
                    segobToast('warning', data.message || 'No se pudo actualizar la fijación');
                }
                return;
            }
            if (typeof segobToast === 'function') {
                segobToast('success', data.pinned ? 'Carpeta fijada' : 'Fijación quitada');
            }
            loadFolders();
        })
        .catch(() => {
            if (typeof segobToast === 'function') segobToast('error', 'Fallo de conexión');
        });
};

window.archiveFolder = function(id, name) {
    const archiveUrl = window.paRoutes.foldersArchive && window.paRoutes.foldersArchive.replace(':id', id);
    if (!archiveUrl || archiveUrl.includes(':id')) {
        if (typeof segobToast === 'function') segobToast('error', 'Ruta de archivado no configurada. Recarga la página.');
        console.error('foldersArchive inválida', window.paRoutes.foldersArchive);
        return;
    }
    swalAlert.fire({
        title: '¿Archivar carpeta?',
        html: `<p class="text-start" style="font-size:0.9rem;">La carpeta <strong>${String(name).replace(/</g, '&lt;')}</strong> y todas sus notas pasarán al <strong>Archivo</strong>. La carpeta dejará de mostrarse en la lista.</p>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, archivar',
        cancelButtonText: 'Cancelar',
        customClass: {
            confirmButton: 'tm-swal-confirm btn-primary',
            cancelButton: 'tm-swal-cancel'
        }
    }).then((result) => {
        if (!result.isConfirmed) return;
        fetch(archiveUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(paParseFetchJson)
            .then(({ ok, data, parseError, emptyBody }) => {
                if (parseError) {
                    if (typeof segobToast === 'function') {
                        segobToast('error', 'Respuesta inválida (¿sesión caducada?). Recarga e inténtalo de nuevo.');
                    }
                    return;
                }
                if (emptyBody && !ok) {
                    if (typeof segobToast === 'function') segobToast('error', 'Error del servidor al archivar');
                    return;
                }
                if (data && data.success) {
                    if (typeof segobToast === 'function') segobToast('success', 'Carpeta archivada');
                    loadFolders();
                    if (document.querySelector('.pa-nav-item.is-active')?.dataset.filter === 'archive' && typeof window.loadNotes === 'function') {
                        window.loadNotes('archive');
                    }
                    return;
                }
                if (typeof segobToast === 'function') {
                    segobToast('warning', (data && data.message) || 'No se pudo archivar');
                }
            })
            .catch(() => {
                if (typeof segobToast === 'function') segobToast('error', 'Fallo de conexión');
            });
    });
};

window.restoreArchivedFolder = function(id) {
    const url = window.paRoutes.foldersRestore && window.paRoutes.foldersRestore.replace(':id', String(id));
    if (!url || url.includes(':id')) {
        if (typeof segobToast === 'function') segobToast('error', 'Ruta no configurada. Recarga la página.');
        return;
    }
    fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
        .then(paParseFetchJson)
        .then(({ ok, data }) => {
            if (!ok || !data || !data.success) {
                if (typeof segobToast === 'function') segobToast('error', 'No se pudo restaurar la carpeta');
                return;
            }
            if (typeof segobToast === 'function') segobToast('success', 'Carpeta restaurada');
            
            // Si estamos dentro de la carpeta que se acaba de restaurar, salir a la raíz del archivo
            if (String(window.paCurrentFolderId) === String(id)) {
                if (typeof navigateToFolder === 'function') navigateToFolder(null);
            } else {
                if (typeof loadFolders === 'function') loadFolders();
                if (typeof window.loadNotes === 'function') window.loadNotes('archive');
            }
        })
        .catch(() => {
            if (typeof segobToast === 'function') segobToast('error', 'Fallo de conexión');
        });
};

window.emptyTrash = function() {
    if (!window.paRoutes || !window.paRoutes.trashEmpty) return;
    swalAlert.fire({
        title: '¿Vaciar la papelera?',
        text: 'Se eliminarán permanentemente todas las notas que están en la papelera. No se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, vaciar',
        cancelButtonText: 'Cancelar',
        customClass: {
            confirmButton: 'tm-swal-confirm btn-danger',
            cancelButton: 'tm-swal-cancel',
        },
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        try {
            const response = await fetch(window.paRoutes.trashEmpty, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const parsed = await paParseFetchJson(response);
            const data = parsed.data || {};
            if (parsed.parseError || !parsed.ok || !data.success) {
                if (typeof segobToast === 'function') segobToast('error', 'No se pudo vaciar la papelera');
                return;
            }
            const n = typeof data.deleted === 'number' ? data.deleted : 0;
            if (typeof segobToast === 'function') {
                segobToast('success', n > 0 ? `Se eliminaron ${n} nota(s)` : 'Papelera vacía');
            }
            if (typeof window.loadNotes === 'function') window.loadNotes('trash');
            if (typeof loadFolders === 'function') loadFolders();
        } catch (e) {
            if (typeof segobToast === 'function') segobToast('error', 'Fallo de conexión');
        }
    });
};

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('pa-search-notes');
    const searchClearBtn = document.getElementById('pa-search-clear');
    const tabs = document.querySelectorAll('.pa-tab');

    const PA_SEARCH_DEBOUNCE_MS = 320;
    let paSearchDebounceTimer = null;

    function scheduleSearchReload() {
        if (paSearchDebounceTimer) clearTimeout(paSearchDebounceTimer);
        paSearchDebounceTimer = setTimeout(() => {
            paSearchDebounceTimer = null;
            paSyncSearchClearUi();
            if (typeof window.loadNotes === 'function') window.loadNotes();
            if (typeof updateHash === 'function') updateHash();
        }, PA_SEARCH_DEBOUNCE_MS);
    }

    if (searchInput) {
        paSyncSearchClearUi();
        searchInput.addEventListener('input', () => {
            paSyncSearchClearUi();
            scheduleSearchReload();
        });
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                searchInput.value = '';
                paSyncSearchClearUi();
                if (typeof window.loadNotes === 'function') window.loadNotes();
                if (typeof updateHash === 'function') updateHash();
            }
        });
    }
    if (searchClearBtn && searchInput) {
        searchClearBtn.addEventListener('click', () => {
            searchInput.value = '';
            paSyncSearchClearUi();
            searchInput.focus();
            if (typeof window.loadNotes === 'function') window.loadNotes();
            if (typeof updateHash === 'function') updateHash();
        });
    }

    // Lógica de Tabs (Tiempo)
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.pa-tab').forEach(t => t.classList.remove('is-active'));
            this.classList.add('is-active');
            window.paCurrentFolderId = null; // Al cambiar un filtro principal, salimos de la carpeta
            loadNotes();
        });
    });


    /**
     * Sidebar Collapsible Logic
     */
    const sidebar = document.getElementById('pa-sidebar');
    const toggleSidebarBtn = document.getElementById('btn-sidebar-collapse');

    if (sidebar && toggleSidebarBtn) {
        toggleSidebarBtn.addEventListener('click', () => {
            sidebar.classList.toggle('is-collapsed');
            const icon = toggleSidebarBtn.querySelector('i');
            if (sidebar.classList.contains('is-collapsed')) {
                icon.classList.replace('fa-angles-left', 'fa-angles-right');
            } else {
                icon.classList.replace('fa-angles-right', 'fa-angles-left');
            }
        });
    }

    // ===== Explorer State =====
    window.paCurrentFolderId = null;
    window.paExplorerView = 'grid'; // 'grid' or 'list'

    function setExplorerMode(enabled) {
        const main = document.querySelector('.pa-main');
        const explorerBar = document.getElementById('pa-explorer-bar');
        const sliderBtns = document.querySelectorAll('.pa-slider-btn');
        const notesContainer = document.getElementById('pa-notes-container');
        const notesHeader = document.getElementById('notes-section-header');

        if (enabled) {
            main.classList.add('is-explorer-mode');
            if (explorerBar) explorerBar.style.display = 'flex';
            if (notesContainer) {
                notesContainer.classList.add('is-grid');
                if (window.paExplorerView === 'list') {
                    notesContainer.classList.add('is-list-view');
                }
            }
        } else {
            main.classList.remove('is-explorer-mode');
            if (explorerBar) explorerBar.style.display = 'none';
            if (notesContainer) {
                notesContainer.classList.remove('is-grid', 'is-list-view');
            }
            window.paCurrentFolderId = null;
            updateBreadcrumb(null);
        }
    }

    function updateBreadcrumb(folder) {
        const breadcrumb = document.getElementById('pa-breadcrumb');
        if (!breadcrumb) return;

        const activeNav = document.querySelector('.pa-nav-item.is-active');
        const isArchive = activeNav && activeNav.dataset.filter === 'archive';
        const rootLabel = isArchive ? 'Archivo' : 'Mis Notas';
        const rootIcon = isArchive ? 'fa-box-archive' : 'fa-house';

        let html = `<a href="#" class="pa-breadcrumb-item pa-breadcrumb-root ${!folder ? 'is-current' : ''}" onclick="event.preventDefault(); navigateToFolder(null);">
            <i class="fa-solid ${rootIcon}" style="font-size: 0.7rem;"></i> ${rootLabel}
        </a>`;

        if (folder) {
            html += `<span class="pa-breadcrumb-separator"><i class="fa-solid fa-chevron-right"></i></span>`;
            html += `<span class="pa-breadcrumb-item is-current"><i class="fa-solid ${folder.icon || 'fa-folder'}"></i> ${folder.name}</span>`;
        }

        breadcrumb.innerHTML = html;
    }

    window.navigateToFolder = async function(folderId) {
        window.paCurrentFolderId = folderId;
        document.querySelectorAll('.pa-tab-pill').forEach(i => i.classList.remove('is-active'));

        // Sincronizar el select de filtros si existe
        const folderFilter = document.getElementById('filter-folder');
        if (folderFilter) {
            folderFilter.value = folderId || '';
        }

        const activeNav = document.querySelector('.pa-nav-item.is-active');
        const currentFilter = activeNav ? activeNav.dataset.filter : 'all';

        if (!folderId) {
            // Go back to root view
            updateBreadcrumb(null);
            
            if (currentFilter === 'archive') {
                setExplorerMode(false);
                loadNotes('archive');
                const archivedFolderSec = document.getElementById('section-archived-folders');
                if (archivedFolderSec) archivedFolderSec.style.display = 'block';
                const folderSec = document.getElementById('section-folders');
                if (folderSec) folderSec.style.display = 'none';
            } else if (currentFilter === 'all') {
                setExplorerMode(false);
                // Force time filter to all when returning to root to avoid "disappearing" notes from previous weeks
                document.querySelectorAll('.pa-tab-pill').forEach(i => i.classList.remove('is-active'));
                loadNotes('all', 'all');
                const folderSec = document.getElementById('section-folders');
                if (folderSec) folderSec.style.display = 'block';
                const archivedFolderSec = document.getElementById('section-archived-folders');
                if (archivedFolderSec) archivedFolderSec.style.display = 'none';
            } else {
                setExplorerMode(currentFilter === 'folders');
                loadNotes(currentFilter);
                const folderSec = document.getElementById('section-folders');
                const isFolderFilter = (currentFilter === 'folders' || currentFilter === 'all');
                if (folderSec) folderSec.style.display = isFolderFilter ? 'block' : 'none';
                const archivedFolderSec = document.getElementById('section-archived-folders');
                if (archivedFolderSec) archivedFolderSec.style.display = (currentFilter === 'archive' ? 'block' : 'none');
            }

            const notesHeader = document.getElementById('notes-section-header');
            if (notesHeader) notesHeader.style.display = '';
            return;
        }

        // Hide dynamic sections — show only notes inside this folder
        const folderSec = document.getElementById('section-folders');
        const archivedFolderSec = document.getElementById('section-archived-folders');
        if (folderSec) folderSec.style.display = 'none';
        if (archivedFolderSec) archivedFolderSec.style.display = 'none';
        const notesHeader = document.getElementById('notes-section-header');
        if (notesHeader) notesHeader.style.display = 'none';

        const container = document.getElementById('pa-notes-container');
        if (container) container.style.opacity = '0.5';

        if (container) {
            container.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 40px; opacity: 0.5;"><i class="fa-solid fa-spinner fa-spin"></i> Cargando notas...</div>';
            container.style.opacity = '0.5';
        }

        try {
            const activeNav = document.querySelector('.pa-nav-item.is-active');
            let currentFilter = activeNav ? activeNav.dataset.filter : 'all';
            
            const cleanParams = { 
                filter: currentFilter === 'archive' ? 'archive' : 'folder', 
                folder_id: folderId,
                search: paGetSearchQuery(),
            };

            const response = await paFetch(paBuildUrl(window.paRoutes.index, cleanParams), {
                headers: {
                    'Accept': 'application/json'
                }
            });
            const parsed = await paParseFetchJson(response);
            const data = parsed.data || {};
            if (parsed.parseError || (parsed.emptyBody && !data.html)) {
                throw new Error('Respuesta inválida al abrir carpeta');
            }

            if (container) {
                container.innerHTML = data.html;
                container.style.opacity = '1';
            }
            if (data.folder) {
                updateBreadcrumb(data.folder);
            }

            setExplorerMode(true);
            initDragAndDrop();
            initContextMenu();
            applyContrast();

            if (typeof updateHash === 'function') updateHash();
        } catch (error) {
            console.error('Error navigating to folder:', error);
            if (container) container.style.opacity = '1';
        }
    };

    // View toggle buttons
    document.querySelectorAll('.pa-view-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.pa-view-btn').forEach(b => b.classList.remove('is-active'));
            this.classList.add('is-active');
            window.paExplorerView = this.dataset.view;
            const container = document.getElementById('pa-notes-container');
            if (container) {
                if (this.dataset.view === 'list') {
                    container.classList.add('is-list-view');
                } else {
                    container.classList.remove('is-list-view');
                }
            }
        });
    });

    // Folder card click → navigate inside
    function bindFolderClicks() {
        document.querySelectorAll('.pa-card--folder:not(.pa-card--placeholder):not(.pa-card--folder-archived)').forEach(card => {
            card.style.cursor = 'pointer';
            card.onclick = function(e) {
                if (e.target.closest('.pa-folder-delete') || e.target.closest('.pa-folder-action-btn')) return;
                const folderId = this.dataset.id;
                if (!folderId) return;

                const activeNav = document.querySelector('.pa-nav-item.is-active');
                const isArchive = activeNav && activeNav.dataset.filter === 'archive';

                if (!isArchive) {
                    const foldersNavItem = document.querySelector('.pa-nav-item[data-filter="folders"]');
                    if (foldersNavItem && !foldersNavItem.classList.contains('is-active')) {
                        window.__paSuppressNextNavLoadNotes = true;
                        foldersNavItem.click();
                    }
                }

                navigateToFolder(folderId);
            };
        });
    }
    bindFolderClicks();

    function syncPersonalAgendaNavChrome(navFilter) {
        navFilter = navFilter || document.querySelector('.pa-nav-item.is-active')?.dataset.filter || 'all';
        const calFilter = document.getElementById('notes-calendar-filter');
        const sectionNotes = document.getElementById('section-notes');
        if (sectionNotes) {
            sectionNotes.classList.toggle('is-mis-notas-home', navFilter === 'all');
        }
        if (calFilter) {
            calFilter.style.display = (navFilter === 'all' || navFilter === 'calendar') ? 'flex' : 'none';
        }
    }

    document.querySelectorAll('.pa-nav-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.pa-nav-item').forEach(i => i.classList.remove('is-active'));
            this.classList.add('is-active');

            const filter = this.dataset.filter;
            window.paCurrentFolderId = null;
            // Preservation: Not forced-clearing filters when switching root views to maintain user context.
            syncAllNotesPillState();

            // UI adjustments based on filter
            const timeTabs = document.querySelector('.pa-tabs-pills');

            if (filter === 'folders') {
                // Force time filter to all when entering folders to list everything by default
                document.querySelectorAll('.pa-tab-pill').forEach(i => i.classList.remove('is-active'));
                if (filterAllNotes) filterAllNotes.classList.add('is-active');
            }

            syncPersonalAgendaNavChrome(filter);
            if (timeTabs) timeTabs.style.display = (filter === 'all' || filter === 'calendar') ? 'flex' : 'none';

            if (filter === 'calendar') {
                const monthPill = document.querySelector('.pa-tab-pill[data-tab="month"]');
                if (monthPill) {
                    document.querySelectorAll('.pa-tab-pill').forEach(i => i.classList.remove('is-active'));
                    monthPill.classList.add('is-active');
                }
            }

            const folderSec = document.getElementById('section-folders');
            const archivedFolderSec = document.getElementById('section-archived-folders');
            if (archivedFolderSec) {
                archivedFolderSec.style.display = filter === 'archive' ? 'block' : 'none';
            }
            if (folderSec) {
                folderSec.style.display = (filter === 'all' || filter === 'folders') ? 'block' : 'none';

                const folderGrid = document.getElementById('pa-folders-container');
                const showAllLink = folderSec.querySelector('[onclick*="folders"]');
                const folderTitle = folderSec.querySelector('.pa-section-title');

                if (filter === 'folders') {
                    if (folderGrid) folderGrid.classList.add('is-expanded');
                    if (showAllLink) showAllLink.style.display = 'none';
                    if (folderTitle) folderTitle.textContent = 'Todas las Carpetas';
                } else {
                    if (folderGrid) folderGrid.classList.remove('is-expanded');
                    if (showAllLink) showAllLink.style.display = 'block';
                    if (folderTitle) folderTitle.textContent = 'Carpetas Recientes';
                }
            }

            const notesTitle = document.getElementById('notes-section-title');
            const showAllNotesLink = document.querySelector('[onclick="toggleNotesGrid()"]');
            const notesHeader = document.getElementById('notes-section-header');

            // Always restore notes header when switching sidebar filters
            if (notesHeader) notesHeader.style.display = '';

            if (filter === 'folders') {
                setExplorerMode(true);
                document.querySelectorAll('.pa-tab-pill').forEach(i => i.classList.remove('is-active'));
                if (notesTitle) notesTitle.textContent = 'Todas las Notas';
                if (showAllNotesLink) showAllNotesLink.style.display = 'none';
            } else {
                setExplorerMode(false);
                if (showAllNotesLink) showAllNotesLink.style.display = 'block';

                if (notesTitle) {
                    if (filter === 'archive') {
                        notesTitle.textContent = 'Notas Archivadas';
                    } else if (filter === 'trash') {
                        notesTitle.textContent = 'Papelera de Reciclaje';
                    } else if (filter === 'calendar') {
                        notesTitle.textContent = 'Calendario';
                    } else {
                        notesTitle.textContent = 'Notas Recientes';
                    }
                }

                const sectionNotes = document.getElementById('section-notes');
                if (sectionNotes) {
                    sectionNotes.classList.toggle('is-calendar-section', filter === 'calendar');
                    if (filter !== 'calendar') {
                        sectionNotes.classList.remove('is-calendar-collapsed');
                    }
                }
                const sliderEl = document.getElementById('pa-slider-container');
                if (sliderEl) {
                    sliderEl.classList.toggle('is-calendar-view', filter === 'calendar');
                }
                syncCalendarToggleButton();

                // Archive & Trash: full grid, no slider arrows
                toggleNotesGrid(filter === 'archive' || filter === 'trash');
            }

            const emptyTrashBtn = document.getElementById('pa-empty-trash-btn');
            if (emptyTrashBtn) {
                emptyTrashBtn.style.display = filter === 'trash' ? 'inline-flex' : 'none';
            }

            // Evitar loadNotes al activar "Carpetas" desde un clic en tarjeta: navigateToFolder() cargará con folder_id (sin esto, loadNotes('folders') sin carpeta gana la carrera y muestra notas incorrectas).
            if (window.__paSuppressNextNavLoadNotes) {
                window.__paSuppressNextNavLoadNotes = false;
            } else {
                loadNotes(filter);
            }
            updateHash();
            syncAllNotesPillState();
        });
    });

    document.querySelectorAll('.pa-tab-pill').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.pa-tab-pill').forEach(i => i.classList.remove('is-active'));
            this.classList.add('is-active');

            const filter = document.querySelector('.pa-nav-item.is-active').dataset.filter;
            const tab = this.dataset.tab;
            if (tab) {
                // If switching tabs, we usually reset advanced filters? 
                // The user said "Ver Todo" should reset things.
                loadNotes(filter, tab);
                updateHash();
                syncAllNotesPillState();
            }
        });
    });

    // Advanced Filters Logic
    const filterAllNotes = document.getElementById('filter-all-notes');
    const priorityPills = document.querySelectorAll('.pa-filter-pill[data-priority]');
    const folderFilter = document.getElementById('filter-folder');
    const dateFilter = document.getElementById('filter-creation-date');

    if (filterAllNotes) {
        filterAllNotes.addEventListener('click', () => {
            // Reset all
            priorityPills.forEach(p => p.classList.remove('is-active'));
            if (folderFilter) folderFilter.value = '';
            if (dateFilter) dateFilter.value = '';
            // Clear time tab to show everything
            document.querySelectorAll('.pa-tab-pill').forEach(i => i.classList.remove('is-active'));
            filterAllNotes.classList.add('is-active');
            loadNotes('all', 'all');
            updateHash();
            syncAllNotesPillState();
        });
    }

    priorityPills.forEach(pill => {
        pill.addEventListener('click', () => {
            const wasActive = pill.classList.contains('is-active');
            priorityPills.forEach(p => p.classList.remove('is-active'));
            if (!wasActive) pill.classList.add('is-active');
            
            if (filterAllNotes) filterAllNotes.classList.remove('is-active');
            loadNotes();
            updateHash();
            syncAllNotesPillState();
        });
    });

    [folderFilter, dateFilter].forEach(el => {
        if (el) {
            el.addEventListener('change', () => {
                if (filterAllNotes) filterAllNotes.classList.remove('is-active');
                loadNotes();
                updateHash();
                syncAllNotesPillState();
            });
        }
    });

    function updateHash() {
        const filter = document.querySelector('.pa-nav-item.is-active')?.dataset.filter || 'all';
        const tab = (filter === 'folders' || window.paCurrentFolderId)
            ? 'all'
            : (document.querySelector('.pa-tab-pill.is-active')?.dataset.tab || 'all');
        const priority = document.querySelector('.pa-filter-pill[data-priority].is-active')?.dataset.priority || '';
        const folderId = folderFilter?.value || window.paCurrentFolderId || '';
        const creationDate = dateFilter?.value || '';
        
        let hash = `filter=${filter}&tab=${tab}`;
        if (priority) hash += `&priority=${priority}`;
        if (folderId) hash += `&folder_id=${folderId}`;
        if (creationDate) hash += `&creation_date=${creationDate}`;
        const searchQ = paGetSearchQuery();
        if (searchQ) hash += `&search=${encodeURIComponent(searchQ)}`;

        window.location.hash = hash;
    }

    function syncAllNotesPillState() {
        const hasPriority = !!document.querySelector('.pa-filter-pill[data-priority].is-active');
        const hasFolder = !!(folderFilter?.value);
        const hasCreationDate = !!(dateFilter?.value);
        if (filterAllNotes) {
            filterAllNotes.classList.toggle('is-active', !hasPriority && !hasFolder && !hasCreationDate);
        }
    }

    function syncCalendarToggleButton() {
        const notesSection = document.getElementById('section-notes');
        const btn = document.getElementById('pa-calendar-toggle-btn');
        if (!notesSection || !btn) return;
        const expanded = !notesSection.classList.contains('is-calendar-collapsed');
        btn.textContent = expanded ? 'Ocultar' : 'Expandir';
        btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }

    // Filtering logic state
    window.paCurrentMonth = new Date().getMonth() + 1;
    window.paCurrentYear = new Date().getFullYear();

    window.loadNotes = async function(filter = null, timeFilter = null) {
        const container = document.getElementById('pa-notes-container');
        if (!container) return;
        container.style.opacity = '0.5';

        filter = filter || document.querySelector('.pa-nav-item.is-active')?.dataset.filter || 'all';

        const activeTab = document.querySelector('.pa-tab-pill.is-active');
        timeFilter = timeFilter || (activeTab ? activeTab.dataset.tab : 'all');
        if (filter === 'folders' || filter === 'folder') {
            timeFilter = 'all';
        }

        let priority = document.querySelector('.pa-filter-pill[data-priority].is-active')?.dataset.priority || '';
        let folderId = document.getElementById('filter-folder')?.value || window.paCurrentFolderId || '';
        let creationDate = document.getElementById('filter-creation-date')?.value || '';

        if (filter === 'all') {
            priority = '';
            folderId = '';
            creationDate = '';
        }

        const url = paBuildUrl(window.paRoutes.index, {
            filter,
            time_filter: timeFilter,
            month: window.paCurrentMonth,
            year: window.paCurrentYear,
            folder_id: folderId,
            priority: priority,
            creation_date: creationDate,
            search: paGetSearchQuery(),
        });

        try {
            const wasGrid = container.classList.contains('is-grid');
            const response = await paFetch(url);

            if (!response.ok) {
                console.error('loadNotes HTTP', response.status, url);
                if (typeof segobToast === 'function') {
                    segobToast('error', response.status === 404 ? 'No se encontró la agenda (revisa la URL de la aplicación).' : 'No se pudieron cargar las notas.');
                }
                return;
            }

            let html;
            const text = await response.text();
            const folderIdParam = document.getElementById('filter-folder')?.value || '';
            const looksLikeJsonEnvelope = text.trim().startsWith('{') && text.includes('"html"');
            if (filter === 'archive') {
                try {
                    const data = JSON.parse(text);
                    html = data && typeof data.html === 'string' ? data.html : text;
                    const af = document.getElementById('pa-archived-folders-container');
                    if (af && data && typeof data.archived_folders_html === 'string') {
                        af.innerHTML = data.archived_folders_html;
                    }
                    if (data && data.folders && typeof paSyncFolderFilterSelect === 'function') {
                        paSyncFolderFilterSelect(data.folders);
                    }
                } catch (_) {
                    html = text;
                }
            } else if (window.paCurrentFolderId || folderIdParam || looksLikeJsonEnvelope) {
                try {
                    const data = JSON.parse(text);
                    if (data && typeof data.html === 'string') {
                        html = data.html;
                    } else {
                        html = text;
                    }
                    if (data && data.folders && typeof paSyncFolderFilterSelect === 'function') {
                        paSyncFolderFilterSelect(data.folders);
                    }
                    if (data && typeof data.folders_html === 'string') {
                        const fg = document.getElementById('pa-folders-container');
                        if (fg) fg.innerHTML = data.folders_html;
                    }
                    const paFoldersJson = document.getElementById('pa-folders-json');
                    if (paFoldersJson && data && data.folders) {
                        paFoldersJson.textContent = JSON.stringify(data.folders);
                    }
                } catch (_) {
                    html = text;
                }
            } else {
                html = text;
            }
            container.innerHTML = html;
            syncAllNotesPillState();

            if (wasGrid) {
                toggleNotesGrid(true);
            }
        } catch (error) {
            console.error('Error loading notes:', error);
        } finally {
            container.style.opacity = '1';
            applyContrast();
            const navFilter = document.querySelector('.pa-nav-item.is-active')?.dataset.filter || 'all';
            const sliderEl = document.getElementById('pa-slider-container');
            if (sliderEl) {
                sliderEl.classList.toggle('is-calendar-view', navFilter === 'calendar');
            }
            const sectionNotes = document.getElementById('section-notes');
            if (sectionNotes) {
                sectionNotes.classList.toggle('is-calendar-section', navFilter === 'calendar');
                sectionNotes.classList.toggle('is-mis-notas-home', navFilter === 'all');
            }
            syncPersonalAgendaNavChrome(navFilter);
            syncCalendarToggleButton();
        }
    }

    window.navCalendar = function(direction) {
        if (direction === 'prev') {
            window.paCurrentMonth--;
            if (window.paCurrentMonth < 1) { window.paCurrentMonth = 12; window.paCurrentYear--; }
        } else {
            window.paCurrentMonth++;
            if (window.paCurrentMonth > 12) { window.paCurrentMonth = 1; window.paCurrentYear++; }
        }

        // When navigating manually, we should switch the time filter to 'month'
        // because 'today' and 'week' are relative to the literal current time.
        const monthPill = document.querySelector('.pa-tab-pill[data-tab="month"]');
        if (monthPill) {
            document.querySelectorAll('.pa-tab-pill').forEach(i => i.classList.remove('is-active'));
            monthPill.classList.add('is-active');
        }

        updateCalUI();
        const currentFilter = document.querySelector('.pa-nav-item.is-active')?.dataset.filter || 'all';
        window.loadNotes(currentFilter, 'month');
    };

    function updateCalUI() {
        const date = new Date(window.paCurrentYear, window.paCurrentMonth - 1);
        const monthName = date.toLocaleString('es-ES', { month: 'short', year: 'numeric' });
        const calLabel = document.querySelector('.cal-current-month');
        if (calLabel) calLabel.textContent = monthName.toUpperCase();
    }

    window.saveNote = async function(data) {
        const isEdit = !!data.id;
        const url = isEdit ? window.paRoutes.update.replace(':id', data.id) : window.paRoutes.store;
        const hasRealPassword = typeof data.password === 'string' && data.password.trim().length > 0;
        data.is_encrypted = hasRealPassword ? 1 : 0;
        data.password = hasRealPassword ? data.password.trim() : '';

        const formData = new FormData();
        if (isEdit) formData.append('_method', 'PUT');

        Object.keys(data).forEach(key => {
            if (key === 'attachments') {
                for (let i = 0; i < data.attachments.length; i++) {
                    formData.append('attachments[]', data.attachments[i]);
                }
            } else if (data[key] !== null && data[key] !== undefined) {
                formData.append(key, data[key]);
            }
        });

        try {
            const response = await fetch(url, {
                method: 'POST', // Use POST for FormData even for PUT (Laravel _method handles it)
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            if (response.ok) {
                segobToast('success', isEdit ? 'Nota actualizada' : 'Nota creada');
                paRefreshNotesAfterMutation();
                // Refresh folder counts
                loadFolders();
            } else if (response.status === 422) {
                const err = await response.json().catch(() => null);
                segobToast('warning', err?.message || 'Máximo 10 imágenes y 10 archivos por nota.');
            } else {
                segobToast('error', 'No se pudo guardar la nota');
            }
        } catch (error) {
            console.error('Save error:', error);
            segobToast('error', 'Fallo de conexión');
        }
    }

    function paEscapeHtml(s) {
        if (s == null || s === '') return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function paPriorityLabel(p) {
        if (!p || p === 'none' || p === 'all') return '';
        switch (p) {
            case 'high': return 'Alta';
            case 'medium': return 'Media';
            case 'low': return 'Baja';
            default: return '';
        }
    }

    function paBuildAttachmentsPreviewHtml(noteData) {
        return (noteData.attachments || []).map(att => {
            const name = paEscapeHtml(att.file_name || '');
            const path = att.file_path || '';
            if (att.file_type === 'image') {
                return `<div class="pa-preview-att-item"><img src="${path}" alt="${name}" onclick="window.open(this.src,'_blank');" style="cursor:pointer;"></div>`;
            }
            return `<a href="${path}" target="_blank" class="pa-preview-att-item pa-preview-att-doc"><i class="fa-solid fa-file-lines"></i><span>${name}</span></a>`;
        }).join('');
    }

    /**
     * Vista previa estilo itinerario (tarjeta limpia, chips, panel de contenido).
     * opts.popover: anclada al calendario (cierra con paCloseCalendarPreviewPopover).
     */
    function paBuildNotePreviewCardHtml(noteData, metaLine, noteId, opts) {
        opts = opts || {};
        const title = paEscapeHtml(noteData.title || 'Sin título');
        const bodyRaw = noteData.content || '';
        const body = paEscapeHtml(bodyRaw).replace(/\n/g, '<br>');
        const meta = paEscapeHtml(metaLine || '');
        const attachmentsHtml = paBuildAttachmentsPreviewHtml(noteData);
        const pri = paPriorityLabel(noteData.priority);
        const chips = [];
        if (meta) {
            chips.push(`<span class="pa-preview-chip"><i class="fa-regular fa-calendar" aria-hidden="true"></i>${meta}</span>`);
        }
        if (pri) {
            chips.push(`<span class="pa-preview-chip"><i class="fa-solid fa-flag" aria-hidden="true"></i>${pri}</span>`);
        }
        if (opts.showDecryptedBadge) {
            chips.push(`<span class="pa-preview-chip pa-preview-chip--lock"><i class="fa-solid fa-lock-open" aria-hidden="true"></i>Descifrada</span>`);
        }
        const chipsHtml = chips.length ? `<div class="pa-preview-chips">${chips.join('')}</div>` : '';

        const closeAct = opts.popover
            ? 'if(window.paCloseCalendarPreviewPopover)window.paCloseCalendarPreviewPopover()'
            : 'this.closest(\'.pa-preview-overlay\').remove()';
        const closeEditAct = opts.popover
            ? 'if(window.paCloseCalendarPreviewPopover)window.paCloseCalendarPreviewPopover();editNote(' + noteId + ')'
            : 'this.closest(\'.pa-preview-overlay\').remove();editNote(' + noteId + ')';

        return `
            <div class="pa-preview-card pa-preview-card--modern">
                <button type="button" class="pa-preview-close" aria-label="Cerrar" onclick="${closeAct}"><i class="fa-solid fa-xmark"></i></button>
                <div class="pa-preview-topbar">
                    <span class="pa-preview-kicker">Tu nota</span>
                    <button type="button" class="pa-preview-footer-btn pa-preview-topbar-edit" onclick="${closeEditAct}"><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i> Editar</button>
                </div>
                ${chipsHtml}
                <div class="pa-preview-panel pa-preview-panel--note">
                    <div class="pa-preview-panel-body">
                        <h2 class="pa-preview-title pa-preview-title--modern">${title}</h2>
                        <div class="pa-preview-body pa-preview-body--modern">${body ? body : '<span class="pa-preview-empty">Sin contenido</span>'}</div>
                    </div>
                </div>
                ${attachmentsHtml ? `<div class="pa-preview-attachments pa-preview-attachments--modern">${attachmentsHtml}</div>` : ''}
            </div>
        `;
    }

    let paCalPreviewLastAnchor = null;
    let paCalPreviewOutsideHandler = null;
    let paCalPreviewEscapeHandler = null;

    function paPositionCalendarPreviewPopover(anchorEl, popEl) {
        if (!anchorEl || !popEl) return;
        const apply = () => {
            const rect = anchorEl.getBoundingClientRect();
            const pad = 10;
            const gap = 10;
            const vw = window.innerWidth;
            const vh = window.innerHeight;
            const measureEl = popEl.querySelector('.pa-preview-card--modern, .pa-cal-decrypt-card') || popEl;
            let pw = measureEl.offsetWidth || popEl.offsetWidth;
            let ph = measureEl.offsetHeight || popEl.offsetHeight;
            if (!pw || pw < 80) pw = 360;
            if (!ph || ph < 40) ph = 200;

            const clamp = (v, a, b) => {
                const hi = Math.max(a, b);
                const lo = Math.min(a, b);
                return Math.min(Math.max(v, lo), hi);
            };

            const fitsH = (left) => left >= pad && left + pw <= vw - pad;

            let left;
            let top;

            /* Preferir a la izquierda del icono (no tapa la miniatura ni empuja hacia el borde derecho). */
            if (fitsH(rect.left - pw - gap)) {
                left = rect.left - pw - gap;
                top = rect.top + rect.height / 2 - ph / 2;
            } else if (fitsH(rect.right + gap)) {
                left = rect.right + gap;
                top = rect.top + rect.height / 2 - ph / 2;
            } else {
                left = rect.left + rect.width / 2 - pw / 2;
                top = rect.bottom + gap;
                if (top + ph > vh - pad) {
                    top = rect.top - ph - gap;
                }
            }

            left = clamp(left, pad, vw - pw - pad);
            top = clamp(top, pad, vh - ph - pad);

            popEl.style.position = 'fixed';
            popEl.style.left = `${Math.round(left)}px`;
            popEl.style.top = `${Math.round(top)}px`;
            popEl.style.zIndex = '10050';
            popEl.style.transform = 'translateZ(0)';
        };
        requestAnimationFrame(() => {
            requestAnimationFrame(apply);
        });
    }

    function paCalPreviewReposition() {
        const pop = document.getElementById('pa-cal-preview-popover');
        if (pop && paCalPreviewLastAnchor) {
            paPositionCalendarPreviewPopover(paCalPreviewLastAnchor, pop);
        }
    }

    function paCalPreviewBindDismiss(popEl, anchorEl) {
        paCalPreviewOutsideHandler = (e) => {
            if (!document.getElementById('pa-cal-preview-popover')) return;
            if (popEl.contains(e.target) || anchorEl.contains(e.target)) return;
            paCloseCalendarPreviewPopover();
        };
        document.addEventListener('mousedown', paCalPreviewOutsideHandler, true);
        paCalPreviewEscapeHandler = (e) => {
            if (e.key === 'Escape') paCloseCalendarPreviewPopover();
        };
        document.addEventListener('keydown', paCalPreviewEscapeHandler);
        window.addEventListener('resize', paCalPreviewReposition);
        const sc = document.querySelector('.pa-schedule-container-scroll');
        if (sc) sc.addEventListener('scroll', paCalPreviewReposition, { passive: true });
    }

    function paCloseCalendarPreviewPopover() {
        const el = document.getElementById('pa-cal-preview-popover');
        if (el) el.remove();
        paCalPreviewLastAnchor = null;
        if (paCalPreviewOutsideHandler) {
            document.removeEventListener('mousedown', paCalPreviewOutsideHandler, true);
            paCalPreviewOutsideHandler = null;
        }
        if (paCalPreviewEscapeHandler) {
            document.removeEventListener('keydown', paCalPreviewEscapeHandler);
            paCalPreviewEscapeHandler = null;
        }
        window.removeEventListener('resize', paCalPreviewReposition);
        const sc = document.querySelector('.pa-schedule-container-scroll');
        if (sc) sc.removeEventListener('scroll', paCalPreviewReposition);
    }

    window.paCloseCalendarPreviewPopover = paCloseCalendarPreviewPopover;

    function paOpenCalendarPreviewPopover(anchorEl, innerHtml) {
        paCloseCalendarPreviewPopover();
        paCalPreviewLastAnchor = anchorEl;
        const pop = document.createElement('div');
        pop.id = 'pa-cal-preview-popover';
        pop.className = 'pa-cal-preview-popover';
        pop.setAttribute('role', 'dialog');
        pop.setAttribute('aria-modal', 'true');
        pop.innerHTML = innerHtml;
        document.body.appendChild(pop);
        paPositionCalendarPreviewPopover(anchorEl, pop);
        paCalPreviewBindDismiss(pop, anchorEl);
    }

    /** Solo anclar si el clic vino explícitamente de una miniatura del calendario (evita Swal al llamar decryptNote(id) sin elemento). */
    function paResolveCalendarAnchor(sourceEl) {
        if (!sourceEl || !sourceEl.closest) return null;
        const pill = sourceEl.closest('.pa-cal-note');
        if (pill && document.getElementById('pa-schedule-wrapper')?.contains(pill)) return pill;
        return null;
    }

    function paOpenCalendarDecryptPopover(anchorEl, noteId, noteData, action) {
        paCloseCalendarPreviewPopover();
        paCalPreviewLastAnchor = anchorEl;
        const title = paEscapeHtml(noteData.title || 'Sin título');
        const pop = document.createElement('div');
        pop.id = 'pa-cal-preview-popover';
        pop.className = 'pa-cal-preview-popover pa-cal-preview-popover--decrypt';
        pop.setAttribute('role', 'dialog');
        pop.innerHTML = `
            <div class="pa-preview-card pa-preview-card--modern pa-cal-decrypt-card">
                <button type="button" class="pa-preview-close" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
                <div class="pa-preview-topbar">
                    <span class="pa-preview-kicker">Nota cifrada</span>
                </div>
                <p class="pa-cal-decrypt-note-title">${title}</p>
                <label class="pa-cal-decrypt-label" for="pa-cal-decrypt-pw">Contraseña</label>
                <input type="password" id="pa-cal-decrypt-pw" class="pa-cal-decrypt-input" autocomplete="off" placeholder="Introduce la contraseña">
                <p class="pa-cal-decrypt-err" id="pa-cal-decrypt-err" hidden></p>
                <div class="pa-cal-decrypt-actions">
                    <button type="button" class="pa-cal-decrypt-cancel pa-preview-footer-btn">Cancelar</button>
                    <button type="button" class="pa-cal-decrypt-submit pa-preview-footer-btn pa-preview-footer-btn--primary">Desbloquear</button>
                </div>
            </div>
        `;
        document.body.appendChild(pop);
        paPositionCalendarPreviewPopover(anchorEl, pop);
        paCalPreviewBindDismiss(pop, anchorEl);

        const close = () => paCloseCalendarPreviewPopover();
        pop.querySelector('.pa-preview-close').addEventListener('click', close);
        pop.querySelector('.pa-cal-decrypt-cancel').addEventListener('click', close);
        const inputPw = pop.querySelector('#pa-cal-decrypt-pw');
        const errEl = pop.querySelector('#pa-cal-decrypt-err');

        const submitDecrypt = async () => {
            const password = inputPw.value;
            errEl.hidden = true;
            if (!password || !password.trim()) {
                errEl.textContent = 'Introduce la contraseña.';
                errEl.hidden = false;
                return;
            }
            try {
                const response = await fetch(window.paRoutes.decrypt.replace(':id', noteId), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ password: password.trim() })
                });
                const parsed = await paParseFetchJson(response);
                const data = parsed.data;
                if (data.success) {
                    noteData.content = data.content;
                    noteData.password = password.trim();
                    window._decryptedNotes[noteId] = { content: data.content, password: password.trim() };
                    if (action === 'edit') {
                        paCloseCalendarPreviewPopover();
                        window.openPersonalNoteModal(noteData);
                        return;
                    }
                    const dateLine = noteData.displayDate || '';
                    pop.innerHTML = paBuildNotePreviewCardHtml(noteData, dateLine, noteId, { showDecryptedBadge: true, popover: true });
                    paPositionCalendarPreviewPopover(anchorEl, pop);
                } else {
                    errEl.textContent = data.message || 'Contraseña incorrecta';
                    errEl.hidden = false;
                }
            } catch (error) {
                console.error(error);
                if (typeof segobToast === 'function') segobToast('error', 'Fallo de conexión');
            }
        };

        pop.querySelector('.pa-cal-decrypt-submit').addEventListener('click', submitDecrypt);
        inputPw.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') submitDecrypt();
        });
        setTimeout(() => inputPw.focus(), 50);
    }

    // Note Preview (read-only full content view); sourceEl = miniatura calendario (.pa-cal-note)
    window.previewNote = function(id, sourceEl) {
        const card = document.querySelector(`.pa-card--note[data-id="${id}"]`);
        const pill = sourceEl && sourceEl.closest ? sourceEl.closest('.pa-cal-note') : null;
        const noteData = paParseNoteDataFromEl(pill) || paParseNoteDataFromEl(card);
        if (!noteData) return;
        if (noteData.is_encrypted) { decryptNote(id, 'preview', pill || sourceEl); return; }

        const dateLine = paResolveCardDateLineFromEl(card) || noteData.displayDate || '';

        const calAnchor = paResolveCalendarAnchor(sourceEl);
        if (calAnchor) {
            const html = paBuildNotePreviewCardHtml(noteData, dateLine, id, { showDecryptedBadge: false, popover: true });
            paOpenCalendarPreviewPopover(calAnchor, html);
            return;
        }

        const overlay = document.createElement('div');
        overlay.className = 'pa-preview-overlay';
        overlay.innerHTML = paBuildNotePreviewCardHtml(noteData, dateLine, id, { showDecryptedBadge: false, popover: false });
        overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });
        document.body.appendChild(overlay);
    };

    document.getElementById('pa-notes-container')?.addEventListener('click', function(e) {
        const btn = e.target.closest('.pa-cal-note');
        if (!btn || !document.getElementById('pa-schedule-wrapper')?.contains(btn)) return;
        e.preventDefault();
        const id = btn.dataset.noteId;
        if (!id) return;
        const noteId = parseInt(id, 10);
        if (Number.isNaN(noteId)) return;
        if (btn.dataset.noteEncrypted === '1') {
            window.decryptNote(noteId, 'preview', btn);
        } else {
            window.previewNote(noteId, btn);
        }
    });

    window.editNote = function(id) {
        const card = document.querySelector(`.pa-card--note[data-id="${id}"]`);
        const pill = document.querySelector(`.pa-cal-note[data-note-id="${id}"]`);
        const noteData = paParseNoteDataFromEl(card) || paParseNoteDataFromEl(pill);
        if (!noteData) return;

        if (noteData.is_encrypted) {
            decryptNote(id, 'edit', pill || null);
        } else {
            window.openPersonalNoteModal(noteData);
        }
    };

    // Session cache for decrypted notes { noteId: { content, password } }
    if (!window._decryptedNotes) window._decryptedNotes = {};

    window.decryptNote = async function(id, action, sourceEl) {
        action = action || 'preview'; // 'preview' or 'edit'
        const card = document.querySelector(`.pa-card--note[data-id="${id}"]`);
        const pill = sourceEl && sourceEl.closest ? sourceEl.closest('.pa-cal-note') : document.querySelector(`.pa-cal-note[data-note-id="${id}"]`);
        const noteData = paParseNoteDataFromEl(card) || paParseNoteDataFromEl(pill);
        if (!noteData) {
            if (typeof segobToast === 'function') {
                segobToast('error', 'No se pudo leer los datos de la nota.');
            }
            return;
        }

        const calendarAnchor = paResolveCalendarAnchor(sourceEl);
        const useCalendarInline = !!calendarAnchor;

        // If already decrypted this session, use cached content
        if (window._decryptedNotes[id]) {
            noteData.content = window._decryptedNotes[id].content;
            noteData.password = window._decryptedNotes[id].password;
            if (action === 'edit') {
                window.openPersonalNoteModal(noteData);
            } else if (useCalendarInline) {
                showDecryptedPreview(noteData, card || pill, { popover: true, anchorEl: calendarAnchor });
            } else {
                showDecryptedPreview(noteData, card || pill);
            }
            return;
        }

        if (useCalendarInline) {
            paOpenCalendarDecryptPopover(calendarAnchor, id, noteData, action);
            return;
        }

        const { value: password } = await swalAlert.fire({
            title: 'Nota Cifrada',
            input: 'password',
            inputLabel: 'Introduce la contraseña',
            inputPlaceholder: 'Contraseña...',
            inputAttributes: {
                style: 'max-width: 200px; margin: 0 auto;'
            },
            showCancelButton: true,
            confirmButtonText: 'Desbloquear',
            cancelButtonText: 'Cancelar',
            customClass: {
                popup: 'pa-swal-popup tm-swal-popup',
                confirmButton: 'tm-swal-confirm',
                cancelButton: 'tm-swal-cancel'
            }
        });

        if (password) {
            try {
                const response = await fetch(window.paRoutes.decrypt.replace(':id', id), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ password })
                });
                const parsed = await paParseFetchJson(response);
                const data = parsed.data;
                if (data.success) {
                    noteData.content = data.content;
                    noteData.password = password;
                    // Cache for session
                    window._decryptedNotes[id] = { content: data.content, password: password };
                    if (action === 'edit') {
                        window.openPersonalNoteModal(noteData);
                    } else {
                        showDecryptedPreview(noteData, card || pill);
                    }
                } else {
                    segobToast('error', data.message || 'Contraseña incorrecta');
                }
            } catch (error) {
                console.error(error);
                segobToast('error', 'Fallo de conexión');
            }
        }
    };

    function paResolveCardDateLineFromEl(el) {
        if (!el || !el.querySelector) return '';
        const rem = el.querySelector('.pa-card-reminder');
        if (rem) return rem.textContent.replace(/\s+/g, ' ').trim();
        const d = el.querySelector('.pa-card-date');
        return d ? d.textContent.trim() : '';
    }

    function showDecryptedPreview(noteData, cardOrPill, opts) {
        opts = opts || {};
        const dateLine = paResolveCardDateLineFromEl(cardOrPill) || (noteData.displayDate || '');

        const usePop = opts.popover && opts.anchorEl;
        const html = paBuildNotePreviewCardHtml(noteData, dateLine, noteData.id, { showDecryptedBadge: true, popover: usePop });
        if (usePop) {
            paOpenCalendarPreviewPopover(opts.anchorEl, html);
            return;
        }

        const overlay = document.createElement('div');
        overlay.className = 'pa-preview-overlay';
        overlay.innerHTML = html;
        overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });
        document.body.appendChild(overlay);
    }

    window.removeAttachment = async function(attachmentId, element) {
        element.style.opacity = '0.3';
        try {
            const response = await fetch(window.paRoutes.attachmentsDestroy.replace(':id', attachmentId), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ _method: 'DELETE' })
            });
            if (response.ok) {
                element.closest('.pa-att-item')?.remove();
            }
        } catch (e) {
            element.style.opacity = '1';
        }
    };

    window.previewNewAttachments = function(input) {
        const container = document.getElementById('attachments-preview');
        if (!container) return;

        const MAX_IMAGES = 10, MAX_FILES = 10;
        const existingImages = container.querySelectorAll('.pa-att-item img').length;
        const existingDocs  = container.querySelectorAll('.pa-att-item .pa-att-file-icon').length;
        let addedImages = 0, addedDocs = 0;

        Array.from(input.files).forEach(file => {
            const isImage = file.type.startsWith('image/');
            if (isImage && (existingImages + addedImages) >= MAX_IMAGES) {
                segobToast('warning', `Máximo ${MAX_IMAGES} imágenes por nota`);
                return;
            }
            if (!isImage && (existingDocs + addedDocs) >= MAX_FILES) {
                segobToast('warning', `Máximo ${MAX_FILES} archivos por nota`);
                return;
            }

            const fileIndex = window._pendingAttachments.length;
            window._pendingAttachments.push(file);
            const item = document.createElement('div');
            item.className = 'pa-att-item pa-att-item--new';
            item.dataset.fileIndex = fileIndex;
            if (isImage) {
                addedImages++;
                const reader = new FileReader();
                reader.onload = (e) => {
                    item.innerHTML = `<img src="${e.target.result}" alt="${file.name}"><div class="pa-att-name" title="${file.name}">${file.name}</div><button class="pa-att-remove" onclick="removeNewAttachment(this.closest('.pa-att-item'))"><i class="fa-solid fa-xmark"></i></button>`;
                };
                reader.readAsDataURL(file);
            } else {
                addedDocs++;
                item.innerHTML = `<div class="pa-att-file-icon"><i class="fa-solid fa-file-lines"></i></div><div class="pa-att-name" title="${file.name}">${file.name}</div><button class="pa-att-remove" onclick="removeNewAttachment(this.closest('.pa-att-item'))"><i class="fa-solid fa-xmark"></i></button>`;
            }
            container.appendChild(item);
        });
        input.value = '';
        updateAttCounter();
    };

    window.updateAttCounter = function() {
        const container = document.getElementById('attachments-preview');
        const counter = document.getElementById('att-counter');
        if (!container || !counter) return;
        const imgs = container.querySelectorAll('.pa-att-item img').length;
        const docs = container.querySelectorAll('.pa-att-item .pa-att-file-icon').length;
        counter.textContent = (imgs || docs) ? `${imgs}/10 img · ${docs}/10 arch` : '';
    };

    window.removeNewAttachment = function(item) {
        const idx = parseInt(item.dataset.fileIndex);
        if (!isNaN(idx)) window._pendingAttachments[idx] = null;
        item.remove();
        updateAttCounter();
    };

    function paRefreshNotesAfterMutation() {
        if (document.getElementById('pa-notes-container')) {
            if (window.paCurrentFolderId) {
                navigateToFolder(window.paCurrentFolderId);
            } else {
                const activeNav = document.querySelector('.pa-nav-item.is-active');
                if (activeNav) {
                    loadNotes(activeNav.dataset.filter);
                } else {
                    loadNotes();
                }
            }
            return;
        }
        if (document.getElementById('pa-home-personal-notes')) {
            window.location.reload();
        }
    }

    window.archiveNote = async function(id) {
        const result = await swalAlert.fire({
            title: '¿Archivar nota?',
            text: 'La nota se moverá a la sección de archivadas.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, archivar',
            cancelButtonText: 'Cancelar',
            customClass: {
                confirmButton: 'tm-swal-confirm btn-primary',
                cancelButton: 'tm-swal-cancel'
            }
        });

        if (result.isConfirmed) {
            try {
                const response = await fetch(window.paRoutes.archive.replace(':id', id), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                if (response.ok) {
                    segobToast('success', 'Nota archivada');
                    loadFolders();
                } else {
                    console.warn('Archive response:', response.status);
                    segobToast('warning', 'Posible error al archivar');
                }
            } catch (error) {
                console.error('Archive fetch error:', error);
            }
            paRefreshNotesAfterMutation();
        }
    };

    window.restoreNote = async function(id) {
        try {
            const response = await fetch(window.paRoutes.restore.replace(':id', id), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            if (response.ok) {
                segobToast('success', 'Nota restaurada');
            } else {
                console.warn('Restore response:', response.status);
                segobToast('warning', 'Posible error al restaurar');
            }
        } catch (error) {
            console.error('Restore fetch error:', error);
        }
        paRefreshNotesAfterMutation();
        // Refresh folder counts
        loadFolders();
    };

    window.deleteNote = async function(id, permanent = false) {
        const result = await swalAlert.fire({
            title: permanent ? '¿Eliminar permanentemente?' : '¿Enviar a la papelera?',
            text: permanent ? 'Esta acción no se puede deshacer.' : 'Podrás restaurarla desde la papelera.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        });

        if (result.isConfirmed) {
            try {
                const response = await fetch(window.paRoutes.destroy.replace(':id', id), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ _method: 'DELETE' })
                });
                if (response.ok) {
                    segobToast('success', 'Nota eliminada');
                    loadFolders();
                } else {
                    console.warn('Delete response:', response.status);
                    segobToast('error', 'Error al eliminar');
                }
            } catch (error) {
                console.error('Delete fetch error:', error);
                segobToast('error', 'Fallo de conexión');
            }
            paRefreshNotesAfterMutation();
        }
    };

    window.toggleAllFolders = function() {
        const container = document.getElementById('pa-folders-container');
        if (container) container.classList.toggle('is-expanded');
    };

    window.toggleNotesGrid = function(forceGrid) {
        const container = document.getElementById('pa-notes-container');
        const slider = document.getElementById('pa-slider-container');
        const toggleBtn = document.getElementById('pa-notes-toggle');
        if (!container) return;

        const isGrid = forceGrid === true || (forceGrid === undefined && !container.classList.contains('is-grid'));

        container.classList.toggle('is-grid', isGrid);
        if (slider) {
            slider.classList.toggle('is-grid-view', isGrid);
            // Hide slider buttons if in grid view
            const btns = slider.querySelectorAll('.pa-slider-btn');
            btns.forEach(b => b.style.display = isGrid ? 'none' : 'flex');
        }

        if (toggleBtn) {
            toggleBtn.textContent = isGrid ? 'Ver menos' : 'Ver todas';
        }
    };

    /**
     * Slider Navigation
     */
    window.slideNotes = function(direction) {
        const container = document.getElementById('pa-notes-container');
        if (!container) return;
        const scrollAmount = 300;
        if (direction === 'left') {
            container.scrollLeft -= scrollAmount;
        } else {
            container.scrollLeft += scrollAmount;
        }
    };

    window.slideFolders = function(direction) {
        const container = document.getElementById('pa-folders-container');
        if (!container) return;
        const scrollAmount = 280;
        if (direction === 'left') {
            container.scrollLeft -= scrollAmount;
        } else {
            container.scrollLeft += scrollAmount;
        }
    };

    window.slideArchivedFolders = function(direction) {
        const container = document.getElementById('pa-archived-folders-container');
        if (!container) return;
        const scrollAmount = 280;
        if (direction === 'left') {
            container.scrollLeft -= scrollAmount;
        } else {
            container.scrollLeft += scrollAmount;
        }
    };

    // --- Lógica de Reubicación (Drag & Drop + Context Menu) ---
    let selectedNoteId = null;

    function initDragAndDrop() {
        const notes = document.querySelectorAll('.pa-card--note:not(.pa-card--note-home)');
        const folders = document.querySelectorAll('.pa-card--folder:not(.pa-card--placeholder):not(.pa-card--folder-archived)');

        notes.forEach(note => {
            note.addEventListener('dragstart', function(e) {
                if (this.classList.contains('pa-card--placeholder')) return;
                this.classList.add('dragging');
                e.dataTransfer.setData('text/plain', this.dataset.id);
                e.dataTransfer.effectAllowed = 'move';
            });

            note.addEventListener('dragend', function() {
                this.classList.remove('dragging');
                document.querySelectorAll('.pa-card--folder.drag-over').forEach(f => f.classList.remove('drag-over'));
            });
        });

        folders.forEach(folder => {
            folder.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                this.classList.add('drag-over');
            });

            folder.addEventListener('dragleave', function() {
                this.classList.remove('drag-over');
            });

            folder.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('drag-over');
                const noteId = e.dataTransfer.getData('text/plain');
                const folderId = this.dataset.id;
                const folderName = this.querySelector('.pa-card-title')?.textContent || 'esta carpeta';

                // Confirm before moving
                swalAlert.fire({
                    title: '¿Mover nota?',
                    html: `La nota se moverá a la carpeta <b>"${folderName}"</b>.`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: '<i class="fa-solid fa-folder-open"></i> Mover',
                    cancelButtonText: 'Cancelar',
                    customClass: {
                        popup: 'pa-swal-popup',
                        confirmButton: 'tm-swal-confirm btn-primary',
                        cancelButton: 'tm-swal-cancel'
                    }
                }).then(result => {
                    if (result.isConfirmed) {
                        moveNoteToFolder(folderId, noteId);
                    }
                });
            });
        });
    }

    function initContextMenu() {
        const notes = document.querySelectorAll('.pa-card--note:not(.pa-card--placeholder):not(.pa-card--note-home)');
        const menu = document.getElementById('pa-context-menu');
        const submenu = document.getElementById('pa-folder-submenu');
        const moveItem = document.getElementById('ctx-move');

        if (!menu) return;

        notes.forEach(note => {
            note.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                selectedNoteId = this.dataset.id;

                menu.style.display = 'block';
                menu.style.left = `${e.pageX}px`;
                menu.style.top = `${e.pageY}px`;
                submenu.style.display = 'none';
            });
        });

        if (moveItem) {
            let timeout;
            moveItem.onmouseenter = () => {
                clearTimeout(timeout);
                submenu.style.display = 'block';
            };
            moveItem.onmouseleave = () => {
                timeout = setTimeout(() => {
                    if (!submenu.matches(':hover')) submenu.style.display = 'none';
                }, 200);
            };
            submenu.onmouseenter = () => clearTimeout(timeout);
            submenu.onmouseleave = () => submenu.style.display = 'none';
        }

        document.addEventListener('click', (e) => {
            if (!menu.contains(e.target)) {
                menu.style.display = 'none';
                submenu.style.display = 'none';
            }
        });
    }

    function applyContrast() {
        document.querySelectorAll('.pa-card').forEach(card => {
            const bg = card.style.backgroundColor || window.getComputedStyle(card).backgroundColor;
            if (!bg || bg === 'transparent' || bg === 'rgba(0, 0, 0, 0)') return;

            const m = bg.match(/\d+/g);
            if (m && m.length >= 3) {
                // Si tiene canal alpha y es 0 (transparente), ignorar
                if (m.length === 4 && parseFloat(m[3]) === 0) return;

                const lum = (parseInt(m[0]) * 299 + parseInt(m[1]) * 587 + parseInt(m[2]) * 114) / 1000;
                card.classList.remove('text-light', 'text-dark');
                if (lum < 150) {
                    card.classList.add('text-light');
                } else {
                    card.classList.add('text-dark');
                }
            }
        });
    }

    window.moveNoteToFolder = function(folderId, noteId = null) {
        const targetNoteId = noteId || selectedNoteId;
        if (!targetNoteId) return;

        fetch(window.paRoutes.move.replace(':id', targetNoteId), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ folder_id: folderId })
        })
        .then(paParseFetchJson)
        .then(({ data }) => {
            if (data && data.success) {
                const menu = document.getElementById('pa-context-menu');
                if (menu) menu.style.display = 'none';

                // Refresh folder counts via AJAX (cleaner than manual DOM manipulation)
                loadFolders();

                paRefreshNotesAfterMutation();
                segobToast('success', 'Nota movida correctamente');
            } else {
                segobToast('error', 'Error al mover la nota');
            }
        });
    };

    // Inicializar
    initDragAndDrop();
    initContextMenu();
    bindFolderClicks();
    syncAllNotesPillState();
    syncPersonalAgendaNavChrome();
    syncCalendarToggleButton();

    (function syncTrashEmptyBtnVisibility() {
        const btn = document.getElementById('pa-empty-trash-btn');
        const f = document.querySelector('.pa-nav-item.is-active')?.dataset.filter;
        if (btn) btn.style.display = f === 'trash' ? 'inline-flex' : 'none';
    })();

    document.getElementById('pa-calendar-toggle-btn')?.addEventListener('click', () => {
        const notesSection = document.getElementById('section-notes');
        const currentFilter = document.querySelector('.pa-nav-item.is-active')?.dataset.filter || 'all';
        if (!notesSection || currentFilter !== 'calendar') return;
        notesSection.classList.toggle('is-calendar-collapsed');
        syncCalendarToggleButton();
    });

    window.deleteFolder = function(id, name) {
        swalAlert.fire({
            title: `¿Eliminar carpeta "${name}"?`,
            html: `
                <div class="text-start" style="font-size: 0.9rem; color: #666; padding: 10px;">
                    <p>Elige qué hacer con las notas que contiene esta carpeta:</p>
                    <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 15px; border-top: 1px solid #eee; padding-top: 15px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 5px;">
                            <input type="radio" name="delete_mode" value="move" checked style="width: 18px; height: 18px;">
                            <span><b>Conservar notas</b> <br><small>Se moverán a la sección principal</small></span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 5px;">
                            <input type="radio" name="delete_mode" value="all" style="width: 18px; height: 18px;">
                            <span style="color: #dc3545;"><b>Borrar todo</b> <br><small>Se eliminará la carpeta y sus notas</small></span>
                        </label>
                    </div>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
            customClass: {
                popup: 'pa-swal-popup',
                confirmButton: 'tm-swal-confirm btn-danger',
                cancelButton: 'tm-swal-cancel'
            },
            preConfirm: () => {
                const modeInput = document.querySelector('input[name="delete_mode"]:checked');
                return { delete_notes: modeInput ? modeInput.value === 'all' : false };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(window.paRoutes.foldersDestroy.replace(':id', id), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ _method: 'DELETE', delete_notes: result.value.delete_notes })
                })
                .then(paParseFetchJson)
                .then(({ data }) => {
                    if (data && data.success) {
                        segobToast('success', 'Carpeta eliminada');
                        loadFolders();
                    } else if (typeof segobToast === 'function') {
                        segobToast('error', (data && data.message) || 'No se pudo eliminar');
                    }
                })
                .catch(() => {
                    if (typeof segobToast === 'function') segobToast('error', 'Fallo de conexión');
                });
            }
        });
    };

    // Hook into MutationObserver for notes grid and folders grid
    const notesContainer = document.getElementById('pa-notes-container');
    const foldersContainer = document.getElementById('pa-folders-container');
    const archivedFoldersContainer = document.getElementById('pa-archived-folders-container');
    const gridObserver = new MutationObserver(() => {
        initDragAndDrop();
        initContextMenu();
        applyContrast();
        bindFolderClicks();
    });
    if (notesContainer) gridObserver.observe(notesContainer, { childList: true });
    if (foldersContainer) gridObserver.observe(foldersContainer, { childList: true });
    if (archivedFoldersContainer) gridObserver.observe(archivedFoldersContainer, { childList: true });
    const homePersonalNotesEl = document.getElementById('pa-home-personal-notes');
    if (homePersonalNotesEl) {
        gridObserver.observe(homePersonalNotesEl, { childList: true });
        applyContrast();
    }

    // Handle initial state from URL hash
    function applyUrlState() {
        const hash = window.location.hash.replace('#', '');
        if (!hash) return;

        const params = new URLSearchParams(hash);
        const filter = params.get('filter');
        const tab = params.get('tab');
        const folderId = params.get('folder_id');
        const priority = params.get('priority');
        const creationDate = params.get('creation_date');
        const searchFromHash = params.get('search');

        if (searchFromHash !== null) {
            const si = document.getElementById('pa-search');
            if (si) {
                si.value = searchFromHash;
                paSyncSearchClearUi();
            }
        }

        if (folderId) {
            // If it's a specific folder in sidebar 'folders' mode, or just a folder filter
            const folderSelect = document.getElementById('filter-folder');
            if (folderSelect) {
                folderSelect.value = folderId;
            } else {
                navigateToFolder(folderId);
            }
        }

        if (priority) {
            const pill = document.querySelector(`.pa-filter-pill[data-priority="${priority}"]`);
            if (pill) pill.click();
        }

        if (creationDate) {
            const dateInput = document.getElementById('filter-creation-date');
            if (dateInput) {
                dateInput.value = creationDate;
                // Trigger change to update UI group class
                dateInput.dispatchEvent(new Event('change'));
            }
        }

        if (filter) {
            const navItem = document.querySelector(`.pa-nav-item[data-filter="${filter}"]`);
            if (navItem && !navItem.classList.contains('is-active')) {
                navItem.click();
            }
        }

        if (tab) {
            const tabPill = document.querySelector(`.pa-tab-pill[data-tab="${tab}"]`);
            if (tabPill && !tabPill.classList.contains('is-active')) {
                // Large delay to ensure AJAX result doesn't overwrite
                setTimeout(() => tabPill.click(), 300);
            }
        }
    }

    // Delay a bit to ensure other listeners are ready
    setTimeout(applyUrlState, 200);
});
