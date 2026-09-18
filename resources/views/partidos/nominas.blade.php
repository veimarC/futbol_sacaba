@extends('layouts.app')

@section('content')
<div class="contenedor-transparente animar-aparecer">
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
        <div>
            <span class="page-kicker">Mesa de control</span>
            <h2 class="fw-bold mb-1">Nóminas del partido</h2>
            <p class="opacity-75 mb-0">{{ $partido->equipoLocal->nombre }} vs {{ $partido->equipoVisita->nombre }}</p>
        </div>
        <a href="{{ route('campeonatos.show', $partido->campeonato) }}" class="btn btn-borde align-self-md-start">← Volver al campeonato</a>
    </div>

    @if(session('mensaje'))<div class="alert alert-success">{{ session('mensaje') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="match-roster-grid">
        @foreach($equipos as $equipo)
            @php
                $nomina = $partido->nominas->firstWhere('equipo_id', $equipo->id);
                $esDelegado = auth()->user()->rol === \App\Models\User::ROLE_DELEGADO && (int) $equipo->delegado_id === (int) auth()->id();
                $esMesa = auth()->user()->esDesarrollador() || (auth()->user()->rol === \App\Models\User::ROLE_ORGANIZADOR && (int) $partido->campeonato->usuario_creador === (int) auth()->id());
                $claseEstado = match($nomina?->estado) { 'aprobada' => 'bg-success', 'rechazada' => 'bg-danger', default => 'bg-warning text-dark' };
            @endphp
            <section class="match-roster-card">
                <header>
                    <x-team-crest :equipo="$equipo" />
                    <div><h3>{{ $equipo->nombre }}</h3><small>{{ $equipo->delegado?->name ?? 'Sin delegado' }}</small></div>
                    <span class="badge {{ $claseEstado }}">{{ $nomina ? ucfirst($nomina->estado) : 'Sin enviar' }}</span>
                </header>

                @if($nomina)
                    <div class="roster-player-list">
                        @foreach($nomina->jugadores as $registro)
                            <div>
                                <b>{{ $registro->dorsal }}</b>
                                <span>{{ $registro->jugador->nombre }} {{ $registro->jugador->apellido }} <small class="opacity-75">· CI: {{ $registro->jugador->dni ?: 'Sin registrar' }}</small></span>
                            </div>
                        @endforeach
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
                    @if($esMesa && $nomina?->estado === 'pendiente')
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
                <a href="{{ route('partidos.resultado', $partido) }}" class="btn btn-success">
                    <i class="bi bi-check2-square me-1"></i>Registrar resultado
                </a>
            @else
                <button class="btn btn-secondary" disabled><i class="bi bi-lock me-1"></i>Registrar resultado</button>
            @endif
        </section>
    @endif
</div>
@endsection
