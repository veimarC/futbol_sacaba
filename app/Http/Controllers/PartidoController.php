<?php

namespace App\Http\Controllers;

use App\Models\Campeonato;
use App\Models\Partido;
use App\Models\User;
use App\Notifications\NotificacionPlataforma;
use App\Services\MarcadorPartidoService;
use App\Services\NotificadorWhatsAppPartido;
use Illuminate\Http\Request;

class PartidoController extends Controller
{
    public function __construct(
        private MarcadorPartidoService $marcadorService,
        private NotificadorWhatsAppPartido $notificadorPartido,
    ) {
        $this->middleware('auth');
    }

    public function create(Request $request)
    {
        $this->autorizarOrganizacion();
        $user = $request->user();
        $campeonatos = Campeonato::noFinalizadosActuales()
            ->when(! $user->esDesarrollador(), fn ($q) => $q->where('usuario_creador', $user->id))->latest()->get();
        $campeonatoSeleccionado = $request->query('campeonato');
        $equipos = collect();
        if ($campeonatoSeleccionado) {
            $campeonato = Campeonato::findOrFail($campeonatoSeleccionado);
            $this->autorizarCampeonato($campeonato);
            $equipos = $campeonato->equipos()->wherePivot('estado', 'aprobado')->orderBy('nombre')->get();
        }
        $arbitros = isset($campeonato)
            ? $campeonato->arbitros()->wherePivot('estado', 'aprobado')->orderBy('name')->get()
            : collect();
        return view('partidos.create', compact('campeonatos', 'campeonatoSeleccionado', 'equipos', 'arbitros'));
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);
        $campeonato = Campeonato::findOrFail($data['campeonato_id']);
        $this->autorizarCampeonato($campeonato);
        if ($campeonato->formato === 'grupos_eliminacion' && $campeonato->fases()->where('tipo', 'grupo')->exists()) {
            return back()->withErrors([
                'campeonato_id' => 'La Copa ya tiene su fixture por grupos. Edita los partidos generados para asignar fecha, cancha y árbitro.',
            ])->withInput();
        }
        if (! $this->equiposValidos($campeonato, $data)) return back()->withErrors(['campeonato_id' => 'Ambos equipos deben estar aprobados en el campeonato.'])->withInput();
        if (! $this->arbitroValido($campeonato, $data)) return back()->withErrors(['arbitro_id' => 'Selecciona un árbitro aprobado en este campeonato.'])->withInput();
        $partido = Partido::create($this->datosPartido($data));
        $this->notificarArbitro($partido, 'Nuevo partido asignado');
        $this->notificadorPartido->programacion($partido);
        return redirect()->route('campeonatos.show', $campeonato)->with('mensaje', 'Partido programado.');
    }

    public function edit(Partido $partido)
    {
        $this->autorizarCampeonato($partido->campeonato);
        abort_if($partido->estado !== 'programado', 422, 'Solo se editan partidos programados.');
        $user = auth()->user();
        $campeonatos = Campeonato::noFinalizadosActuales()->when(! $user->esDesarrollador(), fn ($q) => $q->where('usuario_creador', $user->id))->get();
        $equipos = $partido->campeonato->equipos()->wherePivot('estado', 'aprobado')
            ->when($partido->fase?->tipo === 'grupo', fn ($consulta) => $consulta->whereIn(
                'equipos.id',
                data_get($partido->fase->metadata, 'teams', []),
            ))
            ->get();
        $arbitros = $partido->campeonato->arbitros()->wherePivot('estado', 'aprobado')->orderBy('name')->get();
        return view('partidos.edit', compact('partido', 'campeonatos', 'equipos', 'arbitros'));
    }

    public function update(Request $request, Partido $partido)
    {
        $this->autorizarCampeonato($partido->campeonato);
        if ($partido->estado !== 'programado') return back()->withErrors(['error' => 'El partido ya no puede modificarse.']);
        $data = $this->validar($request);
        $campeonato = Campeonato::findOrFail($data['campeonato_id']);
        $this->autorizarCampeonato($campeonato);
        if (! $this->equiposValidos($campeonato, $data)) return back()->withErrors(['campeonato_id' => 'Los equipos no están aprobados.'])->withInput();
        if (! $this->equiposPertenecenALaFase($partido, $data)) {
            return back()->withErrors([
                'equipo_visita_id' => 'En la fase de grupos solo puedes enfrentar equipos del mismo grupo.',
            ])->withInput();
        }
        if (! $this->arbitroValido($campeonato, $data)) return back()->withErrors(['arbitro_id' => 'Selecciona un árbitro aprobado en este campeonato.'])->withInput();
        $arbitroAnterior = $partido->arbitro_id;
        $partido->update($this->datosPartido($data));
        $programacionCambio = $partido->wasChanged([
            'equipo_local_id',
            'equipo_visita_id',
            'fecha',
            'hora',
            'cancha',
        ]);
        if ($partido->arbitro_id && ((int) $arbitroAnterior !== (int) $partido->arbitro_id || $partido->wasChanged(['fecha', 'hora', 'cancha']))) {
            $this->notificarArbitro($partido, $arbitroAnterior ? 'Partido actualizado' : 'Nuevo partido asignado');
        }
        if ($programacionCambio) {
            $partido->update(['whatsapp_recordatorio_enviado_at' => null]);
            $this->notificadorPartido->programacion($partido);
        }
        return redirect()->route('campeonatos.show', $campeonato)->with('mensaje', 'Partido actualizado.');
    }

    public function destroy(Partido $partido)
    {
        $this->autorizarCampeonato($partido->campeonato);
        if ($partido->estado !== 'programado') return back()->withErrors(['error' => 'No se puede eliminar un partido jugado.']);
        $campeonato = $partido->campeonato;
        $partido->delete();
        return redirect()->route('campeonatos.show', $campeonato)->with('mensaje', 'Partido eliminado.');
    }

    public function resultado(Partido $partido)
    {
        $this->autorizarResultado($partido);
        if ($partido->estado !== 'programado') return back()->withErrors(['error' => 'El partido ya tiene resultado.']);
        $partido->load([
            'nominas' => fn ($query) => $query->where('estado', 'aprobada'),
            'nominas.jugadores.jugador',
            'incidencias.jugador.equipo',
            'equipoLocal',
            'equipoVisita',
        ]);
        $jugadoresPorEquipo = collect([
            ['equipo' => $partido->equipoLocal, 'condicion' => 'Local'],
            ['equipo' => $partido->equipoVisita, 'condicion' => 'Visita'],
        ])->map(function (array $grupo) use ($partido) {
            $nomina = $partido->nominas->firstWhere('equipo_id', $grupo['equipo']->id);
            $grupo['jugadores'] = $nomina?->jugadores->sortBy('dorsal')->values() ?? collect();

            return $grupo;
        });

        return view('partidos.resultado', compact('partido', 'jugadoresPorEquipo'));
    }

    public function guardarResultado(Request $request, Partido $partido)
    {
        $this->autorizarResultado($partido);
        if ($partido->estado !== 'programado') return back()->withErrors(['error' => 'El resultado ya fue registrado.']);
        if ($partido->nominas()->where('estado', 'aprobada')->count() !== 2) {
            return back()->withErrors(['error' => 'La Mesa de control debe aprobar las dos nóminas antes de registrar el resultado.']);
        }
        $this->marcadorService->sincronizar($partido);
        $partido->update(['estado' => 'jugado']);
        User::find($partido->campeonato->usuario_creador)?->notify(new NotificacionPlataforma(
            'Resultado registrado',
            "Se registró el resultado de {$partido->equipoLocal->nombre} vs {$partido->equipoVisita->nombre}.",
            route('campeonatos.show', $partido->campeonato),
            'bi-clipboard-check',
            'success',
        ));
        return redirect()->route('partidos.nominas.index', $partido)->with('mensaje', 'Resultado guardado correctamente.');
    }

    public function equiposPorCampeonato(Campeonato $campeonato)
    {
        return response()->json($campeonato->equipos()->wherePivot('estado', 'aprobado')->get(['equipos.id', 'equipos.nombre']));
    }

    private function validar(Request $request): array
    {
        return $request->validate(['campeonato_id' => 'required|exists:campeonatos,id', 'equipo_local_id' => 'required|exists:equipos,id|different:equipo_visita_id', 'equipo_visita_id' => 'required|exists:equipos,id', 'fecha' => 'required|date', 'hora' => 'required', 'lugar' => 'required|string|max:255', 'arbitro_id' => 'nullable|exists:users,id']);
    }

    private function datosPartido(array $data): array
    {
        return ['campeonato_id' => $data['campeonato_id'], 'equipo_local_id' => $data['equipo_local_id'], 'equipo_visita_id' => $data['equipo_visita_id'], 'fecha' => $data['fecha'], 'hora' => $data['hora'], 'cancha' => $data['lugar'], 'arbitro_id' => $data['arbitro_id'] ?? null];
    }

    private function equiposValidos(Campeonato $campeonato, array $data): bool
    {
        return $campeonato->equipos()->wherePivot('estado', 'aprobado')->whereIn('equipos.id', [$data['equipo_local_id'], $data['equipo_visita_id']])->count() === 2;
    }

    private function equiposPertenecenALaFase(Partido $partido, array $data): bool
    {
        if ($partido->fase?->tipo !== 'grupo') return true;

        $equiposGrupo = collect(data_get($partido->fase->metadata, 'teams', []))
            ->map(fn ($id) => (int) $id);

        return $equiposGrupo->contains((int) $data['equipo_local_id'])
            && $equiposGrupo->contains((int) $data['equipo_visita_id']);
    }

    private function arbitroValido(Campeonato $campeonato, array $data): bool
    {
        if (empty($data['arbitro_id'])) return true;

        return $campeonato->arbitros()
            ->wherePivot('estado', 'aprobado')
            ->where('users.id', $data['arbitro_id'])
            ->exists();
    }

    private function autorizarOrganizacion(): void
    {
        abort_unless(auth()->user()->hasRole([User::ROLE_ORGANIZADOR, User::ROLE_DESARROLLADOR]), 403);
    }

    private function autorizarCampeonato(Campeonato $campeonato): void
    {
        $user = auth()->user();
        abort_unless($user->esDesarrollador() || ($user->rol === User::ROLE_ORGANIZADOR && (int) $campeonato->usuario_creador === (int) $user->id), 403);
    }

    private function autorizarResultado(Partido $partido): void
    {
        $user = auth()->user();
        $permitido = $user->esDesarrollador()
            || ($user->rol === User::ROLE_ORGANIZADOR && (int) $partido->campeonato->usuario_creador === (int) $user->id)
            || ($user->rol === User::ROLE_ARBITRO && (int) $partido->arbitro_id === (int) $user->id);
        abort_unless($permitido, 403);
    }

    private function notificarArbitro(Partido $partido, string $titulo): void
    {
        if (! $partido->arbitro) return;
        $fecha = $partido->fecha?->format('d/m/Y') ?? 'fecha por confirmar';
        $partido->arbitro->notify(new NotificacionPlataforma(
            $titulo,
            "Dirigirás {$partido->equipoLocal->nombre} vs {$partido->equipoVisita->nombre} el {$fecha}.",
            route('campeonatos.show', $partido->campeonato),
            'bi-flag',
            'info',
        ));
    }
}
