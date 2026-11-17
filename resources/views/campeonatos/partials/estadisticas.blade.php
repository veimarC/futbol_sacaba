<section class="championship-stats">
<div class="module-heading mb-4">
    <h5 class="fw-semibold mb-1">Estadísticas del campeonato</h5>
    <p class="small opacity-75 mb-0">Resumen de los partidos finalizados de todas las fases.</p>
</div>

<div class="row g-3 mb-4">
    @foreach(['partidos' => 'Partidos finalizados', 'goles' => 'Goles anotados', 'tarjetas' => 'Total de tarjetas'] as $clave => $etiqueta)
        <div class="col-12 col-sm-4">
            <div class="stats-metric rounded-3 p-3 text-center h-100">
                <div class="small opacity-75">{{ $etiqueta }}</div>
                <strong class="fs-2">{{ $estadisticas[$clave] }}</strong>
            </div>
        </div>
    @endforeach
</div>

<div class="stats-chart rounded-3 p-3 p-md-4">
    <h6 class="fw-semibold mb-3">Victorias por equipo</h6>
    @if($estadisticas['partidos'] === 0)
        <p class="opacity-75 mb-0">Las estadísticas aparecerán cuando finalice el primer partido.</p>
    @else
        @foreach($estadisticas['victorias'] as $fila)
            <div class="stats-bar-row">
                <div class="d-flex align-items-center justify-content-between gap-3 mb-1">
                    <span class="stats-team-label">
                        <x-team-crest :equipo="$fila['equipo']" />
                        <span class="text-break">{{ $fila['equipo']->nombre }}</span>
                    </span>
                    <strong>{{ $fila['total'] }} <span class="visually-hidden">victorias</span></strong>
                </div>
                <div class="progress" aria-hidden="true">
                    <div class="progress-bar" style="width: {{ $fila['total'] / $estadisticas['max_victorias'] * 100 }}%"></div>
                </div>
            </div>
        @endforeach
    @endif
</div>
<details class="stats-notes mt-3">
<summary>Cómo se calculan las estadísticas</summary>
<p class="small mt-2 mb-0">
    Los goles excluyen W.O. y tandas de penales. Las victorias incluyen W.O.; los empates resueltos por penales se mantienen como empates en este gráfico.
    Las tarjetas suman amonestaciones y rojas directas; una doble amarilla cuenta como segunda amonestación.
</p>
</details>
</section>
