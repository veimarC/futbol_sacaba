@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="contenedor-transparente animar-aparecer">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div><h1 class="h2 fw-bold mb-1"><i class="bi bi-bell-fill me-2 text-warning"></i>Notificaciones</h1><p class="opacity-75 mb-0">Novedades que requieren tu atención.</p></div>
                @if(auth()->user()->unreadNotifications()->exists())
                    <form method="POST" action="{{ route('notificaciones.leer-todas') }}">@csrf<button class="btn btn-borde"><i class="bi bi-check2-all me-1"></i> Marcar todas como leídas</button></form>
                @endif
            </div>
            <div class="d-grid gap-3">
                @forelse($notificaciones as $notificacion)
                    <a href="{{ route('notificaciones.abrir', $notificacion) }}" class="notification-row {{ $notificacion->read_at ? '' : 'unread' }}">
                        <span class="notification-icon bg-{{ $notificacion->data['color'] ?? 'primary' }}"><i class="bi {{ $notificacion->data['icono'] ?? 'bi-bell' }}"></i></span>
                        <span class="flex-grow-1"><strong>{{ $notificacion->data['titulo'] ?? 'Notificación' }}</strong><span class="d-block small opacity-75 mt-1">{{ $notificacion->data['mensaje'] ?? '' }}</span></span>
                        <span class="small opacity-75 text-nowrap">{{ $notificacion->created_at->diffForHumans() }}</span>
                    </a>
                @empty
                    <div class="text-center py-5"><i class="bi bi-bell-slash display-4 opacity-50"></i><h2 class="h5 mt-3">No tienes notificaciones</h2><p class="opacity-75">Aquí aparecerán las novedades de tus campeonatos.</p></div>
                @endforelse
            </div>
            @if($notificaciones->hasPages())<div class="mt-4">{{ $notificaciones->links() }}</div>@endif
        </div>
    </div>
</div>

@endsection
