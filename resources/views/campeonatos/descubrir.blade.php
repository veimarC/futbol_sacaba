@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-xl-11">
        <section class="contenedor-transparente animar-aparecer mb-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-4">
                <div>
                    <span class="badge rounded-pill bg-warning text-dark mb-3"><i class="bi bi-compass me-1"></i> Descubrir</span>
                    <h1 class="fw-bold mb-2">Encuentra tu próximo campeonato</h1>
                    <p class="opacity-75 mb-0">Busca por nombre, revisa las fechas y solicita la inscripción de tu equipo.</p>
                </div>
                <form method="GET" action="{{ route('campeonatos.descubrir') }}" class="search-championship" role="search">
                    <label for="buscar-campeonato" class="visually-hidden">Buscar campeonato por nombre</label>
                    <i class="bi bi-search"></i>
                    <input id="buscar-campeonato" name="buscar" type="search" value="{{ $busqueda }}" placeholder="Buscar por nombre..." autocomplete="off">
                    <button type="submit">Buscar</button>
                </form>
            </div>
        </section>

        <div class="d-flex justify-content-between align-items-center mb-3 px-1">
            <h2 class="h5 fw-semibold mb-0">
                {{ $busqueda ? 'Resultados para “'.$busqueda.'”' : 'Inscripciones abiertas' }}
            </h2>
            <span class="small opacity-75">{{ $campeonatos->total() }} {{ $campeonatos->total() === 1 ? 'campeonato' : 'campeonatos' }}</span>
        </div>

        <div class="row g-4">
            @forelse($campeonatos as $campeonato)
                <div class="col-md-6 col-xl-4 animar-aparecer">
                    <article class="championship-card h-100">
                        <a href="{{ route('campeonatos.show', $campeonato) }}" class="championship-cover text-decoration-none" aria-label="Ver {{ $campeonato->nombre }}">
                            @if($portadaUrl = $campeonato->portada_url)<img class="championship-cover-image" src="{{ $portadaUrl }}" alt="Portada de {{ $campeonato->nombre }}">@endif
                            <span class="championship-ball"><i class="bi bi-trophy-fill"></i></span>
                            <span class="badge bg-success"><i class="bi bi-broadcast me-1"></i> Inscripciones abiertas</span>
                        </a>
                        <div class="p-4 d-flex flex-column flex-grow-1">
                            <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                                <div>
                                    <h3 class="h4 fw-bold mb-1">{{ $campeonato->nombre }}</h3>
                                    <span class="small opacity-75">Fútbol {{ $campeonato->modalidad }} · {{ $campeonato->categoria_edad_label }} · {{ $campeonato->sexo_label }}</span>
                                    @if($campeonato->organizador)
                                        <div class="small mt-2"><i class="bi bi-person-badge me-1 text-warning"></i>Organiza: <strong>{{ $campeonato->organizador->name }}</strong></div>
                                    @endif
                                </div>
                                <span class="championship-avatar"><i class="bi bi-shield-fill"></i></span>
                            </div>
                            <p class="championship-description opacity-75 mb-4">
                                {{ \Illuminate\Support\Str::limit($campeonato->descripcion ?: 'Un nuevo desafío te espera. Conoce el campeonato e inscribe a tu equipo.', 125) }}
                            </p>
                            <div class="championship-meta mt-auto">
                                <span><i class="bi bi-calendar-event"></i> {{ $campeonato->fecha_inicio->format('d/m/Y') }}</span>
                                <span><i class="bi bi-people-fill"></i> {{ $campeonato->equipos_aprobados_count }} equipos</span>
                            </div>
                            @if($campeonato->fecha_limite_inscripcion)
                                <div class="small mt-3 opacity-75"><i class="bi bi-hourglass-split me-1"></i> Solicitudes hasta el {{ $campeonato->fecha_limite_inscripcion->format('d/m/Y') }}</div>
                            @endif
                            <a href="{{ route('campeonatos.show', $campeonato) }}" class="btn btn-naranja w-100 mt-4">
                                Ver campeonato <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </article>
                </div>
            @empty
                <div class="col-12">
                    <div class="contenedor-transparente text-center py-5">
                        <i class="bi bi-search display-4 opacity-50"></i>
                        <h3 class="h4 mt-3">No encontramos campeonatos</h3>
                        <p class="opacity-75">Prueba con otro nombre o consulta nuevamente más adelante.</p>
                        @if($busqueda)<a href="{{ route('campeonatos.descubrir') }}" class="btn btn-borde">Ver todos</a>@endif
                    </div>
                </div>
            @endforelse
        </div>

        @if($campeonatos->hasPages())
            <div class="mt-4">{{ $campeonatos->links() }}</div>
        @endif
    </div>
</div>


@endsection
