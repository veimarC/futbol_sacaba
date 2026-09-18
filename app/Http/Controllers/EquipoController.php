<?php

namespace App\Http\Controllers;

use App\Models\Campeonato;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\User;
use App\Notifications\NotificacionPlataforma;
use App\Services\CloudinaryImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class EquipoController extends Controller
{
    public function __construct(private readonly CloudinaryImageService $imagenes)
    {
        $this->middleware('auth');
        $this->authorizeResource(Equipo::class, 'equipo', ['except' => ['store', 'solicitarInscripcion', 'gestionarSolicitud']]);
    }

    /** Lista los equipos propios; el desarrollador puede inspeccionarlos todos. */
    public function index()
    {
        $this->authorize('viewAny', Equipo::class);

        $user = auth()->user();
        $equipos = Equipo::query()
            ->when(! $user->esDesarrollador(), fn ($query) => $query->where('delegado_id', $user->id))
            ->with(['campeonatos' => fn ($query) => $query->orderByDesc('campeonato_equipo.created_at')])
            ->withCount('jugadores')
            ->latest()
            ->get();

        return view('equipos.index', compact('equipos'));
    }

    public function create(Request $request)
    {
        $campeonatos = $this->campeonatosAbiertos()->get();
        $campeonatoSeleccionado = $request->query('campeonato');
        $campeonatoElegido = $campeonatoSeleccionado
            ? $campeonatos->firstWhere('id', (int) $campeonatoSeleccionado)
            : null;

        if ($campeonatoSeleccionado && ! $campeonatoElegido) {
            return redirect()->route('campeonatos.descubrir')
                ->withErrors(['campeonato_id' => 'El campeonato elegido ya no recibe inscripciones.']);
        }
        if ($campeonatoElegido && $this->delegadoYaParticipa($campeonatoElegido, auth()->id())) {
            return redirect()->route('campeonatos.show', $campeonatoElegido)
                ->withErrors(['campeonato_id' => 'Ya participas con un equipo en este campeonato. Puedes inscribirte en otro campeonato diferente.']);
        }

        return view('equipos.create', compact('campeonatos', 'campeonatoSeleccionado', 'campeonatoElegido'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Equipo::class);
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'ciudad' => 'nullable|string|max:255',
            'escudo' => 'nullable|image|max:2048',
            'campeonato_id' => 'nullable|exists:campeonatos,id',
        ]);

        $campeonato = $request->filled('campeonato_id') ? Campeonato::findOrFail($data['campeonato_id']) : null;
        if ($campeonato && ! $this->aceptaInscripciones($campeonato)) {
            return back()->withErrors(['campeonato_id' => 'Las inscripciones están cerradas.'])->withInput();
        }
        if ($campeonato && $this->delegadoYaParticipa($campeonato, auth()->id())) {
            return back()->withErrors(['campeonato_id' => 'Ya tienes un equipo inscrito o pendiente en este campeonato. Puedes participar en otros campeonatos, pero no repetir en el mismo.'])->withInput();
        }

        $nuevoEscudoCloudinary = null;
        if ($request->hasFile('escudo')) {
            try {
                $subida = $this->imagenes->subir($request->file('escudo'), 'escudos');
                $nuevoEscudoCloudinary = $subida['public_id'];
            } catch (Throwable $error) {
                Log::warning('No se pudo subir el escudo del equipo a Cloudinary.', [
                    'error' => $error->getMessage(),
                ]);

                return back()->withErrors([
                    'escudo' => 'No se pudo subir el escudo. Inténtalo nuevamente.',
                ])->withInput();
            }
        }

        try {
            $equipo = DB::transaction(function () use ($data, $campeonato, $nuevoEscudoCloudinary) {
                $equipo = Equipo::create([
                    'nombre' => $data['nombre'],
                // Mantiene poblada la columna histórica mientras la relación
                // campeonato_equipo sigue siendo la fuente principal.
                    'campeonato_id' => $campeonato?->id,
                    'ciudad' => $data['ciudad'] ?? null,
                    'delegado_id' => auth()->id(),
                    'escudo' => null,
                    'escudo_cloudinary_id' => $nuevoEscudoCloudinary,
                ]);
                if ($campeonato) {
                    $equipo->campeonatos()->attach($campeonato->id, ['estado' => 'pendiente']);
                }

                return $equipo;
            });
        } catch (Throwable $error) {
            $this->eliminarImagenCloudinarySinInterrumpir($nuevoEscudoCloudinary);

            throw $error;
        }

        if ($campeonato && ($organizador = User::find($campeonato->usuario_creador))) {
            $organizador->notify(new NotificacionPlataforma(
                'Nueva solicitud de equipo',
                "{$equipo->nombre} solicita participar en {$campeonato->nombre}.",
                route('campeonatos.show', $campeonato),
                'bi-shield-plus',
                'warning',
                whatsappTemplate: config('services.whatsapp.templates.team_request'),
                whatsappParameters: [$organizador->name, $equipo->nombre, $campeonato->nombre],
            ));
        }

        return redirect()->route('equipos.show', $equipo)->with('mensaje', 'Equipo registrado correctamente.');
    }

    public function show(Request $request, Equipo $equipo)
    {
        $equipo->load('delegado', 'jugadores');
        $campeonatos = $equipo->campeonatos()
            ->orderBy('campeonatos.fecha_inicio')
            ->orderBy('campeonatos.id')
            ->get();
        if ($campeonatos->isEmpty() && $equipo->campeonato_id !== null) {
            // Algunos registros históricos todavía no fueron migrados al
            // pivote; se muestran sin convertir esa columna en la relación
            // principal.
            $campeonatoLegacy = $equipo->campeonato()->first();
            if ($campeonatoLegacy) {
                $campeonatos = collect([$campeonatoLegacy]);
            }
        }
        $equipo->setRelation('campeonatos', $campeonatos);

        $campeonato = null;
        if ($request->filled('campeonato')) {
            $campeonato = $campeonatos->firstWhere('id', (int) $request->query('campeonato'));
            abort_unless($campeonato, 404);
        } elseif ($equipo->campeonato_id !== null) {
            // A legacy primary value is deterministic and safe to use only
            // when it is still one of the pivot registrations.
            $campeonato = $campeonatos->firstWhere('id', (int) $equipo->campeonato_id);
            if (! $campeonato && $campeonatos->isEmpty()) {
                $campeonato = $equipo->campeonato()->first();
            }
        } elseif ($campeonatos->count() === 1) {
            $campeonato = $campeonatos->first();
        }

        $goleadores = DB::table('incidencias')->join('jugadores', 'incidencias.jugador_id', '=', 'jugadores.id')
            ->select('jugadores.nombre', 'jugadores.apellido', DB::raw('COUNT(*) as goles'))
            ->where('incidencias.tipo', 'gol')->where('jugadores.equipo_id', $equipo->id)
            ->when($campeonato, fn ($query) => $query->whereExists(fn ($subquery) => $subquery
                ->select(DB::raw(1))
                ->from('partidos')
                ->whereColumn('partidos.id', 'incidencias.partido_id')
                ->where('partidos.campeonato_id', $campeonato->id)))
            ->groupBy('incidencias.jugador_id', 'jugadores.nombre', 'jugadores.apellido')->orderByDesc('goles')->get();
        $tarjetas = DB::table('incidencias')->join('jugadores', 'incidencias.jugador_id', '=', 'jugadores.id')
            ->select('jugadores.nombre', 'jugadores.apellido', DB::raw("SUM(CASE WHEN incidencias.tipo IN ('amarilla', 'doble_amarilla') THEN 1 ELSE 0 END) as amarillas"), DB::raw("SUM(CASE WHEN incidencias.tipo='roja' THEN 1 ELSE 0 END) as rojas"))
            ->whereIn('incidencias.tipo', ['amarilla', 'doble_amarilla', 'roja'])->where('jugadores.equipo_id', $equipo->id)
            ->when($campeonato, fn ($query) => $query->whereExists(fn ($subquery) => $subquery
                ->select(DB::raw(1))
                ->from('partidos')
                ->whereColumn('partidos.id', 'incidencias.partido_id')
                ->where('partidos.campeonato_id', $campeonato->id)))
            ->groupBy('incidencias.jugador_id', 'jugadores.nombre', 'jugadores.apellido')->get();

        // La revisión se habilita únicamente al organizador dueño de uno de
        // los campeonatos aprobados en los que participa este equipo.
        $organizadorGestionaEquipo = auth()->user()->rol === User::ROLE_ORGANIZADOR
            && $equipo->campeonatos()
                ->where('campeonatos.usuario_creador', auth()->id())
                ->wherePivot('estado', 'aprobado')
                ->exists();

        return view('equipos.show', compact('equipo', 'campeonatos', 'campeonato', 'goleadores', 'tarjetas', 'organizadorGestionaEquipo'));
    }

    public function edit(Request $request, Equipo $equipo)
    {
        return view('equipos.edit', [
            'equipo' => $equipo,
            'campeonatoId' => $request->query('campeonato'),
        ]);
    }

    public function update(Request $request, Equipo $equipo)
    {
        $data = $request->validate(['nombre' => 'required|string|max:255', 'ciudad' => 'nullable|string|max:255', 'escudo' => 'nullable|image|max:2048']);
        unset($data['escudo']);

        $escudoCloudinaryAnterior = $equipo->escudo_cloudinary_id;
        $escudoLocalAnterior = $equipo->escudo;
        $nuevoEscudoCloudinary = null;

        if ($request->hasFile('escudo')) {
            try {
                $subida = $this->imagenes->subir($request->file('escudo'), 'escudos');
                $nuevoEscudoCloudinary = $subida['public_id'];
                $data['escudo_cloudinary_id'] = $nuevoEscudoCloudinary;
                $data['escudo'] = null;
            } catch (Throwable $error) {
                Log::warning('No se pudo reemplazar el escudo del equipo en Cloudinary.', [
                    'equipo_id' => $equipo->id,
                    'error' => $error->getMessage(),
                ]);

                return back()->withErrors([
                    'escudo' => 'No se pudo subir el nuevo escudo. La imagen anterior se conserva.',
                ])->withInput();
            }
        }

        try {
            $equipo->update($data);
        } catch (Throwable $error) {
            $this->eliminarImagenCloudinarySinInterrumpir($nuevoEscudoCloudinary);

            throw $error;
        }

        if ($nuevoEscudoCloudinary) {
            $this->eliminarImagenCloudinarySinInterrumpir($escudoCloudinaryAnterior);
            if ($escudoLocalAnterior && Storage::disk('public')->exists($escudoLocalAnterior)) {
                Storage::disk('public')->delete($escudoLocalAnterior);
            }
        }
        $parametrosRuta = $request->input('campeonato') ?: $request->query('campeonato');

        return redirect()->route('equipos.show', array_filter([
            $equipo,
            'campeonato' => $parametrosRuta,
        ], fn ($valor) => $valor !== null && $valor !== ''))->with('mensaje', 'Equipo actualizado exitosamente.');
    }

    public function destroy(Equipo $equipo)
    {
        $dependencias = [];
        if ($equipo->jugadores()->exists()) {
            $dependencias[] = 'jugadores';
        }
        if ($equipo->campeonatos()->exists() || $equipo->campeonato_id !== null) {
            $dependencias[] = 'participaciones en campeonatos';
        }
        if ($equipo->nominasPartido()->exists()) {
            $dependencias[] = 'nóminas de partidos';
        }
        if ($equipo->incidencias()->exists()) {
            $dependencias[] = 'historial deportivo';
        }
        if ($equipo->partidosLocal()->exists() || $equipo->partidosVisita()->exists()) {
            $dependencias[] = 'partidos';
        }

        if ($dependencias) {
            return back()->withErrors([
                'error' => 'No se puede eliminar el equipo porque conserva '.implode(', ', $dependencias).'. Retíralo del campeonato en lugar de borrar su historial.',
            ]);
        }

        if ($equipo->escudo_cloudinary_id) {
            try {
                $this->imagenes->eliminar($equipo->escudo_cloudinary_id);
            } catch (Throwable $error) {
                Log::warning('No se pudo eliminar de Cloudinary el escudo del equipo.', [
                    'equipo_id' => $equipo->id,
                    'error' => $error->getMessage(),
                ]);

                return back()->withErrors([
                    'error' => 'No se pudo eliminar el escudo de Cloudinary. Inténtalo nuevamente.',
                ]);
            }
        }
        if ($equipo->escudo && Storage::disk('public')->exists($equipo->escudo)) {
            Storage::disk('public')->delete($equipo->escudo);
        }
        $equipo->delete();

        return redirect()->route('home')->with('mensaje', 'Equipo eliminado.');
    }

    public function solicitarInscripcion(Request $request)
    {
        $data = $request->validate(['equipo_id' => 'required|exists:equipos,id', 'campeonato_id' => 'required|exists:campeonatos,id']);
        $equipo = Equipo::findOrFail($data['equipo_id']);
        $campeonato = Campeonato::findOrFail($data['campeonato_id']);
        abort_unless((int) $equipo->delegado_id === (int) auth()->id(), 403);
        if (! $this->aceptaInscripciones($campeonato)) {
            return back()->withErrors(['error' => 'Las inscripciones están cerradas.']);
        }
        $estadoActual = $equipo->campeonatos()->where('campeonatos.id', $campeonato->id)->value('campeonato_equipo.estado');
        if (in_array($estadoActual, ['pendiente', 'aprobado'], true)) {
            return back()->withErrors(['campeonato_id' => 'Este equipo ya está inscrito o tiene una solicitud pendiente en el campeonato.']);
        }
        if ($estadoActual === 'retirado') {
            return back()->withErrors(['campeonato_id' => 'Este equipo fue retirado del campeonato por el organizador. Comunícate con él si deseas solicitar una revisión.']);
        }
        if ($this->delegadoYaParticipa($campeonato, auth()->id(), $equipo->id)) {
            return back()->withErrors(['campeonato_id' => 'Ya participas con otro equipo en este campeonato. Solo puedes inscribir un equipo por campeonato.']);
        }
        if ($ciDuplicada = $this->ciDuplicadaAlInscribir($equipo, $campeonato)) {
            return back()->withErrors(['equipo_id' => "El jugador con CI {$ciDuplicada} ya está registrado en otro equipo de este campeonato."]);
        }
        if ($equipo->campeonato_id === null) {
            // Deja un valor legado útil para integraciones antiguas; las
            // consultas nuevas continúan usando campeonato_equipo.
            $equipo->update(['campeonato_id' => $campeonato->id]);
        }
        $equipo->campeonatos()->syncWithoutDetaching([$campeonato->id => ['estado' => 'pendiente']]);
        $equipo->campeonatos()->updateExistingPivot($campeonato->id, ['estado' => 'pendiente']);
        if ($organizador = User::find($campeonato->usuario_creador)) {
            $organizador->notify(new NotificacionPlataforma(
                'Nueva solicitud de equipo',
                "{$equipo->nombre} solicita participar en {$campeonato->nombre}.",
                route('campeonatos.show', $campeonato),
                'bi-shield-plus',
                'warning',
                whatsappTemplate: config('services.whatsapp.templates.team_request'),
                whatsappParameters: [$organizador->name, $equipo->nombre, $campeonato->nombre],
            ));
        }

        return back()->with('mensaje', 'Solicitud enviada al organizador.');
    }

    public function gestionarSolicitud(Request $request, Campeonato $campeonato, Equipo $equipo)
    {
        $data = $request->validate(['estado' => 'required|in:aprobado,rechazado']);
        abort_unless(auth()->user()->esDesarrollador() || (int) $campeonato->usuario_creador === (int) auth()->id(), 403);
        abort_unless($campeonato->equipos()->whereKey($equipo->id)->exists(), 404);
        $campeonato->equipos()->updateExistingPivot($equipo->id, ['estado' => $data['estado']]);
        if ($equipo->delegado) {
            $aprobado = $data['estado'] === 'aprobado';
            $equipo->delegado->notify(new NotificacionPlataforma(
                $aprobado ? 'Equipo aprobado' : 'Solicitud rechazada',
                $aprobado ? "{$equipo->nombre} fue aprobado en {$campeonato->nombre}." : "La solicitud de {$equipo->nombre} para {$campeonato->nombre} fue rechazada.",
                route('campeonatos.show', $campeonato),
                $aprobado ? 'bi-check-circle' : 'bi-x-circle',
                $aprobado ? 'success' : 'danger',
            ));
        }

        return back()->with('mensaje', 'Solicitud '.$data['estado'].'.');
    }

    public function retirarDelCampeonato(Request $request, Campeonato $campeonato, Equipo $equipo)
    {
        abort_unless(auth()->user()->esDesarrollador() || (int) $campeonato->usuario_creador === (int) auth()->id(), 403);
        $data = $request->validate([
            'motivo_retiro' => 'required|string|min:10|max:500',
        ]);
        $estado = $campeonato->equipos()->where('equipos.id', $equipo->id)->value('campeonato_equipo.estado');
        if ($estado !== 'aprobado') {
            return back()->withErrors(['equipo_id' => 'Solo puedes retirar equipos que estén aprobados en este campeonato.']);
        }

        $campeonato->equipos()->updateExistingPivot($equipo->id, [
            'estado' => 'retirado',
            'motivo_retiro' => trim($data['motivo_retiro']),
            'retirado_at' => now(),
            'retirado_por' => auth()->id(),
        ]);

        $equipo->delegado?->notify(new NotificacionPlataforma(
            'Equipo retirado del campeonato',
            "{$equipo->nombre} fue retirado de {$campeonato->nombre}. Motivo: ".trim($data['motivo_retiro']),
            route('campeonatos.show', $campeonato),
            'bi-person-x',
            'danger',
        ));

        return back()->with('mensaje', "{$equipo->nombre} fue retirado únicamente de este campeonato. Su equipo, jugadores e historial se conservaron.");
    }

    private function campeonatosAbiertos()
    {
        return Campeonato::noFinalizadosActuales()
            ->where('inscripciones_abiertas', true)
            ->where(fn ($q) => $q->whereNull('fecha_limite_inscripcion')->orWhereDate('fecha_limite_inscripcion', '>=', today()))
            ->orderBy('fecha_inicio');
    }

    private function eliminarImagenCloudinarySinInterrumpir(?string $publicId): void
    {
        if (! $publicId) {
            return;
        }

        try {
            $this->imagenes->eliminar($publicId);
        } catch (Throwable $error) {
            Log::warning('No se pudo limpiar un escudo reemplazado de Cloudinary.', [
                'public_id' => $publicId,
                'error' => $error->getMessage(),
            ]);
        }
    }

    private function aceptaInscripciones(Campeonato $campeonato): bool
    {
        return $campeonato->estado_actual !== 'finalizado'
            && $campeonato->inscripciones_abiertas
            && (! $campeonato->fecha_limite_inscripcion || now()->lte($campeonato->fecha_limite_inscripcion->endOfDay()));
    }

    private function delegadoYaParticipa(Campeonato $campeonato, int $delegadoId, ?int $exceptoEquipoId = null): bool
    {
        return $campeonato->equipos()
            ->where('equipos.delegado_id', $delegadoId)
            ->wherePivotIn('estado', ['pendiente', 'aprobado', 'retirado'])
            ->when($exceptoEquipoId, fn ($q) => $q->where('equipos.id', '<>', $exceptoEquipoId))
            ->exists();
    }

    private function ciDuplicadaAlInscribir(Equipo $equipo, Campeonato $campeonato): ?string
    {
        // La columna conserva el nombre técnico `dni` para no perder los datos
        // recuperados, pero para el usuario boliviano representa su CI.
        $documentosCi = $equipo->jugadores()->whereNotNull('dni')->where('dni', '<>', '')->pluck('dni');
        if ($documentosCi->isEmpty()) {
            return null;
        }

        return Jugador::whereIn('dni', $documentosCi)
            ->where('equipo_id', '<>', $equipo->id)
            ->whereHas('equipo.campeonatos', fn ($q) => $q
                ->where('campeonatos.id', $campeonato->id)
                ->whereIn('campeonato_equipo.estado', ['pendiente', 'aprobado']))
            ->value('dni');
    }
}
