<?php

namespace App\Services;

use App\Models\Incidencia;
use App\Models\Partido;

/** Mantiene el marcador sincronizado con los goles registrados. */
class MarcadorPartidoService
{
    public function sincronizar(Partido $partido): Partido
    {
        $contarGoles = fn (int $equipoId): int => Incidencia::query()
            ->where('partido_id', $partido->id)
            ->where('tipo', Incidencia::TIPO_GOL)
            ->whereHas('jugador', fn ($query) => $query->where('equipo_id', $equipoId))
            ->count();

        $partido->update([
            'goles_local' => $contarGoles((int) $partido->equipo_local_id),
            'goles_visita' => $contarGoles((int) $partido->equipo_visita_id),
        ]);

        return $partido->refresh();
    }
}
