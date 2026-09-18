{{--
    Vista de lista de campeonatos.
    Muestra todos los torneos existentes y permite crear uno nuevo.
--}}
@extends('layouts.app')

@section('content')
<div class="contenedor-transparente animar-aparecer">
    <div class="d-flex justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-trophy me-2 text-accent"></i>{{ auth()->user()->rol === \App\Models\User::ROLE_DELEGADO ? 'Mis campeonatos' : 'Campeonatos' }}</h2>
            @if(auth()->user()->rol === \App\Models\User::ROLE_DELEGADO)
                <p class="opacity-75 mb-0">Campeonatos donde inscribiste uno de tus equipos.</p>
            @endif
        </div>
        @can('create', \App\Models\Campeonato::class)
        <a href="{{ route('campeonatos.create') }}" class="btn btn-naranja">
            <i class="bi bi-plus-circle me-2"></i>Nuevo Campeonato
        </a>
        @endcan
    </div>

    @if(session('mensaje'))
        <div class="alert alert-success mb-4 success-glass">
            {{ session('mensaje') }}
        </div>
    @endif

    @if($campeonatos->isEmpty())
        <div class="text-center py-5">
            <p class="opacity-75">{{ auth()->user()->rol === \App\Models\User::ROLE_DELEGADO ? 'Todavía no participas en ningún campeonato.' : 'No hay campeonatos creados aún.' }}</p>
            @if(auth()->user()->rol === \App\Models\User::ROLE_DELEGADO)
                <a href="{{ route('campeonatos.descubrir') }}" class="btn btn-naranja">Explorar campeonatos</a>
            @endif
        </div>
    @else
        <div class="row g-4">
            @foreach($campeonatos as $campeonato)
                <a href="{{ route('campeonatos.show', ['campeonato' => $campeonato, 'seccion' => auth()->user()->rol === \App\Models\User::ROLE_DELEGADO ? 'partidos' : null]) }}" class="col-md-6 tarjeta-opcion d-block text-decoration-none link-inherit">
                    <h4 class="fw-bold mb-2">{{ $campeonato->nombre }}</h4>
                    @if($campeonato->organizador)
                        <div class="small mb-2"><i class="bi bi-person-badge me-1 text-warning"></i>Organiza: <strong>{{ $campeonato->organizador->name }}</strong></div>
                    @endif
                    @if($campeonato->descripcion)
                        <p class="small opacity-75 mb-2">{{ $campeonato->descripcion }}</p>
                    @endif
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <span class="badge rounded-pill {{ $campeonato->estado_actual === 'pendiente' ? 'bg-warning text-dark' : ($campeonato->estado_actual === 'en_curso' ? 'bg-primary' : 'bg-secondary') }}">
                            {{ $campeonato->estado_actual_label }}
                        </span>
                        <span class="small opacity-75">Inicio: {{ \Carbon\Carbon::parse($campeonato->fecha_inicio)->format('d/m/Y') }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
     

</div>
@endsection
