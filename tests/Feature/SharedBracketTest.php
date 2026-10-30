<?php

namespace Tests\Feature;

use App\Models\Campeonato;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedBracketTest extends TestCase
{
    use RefreshDatabase;

    public function test_standings_and_bracket_tabs_match_the_championship_format(): void
    {
        $owner = User::factory()->create(['rol' => User::ROLE_ORGANIZADOR]);
        foreach (['liga', 'grupos_eliminacion', 'eliminacion'] as $format) {
            $cup = Campeonato::forceCreate([
                'nombre' => 'Torneo '.$format, 'fecha_inicio' => today(),
                'usuario_creador' => $owner->id, 'formato' => $format, 'aprobacion_estado' => 'aprobado',
            ]);
            auth()->logout();
            $public = $this->get(route('publico.show', $cup))->assertOk();
            $private = $this->actingAs($owner)->get(route('campeonatos.show', $cup))->assertOk();
            foreach ([[$public, 'tabla'], [$private, 'tab']] as [$response, $panel]) {
                if ($format === 'eliminacion') {
                    $response->assertDontSee('id="'.$panel.'"', false)
                        ->assertDontSee($panel === 'tabla' ? 'data-module="tabla"' : 'data-bs-target="#tab"', false)
                        ->assertViewHas('tabla', []);
                } else {
                    $response->assertSee('id="'.$panel.'"', false);
                }
                $response->assertViewHas('mostrarLlaves', $format !== 'liga');
                $response->assertSee('id="goleadores"', false)->assertSee('id="tarjetas"', false);
            }
        }
    }

    public function test_visitor_and_organizer_see_the_same_preliminary_bracket(): void
    {
        $owner = User::factory()->create(['rol' => User::ROLE_ORGANIZADOR]);
        $cup = Campeonato::forceCreate([
            'nombre' => 'Copa compartida',
            'fecha_inicio' => now()->toDateString(),
            'usuario_creador' => $owner->id,
            'formato' => 'grupos_eliminacion',
            'aprobacion_estado' => 'aprobado',
            'formato_config' => ['elimination_start' => 'quarterfinals', 'third_place_match' => true],
        ]);

        $public = $this->get(route('publico.show', $cup))->assertOk();
        $private = $this->actingAs($owner)->get(route('campeonatos.show', $cup))->assertOk();

        foreach ([$public, $private] as $response) {
            $response->assertSee('CAMINO A LA COPA')
                ->assertSee('Por definir')
                ->assertSee('Tercer lugar')
                ->assertViewHas('llaves', fn ($rounds) =>
                    $rounds->pluck('nombre')->all() === ['Cuartos de final', 'Semifinales', 'Final']
                    && $rounds->map(fn ($round) => $round['series']->count())->all() === [4, 2, 1]
                );
        }

        $this->assertSame(
            view('publico.partials.bracket', $public->viewData('llaves') ? [
                'llaves' => $public->viewData('llaves'), 'tercerLugar' => $public->viewData('tercerLugar'),
            ] : [])->render(),
            view('publico.partials.bracket', [
                'llaves' => $private->viewData('llaves'), 'tercerLugar' => $private->viewData('tercerLugar'),
            ])->render()
        );
        $this->assertDatabaseCount('partidos', 0);
    }
}
