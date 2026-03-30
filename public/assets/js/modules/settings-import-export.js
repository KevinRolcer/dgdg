document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('btnAbrirListaMicroregiones');
    var modal = document.getElementById('modalListaMicroregiones');

    function bindModal(trigger, target, closeSelector) {
        if (!trigger || !target) {
            return;
        }

        function openModal() {
            target.classList.add('is-open');
            target.setAttribute('aria-hidden', 'false');
        }

        function closeModal() {
            target.classList.remove('is-open');
            target.setAttribute('aria-hidden', 'true');
        }

        trigger.addEventListener('click', openModal);
        target.querySelectorAll(closeSelector).forEach(function (element) {
            element.addEventListener('click', closeModal);
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && target.classList.contains('is-open')) {
                closeModal();
            }
        });
    }

    bindModal(btn, modal, '[data-close-microrregiones-modal]');
    bindModal(
        document.getElementById('btnAbrirLogDistribucion'),
        document.getElementById('modalLogDistribucion'),
        '[data-close-log-modal]'
    );

    function bindPopover(buttonId, popoverId) {
        var button = document.getElementById(buttonId);
        var popover = document.getElementById(popoverId);
        if (!button || !popover) {
            return;
        }

        var closeButton = popover.querySelector('.settings-help-popover-close');

        function closePopover() {
            popover.setAttribute('aria-hidden', 'true');
            button.setAttribute('aria-expanded', 'false');
            popover.classList.remove('is-open');
        }

        button.addEventListener('click', function (event) {
            event.preventDefault();
            var isOpen = popover.getAttribute('aria-hidden') !== 'true';
            if (isOpen) {
                closePopover();
                return;
            }
            popover.setAttribute('aria-hidden', 'false');
            button.setAttribute('aria-expanded', 'true');
            popover.classList.add('is-open');
        });

        if (closeButton) {
            closeButton.addEventListener('click', closePopover);
        }
    }

    bindPopover('btnMicroHelp', 'microHelpPopover');
    bindPopover('btnMigrateHelp', 'migrateHelpPopover');

    var fileInput = document.getElementById('distribuir_excel_file');
    var fileName = document.getElementById('microFileName');
    var uploadZone = document.getElementById('microUploadZone');

    if (!fileInput || !fileName || !uploadZone) {
        return;
    }

    function syncFileState(file) {
        fileName.textContent = file ? file.name : '—';
        uploadZone.classList.toggle('has-file', Boolean(file));
    }

    fileInput.addEventListener('change', function () {
        syncFileState(this.files && this.files[0] ? this.files[0] : null);
    });

    uploadZone.addEventListener('dragover', function (event) {
        event.preventDefault();
        uploadZone.classList.add('is-dragover');
    });

    uploadZone.addEventListener('dragleave', function () {
        uploadZone.classList.remove('is-dragover');
    });

    uploadZone.addEventListener('drop', function (event) {
        event.preventDefault();
        uploadZone.classList.remove('is-dragover');
        if (!event.dataTransfer.files.length) {
            return;
        }
        fileInput.files = event.dataTransfer.files;
        syncFileState(event.dataTransfer.files[0]);
    });
});
