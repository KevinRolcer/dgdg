(function () {
    'use strict';

    function onSubmit(e) {
        var form = e.target && e.target.closest ? e.target.closest('form.js-wa-chat-delete-form') : null;
        if (!form) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();

        var title = form.getAttribute('data-wa-delete-title') || '¿Eliminar esta exportación?';
        var text =
            form.getAttribute('data-wa-delete-text') ||
            'Se eliminarán los archivos del servidor. Esta acción no se puede deshacer.';

        var swal = window.Swal;
        if (!swal || typeof swal.fire !== 'function') {
            if (window.confirm(text)) {
                form.submit();
            }
            return;
        }

        swal
            .fire({
                title: title,
                text: text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                focusCancel: true,
                reverseButtons: true,
                customClass: {
                    popup: 'tm-swal-popup',
                    title: 'tm-swal-title',
                    htmlContainer: 'tm-swal-text',
                    confirmButton: 'tm-swal-confirm btn-danger',
                    cancelButton: 'tm-swal-cancel'
                }
            })
            .then(function (result) {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
    }

    document.addEventListener('submit', onSubmit, true);
})();
