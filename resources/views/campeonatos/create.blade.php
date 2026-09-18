{{--
    Vista para crear un campeonato.
    Muestra un asistente paso a paso para seleccionar el formato,
    configurar el campeonato y guardar los datos básicos.
--}}
@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-xl-8 col-lg-10">
        <div class="contenedor-transparente animar-aparecer">
            <div class="mb-4">
                <h2 class="fw-bold">Crear Campeonato</h2>
                <p class="text-white-75">Sigue el asistente paso a paso para crear un campeonato amateur de forma clara y rápida.</p>
            </div>

            <div class="mb-4">
                <div class="d-flex align-items-center justify-content-between gap-3 flex-column flex-md-row">
                    <div>
                        <span class="badge bg-warning text-dark">Paso <span id="wizard-step-number">1</span> de 4</span>
                        <h5 class="mt-3 mb-1" id="wizard-step-title">Datos del campeonato</h5>
                        <p class="text-white-75 mb-0" id="wizard-step-description">Completa la información básica para iniciar el torneo.</p>
                    </div>
                    <div class="progress flex-grow-1 wizard-progress">
                        <div class="progress-bar bg-white" id="wizard-progress" role="progressbar" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('campeonatos.store') }}" id="campeonato-wizard-form" enctype="multipart/form-data">
                @csrf
                <p class="required-note"><strong>*</strong> Campos obligatorios. Puedes completar los demás más adelante.</p>
                <div id="wizard-error" class="alert alert-danger d-none" role="alert" aria-live="polite">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <span>Revisa los campos marcados antes de continuar.</span>
                </div>

                <div class="wizard-step" data-step="1">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nombre" class="form-label">Nombre del campeonato *</label>
                            <input type="text" id="nombre" name="nombre" class="form-control form-control-lg @error('nombre') is-invalid @enderror" required value="{{ old('nombre') }}">
                            @error('nombre') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="fecha_inicio" class="form-label">Fecha de inicio *</label>
                            <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control @error('fecha_inicio') is-invalid @enderror" required value="{{ old('fecha_inicio') }}">
                            @error('fecha_inicio') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            <div class="form-text">Día en que comenzarán los partidos.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="fecha_fin" class="form-label">Fecha de fin</label>
                            <input type="date" id="fecha_fin" name="fecha_fin" class="form-control @error('fecha_fin') is-invalid @enderror" value="{{ old('fecha_fin') }}">
                            @error('fecha_fin') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            <div class="form-text">Debe ser posterior a la fecha de inicio.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="fecha_limite_inscripcion" class="form-label">Límite de inscripción</label>
                            <input type="date" id="fecha_limite_inscripcion" name="fecha_limite_inscripcion" class="form-control @error('fecha_limite_inscripcion') is-invalid @enderror" value="{{ old('fecha_limite_inscripcion') }}">
                            @error('fecha_limite_inscripcion') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                            <div class="form-text">Último día para recibir equipos. Debe ser igual o anterior al inicio.</div>
                        </div>
                        <div class="col-md-6 d-flex align-items-end pb-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="inscripciones_abiertas" name="inscripciones_abiertas" value="1" {{ old('inscripciones_abiertas', true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="inscripciones_abiertas">Permitir solicitudes de equipos</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea id="descripcion" name="descripcion" rows="3" class="form-control @error('descripcion') is-invalid @enderror">{{ old('descripcion') }}</textarea>
                            @error('descripcion') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-12">
                            <label for="portada" class="form-label">Imagen de portada <span class="opacity-75 fw-normal">(opcional)</span></label>
                            <div class="championship-image-picker">
                                <div class="championship-image-preview" id="portada-preview"><i class="bi bi-image"></i><span>La vista previa aparecerá aquí</span></div>
                                <div class="flex-grow-1">
                                    <input id="portada" type="file" name="portada" class="form-control @error('portada') is-invalid @enderror" accept="image/jpeg,image/png,image/webp">
                                    @error('portada') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                    <div class="form-text">Usa una imagen horizontal JPG, PNG o WebP. Máximo 4 MB. Si no eliges una, usaremos una portada deportiva.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="wizard-step d-none" data-step="2">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="formato" class="form-label">Formato de competición *</label>
                            <select id="formato" name="formato" class="form-select @error('formato') is-invalid @enderror" required>
                                <option value="">-- Seleccionar formato --</option>
                                @foreach(\App\Models\Campeonato::$formatos as $key => $label)
                                    <option value="{{ $key }}" {{ old('formato') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('formato') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            <div class="form-text text-white-75" id="format-description">Elige una opción para ver una explicación sencilla.</div>
                        </div>
                        <div class="col-12">
                            <div class="p-3 rounded-3 form-panel">
                                <h6 class="text-white">¿Qué formato necesitas?</h6>
                                <p class="text-white-75 mb-0">Selecciona el tipo de torneo. Si no estás seguro, usa "Liga" para un campeonato simple.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="wizard-step d-none" data-step="3">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="p-3 rounded-3 form-panel">
                                <h6 class="text-white">Reglas de participación</h6>
                                <p class="text-white-75 mb-0">Define la plantilla y el perfil de los jugadores. La estructura del torneo se calculará después.</p>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="p-3 rounded-3 form-panel-accent">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <i class="bi bi-people-fill fs-4 text-warning"></i>
                                    <div>
                                        <h6 class="mb-0">Plantilla de cada equipo</h6>
                                        <small class="opacity-75">La modalidad propone valores, pero puedes adaptarlos a tu reglamento.</small>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    {{-- La modalidad necesita más ancho para que "Fútbol 11" no se recorte. --}}
                                    <div class="col-md-4">
                                        <label for="modalidad" class="form-label">Modalidad *</label>
                                        <select id="modalidad" name="modalidad" class="form-select @error('modalidad') is-invalid @enderror" required>
                                            @foreach([11, 9, 8, 7, 6, 5] as $cantidad)
                                                <option value="{{ $cantidad }}" {{ (int) old('modalidad', 11) === $cantidad ? 'selected' : '' }}>Fútbol {{ $cantidad }}</option>
                                            @endforeach
                                        </select>
                                        @error('modalidad')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                    </div>
                                    <div class="col-md-2">
                                        <label for="jugadores_cancha" class="form-label">En cancha *</label>
                                        <input id="jugadores_cancha" name="jugadores_cancha" type="number" min="1" max="50" class="form-control @error('jugadores_cancha') is-invalid @enderror" required value="{{ old('jugadores_cancha', 11) }}">
                                        @error('jugadores_cancha')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                    </div>
                                    <div class="col-md-3">
                                        <label for="min_jugadores_equipo" class="form-label">Mínimo por equipo *</label>
                                        <input id="min_jugadores_equipo" name="min_jugadores_equipo" type="number" min="1" max="100" class="form-control @error('min_jugadores_equipo') is-invalid @enderror" required value="{{ old('min_jugadores_equipo', 15) }}">
                                        @error('min_jugadores_equipo')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                    </div>
                                    <div class="col-md-3">
                                        <label for="max_jugadores_equipo" class="form-label">Máximo por equipo *</label>
                                        <input id="max_jugadores_equipo" name="max_jugadores_equipo" type="number" min="1" max="100" class="form-control @error('max_jugadores_equipo') is-invalid @enderror" required value="{{ old('max_jugadores_equipo', 25) }}">
                                        @error('max_jugadores_equipo')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                                <div class="form-text mt-2">El mínimo debe alcanzar para formar el equipo en cancha y el máximo nunca puede ser menor al mínimo.</div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="p-3 rounded-3 form-panel-dark">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <i class="bi bi-sliders fs-4 text-warning"></i>
                                    <div>
                                        <h6 class="mb-0">Perfil deportivo</h6>
                                        <small class="opacity-75">Estas reglas se comprobarán al registrar cada jugador.</small>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="categoria_edad" class="form-label">Categoría por edad *</label>
                                        <select id="categoria_edad" name="categoria_edad" class="form-select @error('categoria_edad') is-invalid @enderror" required>
                                            @foreach(\App\Models\Campeonato::$categoriasEdad as $valor => $etiqueta)
                                                <option value="{{ $valor }}" {{ old('categoria_edad', 'libre') === $valor ? 'selected' : '' }}>{{ $etiqueta }}</option>
                                            @endforeach
                                            <option value="personalizada" {{ old('categoria_edad') === 'personalizada' ? 'selected' : '' }}>Crear otra categoría...</option>
                                        </select>
                                        @error('categoria_edad')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                    </div>
                                    <div class="col-md-8 d-none" id="categoria-personalizada-wrap">
                                        <label for="categoria_personalizada" class="form-label">Nombre de la categoría *</label>
                                        <input id="categoria_personalizada" name="categoria_personalizada" class="form-control @error('categoria_personalizada') is-invalid @enderror" maxlength="80" value="{{ old('categoria_personalizada') }}" placeholder="Ej.: Jóvenes de 18 a 23 años">
                                        @error('categoria_personalizada')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                    </div>
                                    <div class="col-md-3">
                                        <label for="edad_min" class="form-label">Edad mínima</label>
                                        <input id="edad_min" name="edad_min" type="number" min="0" max="99" class="form-control @error('edad_min') is-invalid @enderror" value="{{ old('edad_min') }}" placeholder="Sin mínimo">
                                        @error('edad_min')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                    </div>
                                    <div class="col-md-3">
                                        <label for="edad_max" class="form-label">Edad máxima</label>
                                        <input id="edad_max" name="edad_max" type="number" min="0" max="99" class="form-control @error('edad_max') is-invalid @enderror" value="{{ old('edad_max') }}" placeholder="Sin máximo">
                                        @error('edad_max')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label for="sexo" class="form-label">Participación *</label>
                                        <select id="sexo" name="sexo" class="form-select @error('sexo') is-invalid @enderror" required>
                                            @foreach(\App\Models\Campeonato::$sexos as $valor => $etiqueta)
                                                <option value="{{ $valor }}" {{ old('sexo', 'mixto') === $valor ? 'selected' : '' }}>{{ $etiqueta }}</option>
                                            @endforeach
                                        </select>
                                        @error('sexo')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                                <div class="form-text mt-2">Las edades se calculan en la fecha de inicio. Deja un límite vacío cuando no corresponda.</div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="alert alert-info mb-0">
                                <strong>Configuración automática según las inscripciones.</strong>
                                Cuando apruebes los equipos y pulses «Generar fixture», la plataforma calculará las vueltas, los grupos, la distribución, los clasificados y la ronda inicial. No necesitas definir esos datos ahora.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="wizard-step d-none" data-step="4">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="p-3 rounded-3 form-panel">
                                <h6 class="text-white">Resumen rápido</h6>
                                <p class="text-white-75">Revisa los datos y guarda el campeonato. Luego podrás inscribir equipos y generar el fixture apropiado.</p>
                            </div>
                        </div>
                        <div class="col-12">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item bg-transparent text-white border-bottom">Nombre: <strong id="summary-nombre"></strong></li>
                                <li class="list-group-item bg-transparent text-white border-bottom">Fechas: <strong id="summary-fechas"></strong></li>
                                <li class="list-group-item bg-transparent text-white border-bottom">Formato: <strong id="summary-formato"></strong></li>
                                <li class="list-group-item bg-transparent text-white border-bottom">Plantilla: <strong id="summary-plantilla"></strong></li>
                                <li class="list-group-item bg-transparent text-white border-bottom">Perfil: <strong id="summary-perfil"></strong></li>
                                <li class="list-group-item bg-transparent text-white">Notas: <span class="text-white-75" id="summary-notas"></span></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-4">
                    <button type="button" class="btn btn-borde" id="wizard-prev">Anterior</button>
                    <button type="button" class="btn btn-naranja" id="wizard-next">Siguiente</button>
                    <button type="submit" class="btn btn-naranja d-none" id="wizard-submit">Guardar Campeonato</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const steps = ['Datos del campeonato', 'Seleccionar formato', 'Configuración', 'Confirmar campeonato'];
    const descriptions = [
        'Completa la información básica del campeonato.',
        'Elige el formato que mejor se adapte a tu torneo.',
        'Ajusta solo los datos necesarios para este formato.',
        'Revisa y guarda el campeonato para continuar con la inscripción de equipos.'
    ];

    let currentStep = 1;
    const totalSteps = 4;
    const stepElements = document.querySelectorAll('.wizard-step');
    const nextButton = document.getElementById('wizard-next');
    const prevButton = document.getElementById('wizard-prev');
    const submitButton = document.getElementById('wizard-submit');
    const progressBar = document.getElementById('wizard-progress');
    const stepNumber = document.getElementById('wizard-step-number');
    const stepTitle = document.getElementById('wizard-step-title');
    const stepDescription = document.getElementById('wizard-step-description');
    const formatoSelect = document.getElementById('formato');
    const formatDescription = document.getElementById('format-description');
    const fechaInicio = document.getElementById('fecha_inicio');
    const fechaFin = document.getElementById('fecha_fin');
    const fechaLimite = document.getElementById('fecha_limite_inscripcion');
    const modalidad = document.getElementById('modalidad');
    const jugadoresCancha = document.getElementById('jugadores_cancha');
    const minJugadores = document.getElementById('min_jugadores_equipo');
    const maxJugadores = document.getElementById('max_jugadores_equipo');
    const categoriaEdad = document.getElementById('categoria_edad');
    const categoriaPersonalizadaWrap = document.getElementById('categoria-personalizada-wrap');
    const categoriaPersonalizada = document.getElementById('categoria_personalizada');
    const edadMin = document.getElementById('edad_min');
    const edadMax = document.getElementById('edad_max');
    const sexoSelect = document.getElementById('sexo');
    const wizardForm = document.getElementById('campeonato-wizard-form');
    const wizardError = document.getElementById('wizard-error');

    function showStep(step) {
        stepElements.forEach(el => {
            el.classList.toggle('d-none', Number(el.dataset.step) !== step);
        });
        stepNumber.textContent = step;
        stepTitle.textContent = steps[step - 1];
        stepDescription.textContent = descriptions[step - 1];
        progressBar.style.width = `${(step / totalSteps) * 100}%`;

        prevButton.classList.toggle('d-none', step === 1);
        nextButton.classList.toggle('d-none', step === totalSteps);
        submitButton.classList.toggle('d-none', step !== totalSteps);

        if (step === 4) {
            updateSummary();
        }
    }

    function updateFormatSettings() {
        const selected = formatoSelect ? formatoSelect.value : '';
        const descriptions = {
            liga: 'Todos juegan contra todos. El campeón es quien suma más puntos.',
            grupos_eliminacion: 'Los equipos comienzan en grupos y los clasificados avanzan a llaves.',
            eliminacion: 'El que pierde queda eliminado. Ideal para campeonatos cortos o de fin de semana.'
        };
        if (formatDescription) {
            formatDescription.textContent = descriptions[selected] || 'Elige una opción para ver una explicación sencilla.';
        }
    }

    function updateSummary() {
        document.getElementById('summary-nombre').textContent = document.getElementById('nombre').value || 'Sin nombre';
        document.getElementById('summary-fechas').textContent = document.getElementById('fecha_inicio').value + ' - ' + (document.getElementById('fecha_fin').value || 'Sin definir');
        const formatoLabel = formatoSelect.options[formatoSelect.selectedIndex]?.text || 'No definido';
        document.getElementById('summary-formato').textContent = formatoLabel;
        document.getElementById('summary-plantilla').textContent = `Fútbol ${modalidad.value}: ${jugadoresCancha.value} en cancha, mínimo ${minJugadores.value}, máximo ${maxJugadores.value}`;
        const categoriaTexto = categoriaEdad.value === 'personalizada' ? categoriaPersonalizada.value : categoriaEdad.options[categoriaEdad.selectedIndex]?.text;
        document.getElementById('summary-perfil').textContent = `${categoriaTexto || 'Sin categoría'} · ${sexoSelect.options[sexoSelect.selectedIndex]?.text}`;
        const notas = [];
        if (selectedFormat() === 'grupos_eliminacion') {
            notas.push('Los grupos y clasificados se calcularán con los equipos aprobados.');
        } else if (selectedFormat() === 'liga') {
            notas.push('Las vueltas se calcularán con los equipos aprobados.');
        } else if (selectedFormat() === 'eliminacion') {
            notas.push('Las llaves se calcularán con los equipos aprobados.');
        }
        document.getElementById('summary-notas').textContent = notas.join(' ');
    }

    function selectedFormat() {
        return formatoSelect ? formatoSelect.value : '';
    }

    const sugerenciasPlantilla = {
        11: { cancha: 11, minimo: 15, maximo: 25 },
        9: { cancha: 9, minimo: 13, maximo: 20 },
        8: { cancha: 8, minimo: 12, maximo: 18 },
        7: { cancha: 7, minimo: 10, maximo: 15 },
        6: { cancha: 6, minimo: 8, maximo: 12 },
        5: { cancha: 5, minimo: 7, maximo: 12 },
    };

    modalidad?.addEventListener('change', () => {
        const sugerencia = sugerenciasPlantilla[modalidad.value];
        if (!sugerencia) return;
        jugadoresCancha.value = sugerencia.cancha;
        minJugadores.value = sugerencia.minimo;
        maxJugadores.value = sugerencia.maximo;
        validarPlantilla();
    });

    function validarPlantilla() {
        minJugadores.min = jugadoresCancha.value || 1;
        maxJugadores.min = minJugadores.value || 1;
        minJugadores.setCustomValidity(Number(minJugadores.value) < Number(jugadoresCancha.value) ? 'El mínimo no puede ser menor que los jugadores en cancha.' : '');
        maxJugadores.setCustomValidity(Number(maxJugadores.value) < Number(minJugadores.value) ? 'El máximo debe ser mayor o igual al mínimo.' : '');
    }

    [jugadoresCancha, minJugadores, maxJugadores].forEach(field => field?.addEventListener('input', validarPlantilla));

    const edadesSugeridas = {
        sub_7:[null,6], sub_9:[null,8], sub_11:[null,10], sub_13:[null,12],
        sub_15:[null,14], sub_17:[null,16], sub_20:[null,19], libre:[null,null],
        mas_30:[30,null], mas_35:[35,null], mas_40:[40,null], mas_45:[45,null], mas_50:[50,null]
    };

    function actualizarCamposPersonalizados(aplicarSugerencia = false) {
        const categoriaCustom = categoriaEdad.value === 'personalizada';
        categoriaPersonalizadaWrap.classList.toggle('d-none', !categoriaCustom);
        categoriaPersonalizada.required = categoriaCustom;
        if (aplicarSugerencia && edadesSugeridas[categoriaEdad.value]) {
            const [minimo, maximo] = edadesSugeridas[categoriaEdad.value];
            edadMin.value = minimo ?? '';
            edadMax.value = maximo ?? '';
        }
        validarEdades();
    }

    function validarEdades() {
        edadMax.min = edadMin.value || 0;
        edadMax.setCustomValidity(edadMin.value !== '' && edadMax.value !== '' && Number(edadMax.value) < Number(edadMin.value) ? 'La edad máxima debe ser mayor o igual a la edad mínima.' : '');
    }

    categoriaEdad?.addEventListener('change', () => actualizarCamposPersonalizados(true));
    edadMin?.addEventListener('input', validarEdades);
    edadMax?.addEventListener('input', validarEdades);

    function hideWizardError() {
        wizardError?.classList.add('d-none');
    }

    function showWizardError(message = 'Revisa los campos marcados antes de continuar.') {
        if (!wizardError) return;
        wizardError.querySelector('span').textContent = message;
        wizardError.classList.remove('d-none');
        wizardError.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function validateStep(step) {
        sincronizarFechas();
        validarPlantilla();
        actualizarCamposPersonalizados(false);
        const stepElement = document.querySelector(`.wizard-step[data-step="${step}"]`);
        const invalidField = stepElement?.querySelector('input:invalid, select:invalid, textarea:invalid');

        if (!invalidField) {
            hideWizardError();
            return true;
        }

        currentStep = step;
        showStep(step);
        invalidField.classList.add('is-invalid');
        showWizardError(invalidField.validationMessage || 'Completa correctamente el campo indicado.');
        setTimeout(() => {
            invalidField.focus({ preventScroll: true });
            invalidField.reportValidity();
        }, 100);
        return false;
    }

    function findFirstInvalidStep() {
        for (let step = 1; step <= totalSteps; step++) {
            if (!validateStep(step)) return step;
        }
        return null;
    }

    formatoSelect?.addEventListener('change', () => {
        updateFormatSettings();
    });

    function sincronizarFechas() {
        if (!fechaInicio?.value) return;
        fechaFin.min = fechaInicio.value;
        fechaLimite.max = fechaInicio.value;
        if (fechaFin.value && fechaFin.value <= fechaInicio.value) fechaFin.setCustomValidity('La fecha de fin debe ser posterior a la fecha de inicio.');
        else fechaFin.setCustomValidity('');
        if (fechaLimite.value && fechaLimite.value > fechaInicio.value) fechaLimite.setCustomValidity('La inscripción debe cerrar antes o el mismo día del inicio.');
        else fechaLimite.setCustomValidity('');
    }

    fechaInicio?.addEventListener('change', sincronizarFechas);
    fechaFin?.addEventListener('change', sincronizarFechas);
    fechaLimite?.addEventListener('change', sincronizarFechas);

    nextButton?.addEventListener('click', () => {
        if (currentStep < totalSteps) {
            if (!validateStep(currentStep)) return;
            currentStep++;
            showStep(currentStep);
        }
    });

    prevButton?.addEventListener('click', () => {
        if (currentStep > 1) {
            hideWizardError();
            currentStep--;
            showStep(currentStep);
        }
    });

    submitButton?.addEventListener('click', event => {
        updateFormatSettings();
        if (findFirstInvalidStep() !== null) event.preventDefault();
    });

    wizardForm?.addEventListener('submit', event => {
        updateFormatSettings();
        if (findFirstInvalidStep() !== null) {
            event.preventDefault();
            return;
        }
        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Guardando...';
    });

    // Mostrar el paso correcto si hay errores o valores previos
    window.addEventListener('DOMContentLoaded', () => {
        const hasFormato = formatoSelect && formatoSelect.value;
        if (hasFormato) {
            updateFormatSettings();
        }
        sincronizarFechas();
        actualizarCamposPersonalizados(false);
        const fieldWithServerError = document.querySelector('.wizard-step .is-invalid');
        currentStep = Number(fieldWithServerError?.closest('.wizard-step')?.dataset.step || 1);
        showStep(currentStep);
        if (fieldWithServerError) showWizardError('Laravel encontró un dato que debes corregir antes de guardar.');
    });
</script>
@endsection
