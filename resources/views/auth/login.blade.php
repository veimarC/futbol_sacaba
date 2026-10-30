@extends('layouts.app')

@section('content')
<div class="row justify-content-center mt-5">
    <div class="col-md-5">
        <div class="contenedor-transparente animar-aparecer">
            <h2 class="text-center mb-4 fw-bold">
                <i class="bi bi-box-arrow-in-right me-2"></i> Iniciar Sesión
            </h2>

            <form method="POST" action="{{ route('login') }}" data-login-validation>
                @csrf
                <p class="required-note">Ingresa el correo y la contraseña de tu cuenta.</p>

                <div class="mb-3">
                    <label for="email" class="form-label">Correo Electrónico</label>
                    <input id="email" type="email" class="form-control form-control-lg @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
                    <div id="login-email-error" class="invalid-feedback" aria-live="polite">@error('email'){{ $message }}@enderror</div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label">Contraseña</label>
                    <div class="input-group has-validation">
                    <input id="password" type="password" class="form-control form-control-lg @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">
                        <button type="button" class="btn btn-light border" data-password-toggle="password" aria-controls="password" aria-label="Mostrar contraseña" aria-pressed="false" title="Mostrar contraseña">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                        <div id="login-password-error" class="invalid-feedback" aria-live="polite">@error('password'){{ $message }}@enderror</div>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-naranja btn-lg">
                        <i class="bi bi-lock-fill me-2"></i> Ingresar
                    </button>
                </div>

                <div class="text-center mt-4">
                    <p class="mb-0 opacity-90">¿No tienes cuenta? <a href="{{ route('register') }}" class="fw-bold text-accent">Regístrate aquí</a></p>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
