@extends('pdf.layout')
@php
    $listas = $equipos->map(fn($equipo) => $partido->nominas->firstWhere('equipo_id', $equipo->id)?->jugadores ?? collect());
    $filas = max(1, $listas->map->count()->max(), $partido->estado !== 'jugado' ? ($partido->campeonato->max_jugadores_equipo ?: 18) : 0);
    $altoFila = min(19, 540 / $filas);
    $fuenteFila = min(10, $altoFila * 0.65);
@endphp
@section('styles')
<style>
    h1 {font-size:17px;margin:4px 0} h2 {font-size:12px;margin:4px 0}
    p {margin:4px 0}
    .tag {margin:6px 0;padding:4px}
    .score {font-size:18px;margin:5px 0}
    .score-box {height:25px}
    .roster {table-layout:auto}
    .roster th {padding:3px}
    .roster td {height:{{ $altoFila }}px;padding:0 3px;font-size:{{ $fuenteFila }}px;line-height:1.05}
    .crest {width:24px;height:24px}
    .team {font-size:11px}
    .muted {font-size:8px}
    .signatures {margin-top:8px;font-size:8px}
    .signature-table td {width:33.33%;padding:4px 8px;vertical-align:top}
    .signature-line {margin:22px 0 5px}
</style>
@endsection
@section('heading')
<h1>Planilla del partido #{{ $partido->numero_campeonato }}</h1><h2>{{ $partido->campeonato->nombre }}</h2>
<p>{{ $partido->fase?->nombre }} · {{ $partido->fecha?->format('d/m/Y') ?? 'Por programar' }} · {{ $partido->hora ? substr($partido->hora, 0, 5) : 'Hora por definir' }} · {{ $partido->cancha ?: 'Cancha por definir' }}</p>
<div class="tag">{{ $partido->estado === 'jugado' ? 'FINALIZADO' : 'PROVISIONAL — Partido sin finalizar' }}</div>
@php
    $tieneMarcador = $partido->estado === 'jugado' || $partido->incidencias->contains('tipo', 'gol') || $partido->goles_local > 0 || $partido->goles_visita > 0;
@endphp
<p class="score"><span class="score-box">{{ $tieneMarcador ? ($partido->goles_local ?? 0) : '' }}</span> - <span class="score-box">{{ $tieneMarcador ? ($partido->goles_visita ?? 0) : '' }}</span></p>
@if($partido->penales_local !== null)<p>Penales: {{ $partido->penales_local }} - {{ $partido->penales_visita }}</p>@endif
@if($partido->wo_motivo)<p>Resultado por W.O. · {{ $partido->wo_motivo === 'inasistencia' ? 'Inasistencia' : 'Jugadores insuficientes' }}</p>@endif
@endsection
@section('content')
<table class="roster"><colgroup>@foreach($equipos as $equipo)<col style="width:5%"><col style="width:28%"><col style="width:5%"><col style="width:6%"><col style="width:6%">@endforeach</colgroup><thead><tr>@foreach($equipos as $equipo)<th colspan="5" class="team">
@if($logo = $imagenes->imagen($equipo->escudo))<img class="crest" src="{{ $logo }}">@endif
{{ $equipo->nombre }}<br><small>{{ ucfirst($partido->nominas->firstWhere('equipo_id', $equipo->id)?->estado ?? 'Sin nómina') }}</small>
</th>@endforeach</tr>
<tr>@foreach($equipos as $equipo)<th style="width:5%">N°</th><th style="width:28%">Jugador</th><th>G</th><th>TA</th><th>TR</th>@endforeach</tr></thead><tbody>
@for($i = 0; $i < $filas; $i++)<tr>
@foreach($listas as $lista)
@php $registro = $lista->get($i); $eventos = $registro ? $partido->incidencias->where('jugador_id', $registro->jugador_id) : collect(); @endphp
@if($registro)
<td>{{ $registro->dorsal }}</td><td class="player-name">{{ $registro->jugador->nombre }} {{ $registro->jugador->apellido }}</td>
<td>{{ $eventos->where('tipo', 'gol')->count() ?: '' }}</td><td>{{ $eventos->whereIn('tipo', ['amarilla', 'doble_amarilla'])->count() ?: '' }}</td><td>{{ $eventos->whereIn('tipo', ['roja', 'doble_amarilla'])->count() ?: '' }}</td>
@else<td></td><td class="player-name"></td><td></td><td></td><td></td>@endif
@endforeach</tr>@endfor
</tbody></table>
<p class="muted">G: goles · TA: tarjetas amarillas · TR: tarjetas rojas. Los penales no suman a los goles.</p>
@if($partido->estado !== 'jugado')<p class="muted">Casillas vacías para completar a mano. Después del partido, registra la información en la plataforma.</p>@endif
<div class="signatures">
    <p class="muted">Firmas para completar a mano después de imprimir.</p>
    <table class="signature-table"><tr>
        @foreach($equipos as $equipo)
        <td><div class="signature-line"></div><strong>Firma del capitán</strong><br>{{ $equipo->nombre }}<p>Nombre: ________________________</p></td>
        @endforeach
        <td><div class="signature-line"></div><strong>Firma del responsable de mesa</strong><p>Nombre: ________________________</p></td>
    </tr></table>
</div>
@endsection
