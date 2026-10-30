import './bootstrap';
import './dynamic-styles';
import './bracket';

// Mantiene visible la sección activa sin desplazar verticalmente la página.
document.querySelectorAll('.navbar-context-tabs').forEach(menu => {
    const revealActiveTab = () => {
        const active = menu.querySelector('.active');
        if (!active || menu.scrollWidth <= menu.clientWidth) return;

        const bounds = menu.getBoundingClientRect();
        const tab = active.getBoundingClientRect();
        if (tab.left < bounds.left || tab.right > bounds.right) {
            menu.scrollTo({
                left: menu.scrollLeft + tab.left - bounds.left - (menu.clientWidth - tab.width) / 2,
                behavior: 'auto',
            });
        }
    };

    const observer = new MutationObserver(revealActiveTab);
    observer.observe(menu, { subtree: true, attributes: true, attributeFilter: ['class'] });
    window.addEventListener('resize', revealActiveTab);
    requestAnimationFrame(revealActiveTab);
});

import './module-context';

// Control explicito para mostrar u ocultar la clave sin enviar el formulario.
document.querySelectorAll('[data-password-toggle]').forEach(button => {
    const input = document.getElementById(button.dataset.passwordToggle);
    if (!input) return;
    button.addEventListener('click', () => {
        const visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        const label = visible ? 'Ocultar contrase\u00f1a' : 'Mostrar contrase\u00f1a';
        button.setAttribute('aria-pressed', String(visible));
        button.setAttribute('aria-label', label);
        button.title = label;
        button.querySelector('i').className = visible ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
});

import './match-sheet';

import './roster-numbers';
import './login-validation';
