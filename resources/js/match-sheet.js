let busy = false;
let selectedTeam;
function initializeSheet() {
    const sheet = document.querySelector('[data-match-sheet]');
    if (!sheet) return;
    selectedTeam ||= sheet.querySelector('[data-sheet-team]')?.dataset.sheetTeam;
    sheet.dataset.sheetReady = '';
    sheet.querySelectorAll('[data-sheet-panel]').forEach(panel => panel.classList.toggle('is-selected', panel.dataset.sheetPanel === selectedTeam));
    sheet.querySelectorAll('[data-sheet-team]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.sheetTeam === selectedTeam)));
}
document.addEventListener('DOMContentLoaded', initializeSheet);
document.addEventListener('click', event => {
    const button = event.target.closest('[data-sheet-team]');
    if (!button) return;
    selectedTeam = button.dataset.sheetTeam;
    initializeSheet();
});
document.addEventListener('submit', async event => {
    const form = event.target.closest('[data-match-sheet] form');
    if (!form || event.defaultPrevented) return;
    if (busy || (form.dataset.sheetConfirm && !confirm(form.dataset.sheetConfirm))) {
        event.preventDefault();
        return;
    }
    if (!form.matches('[data-sheet-event]')) return;
    event.preventDefault();
    const sheet = document.querySelector('[data-match-sheet]');
    const status = sheet.querySelector('[data-sheet-status]');
    const body = new FormData(form);
    if (event.submitter?.name) body.set(event.submitter.name, event.submitter.value);
    const scrollY = window.scrollY;
    const openPlayers = [...sheet.querySelectorAll('.sheet-actions[open]')].map(item => item.querySelector('[name="jugador_id"]').value);
    busy = true;
    sheet.querySelectorAll('[data-sheet-event] button').forEach(button => button.disabled = true);
    status.textContent = 'Guardando...';
    try {
        const response = await fetch(form.action, {method:'POST',body,credentials:'same-origin',headers:{Accept:'text/html'}});
        const html = new DOMParser().parseFromString(await response.text(), 'text/html');
        const replacement = html.querySelector('[data-match-sheet]');
        if (!response.ok || !replacement) throw new Error('No se pudo confirmar el guardado. Recarga para comprobar el registro antes de repetirlo.');
        sheet.replaceWith(replacement);
        initializeSheet();
        replacement.querySelectorAll('.sheet-actions').forEach(item => { item.open = openPlayers.includes(item.querySelector('[name="jugador_id"]').value); });
        const error = replacement.querySelector('.alert-danger');
        replacement.querySelector('[data-sheet-status]').textContent = error ? error.textContent.trim() : 'Planilla actualizada.';
        requestAnimationFrame(() => window.scrollTo(0, scrollY));
    } catch (error) {
        status.textContent = error.message;
        // No reenviar automaticamente una operacion cuyo resultado es incierto.
    } finally {
        busy = false;
    }
});
