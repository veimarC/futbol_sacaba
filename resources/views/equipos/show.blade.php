{{--
    Vista de ficha del equipo.
    Muestra la lista de jugadores, historial de goles y tarjetas, y acciones disponibles.
--}}
@extends('layouts.app')

@section('content')
<div class="contenedor-transparente animar-aparecer">
    <div class="row align-items-center">
        <div class="col-md-8">
            <a href="{{ $campeonato ? route('campeonatos.show', $campeonato) : route('home') }}" class="btn btn-borde btn-sm mb-3">← Volver</a>
            <h1 class="fw-bold mb-2">{{ $equipo->nombre }}</h1>
            <p class="opacity-75 mb-1">Campeonatos: <strong>{{ $equipo->campeonatos->pluck('nombre')->join(', ') ?: 'Sin inscripción' }}</strong></p>
            <p class="opacity-75 mb-0">Delegado: <strong>{{ $equipo->delegado->name ?? '-' }}</strong> @if($equipo->ciudad)| {{ $equipo->ciudad }} @endif</p>
            @if($campeonatos->count() > 1 && ! $campeonato)
                <div class="alert alert-info mt-3 mb-0">
                    Este equipo participa en varios campeonatos. Selecciona uno para consultar su contexto:
                    @foreach($campeonatos as $campeonatoDisponible)
                        <a class="alert-link ms-2" href="{{ route('equipos.show', [$equipo, 'campeonato' => $campeonatoDisponible->id]) }}">{{ $campeonatoDisponible->nombre }}</a>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            @can('update', $equipo)
                <a href="{{ route('equipos.edit', array_filter([$equipo, 'campeonato' => $campeonato?->id], fn ($valor) => $valor !== null)) }}" class="btn btn-warning text-dark me-2">
                    <i class="bi bi-pencil-square"></i> Editar
                </a>
            @endcan
            <a href="{{ $campeonato ? route('campeonatos.show', $campeonato) : route('home') }}" class="btn btn-light text-dark">X</a>
        </div>
    </div>

    <div class="mt-4">
        <div class="d-flex flex-wrap gap-2 mb-4">
            <button class="btn module-tab active px-4 py-2 fw-semibold shadow-sm" type="button" data-bs-toggle="tab" data-bs-target="#jugadores">
                <i class="bi bi-people-fill me-2"></i>Jugadores
            </button>
            <button class="btn module-tab px-4 py-2 fw-semibold shadow-sm" type="button" data-bs-toggle="tab" data-bs-target="#historial">
                <i class="bi bi-bar-chart-line me-2"></i>Historiales
            </button>
            <a href="{{ $campeonato ? route('campeonatos.show', $campeonato) : route('home') }}" class="btn module-tab px-4 py-2 fw-semibold shadow-sm">
                <i class="bi bi-trophy me-2"></i>Volver al torneo
            </a>
        </div>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="jugadores">
                <div class="d-flex justify-content-between flex-column flex-md-row align-items-md-center mb-4 gap-3">
                    <div>
                        <h4 class="fw-semibold">Jugadores registrados</h4>
                        <p class="opacity-75 mb-0">Lista de jugadores inscritos en este equipo.</p>
                    </div>
                    @if(auth()->check() && (auth()->user()->rol === \App\Models\User::ROLE_DESARROLLADOR || (auth()->user()->rol === \App\Models\User::ROLE_DELEGADO && $equipo->delegado_id == auth()->id())))
                        <a href="{{ route('equipos.jugadores.create', array_filter([$equipo, 'campeonato' => $campeonato?->id], fn ($valor) => $valor !== null)) }}" class="btn btn-naranja">Registrar Jugador</a>
                    @endif
                </div>

                <div class="table-responsive rounded-4 overflow-hidden">
                    <table class="table table-dark table-striped mb-0">
                        <thead class="bg-white bg-opacity-10">
                            <tr>
                                <th>Foto</th>
                                <th>Nombre</th>
                                <th>CI</th>
                                <th>Edad</th>
                                
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($equipo->jugadores as $jugador)
                                <tr>
                                    <td><img src="{{ $jugador->foto_url }}" alt="Foto de {{ $jugador->nombre }}" class="photo-xs"></td>
                                    <td>{{ $jugador->nombre }} {{ $jugador->apellido }}</td>
                                    <td>{{ $jugador->dni ?: '—' }}</td>
                                    <td>{{ $jugador->edad !== null ? $jugador->edad.' años' : '—' }}</td>
                                    <td><span class="badge bg-secondary text-capitalize">{{ $jugador->estado }}</span></td>
                                    <td>
                                        @if(auth()->user()->esDesarrollador() || (auth()->user()->rol === \App\Models\User::ROLE_DELEGADO && (int) $equipo->delegado_id === (int) auth()->id()))
                                            <div class="d-flex flex-wrap gap-2">
                                                <a href="{{ route('jugadores.edit', $jugador) }}" class="btn btn-sm btn-borde">Editar</a>
                                                <form action="{{ route('jugadores.destroy', $jugador) }}" method="POST" onsubmit="return confirm('¿Eliminar a {{ addslashes($jugador->nombre) }} de la plantilla? Esta acción no se puede deshacer.')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash me-1"></i>Eliminar</button>
                                                </form>
                                            </div>
                                        @endif
                                        @hasanyrol(['organizador'])
                                            @if($organizadorGestionaEquipo)
                                                <div class="d-flex flex-wrap gap-2">
                                                    <a href="{{ route('jugadores.show', $jugador) }}" class="btn btn-sm btn-borde">Ver</a>
                                                    @if($jugador->estado === \App\Models\Jugador::ESTADO_PENDIENTE)
                                                        <form action="{{ route('jugadores.aprobar', $jugador) }}" method="POST">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-success">Aprobar</button>
                                                        </form>
                                                        <form action="{{ route('jugadores.rechazar', $jugador) }}" method="POST" onsubmit="return confirm('¿Rechazar a {{ addslashes($jugador->nombre) }}?')">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-danger">Rechazar</button>
                                                        </form>
                                                    @endif
                                                </div>
                                            @endif
                                        @endhasanyrol
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 opacity-75">No hay jugadores registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="historial">
                <div class="mb-4">
                    <div>
                        <h4 class="fw-semibold">Historiales</h4>
                        <p class="opacity-75 mb-0">Goleadores y estadísticas disciplinarias del equipo.</p>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="tarjeta-opcion p-4 h-100">
                            <h5 class="fw-semibold">Goleadores</h5>
                            @if($goleadores->isEmpty())
                                <p class="opacity-75 mt-3">No hay datos de goles por jugador todavía.</p>
                            @else
                                <ol class="mt-3">
                                    @foreach($goleadores as $g)
                                        <li>{{ $g->nombre }} - {{ $g->goles }} goles</li>
                                    @endforeach
                                </ol>
                            @endif
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="tarjeta-opcion p-4 h-100">
                            <h5 class="fw-semibold">Tarjetas</h5>
                            @if($tarjetas->isEmpty())
                                <p class="opacity-75 mt-3">No hay datos de tarjetas por jugador todavía.</p>
                            @else
                                <ol class="mt-3">
                                    @foreach($tarjetas as $t)
                                        <li>{{ $t->nombre }} - {{ $t->amarillas }} amarillas, {{ $t->rojas }} rojas</li>
                                    @endforeach
                                </ol>
                            @endif
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="alert alert-info mb-0">
                            Los goles y las tarjetas se registran desde el partido usando la nómina aprobada por la Mesa de control.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
