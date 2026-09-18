<?php

namespace App\Http\Controllers;

use App\Models\Campeonato;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\User;
use App\Notifications\NotificacionPlataforma;
use App\Services\CloudinaryImageService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Controlador de jugadores.
 *
 * Maneja la inscripción y gestión de jugadores por delegados y la aprobación
 * por parte de organizadores.
 * Rutas principales:
 *  - jugadores.show: ficha individual
 *  - equipos/{equipo}/jugadores/create: registro de nuevo jugador
 *  - jugadores.store: guardar jugador
 *  - jugadores.edit: editar jugador
 *  - jugadores.update: actualizar jugador
 *  - jugadores.destroy: eliminar jugador
 *  - jugadores.aprobar: aprobar jugador
 *  - jugadores.rechazar: rechazar jugador
 */
class JugadorController extends Controller
{
    public function __construct(private CloudinaryImageService $imagenes) {}

    /**
     * Mostrar la ficha de un jugador.
     * Organizadores pueden ver sólo si el jugador pertenece a un campeonato creado por ellos.
     */
    public function show(Jugador $jugador)
    {
        $user = auth()->user();

        if ($user->rol === User::ROLE_DELEGADO) {
            abort_unless((int) $jugador->equipo->delegado_id === (int) $user->id, 403);
        } elseif ($user->rol === User::ROLE_ORGANIZADOR) {
            abort_unless($this->organizadorPuedeVerJugador($user, $jugador), 403);
        } elseif ($user->rol !== User::ROLE_DESARROLLADOR) {
            abort(403);
        }

        return view('jugadores.show', compact('jugador'));
    }

    /**
     * Aprobar un jugador (organizador).
     */
    public function aprobar(Jugador $jugador)
    {
        $user = auth()->user();
        if ($user->rol !== User::ROLE_ORGANIZADOR) {
            abort(403);
        }

        if (! $this->organizadorPuedeVerJugador($user, $jugador)) {
            abort(403);
        }
        if ($errorElegibilidad = $this->errorElegibilidadJugador([
            'sexo' => $jugador->sexo,
            'fecha_nacimiento' => optional($jugador->fecha_nacimiento)->format('Y-m-d'),
        ], $jugador->equipo)) {
            return back()->withErrors($errorElegibilidad);
        }

        $jugador->estado = Jugador::ESTADO_APROBADO;
        $jugador->save();
        $jugador->equipo->delegado?->notify(new NotificacionPlataforma('Jugador aprobado', "{$jugador->nombre} fue aprobado para competir.", route('equipos.show', $jugador->equipo).'#jugadores', 'bi-person-check', 'success'));

        return back()->with('success', 'Jugador aprobado.');
    }

    /**
     * Rechazar un jugador (organizador).
     */
    public function rechazar(Jugador $jugador)
    {
        $user = auth()->user();
        if ($user->rol !== User::ROLE_ORGANIZADOR) {
            abort(403);
        }

        if (! $this->organizadorPuedeVerJugador($user, $jugador)) {
            abort(403);
        }

        $jugador->estado = Jugador::ESTADO_RECHAZADO;
        $jugador->save();
        $jugador->equipo->delegado?->notify(new NotificacionPlataforma('Jugador rechazado', "{$jugador->nombre} fue rechazado. Revisa sus datos con el organizador.", route('equipos.show', $jugador->equipo).'#jugadores', 'bi-person-x', 'danger'));

        return back()->with('success', 'Jugador rechazado.');
    }

    /**
     * Mostrar formulario para crear jugador. El delegado sólo puede elegir entre sus equipos.
     */
    public function create(Request $request, ?Equipo $equipo = null)
    {
        $user = auth()->user();
        $equipoSeleccionado = $equipo?->id ?: $request->query('equipo');

        if (! $equipoSeleccionado) {
            return redirect()->route($user->rol === User::ROLE_DELEGADO ? 'equipos.index' : 'campeonatos.index')
                ->with('error', 'Los jugadores deben registrarse desde la página del equipo correspondiente.');
        }

        if (! $equipo) {
            $equipo = Equipo::with('campeonatos')->findOrFail($equipoSeleccionado);
        }

        if ($user->rol !== User::ROLE_DESARROLLADOR && $equipo->delegado_id != $user->id) {
            abort(403, 'No tienes permiso para registrar jugadores en ese equipo.');
        }

        $equipos = collect([$equipo]);
        $limitePlantilla = $this->limitePlantillaParaEquipo($equipo);
        $reglasCampeonatos = $this->campeonatosActivosDelEquipo($equipo);
        $jugadoresRegistrados = $equipo->jugadores()->where('estado', '<>', Jugador::ESTADO_RECHAZADO)->count();

        return view('jugadores.create', compact('equipos', 'equipoSeleccionado', 'limitePlantilla', 'reglasCampeonatos', 'jugadoresRegistrados'));
    }

    /**
     * Guardar jugador en la base de datos. Maneja subida de foto opcional.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $rules = [
            'nombre' => 'required|string|max:255',
            'apellido' => 'nullable|string|max:255',
            'dni' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'regex:/^[67][0-9]{7}$/'],
            'whatsapp_notifications' => ['nullable', 'boolean'],
            'fecha_nacimiento' => 'nullable|date|before_or_equal:today',
            'sexo' => 'required|in:masculino,femenino',
            'posicion' => 'nullable|string|max:100',
            'equipo_id' => 'required|exists:equipos,id',
            'foto' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ];

        // La CI no es única globalmente: la misma persona puede participar en
        // campeonatos diferentes, pero no repetirse dentro del mismo torneo.

        $data = $request->validate($rules, [
            'whatsapp.regex' => 'Ingresa un celular boliviano válido de 8 dígitos, por ejemplo 70000000.',
        ]);

        // Verificar que el delegado realmente tiene permiso sobre el equipo seleccionado
        $equipo = Equipo::findOrFail($data['equipo_id']);
        if ($user->rol !== User::ROLE_DESARROLLADOR && $equipo->delegado_id != $user->id) {
            abort(403, 'No tienes permiso para agregar jugadores a este equipo.');
        }
        $limitePlantilla = $this->limitePlantillaParaEquipo($equipo);
        $jugadoresRegistrados = $equipo->jugadores()->where('estado', '<>', Jugador::ESTADO_RECHAZADO)->count();
        if ($limitePlantilla && $jugadoresRegistrados >= $limitePlantilla->max_jugadores_equipo) {
            return back()->withErrors(['equipo_id' => "Este equipo alcanzó el máximo de {$limitePlantilla->max_jugadores_equipo} jugadores permitido por {$limitePlantilla->nombre}."])->withInput();
        }
        if ($errorElegibilidad = $this->errorElegibilidadJugador($data, $equipo)) {
            return back()->withErrors($errorElegibilidad)->withInput();
        }
        if (! empty($data['dni'])) {
            $data['dni'] = trim($data['dni']);
            if ($this->ciRepetidaEnCampeonatosDelEquipo($data['dni'], $equipo)) {
                return back()->withErrors(['dni' => 'Esta CI ya está registrada en otro equipo del mismo campeonato. El jugador sí puede participar en campeonatos diferentes.'])->withInput();
            }
        }

        // La imagen se almacena en Cloudinary; la base conserva solo su public_id.
        if ($request->hasFile('foto')) {
            try {
                $subida = $this->imagenes->subir($request->file('foto'), 'jugadores');
                $data['foto_cloudinary_id'] = $subida['public_id'];
                $data['foto'] = null;
            } catch (Throwable $error) {
                Log::warning('No se pudo subir la foto del jugador a Cloudinary.', [
                    'error' => $error->getMessage(),
                ]);

                return back()->withErrors([
                    'foto' => 'No se pudo subir la foto. Inténtalo nuevamente.',
                ])->withInput();
            }
        }

        $data['whatsapp_opt_in_at'] = ! empty($data['whatsapp']) && ! empty($data['whatsapp_notifications'])
            ? now()
            : null;
        unset($data['whatsapp_notifications']);

        try {
            $jugador = Jugador::create($data);
        } catch (Throwable $error) {
            if (! empty($data['foto_cloudinary_id'])) {
                $this->eliminarImagenCloudinarySinInterrumpir($data['foto_cloudinary_id']);
            }

            throw $error;
        }
        $equipo->campeonatos()->wherePivot('estado', 'aprobado')->get()
            ->pluck('usuario_creador')->unique()
            ->each(function ($organizadorId) use ($jugador, $equipo) {
                User::find($organizadorId)?->notify(new NotificacionPlataforma(
                    'Nuevo jugador pendiente',
                    "{$jugador->nombre} fue registrado en {$equipo->nombre} y espera revisión.",
                    route('equipos.show', $equipo).'#jugadores',
                    'bi-person-plus',
                    'warning',
                ));
            });

        // Regresa a la plantilla del equipo para que el delegado vea de
        // inmediato al jugador recién registrado y pueda continuar agregando.
        return redirect()->to(route('equipos.show', $equipo).'#jugadores')
            ->with('success', 'Jugador registrado correctamente.');
    }

    /**
     * Mostrar formulario de edición.
     */
    public function edit(Jugador $jugador)
    {
        $user = auth()->user();

        if ($user->rol === User::ROLE_DESARROLLADOR) {
            $equipos = Equipo::with('campeonatos')->get();
        } else {
            $equipos = Equipo::where('delegado_id', $user->id)->get();
        }

        // Permiso: delegados sólo sobre sus equipos; desarrollador puede editar todo
        if ($user->rol !== User::ROLE_DESARROLLADOR && $jugador->equipo->delegado_id != $user->id) {
            abort(403);
        }

        return view('jugadores.edit', compact('jugador', 'equipos'));
    }

    /**
     * Actualizar jugador (incluye reemplazo de foto).
     */
    public function update(Request $request, Jugador $jugador)
    {
        $user = $request->user();

        $rules = [
            'nombre' => 'required|string|max:255',
            'apellido' => 'nullable|string|max:255',
            'dni' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'regex:/^[67][0-9]{7}$/'],
            'whatsapp_notifications' => ['nullable', 'boolean'],
            'fecha_nacimiento' => 'nullable|date|before_or_equal:today',
            'sexo' => 'required|in:masculino,femenino',
            'posicion' => 'nullable|string|max:100',
            'equipo_id' => 'required|exists:equipos,id',
            'foto' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ];

        // La CI se comprueba por campeonato y no como identificador único global.
        $data = $request->validate($rules, [
            'whatsapp.regex' => 'Ingresa un celular boliviano válido de 8 dígitos, por ejemplo 70000000.',
        ]);

        // Permiso
        if ($user->rol !== User::ROLE_DESARROLLADOR && $jugador->equipo->delegado_id != $user->id) {
            abort(403);
        }
        $equipoDestino = Equipo::findOrFail($data['equipo_id']);
        if ($user->rol !== User::ROLE_DESARROLLADOR && $equipoDestino->delegado_id != $user->id) {
            abort(403);
        }
        if ((int) $equipoDestino->id !== (int) $jugador->equipo_id) {
            $limitePlantilla = $this->limitePlantillaParaEquipo($equipoDestino);
            $jugadoresRegistrados = $equipoDestino->jugadores()->where('estado', '<>', Jugador::ESTADO_RECHAZADO)->count();
            if ($limitePlantilla && $jugadoresRegistrados >= $limitePlantilla->max_jugadores_equipo) {
                return back()->withErrors(['equipo_id' => "El equipo de destino alcanzó el máximo de {$limitePlantilla->max_jugadores_equipo} jugadores permitido por {$limitePlantilla->nombre}."])->withInput();
            }
        }
        if ($errorElegibilidad = $this->errorElegibilidadJugador($data, $equipoDestino)) {
            return back()->withErrors($errorElegibilidad)->withInput();
        }
        if (! empty($data['dni'])) {
            $data['dni'] = trim($data['dni']);
            if ($this->ciRepetidaEnCampeonatosDelEquipo($data['dni'], $equipoDestino, $jugador->id)) {
                return back()->withErrors(['dni' => 'Esta CI ya está registrada en otro equipo del mismo campeonato. El jugador sí puede participar en campeonatos diferentes.'])->withInput();
            }
        }

        $fotoCloudinaryAnterior = $jugador->foto_cloudinary_id;
        $fotoLocalAnterior = $jugador->foto;
        $nuevaFotoCloudinary = null;

        // Primero se sube la nueva imagen; la anterior se borra solo tras guardar.
        if ($request->hasFile('foto')) {
            try {
                $subida = $this->imagenes->subir($request->file('foto'), 'jugadores');
                $nuevaFotoCloudinary = $subida['public_id'];
                $data['foto_cloudinary_id'] = $nuevaFotoCloudinary;
                $data['foto'] = null;
            } catch (Throwable $error) {
                Log::warning('No se pudo reemplazar la foto del jugador en Cloudinary.', [
                    'jugador_id' => $jugador->id,
                    'error' => $error->getMessage(),
                ]);

                return back()->withErrors([
                    'foto' => 'No se pudo subir la nueva foto. La imagen anterior se conserva.',
                ])->withInput();
            }
        }

        $data['whatsapp_opt_in_at'] = ! empty($data['whatsapp']) && ! empty($data['whatsapp_notifications'])
            ? ($jugador->whatsapp_opt_in_at ?: now())
            : null;
        unset($data['whatsapp_notifications']);

        try {
            $jugador->update($data);
        } catch (Throwable $error) {
            if ($nuevaFotoCloudinary) {
                $this->eliminarImagenCloudinarySinInterrumpir($nuevaFotoCloudinary);
            }

            throw $error;
        }

        if ($nuevaFotoCloudinary) {
            $this->eliminarImagenCloudinarySinInterrumpir($fotoCloudinaryAnterior);
            if ($fotoLocalAnterior && Storage::disk('public')->exists($fotoLocalAnterior)) {
                Storage::disk('public')->delete($fotoLocalAnterior);
            }
        }

        return redirect()->to(route('equipos.show', $equipoDestino).'#jugadores')
            ->with('success', 'Jugador actualizado correctamente.');
    }

    /**
     * Eliminar jugador (delegado sólo si pertenece a su equipo).
     */
    public function destroy(Jugador $jugador)
    {
        $user = auth()->user();
        if ($user->rol !== User::ROLE_DESARROLLADOR && $jugador->equipo->delegado_id != $user->id) {
            abort(403);
        }

        // Los goles y tarjetas forman parte del historial del campeonato.
        // En ese caso el jugador se conserva para no dejar estadísticas huérfanas.
        if ($jugador->incidencias()->exists()) {
            return back()->withErrors([
                'jugador' => 'No se puede eliminar este jugador porque tiene goles o tarjetas registrados. Su historial deportivo debe conservarse.',
            ]);
        }

        if ($jugador->foto_cloudinary_id) {
            try {
                $this->imagenes->eliminar($jugador->foto_cloudinary_id);
            } catch (Throwable $error) {
                Log::warning('No se pudo eliminar de Cloudinary la foto del jugador.', [
                    'jugador_id' => $jugador->id,
                    'error' => $error->getMessage(),
                ]);

                return back()->withErrors([
                    'jugador' => 'No se pudo eliminar su foto de Cloudinary. Inténtalo nuevamente.',
                ]);
            }
        }

        // Compatibilidad con las fotografías guardadas antes de Cloudinary.
        if ($jugador->foto && Storage::disk('public')->exists($jugador->foto)) {
            Storage::disk('public')->delete($jugador->foto);
        }

        $jugador->delete();

        return back()->with('success', 'Jugador eliminado.');
    }

    private function eliminarImagenCloudinarySinInterrumpir(?string $publicId): void
    {
        if (! $publicId) {
            return;
        }

        try {
            $this->imagenes->eliminar($publicId);
        } catch (Throwable $error) {
            Log::warning('No se pudo limpiar una imagen reemplazada de Cloudinary.', [
                'public_id' => $publicId,
                'error' => $error->getMessage(),
            ]);
        }
    }

    private function ciRepetidaEnCampeonatosDelEquipo(string $ci, Equipo $equipo, ?int $ignorarJugadorId = null): bool
    {
        $campeonatosIds = $equipo->campeonatos()
            ->wherePivotIn('estado', ['pendiente', 'aprobado'])
            ->pluck('campeonatos.id');

        return Jugador::where('dni', $ci)
            ->when($ignorarJugadorId, fn ($q) => $q->where('jugadores.id', '<>', $ignorarJugadorId))
            ->where(function ($q) use ($equipo, $campeonatosIds) {
                $q->where('equipo_id', $equipo->id);
                if ($campeonatosIds->isNotEmpty()) {
                    $q->orWhereHas('equipo.campeonatos', fn ($campeonatoQuery) => $campeonatoQuery
                        ->whereIn('campeonatos.id', $campeonatosIds)
                        ->whereIn('campeonato_equipo.estado', ['pendiente', 'aprobado']));
                }
            })
            ->exists();
    }

    private function limitePlantillaParaEquipo(Equipo $equipo): ?Campeonato
    {
        return $equipo->campeonatos()
            ->wherePivotIn('estado', ['pendiente', 'aprobado'])
            ->noFinalizadosActuales()
            ->orderBy('campeonatos.max_jugadores_equipo')
            ->first();
    }

    private function campeonatosActivosDelEquipo(Equipo $equipo)
    {
        return $equipo->campeonatos()
            ->wherePivotIn('estado', ['pendiente', 'aprobado'])
            ->noFinalizadosActuales()
            ->orderBy('campeonatos.fecha_inicio')
            ->get();
    }

    private function errorElegibilidadJugador(array $data, Equipo $equipo): ?array
    {
        foreach ($this->campeonatosActivosDelEquipo($equipo) as $campeonato) {
            if ($campeonato->sexo !== 'mixto' && $data['sexo'] !== $campeonato->sexo) {
                return ['sexo' => "Este campeonato es {$campeonato->sexo_label}. El jugador no cumple la regla de participación de {$campeonato->nombre}."];
            }

            if ($campeonato->edad_min === null && $campeonato->edad_max === null) {
                continue;
            }
            if (empty($data['fecha_nacimiento'])) {
                return ['fecha_nacimiento' => "La fecha de nacimiento es obligatoria para comprobar la categoría {$campeonato->categoria_edad_label} de {$campeonato->nombre}."];
            }

            $nacimiento = Carbon::parse($data['fecha_nacimiento'])->startOfDay();
            $fechaReferencia = ($campeonato->fecha_inicio ?: today())->copy()->startOfDay();
            if ($nacimiento->gt($fechaReferencia)) {
                return ['fecha_nacimiento' => 'La fecha de nacimiento no puede ser posterior al inicio del campeonato.'];
            }
            $edad = (int) $nacimiento->diffInYears($fechaReferencia);

            if ($campeonato->edad_min !== null && $edad < $campeonato->edad_min) {
                return ['fecha_nacimiento' => "El jugador tendrá {$edad} años al iniciar {$campeonato->nombre}; la edad mínima es {$campeonato->edad_min}."];
            }
            if ($campeonato->edad_max !== null && $edad > $campeonato->edad_max) {
                return ['fecha_nacimiento' => "El jugador tendrá {$edad} años al iniciar {$campeonato->nombre}; la edad máxima es {$campeonato->edad_max}."];
            }
        }

        return null;
    }

    private function organizadorPuedeVerJugador(User $organizador, Jugador $jugador): bool
    {
        return $organizador->rol === User::ROLE_ORGANIZADOR
            && $jugador->equipo->campeonatos()
                ->where('campeonatos.usuario_creador', $organizador->id)
                ->wherePivot('estado', 'aprobado')
                ->exists();
    }
}
