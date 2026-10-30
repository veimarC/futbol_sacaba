<?php

namespace Tests\Feature;

use App\Models\Campeonato;
use App\Models\Equipo;
use App\Models\Incidencia;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\User;
use App\Services\MarcadorPartidoService;
use App\Services\TablaPosicionesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartidoWalkoverTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $owner = User::factory()->create(['rol' => User::ROLE_ORGANIZADOR]);
        $delegate = User::factory()->create(['rol' => User::ROLE_DELEGADO]);
        $cup = Campeonato::forceCreate([
            'nombre' => 'Copa WO', 'fecha_inicio' => now()->toDateString(),
            'usuario_creador' => $owner->id, 'formato' => 'liga',
            'aprobacion_estado' => 'aprobado',
        ]);
        $local = Equipo::create(['nombre' => 'Local WO', 'delegado_id' => $delegate->id]);
        $away = Equipo::create(['nombre' => 'Visita WO', 'delegado_id' => $delegate->id]);
        $match = Partido::create(['campeonato_id' => $cup->id, 'equipo_local_id' => $local->id,
            'equipo_visita_id' => $away->id, 'estado' => 'programado']);
        return [$owner, $delegate, $cup, $local, $away, $match];
    }

    public function test_absent_visitor_loses_without_rosters_and_points_are_awarded_without_scorers(): void
    {
        [$owner, $delegate, $cup, $local, $away, $match] = $this->fixture();
        $this->actingAs($owner)->get(route('partidos.nominas.index', $match))->assertOk()->assertSee('Confirmar W.O.');
        $this->post(route('partidos.wo', $match), [
            'equipo_perdedor_id' => $away->id, 'motivo' => 'inasistencia',
        ])->assertSessionHasNoErrors()->assertRedirect(route('partidos.nominas.index', $match));
        $match->refresh();
        $this->assertSame('jugado', $match->estado);
        $this->assertEquals(3, $match->goles_local);
        $this->assertEquals(0, $match->goles_visita);
        $this->assertEquals($owner->id, $match->wo_registrado_por);
        $this->assertNotNull($match->wo_registrado_at);
        $this->assertDatabaseCount('incidencias', 0);
        $this->assertDatabaseCount('nominas_partido', 0);
        $table = app(TablaPosicionesService::class)->calcular(collect([$local, $away]), collect([$match]));
        $this->assertSame(3, $table[0]['pts']);
        $this->assertSame(0, $table[1]['pts']);
        app(MarcadorPartidoService::class)->sincronizar($match);
        $this->assertEquals(3, $match->fresh()->goles_local);
        $this->get(route('partidos.nominas.index', $match))->assertOk()->assertSee('No se presentó.')->assertDontSee('Confirmar W.O.');
        $this->get(route('publico.show', $cup))->assertOk()->assertSee('W.O.');
        $this->post(route('partidos.wo', $match), [
            'equipo_perdedor_id' => $local->id, 'motivo' => 'inasistencia',
        ])->assertSessionHasErrors('wo');
        $this->assertEquals(3, $match->fresh()->goles_local);
    }

    public function test_insufficient_local_players_lose_by_walkover(): void
    {
        [$owner, , , $local, , $match] = $this->fixture();
        $this->actingAs($owner)->post(route('partidos.wo', $match), [
            'equipo_perdedor_id' => $local->id, 'motivo' => 'jugadores_insuficientes',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('partidos', ['id' => $match->id, 'goles_local' => 0, 'goles_visita' => 3,
            'wo_motivo' => 'jugadores_insuficientes']);
    }

    public function test_delegate_and_other_organizer_cannot_declare_walkover(): void
    {
        [$owner, $delegate, , $local, , $match] = $this->fixture();
        $this->actingAs($delegate)->get(route('partidos.nominas.index', $match))->assertOk()->assertDontSee('Confirmar W.O.');
        foreach ([$delegate, User::factory()->create(['rol' => User::ROLE_ORGANIZADOR])] as $user) {
            $this->actingAs($user)->post(route('partidos.wo', $match), [
                'equipo_perdedor_id' => $local->id, 'motivo' => 'inasistencia',
            ])->assertForbidden();
        }
        $this->assertSame('programado', $match->fresh()->estado);
    }

    public function test_invalid_team_and_reason_are_rejected(): void
    {
        [$owner, , , , , $match] = $this->fixture();
        $this->actingAs($owner)->post(route('partidos.wo', $match), [
            'equipo_perdedor_id' => 99999, 'motivo' => 'otro',
        ])->assertSessionHasErrors(['equipo_perdedor_id', 'motivo']);
        $this->assertSame('programado', $match->fresh()->estado);
    }

    public function test_existing_incidences_are_not_overwritten(): void
    {
        [$owner, , , $local, , $match] = $this->fixture();
        $player = Jugador::create(['nombre' => 'Jugador', 'equipo_id' => $local->id]);
        Incidencia::create(['partido_id' => $match->id, 'jugador_id' => $player->id,
            'tipo' => 'gol', 'creado_por' => $owner->id]);
        $this->actingAs($owner)->post(route('partidos.wo', $match), [
            'equipo_perdedor_id' => $local->id, 'motivo' => 'inasistencia',
        ])->assertSessionHasErrors('wo');
        $this->assertSame('programado', $match->fresh()->estado);
        $this->assertDatabaseCount('incidencias', 1);
    }
}