<?php

namespace App\Http\Controllers;

use App\Models\Incidencia;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\User;
use App\Services\MarcadorPartidoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Registra goles y tarjetas usando la nómina aprobada del partido. */
class IncidenciaController extends Controller
{
    public function __construct(private MarcadorPartidoService $marcadorService)
    {
        $this->middleware('auth');
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'partido_id' => ['required', 'exists:partidos,id'],
            'jugador_id' => ['required', 'exists:jugadores,id'],
            'tipo' => ['required', 'in:gol,amarilla,roja'],
        ]);

        $partido = Partido::findOrFail($datos['partido_id']);
        $jugador = Jugador::findOrFail($datos['jugador_id']);
        $user = $request->user();
        $this->autorizarGestion($partido, $user);
        abort_if($partido->estado !== 'programado', 422, 'El partido ya fue finalizado.');

        $nomina = $partido->nominas()->where('estado', 'aprobada')
            ->whereHas('jugadores', fn ($query) => $query->where('jugador_id', $jugador->id))
            ->with(['jugadores' => fn ($query) => $query->where('jugador_id', $jugador->id)])
            ->first();
        if (! $nomina) {
            return back()->withErrors(['jugador_id' => 'El jugador no figura en una nómina aprobada para este partido.']);
        }
        $tipo = $this->resolverTipo($partido, $jugador, $datos['tipo']);

        DB::transaction(function () use ($partido, $jugador, $nomina, $tipo, $user) {
            Incidencia::create([
                'partido_id' => $partido->id,
                'jugador_id' => $jugador->id,
                'dorsal' => $nomina->jugadores->first()->dorsal,
                'tipo' => $tipo,
                'creado_por' => $user->id,
            ]);

            $this->marcadorService->sincronizar($partido);
        });

        $mensaje = match ($tipo) {
            Incidencia::TIPO_GOL => 'Gol registrado y marcador actualizado.',
            Incidencia::TIPO_DOBLE_AMARILLA => 'Doble amarilla registrada. El jugador queda expulsado.',
            default => 'Tarjeta registrada correctamente.',
        };

        return back()->with('mensaje', $mensaje);
    }

    public function destroy(Request $request, Incidencia $incidencia)
    {
        $partido = $incidencia->partido()->firstOrFail();
        $this->autorizarGestion($partido, $request->user());
        abort_if($partido->estado !== 'programado', 422, 'No se pueden corregir incidencias de un partido finalizado.');

        DB::transaction(function () use ($partido, $incidencia) {
            $jugadorId = $incidencia->jugador_id;
            $eraAmarilla = $incidencia->tipo === Incidencia::TIPO_AMARILLA;
            $incidencia->delete();

            // Si se quitó la primera amarilla, la segunda deja de ser expulsión.
            if ($eraAmarilla) {
                Incidencia::query()
                    ->where('partido_id', $partido->id)
                    ->where('jugador_id', $jugadorId)
                    ->where('tipo', Incidencia::TIPO_DOBLE_AMARILLA)
                    ->first()?->update(['tipo' => Incidencia::TIPO_AMARILLA]);
            }

            $this->marcadorService->sincronizar($partido);
        });

        return back()->with('mensaje', 'Incidencia eliminada y marcador actualizado.');
    }

    private function resolverTipo(Partido $partido, Jugador $jugador, string $tipo): string
    {
        if (! in_array($tipo, [Incidencia::TIPO_AMARILLA, Incidencia::TIPO_ROJA], true)) {
            return $tipo;
        }

        $tarjetas = Incidencia::query()
            ->where('partido_id', $partido->id)
            ->where('jugador_id', $jugador->id)
            ->whereIn('tipo', [
                Incidencia::TIPO_AMARILLA,
                Incidencia::TIPO_DOBLE_AMARILLA,
                Incidencia::TIPO_ROJA,
            ])
            ->pluck('tipo');

        if ($tarjetas->contains(Incidencia::TIPO_DOBLE_AMARILLA) || $tarjetas->contains(Incidencia::TIPO_ROJA)) {
            throw ValidationException::withMessages([
                'jugador_id' => 'El jugador ya fue expulsado en este partido.',
            ]);
        }

        if ($tipo === Incidencia::TIPO_AMARILLA && $tarjetas->contains(Incidencia::TIPO_AMARILLA)) {
            return Incidencia::TIPO_DOBLE_AMARILLA;
        }

        return $tipo;
    }

    private function autorizarGestion(Partido $partido, User $user): void
    {
        $permitido = $user->esDesarrollador()
            || ($user->rol === User::ROLE_ORGANIZADOR && (int) $partido->campeonato->usuario_creador === (int) $user->id)
            || ($user->rol === User::ROLE_ARBITRO && (int) $partido->arbitro_id === (int) $user->id);

        abort_unless($permitido, 403, 'No tienes permisos para gestionar incidencias.');
    }
}
