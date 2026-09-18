@extends('layouts.app')

@section('content')
<div class="public-portal">
    <div class="feed-heading">
        <div>
            <span>Explorar</span>
            <h2>{{ $buscar ? 'Resultados para “'.$buscar.'”' : 'Campeonatos destacados' }}</h2>
        </div>
        <strong>{{ $campeonatos->total() }} encontrados</strong>
    </div>

    <div class="public-feed" id="public-feed">
        @forelse($campeonatos as $campeonato)
            <article class="feed-card animar-aparecer">
                <a href="{{ route('publico.show', $campeonato) }}" class="feed-cover" aria-label="Ver {{ $campeonato->nombre }}">
                    @if($portadaUrl = $campeonato->portada_url)
                        <img class="feed-cover-image" src="{{ $portadaUrl }}" alt="Portada de {{ $campeonato->nombre }}">
                    @endif
                    <span class="feed-trophy"><i class="bi bi-trophy-fill"></i></span>
                    <span class="status status-{{ $campeonato->estado_actual }}"><i class="bi bi-circle-fill"></i>{{ $campeonato->estado_actual_label }}</span>
                </a>

                <div class="feed-body">
                    <div class="feed-title">
                        <span>Campeonato amateur</span>
                        <h3>{{ $campeonato->nombre }}</h3>
                        @if($campeonato->organizador)
                            <span class="feed-organizer"><i class="bi bi-person-badge"></i> Organiza: {{ $campeonato->organizador->name }}</span>
                        @endif
                    </div>
                    <p class="feed-description">
                        {{ \Illuminate\Support\Str::limit($campeonato->descripcion ?: 'Consulta los equipos participantes, próximos partidos, resultados y tabla de posiciones.', 180) }}
                    </p>
                    <div class="feed-tags">
                        <span><i class="bi bi-dribbble"></i> Fútbol {{ $campeonato->modalidad ?: 'libre' }}</span>
                        <span><i class="bi bi-gender-ambiguous"></i> {{ $campeonato->sexo_label ?: 'Mixto' }}</span>
                        <span><i class="bi bi-calendar3"></i> {{ $campeonato->categoria_edad_label ?: 'Libre' }}</span>
                    </div>
                    <div class="feed-footer">
                        <div class="feed-counts">
                            <span><strong>{{ $campeonato->equipos_aprobados_count }}</strong> equipos</span>
                            <span><strong>{{ $campeonato->partidos_count }}</strong> partidos</span>
                            <span><strong>{{ $campeonato->fecha_inicio->format('d/m/Y') }}</strong> inicio</span>
                        </div>
                        <a href="{{ route('publico.show', $campeonato) }}" class="btn btn-naranja">
                            Ver campeonato <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </article>
        @empty
            <div class="empty-feed">
                <i class="bi bi-search"></i>
                <h2>No encontramos campeonatos</h2>
                <p>Prueba buscando otro nombre.</p>
                <a href="{{ route('publico.index') }}" class="btn btn-borde">Ver todos</a>
            </div>
        @endforelse
    </div>

    @if($campeonatos->hasMorePages())
        <div class="text-center mt-4">
            <a id="load-more" href="{{ $campeonatos->nextPageUrl() }}" class="btn btn-borde btn-lg">
                <i class="bi bi-arrow-down-circle me-1"></i> Cargar más campeonatos
            </a>
        </div>
    @endif
</div>



<script>
document.addEventListener('DOMContentLoaded', () => {
    const button = document.getElementById('load-more');
    if (!button) return;

    button.addEventListener('click', async event => {
        event.preventDefault();
        if (button.dataset.loading) return;

        button.dataset.loading = '1';
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Cargando...';

        try {
            const response = await fetch(button.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error('No se pudo cargar la página');

            const html = await response.text();
            const nextDocument = new DOMParser().parseFromString(html, 'text/html');
            const feed = document.getElementById('public-feed');
            nextDocument.querySelectorAll('#public-feed .feed-card').forEach(card => feed.append(card));

            const nextButton = nextDocument.getElementById('load-more');
            if (!nextButton) {
                button.parentElement.remove();
                return;
            }

            button.href = nextButton.href;
            button.innerHTML = '<i class="bi bi-arrow-down-circle me-1"></i> Cargar más campeonatos';
            delete button.dataset.loading;
        } catch (error) {
            button.innerHTML = 'No se pudo cargar. Intentar nuevamente';
            delete button.dataset.loading;
        }
    });
});
</script>
@endsection
