@extends('layouts.app')

@section('content')
<div class="text-center animar-aparecer delay-100">
    <h1 class="display-3 fw-bold mb-4">Plataforma de Gestión Deportiva</h1>
    <p class="lead mb-5 mx-auto content-readable">
        Organiza, consulta y administra campeonatos, partidos, resultados y estadísticas de forma clara, rápida y segura.
    </p>
    <div class="d-flex justify-content-center gap-4 flex-wrap mb-5">
        <a href="{{ auth()->check() ? route('campeonatos.index') : route('login') }}" class="btn btn-naranja btn-lg px-5">
            <i class="bi bi-eye me-2"></i> Ver Campeonatos
        </a>
        <a href="{{ route('register') }}" class="btn btn-borde btn-lg px-5">
            <i class="bi bi-pencil-square me-2"></i> Unirme al Torneo
        </a>
    </div>
</div>

<div class="animar-aparecer delay-300">
    <h2 class="text-center mb-5 fw-semibold">¿Qué puedes hacer aquí?</h2>
    <div class="row g-4">
        <div class="col-md-4">
            <div class="tarjeta-opcion text-center">
                <i class="bi bi-trophy fs-1 mb-3 text-accent"></i>
                <h4 class="fw-semibold mb-2">Campeonatos</h4>
                <p class="opacity-75">Torneos activos, fechas y equipos participantes.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="tarjeta-opcion text-center">
                <i class="bi bi-calendar-check fs-1 mb-3 text-accent"></i>
                <h4 class="fw-semibold mb-2">Calendario y Resultados</h4>
                <p class="opacity-75">Horarios, canchas y marcadores de cada encuentro.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="tarjeta-opcion text-center">
                <i class="bi bi-bar-chart-line fs-1 mb-3 text-accent"></i>
                <h4 class="fw-semibold mb-2">Estadísticas</h4>
                <p class="opacity-75">Tablas de posiciones, goleadores y sanciones.</p>
            </div>
        </div>
    </div>
</div>
@endsection
