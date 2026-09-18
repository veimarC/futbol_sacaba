<?php

namespace App\Services\FormatGenerators;

use App\Models\Campeonato;
use App\Models\Fase;
use App\Models\Partido;
use App\Models\Equipo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LigaGenerator implements FormatGeneratorInterface
{
    protected Campeonato $campeonato;
    protected array $options = [];

    public function generate(Campeonato $campeonato, array $options = [])
    {
        $this->campeonato = $campeonato;
        $this->options = $options;

        // Obtener equipos inscritos
        $equipos = $campeonato->equipos()->wherePivot('estado', 'aprobado')->orderBy('nombre')->get();
        if ($equipos->count() < 2) {
            throw new \Exception('Se requieren al menos 2 equipos para generar una liga.');
        }

        // Crear fase
        $fase = Fase::create([
            'campeonato_id' => $campeonato->id,
            'tipo' => 'liga',
            'orden' => 1,
            'metadata' => ['tipo' => 'liga_simple'],
            'estado' => 'pendiente'
        ]);

        // Aplicar algoritmo round-robin
        $pairings = $this->roundRobin($equipos);

        DB::transaction(function() use ($pairings, $fase) {
            foreach ($pairings as $ronda => $parejas) {
                $i = 0;
                foreach ($parejas as $par) {
                    [$local, $visita] = $par;
                    if ($local === null || $visita === null) {
                        // bye, no se crea partido
                        continue;
                    }

                    Partido::create([
                        'campeonato_id' => $this->campeonato->id,
                        'fase_id' => $fase->id,
                        'equipo_local_id' => $local->id,
                        'equipo_visita_id' => $visita->id,
                        'fecha' => null,
                        'hora' => null,
                        'cancha' => null,
                        'ronda_number' => $ronda + 1,
                        'orden_en_ronda' => $i + 1,
                    ]);
                    $i++;
                }
            }

            // Marcar fase como pendiente / lista
            $fase->estado = 'pendiente';
            $fase->save();
        });

        return $fase;
    }

    /**
     * Round robin algorithm (circle method)
     * Returns array of rounds, each round is array of pairs [local, visita]
     */
    // Make roundRobin public so other generators can reuse it
    public function roundRobin(Collection $teams)
    {
        $teamsArray = $teams->all();
        $n = count($teamsArray);

        $hasBye = false;
        if ($n % 2 != 0) {
            $hasBye = true;
            $teamsArray[] = null; // bye
            $n++;
        }

        $rounds = $n - 1;
        $matchesPerRound = $n / 2;

        $array = $teamsArray;
        $pairings = [];

        for ($round = 0; $round < $rounds; $round++) {
            $pairs = [];
            for ($i = 0; $i < $matchesPerRound; $i++) {
                $home = $array[$i];
                $away = $array[$n - 1 - $i];
                $pairs[] = [$home, $away];
            }

            $pairings[] = $pairs;

            // Rotate (except first element)
            $fixed = $array[0];
            $rest = array_slice($array, 1);
            array_unshift($rest, array_pop($rest));
            $array = array_merge([$fixed], $rest);
        }

        return $pairings;
    }
}
