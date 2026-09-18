@extends('layouts.app')

@section('content')
<div class="contenedor-transparente animar-aparecer">
    <div class="mb-4">
        <span class="page-kicker">Nómina por partido</span>
        <h2 class="fw-bold mb-1">{{ $equipo->nombre }}</h2>
        <p class="opacity-75 mb-0">Escribe el dorsal solamente en los jugadores convocados.</p>
    </div>

    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('partidos.nominas.guardar', [$partido, $equipo]) }}">
        @csrf @method('PUT')
        <div class="table-responsive sports-table-wrap">
            <table class="table table-dark align-middle mb-0">
                <thead><tr><th>Jugador</th><th>Posición</th><th style="width:140px">Dorsal</th><th>Disponibilidad</th></tr></thead>
                <tbody>
                    @foreach($jugadores as $jugador)
                        @php $suspendido = $suspendidos->contains($jugador->id); @endphp
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img src="{{ $jugador->foto_url }}" alt="" class="photo-xs">
                                    <div class="d-flex flex-column">
                                        <strong>{{ $jugador->nombre }} {{ $jugador->apellido }}</strong>
                                        <small class="opacity-75">CI: {{ $jugador->dni ?: 'Sin registrar' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $jugador->posicion ?: 'Sin definir' }}</td>
                            <td><input type="number" name="dorsales[{{ $jugador->id }}]" min="1" max="99" class="form-control" value="{{ old('dorsales.'.$jugador->id, $dorsales->get($jugador->id)) }}" placeholder="—" @disabled($suspendido)></td>
                            <td>@if($suspendido)<span class="badge bg-danger"><i class="bi bi-lock-fill me-1"></i>Suspendido</span>@else<span class="badge bg-success">Disponible</span>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-4">
            <button class="btn btn-naranja"><i class="bi bi-send me-1"></i>Enviar a Mesa de control</button>
            <a href="{{ route('partidos.nominas.index', $partido) }}" class="btn btn-borde">Cancelar</a>
        </div>
    </form>
</div>
@endsection
