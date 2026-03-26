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

window.openPersonalNoteModal = function(noteData = null) {
    window._pendingAttachments = [];
    const foldersContainer = document.getElementById('pa-folders-json');
    const folders = foldersContainer ? JSON.parse(foldersContainer.textContent) : [];
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
                    <button type="button" id="btn-toggle-priority" class="pa-toggle-btn ${noteData?.priority && noteData.priority !== 'medium' ? 'is-active' : ''}" style="margin: 0; padding: 6px 10px; font-size: 0.75rem;">
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
                    <div id="wrapper-priority" style="${noteData?.priority && noteData.priority !== 'medium' ? '' : 'display:none;'}">
                        <label class="swal2-label" style="font-size: 0.75rem;">Prioridad</label>
                        <select id="note-priority" class="swal2-select" style="height: 34px !important; font-size: 0.8rem !important;">
                            <option value="low" ${noteData?.priority === 'low' ? 'selected' : ''}>Baja</option>
                            <option value="medium" ${noteData?.priority === 'medium' || !noteData ? 'selected' : ''}>Media</option>
                            <option value="high" ${noteData?.priority === 'high' ? 'selected' : ''}>Alta</option>
                        </select>
                    </div>
                    <div id="wrapper-date" style="${noteData?.scheduled_date ? '' : 'display:none;'}">
                        <label class="swal2-label" style="font-size: 0.75rem;">Fecha</label>
                        <input type="date" id="note-date" class="swal2-input" value="${noteData?.scheduled_date || ''}" style="height: 34px !important; font-size: 0.8rem !important;">
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
                { btnId: 'btn-toggle-priority', wrapperId: 'wrapper-priority', inputId: 'note-priority', resetValue: 'medium' },
                { btnId: 'btn-toggle-encrypt', wrapperId: 'password-container', inputId: 'note-password', resetValue: '' }
            ];

            setups.forEach(s => {
                const btn = document.getElementById(s.btnId);
                const wrapper = document.getElementById(s.wrapperId);
                if (!btn || !wrapper) return;
                btn.addEventListener('click', () => {
                    const isActive = btn.classList.toggle('is-active');
                    wrapper.style.display = isActive ? 'block' : 'none';
                    if (!isActive) document.getElementById(s.inputId).value = s.resetValue;
                });
            });
        },
        preConfirm: () => {
            const isReminderActive = document.getElementById('btn-toggle-reminder').classList.contains('is-active');
            const isPriorityActive = document.getElementById('btn-toggle-priority').classList.contains('is-active');
            const isEncryptActive = document.getElementById('btn-toggle-encrypt').classList.contains('is-active');

            return {
                id: noteData?.id,
                title: document.getElementById('note-title').value,
                content: document.getElementById('note-content').value,
                folder_id: document.getElementById('note-folder').value,
                color: document.getElementById('note-color').value,
                priority: isPriorityActive ? document.getElementById('note-priority').value : 'medium',
                scheduled_date: isReminderActive ? document.getElementById('note-date').value : null,
                is_encrypted: isEncryptActive ? 1 : 0,
                password: document.getElementById('note-password').value,
                attachments: (window._pendingAttachments || []).filter(f => f !== null)
            };
        }
    }).then((result) => {
        if (result.isConfirmed) saveNote(result.value);
    });
};

window.openFolderModal = function() {
    swalAlert.fire({
        title: `<div>Nueva Carpeta</div><i class="fa-solid fa-xmark" style="cursor:pointer; font-size: 1rem; opacity: 0.5;" onclick="Swal.close()"></i>`,
        html: `
            <div class="swal-form-container">
                <label class="swal2-label">Nombre de la carpeta</label>
                <input id="folder-name" class="swal2-input" placeholder="Ej. Proyectos...">

                <div style="display: flex; align-items: center; gap: 15px; margin-top: 10px;">
                    <label class="swal2-label" style="margin: 0;">Identificador visual</label>
                    <div class="pa-color-circle" id="folder-color-preview" style="background: #e3f2fd; border: 1.5px solid #ddd; cursor: pointer; width: 32px; height: 32px; border-radius: 50%; position: relative !important;">
                        <input type="color" id="folder-color" value="#e3f2fd"
                               style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; cursor: pointer; border: none; padding: 0; background: transparent; opacity: 0; -webkit-appearance: none; appearance: none; display: block !important;">
                    </div>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Crear',
        cancelButtonText: 'Cancelar',
        customClass: {
            popup: 'pa-swal-popup tm-swal-popup',
            title: 'tm-swal-title',
            htmlContainer: 'tm-swal-text',
            confirmButton: 'tm-swal-confirm',
            cancelButton: 'tm-swal-cancel'
        },
        didOpen: () => {
            const nameInput = document.getElementById('folder-name');
            if (nameInput) {
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
                colorInput.addEventListener('input', (e) => {
                    colorPreview.style.background = e.target.value;
                });
            }
        },
        preConfirm: () => {
            return {
                name: document.getElementById('folder-name').value,
                color: document.getElementById('folder-color').value
            };
        }
    }).then((result) => {
        if (result.isConfirmed) saveFolder(result.value);
    });
};

async function saveFolder(data) {
    try {
        const response = await fetch(window.paRoutes.foldersStore, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data)
        });
        if (response.ok) location.reload();
    } catch (error) {
        segobToast('error', 'Fallo de conexión');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('pa-search');
    const noteCards = document.querySelectorAll('.pa-card--note');
    const tabs = document.querySelectorAll('.pa-tab');
    const foldersData = JSON.parse(document.getElementById('pa-folders-json')?.textContent || '[]');

    // Buscador en tiempo real
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            noteCards.forEach(card => {
                if (card.classList.contains('pa-card--placeholder')) return;
                const content = card.dataset.searchContent || '';
                card.style.display = content.includes(query) ? 'flex' : 'none';
            });
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

        let html = `<a href="#" class="pa-breadcrumb-item pa-breadcrumb-root ${!folder ? 'is-current' : ''}" onclick="event.preventDefault(); navigateToFolder(null);">
            <i class="fa-solid fa-house" style="font-size: 0.7rem;"></i> Mis Notas
        </a>`;

        if (folder) {
            html += `<span class="pa-breadcrumb-separator"><i class="fa-solid fa-chevron-right"></i></span>`;
            html += `<span class="pa-breadcrumb-item is-current"><i class="fa-solid ${folder.icon || 'fa-folder'}"></i> ${folder.name}</span>`;
        }

        breadcrumb.innerHTML = html;
    }

    window.navigateToFolder = async function(folderId) {
        window.paCurrentFolderId = folderId;

        if (!folderId) {
            // Go back to root folders view
            setExplorerMode(true);
            updateBreadcrumb(null);
            loadNotes('folders');
            // Show folders section & notes header again
            const folderSec = document.getElementById('section-folders');
            if (folderSec) folderSec.style.display = 'block';
            const notesHeader = document.getElementById('notes-section-header');
            if (notesHeader) notesHeader.style.display = '';
            return;
        }

        // Hide folders section & notes header — show only notes inside this folder
        const folderSec = document.getElementById('section-folders');
        if (folderSec) folderSec.style.display = 'none';
        const notesHeader = document.getElementById('notes-section-header');
        if (notesHeader) notesHeader.style.display = 'none';

        const container = document.getElementById('pa-notes-container');
        if (container) container.style.opacity = '0.5';

        try {
            const response = await paFetch(paBuildUrl(window.paRoutes.index, { folder_id: folderId }), {
                headers: {
                    'Accept': 'application/json'
                }
            });
            const data = await response.json();

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
        document.querySelectorAll('.pa-card--folder:not(.pa-card--placeholder)').forEach(card => {
            card.style.cursor = 'pointer';
            card.onclick = function(e) {
                if (e.target.closest('.pa-folder-delete')) return;
                const folderId = this.dataset.id;
                if (!folderId) return;

                // Switch sidebar to "Carpetas"
                const foldersNavItem = document.querySelector('.pa-nav-item[data-filter="folders"]');
                if (foldersNavItem && !foldersNavItem.classList.contains('is-active')) {
                    foldersNavItem.click();
                }

                // Navigate into the folder
                navigateToFolder(folderId);
            };
        });
    }
    bindFolderClicks();

    document.querySelectorAll('.pa-nav-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.pa-nav-item').forEach(i => i.classList.remove('is-active'));
            this.classList.add('is-active');

            const filter = this.dataset.filter;
            window.paCurrentFolderId = null;

            // UI adjustments based on filter
            const calFilter = document.getElementById('notes-calendar-filter');
            const timeTabs = document.querySelector('.pa-tabs-pills');

            if (calFilter) calFilter.style.display = filter === 'calendar' ? 'flex' : 'none';
            if (timeTabs) timeTabs.style.display = (filter === 'all' || filter === 'calendar') ? 'flex' : 'none';

            const folderSec = document.getElementById('section-folders');
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
                if (notesTitle) notesTitle.textContent = 'Notas sin carpeta';
                if (showAllNotesLink) showAllNotesLink.style.display = 'none';
            } else {
                setExplorerMode(false);
                if (showAllNotesLink) showAllNotesLink.style.display = 'block';

                if (notesTitle) {
                    if (filter === 'archive') {
                        notesTitle.textContent = 'Notas Archivadas';
                    } else if (filter === 'trash') {
                        notesTitle.textContent = 'Papelera de Reciclaje';
                    } else {
                        notesTitle.textContent = 'Notas Recientes';
                    }
                }

                // Archive & Trash: full grid, no slider arrows
                toggleNotesGrid(filter === 'archive' || filter === 'trash');
            }

            loadNotes(filter);
        });
    });

    document.querySelectorAll('.pa-tab-pill').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.pa-tab-pill').forEach(i => i.classList.remove('is-active'));
            this.classList.add('is-active');

            const filter = document.querySelector('.pa-nav-item.is-active').dataset.filter;
            const tab = this.dataset.tab;
            if (tab) loadNotes(filter, tab);
        });
    });

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

        const url = paBuildUrl(window.paRoutes.index, {
            filter,
            time_filter: timeFilter,
            month: window.paCurrentMonth,
            year: window.paCurrentYear,
            folder_id: window.paCurrentFolderId || ''
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
            if (window.paCurrentFolderId) {
                try {
                    const data = JSON.parse(text);
                    html = data.html;
                } catch (_) {
                    html = text;
                }
            } else {
                html = text;
            }
            container.innerHTML = html;

            if (wasGrid) {
                toggleNotesGrid(true);
            }
        } catch (error) {
            console.error('Error loading notes:', error);
        } finally {
            container.style.opacity = '1';
            applyContrast();
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
                if (window.paCurrentFolderId) {
                    navigateToFolder(window.paCurrentFolderId);
                } else {
                    const currentFilter = document.querySelector('.pa-nav-item.is-active')?.dataset.filter || 'all';
                    window.loadNotes(currentFilter);
                }
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

    // Note Preview (read-only full content view)
    window.previewNote = function(id) {
        const card = document.querySelector(`.pa-card--note[data-id="${id}"]`);
        if (!card) return;
        const noteData = JSON.parse(card.dataset.noteData);
        if (noteData.is_encrypted) { decryptNote(id, 'preview'); return; }

        const attachmentsHtml = (noteData.attachments || []).map(att => {
            if (att.file_type === 'image') {
                return `<div class="pa-preview-att-item"><img src="${att.file_path}" alt="${att.file_name}" onclick="window.open('${att.file_path}','_blank');" style="cursor:pointer;"></div>`;
            }
            return `<a href="${att.file_path}" target="_blank" class="pa-preview-att-item" style="padding:10px 14px;text-decoration:none;color:inherit;display:flex;align-items:center;gap:6px;font-size:0.75rem;"><i class="fa-solid fa-file-lines"></i>${att.file_name}</a>`;
        }).join('');

        const overlay = document.createElement('div');
        overlay.className = 'pa-preview-overlay';
        overlay.innerHTML = `
            <div class="pa-preview-card" style="border-left: 5px solid ${noteData.color || '#eee'};">
                <button class="pa-preview-close" onclick="this.closest('.pa-preview-overlay').remove()"><i class="fa-solid fa-xmark"></i></button>
                <div class="pa-preview-title">${noteData.title || 'Sin título'}</div>
                <div class="pa-preview-date">${card.querySelector('.pa-card-date')?.textContent || ''}</div>
                <div class="pa-preview-body">${noteData.content ? noteData.content.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>') : ''}</div>
                ${attachmentsHtml ? `<div class="pa-preview-attachments">${attachmentsHtml}</div>` : ''}
                <div style="margin-top:16px;display:flex;gap:8px;justify-content:flex-end;">
                    <button onclick="this.closest('.pa-preview-overlay').remove(); editNote(${id});" style="padding:6px 14px;border-radius:8px;border:1px solid var(--clr-border);background:#fff;cursor:pointer;font-size:0.75rem;font-weight:600;"><i class="fa-regular fa-pen-to-square"></i> Editar</button>
                </div>
            </div>
        `;
        overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });
        document.body.appendChild(overlay);
    };

    window.editNote = function(id) {
        const card = document.querySelector(`.pa-card--note[data-id="${id}"]`);
        if (!card) return;
        const noteData = JSON.parse(card.dataset.noteData);

        if (noteData.is_encrypted) {
            decryptNote(id, 'edit');
        } else {
            window.openPersonalNoteModal(noteData);
        }
    };

    // Session cache for decrypted notes { noteId: { content, password } }
    if (!window._decryptedNotes) window._decryptedNotes = {};

    window.decryptNote = async function(id, action) {
        action = action || 'preview'; // 'preview' or 'edit'
        const card = document.querySelector(`.pa-card--note[data-id="${id}"]`);
        if (!card) return;
        const noteData = JSON.parse(card.dataset.noteData);

        // If already decrypted this session, use cached content
        if (window._decryptedNotes[id]) {
            noteData.content = window._decryptedNotes[id].content;
            noteData.password = window._decryptedNotes[id].password;
            if (action === 'edit') {
                window.openPersonalNoteModal(noteData);
            } else {
                showDecryptedPreview(noteData, card);
            }
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
                const data = await response.json();
                if (data.success) {
                    noteData.content = data.content;
                    noteData.password = password;
                    // Cache for session
                    window._decryptedNotes[id] = { content: data.content, password: password };
                    if (action === 'edit') {
                        window.openPersonalNoteModal(noteData);
                    } else {
                        showDecryptedPreview(noteData, card);
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

    function showDecryptedPreview(noteData, card) {
        const attachmentsHtml = (noteData.attachments || []).map(att => {
            if (att.file_type === 'image') {
                return `<div class="pa-preview-att-item"><img src="${att.file_path}" alt="${att.file_name}" onclick="window.open('${att.file_path}','_blank');" style="cursor:pointer;"></div>`;
            }
            return `<a href="${att.file_path}" target="_blank" class="pa-preview-att-item" style="padding:10px 14px;text-decoration:none;color:inherit;display:flex;align-items:center;gap:6px;font-size:0.75rem;"><i class="fa-solid fa-file-lines"></i>${att.file_name}</a>`;
        }).join('');

        const overlay = document.createElement('div');
        overlay.className = 'pa-preview-overlay';
        overlay.innerHTML = `
            <div class="pa-preview-card" style="border-left: 5px solid ${noteData.color || '#eee'};">
                <button class="pa-preview-close" onclick="this.closest('.pa-preview-overlay').remove()"><i class="fa-solid fa-xmark"></i></button>
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;"><i class="fa-solid fa-lock" style="font-size:0.7rem;opacity:0.5;"></i><span style="font-size:0.65rem;opacity:0.5;">Descifrada</span></div>
                <div class="pa-preview-title">${noteData.title || 'Sin título'}</div>
                <div class="pa-preview-date">${card.querySelector('.pa-card-date')?.textContent || ''}</div>
                <div class="pa-preview-body">${noteData.content ? noteData.content.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>') : ''}</div>
                ${attachmentsHtml ? `<div class="pa-preview-attachments">${attachmentsHtml}</div>` : ''}
                <div style="margin-top:16px;display:flex;gap:8px;justify-content:flex-end;">
                    <button onclick="this.closest('.pa-preview-overlay').remove(); editNote(${noteData.id});" style="padding:6px 14px;border-radius:8px;border:1px solid var(--clr-border);background:#fff;cursor:pointer;font-size:0.75rem;font-weight:600;"><i class="fa-regular fa-pen-to-square"></i> Editar</button>
                </div>
            </div>
        `;
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

    async function saveFolder(data) {
        try {
            const response = await fetch(window.paRoutes.foldersStore, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            });
            if (response.ok) location.reload();
        } catch (error) {
            segobToast('error', 'Fallo de conexión');
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
                } else {
                    console.warn('Archive response:', response.status);
                    segobToast('warning', 'Posible error al archivar');
                }
            } catch (error) {
                console.error('Archive fetch error:', error);
            }
            const activeNav = document.querySelector('.pa-nav-item.is-active');
            if (window.paCurrentFolderId) {
                navigateToFolder(window.paCurrentFolderId);
            } else if (activeNav) {
                loadNotes(activeNav.dataset.filter);
            } else {
                loadNotes();
            }
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
        const activeNav = document.querySelector('.pa-nav-item.is-active');
        if (window.paCurrentFolderId) {
            navigateToFolder(window.paCurrentFolderId);
        } else if (activeNav) {
            loadNotes(activeNav.dataset.filter);
        } else {
            loadNotes();
        }
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
                } else {
                    console.warn('Delete response:', response.status);
                    segobToast('error', 'Error al eliminar');
                }
            } catch (error) {
                console.error('Delete fetch error:', error);
                segobToast('error', 'Fallo de conexión');
            }
            const activeNav = document.querySelector('.pa-nav-item.is-active');
            if (window.paCurrentFolderId) {
                navigateToFolder(window.paCurrentFolderId);
            } else if (activeNav) {
                loadNotes(activeNav.dataset.filter);
            } else {
                loadNotes();
            }
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

    // --- Lógica de Reubicación (Drag & Drop + Context Menu) ---
    let selectedNoteId = null;

    function initDragAndDrop() {
        const notes = document.querySelectorAll('.pa-card--note');
        const folders = document.querySelectorAll('.pa-card--folder:not(.pa-card--placeholder)');

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
        const notes = document.querySelectorAll('.pa-card--note:not(.pa-card--placeholder)');
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
                card.classList.toggle('text-light', lum < 150);
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
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const menu = document.getElementById('pa-context-menu');
                if (menu) menu.style.display = 'none';

                // Update counts in DOM (Folders section)
                const noteElement = document.querySelector(`.pa-card--note[data-id="${targetNoteId}"]`);
                if (noteElement && noteElement.dataset.noteData) {
                    const noteData = JSON.parse(noteElement.dataset.noteData);
                    const oldFolderId = noteData.folder_id;

                    // Decrement old folder if it was in one
                    if (oldFolderId) {
                        const oldFolderCard = document.querySelector(`.pa-card--folder[data-id="${oldFolderId}"]`);
                        if (oldFolderCard) {
                            const countSpan = oldFolderCard.querySelector('.pa-folder-count-num');
                            if (countSpan) {
                                let count = parseInt(countSpan.textContent) || 0;
                                countSpan.textContent = `${Math.max(0, count - 1)} notas`;
                            }
                        }
                    }

                    // Increment new folder
                    if (folderId) {
                        const newFolderCard = document.querySelector(`.pa-card--folder[data-id="${folderId}"]`);
                        if (newFolderCard) {
                            const countSpan = newFolderCard.querySelector('.pa-folder-count-num');
                            if (countSpan) {
                                let count = parseInt(countSpan.textContent) || 0;
                                countSpan.textContent = `${count + 1} notas`;
                            }
                        }
                    }
                }

                // If inside a folder, reload that folder; otherwise reload normal view
                if (window.paCurrentFolderId) {
                    navigateToFolder(window.paCurrentFolderId);
                } else {
                    const currentFilter = document.querySelector('.pa-nav-item.is-active')?.dataset.filter || 'all';
                    loadNotes(currentFilter);
                }
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
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        segobToast('success', 'Carpeta eliminada');
                        location.reload();
                    }
                });
            }
        });
    };

    // Hook into MutationObserver for notes grid
    const notesContainer = document.getElementById('pa-notes-container');
    const gridObserver = new MutationObserver(() => {
        initDragAndDrop();
        initContextMenu();
        applyContrast();
        bindFolderClicks();
    });
    if (notesContainer) gridObserver.observe(notesContainer, { childList: true });
});
