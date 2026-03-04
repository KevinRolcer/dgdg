document.addEventListener('DOMContentLoaded', function () {
    var togglePasswordButton = document.getElementById('togglePassword');
    var passwordInput = document.getElementById('password');

    if (!togglePasswordButton || !passwordInput) {
        return;
    }

    togglePasswordButton.addEventListener('click', function () {
        var passwordIsHidden = passwordInput.getAttribute('type') === 'password';
        passwordInput.setAttribute('type', passwordIsHidden ? 'text' : 'password');

        var icon = togglePasswordButton.querySelector('i');
        if (icon) {
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        }

        togglePasswordButton.setAttribute('aria-label', passwordIsHidden ? 'Ocultar contraseña' : 'Mostrar contraseña');
    });
});
