{{--
    Vista de detalle del campeonato.
    Muestra información general, equipos, partidos, jugadores y tablas.
--}}
@extends('layouts.app')

@section('content')
@php
    $estadoMiInscripcion = $equipoInscritoEnCampeonato?->campeonatos->first()?->pivot?->estado;
    $esDelegadoAprobado = auth()->user()->rol === \App\Models\User::ROLE_DELEGADO
        && $estadoMiInscripcion === 'aprobado';
    $mostrarPartidosInicialmente = $esDelegadoAprobado || request('seccion') === 'partidos';
@endphp
<div class="contenedor-transparente animar-aparecer">

    {{-- ✅ MENSAJES DE ÉXITO Y ERROR --}}
    @if(session('mensaje'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('mensaje') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            @foreach ($errors->all() as $error)
                {{ $error }}
            @endforeach
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="championship-header mb-4 d-flex align-items-center gap-3">
        <a href="{{ auth()->user()->rol === \App\Models\User::ROLE_DELEGADO ? route('equipos.index') : (auth()->user()->rol === \App\Models\User::ROLE_ARBITRO ? route('arbitros.explorar') : route('campeonatos.index')) }}" class="btn btn-borde btn-sm">← Volver</a>
        <div>
            <h2 class="fw-bold mb-1">
                <i class="bi bi-trophy me-2 text-accent"></i>{{ $campeonato->nombre }}
            </h2>
            <span class="badge rounded-pill {{ $campeonato->estado_actual === 'pendiente' ? 'bg-warning text-dark' : ($campeonato->estado_actual === 'en_curso' ? 'bg-primary' : 'bg-secondary') }}">
                {{ $campeonato->estado_actual_label }}
            </span>

            @if($campeonato->formato)
                <span class="badge rounded-pill ms-2 bg-info text-dark">{{ $campeonato->formato_label }}</span>
            @endif

            <p class="opacity-75 mt-2 mb-0">
                Inicio: {{ \Carbon\Carbon::parse($campeonato->fecha_inicio)->format('d/m/Y') }}
                @if($campeonato->fecha_fin) | Fin: {{ \Carbon\Carbon::parse($campeonato->fecha_fin)->format('d/m/Y') }} @endif
            </p>
            @if($campeonato->organizador)
                <p class="small mt-2 mb-0"><i class="bi bi-person-badge me-1 text-warning"></i>Organiza: <strong>{{ $campeonato->organizador->name }}</strong></p>
            @endif
        </div>
        <div class="ms-auto d-flex gap-2 flex-wrap">
            @if(auth()->check() && (auth()->user()->esDesarrollador() || (auth()->user()->rol === \App\Models\User::ROLE_ORGANIZADOR && (int) $campeonato->usuario_creador === (int) auth()->id())))
                <a href="{{ route('campeonatos.arbitros.administrar', $campeonato) }}" class="btn btn-sm btn-borde">
                    <i class="bi bi-person-badge me-1"></i> Árbitros
                </a>
            @endif
            @can('update', $campeonato)
            <a href="{{ route('campeonatos.edit', $campeonato) }}" class="btn btn-sm btn-warning text-dark">
                <i class="bi bi-pencil-square"></i> Editar
            </a>
            <form action="{{ route('campeonatos.destroy', $campeonato) }}" method="POST" onsubmit="return confirm('¿Seguro que quieres eliminar este torneo?')">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-danger">
                    <i class="bi bi-trash3"></i> Eliminar
                </button>
            </form>
            @endcan
            @if(auth()->check() && (auth()->user()->rol === \App\Models\User::ROLE_DESARROLLADOR || (auth()->user()->rol === \App\Models\User::ROLE_ORGANIZADOR && $campeonato->usuario_creador == auth()->id())))
                {{-- Botón rápido para generar fixture si el formato es liga --}}
                @if(in_array($campeonato->formato, ['liga','liga_ida_vuelta','eliminacion','grupos_eliminacion']) && $fases->isEmpty() && $partidos->isEmpty())
                    <form action="{{ route('campeonatos.generarFixture', $campeonato) }}" method="POST" onsubmit="return confirm('La configuración se calculará con los equipos aprobados y se cerrarán las inscripciones. ¿Deseas generar el fixture?')" class="ms-2">
                        @csrf
                        <button class="btn btn-sm btn-success"><i class="bi bi-magic me-1"></i>Configurar y generar fixture</button>
                    </form>
                @endif
            @endif
        </div>
    </div>

    @if(auth()->user()->rol === \App\Models\User::ROLE_DELEGADO)
        @php
            $inscripcionDisponible = $campeonato->estado_actual !== 'finalizado'
                && $campeonato->inscripciones_abiertas
                && (!$campeonato->fecha_limite_inscripcion || now()->lte($campeonato->fecha_limite_inscripcion->endOfDay()));
        @endphp
        <section class="delegate-enrollment mb-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
                <div>
                    <span class="small text-uppercase fw-bold text-warning">Inscripción de equipos</span>
                    <h3 class="h5 fw-bold mt-1 mb-1">Participa con tu equipo</h3>
                    <p class="opacity-75 mb-0">El organizador revisará tu solicitud antes de aprobar la inscripción.</p>
                </div>
                @if($inscripcionDisponible && !$equipoInscritoEnCampeonato)
                    <a href="{{ route('equipos.create', ['campeonato' => $campeonato->id]) }}" class="btn btn-borde align-self-lg-start">
                        <i class="bi bi-plus-circle me-1"></i> Crear un equipo nuevo
                    </a>
                @endif
            </div>

            @if(!$inscripcionDisponible)
                <div class="alert alert-warning mb-0"><i class="bi bi-lock-fill me-2"></i>Las inscripciones para este campeonato están cerradas.</div>
            @elseif($misEquipos->isEmpty())
                <div class="enrollment-empty text-center py-4">
                    <i class="bi bi-shield-plus fs-1 text-warning"></i>
                    <p class="mt-2 mb-3">Todavía no tienes un equipo. Créalo y quedará vinculado a este campeonato.</p>
                    <a href="{{ route('equipos.create', ['campeonato' => $campeonato->id]) }}" class="btn btn-naranja">Crear equipo e inscribirme</a>
                </div>
            @else
                <div class="row g-3">
                    @foreach($misEquipos as $miEquipo)
                                    @php
                                        $registro = $miEquipo->campeonatos->first();
                                        $estadoSolicitud = $registro?->pivot?->estado;
                                        $yaParticipaConOtro = $equipoInscritoEnCampeonato && $equipoInscritoEnCampeonato->id !== $miEquipo->id;
                                    @endphp
                        <div class="col-md-6">
                            <div class="enrollment-team h-100">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="team-mini-shield">
                                        @if($escudoUrl = $miEquipo->escudo_url)
                                            <img src="{{ $escudoUrl }}" alt="">
                                        @else
                                            <i class="bi bi-shield-fill"></i>
                                        @endif
                                    </span>
                                    <div class="flex-grow-1">
                                        <strong>{{ $miEquipo->nombre }}</strong>
                                        <div class="small opacity-75">{{ $miEquipo->ciudad ?: 'Sin ciudad registrada' }}</div>
                                    </div>
                                    @if($estadoSolicitud === 'aprobado')
                                        <span class="badge bg-success">Aprobado</span>
                                    @elseif($estadoSolicitud === 'pendiente')
                                        <span class="badge bg-warning text-dark">Pendiente</span>
                                    @elseif($estadoSolicitud === 'rechazado')
                                        <span class="badge bg-danger">Rechazado</span>
                                    @elseif($estadoSolicitud === 'retirado')
                                        <span class="badge bg-dark">Retirado</span>
                                    @endif
                                </div>
                                <div class="mt-3">
                                    @if($yaParticipaConOtro)
                                        <button class="btn btn-light w-100" disabled><i class="bi bi-shield-check me-1"></i> Ya tienes un registro con {{ $equipoInscritoEnCampeonato->nombre }}</button>
                                    @elseif(!$estadoSolicitud || $estadoSolicitud === 'rechazado')
                                        <form method="POST" action="{{ route('equipos.solicitarInscripcion') }}">
                                            @csrf
                                            <input type="hidden" name="equipo_id" value="{{ $miEquipo->id }}">
                                            <input type="hidden" name="campeonato_id" value="{{ $campeonato->id }}">
                                            <button class="btn btn-naranja w-100"><i class="bi bi-send me-1"></i> {{ $estadoSolicitud === 'rechazado' ? 'Volver a solicitar' : 'Solicitar inscripción' }}</button>
                                        </form>
                                    @elseif($estadoSolicitud === 'pendiente')
                                        <button class="btn btn-light w-100" disabled><i class="bi bi-clock me-1"></i> Esperando respuesta</button>
                                    @elseif($estadoSolicitud === 'retirado')
                                        <div class="alert alert-warning mb-0 py-2 text-start">
                                            <strong><i class="bi bi-info-circle me-1"></i> Retirado del campeonato</strong>
                                            <div class="small mt-1">{{ $registro->pivot->motivo_retiro }}</div>
                                        </div>
                                    @else
                                        <a href="{{ route('equipos.show', [$miEquipo, 'campeonato' => $campeonato->id]) }}" class="btn btn-success w-100"><i class="bi bi-check-circle me-1"></i> Ver mi equipo inscrito</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    @if(auth()->user()->rol === \App\Models\User::ROLE_ARBITRO)
        <section class="referee-enrollment mb-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                <div>
                    <span class="small text-uppercase fw-bold text-warning">Inscripción de árbitros</span>
                    <h3 class="h5 fw-bold mt-1 mb-1">¿Quieres arbitrar este campeonato?</h3>
                    <p class="opacity-75 mb-0">Envía una sola postulación. El organizador debe aprobarte antes de poder asignarte partidos.</p>
                </div>
                <a href="{{ route('arbitros.panel') }}" class="btn btn-borde align-self-lg-start">Mis arbitrajes</a>
            </div>
            @if($campeonato->estado_actual === 'finalizado')
                <div class="alert alert-secondary mt-3 mb-0">Este campeonato ya finalizó.</div>
            @elseif(!$miSolicitudArbitro)
                <form method="POST" action="{{ route('arbitros.solicitar', $campeonato) }}" class="mt-3">
                    @csrf
                    <label for="mensaje-arbitro" class="form-label">Mensaje para el organizador (opcional)</label>
                    <textarea id="mensaje-arbitro" name="mensaje" class="form-control @error('mensaje') is-invalid @enderror" rows="2" maxlength="500" placeholder="Ej.: Tengo disponibilidad los fines de semana.">{{ old('mensaje') }}</textarea>
                    @error('mensaje')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <button class="btn btn-naranja mt-3"><i class="bi bi-send me-1"></i> Enviar postulación</button>
                </form>
            @else
                @php
                    $estadoArbitro = $miSolicitudArbitro->estado;
                @endphp
                <div class="application-result mt-3">
                    <div><strong>Estado de tu postulación:</strong> <span class="badge {{ $estadoArbitro === 'aprobado' ? 'bg-success' : ($estadoArbitro === 'pendiente' ? 'bg-warning text-dark' : 'bg-danger') }}">{{ ucfirst($estadoArbitro) }}</span></div>
                    @if($estadoArbitro === 'pendiente')<p class="small opacity-75 mb-0 mt-2">El organizador todavía está revisando tu solicitud.</p>@endif
                    @if($miSolicitudArbitro->motivo_respuesta)<p class="small mb-0 mt-2"><strong>Respuesta:</strong> {{ $miSolicitudArbitro->motivo_respuesta }}</p>@endif
                </div>
            @endif
        </section>
    @endif

    <div class="championship-tabs d-flex flex-wrap gap-3 mb-4 mt-4" role="tablist" aria-label="Contenido del campeonato">
        <button class="btn championship-tab module-tab {{ $mostrarPartidosInicialmente ? '' : 'active' }} px-4 py-2 fw-semibold shadow-sm" type="button" data-bs-toggle="tab" data-bs-target="#eq">
            <i class="bi bi-people-fill me-2"></i>Equipos
        </button>
        <button class="btn championship-tab module-tab {{ $mostrarPartidosInicialmente ? 'active' : '' }} px-4 py-2 fw-semibold shadow-sm" type="button" data-bs-toggle="tab" data-bs-target="#par">
            <i class="bi bi-calendar-event me-2"></i>Partidos
        </button>
        <button class="btn championship-tab module-tab px-4 py-2 fw-semibold shadow-sm" type="button" data-bs-toggle="tab" data-bs-target="#tab">
            <i class="bi bi-bar-chart-steps me-2"></i>Tabla
        </button>
        @if($mostrarLlaves)
            <button class="btn championship-tab module-tab px-4 py-2 fw-semibold shadow-sm" type="button" data-bs-toggle="tab" data-bs-target="#llaves">
                <i class="bi bi-diagram-3-fill me-2"></i>Llaves
            </button>
        @endif
        <button class="btn championship-tab module-tab px-4 py-2 fw-semibold shadow-sm" type="button" data-bs-toggle="tab" data-bs-target="#goleadores">
            <i class="bi bi-award-fill me-2"></i>Goleadores
        </button>
        <button class="btn championship-tab module-tab px-4 py-2 fw-semibold shadow-sm" type="button" data-bs-toggle="tab" data-bs-target="#tarjetas">
            <i class="bi bi-card-list me-2"></i>Tarjetas
        </button>
    </div>

    <div class="tab-content">
        {{-- Pestaña Equipos --}}
        <div class="tab-pane fade {{ $mostrarPartidosInicialmente ? '' : 'show active' }}" id="eq">
            <div class="teams-heading d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-semibold">Equipos inscritos en este torneo</h5>
                @if($campeonato->estado_actual === 'pendiente'
                    && auth()->user()->rol === \App\Models\User::ROLE_DELEGADO
                    && !$equipoInscritoEnCampeonato)
                    <a href="{{ route('equipos.create') }}?campeonato={{ $campeonato->id }}" class="btn btn-naranja btn-sm shadow-sm">
                        <i class="bi bi-plus-circle me-1"></i>Inscribir Equipo
                    </a>
                @endif
            </div>

            @if(auth()->user()->hasAnyRol(['organizador','desarrollador']) && $solicitudesPendientes->isNotEmpty())
                <div class="mb-4">
                    <h6 class="page-kicker">Solicitudes pendientes</h6>
                    <div class="row g-3 stagger">
                        @foreach($solicitudesPendientes as $solicitud)
                            <div class="col-md-6"><div class="tarjeta-opcion d-flex justify-content-between align-items-center gap-3"><div><strong>{{ $solicitud->nombre }}</strong><div class="small opacity-75">{{ $solicitud->delegado->name ?? 'Sin delegado' }}</div></div><div class="d-flex gap-2"><form method="POST" action="{{ route('campeonatos.equipos.gestionar', [$campeonato, $solicitud]) }}">@csrf<input type="hidden" name="estado" value="aprobado"><button class="btn btn-success btn-sm">Aprobar</button></form><form method="POST" action="{{ route('campeonatos.equipos.gestionar', [$campeonato, $solicitud]) }}">@csrf<input type="hidden" name="estado" value="rechazado"><button class="btn btn-outline-light btn-sm">Rechazar</button></form></div></div></div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($equipos->isEmpty())
                <div class="text-center py-5 opacity-75">
                    <i class="bi bi-shield-x icon-empty"></i>
                    <p class="mt-3">Aún no hay equipos inscritos en este campeonato.</p>
                </div>
            @else
                <div class="championship-team-grid">
                    @foreach($equipos as $equipo)
                        <div>
                            <article class="team-card" data-href="{{ route('equipos.show', [$equipo, 'campeonato' => $campeonato->id]) }}" tabindex="0" aria-label="Ver equipo {{ $equipo->nombre }}">
                                <div class="team-shield">
                                    @if($escudoUrl = $equipo->escudo_url)
                                        <img src="{{ $escudoUrl }}" alt="Escudo de {{ $equipo->nombre }}">
                                    @else
                                        <i class="bi bi-shield"></i>
                                    @endif
                                </div>
                                <h3 class="team-name">{{ $equipo->nombre }}</h3>
                                <p class="team-player-count"><i class="bi bi-people-fill me-1"></i>{{ $equipo->jugadores_inscritos_count }} jugadores</p>

                                <div class="team-actions d-flex justify-content-center gap-2 mt-3">
                                    <a href="{{ route('equipos.show', [$equipo, 'campeonato' => $campeonato->id]) }}" class="btn btn-sm btn-light text-dark"><i class="bi bi-eye me-1"></i>Ver equipo</a>
                                    @if($campeonato->estado_actual === 'pendiente' && auth()->check() && (auth()->user()->rol === \App\Models\User::ROLE_DESARROLLADOR || (auth()->user()->rol === \App\Models\User::ROLE_DELEGADO && $equipo->delegado_id == auth()->id()) || (auth()->user()->rol === \App\Models\User::ROLE_ORGANIZADOR && $campeonato->usuario_creador == auth()->id())))
                                        <a href="{{ route('equipos.edit', $equipo) }}" class="btn btn-sm btn-warning text-dark">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endif
                                    @if($campeonato->estado_actual === 'pendiente')
                                    @can('delete', $equipo)
                                        <form action="{{ route('equipos.destroy', $equipo) }}" method="POST" onsubmit="return confirm('¿Eliminar este equipo?')" class="d-inline">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-danger">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    @endcan
                                    @endif
                                    @if(auth()->user()->rol === \App\Models\User::ROLE_ORGANIZADOR && (int) $campeonato->usuario_creador === (int) auth()->id())
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#retirar-equipo-{{ $equipo->id }}">
                                            <i class="bi bi-person-x me-1"></i> Retirar
                                        </button>
                                    @endif
                                </div>
                            </article>
                        </div>

                        @if(auth()->user()->rol === \App\Models\User::ROLE_ORGANIZADOR && (int) $campeonato->usuario_creador === (int) auth()->id())
                            <div class="modal fade" id="retirar-equipo-{{ $equipo->id }}" tabindex="-1" aria-labelledby="titulo-retirar-{{ $equipo->id }}" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content text-dark">
                                        <form method="POST" action="{{ route('campeonatos.equipos.retirar', [$campeonato, $equipo]) }}" data-confirm="¿Confirmas que deseas retirar este equipo del campeonato?">
                                            @csrf
                                            <div class="modal-header">
                                                <h2 class="modal-title fs-5" id="titulo-retirar-{{ $equipo->id }}">Retirar a {{ $equipo->nombre }}</h2>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="alert alert-warning small">
                                                    El equipo dejará de participar en este campeonato, pero no será eliminado. Sus jugadores y resultados se conservarán.
                                                </div>
                                                <label for="motivo-retiro-{{ $equipo->id }}" class="form-label fw-semibold">Motivo del retiro *</label>
                                                <textarea id="motivo-retiro-{{ $equipo->id }}" name="motivo_retiro" class="form-control" rows="4" minlength="10" maxlength="500" required placeholder="Explica el motivo para que el delegado pueda entender la decisión."></textarea>
                                                <div class="form-text text-dark">Entre 10 y 500 caracteres.</div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                <button type="submit" class="btn btn-danger">Confirmar retiro</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif

            @if(auth()->user()->hasAnyRol(['organizador','desarrollador']) && $equiposRetirados->isNotEmpty())
                <div class="mt-5">
                    <h5 class="fw-semibold"><i class="bi bi-archive me-2"></i>Equipos retirados</h5>
                    <p class="small opacity-75">Se conservan para mantener el historial del campeonato.</p>
                    <div class="row g-3">
                        @foreach($equiposRetirados as $retirado)
                            <div class="col-md-6">
                                <div class="tarjeta-opcion p-3">
                                    <div class="d-flex justify-content-between gap-3">
                                        <div><strong>{{ $retirado->nombre }}</strong><div class="small opacity-75">Delegado: {{ $retirado->delegado->name ?? 'Sin delegado' }}</div></div>
                                        <span class="badge bg-dark align-self-start">Retirado</span>
                                    </div>
                                    <div class="small mt-3"><strong>Motivo:</strong> {{ $retirado->pivot->motivo_retiro }}</div>
                                    @if($retirado->pivot->retirado_at)<div class="small opacity-75 mt-1">Fecha: {{ \Carbon\Carbon::parse($retirado->pivot->retirado_at)->format('d/m/Y H:i') }}</div>@endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Pestaña Partidos --}}
        <div class="tab-pane fade {{ $mostrarPartidosInicialmente ? 'show active' : '' }}" id="par">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-semibold">Partidos programados</h5>
                @if($campeonato->estado_actual !== 'finalizado'
                    && ! ($campeonato->formato === 'grupos_eliminacion' && $fases->where('tipo', 'grupo')->isNotEmpty())
                    && (auth()->user()->esDesarrollador() || (auth()->user()->rol === \App\Models\User::ROLE_ORGANIZADOR && (int) $campeonato->usuario_creador === (int) auth()->id())))
                    <a href="{{ route('partidos.create') }}?campeonato={{ $campeonato->id }}" class="btn btn-naranja btn-sm shadow-sm">
                        <i class="bi bi-plus-circle me-1"></i>Programar Partido
                    </a>
                @endif
            </div>

            @if($partidos->isEmpty())
                <div class="text-center py-5 opacity-75">
                    <i class="bi bi-calendar-x icon-empty"></i>
                    <p class="mt-3">Aún no hay partidos programados en este campeonato.</p>
                </div>
            @else
                <div class="row g-3">
                    @php
                        $ultimaEtiquetaFase = null;
                    @endphp
                    @foreach($partidos as $p)
                        @php
                            $etiquetaFase = $etiquetasFases->get($p->fase_id, 'Otros partidos');
                        @endphp
                        @if($campeonato->formato === 'grupos_eliminacion' && $etiquetaFase !== $ultimaEtiquetaFase)
                            <div class="col-12 {{ $ultimaEtiquetaFase !== null ? 'mt-5' : '' }}">
                                <h5 class="fw-bold mb-0 text-warning">{{ $etiquetaFase }}</h5>
                                <p class="small opacity-75 mb-0">
                                    {{ str_starts_with($etiquetaFase, 'Grupo') ? 'Partidos entre los equipos de este grupo.' : 'Partidos de eliminación directa.' }}
                                </p>
                            </div>
                            @php
                                $ultimaEtiquetaFase = $etiquetaFase;
                            @endphp
                        @endif
                        <div class="col-md-6">
                            <div class="tarjeta-opcion p-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-secondary">
                                        @if($p->fecha && $p->hora)
                                            {{ $p->fecha->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($p->hora)->format('H:i') }}
                                        @else
                                            Fecha por definir
                                        @endif
                                    </span>
                                    <span class="badge {{ $p->estado == 'programado' ? 'bg-primary' : 'bg-success' }}">
                                        {{ ucfirst($p->estado) }}
                                    </span>
                                </div>

                                <div class="d-flex justify-content-between align-items-center text-center">
                                    <div>
                                        <div class="match-shield">
                                            @if($escudoLocalUrl = $p->equipoLocal->escudo_url)
                                                <img src="{{ $escudoLocalUrl }}" alt="Escudo {{ $p->equipoLocal->nombre }}">
                                            @else
                                                <i class="bi bi-shield icon-shield-muted"></i>
                                            @endif
                                        </div>
                                        <h6 class="fw-bold mb-0">{{ $p->equipoLocal->nombre }}</h6>
                                        <small class="opacity-75">Local</small>
                                    </div>

                                    <div class="fw-bold fs-5">VS</div>

                                    <div>
                                        <div class="match-shield">
                                            @if($escudoVisitaUrl = $p->equipoVisita->escudo_url)
                                                <img src="{{ $escudoVisitaUrl }}" alt="Escudo {{ $p->equipoVisita->nombre }}">
                                            @else
                                                <i class="bi bi-shield icon-shield-muted"></i>
                                            @endif
                                        </div>
                                        <h6 class="fw-bold mb-0">{{ $p->equipoVisita->nombre }}</h6>
                                        <small class="opacity-75">Visita</small>
                                    </div>
                                </div>

                                @if($p->cancha)
                                    <p class="small opacity-80 mt-2 mb-0 text-center">
                                        <i class="bi bi-geo-alt me-1"></i>{{ $p->cancha }}
                                    </p>
                                @endif

                                {{-- Acciones rápidas del partido. El resultado se registra desde Mesa de control. --}}
                                <div class="d-flex justify-content-center gap-2 mt-3 flex-wrap">
                                    @php
                                        $usuarioActual = auth()->user();
                                        $puedeVerNominas = $usuarioActual->esDesarrollador()
                                            || ($usuarioActual->rol === \App\Models\User::ROLE_ORGANIZADOR && (int) $campeonato->usuario_creador === (int) $usuarioActual->id)
                                            || ($usuarioActual->rol === \App\Models\User::ROLE_ARBITRO && (int) $p->arbitro_id === (int) $usuarioActual->id)
                                            || ($usuarioActual->rol === \App\Models\User::ROLE_DELEGADO && in_array((int) $usuarioActual->id, [(int) $p->equipoLocal?->delegado_id, (int) $p->equipoVisita?->delegado_id], true));
                                        $equipoDelDelegado = null;
                                        if ($usuarioActual->rol === \App\Models\User::ROLE_DELEGADO) {
                                            $equipoDelDelegado = (int) $p->equipoLocal?->delegado_id === (int) $usuarioActual->id
                                                ? $p->equipoLocal
                                                : ((int) $p->equipoVisita?->delegado_id === (int) $usuarioActual->id ? $p->equipoVisita : null);
                                        }
                                        $nominaPropia = $equipoDelDelegado
                                            ? $p->nominas->firstWhere('equipo_id', $equipoDelDelegado->id)
                                            : null;
                                        $puedePrepararNomina = $equipoDelDelegado
                                            && $p->estado === 'programado'
                                            && $nominaPropia?->estado !== 'aprobada';
                                    @endphp
                                    @if($puedeVerNominas)
                                        <a href="{{ $puedePrepararNomina ? route('partidos.nominas.editar', [$p, $equipoDelDelegado]) : route('partidos.nominas.index', $p) }}" class="btn btn-sm btn-borde">
                                            <i class="bi bi-clipboard2-check"></i>
                                            @if($usuarioActual->rol === \App\Models\User::ROLE_ORGANIZADOR)
                                                Mesa de control
                                            @elseif($puedePrepararNomina)
                                                {{ $nominaPropia ? 'Corregir nómina' : 'Elegir jugadores' }}
                                            @else
                                                Ver nómina
                                            @endif
                                        </a>
                                    @endif
                                    @if($p->estado == 'programado')
                                        @php
                                            $administraPartidos = auth()->user()->esDesarrollador() || (auth()->user()->rol === \App\Models\User::ROLE_ORGANIZADOR && (int) $campeonato->usuario_creador === (int) auth()->id());
                                        @endphp
                                        @if($administraPartidos)
                                        <a href="{{ route('partidos.edit', $p) }}" class="btn btn-sm btn-warning text-dark">
                                            <i class="bi bi-pencil"></i> Editar
                                        </a>
                                        <form action="{{ route('partidos.destroy', $p) }}" method="POST" onsubmit="return confirm('¿Eliminar este partido?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-danger">
                                                <i class="bi bi-trash3"></i> Eliminar
                                            </button>
                                        </form>
                                        @endif
                                    @else
                                        <div class="text-center fw-bold fs-5">
                                            {{ $p->goles_local }} - {{ $p->goles_visita }}
                                            <div class="badge bg-success mt-1">Jugado</div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Pestaña Tablas --}}
        <div class="tab-pane fade" id="tab">
            @if($campeonato->formato === 'grupos_eliminacion' && $tablasGrupos->isNotEmpty())
                @php
                    $menorGrupo = $tablasGrupos->min('cantidad_equipos');
                    $mayorGrupo = $tablasGrupos->max('cantidad_equipos');
                    $totalEquiposGrupos = $tablasGrupos->sum('cantidad_equipos');
                    $clasificadosPorGrupo = (int) data_get($campeonato->formato_config, 'qualifiers_per_group', 1);
                @endphp
                <div class="championship-qualification mb-4">
                    <div>
                        <h5 class="fw-semibold mb-1">Tabla de posiciones</h5>
                        <p class="opacity-75 mb-1">
                            {{ $totalEquiposGrupos }} equipos · {{ $tablasGrupos->count() }} grupos
                            de {{ $menorGrupo === $mayorGrupo ? $menorGrupo : $menorGrupo.' a '.$mayorGrupo }} equipos.
                        </p>
                        <p class="small opacity-75 mb-0">Cada grupo suma sus puntos por separado.</p>
                    </div>

                    @can('update', $campeonato)
                        @if(!$configuracionCopaBloqueada && count($opcionesClasificados) > 0)
                            <form method="POST" action="{{ route('campeonatos.actualizarClasificados', $campeonato) }}" class="qualification-control">
                                @csrf
                                @method('PATCH')
                                <label for="clasificados_por_grupo">Clasifican por grupo</label>
                                <div class="qualification-control-row">
                                    <select id="clasificados_por_grupo" name="clasificados_por_grupo" class="form-select" aria-describedby="qualification-help">
                                        @foreach($opcionesClasificados as $opcion)
                                            <option value="{{ $opcion }}" @selected($opcion === $clasificadosPorGrupo)>
                                                {{ $opcion }} ({{ $opcion * $tablasGrupos->count() }} en total)
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-naranja">Guardar</button>
                                </div>
                                <div class="qualification-options">
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="ida_vuelta_eliminatorias" value="0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="ida_vuelta_eliminatorias" name="ida_vuelta_eliminatorias" value="1" @checked(data_get($campeonato->formato_config, 'eliminations_home_away', false))>
                                        <label class="form-check-label" for="ida_vuelta_eliminatorias">Ida y vuelta en eliminatorias</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="partido_tercer_lugar" value="0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="partido_tercer_lugar" name="partido_tercer_lugar" value="1" @checked(data_get($campeonato->formato_config, 'third_place_match', false))>
                                        <label class="form-check-label" for="partido_tercer_lugar">Partido por el tercer lugar</label>
                                    </div>
                                </div>
                                <small id="qualification-help">La final siempre se juega a un solo partido.</small>
                            </form>
                        @else
                            <div class="qualification-summary">
                                <div>
                                    <span>Configuración definida</span>
                                    <strong>{{ $clasificadosPorGrupo }} por grupo</strong>
                                    <small>
                                        {{ data_get($campeonato->formato_config, 'eliminations_home_away', false) ? 'Ida y vuelta' : 'Partido único' }} ·
                                        {{ data_get($campeonato->formato_config, 'third_place_match', false) ? 'Con tercer lugar' : 'Sin tercer lugar' }}
                                    </small>
                                </div>
                                <i class="bi bi-lock-fill" aria-label="Configuración bloqueada"></i>
                            </div>
                        @endif
                    @else
                        <div class="qualification-summary">
                            <span>Clasifican por grupo</span>
                            <strong>{{ $clasificadosPorGrupo }}</strong>
                        </div>
                    @endcan
                </div>

                <div class="group-tables">
                    @foreach($tablasGrupos as $grupo)
                        <div class="group-table-card">
                            <h3>{{ $grupo['nombre'] }}</h3>
                            @include('publico.partials.tabla-posiciones', ['filas' => $grupo['tabla']])
                        </div>
                    @endforeach
                </div>
            @elseif(empty($tabla))
                <p class="opacity-75">Aún no hay partidos jugados para mostrar la tabla.</p>
            @else
                <h5 class="fw-semibold mb-3">Tabla de posiciones</h5>
                @include('campeonatos.partials.tabla-posiciones', ['filas' => $tabla])
            @endif
        </div>

        @if($mostrarLlaves)
            <div class="tab-pane fade" id="llaves">
                <div class="module-heading mb-3">
                    <h5 class="fw-semibold mb-1">Llaves eliminatorias</h5>
                    <p class="small opacity-75 mb-0">Los ganadores avanzan a la siguiente ronda.</p>
                </div>
                @if($partidosEliminacion->isEmpty())
                    <div class="sports-empty">
                        <i class="bi bi-diagram-3"></i>
                        <strong>Las llaves todavía no fueron generadas.</strong>
                        <span>Primero deben finalizar todos los partidos de la fase de grupos.</span>
                    </div>
                @else
                    @foreach($partidosEliminacion as $numeroRonda => $partidosRonda)
                        <section class="mb-4">
                            <h6 class="page-kicker mb-3">Ronda {{ $numeroRonda }}</h6>
                            <div class="knockout-match-grid">
                                @foreach($partidosRonda as $partido)
                                    <article class="knockout-match-card">
                                        <div><x-team-crest :equipo="$partido->equipoLocal" /><strong>{{ $partido->equipoLocal?->nombre ?? 'Por definir' }}</strong></div>
                                        <span class="knockout-score">
                                            {{ $partido->estado === 'jugado' ? $partido->goles_local.' - '.$partido->goles_visita : 'VS' }}
                                        </span>
                                        <div><x-team-crest :equipo="$partido->equipoVisita" /><strong>{{ $partido->equipoVisita?->nombre ?? 'Por definir' }}</strong></div>
                                        <small><i class="bi bi-calendar-event me-1"></i>{{ $partido->fecha?->format('d/m/Y') ?? 'Fecha por definir' }}</small>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                @endif
            </div>
        @endif

        <div class="tab-pane fade" id="goleadores">
            <div class="module-heading mb-3">
                <h5 class="fw-semibold mb-1">Goleadores</h5>
                <p class="small opacity-75 mb-0">Clasificación según los goles registrados en los partidos.</p>
            </div>
            @forelse($goleadores as $puesto => $jugador)
                <article class="sports-ranking-row">
                    <span class="position-number">{{ $puesto + 1 }}</span>
                    <img src="{{ $jugador->foto_url }}" alt="Foto de {{ $jugador->nombre }}" class="photo-xs">
                    <div><strong>{{ $jugador->nombre }} {{ $jugador->apellido }}</strong><small>{{ $jugador->equipo?->nombre ?? 'Sin equipo' }}</small></div>
                    <b>{{ $jugador->goles_count }} {{ $jugador->goles_count === 1 ? 'gol' : 'goles' }}</b>
                </article>
            @empty
                <div class="sports-empty"><i class="bi bi-award"></i><strong>Aún no hay goleadores.</strong><span>Aparecerán cuando se registren goles.</span></div>
            @endforelse
        </div>

        <div class="tab-pane fade" id="tarjetas">
            <div class="module-heading mb-3">
                <h5 class="fw-semibold mb-1">Tarjetas</h5>
                <p class="small opacity-75 mb-0">Control disciplinario del campeonato.</p>
            </div>
            @if($tarjetas->isEmpty())
                <div class="sports-empty"><i class="bi bi-card-list"></i><strong>Aún no hay tarjetas registradas.</strong></div>
            @else
                <div class="table-responsive sports-table-wrap">
                    <table class="table public-table discipline-table align-middle mb-0">
                        <thead><tr><th>#</th><th>Jugador</th><th>Equipo</th><th class="text-center">Amarillas</th><th class="text-center">Rojas</th></tr></thead>
                        <tbody>
                            @foreach($tarjetas as $puesto => $jugador)
                                <tr>
                                    <td><span class="position-number">{{ $puesto + 1 }}</span></td>
                                    <td class="fw-bold">{{ $jugador->nombre }} {{ $jugador->apellido }}</td>
                                    <td><span class="table-club"><x-team-crest :equipo="$jugador->equipo" /><span>{{ $jugador->equipo?->nombre }}</span></span></td>
                                    <td class="text-center"><span class="card-count yellow-card">{{ $jugador->amarillas_count }}</span></td>
                                    <td class="text-center"><span class="card-count red-card">{{ $jugador->rojas_count }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){

    // Navegar a la vista completa del equipo al hacer clic en la tarjeta
    document.querySelectorAll('.team-card').forEach(card => {
        card.addEventListener('click', function(e){
            // Evitar que los botones dentro de la tarjeta (ver/editar/eliminar) disparen la navegación
            if (e.target.closest('a') || e.target.closest('button') || e.target.closest('form')) return;
            const href = this.getAttribute('data-href');
            if (href) {
                window.location.href = href;
            }
        });
        card.addEventListener('keydown', function(e){
            if ((e.key === 'Enter' || e.key === ' ') && !e.target.closest('a, button, form')) {
                e.preventDefault();
                window.location.href = this.getAttribute('data-href');
            }
        });
    });

    // ✅ 1. MENSAJES: Desaparecen solos a los 4 segundos
    const alertas = document.querySelectorAll('.alert');
    alertas.forEach(alerta => {
        setTimeout(() => {
            alerta.classList.remove('show');
            setTimeout(() => alerta.remove(), 300);
        }, 4000);
    });

    // ✅ 2. PESTAÑAS
    const botones = document.querySelectorAll('[data-bs-toggle="tab"]');
    botones.forEach(btn => {
        btn.addEventListener('click', function(){
            botones.forEach(b => {
                b.style.background = 'rgba(255,255,255,0.12)';
                b.style.border = '1px solid rgba(255,255,255,0.2)';
            });
            this.style.background = 'var(--naranja-vivo)';
            this.style.border = 'none';

            const destino = this.getAttribute('data-bs-target');
            document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('show','active'));
            document.querySelector(destino).classList.add('show','active');
        });
    });

});
</script>
@endsection
