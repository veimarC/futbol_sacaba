<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    protected function sendFailedLoginResponse(\Illuminate\Http\Request $request)
    {
        $existe = \App\Models\User::where('email', $request->input('email'))->exists();

        throw \Illuminate\Validation\ValidationException::withMessages(
            $existe
                ? ['password' => 'La contraseña es incorrecta. Inténtalo nuevamente.']
                : ['email' => 'No existe una cuenta con este correo. Revisa el correo o regístrate.']
        );
    }

    protected function validateLogin(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Ingresa tu correo electrónico.',
            'email.email' => 'Escribe un correo válido, por ejemplo: nombre@correo.com.',
            'password.required' => 'Ingresa tu contraseña.',
        ]);
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }
}
