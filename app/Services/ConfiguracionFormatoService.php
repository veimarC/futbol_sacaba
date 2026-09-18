<?php

namespace App\Services;

use App\Models\Campeonato;
use InvalidArgumentException;

/** Calcula el formato cuando ya se conoce la cantidad de equipos aprobados. */
class ConfiguracionFormatoService
{
    /** Devuelve cantidades que forman una llave completa sin clasificar a todo el grupo. */
    public function opcionesClasificados(Campeonato $campeonato): array
    {
        $grupos = $campeonato->fases()->where('tipo', 'grupo')->orderBy('orden')->get();
        if ($grupos->isEmpty()) {
            return [];
        }

        $menorGrupo = $grupos
            ->map(fn ($grupo) => count(data_get($grupo->metadata, 'teams', [])))
            ->min();
        if ($menorGrupo < 2) {
            return [];
        }

        $cantidadGrupos = $grupos->count();

        return collect(range(1, min(4, $menorGrupo - 1)))
            ->filter(fn (int $cantidad) => in_array($cantidadGrupos * $cantidad, [2, 4, 8, 16], true))
            ->values()
            ->all();
    }

    public function inicioEliminatoria(int $totalClasificados): string
    {
        return match ($totalClasificados) {
            2 => 'final',
            4 => 'semifinal',
            8 => 'cuartos',
            16 => 'octavos',
            default => throw new InvalidArgumentException('La cantidad de clasificados no forma una llave completa.'),
        };
    }

    public function calcular(Campeonato $campeonato, int $cantidadEquipos): array
    {
        if ($cantidadEquipos < 2) {
            throw new InvalidArgumentException('Se requieren al menos 2 equipos aprobados para generar el fixture.');
        }

        $anterior = $campeonato->formato_config ?? [];

        return match ($campeonato->formato) {
            'liga' => [
                // Con torneos pequeños se juega ida y vuelta; con más equipos,
                // una sola vuelta evita un calendario excesivamente largo.
                'league_rounds' => $cantidadEquipos <= 10 ? 2 : 1,
                'calculated_for_teams' => $cantidadEquipos,
            ],
            'grupos_eliminacion' => $this->configuracionCopa($cantidadEquipos, $anterior),
            'eliminacion' => [
                'eliminations_home_away' => (bool) ($anterior['eliminations_home_away'] ?? false),
                'third_place_match' => (bool) ($anterior['third_place_match'] ?? false),
                'calculated_for_teams' => $cantidadEquipos,
            ],
            default => $anterior + ['calculated_for_teams' => $cantidadEquipos],
        };
    }

    private function configuracionCopa(int $cantidadEquipos, array $anterior): array
    {
        if ($cantidadEquipos < 4) {
            throw new InvalidArgumentException('La Copa necesita al menos 4 equipos aprobados. Usa Liga o Eliminación directa para menos equipos.');
        }

        $numeroGrupos = match (true) {
            $cantidadEquipos <= 10 => 2,
            $cantidadEquipos <= 20 => 4,
            default => 8,
        };

        $menorGrupo = intdiv($cantidadEquipos, $numeroGrupos);
        $clasificadosPorGrupo = $menorGrupo >= 3 ? 2 : 1;
        $totalClasificados = $numeroGrupos * $clasificadosPorGrupo;

        $inicioEliminatoria = $this->inicioEliminatoria($totalClasificados);

        return [
            'number_of_groups' => $numeroGrupos,
            'teams_per_group' => (int) ceil($cantidadEquipos / $numeroGrupos),
            'qualifiers_per_group' => $clasificadosPorGrupo,
            'elimination_start' => $inicioEliminatoria,
            'eliminations_home_away' => (bool) ($anterior['eliminations_home_away'] ?? false),
            'third_place_match' => (bool) ($anterior['third_place_match'] ?? false),
            'calculated_for_teams' => $cantidadEquipos,
        ];
    }
}
