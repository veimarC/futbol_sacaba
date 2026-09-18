@extends('layouts.app')

@section('content')
<div class="public-detail">
    <a href="{{ route('publico.index') }}" class="btn btn-borde btn-sm mb-3"><i class="bi bi-arrow-left me-1"></i> Volver a campeonatos</a>
    @php
        $portadaUrl = $campeonato->portada_url;
    @endphp
    <header class="detail-hero animar-aparecer {{ $portadaUrl ? 'has-custom-cover' : '' }}" @if($portadaUrl) data-cover-url="{{ $portadaUrl }}" @endif>
        <div class="detail-icon"><i class="bi bi-trophy-fill"></i></div>
        <div class="flex-grow-1"><div class="d-flex flex-wrap gap-2 mb-2"><span class="badge {{ $campeonato->estado_actual === 'en_curso' ? 'bg-success' : ($campeonato->estado_actual === 'pendiente' ? 'bg-warning text-dark' : 'bg-secondary') }}">{{ $campeonato->estado_actual_label }}</span><span class="badge bg-info text-dark">{{ $campeonato->formato_label }}</span></div><h1>{{ $campeonato->nombre }}</h1><p>{{ $campeonato->descripcion ?: 'Información oficial del campeonato, equipos participantes, partidos y posiciones.' }}</p><div class="detail-dates"><span><i class="bi bi-calendar-event"></i> Inicio: {{ $campeonato->fecha_inicio->format('d/m/Y') }}</span>@if($campeonato->fecha_fin)<span><i class="bi bi-calendar-check"></i> Fin: {{ $campeonato->fecha_fin->format('d/m/Y') }}</span>@endif</div></div>
        @if($campeonato->organizador)
            <div class="detail-organizer"><i class="bi bi-person-badge"></i> Organiza: <strong>{{ $campeonato->organizador->name }}</strong></div>
        @endif
    </header>

    <nav class="detail-nav" aria-label="Secciones del campeonato" role="tablist">
        <a href="#resumen" data-module="resumen"><i class="bi bi-info-circle"></i> Información</a>
        <a href="#equipos" data-module="equipos"><i class="bi bi-shield-fill"></i> Equipos</a>
        <a href="#partidos" data-module="partidos"><i class="bi bi-calendar-event"></i> Partidos</a>
        @if($mostrarLlaves)
            <a href="#llaves" data-module="llaves"><i class="bi bi-diagram-3-fill"></i> Llaves</a>
        @endif
        <a href="#tabla" data-module="tabla"><i class="bi bi-bar-chart-steps"></i> Tabla</a>
        <a href="#goleadores" data-module="goleadores"><i class="bi bi-award"></i> Goleadores</a>
        <a href="#tarjetas" data-module="tarjetas"><i class="bi bi-card-list"></i> Tarjetas</a>
    </nav>

    <section id="resumen" class="detail-module is-active" role="tabpanel">
    <section class="quick-summary" aria-label="Información rápida del campeonato">
        <div class="summary-card main-summary"><i class="bi bi-calendar-event"></i><p><small>Próximo partido</small>@if($proximoPartido)<strong>{{ $proximoPartido->equipoLocal?->nombre ?: 'Por definir' }} vs {{ $proximoPartido->equipoVisita?->nombre ?: 'Por definir' }}</strong><span>{{ $proximoPartido->fecha?->format('d/m/Y') }} · {{ $proximoPartido->hora ? \Carbon\Carbon::parse($proximoPartido->hora)->format('H:i') : 'Hora por definir' }}</span>@else<strong>Sin partido programado</strong><span>Consulta nuevamente más adelante.</span>@endif</p></div>
        <div class="summary-card"><i class="bi bi-people-fill"></i><p><small>Equipos</small><strong>{{ $equipos->count() }}</strong><span>participantes aprobados</span></p></div>
        <div class="summary-card"><i class="bi bi-clipboard-data"></i><p><small>Partidos</small><strong>{{ $partidos->count() }}</strong><span>en el calendario</span></p></div>
    </section>

    <div class="content-heading"><span>Reglas principales</span><h2>Así se juega este campeonato</h2></div>
    <section class="public-rules">
        <div><i class="bi bi-dribbble"></i><small>Modalidad</small><strong>Fútbol {{ $campeonato->modalidad ?: 'libre' }}</strong></div><div><i class="bi bi-calendar3"></i><small>Categoría</small><strong>{{ $campeonato->categoria_edad_label ?: 'Libre' }}</strong></div><div><i class="bi bi-gender-ambiguous"></i><small>Participación</small><strong>{{ $campeonato->sexo_label ?: 'Mixto' }}</strong></div>
    </section>
    </section>

    <section id="partidos" class="public-section detail-module" role="tabpanel">
        <div class="section-title"><div><span>Calendario oficial</span><h2>Partidos y resultados</h2></div><strong>{{ $partidos->count() }} partidos</strong></div>
        @forelse($partidos as $partido)
            <article class="match-row">
                <div class="match-date"><strong>{{ $partido->fecha?->format('d/m') ?: '—' }}</strong><span>{{ $partido->hora ? \Carbon\Carbon::parse($partido->hora)->format('H:i') : 'Por definir' }}</span></div>
                <div class="match-teams">
                    <span class="match-club is-local"><span>{{ $partido->equipoLocal?->nombre ?: 'Por definir' }}</span><x-team-crest :equipo="$partido->equipoLocal" /></span>
                    <div class="score">@if($partido->estado === 'jugado')<strong>{{ $partido->goles_local }}</strong><small>-</small><strong>{{ $partido->goles_visita }}</strong>@else<small>VS</small>@endif</div>
                    <span class="match-club"><x-team-crest :equipo="$partido->equipoVisita" /><span>{{ $partido->equipoVisita?->nombre ?: 'Por definir' }}</span></span>
                </div>
                <div class="match-place"><span><i class="bi bi-geo-alt"></i>{{ $partido->cancha ?: 'Cancha por definir' }}</span>@if($partido->arbitro)<small><i class="bi bi-flag"></i>{{ $partido->arbitro->name }}</small>@endif</div>
            </article>
        @empty<div class="public-empty"><i class="bi bi-calendar2-x"></i><p>Todavía no existen partidos programados.</p></div>@endforelse
    </section>

    @if($mostrarLlaves)
        <section id="llaves" class="public-section detail-module" role="tabpanel">
            <div class="section-title">
                <div><span>Fase eliminatoria</span><h2>Llaves del campeonato</h2></div>
                <strong>El ganador avanza</strong>
            </div>
            <p class="bracket-help"><i class="bi bi-arrow-left-right"></i> Desliza horizontalmente para recorrer las rondas. Los ganadores avanzan hacia la derecha.</p>
            <div class="bracket-scroll" tabindex="0" aria-label="Cuadro de eliminación">
                {{-- Cada columna es una etapa. En pantallas pequeñas se muestran
                     una debajo de otra para mantener un orden de lectura claro. --}}
                @php
                    $serieFinal = $llaves->last()['series']->first();
                    $campeon = null;

                    if ($serieFinal && $serieFinal['completa']) {
                        $campeon = $serieFinal['goles_a'] > $serieFinal['goles_b'] ? $serieFinal['equipo_a'] : ($serieFinal['goles_b'] > $serieFinal['goles_a'] ? $serieFinal['equipo_b'] : null);
                    }
                @endphp
                <div class="stage-bracket">
                    <header class="stage-title"><i class="bi bi-trophy-fill"></i><strong>CAMINO A LA COPA</strong></header>

                    <div class="stage-flow" data-bracket-flow data-bracket-width="{{ max(900, ($llaves->count() * 317) + 265) }}" data-bracket-height="{{ max(390, $llaves->first()['series']->count() * 145) }}">
                        <svg class="stage-connectors" aria-hidden="true"></svg>
                        @foreach($llaves as $indice => $ronda)
                            <section class="stage-column {{ $loop->last ? 'is-final' : '' }}" aria-label="{{ $ronda['nombre'] }}">
                                <header>
                                    <span>{{ $indice + 1 }}</span>
                                    <div>
                                        <small>Etapa {{ $indice + 1 }}</small>
                                        <h3>{{ $loop->last ? 'Gran final' : $ronda['nombre'] }}</h3>
                                    </div>
                                </header>
                                <div class="stage-matches">
                                    @foreach($ronda['series'] as $serie)
                                        @include('publico.partials.bracket-node', ['serie' => $serie])
                                    @endforeach
                                </div>
                            </section>

                            <div class="stage-arrow" aria-hidden="true">
                                <i class="bi bi-arrow-right"></i>
                                <span>{{ $loop->last ? 'Resultado final' : 'Ganadores avanzan' }}</span>
                            </div>
                        @endforeach

                        <div class="stage-outcomes">
                            <aside class="stage-champion {{ $campeon ? 'has-champion' : '' }}" aria-label="Campeón del campeonato">
                                <i class="bi bi-trophy-fill"></i>
                                @if($campeon)<x-team-crest :equipo="$campeon" />@endif
                                <span><small>Campeón</small><strong>{{ $campeon?->nombre ?? 'Por definir' }}</strong></span>
                            </aside>

                            @if($tercerLugar)
                                <section class="third-place-card" aria-label="Partido por el tercer lugar">
                                    <header><i class="bi bi-award-fill"></i><span><small>Partido de bronce</small><strong>Tercer lugar</strong></span></header>
                                    @include('publico.partials.bracket-node', ['serie' => $tercerLugar])
                                    <p>Lo disputan los perdedores de las semifinales.</p>
                                </section>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    <section id="tabla" class="public-section detail-module" role="tabpanel">
        <div class="section-title"><div><span>Clasificación</span><h2>Tabla de posiciones</h2></div></div>
        @if($tablasGrupos->isNotEmpty())
            <div class="group-tables">
                @foreach($tablasGrupos as $grupo)
                    <div class="group-table-card">
                        <h3>{{ $grupo['nombre'] }}</h3>
                        @include('publico.partials.tabla-posiciones', ['filas' => $grupo['tabla']])
                    </div>
                @endforeach
            </div>
        @elseif(empty($tabla))
            <div class="public-empty"><i class="bi bi-bar-chart"></i><p>La tabla aparecerá cuando existan equipos.</p></div>
        @else
            @include('publico.partials.tabla-posiciones', ['filas' => $tabla])
        @endif
    </section>

    <section id="equipos" class="public-section detail-module" role="tabpanel">
        <div class="section-title">
            <div><span>Participantes</span><h2>Equipos inscritos</h2></div>
            <strong>{{ $equipos->count() }} equipos</strong>
        </div>
        <div class="team-grid">
            @forelse($equipos as $equipo)
                <article class="public-team">
                    <div class="team-logo">
                        @if($escudoUrl = $equipo->escudo_url)
                            <img src="{{ $escudoUrl }}" alt="Escudo de {{ $equipo->nombre }}">
                        @else
                            <i class="bi bi-shield"></i>
                        @endif
                    </div>
                    <h3>{{ $equipo->nombre }}</h3>
                    <p>Delegado: {{ $equipo->delegado?->name ?: 'No registrado' }}</p>
                    <span><i class="bi bi-geo-alt me-1"></i>{{ $equipo->ciudad ?: 'Sacaba' }}</span>
                    <small><i class="bi bi-people me-1"></i>{{ $equipo->jugadores_aprobados_count }} jugadores aprobados</small>
                    <a href="#plantillas-equipo-{{ $equipo->id }}" class="btn btn-light btn-sm mt-auto" data-module="plantillas" data-team="{{ $equipo->id }}">Ver plantilla</a>
                </article>
            @empty
                <div class="public-empty w-100"><i class="bi bi-shield-x"></i><p>No hay equipos aprobados.</p></div>
            @endforelse
        </div>
    </section>

    <section id="goleadores" class="public-section detail-module" role="tabpanel">
        <div class="section-title"><div><span>Estadísticas</span><h2>Historial de goleadores</h2></div><strong>{{ $goleadores->sum('goles_count') }} goles registrados</strong></div>
        @if($goleadores->isEmpty())
            <div class="public-empty"><i class="bi bi-award"></i><p>Los goleadores aparecerán cuando se registren incidencias de gol.</p></div>
        @else
            <div class="scorer-list">
                @foreach($goleadores as $puesto => $jugador)
                    <article class="scorer-entry">
                        <div class="scorer-main">
                            <span class="position-number">{{ $puesto + 1 }}</span>
                            <div class="scorer-name"><strong>{{ $jugador->nombre }} {{ $jugador->apellido }}</strong></div>
                            <span class="scorer-club"><x-team-crest :equipo="$jugador->equipo" /><span>{{ $jugador->equipo?->nombre }}</span></span>
                            <b>{{ $jugador->goles_count }} {{ $jugador->goles_count === 1 ? 'gol' : 'goles' }}</b>
                        </div>
                        <details class="goal-history">
                            <summary><i class="bi bi-clock-history"></i> Ver historial de goles</summary>
                            <div class="goal-events">
                                @foreach($jugador->incidencias as $gol)
                                    <div>
                                        <span><strong>{{ $gol->partido?->equipoLocal?->nombre }}</strong> {{ $gol->partido?->goles_local }}–{{ $gol->partido?->goles_visita }} <strong>{{ $gol->partido?->equipoVisita?->nombre }}</strong></span>
                                        <small>{{ $gol->partido?->fecha?->format('d/m/Y') }}</small>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section id="tarjetas" class="public-section detail-module" role="tabpanel">
        <div class="section-title"><div><span>Disciplina</span><h2>Tarjetas amarillas y rojas</h2></div><strong>{{ $tarjetas->count() }} jugadores</strong></div>
        @if($tarjetas->isEmpty())
            <div class="public-empty"><i class="bi bi-card-list"></i><p>Todavía no se registraron tarjetas en este campeonato.</p></div>
        @else
            <div class="table-responsive">
                <table class="table public-table discipline-table align-middle">
                    <thead><tr><th>#</th><th>Jugador</th><th>Equipo</th><th class="text-center">Amarillas</th><th class="text-center">Rojas</th><th class="text-center">Total</th></tr></thead>
                    <tbody>
                        @foreach($tarjetas as $puesto => $jugador)
                            <tr>
                                <td><span class="position-number">{{ $puesto + 1 }}</span></td>
                                <td class="fw-bold">{{ $jugador->nombre }} {{ $jugador->apellido }}</td>
                                <td><span class="table-club"><x-team-crest :equipo="$jugador->equipo" /><span>{{ $jugador->equipo?->nombre }}</span></span></td>
                                <td class="text-center"><span class="card-count yellow-card">{{ $jugador->amarillas_count }}</span></td>
                                <td class="text-center"><span class="card-count red-card">{{ $jugador->rojas_count }}</span></td>
                                <td class="text-center fw-bold">{{ $jugador->amarillas_count + $jugador->rojas_count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section id="plantillas" class="public-section detail-module" role="tabpanel">
        <div class="section-title"><div><span>Información deportiva</span><h2>Plantillas públicas</h2></div></div>
        <p class="privacy-note"><i class="bi bi-shield-lock me-1"></i>Por privacidad solo se muestran foto deportiva, nombre, dorsal, posición y edad. Los documentos y datos de contacto permanecen protegidos.</p>
        <div id="roster-context" class="roster-context d-none">
            <span>Mostrando solamente: <strong id="roster-team-name"></strong></span>
            <button type="button" class="btn btn-sm btn-borde" id="show-all-rosters">Ver todas las plantillas</button>
        </div>
        @if($equipos->isEmpty())
            <div class="public-empty"><i class="bi bi-person-x"></i><p>No hay equipos aprobados para mostrar.</p></div>
        @else
            <div class="roster-columns">
                @foreach($equipos as $equipo)
                    @php($plantilla = $jugadores->where('equipo_id', $equipo->id))
                    <div class="roster-team" data-team="{{ $equipo->id }}" data-team-name="{{ $equipo->nombre }}">
                        <h3><i class="bi bi-shield-fill"></i>{{ $equipo->nombre }}</h3>
                        @forelse($plantilla as $jugador)
                            <div class="roster-player">
                                <img class="roster-player-photo" src="{{ $jugador->foto_url }}" alt="Foto de {{ $jugador->nombre }} {{ $jugador->apellido }}" loading="lazy">
                                <span class="roster-player-info">
                                    <strong>{{ $jugador->nombre }} {{ $jugador->apellido }}</strong>
                                    <small>{{ $jugador->posicion ?: 'Sin posición' }} · {{ $jugador->edad !== null ? $jugador->edad.' años' : 'Edad no registrada' }}</small>
                                </span>
                            </div>
                        @empty
                            <p class="roster-empty">Este equipo todavía no tiene jugadores aprobados.</p>
                        @endforelse
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>



<script>
document.addEventListener('DOMContentLoaded', () => {
    const navigation = document.querySelector('.detail-nav');
    const modules = [...document.querySelectorAll('.detail-module')];
    const menuLinks = [...navigation.querySelectorAll('[data-module]')];
    const validModules = new Set(modules.map(module => module.id));
    const rosterCards = [...document.querySelectorAll('.roster-team[data-team]')];
    const rosterColumns = document.querySelector('.roster-columns');
    const rosterContext = document.getElementById('roster-context');
    const rosterTeamName = document.getElementById('roster-team-name');
    const bracketFlow = document.querySelector('[data-bracket-flow]');
    const bracketSvg = bracketFlow?.querySelector('.stage-connectors');

    // Dibuja una llave clásica usando las posiciones reales de cada tarjeta.
    // Al cambiar el tamaño de la pantalla, las líneas se vuelven a calcular.
    const drawBracketConnectors = () => {
        if (!bracketFlow || !bracketSvg || !bracketFlow.offsetParent) return;

        const namespace = 'http://www.w3.org/2000/svg';
        const flowBox = bracketFlow.getBoundingClientRect();
        const width = bracketFlow.scrollWidth;
        const height = bracketFlow.offsetHeight;
        const columns = [...bracketFlow.querySelectorAll('.stage-column')];
        const champion = bracketFlow.querySelector('.stage-champion');

        bracketSvg.replaceChildren();
        bracketSvg.setAttribute('viewBox', `0 0 ${width} ${height}`);

        const definitions = document.createElementNS(namespace, 'defs');
        const marker = document.createElementNS(namespace, 'marker');
        marker.setAttribute('id', 'bracket-arrowhead');
        marker.setAttribute('markerWidth', '7');
        marker.setAttribute('markerHeight', '7');
        marker.setAttribute('refX', '6');
        marker.setAttribute('refY', '3.5');
        marker.setAttribute('orient', 'auto');
        const arrow = document.createElementNS(namespace, 'path');
        arrow.setAttribute('d', 'M0,0 L7,3.5 L0,7 Z');
        arrow.setAttribute('fill', '#ffd08b');
        marker.appendChild(arrow);
        definitions.appendChild(marker);
        bracketSvg.appendChild(definitions);

        const connect = (source, target) => {
            if (!source || !target) return;
            const sourceBox = source.getBoundingClientRect();
            const targetBox = target.getBoundingClientRect();
            const startX = sourceBox.right - flowBox.left;
            const startY = sourceBox.top - flowBox.top + (sourceBox.height / 2);
            const endX = targetBox.left - flowBox.left - 5;
            const endY = targetBox.top - flowBox.top + (targetBox.height / 2);
            const middleX = startX + ((endX - startX) / 2);
            const path = document.createElementNS(namespace, 'path');

            path.setAttribute('d', `M ${startX} ${startY} H ${middleX} V ${endY} H ${endX}`);
            path.setAttribute('fill', 'none');
            path.setAttribute('stroke', 'rgba(255, 208, 139, .72)');
            path.setAttribute('stroke-width', '2');
            path.setAttribute('stroke-linejoin', 'round');
            path.setAttribute('marker-end', 'url(#bracket-arrowhead)');
            bracketSvg.appendChild(path);
        };

        columns.forEach((column, columnIndex) => {
            const nextColumn = columns[columnIndex + 1];
            if (!nextColumn) return;
            const currentMatches = [...column.querySelectorAll('.tree-match')];
            const nextMatches = [...nextColumn.querySelectorAll('.tree-match')];

            currentMatches.forEach((match, matchIndex) => {
                const targetIndex = Math.min(nextMatches.length - 1, Math.floor(matchIndex * nextMatches.length / currentMatches.length));
                connect(match, nextMatches[targetIndex]);
            });
        });

        connect(columns.at(-1)?.querySelector('.tree-match'), champion);
    };

    const scheduleBracketDraw = () => requestAnimationFrame(() => requestAnimationFrame(drawBracketConnectors));

    const parseHash = () => {
        const hash = window.location.hash.slice(1);
        const teamMatch = hash.match(/^plantillas-equipo-(\d+)$/);
        return teamMatch ? { module: 'plantillas', team: teamMatch[1] } : { module: hash || 'resumen', team: null };
    };

    const filterRosters = teamId => {
        if (!rosterColumns) return;

        const selectedCard = teamId ? rosterCards.find(card => card.dataset.team === String(teamId)) : null;
        rosterCards.forEach(card => { card.hidden = Boolean(selectedCard) && card !== selectedCard; });
        rosterColumns.classList.toggle('is-filtered', Boolean(selectedCard));
        rosterContext?.classList.toggle('d-none', !selectedCard);
        if (selectedCard && rosterTeamName) rosterTeamName.textContent = selectedCard.dataset.teamName;
    };

    const showModule = (moduleId, updateUrl = true, moveToMenu = true, teamId = null) => {
        const selected = validModules.has(moduleId) ? moduleId : 'resumen';

        modules.forEach(module => module.classList.toggle('is-active', module.id === selected));
        menuLinks.forEach(link => {
            const active = link.dataset.module === selected;
            link.classList.toggle('active', active);
            link.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        filterRosters(selected === 'plantillas' ? teamId : null);
        if (selected === 'llaves') scheduleBracketDraw();
        const hash = selected === 'plantillas' && teamId ? `plantillas-equipo-${teamId}` : selected;
        if (updateUrl) history.replaceState(null, '', `#${hash}`);
        if (moveToMenu) navigation.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    document.querySelectorAll('[data-module]').forEach(link => {
        link.addEventListener('click', event => {
            event.preventDefault();
            showModule(link.dataset.module, true, true, link.dataset.team || null);
        });
    });

    document.getElementById('show-all-rosters')?.addEventListener('click', () => showModule('plantillas', true, false));

    const initial = parseHash();
    showModule(initial.module, false, Boolean(window.location.hash), initial.team);
    window.addEventListener('hashchange', () => {
        const selected = parseHash();
        showModule(selected.module, false, true, selected.team);
    });
    window.addEventListener('resize', scheduleBracketDraw);
});
</script>
@endsection
