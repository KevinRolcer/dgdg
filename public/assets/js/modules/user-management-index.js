document.addEventListener('DOMContentLoaded', function () {
    const deleteForms = document.querySelectorAll('form[data-confirm-delete]');
    
    deleteForms.forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const name = form.getAttribute('data-user-name') || 'este usuario';
            
            if (typeof Swal === 'undefined') {
                if (confirm('¿Eliminar a "' + name + '"?')) {
                    form.submit();
                }
                return;
            }

            Swal.fire({
                title: '¿Eliminar usuario?',
                text: 'Se eliminará a "' + name + '" de manera permanente.',
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
        });
    });
});
