<?php

namespace App\Services\FormatGenerators;

use App\Models\Campeonato;
use App\Models\Fase;
use App\Models\Partido;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LigaIdaVueltaGenerator implements FormatGeneratorInterface
{
    protected Campeonato $campeonato;
    protected array $options = [];

    public function generate(Campeonato $campeonato, array $options = [])
    {
        $this->campeonato = $campeonato;
        $this->options = $options;

        $equipos = $campeonato->equipos()->wherePivot('estado', 'aprobado')->orderBy('nombre')->get();
        if ($equipos->count() < 2) {
            throw new \Exception('Se requieren al menos 2 equipos para generar una liga ida y vuelta.');
        }

        $fase = Fase::create([
            'campeonato_id' => $campeonato->id,
            'tipo' => 'liga',
            'orden' => 1,
            'metadata' => ['tipo' => 'liga_ida_vuelta'],
            'estado' => 'pendiente'
        ]);

        // Reusar el algoritmo round-robin de LigaGenerator: generar la primera vuelta
        $baseGen = new LigaGenerator();
        $pairings = $baseGen->roundRobin($equipos);

        DB::transaction(function() use ($pairings, $fase) {
            foreach ($pairings as $ronda => $parejas) {
                $i = 0;
                foreach ($parejas as $par) {
                    [$local, $visita] = $par;
                    if ($local === null || $visita === null) continue;

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

            // Segunda vuelta: invertir local/visita y aumentar ronda_number
            $offset = count($pairings);
            foreach ($pairings as $ronda => $parejas) {
                $i = 0;
                foreach ($parejas as $par) {
                    [$local, $visita] = $par;
                    if ($local === null || $visita === null) continue;

                    Partido::create([
                        'campeonato_id' => $this->campeonato->id,
                        'fase_id' => $fase->id,
                        'equipo_local_id' => $visita->id,
                        'equipo_visita_id' => $local->id,
                        'fecha' => null,
                        'hora' => null,
                        'cancha' => null,
                        'ronda_number' => $offset + $ronda + 1,
                        'orden_en_ronda' => $i + 1,
                    ]);

                    $i++;
                }
            }

            $fase->estado = 'pendiente';
            $fase->save();
        });

        return $fase;
    }
}
