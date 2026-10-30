@extends('layouts.app')

@section('content')
<div class="contenedor-transparente match-sheet" data-match-sheet>
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
        <div>
            <span class="page-kicker">Mesa de control</span>
            <h2 class="fw-bold mb-1">Planilla del partido</h2>
            <p class="opacity-75 mb-0">{{ $partido->equipoLocal->nombre }} vs {{ $partido->equipoVisita->nombre }}</p>
        </div>
        <a href="{{ route('campeonatos.show', ['campeonato' => $partido->campeonato, 'seccion' => 'partidos']) }}" class="btn btn-borde align-self-md-start">← Volver al campeonato</a>
    </div>

    @if(session('mensaje'))<div class="alert alert-success">{{ session('mensaje') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

@can('downloadRules', $partido->campeonato)
    <div class="mb-3"><a class="btn btn-naranja" href="{{ route('partidos.planilla.pdf', $partido) }}"><i class="bi bi-file-earmark-pdf me-1"></i>Descargar planilla PDF</a></div>
@endcan
    <section class="sheet-score" aria-label="Marcador del partido">
        <div><small>{{ $partido->campeonato->nombre }} · Partido #{{ $partido->numero_campeonato }} @if($partido->fase?->nombre) · {{ $partido->fase->nombre }} @endif</small>
        <p>{{ $partido->fecha?->format('d/m/Y') ?: 'Fecha por definir' }} · {{ $partido->hora ?: 'Hora por definir' }} · {{ $partido->cancha ?: 'Cancha por definir' }}</p></div>
        <div class="sheet-scoreline">
            <div class="sheet-score-team"><x-team-crest :equipo="$partido->equipoLocal" /><strong>{{ $partido->equipoLocal->nombre }}</strong></div>
            <b>{{ $partido->goles_local ?? 0 }} - {{ $partido->goles_visita ?? 0 }}</b>
            <div class="sheet-score-team"><x-team-crest :equipo="$partido->equipoVisita" /><strong>{{ $partido->equipoVisita->nombre }}</strong></div>
        </div>
        <p class="mb-0">{{ $partido->estado === 'jugado' ? 'Finalizado · Solo consulta' : ($nominasAprobadas === 2 ? 'Nóminas aprobadas · Puedes registrar incidencias' : 'Preparación · Aprueba las dos nóminas para registrar incidencias') }}</p>
        @if($partido->penales_local !== null)<p>Penales: {{ $partido->penales_local }} - {{ $partido->penales_visita }}</p>@endif
    </section>
    <div class="sheet-team-tabs" role="group" aria-label="Seleccionar equipo">
        @foreach($equipos as $equipo)<button type="button" class="btn btn-light" data-sheet-team="{{ $equipo->id }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ $equipo->nombre }}</button>@endforeach
    </div>
    <p data-sheet-status role="status" aria-live="polite"></p>
    <div class="match-roster-grid">
        @foreach($equipos as $equipo)
            @php
                $nomina = $partido->nominas->firstWhere('equipo_id', $equipo->id);
                $esDelegado = auth()->user()->rol === \App\Models\User::ROLE_DELEGADO && (int) $equipo->delegado_id === (int) auth()->id();
                $esMesa = auth()->user()->esDesarrollador() || (auth()->user()->rol === \App\Models\User::ROLE_ORGANIZADOR && (int) $partido->campeonato->usuario_creador === (int) auth()->id());
                $claseEstado = match($nomina?->estado) { 'aprobada' => 'bg-success', 'rechazada' => 'bg-danger', default => 'bg-warning text-dark' };
            @endphp
            <section class="match-roster-card sheet-team" data-sheet-panel="{{ $equipo->id }}">
                <header>
                    <x-team-crest :equipo="$equipo" />
                    <div><h3>{{ $equipo->nombre }}</h3><small>{{ $equipo->delegado?->name ?? 'Sin delegado' }}</small></div>
                    <span class="badge {{ $claseEstado }}">{{ $nomina ? ucfirst($nomina->estado) : 'Sin enviar' }}</span>
                </header>

                @if($nomina)
                    <p class="small">{{ $nomina->jugadores->count() }} convocados · Mínimo para jugar: {{ $partido->campeonato->minimo_para_jugar }}</p>
                    <div class="table-responsive">
                        <table class="table sheet-table align-middle">
                            <thead><tr><th scope="col">N°</th><th scope="col">Jugador</th><th scope="col">Goles</th><th scope="col"><abbr title="Amarillas">TA</abbr></th><th scope="col"><abbr title="Rojas">TR</abbr></th></tr></thead>
                            <tbody>
                            @foreach($nomina->jugadores as $registro)
                                @php
                                    $eventos = $partido->incidencias->where('jugador_id', $registro->jugador_id);
                                    $expulsado = $eventos->whereIn('tipo', ['roja', 'doble_amarilla'])->isNotEmpty();
                                @endphp
                                <tr>
                                    <th scope="row">{{ $registro->dorsal }}</th>
                                    <td><div class="sheet-player-identity">
                                        <img class="sheet-player-photo" src="{{ $registro->jugador->foto_url }}" alt="Foto de {{ $registro->jugador->nombre }} {{ $registro->jugador->apellido }}" width="44" height="44" loading="lazy">
                                        <strong>{{ $registro->jugador->nombre }} {{ $registro->jugador->apellido }}</strong>
                                    </div>
                                        <details><summary class="small">Ver CI</summary><small>{{ $registro->jugador->dni ?: 'Sin registrar' }}</small></details>
                                        @if($expulsado)<span class="badge bg-danger">Expulsado</span>@endif
                                        @if($esMesa && $partido->estado === 'programado' && $nominasAprobadas === 2)
                                            <details class="sheet-actions"><summary>Registrar</summary>
                                                <form action="{{ route('incidencias.store') }}" method="POST" data-sheet-event>
                                                    @csrf
                                                    <input type="hidden" name="partido_id" value="{{ $partido->id }}">
                                                    <input type="hidden" name="jugador_id" value="{{ $registro->jugador_id }}">
                                                    <span class="small d-block">#{{ $registro->dorsal }} · {{ $registro->jugador->nombre }}</span>
                                                    <button name="tipo" value="gol" class="btn btn-success btn-sm">+ Gol</button>
                                                    <button name="tipo" value="amarilla" class="btn btn-warning btn-sm" @disabled($expulsado)>Amarilla</button>
                                                    <button name="tipo" value="roja" class="btn btn-danger btn-sm" @disabled($expulsado)>Roja</button>
                                                </form>
                                            </details>
                                        @endif
                                    </td>
                                    <td>{{ $eventos->where('tipo', 'gol')->count() }}</td>
                                    <td>{{ $eventos->whereIn('tipo', ['amarilla', 'doble_amarilla'])->count() }}</td>
                                    <td>{{ $eventos->whereIn('tipo', ['roja', 'doble_amarilla'])->count() }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($nomina->motivo_respuesta)<p class="small text-warning mt-3 mb-0"><strong>Observación:</strong> {{ $nomina->motivo_respuesta }}</p>@endif
                @else
                    <div class="sports-empty roster-empty"><i class="bi bi-clipboard"></i><span>El delegado todavía no envió la nómina.</span></div>
                @endif

                <footer>
                    @if(($esDelegado || $esMesa) && $partido->estado === 'programado' && ($esMesa || $nomina?->estado !== 'aprobada'))
                        <a href="{{ route('partidos.nominas.editar', [$partido, $equipo]) }}" class="btn btn-naranja w-100">
                            {{ $esMesa ? ($nomina ? 'Editar nómina' : 'Llenar nómina') : ($nomina ? 'Corregir nómina' : 'Elegir jugadores') }}
                        </a>
                    @endif
                    @if($esMesa && $partido->estado === 'programado' && $nomina?->estado === 'pendiente')
                        <form method="POST" action="{{ route('partidos.nominas.gestionar', [$partido, $nomina]) }}" class="roster-review-actions">
                            @csrf
                            <input name="motivo_respuesta" class="form-control" maxlength="500" placeholder="Observación si necesita corrección">
                            <button name="estado" value="aprobada" class="btn btn-success">Aprobar</button>
                            <button name="estado" value="rechazada" class="btn btn-danger">Devolver</button>
                        </form>
                    @endif
                </footer>
            </section>
        @endforeach
    </div>

    <section class="sheet-history mt-4">
        <h3 class="h5">Registro del partido</h3>
        @forelse($partido->incidencias->sortByDesc('id') as $evento)
            <div class="sheet-history-row">
                <span><strong>{{ match($evento->tipo) { 'doble_amarilla' => 'Doble amarilla', default => ucfirst($evento->tipo) } }}</strong> · #{{ $evento->dorsal }} {{ $evento->jugador?->nombre }} {{ $evento->jugador?->apellido }}</span>
                @if($puedeRegistrarResultado && $partido->estado === 'programado')
                    <form action="{{ route('incidencias.destroy', $evento) }}" method="POST" data-sheet-event data-sheet-confirm="¿Eliminar esta incidencia del partido?">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm">Eliminar</button></form>
                @endif
            </div>
        @empty<p class="mb-0">Todavía no se registraron goles ni tarjetas.</p>@endforelse
    </section>
    @if($partido->wo_motivo)
        <div class="alert alert-warning mt-4">
            <strong>Resultado por W.O.: {{ $partido->goles_local }} - {{ $partido->goles_visita }}</strong>
            <p class="mb-0">
                {{ (int) $partido->wo_equipo_perdedor_id === (int) $partido->equipo_local_id ? $partido->equipoLocal->nombre : $partido->equipoVisita->nombre }}:
                {{ $partido->wo_motivo === 'inasistencia' ? 'No se presentó.' : 'No alcanzó el mínimo de jugadores habilitados.' }}
            </p>
        </div>
    @endif
    @if($puedeRegistrarResultado && $partido->estado === 'programado')
        <details class="match-roster-card mt-4">
            <summary class="fw-bold">Registrar W.O.</summary>
            <p class="mt-3">Si un equipo no se presenta o no alcanza el mínimo de {{ $partido->campeonato->minimo_para_jugar }} jugadores habilitados, puedes registrar su derrota por W.O.</p>
            <p>El rival ganará 3–0. No se requieren las dos nóminas aprobadas. Confirma que el rival se presentó y está habilitado para jugar.</p>
            <form method="POST" action="{{ route('partidos.wo', $partido) }}" data-sheet-confirm="¿Confirmas la derrota por W.O. del equipo seleccionado? El rival ganará 3–0 y el partido quedará finalizado.">
                @csrf
                <div class="mb-3">
                    <label for="wo-equipo" class="form-label">Equipo que pierde por W.O.</label>
                    <select id="wo-equipo" name="equipo_perdedor_id" class="form-select" required>
                        <option value="">Selecciona el equipo</option>
                        @foreach($equipos as $equipo)
                            <option value="{{ $equipo->id }}" @selected((string) old('equipo_perdedor_id') === (string) $equipo->id)>{{ $equipo->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label for="wo-motivo" class="form-label">Motivo</label>
                    <select id="wo-motivo" name="motivo" class="form-select" required>
                        <option value="">Selecciona el motivo</option>
                        <option value="inasistencia" @selected(old('motivo') === 'inasistencia')>No se presentó</option>
                        <option value="jugadores_insuficientes" @selected(old('motivo') === 'jugadores_insuficientes')>No alcanza el mínimo de jugadores</option>
                    </select>
                </div>
                <button class="btn btn-danger" type="submit">Confirmar W.O.</button>
            </form>
        </details>
    @endif
    @if($puedeRegistrarResultado)
        <section class="control-result mt-4">
            <div>
                <span class="page-kicker">Paso final</span>
                <h3 class="h5 fw-bold mb-1">Resultado del partido</h3>
                @if($partido->estado === 'jugado')
                    <p class="mb-0 opacity-75">El resultado ya fue registrado.</p>
                @elseif($nominasAprobadas === 2)
                    <p class="mb-0 opacity-75">Las dos nóminas están aprobadas. Ya puedes cargar el resultado.</p>
                @else
                    <p class="mb-0 opacity-75">Primero deben aprobarse las dos nóminas ({{ $nominasAprobadas }}/2).</p>
                @endif
            </div>

            @if($partido->estado === 'jugado')
                <strong class="control-result-score">{{ $partido->goles_local }} - {{ $partido->goles_visita }}</strong>
            @elseif($nominasAprobadas === 2)
                <details class="sheet-close">
                    <summary class="btn btn-success">Revisar y finalizar</summary>
                    <form method="POST" action="{{ route('partidos.guardarResultado', $partido) }}" data-sheet-confirm="¿Finalizar el partido con este resultado? Las incidencias quedarán bloqueadas.">
                        @csrf @method('PUT')
                        <h4 class="h5 mt-3">{{ $partido->equipoLocal->nombre }} {{ $partido->goles_local ?? 0 }} - {{ $partido->goles_visita ?? 0 }} {{ $partido->equipoVisita->nombre }}</h4>
                        <p>Revisa las incidencias antes de confirmar. Los penales no suman a los goleadores.</p>
                @if($desempate['requiere_penales'])
                    <fieldset class="form-panel rounded p-3 mb-4">
                        <legend class="fs-5">Desempate por penales</legend>
                        <p>{{ $desempate['vuelta'] ? 'El marcador global' : 'El partido' }} está empatado {{ $desempate['local'] }} - {{ $desempate['visita'] }}. Registra la tanda completa con un ganador.</p>
                        <div class="row g-3">
                            <div class="col-6"><label for="penales_local" class="form-label">{{ $partido->equipoLocal->nombre }}</label><input id="penales_local" name="penales_local" type="number" min="0" max="999" required class="form-control" value="{{ old('penales_local') }}"></div>
                            <div class="col-6"><label for="penales_visita" class="form-label">{{ $partido->equipoVisita->nombre }}</label><input id="penales_visita" name="penales_visita" type="number" min="0" max="999" required class="form-control" value="{{ old('penales_visita') }}"></div>
                        </div>
                        <small>Los penales de la tanda no suman al marcador ni a los goleadores.</small>
                    </fieldset>
                @elseif($desempate['doble'])
                    <p class="alert alert-info">{{ $desempate['vuelta'] ? 'Vuelta: primero debe estar registrado el resultado de ida. Los penales solo se usan si el global termina empatado.' : 'Partido de ida: el desempate se resuelve al finalizar la vuelta.' }}</p>
                @endif

                        <button class="btn btn-naranja">Confirmar resultado</button>
                    </form>
                </details>
            @else
                <button class="btn btn-secondary" disabled><i class="bi bi-lock me-1"></i>Registrar resultado</button>
            @endif
        </section>
    @endif
</div>
@endsection
