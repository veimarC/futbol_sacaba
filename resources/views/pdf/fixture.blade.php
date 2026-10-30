@extends('pdf.layout')
@section('heading')<h1>Fixture de la jornada</h1><h2>{{ $campeonato->nombre }}</h2>
<p>{{ $fecha === 'sin-fecha' ? 'Por programar' : \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}</p>@endsection
@section('content')
<table><thead><tr><th style="width:12%">Hora</th><th style="width:25%">Local</th><th style="width:25%">Visitante</th><th>Cancha / Fase</th><th style="width:15%">Estado</th></tr></thead><tbody>
@foreach($partidos as $partido)<tr>
<td>{{ $partido->hora ? substr($partido->hora, 0, 5) : 'Por programar' }}</td>
@foreach([$partido->equipoLocal, $partido->equipoVisita] as $equipo)<td>@if($logo = $imagenes->imagen($equipo?->escudo))<img class="crest" src="{{ $logo }}"><br>@endif{{ $equipo?->nombre ?? 'Por definir' }}</td>@endforeach
<td>{{ $partido->cancha ?: 'Por definir' }}<br>{{ $partido->fase?->nombre }}</td>
<td>{{ ucfirst($partido->estado) }}@if($partido->estado === 'jugado')<br>{{ $partido->goles_local }} - {{ $partido->goles_visita }}@if($partido->penales_local !== null)<br>Pen.: {{ $partido->penales_local }} - {{ $partido->penales_visita }}@endif @endif</td>
</tr>@endforeach</tbody></table>
<p>{{ $partidos->count() }} partidos · Horarios sujetos a cambios.</p>
@endsection
