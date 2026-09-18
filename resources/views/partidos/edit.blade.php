{{--
    Vista para editar partido.
    Permite cambiar fecha, hora, cancha, árbitro y equipos antes de que el partido sea jugado.
--}}
@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="contenedor-transparente animar-aparecer">
            <h2 class="fw-bold mb-4"><i class="bi bi-pencil-square me-2 text-accent"></i>Editar Partido</h2>
            <form method="POST" action="{{ route('partidos.update', $partido) }}">
                @csrf @method('PUT')
                <p class="required-note"><span aria-hidden="true">*</span> Los campos marcados con asterisco son obligatorios.</p>

                <div class="mb-3">
                    <label class="form-label">Campeonato *</label>
                    <select name="campeonato_id" id="campeonato_id" class="form-select form-select-lg @error('campeonato_id') is-invalid @enderror" required>
                        <option value="">Selecciona el campeonato</option>
                        @foreach($campeonatos as $c)
                            <option value="{{ $c->id }}" {{ old('campeonato_id', $partido->campeonato_id) == $c->id ? 'selected' : '' }}>{{ $c->nombre }}</option>
                        @endforeach
                    </select>
                    @error('campeonato_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>
                <div class="form-text mb-3">El equipo local y el visitante deben ser diferentes.</div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Equipo Local *</label>
                        <select name="equipo_local_id" id="equipo_local_id" class="form-select @error('equipo_local_id') is-invalid @enderror" required>
                            <option value="">Elige un equipo</option>
                            @foreach($equipos as $eq)
                                <option value="{{ $eq->id }}" {{ old('equipo_local_id', $partido->equipo_local_id) == $eq->id ? 'selected' : '' }}>{{ $eq->nombre }}</option>
                            @endforeach
                        </select>
                        @error('equipo_local_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Equipo Visita *</label>
                        <select name="equipo_visita_id" id="equipo_visita_id" class="form-select @error('equipo_visita_id') is-invalid @enderror" required>
                            <option value="">Elige un equipo</option>
                            @foreach($equipos as $eq)
                                <option value="{{ $eq->id }}" {{ old('equipo_visita_id', $partido->equipo_visita_id) == $eq->id ? 'selected' : '' }}>{{ $eq->nombre }}</option>
                            @endforeach
                        </select>
                        @error('equipo_visita_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Fecha *</label>
                        <input type="date" name="fecha" class="form-control @error('fecha') is-invalid @enderror" required
                               value="{{ old('fecha', \Carbon\Carbon::parse($partido->fecha)->format('Y-m-d')) }}">
                        @error('fecha')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Hora *</label>
                        <input type="time" name="hora" class="form-control @error('hora') is-invalid @enderror" required
                               value="{{ old('hora', \Carbon\Carbon::parse($partido->hora)->format('H:i')) }}">
                        @error('hora')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Árbitro (opcional)</label>
                    <select name="arbitro_id" id="arbitro_id" class="form-select @error('arbitro_id') is-invalid @enderror">
                        <option value="">Sin asignar</option>
                        @foreach($arbitros as $a)
                            <option value="{{ $a->id }}" {{ old('arbitro_id', $partido->arbitro_id) == $a->id ? 'selected' : '' }}>{{ $a->name }} ({{ $a->email }})</option>
                        @endforeach
                    </select>
                    @error('arbitro_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Puedes dejarlo sin asignar y elegir un árbitro después.</div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Lugar / Cancha *</label>
                    <input type="text" name="lugar" class="form-control @error('lugar') is-invalid @enderror" required value="{{ old('lugar', $partido->cancha) }}" placeholder="Ej: Estadio Municipal">
                    @error('lugar')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>

                <div class="d-flex gap-3">
                    <button type="submit" class="btn btn-naranja">Guardar Cambios</button>
                    <a href="{{ route('campeonatos.show', $partido->campeonato_id) }}" class="btn btn-borde">Cancelar</a>
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
    const arbitro = document.getElementById('arbitro_id');
    local.innerHTML = '<option value="">Elige un equipo</option>';
    visita.innerHTML = '<option value="">Elige un equipo</option>';
    arbitro.innerHTML = '<option value="">Sin asignar</option>';
    if(id){
        fetch(`{{ url('/api/equipos-campeonato') }}/${id}`)
        .then(res => res.json())
        .then(data => {
            data.forEach(eq => {
                local.innerHTML += `<option value="${eq.id}">${eq.nombre}</option>`;
                visita.innerHTML += `<option value="${eq.id}">${eq.nombre}</option>`;
            });
        });
        fetch(`{{ url('/api/arbitros-campeonato') }}/${id}`)
        .then(res => res.json())
        .then(data => {
            arbitro.innerHTML = '<option value="">Sin asignar</option>';
            data.forEach(a => arbitro.innerHTML += `<option value="${a.id}">${a.name} (${a.email})</option>`);
        });
    }
});
</script>
@endsection
