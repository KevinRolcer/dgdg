function onRoleChange(role) {
    const sd = document.getElementById('section-delegado');
    const se = document.getElementById('section-enlace');
    const sa = document.getElementById('section-auditor');
    const mrD = document.getElementById('microrregion_id');
    const mrE = document.getElementById('microrregion_ids');
    const r = (role || '').toLowerCase();
    
    if (sd) sd.style.display = 'none';
    if (se) se.style.display = 'none';
    if (sa) sa.style.display = 'none';
    if (mrD) mrD.required = false;
    if (mrE) mrE.required = false;
    
    if (r === 'delegado') {
        if (sd) sd.style.display = 'block';
        if (mrD) mrD.required = true;
    } else if (r === 'enlace') {
        if (se) se.style.display = 'block';
        if (mrE) mrE.required = true;
    } else if (r === 'auditor') {
        if (sa) sa.style.display = 'block';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const roleSelect = document.getElementById('role');
    if (roleSelect) {
        // Initial state
        onRoleChange(roleSelect.value);
    }
});
