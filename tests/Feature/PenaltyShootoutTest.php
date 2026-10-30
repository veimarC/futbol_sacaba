<?php
namespace Tests\Feature;

use App\Models\{Campeonato, Equipo, Incidencia, Jugador, Partido, User};
use App\Services\{LlavesCampeonatoService, ResolucionEliminatoriaService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PenaltyShootoutTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(int $size = 4, bool $double = false): array
    {
        $owner = User::factory()->create(['rol' => User::ROLE_ORGANIZADOR]);
        $cup = Campeonato::forceCreate(['nombre' => 'Copa penales', 'fecha_inicio' => now(),
            'usuario_creador' => $owner->id, 'formato' => 'eliminacion', 'aprobacion_estado' => 'aprobado',
            'formato_config' => ['eliminations_home_away' => $double, 'third_place_match' => true]]);
        $phase = $cup->fases()->create(['tipo' => 'eliminacion', 'orden' => 1, 'estado' => 'pendiente', 'metadata' => ['bracket_size' => $size]]);
        $teams = collect(range(1, $size))->map(fn ($i) => Equipo::create(['nombre' => 'Equipo '.$i, 'delegado_id' => $owner->id]));
        $cup->equipos()->attach($teams->pluck('id')->all(), ['estado' => 'aprobado']);
        $this->actingAs($owner);
        return [$cup, $phase, $teams];
    }

    private function match($cup, $phase, $a, $b, int $order = 1): Partido
    {
        $match = Partido::create(['campeonato_id' => $cup->id, 'fase_id' => $phase->id,
            'equipo_local_id' => $a->id, 'equipo_visita_id' => $b->id, 'ronda_number' => 1,
            'orden_en_ronda' => $order, 'estado' => 'programado']);
        foreach ([$a, $b] as $team) $match->nominas()->create(['equipo_id' => $team->id, 'estado' => 'aprobada']);
        return $match;
    }

    public function test_tied_knockout_requires_distinct_nonnegative_penalties_and_advances_once(): void
    {
        [$cup, $phase, $teams] = $this->fixture();
        $a = $this->match($cup, $phase, $teams[0], $teams[1]);
        $b = $this->match($cup, $phase, $teams[2], $teams[3], 2);
        $this->get(route('partidos.resultado', $a))->assertOk()->assertSee('Desempate por penales');
        $this->get(route('partidos.nominas.index', $a))->assertOk()
            ->assertSee('Planilla del partido')->assertSee('name="penales_local"', false)
            ->assertSee('name="penales_visita"', false);
        foreach ([[], ['penales_local' => 4, 'penales_visita' => 4], ['penales_local' => -1, 'penales_visita' => 4], ['penales_local' => 4]] as $data) {
            $this->put(route('partidos.guardarResultado', $a), $data)->assertSessionHasErrors();
            $this->assertSame('programado', $a->fresh()->estado);
        }
        $this->put(route('partidos.guardarResultado', $a), ['penales_local' => 5, 'penales_visita' => 4])->assertSessionHasNoErrors();
        $this->assertSame(0, (int) $a->fresh()->goles_local);
        $this->get(route('partidos.nominas.index', $a))->assertOk()
            ->assertSee('Finalizado')->assertDontSee('data-sheet-event', false)
            ->assertDontSee('Revisar y finalizar');
        $this->assertDatabaseCount('incidencias', 0);
        $this->put(route('partidos.guardarResultado', $b), ['penales_local' => 3, 'penales_visita' => 4])->assertSessionHasNoErrors();
        $final = $phase->partidos()->where('ronda_number', 2)->where('orden_en_ronda', 1)->firstOrFail();
        $this->assertSame($teams[0]->id, $final->equipo_local_id);
        $this->assertSame($teams[3]->id, $final->equipo_visita_id);
        $bronze = $phase->partidos()->where('ronda_number', 2)->where('orden_en_ronda', 2)->firstOrFail();
        $this->assertSame($teams[1]->id, $bronze->equipo_local_id);
        $this->assertSame($teams[2]->id, $bronze->equipo_visita_id);
        app(ResolucionEliminatoriaService::class)->avanzar($b->fresh());
        $this->assertSame(2, $phase->partidos()->where('ronda_number', 2)->count());
        $this->get(route('publico.show', $cup))->assertOk()->assertSee('Penales: 5');
        $this->get(route('campeonatos.show', $cup))->assertOk()->assertSee('Penales: 5');
    }

    public function test_return_leg_uses_global_score_and_its_own_home_away_penalties(): void
    {
        [$cup, $phase, $teams] = $this->fixture(4, true);
        $ida = $this->match($cup, $phase, $teams[0], $teams[1]);
        $vuelta = $this->match($cup, $phase, $teams[1], $teams[0]);
        $this->put(route('partidos.guardarResultado', $vuelta), ['penales_local' => 4, 'penales_visita' => 3])->assertSessionHasErrors();
        $this->put(route('partidos.guardarResultado', $ida), ['penales_local' => 4, 'penales_visita' => 3])->assertSessionHasErrors();
        // Ida 1-0, vuelta 1-0: el global esta empatado aunque la vuelta no lo este.
        $ida->update(['estado' => 'jugado', 'goles_local' => 1, 'goles_visita' => 0]);
        $player = Jugador::create(['nombre' => 'Goleador', 'equipo_id' => $teams[1]->id, 'estado' => 'aprobado']);
        Incidencia::create(['partido_id' => $vuelta->id, 'jugador_id' => $player->id, 'tipo' => 'gol']);
        $this->put(route('partidos.guardarResultado', $vuelta), ['penales_local' => 5, 'penales_visita' => 4])->assertSessionHasNoErrors();
        $bracket = app(LlavesCampeonatoService::class)->calcular($cup, $teams, $cup->partidos()->with('fase')->get());
        $series = $bracket['llaves']->first()['series']->first();
        $this->assertSame($teams[1]->id, $series['ganador_id']);
        $this->assertSame(4, $series['penales_a']);
        $this->assertSame(5, $series['penales_b']);
        $this->assertSame(1, $series['goles_a']);
        $this->assertSame(1, $series['goles_b']);
        $this->assertDatabaseCount('incidencias', 1);
    }

    public function test_final_by_penalties_identifies_champion_and_does_not_create_another_round(): void
    {
        [$cup, $phase, $teams] = $this->fixture(2, true);
        $final = $this->match($cup, $phase, $teams[0], $teams[1]);
        $this->put(route('partidos.guardarResultado', $final), ['penales_local' => 4, 'penales_visita' => 5])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('partidos', 1);
        $bracket = app(LlavesCampeonatoService::class)->calcular($cup, $teams, $cup->partidos()->with('fase')->get());
        $this->assertSame($teams[1]->id, $bracket['llaves']->last()['series']->first()['ganador_id']);
        $this->get(route('publico.show', $cup))->assertOk()->assertSee('has-champion');
    }

    public function test_round_of_sixteen_creates_quarterfinals_with_penalty_winners(): void
    {
        [$cup, $phase, $teams] = $this->fixture(16);
        foreach ($teams->chunk(2)->values() as $i => $pair) {
            $pair = $pair->values();
            $match = $this->match($cup, $phase, $pair[0], $pair[1], $i + 1);
            $this->put(route('partidos.guardarResultado', $match), ['penales_local' => 5, 'penales_visita' => 4])->assertSessionHasNoErrors();
        }
        $quarters = $phase->partidos()->where('ronda_number', 2)->orderBy('orden_en_ronda')->get();
        $this->assertCount(4, $quarters);
        foreach ($quarters as $i => $match) {
            $this->assertSame($teams[$i * 4]->id, $match->equipo_local_id);
            $this->assertSame($teams[$i * 4 + 2]->id, $match->equipo_visita_id);
        }
    }

    public function test_non_tied_knockout_rejects_penalties_and_delegate_cannot_close_match(): void
    {
        [$cup, $phase, $teams] = $this->fixture(2);
        $match = $this->match($cup, $phase, $teams[0], $teams[1]);
        $player = Jugador::create(['nombre' => 'Gol normal', 'equipo_id' => $teams[0]->id, 'estado' => 'aprobado']);
        Incidencia::create(['partido_id' => $match->id, 'jugador_id' => $player->id, 'tipo' => 'gol']);
        $this->put(route('partidos.guardarResultado', $match), ['penales_local' => 5, 'penales_visita' => 4])->assertSessionHasErrors();
        $this->put(route('partidos.guardarResultado', $match))->assertSessionHasNoErrors();
        $this->assertSame(1, (int) $match->fresh()->goles_local);
        $this->assertNull($match->fresh()->penales_local);
        $delegate = User::factory()->create(['rol' => User::ROLE_DELEGADO]);
        $this->actingAs($delegate)->put(route('partidos.guardarResultado', $match), ['penales_local' => 1, 'penales_visita' => 2])->assertForbidden();
    }

    public function test_pairs_advance_independently_and_keep_their_bracket_slot(): void
    {
        [$cup, $phase, $teams] = $this->fixture(16);
        $matches = [];
        foreach ($teams->chunk(2)->values() as $i => $pair) {
            $pair = $pair->values();
            $matches[$i + 1] = $this->match($cup, $phase, $pair[0], $pair[1], $i + 1);
        }
        foreach ([3, 4] as $order) {
            $this->put(route('partidos.guardarResultado', $matches[$order]), ['penales_local' => 5, 'penales_visita' => 4])->assertSessionHasNoErrors();
        }
        $quarters = $phase->partidos()->where('ronda_number', 2)->get();
        $this->assertCount(1, $quarters);
        $this->assertSame(2, (int) $quarters->first()->orden_en_ronda);
        $this->assertSame($teams[4]->id, $quarters->first()->equipo_local_id);
        $bracket = app(LlavesCampeonatoService::class)->calcular($cup, $teams, $cup->partidos()->with('fase')->get());
        $this->assertNull($bracket['llaves'][1]['series'][0]);
        $this->assertSame($teams[4]->id, $bracket['llaves'][1]['series'][1]['equipo_a']->id);
        foreach ([1, 2] as $order) {
            $this->put(route('partidos.guardarResultado', $matches[$order]), ['penales_local' => 5, 'penales_visita' => 4])->assertSessionHasNoErrors();
        }
        app(ResolucionEliminatoriaService::class)->avanzar($matches[2]);
        $this->assertSame(2, $phase->partidos()->where('ronda_number', 2)->count());
        $this->assertSame('programado', $matches[5]->fresh()->estado);
    }

    public function test_single_winner_is_visible_in_next_round_without_creating_a_match(): void
    {
        [$cup, $phase, $teams] = $this->fixture(8);
        $match = $this->match($cup, $phase, $teams[0], $teams[1], 2);
        $this->put(route('partidos.guardarResultado', $match), ['penales_local' => 4, 'penales_visita' => 5])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('partidos', 1);
        $bracket = app(LlavesCampeonatoService::class)->calcular($cup, $teams, $cup->partidos()->with('fase')->get());
        $next = $bracket['llaves'][1]['series'][0];
        $this->assertNull($next['equipo_a']);
        $this->assertSame($teams[1]->id, $next['equipo_b']->id);
        $this->assertFalse($next['completa']);
        $this->assertNull($bracket['llaves'][2]['series'][0]);
        $this->get(route('publico.show', $cup))->assertOk()->assertSee('Esperando rival');
        $this->get(route('campeonatos.show', $cup))->assertOk()->assertSee('Esperando rival');
    }

    public function test_league_rejects_penalties_and_allows_draws(): void
    {
        [$cup, $phase, $teams] = $this->fixture();
        $phase->update(['tipo' => 'liga']);
        $match = $this->match($cup, $phase, $teams[0], $teams[1]);
        $this->put(route('partidos.guardarResultado', $match), ['penales_local' => 5, 'penales_visita' => 4])->assertSessionHasErrors();
        $this->put(route('partidos.guardarResultado', $match))->assertSessionHasNoErrors();
        $this->assertNull($match->fresh()->penales_local);
    }
}
