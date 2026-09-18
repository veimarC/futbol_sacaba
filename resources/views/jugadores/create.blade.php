{{--
    Vista para registrar un nuevo jugador.
    El delegado agrega jugadores a su equipo, y el organizador puede verificar la solicitud.
--}}
@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="contenedor-transparente">
            <h4 class="mb-3">Agregar Jugador</h4>

            @if($limitePlantilla)
                @php
                    $cuposDisponibles = max(0, $limitePlantilla->max_jugadores_equipo - $jugadoresRegistrados);
                    $limiteAlcanzado = $cuposDisponibles === 0;
                @endphp
                <div class="alert {{ $limiteAlcanzado ? 'alert-warning' : 'alert-info' }}">
                    <div class="d-flex justify-content-between align-items-center gap-3">
                        <div>
                            <strong>{{ $limiteAlcanzado ? 'Límite de jugadores alcanzado' : 'Cupos de la plantilla' }}</strong>
                            <div class="small">{{ $limitePlantilla->nombre }} permite entre {{ $limitePlantilla->min_jugadores_equipo }} y {{ $limitePlantilla->max_jugadores_equipo }} jugadores.</div>
                        </div>
                        <span class="badge bg-dark fs-6">{{ $jugadoresRegistrados }}/{{ $limitePlantilla->max_jugadores_equipo }}</span>
                    </div>
                    @if(!$limiteAlcanzado)<div class="small mt-2">Quedan {{ $cuposDisponibles }} {{ $cuposDisponibles === 1 ? 'cupo disponible' : 'cupos disponibles' }}.</div>@endif
                </div>
            @else
                @php $limiteAlcanzado = false; @endphp
            @endif

            @if($reglasCampeonatos->isNotEmpty())
                <div class="rule-summary mb-3">
                    <strong><i class="bi bi-shield-check me-1"></i> Reglas que debe cumplir</strong>
                    @foreach($reglasCampeonatos as $regla)
                        <div class="small mt-2">
                            <span class="fw-semibold">{{ $regla->nombre }}:</span>
                            {{ $regla->categoria_edad_label }}
                            @if($regla->edad_min !== null && $regla->edad_max !== null)
                                ({{ $regla->edad_min }} a {{ $regla->edad_max }} años)
                            @elseif($regla->edad_min !== null)
                                (desde {{ $regla->edad_min }} años)
                            @elseif($regla->edad_max !== null)
                                (hasta {{ $regla->edad_max }} años)
                            @endif
                            · {{ $regla->sexo_label }}
                        </div>
                    @endforeach
                </div>
            @endif

            <form action="{{ route('jugadores.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <p class="required-note"><span aria-hidden="true">*</span> Los campos marcados con asterisco son obligatorios.</p>

                <div class="mb-3">
                    <label class="form-label">Nombre *</label>
                    <input type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre') }}" placeholder="Ej.: Carlos" required>
                    @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Apellido</label>
                    <input type="text" name="apellido" class="form-control @error('apellido') is-invalid @enderror" value="{{ old('apellido') }}" placeholder="Ej.: Mendoza">
                    @error('apellido') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">CI (cédula de identidad)</label>
                    <input type="text" name="dni" class="form-control @error('dni') is-invalid @enderror" value="{{ old('dni') }}" placeholder="Ej.: 8456721">
                    @error('dni') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">Ayuda a evitar que el mismo jugador se registre dos veces en un campeonato.</div>
                </div>

                <x-whatsapp-field />

                <div class="form-check mb-3">
                    <input type="hidden" name="whatsapp_notifications" value="0">
                    <input id="whatsapp_notifications" name="whatsapp_notifications" value="1" type="checkbox" class="form-check-input" {{ old('whatsapp_notifications') ? 'checked' : '' }}>
                    <label for="whatsapp_notifications" class="form-check-label">
                        Confirmo que el jugador autorizó recibir por WhatsApp avisos de sus partidos de Fútbol Sacaba.
                    </label>
                    <div class="form-text">Si no existe autorización, deja esta casilla sin marcar. No se enviarán mensajes automáticos.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Fecha de Nacimiento</label>
                    <input type="date" name="fecha_nacimiento" class="form-control @error('fecha_nacimiento') is-invalid @enderror" value="{{ old('fecha_nacimiento') }}" max="{{ now()->format('Y-m-d') }}">
                    @error('fecha_nacimiento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">Se utiliza para comprobar la categoría de edad al inicio del campeonato.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Sexo *</label>
                    <select name="sexo" class="form-select @error('sexo') is-invalid @enderror" required>
                        <option value="">Selecciona una opción</option>
                        <option value="masculino" {{ old('sexo') === 'masculino' ? 'selected' : '' }}>Masculino</option>
                        <option value="femenino" {{ old('sexo') === 'femenino' ? 'selected' : '' }}>Femenino</option>
                    </select>
                    @error('sexo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Posición</label>
                    <input type="text" name="posicion" class="form-control @error('posicion') is-invalid @enderror" value="{{ old('posicion') }}" placeholder="Ej.: Delantero">
                    @error('posicion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Equipo</label>
                    @if($equipos->isEmpty())
                        <div class="alert alert-warning">No tienes equipos disponibles para registrar jugadores. Si eres administrador general, asegúrate de que existan equipos en el campeonato o crea equipos primero.</div>
                    @elseif(!empty($equipoSeleccionado))
                        @php $equipoActual = $equipos->first(); @endphp
                        <input type="hidden" name="equipo_id" value="{{ $equipoActual->id }}">
                        <input type="text" class="form-control bg-light text-dark" value="{{ $equipoActual->nombre }}@if($equipoActual->campeonatos->isNotEmpty()) - {{ $equipoActual->campeonatos->pluck('nombre')->join(', ') }}@endif" readonly>
                    @else
                        <select name="equipo_id" class="form-select @error('equipo_id') is-invalid @enderror" required>
                            @foreach($equipos as $e)
                                <option value="{{ $e->id }}" {{ (old('equipo_id') == $e->id) ? 'selected' : '' }}>{{ $e->nombre }} @if($e->campeonatos->isNotEmpty()) - {{ $e->campeonatos->pluck('nombre')->join(', ') }}@endif</option>
                            @endforeach
                        </select>
                        @error('equipo_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    @endif
                </div>

                <div class="mb-3">
                    <label class="form-label">Foto (opcional)</label>
                    <input type="file" name="foto" class="form-control @error('foto') is-invalid @enderror" accept="image/jpeg,image/png">
                    @error('foto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">Se optimizará en Cloudinary. Si no subes foto, se usará un icono por defecto.</div>
                </div>

                <div class="d-grid">
                    <button class="btn btn-naranja" {{ $limiteAlcanzado ? 'disabled' : '' }}>{{ $limiteAlcanzado ? 'Plantilla completa' : 'Guardar jugador' }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
