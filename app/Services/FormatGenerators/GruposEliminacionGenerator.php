<?php

namespace App\Services\FormatGenerators;

use App\Models\Campeonato;
use App\Models\Fase;
use App\Models\Partido;
use App\Models\Equipo;
use Illuminate\Support\Facades\DB;

class GruposEliminacionGenerator implements FormatGeneratorInterface
{
    protected Campeonato $campeonato;
    protected array $options = [];

    /**
     * Options expected keys (all optional):
     * - number_of_groups (int)
     * - teams_per_group (int)
     * - qualifiers_per_group (int)
     * - elimination_start (string) one of: 'octavos','cuartos','semifinal','final'
     * - eliminations_home_away (bool)
     */
    public function generate(Campeonato $campeonato, array $options = [])
    {
        $this->campeonato = $campeonato;
        $this->options = $options;

        $equipos = $campeonato->equipos()->wherePivot('estado', 'aprobado')->orderBy('nombre')->get()->all();
        $count = count($equipos);
        if ($count < 2) {
            throw new \Exception('Se requieren al menos 2 equipos para generar grupos + eliminatoria.');
        }

        $numGroups = $options['number_of_groups'] ?? null;
        $teamsPerGroup = $options['teams_per_group'] ?? null;
        $qualifiers = $options['qualifiers_per_group'] ?? 1;
        $eliminationStart = $options['elimination_start'] ?? 'cuartos';
        $homeAwayElims = !empty($options['eliminations_home_away']);

        // Si no se indicaron grupos, intentar inferir: si teams_per_group existe usarlo
        if (!$numGroups) {
            if ($teamsPerGroup) {
                $numGroups = (int) floor($count / $teamsPerGroup);
                if ($numGroups < 1) $numGroups = 1;
            } else {
                // defecto: 2 grupos
                $numGroups = ($count <= 8) ? 2 : 4;
            }
        }

        // Asignar equipos a grupos (sorteo): mezclar y repartir round-robin
        shuffle($equipos);
        $groups = [];
        for ($g = 0; $g < $numGroups; $g++) {
            $groups[$g] = [];
        }
        $i = 0;
        foreach ($equipos as $team) {
            $groups[$i % $numGroups][] = $team;
            $i++;
        }

        DB::transaction(function() use ($groups, $qualifiers, $eliminationStart, $homeAwayElims) {
            // Crear fase de grupos por grupo
            $orden = 1;
            $groupFases = [];
            foreach ($groups as $index => $groupTeams) {
                $fase = Fase::create([
                    'campeonato_id' => $this->campeonato->id,
                    'tipo' => 'grupo',
                    'orden' => $orden++,
                    'metadata' => ['grupo' => $index + 1, 'teams' => array_map(function($t){ return $t->id; }, $groupTeams)],
                    'estado' => 'pendiente'
                ]);

                // generar round-robin dentro del grupo
                $lg = new LigaGenerator();
                $pairings = $lg->roundRobin(collect($groupTeams));

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
                        if ((int) ($this->options['group_rounds'] ?? 1) === 2) {
                            Partido::create([
                                'campeonato_id' => $this->campeonato->id,
                                'fase_id' => $fase->id,
                                'equipo_local_id' => $visita->id,
                                'equipo_visita_id' => $local->id,
                                'ronda_number' => count($pairings) + $ronda + 1,
                                'orden_en_ronda' => $i + 1,
                            ]);
                        }
                        $i++;
                    }
                }

                $groupFases[] = $fase;
            }

            // Crear fase eliminatoria placeholder (se llenará con clasificados cuando los grupos terminen)
            // La fase eliminatoria se crea cuando terminan los grupos y se
            // conocen los clasificados; así no se guardan partidos sin equipos.

            // No crear partidos de eliminatoria ahora si no hay clasificados calculados — crear estructura vacía según tamaño
            // calcular número de clasificados
            $totalQualifiers = 0;
            // determinar bracket size (next power of two)
            $nextPow2 = 1;
            while ($nextPow2 < $totalQualifiers) $nextPow2 *= 2;

            // crear placeholders de partidos para la ronda inicial
            $i = 0;
            for ($slot = 0; $slot < $totalQualifiers; $slot += 2) {
                Partido::create([
                    'campeonato_id' => $this->campeonato->id,
                    'fase_id' => null,
                    'equipo_local_id' => null,
                    'equipo_visita_id' => null,
                    'fecha' => null,
                    'hora' => null,
                    'cancha' => null,
                    'ronda_number' => 1,
                    'orden_en_ronda' => $i + 1,
                ]);
                $i++;
            }

        });

        return $groupFases[0] ?? null;
    }
}
