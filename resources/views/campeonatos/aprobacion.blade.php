@if($campeonato->aprobacion_estado !== 'aprobado')
<section class="mb-4 p-3 rounded border border-light border-opacity-25">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h3 class="h5 mb-1">Publicación del campeonato</h3>
            <span class="badge {{ $campeonato->aprobacion_estado === 'aprobado' ? 'bg-success' : 'bg-warning text-dark' }}">{{ $campeonato->aprobacion_label }}</span>
        </div>
        @if(in_array($campeonato->aprobacion_estado, ['borrador', 'devuelto'], true))
            <form method="POST" action="{{ route('campeonatos.enviarRevision', $campeonato) }}">
                @csrf
                <button type="submit" class="btn btn-naranja">Enviar a revisión</button>
            </form>
        @endif
    </div>
    @if($campeonato->aprobacion_estado !== 'aprobado')
        <p class="small mt-3 mb-2">El campeonato será público y permitirá inscripciones cuando lo apruebe el administrador general.</p>
        @if($campeonato->portada_url)
            <img src="{{ $campeonato->portada_url }}" alt="Portada del campeonato" class="img-fluid rounded mb-3" style="max-height: 240px; object-fit: contain;">
        @endif
        <dl class="row small mb-0">
            <dt class="col-sm-4">Descripción</dt><dd class="col-sm-8">{{ $campeonato->descripcion ?: 'Sin descripción' }}</dd>
            <dt class="col-sm-4">Reglamentos internos</dt><dd class="col-sm-8 internal-rules-text">{{ $campeonato->reglamento_interno ?: 'Sin reglamentos internos' }}</dd>
            <dt class="col-sm-4">Formato</dt><dd class="col-sm-8">{{ $campeonato->formato_label }}</dd>
            <dt class="col-sm-4">Partidos y vueltas</dt>
            <dd class="col-sm-8">
                @if($campeonato->formato === 'liga')
                    Liga: {{ data_get($campeonato->formato_config, 'league_rounds') === null ? 'Según cantidad de equipos (configuración anterior)' : ((int) data_get($campeonato->formato_config, 'league_rounds') === 2 ? 'ida y vuelta' : 'una vuelta') }}.
                @else
                    @if($campeonato->formato === 'grupos_eliminacion')
                        Grupos: {{ (int) data_get($campeonato->formato_config, 'group_rounds', 1) === 2 ? 'ida y vuelta' : 'una vuelta' }}.
                    @endif
                    Eliminatorias: {{ data_get($campeonato->formato_config, 'eliminations_home_away', false) ? 'ida y vuelta hasta semifinales' : 'partido único' }}.
                    Final a partido único.
                    {{ data_get($campeonato->formato_config, 'third_place_match', false) ? 'Tercer puesto a partido único.' : 'Sin partido por el tercer puesto.' }}
                @endif
            </dd>
            <dt class="col-sm-4">Categoría</dt><dd class="col-sm-8">{{ $campeonato->categoria_edad_label }} · {{ $campeonato->sexo_label }} ({{ $campeonato->edad_min ?? 'Sin mínimo' }} / {{ $campeonato->edad_max ?? 'Sin máximo' }} años)</dd>
            <dt class="col-sm-4">Modalidad y jugadores</dt><dd class="col-sm-8">Fútbol {{ $campeonato->modalidad }} · {{ $campeonato->jugadores_cancha }} en cancha · {{ $campeonato->min_jugadores_equipo }} a {{ $campeonato->max_jugadores_equipo }} por equipo</dd>
            <dt class="col-sm-4">Inscripciones</dt><dd class="col-sm-8">{{ $campeonato->inscripciones_abiertas ? 'Abrir al aprobarse' : 'Cerradas' }} · Límite: {{ $campeonato->fecha_limite_inscripcion?->format('d/m/Y') ?? 'Sin límite' }}</dd>
        </dl>
    @endif
    @if($campeonato->aprobacion_motivo)
        <div class="mt-3 p-3 bg-light text-dark rounded"><strong>Correcciones solicitadas:</strong><p class="mb-0 text-break">{{ $campeonato->aprobacion_motivo }}</p></div>
    @endif
    @if($campeonato->aprobacion_estado === 'pendiente')
        @if(auth()->user()->esDesarrollador())
            <div class="mt-3 d-flex flex-column gap-3">
                <form method="POST" action="{{ route('campeonatos.revisar', $campeonato) }}">
                    @csrf
                    <input type="hidden" name="decision" value="aprobado">
                    <button type="submit" class="btn btn-success">Aprobar y publicar</button>
                </form>
                <form method="POST" action="{{ route('campeonatos.revisar', $campeonato) }}">
                    @csrf
                    <input type="hidden" name="decision" value="devuelto">
                    <label for="motivo-revision" class="form-label">Motivo de la devolución</label>
                    <textarea id="motivo-revision" name="motivo" class="form-control" rows="3" required minlength="10" maxlength="2000">{{ old('motivo') }}</textarea>
                    <button type="submit" class="btn btn-warning mt-2">Devolver para corregir</button>
                </form>
            </div>
        @else
            <p class="small mt-3 mb-0">Solicitud en revisión. Espera la respuesta del administrador general para continuar.</p>
        @endif
    @endif
</section>
@endif
