<?php

namespace App\Services;

use Illuminate\Support\Collection;

class TablaPosicionesService
{
    public function calcular(Collection $equipos, Collection $partidos): array
    {
        $jugados = $partidos->where('estado', 'jugado');
        $tabla = $equipos->map(function ($equipo) use ($jugados) {
            $fila = ['equipo' => $equipo, 'pj' => 0, 'pg' => 0, 'pe' => 0, 'pp' => 0, 'gf' => 0, 'gc' => 0];

            foreach ($jugados as $partido) {
                $esLocal = (int) $partido->equipo_local_id === (int) $equipo->id;
                $esVisita = (int) $partido->equipo_visita_id === (int) $equipo->id;
                if (! $esLocal && ! $esVisita) continue;

                $favor = (int) ($esLocal ? $partido->goles_local : $partido->goles_visita);
                $contra = (int) ($esLocal ? $partido->goles_visita : $partido->goles_local);
                $fila['pj']++;
                $fila['gf'] += $favor;
                $fila['gc'] += $contra;
                if ($favor > $contra) $fila['pg']++;
                elseif ($favor === $contra) $fila['pe']++;
                else $fila['pp']++;
            }

            $fila['dif'] = $fila['gf'] - $fila['gc'];
            $fila['pts'] = ($fila['pg'] * 3) + $fila['pe'];
            return $fila;
        })->all();

        usort($tabla, fn ($a, $b) => [$b['pts'], $b['dif'], $b['gf'], $a['equipo']->nombre]
            <=> [$a['pts'], $a['dif'], $a['gf'], $b['equipo']->nombre]);

        return $tabla;
    }
}
