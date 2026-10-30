@php
    // Un nodo representa un cruce completo, aunque haya tenido ida y vuelta.
    $ganoA = $serie && $serie['completa'] && ($serie['ganador_id'] ?? null) === $serie['equipo_a']?->id && $serie['equipo_a'] !== null;
    $ganoB = $serie && $serie['completa'] && ($serie['ganador_id'] ?? null) === $serie['equipo_b']?->id && $serie['equipo_b'] !== null;
    $fechas = $serie
        ? $serie['partidos']->pluck('fecha')->filter()->map(fn ($fecha) => $fecha->format('d/m'))->unique()->values()
        : collect();
    $fechaVisible = $fechas->isEmpty()
        ? 'Por definir'
        : ($fechas->count() > 1 ? $fechas->first().' – '.$fechas->last() : $fechas->first());
@endphp

<article class="tree-match {{ $serie ? '' : 'is-pending' }}">
    <div class="tree-team {{ $ganoA ? 'is-winner' : '' }}">
        <x-team-crest :equipo="$serie['equipo_a'] ?? null" />
        <span>{{ $serie['equipo_a']?->nombre ?? 'Por definir' }}</span>
        <strong>{{ $serie && $serie['completa'] ? $serie['goles_a'] : '—' }}</strong>
    </div>
    <div class="tree-team {{ $ganoB ? 'is-winner' : '' }}">
        <x-team-crest :equipo="$serie['equipo_b'] ?? null" />
        <span>{{ $serie['equipo_b']?->nombre ?? 'Por definir' }}</span>
        <strong>{{ $serie && $serie['completa'] ? $serie['goles_b'] : '—' }}</strong>
    </div>
    @if($serie && $serie['partidos']->contains(fn ($partido) => (bool) $partido->wo_motivo))<span class="badge bg-warning text-dark">W.O.</span>@endif
    <small class="tree-match-meta">
        <span><i class="bi bi-calendar-event"></i>{{ $fechaVisible }}</span>
        <span>{{ $serie && (! $serie['equipo_a'] || ! $serie['equipo_b']) ? 'Esperando rival' : ($serie ? ($serie['ida_vuelta'] ? 'Resultado global' : 'Partido único') : 'Esperando equipos') }}</span>
    </small>
    @if($serie && $serie['completa'] && ($serie['penales_a'] ?? null) !== null)
        <small class="d-block">Penales: {{ $serie['penales_a'] }} - {{ $serie['penales_b'] }}</small>
    @endif
</article>
