document.addEventListener('DOMContentLoaded', function () {
    var nav = document.getElementById('settingsShellNav');
    var toggle = document.getElementById('settingsNavToggle');
    var backdrop = document.getElementById('settingsShellBackdrop');

    if (!toggle || !nav) {
        return;
    }

    function openNav() {
        nav.classList.add('is-open');
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', 'Cerrar menu de ajustes');
        if (backdrop) {
            backdrop.setAttribute('aria-hidden', 'false');
            backdrop.classList.add('is-visible');
        }
    }

    function closeNav() {
        nav.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Abrir menu de ajustes');
        if (backdrop) {
            backdrop.setAttribute('aria-hidden', 'true');
            backdrop.classList.remove('is-visible');
        }
    }

    toggle.addEventListener('click', function () {
        if (nav.classList.contains('is-open')) {
            closeNav();
            return;
        }
        openNav();
    });

    if (backdrop) {
        backdrop.addEventListener('click', closeNav);
    }

    window.addEventListener('resize', function () {
        if (window.innerWidth >= 769) {
            closeNav();
        }
    });
});
