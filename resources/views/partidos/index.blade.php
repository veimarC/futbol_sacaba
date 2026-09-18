{{--
    Vista de lista de partidos.
    Muestra los partidos programados y jugados para todos los campeonatos.
--}}
@extends('layouts.app')

@section('content')
<div class="contenedor-transparente animar-aparecer">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="bi bi-calendar-event me-2 text-accent"></i>Partidos</h2>
        <a href="{{ route('partidos.create') }}" class="btn btn-naranja">
            <i class="bi bi-plus-circle me-2"></i>Programar Partido
        </a>
    </div>

    @if(session('mensaje'))
        <div class="alert alert-success mb-4 success-glass">
            {{ session('mensaje') }}
        </div>
    @endif

    @if($partidos->isEmpty())
        <p class="text-center opacity-75 py-5">No hay partidos programados aún.</p>
    @else
        <div class="row g-4 stagger">
            @foreach($partidos as $partido)
                <div class="col-md-6 animar-aparecer">
                    <div class="tarjeta-opcion">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="badge rounded-pill {{ $partido->estado == 'programado' ? 'bg-warning text-dark' : ($partido->estado == 'jugado' ? 'bg-success' : 'bg-danger') }}">
                                {{ ucfirst($partido->estado) }}
                            </span>
                            <span class="small opacity-75">{{ \Carbon\Carbon::parse($partido->fecha)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($partido->hora)->format('H:i') }}</span>
                        </div>
                        <p class="small opacity-75 mb-3 text-center">Campeonato: {{ $partido->campeonato->nombre }}</p>
                        <div class="d-flex justify-content-around align-items-center text-center">
                            <div>
                                @if($escudoLocalUrl = $partido->equipoLocal->escudo_url)
                                    <img src="{{ $escudoLocalUrl }}" alt="Escudo de {{ $partido->equipoLocal->nombre }}" class="team-score-shield">
                                @else
                                    <i class="bi bi-shield team-score-placeholder"></i>
                                @endif
                                <h6 class="mb-0">{{ $partido->equipoLocal->nombre }}</h6>
                                @if($partido->estado == 'jugado')
                                    <strong class="fs-5">{{ $partido->goles_local }}</strong>
                                @endif
                            </div>
                            <span class="fs-3 fw-bold opacity-50">VS</span>
                            <div>
                                @if($escudoVisitaUrl = $partido->equipoVisita->escudo_url)
                                    <img src="{{ $escudoVisitaUrl }}" alt="Escudo de {{ $partido->equipoVisita->nombre }}" class="team-score-shield">
                                @else
                                    <i class="bi bi-shield team-score-placeholder"></i>
                                @endif
                                <h6 class="mb-0">{{ $partido->equipoVisita->nombre }}</h6>
                                @if($partido->estado == 'jugado')
                                    <strong class="fs-5">{{ $partido->goles_visita }}</strong>
                                @endif
                            </div>
                        </div>
                        @if($partido->cancha)
                            <p class="small opacity-75 mt-3 mb-0 text-center">Cancha: {{ $partido->cancha }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
