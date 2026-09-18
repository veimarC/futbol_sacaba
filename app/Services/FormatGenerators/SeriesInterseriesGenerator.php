<?php

namespace App\Services\FormatGenerators;

use App\Models\Campeonato;
use App\Models\Fase;
use App\Models\Partido;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Genera dos series y una fecha especial de clásicos interseries.
 *
 * Los equipos juegan dentro de su serie a una o dos vueltas. Después cada
 * integrante de la Serie A se empareja con uno de la Serie B para conservar
 * los clásicos o cruces especiales del campeonato.
 */
class SeriesInterseriesGenerator implements FormatGeneratorInterface
{
    public function generate(Campeonato $campeonato, array $options = [])
    {
        $equipos = $campeonato->equipos()
            ->wherePivot('estado', 'aprobado')
            ->orderBy('nombre')
            ->get();

        if ($equipos->count() < 4) {
            throw new \Exception('Se requieren al menos 4 equipos para crear dos series.');
        }

        if ($equipos->count() % 2 !== 0) {
            throw new \Exception('El formato de series necesita una cantidad par de equipos para formar los clásicos interseries.');
        }

        $vueltasSerie = max(1, min(2, (int) ($options['series_rounds'] ?? 1)));
        $vueltasClasico = max(1, min(2, (int) ($options['interseries_rounds'] ?? 1)));

        // Se mezcla el listado y se reparte alternadamente para obtener series
        // con la misma cantidad de participantes.
        $sorteados = $equipos->shuffle()->values();
        $serieA = $sorteados->filter(fn ($equipo, int $indice) => $indice % 2 === 0)->values();
        $serieB = $sorteados->filter(fn ($equipo, int $indice) => $indice % 2 === 1)->values();

        return DB::transaction(function () use ($campeonato, $serieA, $serieB, $vueltasSerie, $vueltasClasico) {
            $faseA = $this->crearFaseSerie($campeonato, 'Serie A', 1, $serieA);
            $faseB = $this->crearFaseSerie($campeonato, 'Serie B', 2, $serieB);

            $this->crearRoundRobin($campeonato, $faseA, $serieA, $vueltasSerie);
            $this->crearRoundRobin($campeonato, $faseB, $serieB, $vueltasSerie);

            $faseClasicos = Fase::create([
                'campeonato_id' => $campeonato->id,
                'nombre' => 'Clásicos interseries',
                'tipo' => 'interserie',
                'orden' => 3,
                'metadata' => [
                    'serie_a' => $serieA->pluck('id')->all(),
                    'serie_b' => $serieB->pluck('id')->all(),
                    'vueltas' => $vueltasClasico,
                ],
                'estado' => 'pendiente',
            ]);

            // El equipo A1 juega con B1, A2 con B2, etc. El organizador puede
            // editar posteriormente esos emparejamientos si desea otros clásicos.
            foreach ($serieA as $indice => $equipoA) {
                $equipoB = $serieB->get($indice);
                $this->crearPartido($campeonato, $faseClasicos, $equipoA, $equipoB, 1, $indice + 1);

                if ($vueltasClasico === 2) {
                    $this->crearPartido($campeonato, $faseClasicos, $equipoB, $equipoA, 2, $indice + 1);
                }
            }

            return $faseA;
        });
    }

    private function crearFaseSerie(Campeonato $campeonato, string $nombre, int $orden, Collection $equipos): Fase
    {
        return Fase::create([
            'campeonato_id' => $campeonato->id,
            'nombre' => $nombre,
            'tipo' => 'grupo',
            'orden' => $orden,
            'metadata' => [
                'grupo' => $orden,
                'nombre' => $nombre,
                'teams' => $equipos->pluck('id')->all(),
                'series_interseries' => true,
            ],
            'estado' => 'pendiente',
        ]);
    }

    private function crearRoundRobin(Campeonato $campeonato, Fase $fase, Collection $equipos, int $vueltas): void
    {
        $emparejamientos = (new LigaGenerator())->roundRobin($equipos);

        foreach ($emparejamientos as $ronda => $parejas) {
            foreach ($parejas as $orden => [$local, $visita]) {
                if (! $local || ! $visita) continue;
                $this->crearPartido($campeonato, $fase, $local, $visita, $ronda + 1, $orden + 1);
            }
        }

        if ($vueltas === 2) {
            $desplazamiento = count($emparejamientos);
            foreach ($emparejamientos as $ronda => $parejas) {
                foreach ($parejas as $orden => [$local, $visita]) {
                    if (! $local || ! $visita) continue;
                    $this->crearPartido($campeonato, $fase, $visita, $local, $desplazamiento + $ronda + 1, $orden + 1);
                }
            }
        }
    }

    private function crearPartido(Campeonato $campeonato, Fase $fase, $local, $visita, int $ronda, int $orden): void
    {
        Partido::create([
            'campeonato_id' => $campeonato->id,
            'fase_id' => $fase->id,
            'equipo_local_id' => $local->id,
            'equipo_visita_id' => $visita->id,
            'fecha' => null,
            'hora' => null,
            'cancha' => null,
            'ronda_number' => $ronda,
            'orden_en_ronda' => $orden,
        ]);
    }
}
