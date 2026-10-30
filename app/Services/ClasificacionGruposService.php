<?php

namespace App\Services;

use App\Models\Campeonato;
use App\Models\Equipo;
use App\Models\Partido;
use Illuminate\Support\Facades\DB;

class ClasificacionGruposService
{
    public function generar(Campeonato $campeonato): bool
    {
        return DB::transaction(function () use ($campeonato) {
            $campeonato = Campeonato::whereKey($campeonato->id)->lockForUpdate()->firstOrFail();
            if ($campeonato->formato !== 'grupos_eliminacion'
                || $campeonato->aprobacion_estado !== 'aprobado'
                || $campeonato->fases()->where('tipo', 'eliminacion')->whereHas('partidos')->exists()) {
                return false;
            }
            $grupos = $campeonato->fases()->where('tipo', 'grupo')->with('partidos')->orderBy('orden')->get();
            $cantidad = (int) data_get($campeonato->formato_config, 'qualifiers_per_group', 0);
            if ($grupos->isEmpty() || $grupos->count() % 2 !== 0
                || ! in_array($cantidad, [1, 2, 4], true)
                || ! in_array($grupos->count() * $cantidad, [2, 4, 8, 16], true)) {
                return false;
            }
            $clasificados = [];
            foreach ($grupos as $grupo) {
                if ($grupo->partidos->isEmpty() || $grupo->partidos->contains(fn ($p) => $p->estado !== 'jugado')) {
                    return false;
                }
                $equipos = Equipo::whereIn('id', data_get($grupo->metadata, 'teams', []))->get();
                if ($equipos->count() < $cantidad) {
                    return false;
                }
                $clasificados[] = array_column(array_slice(
                    app(TablaPosicionesService::class)->calcular($equipos, $grupo->partidos), 0, $cantidad
                ), 'equipo');
            }
            $fase = $campeonato->fases()->where('tipo', 'eliminacion')->orderByDesc('orden')->first();
            $datos = [
                'tipo' => 'eliminacion',
                'metadata' => ['from_groups' => true, 'qualifiers_per_group' => $cantidad, 'bracket_size' => $grupos->count() * $cantidad],
                'estado' => 'pendiente',
            ];
            if ($fase) {
                $fase->update($datos);
            } else {
                $fase = $campeonato->fases()->create($datos + ['orden' => ((int) $campeonato->fases()->max('orden')) + 1]);
            }
            $orden = 0;
            $idaVuelta = $grupos->count() * $cantidad > 2 && ! empty($campeonato->formato_config['eliminations_home_away']);
            $crear = function ($local, $visita) use ($campeonato, $fase, $idaVuelta, &$orden) {
                $datos = ['campeonato_id' => $campeonato->id, 'fase_id' => $fase->id,
                    'ronda_number' => 1, 'orden_en_ronda' => ++$orden, 'estado' => 'programado'];
                Partido::create($datos + ['equipo_local_id' => $local->id, 'equipo_visita_id' => $visita->id]);
                if ($idaVuelta) {
                    Partido::create($datos + ['equipo_local_id' => $visita->id, 'equipo_visita_id' => $local->id]);
                }
            };
            for ($g = 0; $g < count($clasificados); $g += 2) {
                if ($cantidad === 1) {
                    $crear($clasificados[$g][0], $clasificados[$g + 1][0]);
                    continue;
                }
                for ($puesto = 0; $puesto < intdiv($cantidad, 2); $puesto++) {
                    $contrario = $cantidad - 1 - $puesto;
                    $crear($clasificados[$g][$puesto], $clasificados[$g + 1][$contrario]);
                    $crear($clasificados[$g + 1][$puesto], $clasificados[$g][$contrario]);
                }
            }
            return true;
        });
    }
}