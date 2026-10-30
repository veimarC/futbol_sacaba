<?php

namespace App\Http\Controllers;

use App\Models\Campeonato;
use App\Models\Equipo;
use App\Models\Incidencia;
use App\Models\Jugador;
use App\Services\LocalImageService;
use App\Services\ConfiguracionFormatoService;
use App\Services\TablaPosicionesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CampeonatoController extends Controller
{
    public function __construct(
        private TablaPosicionesService $tablaPosiciones,
        private ConfiguracionFormatoService $configuracionFormato,
        private LocalImageService $imagenes,
    )
    {
        $this->middleware('auth');
        $this->authorizeResource(Campeonato::class, 'campeonato', ['except' => ['generarFixture']]);
    }

    public function index()
    {
        $user = auth()->user();
        $campeonatos = Campeonato::query()
            ->with('organizador:id,name')
            ->when(! auth()->user()->esDesarrollador() && auth()->user()->rol !== \App\Models\User::ROLE_ORGANIZADOR, fn ($q) => $q->aprobados())
            ->when($user->rol === \App\Models\User::ROLE_ORGANIZADOR, fn ($q) => $q->where('usuario_creador', $user->id))
            ->when($user->rol === \App\Models\User::ROLE_DELEGADO, fn ($q) => $q
                ->whereHas('equipos', fn ($equipos) => $equipos
                    ->where('equipos.delegado_id', $user->id)
                    ->whereIn('campeonato_equipo.estado', ['pendiente', 'aprobado'])))
            ->orderByRaw("CASE WHEN aprobacion_estado = 'pendiente' THEN 0 ELSE 1 END")->latest()->get();
        return view('campeonatos.index', compact('campeonatos'));
    }

    public function descubrir(Request $request)
    {
        $busqueda = trim((string) $request->query('buscar'));

        $campeonatos = Campeonato::query()
            ->with('organizador:id,name')
            ->when(! auth()->user()->esDesarrollador() && auth()->user()->rol !== \App\Models\User::ROLE_ORGANIZADOR, fn ($q) => $q->aprobados())
            ->withCount(['equipos as equipos_aprobados_count' => fn ($q) => $q->where('campeonato_equipo.estado', 'aprobado')])
            ->aprobados()->where('inscripciones_abiertas', true)
            ->noFinalizadosActuales()
            ->where(fn ($q) => $q->whereNull('fecha_limite_inscripcion')->orWhereDate('fecha_limite_inscripcion', '>=', today()))
            ->when($busqueda !== '', fn ($q) => $q->where('nombre', 'like', '%'.$busqueda.'%'))
            ->orderBy('fecha_inicio')
            ->paginate(9)
            ->withQueryString();

        return view('campeonatos.descubrir', compact('campeonatos', 'busqueda'));
    }

    public function create() { return view('campeonatos.create'); }

    public function store(Request $request)
    {
        $request->merge([
            'jugadores_cancha' => $request->input('modalidad'),
            'min_jugadores_equipo' => $request->input('min_jugadores_equipo', $request->input('modalidad')),
        ]);
        $data = $this->validar($request);
        $data['estado'] = 'pendiente';
        unset($data['eliminar_portada']);
        $nuevaPortada = null;
        if ($request->hasFile('portada')) {
            try {
                $subida = $this->imagenes->subir($request->file('portada'), 'campeonatos');
                $nuevaPortada = $subida;
                $data['portada'] = $nuevaPortada;
            } catch (Throwable $error) {
                Log::warning('No se pudo subir la portada del campeonato en el almacenamiento local.', [
                    'error' => $error->getMessage(),
                ]);

                return back()->withErrors([
                    'portada' => 'No se pudo subir la portada. Inténtalo nuevamente.',
                ])->withInput();
            }
        }
        $data['usuario_creador'] = auth()->id();
        $data['inscripciones_abiertas'] = $request->boolean('inscripciones_abiertas');
        try {
            Campeonato::create($data);
        } catch (Throwable $error) {
            $this->eliminarImagenLocalSinInterrumpir($nuevaPortada);

            throw $error;
        }
        return redirect()->route('campeonatos.index')->with('mensaje', 'Borrador guardado. Abre el campeonato para enviarlo a revisión.');
    }

    public function show(Campeonato $campeonato)
    {
        $campeonato->loadMissing('organizador:id,name');
        $equipos = $campeonato->equipos()->wherePivot('estado', 'aprobado')
            ->withCount(['jugadores as jugadores_inscritos_count' => fn ($q) => $q->where('estado', '<>', Jugador::ESTADO_RECHAZADO)])
            ->get();
        $solicitudesPendientes = $campeonato->equipos()->wherePivot('estado', 'pendiente')->get();
        $equiposRetirados = $campeonato->equipos()->wherePivot('estado', 'retirado')->get();
        $fases = $campeonato->fases()->with('partidos')->orderBy('orden')->get();
        $partidos = ($liga = $fases->firstWhere('tipo', 'liga')) ? $liga->partidos()->get() : $campeonato->partidos;
        $etiquetasFases = collect();
        if ($campeonato->formato === 'grupos_eliminacion') {
            $ordenFases = $fases->pluck('orden', 'id');
            $partidos = $partidos->sortBy(fn ($partido) => sprintf(
                '%05d-%05d-%05d-%010d',
                $ordenFases->get($partido->fase_id, 99999),
                $partido->ronda_number ?? 0,
                $partido->orden_en_ronda ?? 0,
                $partido->id,
            ))->values();

            $indiceGrupo = 0;
            foreach ($fases as $fase) {
                if ($fase->tipo === 'grupo') {
                    $etiquetasFases->put($fase->id, 'Grupo '.chr(65 + $indiceGrupo));
                    $indiceGrupo++;
                } elseif ($fase->tipo === 'eliminacion') {
                    $etiquetasFases->put($fase->id, 'Fase eliminatoria');
                }
            }
        }
        $partidos->loadMissing(['equipoLocal', 'equipoVisita', 'nominas']);
        $tabla = $campeonato->tieneTablaPosiciones() ? $this->tablaPosiciones->calcular($equipos, $partidos) : [];
        $tablasGrupos = collect();
        if ($campeonato->formato === 'grupos_eliminacion') {
            $tablasGrupos = $fases->where('tipo', 'grupo')->values()
                ->map(function ($fase, int $indice) use ($equipos) {
                    $equiposGrupo = $equipos
                        ->whereIn('id', data_get($fase->metadata, 'teams', []))
                        ->values();

                    return [
                        'nombre' => 'Grupo '.chr(65 + $indice),
                        'cantidad_equipos' => $equiposGrupo->count(),
                        'tabla' => $this->tablaPosiciones->calcular($equiposGrupo, $fase->partidos),
                    ];
                });
        }
        $opcionesClasificados = $this->configuracionFormato->opcionesClasificados($campeonato);
        $llavesGeneradas = $fases->contains('tipo', 'eliminacion');
        $faseGruposIniciada = $fases->where('tipo', 'grupo')->contains(
            fn ($fase) => $fase->partidos->contains('estado', 'jugado')
        );
        $configuracionCopaBloqueada = $llavesGeneradas || $faseGruposIniciada;
        $goleadores = Jugador::query()->with('equipo')
            ->withCount(['incidencias as goles_count' => fn ($query) => $query
                ->where('tipo', Incidencia::TIPO_GOL)
                ->whereHas('partido', fn ($partidos) => $partidos->where('campeonato_id', $campeonato->id))])
            ->where('estado', Jugador::ESTADO_APROBADO)
            ->whereIn('equipo_id', $equipos->pluck('id'))->get()
            ->where('goles_count', '>', 0)->sortByDesc('goles_count')->take(10)->values();
        $tarjetas = Jugador::query()->with('equipo')
            ->withCount([
                'incidencias as amarillas_count' => fn ($query) => $query
                    ->whereIn('tipo', [Incidencia::TIPO_AMARILLA, Incidencia::TIPO_DOBLE_AMARILLA])
                    ->whereHas('partido', fn ($partidos) => $partidos->where('campeonato_id', $campeonato->id)),
                'incidencias as rojas_count' => fn ($query) => $query
                    ->where('tipo', Incidencia::TIPO_ROJA)
                    ->whereHas('partido', fn ($partidos) => $partidos->where('campeonato_id', $campeonato->id)),
            ])
            ->where('estado', Jugador::ESTADO_APROBADO)
            ->whereIn('equipo_id', $equipos->pluck('id'))->get()
            ->filter(fn ($jugador) => $jugador->amarillas_count > 0 || $jugador->rojas_count > 0)
            ->sortByDesc(fn ($jugador) => ($jugador->rojas_count * 1000) + $jugador->amarillas_count)
            ->values();
        ['mostrarLlaves' => $mostrarLlaves, 'llaves' => $llaves, 'tercerLugar' => $tercerLugar]
            = app(\App\Services\LlavesCampeonatoService::class)->calcular(
                $campeonato,
                $equipos,
                $campeonato->partidos()->with(['fase', 'equipoLocal', 'equipoVisita'])->get()
            );

        $misEquipos = collect();
        $equipoInscritoEnCampeonato = null;
        if (auth()->user()->rol === \App\Models\User::ROLE_DELEGADO) {
            $misEquipos = Equipo::where('delegado_id', auth()->id())
                ->with(['campeonatos' => fn ($q) => $q->where('campeonatos.id', $campeonato->id)])
                ->orderBy('nombre')
                ->get();
            $equipoInscritoEnCampeonato = $misEquipos->first(fn ($equipo) => in_array(
                $equipo->campeonatos->first()?->pivot?->estado,
                ['pendiente', 'aprobado', 'retirado'],
                true
            ));
        }
        return view('campeonatos.show', compact('campeonato', 'equipos', 'solicitudesPendientes', 'equiposRetirados', 'partidos', 'etiquetasFases', 'tabla', 'tablasGrupos', 'opcionesClasificados', 'llavesGeneradas', 'faseGruposIniciada', 'configuracionCopaBloqueada', 'goleadores', 'tarjetas', 'mostrarLlaves', 'llaves', 'tercerLugar', 'fases', 'misEquipos', 'equipoInscritoEnCampeonato'));
    }

    public function actualizarClasificados(Request $request, Campeonato $campeonato)
    {
        $this->authorize('update', $campeonato);

        if ($campeonato->formato !== 'grupos_eliminacion') {
            return back()->withErrors(['error' => 'Este control solo corresponde a campeonatos con fase de grupos.']);
        }
        if ($campeonato->fases()->where('tipo', 'eliminacion')->exists()) {
            return back()->withErrors(['error' => 'Las llaves ya fueron generadas; no puedes cambiar los clasificados por grupo.']);
        }
        if ($campeonato->fases()->where('tipo', 'grupo')->whereHas('partidos', fn ($query) => $query->where('estado', 'jugado'))->exists()) {
            return back()->withErrors(['error' => 'La fase de grupos ya comenzó; la configuración de clasificación quedó bloqueada.']);
        }

        $opciones = $this->configuracionFormato->opcionesClasificados($campeonato);
        $data = $request->validate([
            'clasificados_por_grupo' => ['required', 'integer', \Illuminate\Validation\Rule::in($opciones)],
            'ida_vuelta_eliminatorias' => ['nullable', 'boolean'],
            'partido_tercer_lugar' => ['nullable', 'boolean'],
        ], [
            'clasificados_por_grupo.in' => 'Elige una cantidad válida para el número y tamaño de los grupos.',
        ]);

        $cantidad = (int) $data['clasificados_por_grupo'];
        $total = $campeonato->fases()->where('tipo', 'grupo')->count() * $cantidad;
        $configuracion = $campeonato->formato_config ?? [];
        $configuracion['qualifiers_per_group'] = $cantidad;
        $configuracion['elimination_start'] = $this->configuracionFormato->inicioEliminatoria($total);
        $campeonato->update(['formato_config' => $configuracion]);

        return back()->with('mensaje', "Ahora clasifican {$cantidad} equipos por grupo ({$total} en total).");
    }

    public function edit(Campeonato $campeonato)
    {
        abort_if($campeonato->aprobacion_estado === 'pendiente', 403);
        $soloDatosInformativos = $campeonato->aprobacion_estado === 'aprobado';

        return view('campeonatos.edit', compact('campeonato', 'soloDatosInformativos'));
    }

    public function update(Request $request, Campeonato $campeonato)
    {
        return DB::transaction(function () use ($request, $campeonato) {
            $actual = Campeonato::lockForUpdate()->findOrFail($campeonato->id);
            return $this->actualizarDatos($request, $actual);
        });
    }

    private function actualizarDatos(Request $request, Campeonato $campeonato)
    {
        abort_if($campeonato->aprobacion_estado === 'pendiente', 409, 'El campeonato está en revisión.');
        if ($campeonato->aprobacion_estado === 'aprobado') {
            return $this->actualizarDatosInformativos($request, $campeonato);
        }

        $data = $this->validar($request);
        $eliminarPortada = $request->boolean('eliminar_portada');
        unset($data['portada'], $data['eliminar_portada']);
        $plantillaMasGrande = (int) (DB::table('jugadores')
            ->join('equipos', 'jugadores.equipo_id', '=', 'equipos.id')
            ->join('campeonato_equipo', 'equipos.id', '=', 'campeonato_equipo.equipo_id')
            ->where('campeonato_equipo.campeonato_id', $campeonato->id)
            ->whereIn('campeonato_equipo.estado', ['pendiente', 'aprobado'])
            ->where('jugadores.estado', '<>', \App\Models\Jugador::ESTADO_RECHAZADO)
            ->groupBy('equipos.id')
            ->selectRaw('COUNT(jugadores.id) as total')
            ->orderByDesc('total')
            ->value('total') ?? 0);
        if ($plantillaMasGrande > $data['max_jugadores_equipo']) {
            return back()->withErrors([
                'max_jugadores_equipo' => "No puedes bajar el máximo a {$data['max_jugadores_equipo']}: ya existe un equipo con {$plantillaMasGrande} jugadores.",
            ])->withInput();
        }
        if ($errorReglas = $this->errorAlCambiarReglasConJugadores($campeonato, $data)) {
            return back()->withErrors($errorReglas)->withInput();
        }
        $data['inscripciones_abiertas'] = $request->boolean('inscripciones_abiertas');
        $portadaAnterior = $campeonato->portada;
        $nuevaPortada = null;
        $cambiaFormato = $data['formato'] !== $campeonato->formato;
        if ($cambiaFormato && auth()->user()->cannot('changeFormat', $campeonato)) {
            return back()->withErrors(['error' => 'No se puede cambiar el formato después de generar partidos.']);
        }
        if ($campeonato->partidos()->exists()) {
            $data['formato_config'] = $campeonato->formato_config;
        }
        if ($request->hasFile('portada')) {
            try {
                $subida = $this->imagenes->subir($request->file('portada'), 'campeonatos');
                $nuevaPortada = $subida;
                $data['portada'] = $nuevaPortada;
            } catch (Throwable $error) {
                Log::warning('No se pudo reemplazar la portada del campeonato en el almacenamiento local.', [
                    'campeonato_id' => $campeonato->id,
                    'error' => $error->getMessage(),
                ]);

                return back()->withErrors([
                    'portada' => 'No se pudo subir la nueva portada. La imagen anterior se conserva.',
                ])->withInput();
            }
        } elseif ($eliminarPortada) {
            $data['portada'] = null;
        }
        try {
            $campeonato->update($data);
        } catch (Throwable $error) {
            $this->eliminarImagenLocalSinInterrumpir($nuevaPortada);

            throw $error;
        }
        if ($portadaAnterior && ($request->hasFile('portada') || $eliminarPortada) && Storage::disk('public')->exists($portadaAnterior)) {
            Storage::disk('public')->delete($portadaAnterior);
        }
        return redirect()->route('campeonatos.show', $campeonato)->with('mensaje', 'Campeonato actualizado.');
    }

    private function actualizarDatosInformativos(Request $request, Campeonato $campeonato)
    {
        $data = $request->validate([
            'fecha_fin' => ['sometimes', 'nullable', 'date', 'after:'.$campeonato->fecha_inicio->format('Y-m-d')],
            'min_jugadores_partido' => ['sometimes', 'required', 'integer', 'min:1', 'max:'.$campeonato->jugadores_cancha],
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:3000',
            'reglamento_interno' => 'nullable|string|max:10000',
            'portada' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'eliminar_portada' => 'nullable|boolean',
        ]);
        if (! empty($data['fecha_fin']) && $campeonato->partidos()
            ->whereDate('fecha', '>', $data['fecha_fin'])->exists()) {
            return back()->withErrors([
                'fecha_fin' => 'La fecha de fin no puede ser anterior a la fecha de un partido del campeonato.',
            ])->withInput();
        }
        $eliminarPortada = $request->boolean('eliminar_portada');
        unset($data['eliminar_portada']);

        $portadaAnterior = $campeonato->portada;
        $nuevaPortada = null;
        if ($request->hasFile('portada')) {
            try {
                $nuevaPortada = $this->imagenes->subir($request->file('portada'), 'campeonatos');
                $data['portada'] = $nuevaPortada;
            } catch (Throwable $error) {
                Log::warning('No se pudo reemplazar la portada del campeonato en el almacenamiento local.', [
                    'campeonato_id' => $campeonato->id,
                    'error' => $error->getMessage(),
                ]);

                return back()->withErrors([
                    'portada' => 'No se pudo subir la nueva portada. La imagen anterior se conserva.',
                ])->withInput();
            }
        } elseif ($eliminarPortada) {
            $data['portada'] = null;
        }

        try {
            $campeonato->update($data);
        } catch (Throwable $error) {
            $this->eliminarImagenLocalSinInterrumpir($nuevaPortada);

            throw $error;
        }

        if ($portadaAnterior && ($request->hasFile('portada') || $eliminarPortada) && Storage::disk('public')->exists($portadaAnterior)) {
            Storage::disk('public')->delete($portadaAnterior);
        }

        return redirect()->route('campeonatos.show', $campeonato)->with('mensaje', 'Datos del campeonato actualizados.');
    }

    public function destroy(Campeonato $campeonato)
    {
        if (! auth()->user()->esDesarrollador() && ($campeonato->equipos()->exists() || $campeonato->partidos()->exists())) return back()->withErrors(['error' => 'No se puede eliminar un campeonato con equipos o partidos.']);
        $portada = $campeonato->portada;
        DB::transaction(function () use ($campeonato) {
            // Conservar equipos y jugadores vinculados por la referencia antigua.
            Equipo::where('campeonato_id', $campeonato->id)->update(['campeonato_id' => null]);
            // Las incidencias no tienen una clave foranea con borrado en cascada.
            Incidencia::whereIn('partido_id', $campeonato->partidos()->select('id'))->delete();
            $campeonato->delete();
        });
        if ($portada && Storage::disk('public')->exists($portada)) Storage::disk('public')->delete($portada);
        return redirect()->route('campeonatos.index')->with('mensaje', 'Campeonato eliminado.');
    }

    public function generarFixture(Campeonato $campeonato)
    {
        $this->authorize('generateFixture', $campeonato);
        abort_unless($campeonato->aprobacion_estado === 'aprobado', 403, 'El campeonato requiere aprobación.');
        if ($campeonato->fases()->exists() || $campeonato->partidos()->exists()) return back()->withErrors(['error' => 'El fixture ya fue generado.']);

        if ($campeonato->equipos()->wherePivot('estado', 'pendiente')->exists()) {
            return back()->withErrors(['error' => 'Primero aprueba o rechaza todas las solicitudes de equipos pendientes.']);
        }

        $cantidadEquipos = $campeonato->equipos()->wherePivot('estado', 'aprobado')->count();

        try {
            $configuracion = $this->configuracionFormato->calcular($campeonato, $cantidadEquipos);
        } catch (\InvalidArgumentException $error) {
            return back()->withErrors(['error' => $error->getMessage()]);
        }

        $generador = match ($campeonato->formato) {
            'liga' => (int) data_get($configuracion, 'league_rounds', 1) === 2
                ? new \App\Services\FormatGenerators\LigaIdaVueltaGenerator
                : new \App\Services\FormatGenerators\LigaGenerator,
            'liga_ida_vuelta' => new \App\Services\FormatGenerators\LigaIdaVueltaGenerator,
            'eliminacion' => new \App\Services\FormatGenerators\EliminacionDirectaGenerator,
            'grupos_eliminacion' => new \App\Services\FormatGenerators\GruposEliminacionGenerator,
            'series_interseries' => new \App\Services\FormatGenerators\SeriesInterseriesGenerator,
            default => null,
        };
        if (! $generador) return back()->withErrors(['error' => 'Este formato todavía no tiene generador automático.']);

        try {
            DB::transaction(function () use ($campeonato, $generador, $configuracion) {
                $campeonato->update([
                    'formato_config' => $configuracion,
                    'inscripciones_abiertas' => false,
                ]);
                $generador->generate($campeonato, $configuracion);
            });
            return back()->with('mensaje', "Fixture generado automáticamente para {$cantidadEquipos} equipos. Las inscripciones quedaron cerradas.");
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    private function eliminarImagenLocalSinInterrumpir(?string $ruta): void
    {
        if (! $ruta) {
            return;
        }

        try {
            $this->imagenes->eliminar($ruta);
        } catch (Throwable $error) {
            Log::warning('No se pudo limpiar una portada reemplazada del almacenamiento local.', [
                'ruta' => $ruta,
                'error' => $error->getMessage(),
            ]);
        }
    }

    private function validar(Request $request): array
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:3000',
            'reglamento_interno' => 'nullable|string|max:10000',
            'portada' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'eliminar_portada' => 'nullable|boolean',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'nullable|date|after:fecha_inicio',
            'fecha_limite_inscripcion' => 'nullable|date|before_or_equal:fecha_inicio',
            'inscripciones_abiertas' => 'nullable|boolean',
            'formato' => 'required|in:liga,grupos_eliminacion,eliminacion',
            'vueltas_liga' => 'nullable|integer|in:1,2',
            'vueltas_grupos' => 'nullable|integer|in:1,2',
            'ida_vuelta_eliminatorias' => 'nullable|boolean',
            'partido_tercer_lugar' => 'nullable|boolean',
            'modalidad' => 'required|integer|in:5,6,7,8,9,11',
            'jugadores_cancha' => 'required|integer|min:1|max:50',
            'min_jugadores_partido' => 'nullable|integer|min:1|lte:jugadores_cancha',
            'min_jugadores_equipo' => 'required|integer|min:1|gte:jugadores_cancha|max:100',
            'max_jugadores_equipo' => 'required|integer|min:1|gte:min_jugadores_equipo|max:100',
            'categoria_edad' => 'required|in:sub_7,sub_9,sub_11,sub_13,sub_15,sub_17,sub_20,libre,mas_30,mas_35,mas_40,mas_45,mas_50,personalizada',
            'categoria_personalizada' => 'nullable|required_if:categoria_edad,personalizada|string|max:80',
            'edad_min' => 'nullable|integer|min:0|max:99',
            'edad_max' => 'nullable|integer|min:0|max:99|gte:edad_min',
            'sexo' => 'required|in:masculino,femenino,mixto',
        ]);

        $data['formato_config'] = $data['formato'] === 'liga'
            ? ['league_rounds' => (int) ($data['vueltas_liga'] ?? 1)]
            : [
                'eliminations_home_away' => $request->boolean('ida_vuelta_eliminatorias'),
                'third_place_match' => $request->boolean('partido_tercer_lugar'),
            ];
        if ($data['formato'] === 'grupos_eliminacion') {
            $data['formato_config']['group_rounds'] = (int) ($data['vueltas_grupos'] ?? 1);
        }
        unset($data['vueltas_liga'], $data['vueltas_grupos'], $data['ida_vuelta_eliminatorias'], $data['partido_tercer_lugar']);

        $categoriaSeleccionada = $data['categoria_edad'];
        if ($categoriaSeleccionada === 'personalizada') {
            $data['categoria_edad'] = trim($data['categoria_personalizada']);
        }
        unset($data['categoria_personalizada']);

        if (($data['edad_min'] ?? null) === null && ($data['edad_max'] ?? null) === null) {
            $limitesSugeridos = [
                'sub_7' => [null, 6], 'sub_9' => [null, 8], 'sub_11' => [null, 10],
                'sub_13' => [null, 12], 'sub_15' => [null, 14], 'sub_17' => [null, 16],
                'sub_20' => [null, 19], 'libre' => [null, null], 'mas_30' => [30, null],
                'mas_35' => [35, null], 'mas_40' => [40, null], 'mas_45' => [45, null],
                'mas_50' => [50, null],
            ];
            [$data['edad_min'], $data['edad_max']] = $limitesSugeridos[$categoriaSeleccionada] ?? [null, null];
        }

        return $data;
    }

    private function errorAlCambiarReglasConJugadores(Campeonato $campeonato, array $reglas): ?array
    {
        $jugadores = Jugador::where('estado', '<>', Jugador::ESTADO_RECHAZADO)
            ->whereHas('equipo.campeonatos', fn ($q) => $q
                ->where('campeonatos.id', $campeonato->id)
                ->whereIn('campeonato_equipo.estado', ['pendiente', 'aprobado']))
            ->get();

        $fechaReferencia = \Carbon\Carbon::parse($reglas['fecha_inicio'])->startOfDay();
        foreach ($jugadores as $jugador) {
            $nombre = trim($jugador->nombre.' '.$jugador->apellido);
            if ($reglas['sexo'] !== 'mixto' && $jugador->sexo !== $reglas['sexo']) {
                return ['sexo' => "No puedes aplicar esta participación: {$nombre} no cumple la nueva regla. Revisa primero su ficha."];
            }
            if (($reglas['edad_min'] ?? null) === null && ($reglas['edad_max'] ?? null) === null) continue;
            if (! $jugador->fecha_nacimiento) {
                return ['edad_min' => "No puedes aplicar límites de edad porque {$nombre} no tiene fecha de nacimiento registrada."];
            }

            $edad = (int) $jugador->fecha_nacimiento->copy()->startOfDay()->diffInYears($fechaReferencia);
            if (($reglas['edad_min'] ?? null) !== null && $edad < $reglas['edad_min']) {
                return ['edad_min' => "{$nombre} tendría {$edad} años y no cumpliría la nueva edad mínima."];
            }
            if (($reglas['edad_max'] ?? null) !== null && $edad > $reglas['edad_max']) {
                return ['edad_max' => "{$nombre} tendría {$edad} años y superaría la nueva edad máxima."];
            }
        }

        return null;
    }
}
