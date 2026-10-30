{{--
    Vista para editar jugador.
    Permite modificar datos personales y equipo cuando el delegado o administrador general tiene permiso.
--}}
@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="contenedor-transparente">
            <h4 class="mb-3">Editar Jugador</h4>

            <form action="{{ route('jugadores.update', $jugador) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="nombre" class="form-control" value="{{ old('nombre', $jugador->nombre) }}" required>
                    @error('nombre') <div class="text-danger">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Apellido</label>
                    <input type="text" name="apellido" class="form-control" value="{{ old('apellido', $jugador->apellido) }}">
                </div>

                <div class="mb-3">
                    <label class="form-label">CI (cédula de identidad)</label>
                    <input type="text" name="dni" class="form-control" value="{{ old('dni', $jugador->dni) }}">
                    @error('dni') <div class="text-danger">{{ $message }}</div> @enderror
                </div>

                <x-whatsapp-field :value="$jugador->whatsapp_nacional" />

                <div class="form-check mb-3">
                    <input type="hidden" name="whatsapp_notifications" value="0">
                    <input id="whatsapp_notifications" name="whatsapp_notifications" value="1" type="checkbox" class="form-check-input" {{ old('whatsapp_notifications', $jugador->whatsapp_opt_in_at !== null) ? 'checked' : '' }}>
                    <label for="whatsapp_notifications" class="form-check-label">
                        Confirmo que el jugador autorizó recibir por WhatsApp avisos de sus partidos de Fútbol Sacaba.
                    </label>
                    <div class="form-text">Desmarca la casilla para cancelar inmediatamente los mensajes automáticos.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Fecha de Nacimiento</label>
                    <input type="date" name="fecha_nacimiento" class="form-control" value="{{ old('fecha_nacimiento', optional($jugador->fecha_nacimiento)->format('Y-m-d')) }}">
                </div>

                <div class="mb-3">
                    <label class="form-label">Sexo *</label>
                    <select name="sexo" class="form-select @error('sexo') is-invalid @enderror" required>
                        <option value="masculino" {{ old('sexo', $jugador->sexo) === 'masculino' ? 'selected' : '' }}>Masculino</option>
                        <option value="femenino" {{ old('sexo', $jugador->sexo) === 'femenino' ? 'selected' : '' }}>Femenino</option>
                    </select>
                    @error('sexo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Equipo</label>
                    <select name="equipo_id" class="form-select" required>
                        @foreach($equipos as $e)
                            <option value="{{ $e->id }}" {{ $jugador->equipo_id == $e->id ? 'selected' : '' }}>{{ $e->nombre }}</option>
                        @endforeach
                    </select>
                    @error('equipo_id') <div class="text-danger">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Foto (opcional)</label>
                    <div class="mb-2"><img src="{{ $jugador->foto_url }}" alt="Foto de {{ $jugador->nombre }}" class="photo-md"></div>
                    <input type="file" name="foto" class="form-control">
                    <div class="form-text">La nueva foto reemplazará la anterior.</div>
                </div>

                <div class="d-grid">
                    <button class="btn btn-naranja">Actualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
