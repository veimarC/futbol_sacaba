<?php
namespace App\Services;

use App\Models\Partido;
use Illuminate\Validation\ValidationException;

class ResolucionEliminatoriaService
{
    public function contexto(Partido $partido): array
    {
        $eliminacion = $partido->fase?->tipo === 'eliminacion';
        $total = (int) round(log(max(2, (int) data_get($partido->fase?->metadata, 'bracket_size', 2)), 2));
        $doble = $eliminacion && (int) $partido->ronda_number < $total
            && (bool) data_get($partido->campeonato->formato_config, 'eliminations_home_away', false);
        $serie = $eliminacion ? $partido->fase->partidos()
            ->where('ronda_number', $partido->ronda_number)
            ->whereIn('equipo_local_id', [$partido->equipo_local_id, $partido->equipo_visita_id])
            ->whereIn('equipo_visita_id', [$partido->equipo_local_id, $partido->equipo_visita_id])
            ->orderBy('id')->get() : collect([$partido]);
        $vuelta = $doble && $serie->count() === 2 && $serie->last()->id === $partido->id;
        $lista = ! $doble || ($vuelta && $serie->first()->estado === 'jugado');
        $local = (int) $partido->goles_local;
        $visita = (int) $partido->goles_visita;
        if ($vuelta) {
            $ida = $serie->first();
            $local += (int) ($ida->equipo_local_id === $partido->equipo_local_id ? $ida->goles_local : $ida->goles_visita);
            $visita += (int) ($ida->equipo_local_id === $partido->equipo_local_id ? $ida->goles_visita : $ida->goles_local);
        }
        return compact('eliminacion', 'doble', 'vuelta', 'lista', 'local', 'visita')
            + ['requiere_penales' => $eliminacion && $lista && $local === $visita];
    }

    public function validar(Partido $partido, array $datos): array
    {
        $contexto = $this->contexto($partido);
        if ($contexto['vuelta'] && ! $contexto['lista']) {
            throw ValidationException::withMessages(['penales_local' => 'Primero registra el resultado de ida.']);
        }
        $local = $datos['penales_local'] ?? null;
        $visita = $datos['penales_visita'] ?? null;
        if ($contexto['requiere_penales']) {
            if ($local === null || $visita === null || (int) $local === (int) $visita) {
                throw ValidationException::withMessages(['penales_local' => 'La eliminatoria esta empatada. Registra una tanda de penales con un ganador.']);
            }
        } elseif ($local !== null || $visita !== null) {
            throw ValidationException::withMessages(['penales_local' => 'Los penales solo corresponden al desempate final de una eliminatoria.']);
        }
        return ['penales_local' => $local, 'penales_visita' => $visita];
    }

    public function avanzar(Partido $partido): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($partido) {
            \App\Models\Campeonato::whereKey($partido->campeonato_id)->lockForUpdate()->firstOrFail();
            $this->avanzarCruces($partido->fresh());
        });
    }

    private function avanzarCruces(Partido $partido): void
    {
        if ($partido->fase?->tipo !== 'eliminacion') return;
        $fase = $partido->fase;
        $size = (int) data_get($fase->metadata, 'bracket_size', 0);
        $ronda = (int) $partido->ronda_number;
        if ($size < 2 || $ronda < 1) return;
        $total = (int) round(log($size, 2));
        if ($ronda >= $total) return;
        $series = $fase->partidos()->where('ronda_number', $ronda)->orderBy('orden_en_ronda')->orderBy('id')->get()
            ->groupBy(function ($p) {
                $ids = [(int) $p->equipo_local_id, (int) $p->equipo_visita_id];
                sort($ids);
                return implode('-', $ids);
            });
        $ganadores = [];
        $perdedores = [];
        foreach ($series as $serie) {
            $ultimo = $serie->last();
            $ctx = $this->contexto($ultimo);
            if ($serie->count() !== ($ctx['doble'] ? 2 : 1) || $serie->contains(fn ($p) => $p->estado !== 'jugado')) continue;
            if ($ctx['local'] === $ctx['visita']) {
                if ($ultimo->penales_local === null || $ultimo->penales_visita === null || $ultimo->penales_local === $ultimo->penales_visita) continue;
                $ganaLocal = $ultimo->penales_local > $ultimo->penales_visita;
            } else {
                $ganaLocal = $ctx['local'] > $ctx['visita'];
            }
            $orden = (int) $serie->first()->orden_en_ronda;
            if ($orden < 1) continue;
            $ganadores[$orden] = $ganaLocal ? $ultimo->equipo_local_id : $ultimo->equipo_visita_id;
            $perdedores[$orden] = $ganaLocal ? $ultimo->equipo_visita_id : $ultimo->equipo_local_id;
        }
        $crear = function ($local, $visita, $orden, $doble) use ($partido, $fase, $ronda) {
            if ($fase->partidos()->where('ronda_number', $ronda + 1)->where('orden_en_ronda', $orden)->exists()) return;
            $base = ['campeonato_id' => $partido->campeonato_id, 'fase_id' => $fase->id,
                'ronda_number' => $ronda + 1, 'orden_en_ronda' => $orden, 'estado' => 'programado'];
            Partido::create($base + ['equipo_local_id' => $local, 'equipo_visita_id' => $visita]);
            if ($doble) Partido::create($base + ['equipo_local_id' => $visita, 'equipo_visita_id' => $local]);
        };
        $doble = $ronda + 1 < $total && (bool) data_get($partido->campeonato->formato_config, 'eliminations_home_away', false);
        $cantidad = intdiv($size, 2 ** $ronda);
        for ($orden = 1; $orden < $cantidad; $orden += 2) {
            if (isset($ganadores[$orden], $ganadores[$orden + 1])) {
                $crear($ganadores[$orden], $ganadores[$orden + 1], intdiv($orden + 1, 2), $doble);
            }
        }
        if ($ronda + 1 === $total && isset($perdedores[1], $perdedores[2]) && data_get($partido->campeonato->formato_config, 'third_place_match', false)) {
            $crear($perdedores[1], $perdedores[2], 2, false);
        }
    }
}
