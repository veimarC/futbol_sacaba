@extends('pdf.layout')
@section('heading')
<h1>Reglamento del campeonato</h1>
<h2>{{ $campeonato->nombre }}</h2>
<p>Inicio: {{ $campeonato->fecha_inicio?->format('d/m/Y') ?? 'Por definir' }} · Fin: {{ $campeonato->fecha_fin?->format('d/m/Y') ?? 'Por definir' }}</p>
@if($campeonato->aprobacion_estado !== 'aprobado')<div class="tag">BORRADOR — Campeonato pendiente de aprobación</div>@endif
@endsection
@section('content')
<h2>Reglas de participación</h2>
<table><tbody>
<tr><th>Modalidad</th><td>Fútbol {{ $campeonato->modalidad ?: 'libre' }}</td><th>Categoría</th><td>{{ $campeonato->categoria_edad ? $campeonato->categoria_edad_label : 'Libre' }}</td></tr>
<tr><th>Participación</th><td>{{ $campeonato->sexo ? $campeonato->sexo_label : 'Mixto' }}</td><th>Jugadores en cancha</th><td>{{ $campeonato->jugadores_cancha ?? 'Sin definir' }}</td></tr>
<tr><th>Mínimo para jugar</th><td>{{ $campeonato->minimo_para_jugar }} jugadores</td><th>Plantilla por equipo</th><td>{{ $campeonato->min_jugadores_equipo ?? 'Sin definir' }} a {{ $campeonato->max_jugadores_equipo ?? 'sin definir' }} jugadores</td></tr>
<tr><th>Edad mínima</th><td>{{ $campeonato->edad_min ?? 'Sin límite' }}</td><th>Edad máxima</th><td>{{ $campeonato->edad_max ?? 'Sin límite' }}</td></tr>
</tbody></table>
<h2>Reglamentos internos</h2>
@if(filled($campeonato->reglamento_interno))
<div style="white-space:pre-wrap;line-height:1.6;word-wrap:break-word">{{ $campeonato->reglamento_interno }}</div>
@else
<p>El organizador todavía no publicó reglamentos internos.</p>
@endif
<div class="signatures"><div class="table-official">
<div class="signature-line"></div>
<strong>Firma del organizador</strong>
<p>{{ $campeonato->organizador?->name ?? 'Nombre: ________________________' }}</p>
<p>Fecha: ____ / ____ / ________</p>
<p class="muted">Espacio para firmar a mano después de imprimir.</p>
</div></div>
@endsection
