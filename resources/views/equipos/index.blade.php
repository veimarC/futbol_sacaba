{{-- Cada tarjeta representa un equipo visible para el usuario actual. --}}
@extends('layouts.app')

@section('content')
<div class="contenedor-transparente animar-aparecer">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h2 fw-bold mb-1"><i class="bi bi-shield-fill me-2 text-warning"></i>Mis equipos</h1>
            <p class="opacity-75 mb-0">{{ auth()->user()->esDesarrollador() ? 'Inspecciona todos los equipos y sus jugadores.' : 'Administra tus equipos y sus jugadores.' }}</p>
        </div>
        <a href="{{ route('equipos.create') }}" class="btn btn-naranja align-self-start">
            <i class="bi bi-plus-circle me-1"></i> Crear equipo
        </a>
    </div>

    @if($equipos->isEmpty())
        <div class="team-empty text-center">
            <i class="bi bi-shield-plus display-3"></i>
            <h2 class="h4 mt-3">Todavía no tienes equipos</h2>
            <p class="opacity-75">Crea tu primer equipo y luego podrás registrar sus jugadores.</p>
            <a href="{{ route('equipos.create') }}" class="btn btn-naranja">Crear mi primer equipo</a>
        </div>
    @else
        <div class="row g-4 stagger">
            @foreach($equipos as $equipo)
                <div class="col-md-6 col-xl-4 animar-aparecer">
                    <article class="team-card h-100">
                        <div class="team-card-head">
                            <span class="team-shield">
                                @if($escudoUrl = $equipo->escudo_url)
                                    <img src="{{ $escudoUrl }}" alt="Escudo de {{ $equipo->nombre }}">
                                @else
                                    <i class="bi bi-shield"></i>
                                @endif
                            </span>
                            <div class="min-w-0">
                                <h2 class="h4 fw-bold mb-1 text-truncate">{{ $equipo->nombre }}</h2>
                                <span class="small opacity-75"><i class="bi bi-geo-alt me-1"></i>{{ $equipo->ciudad ?: 'Ciudad no indicada' }}</span>
                            </div>
                        </div>

                        <div class="team-stats">
                            <span><strong>{{ $equipo->jugadores_count }}</strong><small>jugadores</small></span>
                            <span><strong>{{ $equipo->campeonatos->count() }}</strong><small>campeonatos</small></span>
                        </div>

                        <div class="d-flex gap-2 mt-auto pt-3">
                            @php($campeonatoContexto = $equipo->campeonatos->count() === 1 ? $equipo->campeonatos->first()->id : ($equipo->campeonatos->isEmpty() ? $equipo->campeonato_id : null))
                            <a href="{{ route('equipos.show', array_filter([$equipo, 'campeonato' => $campeonatoContexto], fn ($valor) => $valor !== null)) }}" class="btn btn-naranja flex-grow-1"><i class="bi bi-people me-1"></i> Ver plantilla</a>
                            <a href="{{ route('equipos.edit', array_filter([$equipo, 'campeonato' => $campeonatoContexto], fn ($valor) => $valor !== null)) }}" class="btn btn-borde" aria-label="Editar {{ $equipo->nombre }}"><i class="bi bi-pencil"></i></a>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
    @endif
</div>


@endsection
