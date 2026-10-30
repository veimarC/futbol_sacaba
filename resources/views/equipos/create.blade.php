{{--
    Vista para registrar un equipo.
    El delegado registra equipos y solicita su inscripción en campeonatos pendientes.
--}}
@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="contenedor-transparente animar-aparecer">
            <h2 class="fw-bold mb-4"><i class="bi bi-person-plus me-2 text-accent"></i>Registrar Equipo</h2>
            <form method="POST" action="{{ route('equipos.store') }}" enctype="multipart/form-data">
                @csrf
                <p class="required-note"><span aria-hidden="true">*</span> Los campos marcados con asterisco son obligatorios.</p>
                <div class="mb-3">
                    <label class="form-label">Nombre del Equipo *</label>
                    <input type="text" name="nombre" class="form-control form-control-lg @error('nombre') is-invalid @enderror" required value="{{ old('nombre') }}" placeholder="Ej.: Sacaba Fútbol Club">
                    @error('nombre')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>
                @if($campeonatoElegido)
                    <input type="hidden" name="campeonato_id" value="{{ $campeonatoElegido->id }}">
                    <div class="selected-championship mb-4">
                        <span class="selected-championship-icon"><i class="bi bi-trophy-fill"></i></span>
                        <div class="flex-grow-1">
                            <div class="small text-uppercase fw-bold opacity-75">Te inscribirás en</div>
                            <div class="fs-5 fw-bold">{{ $campeonatoElegido->nombre }}</div>
                            <div class="small opacity-75">
                                Inicio: {{ $campeonatoElegido->fecha_inicio->format('d/m/Y') }} · {{ $campeonatoElegido->formato_label }}
                            </div>
                        </div>
                        <i class="bi bi-check-circle-fill text-success fs-4" title="Campeonato seleccionado"></i>
                    </div>
                    <div class="form-text mb-3">Al guardar el equipo, la solicitud se enviará automáticamente al organizador.</div>
                @else
                    <div class="mb-3">
                        <label class="form-label">Campeonato (opcional)</label>
                        <select name="campeonato_id" class="form-select form-select-lg @error('campeonato_id') is-invalid @enderror">
                            <option value="">Lo elegiré más adelante</option>
                            @foreach($campeonatos as $c)
                                <option value="{{ $c->id }}" {{ old('campeonato_id') == $c->id ? 'selected' : '' }}>{{ $c->nombre }}</option>
                            @endforeach
                        </select>
                        @error('campeonato_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        <div class="form-text">Si eliges un campeonato, se enviará una solicitud de inscripción al organizador.</div>
                    </div>
                @endif
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Ciudad</label>
                        <input type="text" name="ciudad" class="form-control" value="{{ old('ciudad') }}" placeholder="Ej.: Sacaba">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">Escudo (opcional)</label>
                    <input type="file" name="escudo" class="form-control @error('escudo') is-invalid @enderror" accept="image/jpeg,image/png,image/webp">
                    @error('escudo')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    <div class="form-text">Sube una imagen JPG, PNG o WebP de hasta 2 MB.</div>
                </div>
                <div class="d-flex gap-3">
                    <button type="submit" class="btn btn-naranja">Guardar</button>
                    <a href="{{ $campeonatoElegido ? route('campeonatos.show', $campeonatoElegido) : url()->previous() }}" class="btn btn-borde">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>


@endsection
