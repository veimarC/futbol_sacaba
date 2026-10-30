<?php

namespace App\Services;

use App\Models\Campeonato;
use Illuminate\Support\Collection;

class LlavesCampeonatoService
{
    public function calcular(Campeonato $campeonato, Collection $equipos, Collection $partidos): array
    {
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
                    ->values()
                    ->mapWithKeys(fn ($partidosSerie, $indice) => [
                        ((int) $partidosSerie->first()->orden_en_ronda ?: $indice + 1) - 1
                            => $this->resumirSerie($partidosSerie->values(), $idaVuelta),
                    ]);

                return [
                    'numero' => $numeroRonda,
                    'nombre' => $this->nombreRonda($cantidadPartidos, $numeroRonda, $totalRondas),
                    'series' => collect(range(0, $cantidadPartidos - 1))
                        ->map(fn (int $indice) => $series->get($indice)),
                ];
            });

            // Mostrar clasificados sin crear encuentros con un rival desconocido.
            for ($indiceRonda = 1; $indiceRonda < $llaves->count(); $indiceRonda++) {
                $anteriores = $llaves[$indiceRonda - 1]['series'];
                $actual = $llaves[$indiceRonda];
                $actual['series'] = $actual['series']->map(function ($serie, $indice) use ($anteriores, $config, $indiceRonda, $totalRondas) {
                    if ($serie) return $serie;
                    $ganador = function ($anterior) {
                        if (! $anterior || ! $anterior['completa'] || empty($anterior['ganador_id'])) return null;
                        return (int) $anterior['ganador_id'] === (int) $anterior['equipo_a']?->id
                            ? $anterior['equipo_a'] : $anterior['equipo_b'];
                    };
                    $a = $ganador($anteriores->get($indice * 2));
                    $b = $ganador($anteriores->get($indice * 2 + 1));
                    if (! $a && ! $b) return null;
                    return [
                        'equipo_a' => $a, 'equipo_b' => $b,
                        'goles_a' => 0, 'goles_b' => 0, 'ganador_id' => null,
                        'penales_a' => null, 'penales_b' => null, 'completa' => false,
                        'ida_vuelta' => $indiceRonda + 1 < $totalRondas && ! empty($config['eliminations_home_away']),
                        'partidos' => collect(),
                    ];
                });
                $llaves->put($indiceRonda, $actual);
            }

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
                        if (! $serie || ! $serie['completa'] || ! $serie['ganador_id']) {
                            return null;
                        }

                        return (int) $serie['ganador_id'] === (int) $serie['equipo_a']?->id ? $serie['equipo_b'] : $serie['equipo_a'];
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

        return compact('mostrarLlaves', 'llaves', 'tercerLugar');
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

        $ultimo = $partidos->sortBy('id')->last();
        $penalesA = (int) $ultimo->equipo_local_id === (int) $equipoA?->id ? $ultimo->penales_local : $ultimo->penales_visita;
        $penalesB = (int) $ultimo->equipo_local_id === (int) $equipoA?->id ? $ultimo->penales_visita : $ultimo->penales_local;
        $ganador = null;
        if ($completa) {
            if ($golesA !== $golesB) $ganador = $golesA > $golesB ? $equipoA?->id : $equipoB?->id;
            elseif ($penalesA !== null && $penalesB !== null && $penalesA !== $penalesB) $ganador = $penalesA > $penalesB ? $equipoA?->id : $equipoB?->id;
        }
        return [
            'equipo_a' => $equipoA,
            'equipo_b' => $equipoB,
            'ganador_id' => $ganador,
            'penales_a' => $penalesA,
            'penales_b' => $penalesB,
            'goles_a' => $golesA,
            'goles_b' => $golesB,
            'completa' => $completa,
            'ida_vuelta' => $idaVuelta,
            'partidos' => $partidos,
        ];
    }
}
