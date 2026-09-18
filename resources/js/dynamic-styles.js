/*
 * Convierte datos calculados por Blade en variables CSS.
 * Así las vistas solo describen datos y todo el diseño permanece en CSS.
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-cover-url]').forEach((element) => {
        element.style.setProperty('--custom-cover', `url(${JSON.stringify(element.dataset.coverUrl)})`);
    });

    document.querySelectorAll('[data-bracket-width][data-bracket-height]').forEach((element) => {
        element.style.setProperty('--bracket-width', `${element.dataset.bracketWidth}px`);
        element.style.setProperty('--bracket-height', `${element.dataset.bracketHeight}px`);
    });

    document.querySelectorAll('[data-progress]').forEach((element) => {
        const value = Math.min(100, Math.max(0, Number(element.dataset.progress) || 0));
        element.style.setProperty('--progress-width', `${value}%`);
    });
});
