<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    use RegistersUsers;

    protected $redirectTo = '/home';

    public function __construct()
    {
        $this->middleware('guest');
    }

    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'whatsapp' => ['nullable', 'regex:/^[67][0-9]{7}$/'],
            'whatsapp_notifications' => ['nullable', 'boolean'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            // Permitimos ahora que el usuario seleccione 'organizador' desde el formulario de registro.
            // Atención: permitir este rol en el registro público significa que cualquiera puede crearse como organizador.
            // Se recomienda añadir un flujo de aprobación en producción si se quiere restringir este rol.
            'rol' => ['required', 'in:delegado,organizador'],
        ], [
            'whatsapp.regex' => 'Ingresa un celular boliviano válido de 8 dígitos, por ejemplo 70000000.',
        ]);
    }

    protected function create(array $data)
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'whatsapp' => $data['whatsapp'] ?? null,
            'whatsapp_opt_in_at' => ! empty($data['whatsapp']) && ! empty($data['whatsapp_notifications']) ? now() : null,
            'password' => Hash::make($data['password']),
            'rol' => $data['rol'],
        ]);
    }
}
