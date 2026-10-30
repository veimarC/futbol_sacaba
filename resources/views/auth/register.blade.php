@extends('layouts.app')

@section('content')
<div class="row justify-content-center py-lg-4">
    <div class="col-lg-6 col-md-8">
        <div class="contenedor-transparente animar-aparecer">
            <div class="text-center mb-4">
                <span class="page-kicker">Únete a la plataforma</span>
                <h1 class="h2 fw-bold mt-2"><i class="bi bi-person-plus me-2"></i>Crear cuenta</h1>
                <p class="opacity-75 mb-0">Elige tu función para adaptar las herramientas a tu trabajo.</p>
            </div>

            <form method="POST" action="{{ route('register') }}">
                @csrf
                <p class="required-note">Completa tus datos. El número de WhatsApp es opcional.</p>
                <div class="mb-3">
                    <label for="name" class="form-label">Nombre completo</label>
                    <input id="name" type="text" class="form-control form-control-lg @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required autocomplete="name" autofocus>
                    @error('name')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Correo electrónico</label>
                    <input id="email" type="email" class="form-control form-control-lg @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email">
                    @error('email')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
                </div>

                <x-whatsapp-field />

                <div class="form-check mb-3">
                    <input type="hidden" name="whatsapp_notifications" value="0">
                    <input id="whatsapp_notifications" name="whatsapp_notifications" value="1" type="checkbox" class="form-check-input" {{ old('whatsapp_notifications') ? 'checked' : '' }}>
                    <label for="whatsapp_notifications" class="form-check-label">
                        Acepto recibir por WhatsApp avisos administrativos de campeonatos, equipos, nóminas y partidos. No recibiré publicidad.
                    </label>
                </div>

                <div class="mb-3">
                    <label for="rol" class="form-label">¿Qué función tendrás?</label>
                    <select id="rol" class="form-select form-select-lg @error('rol') is-invalid @enderror" name="rol" required>
                        <option value="delegado" {{ old('rol') === 'delegado' ? 'selected' : '' }}>Delegado de equipo</option>
                        <option value="organizador" {{ old('rol') === 'organizador' ? 'selected' : '' }}>Organizador de campeonatos</option>
                    </select>
                    <div class="form-text text-white-50">Si solo quieres consultar campeonatos, no necesitas crear una cuenta.</div>
                    @error('rol')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="password" class="form-label">Contraseña</label>
                    <input id="password" type="password" class="form-control form-control-lg @error('password') is-invalid @enderror" name="password" required autocomplete="new-password">
                        <div class="form-text">Utiliza al menos 8 caracteres.</div>
                        @error('password')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="password-confirm" class="form-label">Confirmar contraseña</label>
                        <input id="password-confirm" type="password" class="form-control form-control-lg" name="password_confirmation" required autocomplete="new-password">
                    </div>
                </div>

                <button type="submit" class="btn btn-naranja btn-lg w-100"><i class="bi bi-check-circle me-2"></i>Crear mi cuenta</button>
                <a href="{{ route('publico.index') }}" class="btn btn-borde btn-lg w-100 mt-3"><i class="bi bi-eye me-2"></i>Solo quiero ver campeonatos</a>
                <p class="text-center mt-4 mb-0 opacity-75">¿Ya tienes cuenta? <a href="{{ route('login') }}" class="fw-bold text-accent">Inicia sesión</a></p>
            </form>
        </div>
    </div>
</div>
@endsection
