@php
    $config = isset($campeonato) ? ($campeonato->formato_config ?? []) : [];
@endphp
<div class="col-12" id="opciones-formato">
    <div data-format-options="liga" class="mb-3">
        <label for="vueltas_liga" class="form-label">Partidos de Liga</label>
        <select id="vueltas_liga" name="vueltas_liga" class="form-select">
            <option value="1" @selected(old('vueltas_liga', $config['league_rounds'] ?? 1) == 1)>Una vuelta</option>
            <option value="2" @selected(old('vueltas_liga', $config['league_rounds'] ?? 1) == 2)>Ida y vuelta</option>
        </select>
    </div>
    <div data-format-options="grupos_eliminacion" class="mb-3">
        <label for="vueltas_grupos" class="form-label">Partidos de la fase de grupos</label>
        <select id="vueltas_grupos" name="vueltas_grupos" class="form-select">
            <option value="1" @selected(old('vueltas_grupos', $config['group_rounds'] ?? 1) == 1)>Una vuelta</option>
            <option value="2" @selected(old('vueltas_grupos', $config['group_rounds'] ?? 1) == 2)>Ida y vuelta</option>
        </select>
    </div>
    <div data-format-options="grupos_eliminacion eliminacion">
        <div class="form-check mb-2">
            <input type="hidden" name="ida_vuelta_eliminatorias" value="0">
            <input class="form-check-input" type="checkbox" id="ida_vuelta_eliminatorias" name="ida_vuelta_eliminatorias" value="1" @checked(old('ida_vuelta_eliminatorias', $config['eliminations_home_away'] ?? false))>
            <label class="form-check-label" for="ida_vuelta_eliminatorias">Ida y vuelta en eliminatorias, hasta semifinales</label>
        </div>
        <div class="form-check mb-2">
            <input type="hidden" name="partido_tercer_lugar" value="0">
            <input class="form-check-input" type="checkbox" id="partido_tercer_lugar" name="partido_tercer_lugar" value="1" @checked(old('partido_tercer_lugar', $config['third_place_match'] ?? false))>
            <label class="form-check-label" for="partido_tercer_lugar">Incluir partido por el tercer puesto</label>
        </div>
        <p class="small">La final y el tercer puesto se juegan siempre a partido único.</p>
    </div>
    <p class="small mt-3" data-format-summary aria-live="polite"></p>
    <p class="small">Estas reglas quedan bloqueadas cuando se aprueba el campeonato.</p>
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const format = document.getElementById('formato');
    const options = document.getElementById('opciones-formato');
    const update = () => {
        const value = format.value;
        options.querySelectorAll('[data-format-options]').forEach(group => {
            const visible = group.dataset.formatOptions.split(' ').includes(value);
            group.hidden = !visible;
            group.querySelectorAll('input, select').forEach(input => input.disabled = !visible);
        });
        const rounds = id => document.getElementById(id).value === '2' ? 'ida y vuelta' : 'una vuelta';
        let summary = '';
        if (value === 'liga') summary = `Liga a ${rounds('vueltas_liga')}.`;
        if (['grupos_eliminacion', 'eliminacion'].includes(value)) {
            if (value === 'grupos_eliminacion') summary = `Grupos a ${rounds('vueltas_grupos')}. `;
            summary += document.getElementById('ida_vuelta_eliminatorias').checked
                ? 'Eliminatorias de ida y vuelta hasta semifinales. ' : 'Eliminatorias a partido único. ';
            summary += 'Final a partido único. ';
            summary += document.getElementById('partido_tercer_lugar').checked
                ? 'Tercer puesto a partido único.' : 'Sin partido por el tercer puesto.';
        }
        document.querySelectorAll('[data-format-summary]').forEach(node => node.textContent = summary);
    };
    format.addEventListener('change', update);
    options.addEventListener('change', update);
    update();
});
</script>
@endpush
