<?php

namespace Tests\Feature;

use App\Models\Campeonato;
use App\Models\Equipo;
use App\Models\Fase;
use App\Models\Partido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicMatchdayTest extends TestCase
{
    use RefreshDatabase;

    public function test_rounds_keep_results_and_all_remain_visible(): void
    {
        $owner = User::factory()->create(['rol' => User::ROLE_ORGANIZADOR]);
        $cup = Campeonato::forceCreate([
            'nombre' => 'Copa por jornadas', 'fecha_inicio' => now()->toDateString(),
            'usuario_creador' => $owner->id, 'formato' => 'liga', 'aprobacion_estado' => 'aprobado',
        ]);
        $local = Equipo::create(['nombre' => 'Local', 'delegado_id' => $owner->id]);
        $away = Equipo::create(['nombre' => 'Visitante', 'delegado_id' => $owner->id]);
        $phase = Fase::create(['campeonato_id' => $cup->id, 'tipo' => 'liga', 'orden' => 1]);
        $base = ['campeonato_id' => $cup->id, 'fase_id' => $phase->id, 'equipo_local_id' => $local->id, 'equipo_visita_id' => $away->id];
        $played = Partido::create($base + ['ronda_number' => 1, 'estado' => 'jugado', 'goles_local' => 2, 'goles_visita' => 1]);
        $undated = Partido::create($base + ['ronda_number' => 2, 'estado' => 'programado']);
        $scheduled = Partido::create($base + ['ronda_number' => 2, 'estado' => 'programado', 'fecha' => now()->addDay()->toDateString(), 'hora' => '15:00']);
        Partido::create($base + ['ronda_number' => 3, 'estado' => 'programado']);

        $response = $this->get(route('publico.show', $cup))->assertOk();
        $response->assertDontSee('<details class="matchday"', false)
            ->assertViewHas('jornadas', function ($rounds) use ($played, $scheduled, $undated) {
                return $rounds->count() === 3
                    && $rounds[0]['jugados'] === 1
                    && $rounds[0]['partidos']->first()->id === $played->id
                    && $rounds[1]['partidos']->pluck('id')->all() === [$scheduled->id, $undated->id];
            })
            ->assertSee('Jugado')->assertSee('Programado')->assertSee('Fecha por definir');

        $cup->partidos()->update(['estado' => 'jugado']);
        $this->get(route('publico.show', $cup))->assertOk()->assertDontSee('<details class="matchday"', false);
    }
}
