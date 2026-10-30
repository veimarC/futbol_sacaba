<?php

namespace App\Http\Controllers;

use App\Models\Campeonato;
use App\Models\Fase;
use App\Models\Partido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador para generar fases eliminatorias.
 *
 * Se utiliza cuando un campeonato con grupos ya finalizados necesita
 * crear automáticamente la siguiente etapa eliminatoria.
 * Ruta clave:
 *  - campeonatos/{campeonato}/generar-eliminatoria
 */
class CampeonatoEliminatoriaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Generar fase eliminatoria a partir de fases de grupo cuando estén finalizadas.
     * Reglas:
     * - Se espera que existan fases tipo 'grupo' con metadata['teams'] = [ids]
     * - formato_config debe contener 'qualifiers_per_group' y 'elimination_start'
     * - Emparejamientos predefinidos: para cada par de grupos (A,B), crear: 1A vs 2B y 1B vs 2A
     */
    public function generar(Campeonato $campeonato, Request $request)
    {
        abort_unless($campeonato->aprobacion_estado === 'aprobado', 403);
        $user = auth()->user();
        if (!($user->rol === \App\Models\User::ROLE_DESARROLLADOR || ($user->rol === \App\Models\User::ROLE_ORGANIZADOR && $campeonato->usuario_creador == $user->id))) {
            abort(403);
        }

        $generada = app(\App\Services\ClasificacionGruposService::class)->generar($campeonato);
        if (! $generada) {
            return back()->withErrors(['error' => 'La eliminatoria ya existe o los grupos y su configuración todavía no están listos.']);
        }
        return redirect()->route('campeonatos.show', $campeonato)->with('mensaje', 'Fase eliminatoria generada con los clasificados de grupos.');
    }
}