<?php

namespace Tests\Feature;

use App\Models\{Campeonato, Equipo, Jugador, Partido, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_player_in_active_championship_requires_organizer_approval(): void
    {
        $owner = User::factory()->create(['rol' => 'organizador']);
        $delegate = User::factory()->create(['rol' => 'delegado']);
        $cup = Campeonato::forceCreate([
            'nombre' => 'Copa en curso', 'usuario_creador' => $owner->id,
            'fecha_inicio' => today()->subDays(5), 'fecha_fin' => today()->addMonth(),
            'formato' => 'liga', 'aprobacion_estado' => 'aprobado',
            'sexo' => 'mixto', 'categoria_edad' => 'libre', 'max_jugadores_equipo' => 25,
        ]);
        $team = Equipo::create(['nombre' => 'Local', 'delegado_id' => $delegate->id]);
        $away = Equipo::create(['nombre' => 'Visita', 'delegado_id' => $owner->id]);
        $cup->equipos()->attach([$team->id => ['estado' => 'aprobado'], $away->id => ['estado' => 'aprobado']]);
        $match = Partido::create(['campeonato_id' => $cup->id, 'equipo_local_id' => $team->id,
            'equipo_visita_id' => $away->id, 'fecha' => today()->addDay(), 'hora' => '15:00', 'estado' => 'programado']);
        $this->assertSame('en_curso', $cup->estado_actual);

        $this->actingAs($delegate)->post(route('jugadores.store'), [
            'nombre' => 'Neymar', 'sexo' => 'masculino', 'equipo_id' => $team->id,
            'estado' => 'aprobado',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $player = $team->jugadores()->sole();
        $this->assertSame(Jugador::ESTADO_PENDIENTE, $player->estado);
        $this->assertSame('Nuevo jugador pendiente', $owner->notifications()->first()->data['titulo']);
        $rosterUrl = route('partidos.nominas.guardar', [$match, $team]);
        $this->put($rosterUrl, ['dorsales' => [$player->id => 10]])->assertSessionHasErrors('dorsales');
        $this->assertDatabaseCount('nomina_partido_jugadores', 0);
        $this->post(route('jugadores.aprobar', $player))->assertForbidden();
        $other = User::factory()->create(['rol' => 'organizador']);
        $this->actingAs($other)->post(route('jugadores.aprobar', $player))->assertForbidden();

        $this->actingAs($owner)->get(route('equipos.show', $team))->assertOk()->assertSee('Aprobar')->assertSee('Rechazar');
        $this->post(route('jugadores.aprobar', $player))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(Jugador::ESTADO_APROBADO, $player->fresh()->estado);
        $this->actingAs($delegate)->put($rosterUrl, ['dorsales' => [$player->id => 10]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('nomina_partido_jugadores', ['jugador_id' => $player->id, 'dorsal' => 10]);
    }
}
