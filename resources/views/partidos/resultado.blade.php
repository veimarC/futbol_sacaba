{{--
    Vista para registrar resultado de partido.
    El organizador carga goles y marca el partido como jugado.
--}}
@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="contenedor-transparente animar-aparecer">
            <h2 class="fw-bold mb-4"><i class="bi bi-clipboard-check me-2 text-accent"></i>Cargar Resultado</h2>

            <div class="text-center mb-4">
                <h5>{{ $partido->equipoLocal->nombre }} vs {{ $partido->equipoVisita->nombre }}</h5>
                <p class="opacity-75">{{ \Carbon\Carbon::parse($partido->fecha)->format('d/m/Y') }} - {{ $partido->hora }} | {{ $partido->cancha }}</p>
            </div>

            @if($partido->nominas->count() !== 2)
                <div class="alert alert-warning">
                    La Mesa de control todavía no aprobó las dos nóminas.
                    <a href="{{ route('partidos.nominas.index', $partido) }}" class="alert-link">Revisar nóminas</a>
                </div>
            @else
                <section class="match-events-box mb-4">
                    <h5 class="fw-semibold">Registrar gol o tarjeta</h5>
                    <form action="{{ route('incidencias.store') }}" method="POST" class="row g-2">
                        @csrf
                        <input type="hidden" name="partido_id" value="{{ $partido->id }}">
                        <div class="col-12 col-md-8">
                            <select name="jugador_id" class="form-select" required>
                                <option value="">Jugador de la nómina...</option>
                                @foreach($jugadoresPorEquipo as $grupo)
                                    <optgroup label="{{ $grupo['equipo']->nombre }} · {{ $grupo['condicion'] }}">
                                    @foreach($grupo['jugadores'] as $registro)
                                        <option value="{{ $registro->jugador_id }}">#{{ $registro->dorsal }} · {{ $registro->jugador->nombre }} {{ $registro->jugador->apellido }} · CI: {{ $registro->jugador->dni ?: 'Sin registrar' }}</option>
                                    @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-4"><select name="tipo" class="form-select" required><option value="gol">Gol</option><option value="amarilla">Amarilla</option><option value="roja">Roja</option></select></div>
                        <div class="col-12"><button class="btn btn-success btn-sm">Agregar incidencia</button></div>
                    </form>
                </section>
            @endif

            @if($partido->incidencias->isNotEmpty())
                <section class="match-events-box mb-4">
                    <h5 class="fw-semibold mb-3">Incidencias registradas</h5>
                    <div class="d-grid gap-2">
                        @foreach($partido->incidencias->sortByDesc('id') as $incidencia)
                            @php
                                $etiquetaIncidencia = match ($incidencia->tipo) {
                                    'doble_amarilla' => 'Doble amarilla',
                                    default => ucfirst($incidencia->tipo),
                                };
                                $claseIncidencia = match ($incidencia->tipo) {
                                    'gol' => 'bg-success',
                                    'roja', 'doble_amarilla' => 'bg-danger',
                                    default => 'bg-warning text-dark',
                                };
                            @endphp
                            <div class="d-flex align-items-center gap-2 p-2 rounded border border-light border-opacity-25">
                                <span class="badge {{ $claseIncidencia }}">
                                    {{ $etiquetaIncidencia }}
                                </span>
                                <span class="flex-grow-1">
                                    #{{ $incidencia->dorsal }} · {{ $incidencia->jugador->nombre }} {{ $incidencia->jugador->apellido }}
                                    <small class="d-block opacity-75">{{ $incidencia->jugador->equipo?->nombre }}</small>
                                </span>
                                <form method="POST" action="{{ route('incidencias.destroy', $incidencia) }}" data-confirm="¿Eliminar esta incidencia?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm" aria-label="Eliminar incidencia"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <form method="POST" action="{{ route('partidos.guardarResultado', $partido) }}">
                @csrf @method('PUT')
                <p class="text-center opacity-75 mb-2">Marcador automático</p>

                <div class="row g-3 mb-4">
                    <div class="col-md-5">
                        <div class="text-center p-3 rounded border border-light border-opacity-25">
                            <span class="d-block">{{ $partido->equipoLocal->nombre }}</span>
                            <strong class="fs-1">{{ $partido->goles_local ?? 0 }}</strong>
                        </div>
                    </div>
                    <div class="col-md-2 text-center align-self-center">
                        <span class="fw-bold fs-3">-</span>
                    </div>
                    <div class="col-md-5">
                        <div class="text-center p-3 rounded border border-light border-opacity-25">
                            <span class="d-block">{{ $partido->equipoVisita->nombre }}</span>
                            <strong class="fs-1">{{ $partido->goles_visita ?? 0 }}</strong>
                        </div>
                    </div>
                </div>

                @if($desempate['requiere_penales'])
                    <fieldset class="form-panel rounded p-3 mb-4">
                        <legend class="fs-5">Desempate por penales</legend>
                        <p>{{ $desempate['vuelta'] ? 'El marcador global' : 'El partido' }} esta empatado {{ $desempate['local'] }}?{{ $desempate['visita'] }}. Registra la tanda completa con un ganador.</p>
                        <div class="row g-3">
                            <div class="col-6"><label for="penales_local" class="form-label">{{ $partido->equipoLocal->nombre }}</label><input id="penales_local" name="penales_local" type="number" min="0" max="999" required class="form-control" value="{{ old('penales_local') }}"></div>
                            <div class="col-6"><label for="penales_visita" class="form-label">{{ $partido->equipoVisita->nombre }}</label><input id="penales_visita" name="penales_visita" type="number" min="0" max="999" required class="form-control" value="{{ old('penales_visita') }}"></div>
                        </div>
                        <small>Los penales de la tanda no suman al marcador ni a los goleadores.</small>
                    </fieldset>
                @elseif($desempate['doble'])
                    <p class="alert alert-info">{{ $desempate['vuelta'] ? 'Vuelta: primero debe estar registrado el resultado de ida. Los penales solo se usan si el global termina empatado.' : 'Partido de ida: el desempate se resuelve al finalizar la vuelta.' }}</p>
                @endif

                <div class="d-flex gap-3">
                    <button type="submit" class="btn btn-naranja" @disabled($partido->nominas->count() !== 2)>Confirmar resultado</button>
                    <a href="{{ route('partidos.nominas.index', $partido) }}" class="btn btn-borde">Volver a Mesa</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
