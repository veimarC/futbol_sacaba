{{--
    Vista para editar un campeonato.
    Permite cambiar datos generales y configuración del formato si el usuario tiene permisos.
--}}
@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="contenedor-transparente animar-aparecer">
            <h2 class="fw-bold mb-4"><i class="bi bi-pencil-square me-2 text-accent"></i>Editar Campeonato</h2>
            <form method="POST" action="{{ route('campeonatos.update', $campeonato) }}" enctype="multipart/form-data">
                @csrf @method('PUT')
                <p class="required-note"><span aria-hidden="true">*</span> Los campos marcados con asterisco son obligatorios.</p>
                @php
                    $categoriaEsPersonalizada = !array_key_exists($campeonato->categoria_edad, \App\Models\Campeonato::$categoriasEdad);
                @endphp

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nombre *</label>
                        <input type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre', $campeonato->nombre) }}" required>
                        @error('nombre')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha Inicio *</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control @error('fecha_inicio') is-invalid @enderror" value="{{ old('fecha_inicio', optional($campeonato->fecha_inicio)->format('Y-m-d')) }}" required>
                        @error('fecha_inicio')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha Fin</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" class="form-control @error('fecha_fin') is-invalid @enderror" value="{{ old('fecha_fin', optional($campeonato->fecha_fin)->format('Y-m-d')) }}">
                        @error('fecha_fin')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        <div class="form-text">Debe ser posterior a la fecha de inicio.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Límite de inscripción</label>
                        <input type="date" id="fecha_limite_inscripcion" name="fecha_limite_inscripcion" class="form-control @error('fecha_limite_inscripcion') is-invalid @enderror" value="{{ old('fecha_limite_inscripcion', optional($campeonato->fecha_limite_inscripcion)->format('Y-m-d')) }}">
                        @error('fecha_limite_inscripcion')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        <div class="form-text">Debe ser igual o anterior a la fecha de inicio.</div>
                    </div>
                    <div class="col-md-6 d-flex align-items-end pb-2">
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="inscripciones_abiertas" value="1" id="inscripciones_abiertas" {{ old('inscripciones_abiertas', $campeonato->inscripciones_abiertas) ? 'checked' : '' }}><label class="form-check-label" for="inscripciones_abiertas">Inscripciones abiertas</label></div>
                    </div>
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-12">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea id="descripcion" name="descripcion" rows="3" class="form-control @error('descripcion') is-invalid @enderror">{{ old('descripcion', $campeonato->descripcion) }}</textarea>
                        @error('descripcion')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="col-12">
                        <label for="portada" class="form-label">Imagen de portada <span class="opacity-75 fw-normal">(opcional)</span></label>
                        <div class="championship-image-picker">
                            @php
                                $portadaUrl = $campeonato->portada_url;
                            @endphp
                            <div class="championship-image-preview {{ $portadaUrl ? 'has-image' : '' }}" id="portada-preview">
                                @if($portadaUrl)<img src="{{ $portadaUrl }}" alt="Portada actual">@else<i class="bi bi-image"></i><span>Sin portada personalizada</span>@endif
                            </div>
                            <div class="flex-grow-1">
                                <input id="portada" type="file" name="portada" class="form-control @error('portada') is-invalid @enderror" accept="image/jpeg,image/png,image/webp">
                                @error('portada')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                <div class="form-text">Déjalo vacío para conservar la imagen actual. JPG, PNG o WebP, máximo 4 MB.</div>
                                @if($portadaUrl)<div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="eliminar_portada" value="1" id="eliminar_portada"><label class="form-check-label" for="eliminar_portada">Quitar la portada actual y usar el diseño predeterminado</label></div>@endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 p-3 rounded-3 form-panel-accent">
                    <h6 class="mb-1"><i class="bi bi-people-fill text-warning me-2"></i>Reglas de jugadores por equipo</h6>
                    <p class="small opacity-75 mb-3">La modalidad propone valores. Puedes cambiarlos según el reglamento del campeonato.</p>
                    <div class="row g-3">
                        {{-- La modalidad necesita más ancho para que "Fútbol 11" no se recorte. --}}
                        <div class="col-md-4">
                            <label for="modalidad" class="form-label">Modalidad *</label>
                            <select id="modalidad" name="modalidad" class="form-select @error('modalidad') is-invalid @enderror" required>
                                @foreach([11, 9, 8, 7, 6, 5] as $cantidad)
                                    <option value="{{ $cantidad }}" {{ (int) old('modalidad', $campeonato->modalidad) === $cantidad ? 'selected' : '' }}>Fútbol {{ $cantidad }}</option>
                                @endforeach
                            </select>
                            @error('modalidad')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-2">
                            <label for="jugadores_cancha" class="form-label">En cancha *</label>
                            <input id="jugadores_cancha" name="jugadores_cancha" type="number" min="1" max="50" required class="form-control @error('jugadores_cancha') is-invalid @enderror" value="{{ old('jugadores_cancha', $campeonato->jugadores_cancha) }}">
                            @error('jugadores_cancha')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-3">
                            <label for="min_jugadores_equipo" class="form-label">Mínimo *</label>
                            <input id="min_jugadores_equipo" name="min_jugadores_equipo" type="number" min="1" max="100" required class="form-control @error('min_jugadores_equipo') is-invalid @enderror" value="{{ old('min_jugadores_equipo', $campeonato->min_jugadores_equipo) }}">
                            @error('min_jugadores_equipo')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-3">
                            <label for="max_jugadores_equipo" class="form-label">Máximo *</label>
                            <input id="max_jugadores_equipo" name="max_jugadores_equipo" type="number" min="1" max="100" required class="form-control @error('max_jugadores_equipo') is-invalid @enderror" value="{{ old('max_jugadores_equipo', $campeonato->max_jugadores_equipo) }}">
                            @error('max_jugadores_equipo')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>

                <div class="mt-3 p-3 rounded-3 form-panel-dark">
                    <h6 class="mb-1"><i class="bi bi-sliders text-warning me-2"></i>Perfil deportivo</h6>
                    <p class="small opacity-75 mb-3">La edad se comprobará en la fecha de inicio del campeonato.</p>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="categoria_edad" class="form-label">Categoría por edad *</label>
                            <select id="categoria_edad" name="categoria_edad" class="form-select @error('categoria_edad') is-invalid @enderror" required>
                                @foreach(\App\Models\Campeonato::$categoriasEdad as $valor => $etiqueta)
                                    <option value="{{ $valor }}" {{ old('categoria_edad', $categoriaEsPersonalizada ? 'personalizada' : $campeonato->categoria_edad) === $valor ? 'selected' : '' }}>{{ $etiqueta }}</option>
                                @endforeach
                                <option value="personalizada" {{ old('categoria_edad', $categoriaEsPersonalizada ? 'personalizada' : '') === 'personalizada' ? 'selected' : '' }}>Crear otra categoría...</option>
                            </select>
                            @error('categoria_edad')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-8" id="categoria-personalizada-wrap">
                            <label for="categoria_personalizada" class="form-label">Nombre de la categoría *</label>
                            <input id="categoria_personalizada" name="categoria_personalizada" class="form-control @error('categoria_personalizada') is-invalid @enderror" maxlength="80" value="{{ old('categoria_personalizada', $categoriaEsPersonalizada ? $campeonato->categoria_edad : '') }}">
                            @error('categoria_personalizada')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-3">
                            <label for="edad_min" class="form-label">Edad mínima</label>
                            <input id="edad_min" name="edad_min" type="number" min="0" max="99" class="form-control @error('edad_min') is-invalid @enderror" value="{{ old('edad_min', $campeonato->edad_min) }}" placeholder="Sin mínimo">
                            @error('edad_min')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-3">
                            <label for="edad_max" class="form-label">Edad máxima</label>
                            <input id="edad_max" name="edad_max" type="number" min="0" max="99" class="form-control @error('edad_max') is-invalid @enderror" value="{{ old('edad_max', $campeonato->edad_max) }}" placeholder="Sin máximo">
                            @error('edad_max')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="sexo" class="form-label">Participación *</label>
                            <select id="sexo" name="sexo" class="form-select @error('sexo') is-invalid @enderror" required>
                                @foreach(\App\Models\Campeonato::$sexos as $valor => $etiqueta)
                                    <option value="{{ $valor }}" {{ old('sexo', $campeonato->sexo) === $valor ? 'selected' : '' }}>{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                            @error('sexo')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>

                <div class="row g-3 mt-3">
                    <div class="col-12">
                        <label class="form-label">Formato de Competición</label>
                        @if(auth()->check() && (auth()->user()->rol === \App\Models\User::ROLE_DESARROLLADOR || (auth()->user()->rol === \App\Models\User::ROLE_ORGANIZADOR && $campeonato->usuario_creador == auth()->id())))
                            <select id="formato" name="formato" class="form-select @error('formato') is-invalid @enderror">
                                <option value="">-- Seleccionar formato --</option>
                                @foreach(\App\Models\Campeonato::$formatos as $key => $label)
                                    <option value="{{ $key }}" {{ (old('formato', $campeonato->formato) == $key) ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            <small class="text-white-75" id="format-description">La configuración se calculará cuando generes el fixture con los equipos aprobados.</small>
                            @error('formato')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        @else
                            <input type="text" class="form-control" value="{{ $campeonato->formato_label }}" disabled>
                        @endif
                    </div>
                </div>

                <div class="alert alert-info mt-4 mb-0">
                    <strong>Configuración automática:</strong> al generar el fixture, la plataforma usará la cantidad real de equipos aprobados para calcular vueltas, grupos, clasificados y llaves.
                </div>

                <div class="d-flex gap-3 mt-4">
                    <button type="submit" class="btn btn-naranja">Guardar Cambios</button>
                    <a href="{{ route('campeonatos.show', $campeonato) }}" class="btn btn-borde">Cancelar</a>
                </div>
            </form>

            @if(auth()->check() && (auth()->user()->rol === \App\Models\User::ROLE_DESARROLLADOR || (auth()->user()->rol === \App\Models\User::ROLE_ORGANIZADOR && $campeonato->usuario_creador == auth()->id())))
            <script>
                const formatoSelect = document.getElementById('formato');
                const formatoDescripcion = document.getElementById('format-description');
                const descripcionesFormato = {
                    liga: 'Todos juegan contra todos. El campeón es quien suma más puntos.',
                    grupos_eliminacion: 'Primero se juega por grupos y luego los clasificados avanzan a las llaves.',
                    eliminacion: 'El perdedor queda eliminado. Ideal para campeonatos cortos o relámpago.'
                };

                function updateFormatoConfigVisibility() {
                    const selected = formatoSelect ? formatoSelect.value : '';
                    if (formatoDescripcion) {
                        formatoDescripcion.textContent = descripcionesFormato[selected]
                            ?? 'Selecciona un formato para ver una explicación sencilla.';
                    }
                }
                formatoSelect?.addEventListener('change', updateFormatoConfigVisibility);
                window.addEventListener('DOMContentLoaded', updateFormatoConfigVisibility);
            </script>
            @endif
            <script>
                const modalidadPlantilla = document.getElementById('modalidad');
                const jugadoresCancha = document.getElementById('jugadores_cancha');
                const minJugadores = document.getElementById('min_jugadores_equipo');
                const maxJugadores = document.getElementById('max_jugadores_equipo');
                const categoriaSelect = document.getElementById('categoria_edad');
                const categoriaWrap = document.getElementById('categoria-personalizada-wrap');
                const categoriaPersonalizada = document.getElementById('categoria_personalizada');
                const edadMin = document.getElementById('edad_min');
                const edadMax = document.getElementById('edad_max');
                const sugerenciasPlantilla = {
                    11:{cancha:11,minimo:15,maximo:25}, 9:{cancha:9,minimo:13,maximo:20},
                    8:{cancha:8,minimo:12,maximo:18}, 7:{cancha:7,minimo:10,maximo:15},
                    6:{cancha:6,minimo:8,maximo:12}, 5:{cancha:5,minimo:7,maximo:12}
                };
                function validarPlantilla() {
                    minJugadores.min = jugadoresCancha.value || 1;
                    maxJugadores.min = minJugadores.value || 1;
                    minJugadores.setCustomValidity(Number(minJugadores.value) < Number(jugadoresCancha.value) ? 'El mínimo no puede ser menor que los jugadores en cancha.' : '');
                    maxJugadores.setCustomValidity(Number(maxJugadores.value) < Number(minJugadores.value) ? 'El máximo debe ser mayor o igual al mínimo.' : '');
                }
                modalidadPlantilla?.addEventListener('change', () => {
                    const sugerencia = sugerenciasPlantilla[modalidadPlantilla.value];
                    jugadoresCancha.value = sugerencia.cancha;
                    minJugadores.value = sugerencia.minimo;
                    maxJugadores.value = sugerencia.maximo;
                    validarPlantilla();
                });
                [jugadoresCancha,minJugadores,maxJugadores].forEach(field => field?.addEventListener('input', validarPlantilla));
                const edadesSugeridas = {sub_7:[null,6],sub_9:[null,8],sub_11:[null,10],sub_13:[null,12],sub_15:[null,14],sub_17:[null,16],sub_20:[null,19],libre:[null,null],mas_30:[30,null],mas_35:[35,null],mas_40:[40,null],mas_45:[45,null],mas_50:[50,null]};
                function actualizarPerfil(aplicarEdad = false) {
                    const categoriaCustom = categoriaSelect.value === 'personalizada';
                    categoriaWrap.classList.toggle('d-none', !categoriaCustom);
                    categoriaPersonalizada.required = categoriaCustom;
                    if (aplicarEdad && edadesSugeridas[categoriaSelect.value]) {
                        const [minimo,maximo] = edadesSugeridas[categoriaSelect.value];
                        edadMin.value = minimo ?? '';
                        edadMax.value = maximo ?? '';
                    }
                    edadMax.min = edadMin.value || 0;
                    edadMax.setCustomValidity(edadMin.value !== '' && edadMax.value !== '' && Number(edadMax.value) < Number(edadMin.value) ? 'La edad máxima debe ser mayor o igual a la edad mínima.' : '');
                }
                categoriaSelect?.addEventListener('change', () => actualizarPerfil(true));
                edadMin?.addEventListener('input', () => actualizarPerfil(false));
                edadMax?.addEventListener('input', () => actualizarPerfil(false));
                validarPlantilla();
                actualizarPerfil(false);
            </script>
        </div>
    </div>
</div>
@endsection
