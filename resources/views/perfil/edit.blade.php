@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8 col-xl-7">
        <div class="contenedor-transparente animar-aparecer">
            <div class="d-flex align-items-center gap-3 mb-4">
                <span class="profile-avatar"><i class="bi bi-person-fill"></i></span>
                <div>
                    <h1 class="h2 fw-bold mb-1">Mi perfil</h1>
                    <p class="opacity-75 mb-0">Actualiza tus datos de acceso a la plataforma.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('perfil.update') }}">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    <div class="col-md-7">
                        <label for="name" class="form-label">Nombre completo *</label>
                        <input id="name" name="name" type="text" value="{{ old('name', $usuario->name) }}" class="form-control @error('name') is-invalid @enderror" required autocomplete="name">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Rol en la plataforma</label>
                        <div class="form-control profile-readonly"><i class="bi bi-shield-check me-2"></i>{{ ucfirst($usuario->rol) }}</div>
                        <div class="form-text text-white-75">El rol protege los permisos de tu cuenta.</div>
                    </div>
                    <div class="col-12">
                        <label for="email" class="form-label">Correo electrónico *</label>
                        <input id="email" name="email" type="email" value="{{ old('email', $usuario->email) }}" class="form-control @error('email') is-invalid @enderror" required autocomplete="email">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <x-whatsapp-field :value="$usuario->whatsapp_nacional" />
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input type="hidden" name="whatsapp_notifications" value="0">
                            <input id="whatsapp_notifications" name="whatsapp_notifications" value="1" type="checkbox" class="form-check-input" {{ old('whatsapp_notifications', $usuario->whatsapp_opt_in_at !== null) ? 'checked' : '' }}>
                            <label for="whatsapp_notifications" class="form-check-label">
                                Recibir por WhatsApp avisos administrativos de campeonatos, equipos, nóminas y partidos. No se enviará publicidad.
                            </label>
                            <div class="form-text">Puedes desactivar estos avisos cuando quieras.</div>
                        </div>
                    </div>
                </div>

                <div class="profile-password mt-4">
                    <h2 class="h5 fw-bold"><i class="bi bi-key-fill text-warning me-2"></i>Cambiar contraseña</h2>
                    <p class="opacity-75 small">Déjala vacía si no deseas cambiarla.</p>
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="current_password" class="form-label">Contraseña actual</label>
                            <input id="current_password" name="current_password" type="password" class="form-control @error('current_password') is-invalid @enderror" autocomplete="current-password">
                            @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">Nueva contraseña</label>
                            <input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password">
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="password_confirmation" class="form-label">Confirmar nueva contraseña</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" autocomplete="new-password">
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-3 mt-4">
                    <button class="btn btn-naranja" type="submit"><i class="bi bi-check2-circle me-1"></i> Guardar cambios</button>
                    <a class="btn btn-borde" href="{{ route('home') }}">Cancelar</a>
                </div>
            </form>

        </div>
    </div>
</div>


@endsection
