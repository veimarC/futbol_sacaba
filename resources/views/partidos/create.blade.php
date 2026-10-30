{{--
    Vista para crear un partido.
    El organizador usa esta pantalla para programar encuentros entre equipos del mismo campeonato.
--}}
@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="contenedor-transparente animar-aparecer">
            <h2 class="fw-bold mb-4"><i class="bi bi-calendar-event me-2 text-accent"></i>Programar Partido</h2>
            <form method="POST" action="{{ route('partidos.store') }}">
                @csrf
                <p class="required-note"><span aria-hidden="true">*</span> Los campos marcados con asterisco son obligatorios.</p>

                <div class="mb-3">
                    <label class="form-label">Campeonato *</label>
                    <select name="campeonato_id" id="campeonato_id" class="form-select form-select-lg @error('campeonato_id') is-invalid @enderror" required>
                        <option value="">Selecciona el campeonato</option>
                        @foreach($campeonatos as $c)
                            <option value="{{ $c->id }}" {{ old('campeonato_id', $campeonatoSeleccionado) == $c->id ? 'selected' : '' }}>{{ $c->nombre }}</option>
                        @endforeach
                    </select>
                    @error('campeonato_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    <div class="form-text">Solo aparecen los campeonatos que puedes administrar.</div>
                </div>
                <div class="form-text mb-3">Selecciona dos equipos diferentes que estén aprobados en el campeonato.</div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Equipo Local *</label>
                        <select name="equipo_local_id" id="equipo_local_id" class="form-select @error('equipo_local_id') is-invalid @enderror" required>
                            <option value="">Elige un equipo</option>
                            @if($equipos->isNotEmpty())
                                @foreach($equipos as $eq)
                                    <option value="{{ $eq->id }}" {{ old('equipo_local_id') == $eq->id ? 'selected' : '' }}>{{ $eq->nombre }}</option>
                                @endforeach
                            @endif
                        </select>
                        @error('equipo_local_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Equipo Visita *</label>
                        <select name="equipo_visita_id" id="equipo_visita_id" class="form-select @error('equipo_visita_id') is-invalid @enderror" required>
                            <option value="">Elige un equipo</option>
                            @if($equipos->isNotEmpty())
                                @foreach($equipos as $eq)
                                    <option value="{{ $eq->id }}" {{ old('equipo_visita_id') == $eq->id ? 'selected' : '' }}>{{ $eq->nombre }}</option>
                                @endforeach
                            @endif
                        </select>
                        @error('equipo_visita_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Fecha *</label>
                        <input type="date" name="fecha" class="form-control @error('fecha') is-invalid @enderror" required value="{{ old('fecha') }}">
                        @error('fecha')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Hora *</label>
                        <input type="time" name="hora" class="form-control @error('hora') is-invalid @enderror" required value="{{ old('hora') }}">
                        @error('hora')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Lugar / Cancha *</label>
                    <input type="text" name="lugar" class="form-control @error('lugar') is-invalid @enderror" required value="{{ old('lugar') }}" placeholder="Ej: Estadio Municipal">
                    @error('lugar')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>

                <div class="d-flex gap-3">
                    <button type="submit" class="btn btn-naranja">Programar Partido</button>
                    {{-- ✅ CAMBIADO: Ahora vuelve siempre al campeonato --}}
                    <a href="{{ $campeonatoSeleccionado ? route('campeonatos.show', ['campeonato' => $campeonatoSeleccionado, 'seccion' => 'partidos']) : route('campeonatos.index') }}" class="btn btn-borde">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>



<script>
document.getElementById('campeonato_id').addEventListener('change', function(){
    const id = this.value;
    const local = document.getElementById('equipo_local_id');
    const visita = document.getElementById('equipo_visita_id');

    local.innerHTML = '<option value="">Elige un equipo</option>';
    visita.innerHTML = '<option value="">Elige un equipo</option>';

    if(id){
        fetch(`{{ url('/api/equipos-campeonato') }}/${id}`)
        .then(res => res.json())
        .then(data => {
            local.innerHTML = '<option value="">Elige un equipo</option>';
            visita.innerHTML = '<option value="">Elige un equipo</option>';
            data.forEach(eq => {
                local.innerHTML += `<option value="${eq.id}">${eq.nombre}</option>`;
                visita.innerHTML += `<option value="${eq.id}">${eq.nombre}</option>`;
            });
        });

    }
});
</script>
@endsection
