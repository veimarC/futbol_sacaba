<?php

namespace App\Services;

use App\Models\Incidencia;
use App\Models\Jugador;
use App\Models\Partido;

class SuspensionJugadorService
{
    /** Una roja directa o doble amarilla impide jugar el siguiente encuentro. */
    public function estaSuspendidoPara(Jugador $jugador, Partido $partido): bool
    {
        $partidosConExpulsion = Incidencia::query()
            ->where('jugador_id', $jugador->id)
            ->whereIn('tipo', [Incidencia::TIPO_ROJA, Incidencia::TIPO_DOBLE_AMARILLA])
            ->whereHas('partido', fn ($query) => $query
                ->where('campeonato_id', $partido->campeonato_id)
                ->where('estado', 'jugado'))
            ->with('partido')->get()->pluck('partido')->filter()
            ->filter(fn (Partido $anterior) => $this->clave($anterior) < $this->clave($partido))
            ->sortBy(fn (Partido $anterior) => $this->clave($anterior));

        $partidoExpulsion = $partidosConExpulsion->last();
        if (! $partidoExpulsion) {
            return false;
        }

        $cumplioSancion = Partido::query()
            ->where('campeonato_id', $partido->campeonato_id)
            ->where('estado', 'jugado')
            ->where(fn ($query) => $query
                ->where('equipo_local_id', $jugador->equipo_id)
                ->orWhere('equipo_visita_id', $jugador->equipo_id))
            ->get()
            ->contains(fn (Partido $jugado) =>
                $this->clave($jugado) > $this->clave($partidoExpulsion)
                && $this->clave($jugado) < $this->clave($partido)
            );

        return ! $cumplioSancion;
    }

    private function clave(Partido $partido): string
    {
        return ($partido->fecha?->format('Ymd') ?? '99999999').sprintf('%010d', $partido->id);
    }
}
