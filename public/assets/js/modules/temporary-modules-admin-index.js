document.addEventListener('DOMContentLoaded', function () {
    const deleteForms = Array.from(document.querySelectorAll('form[data-confirm-delete]'));
    
    deleteForms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const moduleName = form.getAttribute('data-module-name') || 'este módulo';
            
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: '¿Eliminar módulo?',
                    text: '¿Estás seguro de eliminar el módulo "' + moduleName + '"? Los registros capturados se conservarán.',
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
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            } else {
                if (confirm('¿Estás seguro de eliminar el módulo "' + moduleName + '"?')) {
                    form.submit();
                }
            }
        });
    });
});
