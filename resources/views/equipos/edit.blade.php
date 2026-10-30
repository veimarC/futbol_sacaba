{{--
    Vista para editar un equipo.
    Permite cambiar el nombre, delegado, campeonato y datos adicionales antes de iniciar el torneo.
--}}
@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="contenedor-transparente animar-aparecer">
            <h2 class="fw-bold mb-4"><i class="bi bi-pencil-square me-2 text-accent"></i>Editar Equipo</h2>
            <form method="POST" action="{{ route('equipos.update', $equipo) }}" enctype="multipart/form-data">
                @csrf @method('PUT')
                @if($campeonatoId)
                    <input type="hidden" name="campeonato" value="{{ $campeonatoId }}">
                @endif
                <p class="required-note"><span aria-hidden="true">*</span> Los campos marcados con asterisco son obligatorios.</p>

                <div class="mb-3">
                    <label class="form-label">Nombre del Equipo *</label>
                    <input type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre', $equipo->nombre) }}" required>
                    @error('nombre')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>

                {{-- ✅ CAMPO QUE FALTABA: Delegado obligatorio --}}
                <div class="mb-3">
                    <label class="form-label">Ciudad</label>
                    <input type="text" name="ciudad" class="form-control @error('ciudad') is-invalid @enderror" value="{{ old('ciudad', $equipo->ciudad) }}" placeholder="Ej.: Sacaba">
                    @error('ciudad')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>

                <div class="mb-4">
                    <label class="form-label">Escudo</label>
                    @if($escudoUrl = $equipo->escudo_url)
                        <div class="mb-2">
                            <img src="{{ $escudoUrl }}" class="shield-preview" alt="Escudo actual de {{ $equipo->nombre }}">
                            <small class="ms-2 text-white-50">Escudo actual</small>
                        </div>
                    @endif
                    <input type="file" name="escudo" class="form-control @error('escudo') is-invalid @enderror" accept="image/jpeg,image/png,image/webp">
                    @error('escudo')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    <div class="form-text">Déjalo vacío para conservar el escudo actual. Máximo 2 MB.</div>
                </div>

                <div class="d-flex gap-3">
                    <button type="submit" class="btn btn-naranja">Actualizar Equipo</button>
                    <a href="{{ route('equipos.show', array_filter([$equipo, 'campeonato' => $campeonatoId], fn ($valor) => $valor !== null)) }}" class="btn btn-borde">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
