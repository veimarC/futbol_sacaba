<?php

namespace App\Http\Controllers;

use App\Models\Campeonato;
use App\Models\Incidencia;
use App\Models\Jugador;
use App\Services\TablaPosicionesService;
use Illuminate\Http\Request;

class PortalPublicoController extends Controller
{
    public function __construct(private TablaPosicionesService $tablaPosiciones) {}

    public function index(Request $request)
    {
        $datos = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
        ]);
        $buscar = trim((string) ($datos['buscar'] ?? ''));
        $campeonatos = Campeonato::query()
            ->with('organizador:id,name')
            ->withCount([
                'equipos as equipos_aprobados_count' => fn ($q) => $q->where('campeonato_equipo.estado', 'aprobado'),
                'partidos',
            ])
            ->when($buscar !== '', fn ($q) => $q->where('nombre', 'like', '%'.$buscar.'%'))
            ->ordenEstadoActual()
            ->orderBy('fecha_inicio')->paginate(8)->withQueryString();

        return view('publico.index', compact('campeonatos', 'buscar'));
    }

    public function show(Campeonato $campeonato)
    {
        $campeonato->loadMissing('organizador:id,name');
        $equipos = $campeonato->equipos()->wherePivot('estado', 'aprobado')
            ->with('delegado')
            ->withCount(['jugadores as jugadores_aprobados_count' => fn ($q) => $q->where('estado', Jugador::ESTADO_APROBADO)])
            ->orderBy('nombre')->get();
        $partidos = $campeonato->partidos()->with(['fase', 'equipoLocal', 'equipoVisita', 'arbitro'])
            ->orderBy('fecha')->orderBy('hora')->get();
        $proximoPartido = $partidos->where('estado', 'programado')
            ->filter(fn ($partido) => $partido->fecha && $partido->fecha->copy()->endOfDay()->gte(now()))
            ->first();
        $tabla = $this->tablaPosiciones->calcular($equipos, $partidos);
        // Construye una tabla independiente para cada grupo. Así los partidos
        // eliminatorios no alteran los puntos obtenidos durante la fase de grupos.
        $partidosInterseries = $partidos->filter(fn ($partido) => $partido->fase?->tipo === 'interserie');
        $tablasGrupos = $campeonato->fases()->where('tipo', 'grupo')->orderBy('orden')->get()
            ->map(function ($fase, int $indice) use ($equipos, $partidos, $partidosInterseries) {
                $equiposGrupo = $equipos->whereIn('id', data_get($fase->metadata, 'teams', []))->values();
                $idsGrupo = $equiposGrupo->pluck('id');
                // En el formato de series, los clásicos contra la otra serie
                // también suman puntos para la tabla del equipo participante.
                $partidosDeLaTabla = $partidos->where('fase_id', $fase->id)
                    ->concat($partidosInterseries->filter(fn ($partido) =>
                        $idsGrupo->contains($partido->equipo_local_id)
                        || $idsGrupo->contains($partido->equipo_visita_id)
                    ))->values();

                return [
                    'nombre' => data_get($fase->metadata, 'nombre', 'Grupo '.chr(65 + $indice)),
                    'tabla' => $this->tablaPosiciones->calcular(
                        $equiposGrupo,
                        $partidosDeLaTabla,
                    ),
                ];
            });
        $jugadores = Jugador::with('equipo')->where('estado', Jugador::ESTADO_APROBADO)
            ->whereIn('equipo_id', $equipos->pluck('id'))
            ->orderBy('equipo_id')->orderBy('nombre')->orderBy('apellido')->get();
        $goleadores = Jugador::query()->with([
            'equipo',
            'incidencias' => fn ($q) => $q
                ->where('tipo', Incidencia::TIPO_GOL)
                ->whereHas('partido', fn ($partidos) => $partidos->where('campeonato_id', $campeonato->id))
                ->with(['partido.equipoLocal', 'partido.equipoVisita'])
                ->orderBy('id'),
        ])
            ->withCount(['incidencias as goles_count' => fn ($q) => $q
                ->where('tipo', Incidencia::TIPO_GOL)
                ->whereHas('partido', fn ($partidos) => $partidos->where('campeonato_id', $campeonato->id))])
            ->where('estado', Jugador::ESTADO_APROBADO)
            ->whereIn('equipo_id', $equipos->pluck('id'))->get()
            ->where('goles_count', '>', 0)->sortByDesc('goles_count')->take(10)->values();
        $tarjetas = Jugador::query()->with('equipo')
            ->withCount([
                'incidencias as amarillas_count' => fn ($q) => $q
                    ->whereIn('tipo', [Incidencia::TIPO_AMARILLA, Incidencia::TIPO_DOBLE_AMARILLA])
                    ->whereHas('partido', fn ($partidos) => $partidos->where('campeonato_id', $campeonato->id)),
                'incidencias as rojas_count' => fn ($q) => $q
                    ->where('tipo', Incidencia::TIPO_ROJA)
                    ->whereHas('partido', fn ($partidos) => $partidos->where('campeonato_id', $campeonato->id)),
            ])
            ->where('estado', Jugador::ESTADO_APROBADO)
            ->whereIn('equipo_id', $equipos->pluck('id'))->get()
            ->filter(fn ($jugador) => $jugador->amarillas_count > 0 || $jugador->rojas_count > 0)
            ->sortByDesc(fn ($jugador) => ($jugador->rojas_count * 1000) + $jugador->amarillas_count)
            ->values();

        // La pestaña "Llaves" solo aparece cuando el formato o las fases indican
        // que el campeonato contiene una etapa de eliminación.
        $faseEliminacion = $campeonato->fases()
            ->where('tipo', 'eliminacion')
            ->orderByDesc('orden')
            ->first();
        $mostrarLlaves = $faseEliminacion !== null || in_array($campeonato->formato, [
            'eliminacion',
            'grupos_eliminacion',
            'liga_semifinal_final',
        ], true);
        $llaves = collect();
        $tercerLugar = null;

        if ($mostrarLlaves) {
            $config = $campeonato->formato_config ?? [];
            $bracketSize = (int) data_get($faseEliminacion?->metadata, 'bracket_size', 0);

            // Si todavía no existe una fase generada, usamos la configuración del
            // campeonato para dibujar desde ahora los espacios de las rondas futuras.
            if ($bracketSize < 2) {
                $bracketSize = match ($campeonato->formato) {
                    'liga_semifinal_final' => 4,
                    'grupos_eliminacion' => match ($config['elimination_start'] ?? null) {
                        'round_of_16', 'octavos' => 16,
                        'quarterfinals', 'cuartos' => 8,
                        'final' => 2,
                        default => 4,
                    },
                    default => $this->siguientePotenciaDeDos(max(2, $equipos->count())),
                };
            }

            // Separamos los partidos por ronda: 1 = semifinal, 2 = final, etc.
            $partidosEliminacion = $partidos
                ->filter(fn ($partido) => $partido->fase?->tipo === 'eliminacion')
                ->groupBy(fn ($partido) => (int) ($partido->ronda_number ?: 1));
            $totalRondas = (int) round(log($bracketSize, 2));

            $llaves = collect(range(1, $totalRondas))->map(function (int $numeroRonda) use ($bracketSize, $partidosEliminacion, $totalRondas, $config) {
                $cantidadPartidos = max(1, intdiv($bracketSize, 2 ** $numeroRonda));
                $partidosRonda = $partidosEliminacion->get($numeroRonda, collect())
                    ->sortBy(fn ($partido) => $partido->orden_en_ronda ?: $partido->id)
                    ->values();
                $esFinal = $numeroRonda === $totalRondas;
                $idaVuelta = ! $esFinal && ! empty($config['eliminations_home_away']);
                // Dos partidos con los mismos equipos (localías invertidas) forman
                // una sola serie de ida y vuelta dentro de la llave visual.
                $series = $partidosRonda
                    ->groupBy(function ($partido) {
                        $equipos = [(int) $partido->equipo_local_id, (int) $partido->equipo_visita_id];
                        sort($equipos);

                        return implode('-', $equipos);
                    })
                    ->map(fn ($partidosSerie) => $this->resumirSerie($partidosSerie->values(), $idaVuelta))
                    ->values();

                return [
                    'numero' => $numeroRonda,
                    'nombre' => $this->nombreRonda($cantidadPartidos, $numeroRonda, $totalRondas),
                    'series' => collect(range(0, $cantidadPartidos - 1))
                        ->map(fn (int $indice) => $series->get($indice)),
                ];
            });

            // El tercer puesto se juega entre los perdedores de las semifinales.
            // Se guarda como el segundo partido de la ronda final para no mezclarlo
            // con la final principal dentro de la llave.
            if (! empty($config['third_place_match']) && $bracketSize >= 4) {
                $partidosTercerLugar = $partidosEliminacion->get($totalRondas, collect())
                    ->filter(fn ($partido) => (int) $partido->orden_en_ronda === 2)
                    ->values();

                if ($partidosTercerLugar->isNotEmpty()) {
                    $tercerLugar = $this->resumirSerie($partidosTercerLugar, false);
                } else {
                    $semifinales = $llaves->get($totalRondas - 2)['series'] ?? collect();
                    $perdedores = $semifinales->take(2)->map(function ($serie) {
                        if (! $serie || ! $serie['completa'] || $serie['goles_a'] === $serie['goles_b']) {
                            return null;
                        }

                        return $serie['goles_a'] < $serie['goles_b'] ? $serie['equipo_a'] : $serie['equipo_b'];
                    })->values();

                    $tercerLugar = [
                        'equipo_a' => $perdedores->get(0),
                        'equipo_b' => $perdedores->get(1),
                        'goles_a' => 0,
                        'goles_b' => 0,
                        'completa' => false,
                        'ida_vuelta' => false,
                        'partidos' => collect(),
                    ];
                }
            }
        }

        return view('publico.show', compact('campeonato', 'equipos', 'partidos', 'tabla', 'tablasGrupos', 'jugadores', 'goleadores', 'tarjetas', 'proximoPartido', 'mostrarLlaves', 'llaves', 'tercerLugar'));
    }

    private function siguientePotenciaDeDos(int $cantidad): int
    {
        $potencia = 2;

        while ($potencia < $cantidad) {
            $potencia *= 2;
        }

        return $potencia;
    }

    private function nombreRonda(int $cantidadPartidos, int $numeroRonda, int $totalRondas): string
    {
        if ($numeroRonda === $totalRondas) {
            return 'Final';
        }

        return match ($cantidadPartidos) {
            8 => 'Octavos de final',
            4 => 'Cuartos de final',
            2 => 'Semifinales',
            default => 'Ronda '.$numeroRonda,
        };
    }

    private function resumirSerie($partidos, bool $idaVuelta): array
    {
        // El primer encuentro fija quién es el equipo A y quién es el equipo B.
        // Después acumulamos sus goles aunque las localías cambien en la vuelta.
        $primero = $partidos->first();
        $equipoA = $primero->equipoLocal;
        $equipoB = $primero->equipoVisita;
        $golesA = 0;
        $golesB = 0;

        foreach ($partidos->where('estado', 'jugado') as $partido) {
            $golesA += (int) ((int) $partido->equipo_local_id === (int) $equipoA?->id ? $partido->goles_local : $partido->goles_visita);
            $golesB += (int) ((int) $partido->equipo_local_id === (int) $equipoB?->id ? $partido->goles_local : $partido->goles_visita);
        }

        // Todas las eliminatorias configuradas como ida y vuelta necesitan dos
        // resultados. La final llega aquí con $idaVuelta = false y exige solo uno.
        $partidosEsperados = $idaVuelta ? 2 : 1;
        $completa = $partidos->count() >= $partidosEsperados
            && $partidos->take($partidosEsperados)->every(fn ($partido) => $partido->estado === 'jugado');

        return [
            'equipo_a' => $equipoA,
            'equipo_b' => $equipoB,
            'goles_a' => $golesA,
            'goles_b' => $golesB,
            'completa' => $completa,
            'ida_vuelta' => $idaVuelta,
            'partidos' => $partidos,
        ];
    }
}
