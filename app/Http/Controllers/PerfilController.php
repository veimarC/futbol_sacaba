<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/** Permite que el usuario consulte y actualice sus propios datos. */
class PerfilController extends Controller
{
    /** Muestra el formulario del usuario que tiene la sesión iniciada. */
    public function edit(Request $request)
    {
        return view('perfil.edit', ['usuario' => $request->user()]);
    }

    /** Actualiza nombre, correo y, opcionalmente, la contraseña. */
    public function update(Request $request)
    {
        $usuario = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario->id)],
            'whatsapp' => ['nullable', 'regex:/^[67][0-9]{7}$/'],
            'whatsapp_notifications' => ['nullable', 'boolean'],
            'current_password' => ['nullable', 'required_with:password', 'string'],
            'password' => ['nullable', 'required_with:current_password', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.required_with' => 'Escribe tu contraseña actual para establecer una nueva.',
            'password.required_with' => 'Escribe y confirma tu nueva contraseña.',
            'password.confirmed' => 'La confirmación de la nueva contraseña no coincide.',
            'password.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'whatsapp.regex' => 'Ingresa un celular boliviano válido de 8 dígitos, por ejemplo 70000000.',
        ], [
            'name' => 'nombre',
            'email' => 'correo electrónico',
            'whatsapp' => 'número de WhatsApp',
            'current_password' => 'contraseña actual',
            'password' => 'nueva contraseña',
        ]);

        if (! empty($data['password']) && ! Hash::check($data['current_password'], $usuario->password)) {
            return back()->withErrors([
                'current_password' => 'La contraseña actual no es correcta.',
            ])->withInput($request->except(['current_password', 'password', 'password_confirmation']));
        }

        $usuario->name = $data['name'];
        $usuario->email = $data['email'];
        $usuario->whatsapp = $data['whatsapp'] ?? null;
        $usuario->whatsapp_opt_in_at = ! empty($data['whatsapp']) && ! empty($data['whatsapp_notifications'])
            ? ($usuario->whatsapp_opt_in_at ?: now())
            : null;
        if (! empty($data['password'])) {
            $usuario->password = Hash::make($data['password']);
        }
        $usuario->save();

        return redirect()->route('perfil.edit')->with('mensaje', 'Perfil actualizado correctamente.');
    }
}
