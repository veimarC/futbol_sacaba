@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-xl-11">
        <div class="contenedor-transparente animar-aparecer">
            <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
                <div><span class="badge bg-warning text-dark mb-2"><i class="bi bi-flag-fill me-1"></i> Panel del árbitro</span><h1 class="fw-bold mb-1">Mis arbitrajes</h1><p class="opacity-75 mb-0">Revisa tus postulaciones y los partidos que debes dirigir.</p></div>
                <a href="{{ route('arbitros.explorar') }}" class="btn btn-naranja align-self-md-start"><i class="bi bi-search me-1"></i> Buscar campeonatos</a>
            </div>

            <section class="mb-5">
                <h2 class="h4 fw-bold mb-3">Mis postulaciones</h2>
                @if($solicitudes->isEmpty())
                    <div class="empty-referee"><i class="bi bi-send"></i><div><strong>Aún no enviaste postulaciones</strong><p class="mb-0 opacity-75">Explora campeonatos y elige dónde quieres arbitrar.</p></div></div>
                @else
                    <div class="row g-3">
                        @foreach($solicitudes as $campeonato)
                            @php
                                $estado = $campeonato->pivot->estado;
                                $estilos = ['pendiente' => 'bg-warning text-dark', 'aprobado' => 'bg-success', 'rechazado' => 'bg-danger', 'retirado' => 'bg-secondary'];
                            @endphp
                            <div class="col-md-6"><div class="referee-item h-100"><div class="d-flex justify-content-between gap-3"><div><h3 class="h5 fw-bold mb-1">{{ $campeonato->nombre }}</h3><span class="small opacity-75">Inicio: {{ $campeonato->fecha_inicio->format('d/m/Y') }}</span></div><span class="badge align-self-start {{ $estilos[$estado] ?? 'bg-secondary' }}">{{ ucfirst($estado) }}</span></div>
                                @if($campeonato->pivot->motivo_respuesta)<div class="small mt-3 p-2 rounded bg-dark bg-opacity-25"><strong>Respuesta:</strong> {{ $campeonato->pivot->motivo_respuesta }}</div>@endif
                                <a href="{{ route('campeonatos.show', $campeonato) }}" class="btn btn-borde btn-sm mt-3">Ver campeonato</a>
                            </div></div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section>
                <h2 class="h4 fw-bold mb-3">Mis partidos asignados</h2>
                @if($partidos->isEmpty())
                    <div class="empty-referee"><i class="bi bi-calendar2-x"></i><div><strong>No tienes partidos asignados</strong><p class="mb-0 opacity-75">Cuando un organizador te asigne un encuentro aparecerá aquí.</p></div></div>
                @else
                    <div class="row g-3">
                        @foreach($partidos as $partido)
                            <div class="col-lg-6"><article class="referee-item h-100"><div class="d-flex justify-content-between gap-2 mb-2"><span class="small opacity-75">{{ $partido->campeonato->nombre }}</span><span class="badge {{ $partido->estado === 'programado' ? 'bg-primary' : 'bg-success' }}">{{ $partido->estado === 'programado' ? 'Pendiente' : 'Jugado' }}</span></div><h3 class="h5 fw-bold">{{ $partido->equipoLocal->nombre }} <span class="text-warning">vs</span> {{ $partido->equipoVisita->nombre }}</h3><div class="d-flex flex-wrap gap-3 small opacity-75"><span><i class="bi bi-calendar-event me-1"></i>{{ $partido->fecha?->format('d/m/Y') ?: 'Por confirmar' }}</span><span><i class="bi bi-clock me-1"></i>{{ $partido->hora ?: 'Por confirmar' }}</span><span><i class="bi bi-geo-alt me-1"></i>{{ $partido->cancha ?: 'Por confirmar' }}</span></div>
                                @if($partido->estado === 'programado')<a href="{{ route('partidos.resultado', $partido) }}" class="btn btn-naranja btn-sm mt-3"><i class="bi bi-clipboard-check me-1"></i> Registrar resultado</a>@else<div class="mt-3 fw-bold fs-5">Resultado: {{ $partido->goles_local }} - {{ $partido->goles_visita }}</div>@endif
                            </article></div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
</div>

@endsection
