(function () {
    'use strict';

    function debounce(fn, ms) {
        let t;
        return function () {
            const ctx = this;
            const args = arguments;
            clearTimeout(t);
            t = setTimeout(function () {
                fn.apply(ctx, args);
            }, ms);
        };
    }

    function dayStartTs(dateStr) {
        if (!dateStr) return null;
        const d = new Date(dateStr + 'T00:00:00');
        const x = d.getTime();
        return Number.isNaN(x) ? null : Math.floor(x / 1000);
    }

    function dayEndTs(dateStr) {
        if (!dateStr) return null;
        const d = new Date(dateStr + 'T23:59:59');
        const x = d.getTime();
        return Number.isNaN(x) ? null : Math.floor(x / 1000);
    }

    function initHtmlPartsMode() {
        const iframe = document.getElementById('waChatIframe');
        const buttons = Array.from(document.querySelectorAll('.wa-part-btn'));
        if (!iframe || buttons.length === 0) return;

        function setActive(btn) {
            buttons.forEach(function (b) {
                b.classList.remove('wa-active-part');
            });
            btn.classList.add('wa-active-part');
        }

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const url = btn.getAttribute('data-part-url') || '';
                if (!url) return;
                setActive(btn);
                iframe.src = url;
            });
        });
    }

    function initTxtFiltersAndSearch() {
        const root = document.getElementById('waPreviewRoot');
        const wrap = document.getElementById('waChatTxtWrap');
        const searchInput = document.getElementById('waSearchInput');
        const searchResults = document.getElementById('waSearchResults');
        const searchMeta = document.getElementById('waSearchMeta');
        const dataScript = document.getElementById('waMessagesData');

        if (!root || root.getAttribute('data-wa-preview-mode') !== 'txt') return;
        if (!wrap || !searchInput || !searchResults || !dataScript) return;

        let allMessages = [];
        try {
            allMessages = JSON.parse(dataScript.textContent || '[]');
        } catch (e) {
            console.error('Error parsing WhatsApp data', e);
            return;
        }

        if (allMessages.length === 0) {
            wrap.innerHTML = '<p class="wa-empty-msg">No hay mensajes para mostrar.</p>';
            return;
        }

        const elDateFrom = document.getElementById('waFilterDateFrom');
        const elDateTo = document.getElementById('waFilterDateTo');
        const elAuthor = document.getElementById('waFilterAuthor');
        const elMediaOnly = document.getElementById('waFilterMediaOnly');
        const elLongOnly = document.getElementById('waFilterLongOnly');
        const btnReset = document.getElementById('waFilterReset');

        // Initialize Flatpickr if uniqueDates found
        const uniqueDates = Array.from(new Set(allMessages.map(m => {
            if (!m.datetime_ts) return null;
            return new Date(m.datetime_ts * 1000).toISOString().split('T')[0];
        }).filter(d => d !== null))).sort();

        if (typeof flatpickr !== 'undefined' && uniqueDates.length > 0) {
            const commonCfg = {
                locale: 'es',
                dateFormat: 'Y-m-d',
                enable: uniqueDates,
                disableMobile: true,
                onChange: () => applyFilters()
            };

            const fpFrom = flatpickr(elDateFrom, {
                ...commonCfg,
                defaultDate: uniqueDates[0],
                onClose: (selectedDates) => {
                    if (selectedDates.length > 0) {
                        const d = selectedDates[0];
                        fpTo.set('minDate', d);
                        fpTo.setDate(d);
                        applyFilters();
                    }
                }
            });

            const fpTo = flatpickr(elDateTo, {
                ...commonCfg,
                defaultDate: uniqueDates[uniqueDates.length - 1],
                onClose: (selectedDates) => {
                    if (selectedDates.length > 0) {
                        fpFrom.set('maxDate', selectedDates[0]);
                    }
                }
            });

            // Store for reset
            elDateFrom._flatpickr = fpFrom;
            elDateTo._flatpickr = fpTo;
        }

        let filteredMessages = allMessages;
        let renderedCount = 0;
        const CHUNK_SIZE = 30;

        function renderMsgHtml(msg) {
            const hasMedia = !!msg.media_filename;
            let mediaHtml = '';
            if (hasMedia) {
                if (msg.media_url && msg.media_kind === 'image') {
                    mediaHtml = `<div class="wa-media">
                        <a href="${msg.media_url}" target="_blank" rel="noopener">
                            <img src="${msg.media_url}" alt="${msg.media_filename}" class="${msg.media_is_sticker ? 'wa-sticker' : ''}">
                        </a>
                    </div>`;
                } else if (msg.media_url && msg.media_kind === 'video') {
                    mediaHtml = `<div class="wa-media"><video controls preload="metadata"><source src="${msg.media_url}"></video></div>`;
                } else if (msg.media_url && msg.media_kind === 'audio') {
                    mediaHtml = `<div class="wa-media"><audio controls preload="metadata"><source src="${msg.media_url}"></audio></div>`;
                } else if (msg.media_url) {
                    mediaHtml = `<div class="wa-media"><a class="wa-media-file" href="${msg.media_url}" target="_blank" rel="noopener">Abrir archivo: ${msg.media_filename}</a></div>`;
                } else {
                    mediaHtml = `<div class="wa-media"><span class="wa-media-file" style="color:var(--clr-text-muted);">Archivo no encontrado: ${msg.media_filename}</span></div>`;
                }
            }

            return `<article class="wa-msg" id="wa-msg-${msg.index}" data-msg-index="${msg.index}">
                <div class="wa-msg-head">
                    <div>
                        <span class="wa-msg-num">#${msg.index}</span>
                        <span class="wa-msg-author">${escapeHtml(msg.author || 'WhatsApp')}</span>
                    </div>
                    <span class="wa-msg-time">${escapeHtml(msg.datetime_raw || '')}</span>
                </div>
                <div class="wa-msg-body">${escapeHtml(msg.text || '')}</div>
                ${mediaHtml}
            </article>`;
        }

        function renderNextChunk() {
            if (renderedCount >= filteredMessages.length) return;
            const chunk = filteredMessages.slice(renderedCount, renderedCount + CHUNK_SIZE);
            const html = chunk.map(renderMsgHtml).join('');
            
            const temp = document.createElement('div');
            temp.innerHTML = html;
            while (temp.firstChild) {
                if (sentinel) wrap.insertBefore(temp.firstChild, sentinel);
                else wrap.appendChild(temp.firstChild);
            }
            
            renderedCount += chunk.length;
        }

        // Infinite scroll with IntersectionObserver
        const sentinel = document.getElementById('waChatSentinel');
        if (sentinel) {
            const observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting) {
                    renderNextChunk();
                }
            }, { root: wrap, rootMargin: '400px' });
            observer.observe(sentinel);
        }

        function applyFilters() {
            const fromTs = elDateFrom && elDateFrom.value ? dayStartTs(elDateFrom.value) : null;
            const toTs = elDateTo && elDateTo.value ? dayEndTs(elDateTo.value) : null;
            const author = elAuthor && elAuthor.value ? elAuthor.value : '';
            const mediaOnly = elMediaOnly && elMediaOnly.checked;
            const longOnly = elLongOnly && elLongOnly.checked;

            filteredMessages = allMessages.filter(msg => {
                const ts = msg.datetime_ts;
                if (fromTs !== null && (ts === null || ts < fromTs)) return false;
                if (toTs !== null && (ts === null || ts > toTs)) return false;
                if (author && msg.author !== author) return false;
                if (mediaOnly && !msg.media_filename) return false;
                if (longOnly && (msg.text || '').length <= 200) return false;
                return true;
            });

            renderedCount = 0;
            wrap.scrollTop = 0;
            // Clear but keep sentinel
            Array.from(wrap.children).forEach(child => {
                if (child.id !== 'waChatSentinel') wrap.removeChild(child);
            });
            renderNextChunk();
            runSearch();
        }

        function snippetAround(text, query, radius) {
            const q = query.trim().toLowerCase();
            const lower = text.toLowerCase();
            const i = lower.indexOf(q);
            if (i < 0) return text.slice(0, radius * 2) + (text.length > radius * 2 ? '…' : '');
            const start = Math.max(0, i - radius);
            const end = Math.min(text.length, i + q.length + radius);
            let s = text.slice(start, end);
            if (start > 0) s = '…' + s;
            if (end < text.length) s = s + '…';
            return s;
        }

        let searchHits = [];
        let searchRenderedCount = 0;
        const SEARCH_CHUNK_SIZE = 30;

        function renderNextSearchChunk() {
            if (searchRenderedCount >= searchHits.length) return;
            const q = (searchInput.value || '').trim();
            const chunk = searchHits.slice(searchRenderedCount, searchRenderedCount + SEARCH_CHUNK_SIZE);
            
            chunk.forEach(hit => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'wa-search-hit';
                const snippet = snippetAround((hit.author + ' ' + (hit.text || '')), q, 40);
                btn.innerHTML = `<span class="wa-search-hit-num">#${hit.index}</span>
                                 <span class="wa-search-hit-author">${escapeHtml(hit.author)}</span>
                                 <span class="wa-search-hit-snippet">${escapeHtml(snippet)}</span>`;
                btn.addEventListener('click', () => scrollToMessageByIndex(hit.index));
                
                if (sSentinel) searchResults.insertBefore(btn, sSentinel);
                else searchResults.appendChild(btn);
            });
            searchRenderedCount += chunk.length;
        }

        // Search infinite scroll
        const sSentinel = document.getElementById('waSearchSentinel');
        if (sSentinel && searchResults) {
            const sObserver = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting) {
                    renderNextSearchChunk();
                }
            }, { root: searchResults, rootMargin: '100px' });
            sObserver.observe(sSentinel);
        }

        function runSearch() {
            const q = (searchInput.value || '').trim();
            // Clear hits but keep sentinel
            Array.from(searchResults.children).forEach(child => {
                if (child.id !== 'waSearchSentinel') searchResults.removeChild(child);
            });
            
            searchRenderedCount = 0;
            searchHits = [];

            if (q === '') {
                if (searchMeta) searchMeta.textContent = filteredMessages.length + ' mensajes visibles.';
                return;
            }

            const ql = q.toLowerCase();
            searchHits = filteredMessages.filter(msg => {
                return (msg.author || '').toLowerCase().includes(ql) || (msg.text || '').toLowerCase().includes(ql);
            });

            if (searchMeta) {
                searchMeta.textContent = searchHits.length + ' coincidencias en ' + filteredMessages.length + ' mensajes visibles.';
            }

            renderNextSearchChunk();
        }

        function scrollToMessageByIndex(index) {
            // Check if it's already rendered
            let el = document.getElementById('wa-msg-' + index);
            if (!el) {
                // Find index in filteredMessages
                const pos = filteredMessages.findIndex(m => m.index == index);
                if (pos !== -1) {
                    // Render everything up to that position + some margin
                    const targetCount = pos + 10;
                    while (renderedCount < targetCount && renderedCount < filteredMessages.length) {
                        renderNextChunk();
                    }
                    el = document.getElementById('wa-msg-' + index);
                }
            }
            if (el) {
                messagesFlash(el);
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        function messagesFlash(el) {
            Array.from(wrap.querySelectorAll('.wa-msg--flash')).forEach(m => m.classList.remove('wa-msg--flash'));
            el.classList.add('wa-msg--flash');
            setTimeout(() => el.classList.remove('wa-msg--flash'), 3000);
        }

        function escapeHtml(s) {
            if (!s) return '';
            const d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        const debouncedSearch = debounce(runSearch, 250);
        [elDateFrom, elDateTo, elAuthor, elMediaOnly, elLongOnly].forEach(el => {
            if (el) {
                el.addEventListener('input', applyFilters);
                el.addEventListener('change', applyFilters);
            }
        });
        searchInput.addEventListener('input', debouncedSearch);

        if (btnReset) {
            btnReset.addEventListener('click', () => {
                if (elDateFrom && elDateFrom._flatpickr) elDateFrom._flatpickr.clear();
                if (elDateTo && elDateTo._flatpickr) elDateTo._flatpickr.clear();
                if (elDateFrom && !elDateFrom._flatpickr) elDateFrom.value = '';
                if (elDateTo && !elDateTo._flatpickr) elDateTo.value = '';
                if (elAuthor) elAuthor.value = '';
                if (elMediaOnly) elMediaOnly.checked = false;
                if (elLongOnly) elLongOnly.checked = false;
                applyFilters();
            });
        }
        
        applyFilters();
    }

    document.addEventListener('DOMContentLoaded', function () {
        initHtmlPartsMode();
        initTxtFiltersAndSearch();
    });
})();
