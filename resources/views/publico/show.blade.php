@extends('layouts.app')

@section('content')
<div class="public-detail">
    <header class="detail-hero mb-3">
        @if($portadaUrl = $campeonato->portada_url)
            <img class="detail-icon" src="{{ $portadaUrl }}" alt="Portada de {{ $campeonato->nombre }}" style="object-fit: cover">
        @endif
        <div>
            <span class="status status-{{ $campeonato->estado_actual }}">{{ $campeonato->estado_actual_label }}</span>
            <h1>{{ $campeonato->nombre }}</h1>
            @if($campeonato->descripcion)<p>{{ $campeonato->descripcion }}</p>@endif
            <div class="detail-dates">
                <span>Inicio: {{ $campeonato->fecha_inicio?->format('d/m/Y') }}</span>
                @if($campeonato->fecha_fin)<span>Fin: {{ $campeonato->fecha_fin->format('d/m/Y') }}</span>@endif
            </div>
            @if($campeonato->organizador)<p>Organiza: {{ $campeonato->organizador->name }}</p>@endif
        </div>
    </header>
    <section id="resumen" class="detail-module" role="tabpanel">
@can('downloadRules', $campeonato)
    <a class="btn btn-naranja mb-3" href="{{ route('campeonatos.reglamento.pdf', $campeonato) }}"><i class="bi bi-file-earmark-pdf me-1"></i>Descargar reglamento PDF</a>
@endcan
    <div class="content-heading"><span>Normas de participación</span><h2>Reglamento del campeonato</h2></div>
    <section class="public-rules">
        <div><i class="bi bi-dribbble"></i><small>Modalidad</small><strong>Fútbol {{ $campeonato->modalidad ?: 'libre' }}</strong></div><div><i class="bi bi-calendar3"></i><small>Categoría</small><strong>{{ $campeonato->categoria_edad_label ?: 'Libre' }}</strong></div><div><i class="bi bi-gender-ambiguous"></i><small>Participación</small><strong>{{ $campeonato->sexo_label ?: 'Mixto' }}</strong></div>
    </section>
    <article class="internal-rules" aria-labelledby="internal-rules-heading">
        <h3 id="internal-rules-heading"><i class="bi bi-journal-text" aria-hidden="true"></i> Reglamentos internos</h3>
        @if(filled($campeonato->reglamento_interno))
            <div class="internal-rules-text">{{ $campeonato->reglamento_interno }}</div>
        @else
            <p class="mb-0">El organizador todavía no publicó reglamentos internos.</p>
        @endif
    </article>
    </section>

    <section id="partidos" class="public-section detail-module is-active" role="tabpanel">
        <div class="section-title"><div><span>Calendario oficial</span><h2>Partidos y resultados</h2></div><strong>{{ $partidos->count() }} partidos</strong></div>
        <p class="small mb-3">Todos los partidos por jornada: resultados y encuentros pendientes.</p>
        @forelse($jornadas as $jornada)
            <section class="matchday">
                <header class="matchday-heading">
                    <strong>{{ $jornada['nombre'] }}</strong>
                    <span>{{ $jornada['jugados'] }} de {{ $jornada['partidos']->count() }} jugados · {{ $jornada['pendientes'] }} pendientes</span>
                </header>
                <div class="matchday-games">
                @foreach($jornada['partidos'] as $partido)
            <article class="match-row">
                <div class="match-date">
                    <span class="match-status {{ $partido->estado === 'jugado' ? 'is-played' : '' }}">{{ $partido->estado === 'jugado' ? ($partido->wo_motivo ? 'W.O.' : 'Jugado') : ($partido->fecha ? 'Programado' : 'Fecha por definir') }}</span>
                    <strong>{{ $partido->fecha?->format('d/m/Y') ?: '—' }}</strong>
                    <span>{{ $partido->hora ? \Carbon\Carbon::parse($partido->hora)->format('H:i') : 'Hora por definir' }}</span>
                    @if($partido->fase?->tipo === 'grupo')<span>{{ $partido->fase->nombre ?: 'Grupo '.data_get($partido->fase->metadata, 'grupo') }}</span>@endif
                </div>
                <div class="match-teams">
                    <span class="match-club is-local"><span>{{ $partido->equipoLocal?->nombre ?: 'Por definir' }}</span><x-team-crest :equipo="$partido->equipoLocal" /></span>
                    <div class="score">@if($partido->estado === 'jugado')<strong>{{ $partido->goles_local }}</strong><small>-</small><strong>{{ $partido->goles_visita }}</strong>@else<small>VS</small>@endif</div>
                    @if($partido->penales_local !== null)<small class="d-block">Penales {{ $partido->penales_local }}?{{ $partido->penales_visita }}</small>@endif
                    <span class="match-club"><x-team-crest :equipo="$partido->equipoVisita" /><span>{{ $partido->equipoVisita?->nombre ?: 'Por definir' }}</span></span>
                </div>
                <div class="match-place"><span><i class="bi bi-geo-alt"></i>{{ $partido->cancha ?: 'Cancha por definir' }}</span></div>
            </article>
                @endforeach
                </div>
            </section>
        @empty<div class="public-empty"><i class="bi bi-calendar2-x"></i><p>Todavía no existen partidos programados.</p></div>@endforelse
    </section>

    @if($mostrarLlaves)
        <section id="llaves" class="public-section detail-module" role="tabpanel">
            @include('publico.partials.bracket')
        </section>
    @endif

    @if($campeonato->tieneTablaPosiciones())
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

    @endif

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
                            <div class="scorer-name d-flex align-items-center gap-2">
                                <img src="{{ $jugador->foto_url }}" alt="Foto de {{ $jugador->nombre }} {{ $jugador->apellido }}" class="photo-xs flex-shrink-0" width="48" height="48" loading="lazy">
                                <strong>{{ $jugador->nombre }} {{ $jugador->apellido }}</strong>
                            </div>
                            <span class="scorer-club"><x-team-crest :equipo="$jugador->equipo" /><span>{{ $jugador->equipo?->nombre }}</span></span>
                            <b>{{ $jugador->goles_count }} {{ $jugador->goles_count === 1 ? 'gol' : 'goles' }}</b>
                        </div>
                        <details class="goal-history">
                            <header class="matchday-heading"><i class="bi bi-clock-history"></i> Ver historial de goles</summary>
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
        <p class="privacy-note"><i class="bi bi-shield-lock me-1"></i>Por privacidad solo se muestran foto deportiva, nombre, dorsal y edad. Los documentos y datos de contacto permanecen protegidos.</p>


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
                                    <small>{{ $jugador->edad !== null ? $jugador->edad.' años' : 'Edad no registrada' }}</small>
                                </span>
                            </div>
                        @empty
                            <p class="roster-empty">Este equipo todavÃ­a no tiene jugadores aprobados.</p>
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
    const parseHash = () => {
        const hash = window.location.hash.slice(1);
        const teamMatch = hash.match(/^plantillas-equipo-(\d+)$/);
        return teamMatch ? { module: 'plantillas', team: teamMatch[1] } : { module: hash || 'partidos', team: null };
    };

    const filterRosters = teamId => {
        if (!rosterColumns) return;

        const selectedCard = teamId ? rosterCards.find(card => card.dataset.team === String(teamId)) : null;
        rosterCards.forEach(card => { card.hidden = ! selectedCard || card !== selectedCard; });
        rosterColumns.classList.toggle('is-filtered', Boolean(selectedCard));
    };

    const showModule = (moduleId, updateUrl = true, moveToMenu = true, teamId = null) => {
        const selected = validModules.has(moduleId) ? moduleId : 'partidos';

        modules.forEach(module => module.classList.toggle('is-active', module.id === selected));
        menuLinks.forEach(link => {
            const active = link.dataset.module === selected;
            link.classList.toggle('active', active);
            link.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        filterRosters(selected === 'plantillas' ? teamId : null);

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

    const initial = parseHash();
    showModule(initial.module, false, Boolean(window.location.hash), initial.team);
    window.addEventListener('hashchange', () => {
        const selected = parseHash();
        showModule(selected.module, false, true, selected.team);
    });

});
</script>
@endsection


