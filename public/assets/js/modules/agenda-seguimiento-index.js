document.addEventListener('DOMContentLoaded', function () {
    // Actions toggle
    document.querySelectorAll('[data-agenda-seg-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var wrap = this.closest('.agenda-seg-actions-wrap');
            if (!wrap) return;
            var isOpen = wrap.classList.toggle('is-open');
            this.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    });

    // Filters logic
    var form = document.getElementById('agendaSegFiltersForm');
    if (form) {
        var hidden = form.querySelector('#agendaSegFilterClasificacion');
        form.querySelectorAll('[data-agenda-seg-clasificacion]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                if (btn.tagName === 'A') return;
                e.preventDefault();
                var v = btn.getAttribute('data-agenda-seg-clasificacion');
                if (hidden) hidden.value = v;
                form.submit();
            });
        });

        var adv = document.getElementById('agendaSegFiltersAdvanced');
        var btnMas = document.getElementById('agendaSegBtnMasFiltros');
        var textMas = document.getElementById('agendaSegBtnMasFiltrosText');
        if (btnMas && adv) {
            btnMas.addEventListener('click', function () {
                var open = !adv.classList.contains('is-open');
                adv.classList.toggle('is-open', open);
                adv.hidden = !open;
                btnMas.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (textMas) textMas.textContent = open ? 'Menos filtros' : 'Más filtros';
            });
        }
    }

    // Row toggle
    document.querySelectorAll('[data-agenda-seg-row-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var row = this.closest('tr');
            if (!row) return;
            var next = row.nextElementSibling;
            if (next && next.classList.contains('agenda-seg-detail-row')) {
                next.hidden = !next.hidden;
                next.classList.toggle('is-open', !next.hidden);
                btn.setAttribute('aria-expanded', next.hidden ? 'false' : 'true');
                var wrap = next.querySelector('.agenda-seg-actions-wrap');
                if (wrap) wrap.classList.toggle('is-open', !next.hidden);
            }
        });
    });
});
