{{--
    Vista de ficha de jugador.
    Muestra detalles del jugador y permite ver su equipo, estado y estadísticas.
--}}
@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="contenedor-transparente">
            <div class="d-flex gap-3 align-items-center mb-3">
                <img src="{{ $jugador->foto_url }}" alt="Foto de {{ $jugador->nombre }}" class="photo-profile">
                <div>
                    <h3 class="mb-0">{{ $jugador->nombre }} {{ $jugador->apellido }}</h3>
                    <p class="mb-0 small text-muted">Equipo: {{ $jugador->equipo->nombre ?? '-' }}</p>
                </div>
            </div>

            <dl class="row">
                <dt class="col-sm-3">CI</dt>
                <dd class="col-sm-9">{{ $jugador->dni ?? '-' }}</dd>

                <dt class="col-sm-3">WhatsApp</dt>
                <dd class="col-sm-9">
                    @if($jugador->whatsapp_url)
                        <a href="{{ $jugador->whatsapp_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-success">
                            <i class="bi bi-whatsapp me-1"></i> +591 {{ $jugador->whatsapp_nacional }}
                        </a>
                    @else
                        No registrado
                    @endif
                </dd>

                <dt class="col-sm-3">Fecha de nacimiento</dt>
                <dd class="col-sm-9">{{ optional($jugador->fecha_nacimiento)->format('d/m/Y') ?? '-' }}</dd>

                <dt class="col-sm-3">Edad actual</dt>
                <dd class="col-sm-9">{{ $jugador->edad !== null ? $jugador->edad.' años' : 'No registrada' }}</dd>

                <dt class="col-sm-3">Posición</dt>
                <dd class="col-sm-9">{{ $jugador->posicion ?? '-' }}</dd>

                <dt class="col-sm-3">Sexo</dt>
                <dd class="col-sm-9">{{ $jugador->sexo ? ucfirst($jugador->sexo) : 'No registrado' }}</dd>
            </dl>

            <a href="{{ route('equipos.show', $jugador->equipo) }}#jugadores" class="btn btn-borde">Volver al equipo</a>
        </div>
    </div>
</div>
@endsection
