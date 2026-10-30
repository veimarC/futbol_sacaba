<?php

namespace Tests\Feature;

use App\Models\{Campeonato, Equipo, Partido, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChampionshipDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_populated_championship_without_deleting_shared_teams(): void
    {
        $owner = User::factory()->create(['rol' => User::ROLE_ORGANIZADOR]);
        $admin = User::factory()->create(['rol' => User::ROLE_DESARROLLADOR]);
        $championship = Campeonato::forceCreate(['nombre' => 'Copa', 'fecha_inicio' => '2026-10-01', 'usuario_creador' => $owner->id, 'formato' => 'liga', 'aprobacion_estado' => 'aprobado']);
        $other = Campeonato::forceCreate(['nombre' => 'Otra copa', 'fecha_inicio' => '2026-10-01', 'usuario_creador' => $owner->id, 'formato' => 'liga']);
        $teams = collect(['Local', 'Visita'])->map(fn ($name) => Equipo::forceCreate(['nombre' => $name, 'delegado_id' => $owner->id, 'campeonato_id' => $championship->id]));
        $championship->equipos()->attach($teams->pluck('id')->all());
        $other->equipos()->attach($teams->pluck('id')->all());
        $match = Partido::create(['campeonato_id' => $championship->id, 'equipo_local_id' => $teams[0]->id, 'equipo_visita_id' => $teams[1]->id, 'fecha' => '2026-10-01', 'hora' => '15:00']);
        $otherMatch = Partido::create(['campeonato_id' => $other->id, 'equipo_local_id' => $teams[0]->id, 'equipo_visita_id' => $teams[1]->id, 'fecha' => '2026-10-02', 'hora' => '15:00']);
        DB::table('incidencias')->insert(['partido_id' => $match->id, 'jugador_id' => 1, 'tipo' => 'gol']);
        DB::table('incidencias')->insert(['partido_id' => $otherMatch->id, 'jugador_id' => 1, 'tipo' => 'gol']);

        $this->actingAs($owner)->delete(route('campeonatos.destroy', $championship))->assertSessionHasErrors('error');
        $outsider = User::factory()->create(['rol' => User::ROLE_ORGANIZADOR]);
        $this->actingAs($outsider)->delete(route('campeonatos.destroy', $championship))->assertForbidden();
        $delegate = User::factory()->create(['rol' => User::ROLE_DELEGADO]);
        $this->actingAs($delegate)->delete(route('campeonatos.destroy', $championship))->assertForbidden();
        $this->actingAs($admin)->get(route('campeonatos.show', $championship))->assertOk()->assertSee('Eliminar campeonato');
        $this->delete(route('campeonatos.destroy', $championship))->assertRedirect(route('campeonatos.index'))->assertSessionDoesntHaveErrors();
        $this->assertModelMissing($championship);
        $this->assertModelMissing($match);
        $this->assertModelExists($otherMatch);
        $this->assertDatabaseMissing('incidencias', ['partido_id' => $match->id]);
        $this->assertDatabaseHas('incidencias', ['partido_id' => $otherMatch->id]);
        $this->assertDatabaseMissing('campeonato_equipo', ['campeonato_id' => $championship->id]);
        foreach ($teams as $team) {
            $this->assertModelExists($team);
            $this->assertNull($team->fresh()->campeonato_id);
            $this->assertDatabaseHas('campeonato_equipo', ['campeonato_id' => $other->id, 'equipo_id' => $team->id]);
        }
    }
}
