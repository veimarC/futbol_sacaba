@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-xl-11">
        <section class="contenedor-transparente animar-aparecer mb-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-4">
                <div>
                    <span class="badge rounded-pill bg-warning text-dark mb-3"><i class="bi bi-flag-fill me-1"></i> Oportunidades para arbitrar</span>
                    <h1 class="fw-bold mb-2">Explorar campeonatos</h1>
                    <p class="opacity-75 mb-0">Busca un campeonato, revisa sus reglas y envía tu postulación al organizador.</p>
                </div>
                <form method="GET" action="{{ route('arbitros.explorar') }}" class="referee-search" role="search">
                    <i class="bi bi-search"></i>
                    <label for="buscar-campeonato" class="visually-hidden">Buscar por nombre</label>
                    <input id="buscar-campeonato" name="buscar" type="search" value="{{ $busqueda }}" placeholder="Nombre del campeonato...">
                    <button type="submit">Buscar</button>
                </form>
            </div>
        </section>

        <div class="d-flex justify-content-between align-items-center mb-3 px-1">
            <h2 class="h5 fw-semibold mb-0">{{ $busqueda ? 'Resultados de la búsqueda' : 'Campeonatos disponibles' }}</h2>
            <a href="{{ route('arbitros.panel') }}" class="btn btn-borde btn-sm"><i class="bi bi-calendar-check me-1"></i> Mis arbitrajes</a>
        </div>

        <div class="row g-4">
            @forelse($campeonatos as $campeonato)
                @php
                    $solicitud = $campeonato->arbitros->first()?->pivot;
                @endphp
                <div class="col-md-6 col-xl-4 animar-aparecer">
                    <article class="referee-championship h-100">
                        <div class="referee-cover">
                            @if($portadaUrl = $campeonato->portada_url)<img class="referee-cover-image" src="{{ $portadaUrl }}" alt="Portada de {{ $campeonato->nombre }}">@endif
                            <i class="bi bi-trophy-fill"></i>
                            <span class="badge {{ $campeonato->estado_actual === 'en_curso' ? 'bg-primary' : 'bg-warning text-dark' }}">
                                {{ $campeonato->estado_actual_label }}
                            </span>
                        </div>
                        <div class="p-4 d-flex flex-column flex-grow-1">
                            <h3 class="h4 fw-bold mb-2">{{ $campeonato->nombre }}</h3>
                            <p class="small opacity-75 mb-3">Fútbol {{ $campeonato->modalidad }} · {{ $campeonato->categoria_edad_label }} · {{ $campeonato->sexo_label }}</p>
                            <div class="referee-meta mb-4">
                                <span><i class="bi bi-calendar-event"></i> {{ $campeonato->fecha_inicio->format('d/m/Y') }}</span>
                                <span><i class="bi bi-flag"></i> {{ $campeonato->arbitros_aprobados_count }} árbitros aprobados</span>
                            </div>
                            <div class="mt-auto">
                                @if($solicitud)
                                    @php
                                        $estilos = ['pendiente' => 'bg-warning text-dark', 'aprobado' => 'bg-success', 'rechazado' => 'bg-danger', 'retirado' => 'bg-secondary'];
                                    @endphp
                                    <div class="application-status mb-3">
                                        <span class="small opacity-75">Tu postulación</span>
                                        <span class="badge {{ $estilos[$solicitud->estado] ?? 'bg-secondary' }}">{{ ucfirst($solicitud->estado) }}</span>
                                    </div>
                                    <a href="{{ route('arbitros.panel') }}" class="btn btn-borde w-100">Ver estado</a>
                                @else
                                    <a href="{{ route('campeonatos.show', $campeonato) }}" class="btn btn-naranja w-100">Ver y postularme <i class="bi bi-arrow-right ms-1"></i></a>
                                @endif
                            </div>
                        </div>
                    </article>
                </div>
            @empty
                <div class="col-12"><div class="contenedor-transparente text-center py-5"><i class="bi bi-search display-4 opacity-50"></i><h3 class="h4 mt-3">No encontramos campeonatos</h3><p class="opacity-75">Prueba con otro nombre.</p></div></div>
            @endforelse
        </div>
        @if($campeonatos->hasPages())<div class="mt-4">{{ $campeonatos->links() }}</div>@endif
    </div>
</div>


@endsection
