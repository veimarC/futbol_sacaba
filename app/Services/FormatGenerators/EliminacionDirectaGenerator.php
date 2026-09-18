<?php

namespace App\Services\FormatGenerators;

use App\Models\Campeonato;
use App\Models\Fase;
use App\Models\Partido;
use Illuminate\Support\Facades\DB;

class EliminacionDirectaGenerator implements FormatGeneratorInterface
{
    protected Campeonato $campeonato;
    protected array $options = [];

    public function generate(Campeonato $campeonato, array $options = [])
    {
        $this->campeonato = $campeonato;
        $this->options = $options;

        $equipos = $campeonato->equipos()->wherePivot('estado', 'aprobado')->orderBy('nombre')->get()->all();
        $count = count($equipos);
        if ($count < 2) {
            throw new \Exception('Se requieren al menos 2 equipos para generar eliminatoria.');
        }

        // Determinar próximo power of two (octavos=16, cuartos=8, semis=4, final=2)
        $nextPow2 = 1;
        while ($nextPow2 < $count) $nextPow2 *= 2;

        // Si no es potencia de dos, algunos equipos quedarán con bye en primera ronda
        $fase = Fase::create([
            'campeonato_id' => $campeonato->id,
            'tipo' => 'eliminacion',
            'orden' => 1,
            'metadata' => ['tipo' => 'eliminacion_directa', 'teams' => $count, 'bracket_size' => $nextPow2],
            'estado' => 'pendiente'
        ]);

        // Mezclar equipos para sorteo simple
        shuffle($equipos);

        DB::transaction(function() use ($equipos, $fase, $nextPow2) {
            // La final siempre se juega a partido único. En rondas anteriores,
            // el organizador puede solicitar ida y vuelta.
            $idaVuelta = $nextPow2 > 2 && ! empty($this->options['eliminations_home_away']);
            // Ronda inicial: pairings hasta llenar bracket
            $matches = [];
            $slots = array_fill(0, $nextPow2, null);
            // colocar equipos en los primeros N slots
            for ($i = 0; $i < count($equipos); $i++) {
                $slots[$i] = $equipos[$i];
            }

            // Crear partidos por pares de slots
            $i = 0;
            for ($slot = 0; $slot < $nextPow2; $slot += 2) {
                $local = $slots[$slot];
                $visita = $slots[$slot + 1] ?? null;

                // Si visita es null, local queda con bye (no crear partido)
                if ($local === null || $visita === null) continue;

                Partido::create([
                    'campeonato_id' => $this->campeonato->id,
                    'fase_id' => $fase->id,
                    'equipo_local_id' => $local ? $local->id : null,
                    'equipo_visita_id' => $visita ? $visita->id : null,
                    'fecha' => null,
                    'hora' => null,
                    'cancha' => null,
                    'ronda_number' => 1,
                    'orden_en_ronda' => $i + 1,
                ]);

                if ($idaVuelta) {
                    Partido::create([
                        'campeonato_id' => $this->campeonato->id,
                        'fase_id' => $fase->id,
                        'equipo_local_id' => $visita->id,
                        'equipo_visita_id' => $local->id,
                        'fecha' => null,
                        'hora' => null,
                        'cancha' => null,
                        'ronda_number' => 1,
                        // Ambos encuentros comparten el orden para que la vista
                        // pueda reconocerlos como una misma llave.
                        'orden_en_ronda' => $i + 1,
                    ]);
                }
                $i++;
            }

            $fase->estado = 'pendiente';
            $fase->save();
        });

        return $fase;
    }
}
