<?php

namespace App\Http\Controllers;

use App\Models\Campeonato;
use App\Models\Partido;
use App\Models\User;
use App\Services\ClasificacionGruposService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PartidoWalkoverController extends Controller
{
    public function store(Request $request, Partido $partido)
    {
        $user = $request->user();
        abort_unless($user->esDesarrollador()
            || ($user->rol === User::ROLE_ORGANIZADOR && (int) $partido->campeonato->usuario_creador === (int) $user->id), 403);
        abort_unless($partido->campeonato->aprobacion_estado === 'aprobado', 403);
        $datos = $request->validate([
            'equipo_perdedor_id' => ['required', 'integer', Rule::in([$partido->equipo_local_id, $partido->equipo_visita_id])],
            'motivo' => ['required', Rule::in(['inasistencia', 'jugadores_insuficientes'])],
        ]);

        DB::transaction(function () use ($partido, $datos, $user) {
            $campeonato = Campeonato::whereKey($partido->campeonato_id)->lockForUpdate()->firstOrFail();
            $partido = Partido::whereKey($partido->id)->lockForUpdate()->firstOrFail();
            if ($partido->estado !== 'programado') {
                throw ValidationException::withMessages(['wo' => 'El partido ya tiene un resultado registrado.']);
            }
            if (! $partido->equipo_local_id || ! $partido->equipo_visita_id) {
                throw ValidationException::withMessages(['wo' => 'Primero deben estar definidos los dos equipos.']);
            }
            if ($partido->incidencias()->exists()) {
                throw ValidationException::withMessages(['wo' => 'El partido tiene incidencias registradas. Revisa esas incidencias antes de declarar un W.O. por falta de presentación.']);
            }
            $pierdeLocal = (int) $datos['equipo_perdedor_id'] === (int) $partido->equipo_local_id;
            $partido->update([
                'estado' => 'jugado',
                'goles_local' => $pierdeLocal ? 0 : 3,
                'goles_visita' => $pierdeLocal ? 3 : 0,
                'wo_motivo' => $datos['motivo'],
                'wo_equipo_perdedor_id' => (int) $datos['equipo_perdedor_id'],
                'wo_registrado_por' => $user->id,
                'wo_registrado_at' => now(),
            ]);
            app(ClasificacionGruposService::class)->generar($campeonato);
            app(\App\Services\ResolucionEliminatoriaService::class)->avanzar($partido);
        });

        return redirect()->route('partidos.nominas.index', $partido)
            ->with('mensaje', 'W.O. registrado: victoria 3–0 para el equipo contrario. El partido quedó finalizado.');
    }
}