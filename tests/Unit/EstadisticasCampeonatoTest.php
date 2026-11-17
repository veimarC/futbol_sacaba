<?php

namespace Tests\Unit;

use App\Models\Equipo;
use App\Models\Incidencia;
use App\Models\Partido;
use App\Services\EstadisticasCampeonatoService;
use Tests\TestCase;

class EstadisticasCampeonatoTest extends TestCase
{
    public function test_separates_walkovers_penalties_and_unfinished_matches(): void
    {
        $local = new Equipo(['nombre' => 'Local']);
        $local->id = 1;
        $visita = new Equipo(['nombre' => 'Visita']);
        $visita->id = 2;
        $crear = function (array $datos, array $tarjetas = []) use ($local, $visita) {
            $partido = new Partido(array_merge([
                'estado' => 'jugado', 'equipo_local_id' => 1, 'equipo_visita_id' => 2,
                'goles_local' => 2, 'goles_visita' => 1,
            ], $datos));
            $partido->setRelation('equipoLocal', $local);
            $partido->setRelation('equipoVisita', $visita);
            $partido->setRelation('incidencias', collect($tarjetas)->map(fn ($tipo) => new Incidencia(['tipo' => $tipo])));
            return $partido;
        };

        $resultado = (new EstadisticasCampeonatoService)->calcular(collect([$local]), collect([
            $crear([], ['gol', 'amarilla', 'doble_amarilla', 'roja']),
            $crear(['goles_local' => 0, 'goles_visita' => 3, 'wo_registrado_at' => '2026-11-16 12:00:00']),
            $crear(['goles_local' => 1, 'goles_visita' => 1, 'penales_local' => 5, 'penales_visita' => 4]),
            $crear(['estado' => 'programado', 'goles_local' => 9], ['roja']),
        ]));

        $this->assertSame(3, $resultado['partidos']);
        $this->assertSame(5, $resultado['goles']);
        $this->assertSame(3, $resultado['tarjetas']);
        $this->assertSame([1 => 1, 2 => 1], $resultado['victorias']->mapWithKeys(fn ($fila) => [$fila['equipo']->id => $fila['total']])->all());
    }

    public function test_empty_championship_has_zero_totals_and_safe_chart_scale(): void
    {
        $resultado = (new EstadisticasCampeonatoService)->calcular(collect(), collect());

        $this->assertSame(0, $resultado['partidos']);
        $this->assertSame(0, $resultado['goles']);
        $this->assertSame(0, $resultado['tarjetas']);
        $this->assertTrue($resultado['victorias']->isEmpty());
        $this->assertSame(1, $resultado['max_victorias']);
    }
}
