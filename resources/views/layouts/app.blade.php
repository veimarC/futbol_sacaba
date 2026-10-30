{{--
    Plantilla base de la aplicación.
    Contiene los estilos, scripts y la estructura general (header, contenido y pie de página).
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fútbol Sacaba - Plataforma Oficial</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @vite(['resources/sass/app.scss', 'resources/css/platform.css', 'resources/js/app.js'])
    
</head>
<body>
    <!-- Navegación principal adaptada al usuario autenticado. -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand marca" href="{{ url('/') }}">
                FÚTBOL SACABA
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="menuPrincipal">
                @guest
                    @if(request()->routeIs('publico.show') && isset($campeonato))
                    <div class="navbar-championship-context ms-lg-4 me-lg-3">
                        <a href="{{ route('publico.index') }}" aria-label="Volver a campeonatos"><i class="bi bi-arrow-left"></i></a>
                    </div>
                    @elseif(request()->routeIs('publico.index'))
                    <form class="navbar-public-search ms-lg-4 me-lg-3" method="GET" action="{{ route('publico.index') }}" role="search">
                        <label for="buscar-navbar" class="visually-hidden">Buscar campeonato por nombre</label>
                        <i class="bi bi-search"></i>
                        <input id="buscar-navbar" type="search" name="buscar" value="{{ request('buscar') }}" placeholder="Buscar campeonato..." autocomplete="off">
                        <button type="submit" aria-label="Buscar"><i class="bi bi-arrow-right"></i></button>
                    </form>
                    @endif
                @endguest
                @auth
                    @if(request()->routeIs('publico.show', 'campeonatos.show') && isset($campeonato))
                    <div class="navbar-championship-context ms-lg-4 me-lg-3">
                        <a href="{{ route(request()->routeIs('publico.show') ? 'publico.index' : 'campeonatos.index') }}" aria-label="Volver a campeonatos"><i class="bi bi-arrow-left"></i></a>
                    </div>
                    @endif
                @endauth
                <ul class="navbar-nav ms-auto align-items-center gap-2 {{ request()->routeIs('publico.show', 'campeonatos.show') ? 'd-none' : '' }}">
                    @guest
                    <li class="nav-item">
                        <a class="nav-link {{ Request::is('/') ? 'active' : '' }}" href="{{ route('publico.index') }}">
                            <i class="bi bi-house-fill"></i> Inicio
                        </a>
                    </li>
                    @endguest
                    @auth
                    <li class="nav-item">
                        <a class="nav-link {{ Request::is('campeonatos*') ? 'active' : '' }}" href="{{ route('campeonatos.index') }}">
                            <i class="bi bi-trophy"></i> {{ Auth::user()->rol === \App\Models\User::ROLE_DELEGADO ? 'Mis campeonatos' : 'Campeonatos' }}
                        </a>
                    </li>

                    @if(Auth::user()->rol === \App\Models\User::ROLE_DELEGADO)
                    <li class="nav-item"><a class="nav-link {{ Request::is('campeonatos-descubrir') ? 'active' : '' }}" href="{{ route('campeonatos.descubrir') }}"><i class="bi bi-search"></i> Inscribirme</a></li>
                    <li class="nav-item"><a class="nav-link {{ Request::is('equipos*') ? 'active' : '' }}" href="{{ route('equipos.index') }}"><i class="bi bi-shield-fill"></i> Mis equipos</a></li>
                    @endif
                    @php
                        $notificacionesNuevas = Auth::user()->unreadNotifications()->latest()->limit(5)->get();
                        $totalNotificacionesNuevas = Auth::user()->unreadNotifications()->count();
                    @endphp
                    <li class="nav-item dropdown">
                        <a class="nav-link position-relative" href="#" role="button" data-bs-toggle="dropdown" aria-label="Notificaciones">
                            <i class="bi bi-bell-fill"></i>
                            @if($totalNotificacionesNuevas)
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">{{ $totalNotificacionesNuevas > 99 ? '99+' : $totalNotificacionesNuevas }}</span>
                            @endif
                        </a>
                        <div class="dropdown-menu dropdown-menu-end notification-dropdown p-2">
                            <div class="d-flex justify-content-between align-items-center px-2 py-2"><strong>Notificaciones</strong><span class="badge bg-danger">{{ $totalNotificacionesNuevas }} nuevas</span></div>
                            @forelse($notificacionesNuevas as $notificacion)
                                <a class="dropdown-item rounded-3 py-2" href="{{ route('notificaciones.abrir', $notificacion) }}">
                                    <div class="fw-semibold small">{{ $notificacion->data['titulo'] ?? 'Notificación' }}</div>
                                    <div class="small text-muted notification-preview">{{ $notificacion->data['mensaje'] ?? '' }}</div>
                                </a>
                            @empty
                                <div class="px-3 py-3 text-muted small text-center">No tienes avisos nuevos.</div>
                            @endforelse
                            <div class="border-top mt-2 pt-2"><a class="dropdown-item text-center fw-semibold" href="{{ route('notificaciones.index') }}">Ver todas</a></div>
                        </div>
                    </li>
                    @endauth
                    @guest
                        <li class="nav-item ms-lg-3">
                            <a class="{{ request()->routeIs('login') ? 'btn btn-naranja' : 'nav-link' }}" href="{{ route('login') }}" @if(request()->routeIs('login')) aria-current="page" @endif>
                                <i class="bi bi-box-arrow-in-right"></i> Ingresar
                            </a>
                        </li>
                        <li class="nav-item ms-lg-2">
                            <a class="{{ request()->routeIs('register') ? 'btn btn-naranja' : 'nav-link' }}" href="{{ route('register') }}" @if(request()->routeIs('register')) aria-current="page" @endif>
                                <i class="bi bi-person-plus"></i> Registrarse
                            </a>
                        </li>
                    @else
                        <li class="nav-item dropdown ms-lg-3">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                {{ Auth::user()->name }}
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('perfil.edit') }}"><i class="bi bi-person-gear me-2"></i> Perfil</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="#" onclick="event.preventDefault(); document.getElementById('form-salir').submit();">
                                        <i class="bi bi-box-arrow-right me-2"></i> Cerrar Sesión
                                    </a>
                                    <form id="form-salir" action="{{ route('logout') }}" method="POST" class="d-none">
                                        @csrf
                                    </form>
                                </li>
                            </ul>
                        </li>
                    @endguest
                </ul>
            </div>
        </div>
        @if(request()->routeIs('publico.show') && isset($campeonato))
            <nav class="navbar-context-tabs detail-nav" aria-label="Secciones del campeonato" role="tablist">
                <a href="#resumen" data-module="resumen"><i class="bi bi-journal-text"></i> Reglamentos</a>
                <a href="#equipos" data-module="equipos"><i class="bi bi-shield-fill"></i> Equipos</a>
                <a href="#partidos" data-module="partidos" class="active"><i class="bi bi-calendar-event"></i> Partidos</a>
                @if($campeonato->tieneTablaPosiciones())
                <a href="#tabla" data-module="tabla"><i class="bi bi-bar-chart-steps"></i> Tabla</a>
                @endif
                @if($mostrarLlaves)<a href="#llaves" data-module="llaves"><i class="bi bi-diagram-3-fill"></i> Llaves</a>@endif
                <a href="#goleadores" data-module="goleadores"><i class="bi bi-award"></i> Goleadores</a>
                <a href="#tarjetas" data-module="tarjetas"><i class="bi bi-card-list"></i> Tarjetas</a>
            </nav>
        @elseif(request()->routeIs('campeonatos.show') && isset($campeonato) && auth()->check())
            @php($barraPartidos = request('seccion') === 'partidos' || auth()->user()->rol === \App\Models\User::ROLE_DELEGADO)
            <div class="navbar-context-tabs championship-tabs" role="tablist" aria-label="Contenido del campeonato">
                <button id="reglamentos-tab" class="championship-tab module-tab" type="button" role="tab" aria-controls="reglamentos" data-bs-toggle="tab" data-bs-target="#reglamentos"><i class="bi bi-journal-text"></i> Reglamentos</button>
                <button class="championship-tab module-tab {{ $barraPartidos ? '' : 'active' }}" type="button" data-bs-toggle="tab" data-bs-target="#eq"><i class="bi bi-people-fill"></i> Equipos</button>
                <button class="championship-tab module-tab {{ $barraPartidos ? 'active' : '' }}" type="button" data-bs-toggle="tab" data-bs-target="#par"><i class="bi bi-calendar-event"></i> Partidos</button>
                @if($campeonato->tieneTablaPosiciones())
                <button class="championship-tab module-tab" type="button" data-bs-toggle="tab" data-bs-target="#tab"><i class="bi bi-bar-chart-steps"></i> Tabla</button>
                @endif
                @if($mostrarLlaves)<button class="championship-tab module-tab" type="button" data-bs-toggle="tab" data-bs-target="#llaves"><i class="bi bi-diagram-3-fill"></i> Llaves</button>@endif
                <button class="championship-tab module-tab" type="button" data-bs-toggle="tab" data-bs-target="#goleadores"><i class="bi bi-award-fill"></i> Goleadores</button>
                <button class="championship-tab module-tab" type="button" data-bs-toggle="tab" data-bs-target="#tarjetas"><i class="bi bi-card-list"></i> Tarjetas</button>
            </div>
        @endif    </nav>

    <!-- Contenido -->
    <main>
        <div class="container">
            @if(session('mensaje') || session('success'))
                <div class="alert alert-success alert-dismissible fade show animar-aparecer" role="status"><i class="bi bi-check-circle-fill me-2"></i>{{ session('mensaje') ?: session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar mensaje"></button></div>
            @endif
            @if(session('error') || $errors->any())
                <div class="alert alert-danger alert-dismissible fade show animar-aparecer" role="alert">
                    <div class="d-flex align-items-start">
                        <i class="bi bi-exclamation-triangle-fill me-2 mt-1"></i>
                        <div class="flex-grow-1">
                            <strong>{{ $errors->any() ? 'Hay algunos datos que debes corregir:' : 'No se pudo completar la acción:' }}</strong>
                            @if(session('error'))
                                <div>{{ session('error') }}</div>
                            @endif
                            @if($errors->any())
                                <ul class="mb-0 mt-1 ps-4">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar mensaje"></button>
                    </div>
                </div>
            @endif
            @yield('content')
        </div>
    </main>

    <script>
        // Los contenedores animados crean una capa visual propia. Mover los modales
        // al body evita que el fondo oscuro quede por encima y bloquee los botones.
        document.querySelectorAll('.modal').forEach(modal => {
            if (modal.parentElement !== document.body) document.body.appendChild(modal);
        });
        document.querySelectorAll('form[data-confirm]').forEach(form => form.addEventListener('submit', event => {
            if (!confirm(form.dataset.confirm)) event.preventDefault();
        }));
        document.querySelectorAll('form').forEach(form => form.addEventListener('submit', () => {
            form.classList.add('was-validated');
        }));
        const portadaInput = document.getElementById('portada');
        const portadaPreview = document.getElementById('portada-preview');
        const eliminarPortada = document.getElementById('eliminar_portada');
        portadaInput?.addEventListener('change', () => {
            const archivo = portadaInput.files?.[0];
            if (!archivo || !portadaPreview) return;
            if (archivo.size > 4 * 1024 * 1024) {
                portadaInput.setCustomValidity('La imagen no puede superar los 4 MB.');
                portadaInput.reportValidity();
                portadaInput.value = '';
                return;
            }
            portadaInput.setCustomValidity('');
            const lector = new FileReader();
            lector.onload = event => {
                portadaPreview.innerHTML = `<img src="${event.target.result}" alt="Vista previa de la portada">`;
                portadaPreview.classList.add('has-image');
                if (eliminarPortada) eliminarPortada.checked = false;
            };
            lector.readAsDataURL(archivo);
        });
        eliminarPortada?.addEventListener('change', () => {
            if (!eliminarPortada.checked || !portadaPreview) return;
            portadaInput.value = '';
            portadaPreview.classList.remove('has-image');
            portadaPreview.innerHTML = '<i class="bi bi-image"></i><span>Se usará la portada predeterminada</span>';
        });
    </script>
    @stack('scripts')
</body>
</html>
