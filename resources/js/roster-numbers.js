document.querySelectorAll('[data-roster-numbers]').forEach(form => {
    const inputs = [...form.querySelectorAll('[data-roster-number]')];

    const validateNumbers = () => {
        const counts = new Map();
        inputs.forEach(input => {
            if (input.disabled || input.value === '') return;
            const number = Number(input.value);
            counts.set(number, (counts.get(number) || 0) + 1);
        });

        inputs.forEach(input => {
            const duplicate = !input.disabled && input.value !== '' && counts.get(Number(input.value)) > 1;
            const message = duplicate ? `El dorsal ${Number(input.value)} ya lo tiene otro jugador. Usa otro n\u00famero.` : '';
            input.setCustomValidity(message);
            input.classList.toggle('is-invalid', duplicate);
            if (duplicate) input.setAttribute('aria-invalid', 'true');
            else input.removeAttribute('aria-invalid');
            input.parentElement.querySelector('[data-number-error]').textContent = message;
        });
    };

    form.addEventListener('input', validateNumbers);
    form.addEventListener('change', validateNumbers);
    form.addEventListener('submit', event => {
        validateNumbers();
        if (!form.checkValidity()) {
            event.preventDefault();
            form.reportValidity();
        }
    });
    validateNumbers();
});
