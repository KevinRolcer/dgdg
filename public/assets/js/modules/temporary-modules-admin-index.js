document.addEventListener('DOMContentLoaded', function () {
    const deleteForms = Array.from(document.querySelectorAll('form[data-confirm-delete]'));

    const csrfToken = function (form) {
        const input = form.querySelector('input[name="_token"]') || document.querySelector('meta[name="csrf-token"]');
        return input ? (input.value || input.getAttribute('content') || '') : '';
    };

    const escapeHtml = function (value) {
        return String(value || '').replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char] || char;
        });
    };

    const progressHtml = function (moduleName, progress, deleted, total, remaining) {
        const safeName = escapeHtml(moduleName);
        const safeProgress = Math.max(0, Math.min(100, Number(progress) || 0));
        const hasTotal = Number(total) > 0;
        const detail = hasTotal
            ? escapeHtml(String(deleted || 0)) + ' de ' + escapeHtml(String(total || 0)) + ' registros eliminados'
            : 'Preparando eliminación del módulo';
        const remainingText = !hasTotal
            ? '<small class="tm-delete-progress__note">Calculando registros...</small>'
            : Number(remaining) > 0
            ? '<small class="tm-delete-progress__note">Restantes: ' + escapeHtml(String(remaining)) + '</small>'
            : '<small class="tm-delete-progress__note">Cerrando módulo...</small>';

        return ''
            + '<div class="tm-delete-progress">'
            + '<p class="tm-delete-progress__module">' + safeName + '</p>'
            + '<div class="tm-delete-progress__bar" aria-hidden="true">'
            + '<span style="width:' + safeProgress + '%"></span>'
            + '</div>'
            + '<div class="tm-delete-progress__meta">'
            + '<strong>' + safeProgress + '%</strong>'
            + '<span>' + detail + '</span>'
            + '</div>'
            + remainingText
            + '</div>';
    };

    const postDeleteBatch = async function (url, token, total) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                total: total || 0,
                batch_size: 500
            })
        });
        const data = await response.json().catch(function () { return {}; });
        if (!response.ok || data.success === false) {
            throw new Error(data.message || 'No se pudo eliminar el módulo.');
        }
        return data;
    };

    const runProgressDelete = async function (form, moduleName, progressUrl) {
        let total = 0;
        let lastData = {
            progress: 0,
            deleted: 0,
            total: 0,
            remaining: 0
        };

        Swal.fire({
            title: 'Eliminando módulo',
            html: progressHtml(moduleName, 0, 0, 0, 0),
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function () {
                Swal.showLoading();
            },
            customClass: {
                popup: 'tm-swal-popup',
                title: 'tm-swal-title',
                htmlContainer: 'tm-swal-text'
            }
        });

        while (!lastData.done) {
            lastData = await postDeleteBatch(progressUrl, csrfToken(form), total);
            total = Number(lastData.total) || total;

            Swal.update({
                html: progressHtml(
                    moduleName,
                    lastData.progress,
                    lastData.deleted,
                    total,
                    lastData.remaining
                )
            });

            await new Promise(function (resolve) {
                window.setTimeout(resolve, 80);
            });
        }

        const row = form.closest('tr');
        if (row) {
            row.remove();
        } else {
            window.location.reload();
            return;
        }

        await Swal.fire({
            title: 'Módulo eliminado',
            text: lastData.message || 'El módulo se eliminó correctamente.',
            icon: 'success',
            confirmButtonText: 'Aceptar',
            buttonsStyling: false,
            customClass: {
                popup: 'tm-swal-popup',
                title: 'tm-swal-title',
                htmlContainer: 'tm-swal-text',
                confirmButton: 'tm-swal-confirm'
            }
        });
    };

    deleteForms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const moduleName = form.getAttribute('data-module-name') || 'este módulo';
            const progressUrl = form.getAttribute('data-delete-progress-url') || '';

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: '¿Eliminar módulo?',
                    text: '¿Estás seguro de eliminar el módulo "' + moduleName + '"? Se borrarán sus registros capturados y archivos asociados.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar',
                    reverseButtons: true,
                    buttonsStyling: false,
                    customClass: {
                        popup: 'tm-swal-popup',
                        title: 'tm-swal-title',
                        htmlContainer: 'tm-swal-text',
                        confirmButton: 'tm-swal-confirm',
                        cancelButton: 'tm-swal-cancel'
                    }
                }).then(function (result) {
                    if (!result.isConfirmed) {
                        return;
                    }

                    if (!progressUrl) {
                        form.submit();
                        return;
                    }

                    runProgressDelete(form, moduleName, progressUrl).catch(function (error) {
                        Swal.fire({
                            title: 'Error',
                            text: error.message || 'No se pudo eliminar el módulo.',
                            icon: 'error',
                            confirmButtonText: 'Aceptar',
                            buttonsStyling: false,
                            customClass: {
                                popup: 'tm-swal-popup',
                                title: 'tm-swal-title',
                                htmlContainer: 'tm-swal-text',
                                confirmButton: 'tm-swal-confirm'
                            }
                        });
                    });
                });
            } else {
                if (confirm('¿Estás seguro de eliminar el módulo "' + moduleName + '"?')) {
                    form.submit();
                }
            }
        });
    });
});
