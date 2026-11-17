<?php

namespace App\Services;

use Illuminate\Support\Collection;

class EstadisticasCampeonatoService
{
    public function calcular(Collection $equipos, Collection $partidos): array
    {
        $finalizados = $partidos->where('estado', 'jugado');
        $participantes = $equipos->concat($finalizados->pluck('equipoLocal'))
            ->concat($finalizados->pluck('equipoVisita'))->filter()->unique('id');
        $victorias = $participantes->map(function ($equipo) use ($finalizados) {
            return [
                'equipo' => $equipo,
                'total' => $finalizados->filter(fn ($partido) =>
                    ($partido->equipo_local_id == $equipo->id && $partido->goles_local > $partido->goles_visita)
                    || ($partido->equipo_visita_id == $equipo->id && $partido->goles_visita > $partido->goles_local)
                )->count(),
            ];
        })->sortByDesc('total')->values();

        return [
            'partidos' => $finalizados->count(),
            'goles' => $finalizados->whereNull('wo_registrado_at')->sum(
                fn ($partido) => (int) $partido->goles_local + (int) $partido->goles_visita
            ),
            // La doble amarilla representa la segunda amonestación, no otra roja directa.
            'tarjetas' => $finalizados->flatMap(fn ($partido) => $partido->incidencias)
                ->whereIn('tipo', ['amarilla', 'doble_amarilla', 'roja'])->count(),
            'victorias' => $victorias,
            'max_victorias' => max(1, (int) $victorias->max('total')),
        ];
    }
}
