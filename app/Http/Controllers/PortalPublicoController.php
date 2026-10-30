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
        $campeonatos = Campeonato::aprobados()
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
        abort_unless($campeonato->aprobacion_estado === 'aprobado', 404);
        $campeonato->loadMissing('organizador:id,name');
        $equipos = $campeonato->equipos()->wherePivot('estado', 'aprobado')
            ->with('delegado')
            ->withCount(['jugadores as jugadores_aprobados_count' => fn ($q) => $q->where('estado', Jugador::ESTADO_APROBADO)])
            ->orderBy('nombre')->get();
        $partidos = $campeonato->partidos()->with(['fase', 'equipoLocal', 'equipoVisita'])
            ->orderBy('fecha')->orderBy('hora')->get();
        $tabla = $campeonato->tieneTablaPosiciones() ? $this->tablaPosiciones->calcular($equipos, $partidos) : [];
        $jornadas = $partidos
            ->sortBy(fn ($partido) => sprintf('%05d-%05d-%s-%s-%010d',
                $partido->fase?->tipo === 'grupo' ? 0 : ($partido->fase?->orden ?? 9999),
                $partido->ronda_number ?? 9999,
                $partido->fecha?->format('Y-m-d') ?? '9999-12-31',
                $partido->hora ?: '99:99:99',
                $partido->id
            ))
            ->groupBy(fn ($partido) => ($partido->fase?->tipo === 'grupo' ? 'grupos' : 'fase-'.($partido->fase_id ?? 'sin-fase'))
                .'-ronda-'.($partido->ronda_number ?? 'sin-jornada'))
            ->map(function ($encuentros) {
                $primero = $encuentros->first();
                $fase = $primero->fase;
                $ronda = $primero->ronda_number;
                $nombre = $ronda ? 'Jornada '.$ronda : 'Partidos sin jornada asignada';
                if ($fase?->tipo === 'eliminacion') {
                    $nombre = ($fase->nombre ?: 'Eliminatorias').' · Ronda '.($ronda ?: 1);
                } elseif ($fase?->tipo === 'grupo') {
                    $nombre .= ' · Fase de grupos';
                } elseif ($fase && $fase->tipo !== 'liga') {
                    $nombre .= ' · '.($fase->nombre ?: ucfirst($fase->tipo));
                }
                return [
                    'nombre' => $nombre,
                    'partidos' => $encuentros,
                    'jugados' => $encuentros->where('estado', 'jugado')->count(),
                    'pendientes' => $encuentros->where('estado', 'programado')->count(),
                ];
            })->values();
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

        ['mostrarLlaves' => $mostrarLlaves, 'llaves' => $llaves, 'tercerLugar' => $tercerLugar]
            = app(\App\Services\LlavesCampeonatoService::class)->calcular($campeonato, $equipos, $partidos);

        return view('publico.show', compact('campeonato', 'equipos', 'partidos', 'jornadas', 'tabla', 'tablasGrupos', 'jugadores', 'goleadores', 'tarjetas', 'mostrarLlaves', 'llaves', 'tercerLugar'));
    }

}
