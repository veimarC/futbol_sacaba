@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="contenedor-transparente animar-aparecer text-center py-5">
            <div class="display-1 fw-bold text-accent">403</div>
            <h1 class="h3 fw-bold mt-2">No tienes permiso para realizar esta acción</h1>
            <p class="opacity-75 mt-3">Este contenido pertenece a otro usuario o ya no puede modificarse.</p>
            <a href="{{ url()->previous() }}" class="btn btn-borde mt-3"><i class="bi bi-arrow-left me-1"></i> Volver</a>
            <a href="{{ route('home') }}" class="btn btn-naranja mt-3"><i class="bi bi-house me-1"></i> Ir al inicio</a>
        </div>
    </div>
</div>
@endsection
