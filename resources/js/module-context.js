import { Tab } from 'bootstrap';

// El contexto se conserva por pagina y por pestana del navegador.
document.addEventListener('DOMContentLoaded', () => {
    const key = `module-context:${location.pathname}`;
    const read = () => {
        try { return JSON.parse(sessionStorage.getItem(key) || '{}'); }
        catch { return {}; }
    };
    const write = (value) => {
        try { sessionStorage.setItem(key, JSON.stringify(value)); } catch {}
    };
    const tabs = [...document.querySelectorAll('.navbar-context-tabs [data-bs-toggle="tab"]')];
    const saved = read();
    const restoreTab = () => {
        const section = new URLSearchParams(location.search).get('seccion');
        const requested = location.hash || ({ reglamentos: '#reglamentos', partidos: '#par', equipos: '#eq', tabla: '#tab', llaves: '#llaves' }[section]);
        const target = requested || read().tab;
        const selected = tabs.find(tab => tab.dataset.bsTarget === target);
        if (selected) Tab.getOrCreateInstance(selected).show();
    };
    tabs.forEach(tab => tab.addEventListener('shown.bs.tab', () => {
        const target = tab.dataset.bsTarget;
        write({ ...read(), tab: target });
        const url = new URL(location.href);
        url.hash = target;
        url.searchParams.delete('seccion');
        history.replaceState(history.state, '', url);
    }));
    restoreTab();
    // Atras puede restaurar la pagina desde la cache sin DOMContentLoaded.
    window.addEventListener('pageshow', restoreTab);
    window.addEventListener('popstate', restoreTab);

    // Restaurar el desplazamiento solo tras enviar un formulario en esta pagina.
    if (saved.submittedAt && Date.now() - saved.submittedAt < 120000) {
        const restore = () => requestAnimationFrame(() => window.scrollTo(0, saved.scrollY || 0));
        if (document.readyState === 'complete') restore();
        else window.addEventListener('load', restore, { once: true });
        write({ tab: saved.tab });
    }
    document.addEventListener('submit', event => {
        if (event.defaultPrevented) return;
        const active = tabs.find(tab => tab.classList.contains('active'));
        write({ tab: active?.dataset.bsTarget || read().tab, scrollY: window.scrollY, submittedAt: Date.now() });
    });
});
