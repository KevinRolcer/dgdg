document.addEventListener('DOMContentLoaded', function () {
    const evidenciasPanel = document.getElementById('evidenciasPanelBody');
    const paginationContainer = document.getElementById('evidenciasPagination');
    if (!paginationContainer) return;

    paginationContainer.addEventListener('click', function (e) {
        const target = e.target.closest('a');
        if (target && target.tagName === 'A' && target.href) {
            e.preventDefault();
            cargarPaginaEvidencias(target.href);
        }
    });

    function cargarPaginaEvidencias(url) {
        if (!evidenciasPanel) return;
        evidenciasPanel.classList.add('position-relative');
        let loader = document.createElement('div');
        loader.className = 'ajax-loader';
        loader.style.position = 'absolute';
        loader.style.top = 0;
        loader.style.left = 0;
        loader.style.width = '100%';
        loader.style.height = '100%';
        loader.style.background = 'rgba(255,255,255,0.7)';
        loader.style.display = 'flex';
        loader.style.alignItems = 'center';
        loader.style.justifyContent = 'center';
        loader.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div>';
        evidenciasPanel.appendChild(loader);

        fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(resp => resp.text())
            .then(html => {
                // Extraer solo el contenido del panel de evidencias
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = html;
                const newPanel = tempDiv.querySelector('#evidenciasPanelBody');
                if (newPanel) {
                    evidenciasPanel.innerHTML = newPanel.innerHTML;
                } else {
                    evidenciasPanel.innerHTML = '<div class="alert alert-danger">Error al cargar la página.</div>';
                }
            })
            .catch(() => {
                evidenciasPanel.innerHTML = '<div class="alert alert-danger">Error de conexión.</div>';
            });
    }
});
