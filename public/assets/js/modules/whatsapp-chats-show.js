document.addEventListener('DOMContentLoaded', function () {
    const iframe = document.getElementById('waChatIframe');
    const buttons = Array.from(document.querySelectorAll('.wa-part-btn'));
    if (!iframe) {
        // Modo TXT: no hay iframe ni cambio de partes.
        return;
    }

    function setActive(btn) {
        buttons.forEach(b => b.classList.remove('wa-active-part'));
        btn.classList.add('wa-active-part');
    }

    buttons.forEach(btn => {
        btn.addEventListener('click', () => {
            const url = btn.getAttribute('data-part-url') || '';
            if (!url) return;
            setActive(btn);
            iframe.src = url;
        });
    });
});
