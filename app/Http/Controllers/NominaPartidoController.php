<?php

namespace App\Http\Controllers;

use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\NominaPartido;
use App\Models\Partido;
use App\Models\User;
use App\Notifications\NotificacionPlataforma;
use App\Services\SuspensionJugadorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NominaPartidoController extends Controller
{
    public function __construct(private SuspensionJugadorService $suspensiones)
    {
        $this->middleware('auth');
    }

    public function index(Partido $partido)
    {
        $this->autorizarConsulta($partido);
        $partido->load(['campeonato', 'equipoLocal', 'equipoVisita', 'nominas.jugadores.jugador', 'incidencias.jugador', 'fase']);
        $equipos = collect([$partido->equipoLocal, $partido->equipoVisita]);
        $usuario = auth()->user();
        $puedeRegistrarResultado = $usuario->esDesarrollador()
            || ($usuario->rol === User::ROLE_ORGANIZADOR && (int) $partido->campeonato->usuario_creador === (int) $usuario->id);
        $nominasAprobadas = $partido->nominas->where('estado', 'aprobada')->count();

        $desempate = app(\App\Services\ResolucionEliminatoriaService::class)->contexto($partido);
        return view('partidos.nominas', compact('partido', 'equipos', 'puedeRegistrarResultado', 'nominasAprobadas', 'desempate'));
    }

    public function pdf(Partido $partido, \App\Services\DocumentoPdfService $pdf)
    {
        $this->autorizarMesa($partido);
        $partido->load(['campeonato', 'equipoLocal', 'equipoVisita', 'nominas.jugadores.jugador', 'incidencias', 'fase']);
        $equipos = collect([$partido->equipoLocal, $partido->equipoVisita]);
        return $pdf->descargar('pdf.planilla', compact('partido', 'equipos'), "planilla-campeonato-{$partido->campeonato_id}-partido-{$partido->numero_campeonato}.pdf");
    }

    public function editar(Partido $partido, Equipo $equipo)
    {
        $this->autorizarEdicion($partido, $equipo);
        abort_if($partido->estado !== 'programado', 422, 'La nómina ya no puede modificarse.');

        $jugadores = $equipo->jugadores()->where('estado', Jugador::ESTADO_APROBADO)->orderBy('nombre')->get();
        $nomina = $partido->nominas()->where('equipo_id', $equipo->id)->with('jugadores')->first();
        $esMesa = $this->esMesa($partido);
        abort_if($nomina?->estado === 'aprobada' && ! $esMesa, 422, 'La Mesa de control ya aprobó esta nómina.');
        $dorsales = $nomina?->jugadores->pluck('dorsal', 'jugador_id') ?? collect();
        $suspendidos = $jugadores->filter(fn ($jugador) => $this->suspensiones->estaSuspendidoPara($jugador, $partido))->pluck('id');

        return view('partidos.nomina-editar', compact('partido', 'equipo', 'jugadores', 'nomina', 'dorsales', 'suspendidos'));
    }

    public function guardar(Request $request, Partido $partido, Equipo $equipo)
    {
        $this->autorizarEdicion($partido, $equipo);
        abort_if($partido->estado !== 'programado', 422, 'La nómina ya no puede modificarse.');

        $datos = $request->validate([
            'dorsales' => ['required', 'array'],
            'dorsales.*' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);
        $seleccionados = collect($datos['dorsales'])
            ->filter(fn ($dorsal) => $dorsal !== null && $dorsal !== '')
            ->mapWithKeys(fn ($dorsal, $jugadorId) => [(int) $jugadorId => (int) $dorsal]);

        if ($seleccionados->isEmpty()) {
            return back()->withErrors(['dorsales' => 'Selecciona al menos un jugador.'])->withInput();
        }
        if ($seleccionados->duplicates()->isNotEmpty()) {
            return back()->withErrors(['dorsales' => 'No puedes repetir un dorsal dentro del mismo equipo.'])->withInput();
        }

        $jugadores = $equipo->jugadores()->where('estado', Jugador::ESTADO_APROBADO)
            ->whereIn('id', $seleccionados->keys())->get();
        if ($jugadores->count() !== $seleccionados->count()) {
            return back()->withErrors(['dorsales' => 'La nómina contiene un jugador que no pertenece al equipo.'])->withInput();
        }
        if ($jugadores->contains(fn ($jugador) => $this->suspensiones->estaSuspendidoPara($jugador, $partido))) {
            return back()->withErrors(['dorsales' => 'No puedes convocar a un jugador suspendido.'])->withInput();
        }

        $esMesa = $this->esMesa($partido);
        if ($esMesa && $seleccionados->count() < $partido->campeonato->minimo_para_jugar) {
            return back()->withErrors(['dorsales' => "La nómina necesita al menos {$partido->campeonato->minimo_para_jugar} jugadores."])->withInput();
        }

        DB::transaction(function () use ($partido, $equipo, $seleccionados, $esMesa) {
            $actual = Partido::whereKey($partido->id)->lockForUpdate()->firstOrFail();
            abort_if($actual->estado !== 'programado', 422, 'La nómina ya no puede modificarse.');
            $existente = $actual->nominas()->where('equipo_id', $equipo->id)->first();
            abort_if($existente?->estado === 'aprobada' && ! $esMesa, 422, 'La Mesa de control ya aprobó esta nómina.');
            $nomina = NominaPartido::updateOrCreate(
                ['partido_id' => $partido->id, 'equipo_id' => $equipo->id],
                [
                    'estado' => $esMesa ? 'aprobada' : 'pendiente',
                    'motivo_respuesta' => null,
                    'enviado_at' => now(),
                    'aprobado_at' => $esMesa ? now() : null,
                    'aprobado_por' => $esMesa ? auth()->id() : null,
                ]
            );
            $nomina->jugadores()->delete();
            foreach ($seleccionados as $jugadorId => $dorsal) {
                $nomina->jugadores()->create(['jugador_id' => $jugadorId, 'dorsal' => $dorsal]);
            }
        });

        if ($esMesa) {
            $equipo->delegado?->notify(new NotificacionPlataforma(
                'Nómina registrada por la Mesa',
                "La Mesa de control registró la nómina de {$equipo->nombre}.",
                route('partidos.nominas.index', $partido),
                'bi-clipboard-check',
                'success',
            ));
        } else {
            User::find($partido->campeonato->usuario_creador)?->notify(new NotificacionPlataforma(
                'Nómina pendiente de revisión',
                "{$equipo->nombre} envió la nómina para su partido.",
                route('partidos.nominas.index', $partido),
                'bi-clipboard-check',
                'warning',
            ));
        }

        return redirect()->route('partidos.nominas.index', $partido)->with(
            'mensaje',
            $esMesa ? 'Nómina registrada y aprobada.' : 'Nómina enviada a la Mesa de control.'
        );
    }

    public function gestionar(Request $request, Partido $partido, NominaPartido $nomina)
    {
        $this->autorizarMesa($partido);
        abort_if($partido->estado !== 'programado', 422, 'El partido ya fue finalizado.');
        abort_unless((int) $nomina->partido_id === (int) $partido->id, 404);
        $datos = $request->validate([
            'estado' => ['required', 'in:aprobada,rechazada'],
            'motivo_respuesta' => ['nullable', 'string', 'max:500'],
        ]);
        if ($datos['estado'] === 'aprobada' && $nomina->jugadores()->count() < $partido->campeonato->minimo_para_jugar) {
            return back()->withErrors(['error' => "La nómina necesita al menos {$partido->campeonato->minimo_para_jugar} jugadores."]);
        }

        $nomina->update([
            'estado' => $datos['estado'],
            'motivo_respuesta' => $datos['motivo_respuesta'] ?? null,
            'aprobado_at' => $datos['estado'] === 'aprobada' ? now() : null,
            'aprobado_por' => $datos['estado'] === 'aprobada' ? auth()->id() : null,
        ]);

        $nomina->equipo->delegado?->notify(new NotificacionPlataforma(
            $datos['estado'] === 'aprobada' ? 'Nómina aprobada' : 'Nómina observada',
            "La Mesa de control revisó la nómina de {$nomina->equipo->nombre}.",
            route('partidos.nominas.index', $partido),
            'bi-clipboard-check',
            $datos['estado'] === 'aprobada' ? 'success' : 'danger',
        ));

        return back()->with('mensaje', $datos['estado'] === 'aprobada' ? 'Nómina aprobada.' : 'Nómina devuelta para corrección.');
    }

    private function autorizarConsulta(Partido $partido): void
    {
        $user = auth()->user();
        $equipos = [(int) $partido->equipo_local_id, (int) $partido->equipo_visita_id];
        $delegadoParticipante = $user->rol === User::ROLE_DELEGADO
            && Equipo::whereIn('id', $equipos)->where('delegado_id', $user->id)->exists();
        $permitido = $user->esDesarrollador()
            || ($user->rol === User::ROLE_ORGANIZADOR && (int) $partido->campeonato->usuario_creador === (int) $user->id)
            || $delegadoParticipante;
        abort_unless($permitido, 403);
    }

    private function autorizarEdicion(Partido $partido, Equipo $equipo): void
    {
        abort_unless(in_array((int) $equipo->id, [(int) $partido->equipo_local_id, (int) $partido->equipo_visita_id], true), 404);
        $user = auth()->user();
        abort_unless(
            $this->esMesa($partido) || ($user->rol === User::ROLE_DELEGADO && (int) $equipo->delegado_id === (int) $user->id),
            403
        );
    }

    private function autorizarMesa(Partido $partido): void
    {
        abort_unless($this->esMesa($partido), 403);
    }

    private function esMesa(Partido $partido): bool
    {
        $user = auth()->user();

        return $user->esDesarrollador()
            || ($user->rol === User::ROLE_ORGANIZADOR && (int) $partido->campeonato->usuario_creador === (int) $user->id);
    }
}
