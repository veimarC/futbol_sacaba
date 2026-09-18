@extends('layouts.app')

@section('content')
<div class="contenedor-transparente animar-aparecer">
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
        <div>
            <span class="page-kicker">Equipo arbitral</span>
            <h2 class="fw-bold mb-1">Árbitros del campeonato</h2>
            <p class="opacity-75 mb-0">{{ $campeonato->nombre }}</p>
        </div>
        <a href="{{ route('campeonatos.show', $campeonato) }}" class="btn btn-borde align-self-md-start">← Volver al campeonato</a>
    </div>

    @if(session('mensaje'))<div class="alert alert-success">{{ session('mensaje') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <section class="referee-management mb-4">
        <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
            <h3 class="h5 fw-bold mb-0">Solicitudes pendientes</h3>
            <span class="badge bg-warning text-dark">{{ $solicitudesArbitros->count() }}</span>
        </div>
        @forelse($solicitudesArbitros as $arbitro)
            <article class="referee-request mb-3">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                    <div><strong><i class="bi bi-person-badge me-1 text-warning"></i>{{ $arbitro->name }}</strong>@if($arbitro->pivot->mensaje)<p class="small mt-2 mb-0">“{{ $arbitro->pivot->mensaje }}”</p>@endif</div>
                    <div class="d-flex flex-column gap-2 referee-actions">
                        <form method="POST" action="{{ route('campeonatos.arbitros.gestionar', [$campeonato, $arbitro]) }}">@csrf<input type="hidden" name="estado" value="aprobado"><button class="btn btn-success btn-sm w-100">Aprobar</button></form>
                        <form method="POST" action="{{ route('campeonatos.arbitros.gestionar', [$campeonato, $arbitro]) }}" class="d-flex gap-2">@csrf<input type="hidden" name="estado" value="rechazado"><input name="motivo_respuesta" class="form-control form-control-sm" maxlength="500" placeholder="Motivo (opcional)"><button class="btn btn-danger btn-sm">Rechazar</button></form>
                    </div>
                </div>
            </article>
        @empty
            <div class="sports-empty"><i class="bi bi-check2-circle"></i><strong>No hay solicitudes pendientes.</strong></div>
        @endforelse
    </section>

    <section>
        <h3 class="h5 fw-bold mb-3">Árbitros aprobados</h3>
        <div class="d-flex flex-wrap gap-2">
            @forelse($arbitrosAprobados as $arbitro)
                <span class="approved-referee"><i class="bi bi-patch-check-fill text-success"></i>{{ $arbitro->name }}</span>
            @empty
                <p class="opacity-75">Todavía no hay árbitros aprobados.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
