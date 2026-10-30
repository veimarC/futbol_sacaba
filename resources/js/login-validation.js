const loginForm = document.querySelector('[data-login-validation]');
if (loginForm) {
    // Sin JavaScript permanece activa la validación nativa del navegador.
    loginForm.noValidate = true;
    const fields = ['email', 'password'].map(name => loginForm.elements.namedItem(name));
    const validate = input => {
        let message = '';
        if (!input.value.trim()) {
            message = input.name === 'email' ? 'Ingresa tu correo electrónico.' : 'Ingresa tu contraseña.';
        } else if (input.name === 'email' && input.validity.typeMismatch) {
            message = 'Escribe un correo válido, por ejemplo: nombre@correo.com.';
        }
        input.classList.toggle('is-invalid', Boolean(message));
        input.setAttribute('aria-invalid', String(Boolean(message)));
        document.getElementById(`login-${input.name}-error`).textContent = message;
        return !message;
    };
    fields.forEach(input => {
        input.setAttribute('aria-describedby', `login-${input.name}-error`);
        input.setAttribute('aria-invalid', String(input.classList.contains('is-invalid')));
        input.addEventListener('blur', () => validate(input));
        input.addEventListener('input', () => {
            if (input.classList.contains('is-invalid')) validate(input);
        });
    });
    loginForm.addEventListener('submit', event => {
        const valid = fields.map(validate).every(Boolean);
        if (!valid) {
            event.preventDefault();
            fields.find(input => input.classList.contains('is-invalid')).focus();
        }
    });
}
