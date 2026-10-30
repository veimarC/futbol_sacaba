<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Envía a cada usuario directamente a su área de trabajo.
 * La ruta /home se conserva porque Laravel la usa después del acceso y registro.
 */
class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $destino = match ($request->user()->rol) {
            User::ROLE_DESARROLLADOR,
            User::ROLE_ORGANIZADOR,
            User::ROLE_DELEGADO => 'campeonatos.index',
            default => 'publico.index',
        };

        return redirect()->route($destino);
    }
}
