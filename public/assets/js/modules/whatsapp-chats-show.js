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
        // Support both old (.wa-part-btn) and new (.wa-part-tab, .wa-part-card) selectors
        const buttons = Array.from(document.querySelectorAll('.wa-part-btn, .wa-part-tab, .wa-part-card'));
        if (!iframe || buttons.length === 0) return;

        function setActive(btn) {
            buttons.forEach(function (b) {
                b.classList.remove('wa-active-part');
                b.setAttribute('aria-selected', 'false');
            });
            btn.classList.add('wa-active-part');
            btn.setAttribute('aria-selected', 'true');
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

        function hasMedia(msg) {
            if (Array.isArray(msg.media_items)) return msg.media_items.length > 0;
            return !!msg.media_filename;
        }

        function isReactionOnlyMessage(msg) {
            const text = String((msg && msg.text) || '').trim();
            const reactions = Array.isArray(msg && msg.reactions) ? msg.reactions : [];
            return text === '' && !hasMedia(msg) && reactions.length > 0;
        }

        function buildReactionEntries(msg) {
            const author = String((msg && msg.author) || 'Contacto').trim() || 'Contacto';
            const reactions = Array.isArray(msg && msg.reactions) ? msg.reactions : [];
            return reactions.map(function (emoji) {
                return {
                    emoji: String(emoji || '').trim(),
                    actor: author
                };
            }).filter(function (entry) {
                return entry.emoji !== '';
            });
        }

        function mergeStandaloneReactions(messages) {
            const merged = [];

            messages.forEach(function (rawMsg) {
                const msg = {
                    ...rawMsg,
                    reaction_entries: buildReactionEntries(rawMsg)
                };

                if (!isReactionOnlyMessage(msg) || merged.length === 0) {
                    merged.push(msg);
                    return;
                }

                let targetIndex = -1;
                for (let i = merged.length - 1; i >= 0; i--) {
                    const candidate = merged[i];
                    if (String((candidate && candidate.text) || '').trim() !== '') {
                        targetIndex = i;
                        break;
                    }
                }

                if (targetIndex < 0) {
                    targetIndex = merged.length - 1;
                }

                const target = merged[targetIndex];
                const currentEntries = Array.isArray(target.reaction_entries) ? target.reaction_entries : [];
                target.reaction_entries = currentEntries.concat(msg.reaction_entries || []);
                target.reactions = target.reaction_entries.map(function (entry) {
                    return entry.emoji;
                });
            });

            return merged;
        }

        allMessages = mergeStandaloneReactions(allMessages);

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

        const uniqueDates = Array.from(new Set(allMessages.map(function (m) {
            if (!m.datetime_ts) return null;
            return new Date(m.datetime_ts * 1000).toISOString().split('T')[0];
        }).filter(function (d) {
            return d !== null;
        }))).sort();

        let defaultFromDate = uniqueDates.length > 0 ? uniqueDates[0] : '';
        let defaultToDate = uniqueDates.length > 0 ? uniqueDates[uniqueDates.length - 1] : '';

        if (typeof flatpickr !== 'undefined' && uniqueDates.length > 0) {
            const commonCfg = {
                locale: 'es',
                dateFormat: 'Y-m-d',
                enable: uniqueDates,
                disableMobile: true,
                onChange: function () { applyFilters(); }
            };

            const fpFrom = flatpickr(elDateFrom, {
                ...commonCfg,
                defaultDate: uniqueDates[0],
                onClose: function (selectedDates) {
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
                onClose: function (selectedDates) {
                    if (selectedDates.length > 0) {
                        fpFrom.set('maxDate', selectedDates[0]);
                    }
                }
            });

            elDateFrom._flatpickr = fpFrom;
            elDateTo._flatpickr = fpTo;
        }

        let filteredMessages = allMessages;
        let renderedCount = 0;
        const CHUNK_SIZE = 100;
        const MAX_IN_DOM = 500;

        function escapeHtml(s) {
            if (!s) return '';
            const d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        function escapeAttr(s) {
            return escapeHtml(s).replace(/"/g, '&quot;');
        }

        const WA_AUTHOR_COLORS = [
            '#e57373', '#64b5f6', '#66bb6a', '#ffa726',
            '#ab47bc', '#26c6da', '#ec407a', '#8d6e63'
        ];

        function getAuthorColor(author) {
            if (!author) return WA_AUTHOR_COLORS[0];
            let hash = 0;
            for (let i = 0; i < author.length; i++) {
                hash = ((hash << 5) - hash) + author.charCodeAt(i);
                hash |= 0;
            }
            return WA_AUTHOR_COLORS[Math.abs(hash) % WA_AUTHOR_COLORS.length];
        }

        function getAuthorInitial(author) {
            return (String(author || '?').trim().charAt(0) || '?').toUpperCase();
        }

        function highlightInEscaped(escapedHtml, rawQuery) {
            if (!rawQuery || !rawQuery.trim()) return escapedHtml;
            const escapedQuery = escapeHtml(rawQuery.trim());
            const safe = escapedQuery.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            return escapedHtml.replace(new RegExp(safe, 'gi'), function (match) {
                return '<mark class="wa-search-highlight">' + match + '</mark>';
            });
        }

        function mapReactionIconClass(emoji) {
            const e = String(emoji || '');
            if (e.includes('👍')) return 'fa-thumbs-up';
            if (e.includes('👎')) return 'fa-thumbs-down';
            if (e.includes('❤') || e.includes('❤️') || e.includes('💜') || e.includes('💙') || e.includes('💚')) return 'fa-heart';
            if (e.includes('😂') || e.includes('🤣') || e.includes('😆') || e.includes('😹')) return 'fa-face-laugh';
            if (e.includes('😮') || e.includes('😯') || e.includes('😲')) return 'fa-face-surprise';
            if (e.includes('😢') || e.includes('😭')) return 'fa-face-sad-tear';
            if (e.includes('🙏')) return 'fa-hands-praying';
            return 'fa-face-smile';
        }

        function buildMediaItemHtml(item) {
            const mediaUrl = item && item.media_url ? String(item.media_url) : '';
            const mediaFilename = item && item.filename ? String(item.filename) : '';
            const mediaKind = item && item.media_kind ? String(item.media_kind) : 'file';
            const mediaIsSticker = !!(item && item.media_is_sticker);

            if (!mediaFilename) return '';

            if (mediaUrl && mediaKind === 'image') {
                return '<div class="wa-media-item">'
                    + '<button type="button" class="wa-media-open js-wa-open-image" data-media-url="' + escapeAttr(mediaUrl) + '" data-media-filename="' + escapeAttr(mediaFilename) + '" aria-label="Vista previa de ' + escapeAttr(mediaFilename) + '">'
                    + '<img src="' + escapeAttr(mediaUrl) + '" alt="' + escapeAttr(mediaFilename) + '" class="' + (mediaIsSticker ? 'wa-sticker' : '') + '">'
                    + '</button>'
                    + '</div>';
            }

            if (mediaUrl && mediaKind === 'video') {
                return '<div class="wa-media-item"><video controls preload="metadata"><source src="' + escapeAttr(mediaUrl) + '"></video></div>';
            }

            if (mediaUrl && mediaKind === 'audio') {
                return '<div class="wa-media-item"><audio controls preload="metadata"><source src="' + escapeAttr(mediaUrl) + '"></audio></div>';
            }

            if (mediaUrl) {
                return '<div class="wa-media-item"><a class="wa-media-file" href="' + escapeAttr(mediaUrl) + '" target="_blank" rel="noopener">Abrir archivo: ' + escapeHtml(mediaFilename) + '</a></div>';
            }

            return '<div class="wa-media-item"><span class="wa-media-file" style="color:var(--clr-text-muted);">Archivo no encontrado: ' + escapeHtml(mediaFilename) + '</span></div>';
        }

        function buildReactionsHtml(entries, fallbackReactions, msgAuthor) {
            let reactionEntries = Array.isArray(entries) ? entries.slice() : [];

            if (reactionEntries.length === 0 && Array.isArray(fallbackReactions) && fallbackReactions.length > 0) {
                const actor = String(msgAuthor || 'Contacto').trim() || 'Contacto';
                reactionEntries = fallbackReactions.map(function (emoji) {
                    return {
                        emoji: String(emoji || '').trim(),
                        actor: actor
                    };
                }).filter(function (entry) {
                    return entry.emoji !== '';
                });
            }

            if (reactionEntries.length === 0) return '';

            const grouped = {};
            reactionEntries.forEach(function (entry) {
                const emoji = entry.emoji;
                if (!grouped[emoji]) {
                    grouped[emoji] = {
                        emoji: emoji,
                        actors: []
                    };
                }
                if (entry.actor && grouped[emoji].actors.indexOf(entry.actor) === -1) {
                    grouped[emoji].actors.push(entry.actor);
                }
            });

            return '<div class="wa-msg-reactions" aria-label="Reacciones">' + Object.keys(grouped).map(function (emoji) {
                const item = grouped[emoji];
                const actorText = item.actors.length > 0 ? item.actors.join(', ') : 'Contacto';
                const iconClass = mapReactionIconClass(item.emoji);
                return '<span class="wa-reaction-chip" title="' + escapeAttr(actorText) + '" aria-label="Reacción por ' + escapeAttr(actorText) + '">'
                    + '<span class="wa-reaction-icon"><i class="fa-solid ' + iconClass + '" aria-hidden="true"></i></span>'
                    + '</span>';
            }).join('') + '</div>';
        }

        function parseEditedMessageState(rawText) {
            const source = String(rawText || '');
            if (source.trim() === '') return { text: '', edited: false };

            const markerRegex = /\s*[<\[]?\s*Se edit[oó] este mensaje\.?\s*[>\]]?\s*$/iu;
            const edited = markerRegex.test(source);
            const cleaned = edited ? source.replace(markerRegex, '') : source;

            return {
                text: cleaned.trim(),
                edited: edited
            };
        }

        function renderMsgHtml(msg) {
            const mediaItems = Array.isArray(msg.media_items)
                ? msg.media_items
                : (msg.media_filename ? [{
                    filename: msg.media_filename,
                    media_url: msg.media_url,
                    media_kind: msg.media_kind,
                    media_is_sticker: msg.media_is_sticker
                }] : []);

            const hasMedia = mediaItems.length > 0;
            let mediaHtml = '';
            if (hasMedia) {
                mediaHtml = '<div class="wa-media">' + mediaItems.map(buildMediaItemHtml).join('') + '</div>';
            }

            const editedState = parseEditedMessageState(msg.text || '');
            const bodyText = editedState.text;
            const bodyHtml = bodyText !== '' ? '<div class="wa-msg-body">' + escapeHtml(bodyText) + '</div>' : '';
            const reactionsHtml = buildReactionsHtml(msg.reaction_entries || [], msg.reactions || [], msg.author || '');
            const editedHtml = editedState.edited ? '<div class="wa-msg-meta"><strong class="wa-msg-edited">EDITADO</strong></div>' : '';

            const authorColor = getAuthorColor(msg.author || '');
            const authorInitial = getAuthorInitial(msg.author || '');

            return '<article class="wa-msg" id="wa-msg-' + msg.index + '" data-msg-index="' + msg.index + '">'
                + '<div class="wa-msg-avatar" style="background:' + escapeAttr(authorColor) + ';" aria-hidden="true">' + escapeHtml(authorInitial) + '</div>'
                + '<div class="wa-msg-bubble">'
                + '<div class="wa-msg-head">'
                + '    <div>'
                + '        <span class="wa-msg-author" style="color:' + escapeAttr(authorColor) + '">' + escapeHtml(msg.author || 'WhatsApp') + '</span>'
                + '        <span class="wa-msg-num">#' + msg.index + '</span>'
                + '    </div>'
                + '    <span class="wa-msg-time">' + escapeHtml(msg.datetime_raw || '') + '</span>'
                + '</div>'
                + bodyHtml
                + mediaHtml
                + reactionsHtml
                + editedHtml
                + '</div>'
                + '</article>';
        }

        function countActiveFilters() {
            let count = 0;
            const fromVal = elDateFrom && elDateFrom.value ? elDateFrom.value : '';
            const toVal = elDateTo && elDateTo.value ? elDateTo.value : '';
            if (fromVal && fromVal !== defaultFromDate) count++;
            if (toVal && toVal !== defaultToDate) count++;
            if (elAuthor && elAuthor.value) count++;
            if (elMediaOnly && elMediaOnly.checked) count++;
            if (elLongOnly && elLongOnly.checked) count++;
            return count;
        }

        function updateStatsBar() {
            const count = countActiveFilters();
            // New layout stats bar
            const visibleGroup = document.getElementById('waStatsVisible');
            const visibleCount = document.getElementById('waStatsVisibleCount');
            const isFiltered = filteredMessages.length < allMessages.length;
            if (visibleGroup) visibleGroup.hidden = !isFiltered;
            if (visibleCount) visibleCount.textContent = filteredMessages.length;

            // Legacy stats elements (keep for backward compat)
            const statsEl = document.getElementById('waChatStatsText');
            if (statsEl) {
                let text = filteredMessages.length + ' de ' + allMessages.length + ' mensajes';
                if (count > 0) text += ' · ' + count + ' filtro' + (count !== 1 ? 's' : '') + ' activo' + (count !== 1 ? 's' : '');
                statsEl.textContent = text;
            }
            const mobileStats = document.getElementById('waMobileStats');
            if (mobileStats) mobileStats.textContent = filteredMessages.length + '/' + allMessages.length;
            const badge = document.getElementById('waMobileFilterBadge');
            if (badge) { badge.textContent = count; badge.hidden = count === 0; }

            // Update active filters chips panel
            const activeFiltersEl = document.getElementById('waActiveFilters');
            const chipsEl = document.getElementById('waActiveFiltersChips');
            if (activeFiltersEl && chipsEl) {
                activeFiltersEl.hidden = count === 0;
                if (count > 0) {
                    const chips = [];
                    const fromVal = document.getElementById('waFilterDateFrom');
                    const toVal = document.getElementById('waFilterDateTo');
                    const auth = document.getElementById('waFilterAuthor');
                    const media = document.getElementById('waFilterMediaOnly');
                    const lng = document.getElementById('waFilterLongOnly');
                    if (fromVal && fromVal.value) chips.push('<span class="wa-filter-chip"><i class="fa-regular fa-calendar" aria-hidden="true"></i> Desde: ' + fromVal.value + '</span>');
                    if (toVal && toVal.value) chips.push('<span class="wa-filter-chip"><i class="fa-regular fa-calendar" aria-hidden="true"></i> Hasta: ' + toVal.value + '</span>');
                    if (auth && auth.value) chips.push('<span class="wa-filter-chip"><i class="fa-solid fa-user" aria-hidden="true"></i> ' + auth.value + '</span>');
                    if (media && media.checked) chips.push('<span class="wa-filter-chip"><i class="fa-solid fa-paperclip" aria-hidden="true"></i> Con archivos</span>');
                    if (lng && lng.checked) chips.push('<span class="wa-filter-chip"><i class="fa-solid fa-align-left" aria-hidden="true"></i> Texto largo</span>');
                    chipsEl.innerHTML = chips.join('');
                }
            }
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

        const sentinel = document.getElementById('waChatSentinel');
        if (sentinel) {
            const observer = new IntersectionObserver(function (entries) {
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

            filteredMessages = allMessages.filter(function (msg) {
                const ts = msg.datetime_ts;
                if (fromTs !== null && (ts === null || ts < fromTs)) return false;
                if (toTs !== null && (ts === null || ts > toTs)) return false;
                if (author && msg.author !== author) return false;
                const hasMedia = Array.isArray(msg.media_items) ? msg.media_items.length > 0 : !!msg.media_filename;
                if (mediaOnly && !hasMedia) return false;
                if (longOnly && (msg.text || '').length <= 200) return false;
                return true;
            });

            renderedCount = 0;
            wrap.scrollTop = 0;
            Array.from(wrap.children).forEach(function (child) {
                if (child.id !== 'waChatSentinel') wrap.removeChild(child);
            });
            renderNextChunk();
            runSearch();
            updateStatsBar();
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
        const SEARCH_CHUNK_SIZE = 50;

        function renderNextSearchChunk() {
            if (searchRenderedCount >= searchHits.length) return;
            const q = (searchInput.value || '').trim();
            const chunk = searchHits.slice(searchRenderedCount, searchRenderedCount + SEARCH_CHUNK_SIZE);

            chunk.forEach(function (hit) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'wa-search-hit';
                const snippet = snippetAround((hit.author + ' ' + (hit.text || '')), q, 40);
                const authorColor = getAuthorColor(hit.author);
                btn.innerHTML = '<span class="wa-search-hit-num">#' + hit.index + '</span>'
                    + '<span class="wa-search-hit-author" style="color:' + escapeAttr(authorColor) + '">' + escapeHtml(hit.author) + '</span>'
                    + '<span class="wa-search-hit-snippet">' + highlightInEscaped(escapeHtml(snippet), q) + '</span>';
                btn.addEventListener('click', function () {
                    scrollToMessageByIndex(hit.index);
                });

                if (sSentinel) searchResults.insertBefore(btn, sSentinel);
                else searchResults.appendChild(btn);
            });
            searchRenderedCount += chunk.length;
        }

        const sSentinel = document.getElementById('waSearchSentinel');
        if (sSentinel && searchResults) {
            const sObserver = new IntersectionObserver(function (entries) {
                if (entries[0].isIntersecting) {
                    renderNextSearchChunk();
                }
            }, { root: searchResults, rootMargin: '100px' });
            sObserver.observe(sSentinel);
        }

        function runSearch() {
            const q = (searchInput.value || '').trim();
            Array.from(searchResults.children).forEach(function (child) {
                if (child.id !== 'waSearchSentinel') searchResults.removeChild(child);
            });

            searchRenderedCount = 0;
            searchHits = [];

            if (q === '') {
                if (searchMeta) searchMeta.textContent = filteredMessages.length + ' mensajes visibles.';
                return;
            }

            const ql = q.toLowerCase();
            searchHits = filteredMessages.filter(function (msg) {
                return (msg.author || '').toLowerCase().includes(ql) || (msg.text || '').toLowerCase().includes(ql);
            });

            if (searchMeta) {
                searchMeta.textContent = searchHits.length + ' coincidencias en ' + filteredMessages.length + ' mensajes visibles.';
            }

            renderNextSearchChunk();
        }

        function scrollToMessageByIndex(index) {
            let el = document.getElementById('wa-msg-' + index);
            if (!el) {
                const pos = filteredMessages.findIndex(function (m) { return m.index == index; });
                if (pos !== -1) {
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
            Array.from(wrap.querySelectorAll('.wa-msg--flash')).forEach(function (m) {
                m.classList.remove('wa-msg--flash');
            });
            el.classList.add('wa-msg--flash');
            setTimeout(function () {
                el.classList.remove('wa-msg--flash');
            }, 3000);
        }

        function ensureImageModal() {
            let modal = document.getElementById('waImagePreviewModal');
            if (modal) return modal;

            modal = document.createElement('div');
            modal.id = 'waImagePreviewModal';
            modal.className = 'wa-image-modal';
            modal.hidden = true;
            modal.innerHTML = '<div class="wa-image-modal__backdrop" data-wa-close-modal="1"></div>'
                + '<div class="wa-image-modal__panel" role="dialog" aria-modal="true" aria-label="Vista previa de imagen">'
                + '    <div class="wa-image-modal__head">'
                + '        <span class="wa-image-modal__name" id="waImagePreviewName"></span>'
                + '        <button type="button" class="wa-image-modal__close" data-wa-close-modal="1" aria-label="Cerrar">×</button>'
                + '    </div>'
                + '    <div class="wa-image-modal__body">'
                + '        <img id="waImagePreviewImg" src="" alt="Vista previa">'
                + '    </div>'
                + '    <div class="wa-image-modal__actions">'
                + '        <a id="waImagePreviewDownload" class="wa-btn wa-btn-secondary wa-image-modal__btn" href="#" download>Descargar</a>'
                + '        <button type="button" id="waImagePreviewCopy" class="wa-btn wa-btn-secondary wa-image-modal__btn">Copiar imagen</button>'
                + '    </div>'
                + '</div>';

            document.body.appendChild(modal);
            return modal;
        }

        function closeImageModal() {
            const modal = document.getElementById('waImagePreviewModal');
            if (!modal) return;
            modal.hidden = true;
            document.body.classList.remove('wa-modal-open');
        }

        async function copyImageToClipboard(url) {
            if (!navigator.clipboard || typeof ClipboardItem === 'undefined') {
                throw new Error('Clipboard API not available');
            }

            const response = await fetch(url, { credentials: 'same-origin' });
            if (!response.ok) throw new Error('Failed to fetch image');

            const blob = await response.blob();
            await navigator.clipboard.write([new ClipboardItem({ [blob.type || 'image/png']: blob })]);
        }

        function openImageModal(url, filename) {
            const modal = ensureImageModal();
            const img = document.getElementById('waImagePreviewImg');
            const nameEl = document.getElementById('waImagePreviewName');
            const downloadEl = document.getElementById('waImagePreviewDownload');
            const copyBtn = document.getElementById('waImagePreviewCopy');

            if (!img || !nameEl || !downloadEl || !copyBtn) return;

            img.src = url;
            img.alt = filename || 'Imagen';
            nameEl.textContent = filename || 'Imagen';
            downloadEl.href = url;
            downloadEl.setAttribute('download', filename || 'imagen');

            copyBtn.onclick = async function () {
                const originalText = copyBtn.textContent;
                try {
                    await copyImageToClipboard(url);
                    copyBtn.textContent = 'Copiada';
                    setTimeout(function () {
                        copyBtn.textContent = originalText;
                    }, 1200);
                } catch (_err) {
                    copyBtn.textContent = 'No se pudo copiar';
                    setTimeout(function () {
                        copyBtn.textContent = originalText;
                    }, 1400);
                }
            };

            modal.hidden = false;
            document.body.classList.add('wa-modal-open');
        }

        const debouncedSearch = debounce(runSearch, 250);
        [elDateFrom, elDateTo, elAuthor, elMediaOnly, elLongOnly].forEach(function (el) {
            if (el) {
                el.addEventListener('input', applyFilters);
                el.addEventListener('change', applyFilters);
            }
        });

        wrap.addEventListener('click', function (event) {
            const imageBtn = event.target.closest('.js-wa-open-image');
            if (!imageBtn) return;

            const url = imageBtn.getAttribute('data-media-url') || '';
            const filename = imageBtn.getAttribute('data-media-filename') || 'imagen';
            if (!url) return;

            openImageModal(url, filename);
        });

        document.addEventListener('click', function (event) {
            const closeBtn = event.target.closest('[data-wa-close-modal="1"]');
            if (closeBtn) closeImageModal();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeImageModal();
        });

        searchInput.addEventListener('input', debouncedSearch);

        if (btnReset) {
            btnReset.addEventListener('click', function () {
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
