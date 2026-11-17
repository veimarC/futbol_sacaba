<?php

namespace Tests\Feature;

use App\Models\Campeonato;
use App\Models\Equipo;
use App\Models\User;
use App\Services\InscripcionTardiaService;
use App\Services\ClasificacionGruposService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InscripcionTardiaTest extends TestCase
{
    use RefreshDatabase;

    private function campeonato(string $formato = 'liga', int $vueltas = 1): array
    {
        Notification::fake();
        $owner = User::factory()->create(['rol' => User::ROLE_ORGANIZADOR]);
        $cup = Campeonato::forceCreate([
            'nombre' => 'Copa con cupos', 'usuario_creador' => $owner->id,
            'fecha_inicio' => today()->subDays(2), 'fecha_fin' => today()->addMonth(),
            'fecha_limite_inscripcion' => today()->subDays(2), 'inscripciones_abiertas' => true,
            'estado' => 'en_curso', 'aprobacion_estado' => 'aprobado', 'formato' => $formato,
            'formato_config' => ['league_rounds' => $vueltas, 'group_rounds' => $vueltas],
        ]);
        for ($i = 0; $i < 4; $i++) $this->equipo($cup, 'aprobado');
        return [$owner, $cup];
    }

    private function equipo(Campeonato $cup, string $estado = 'pendiente'): Equipo
    {
        $delegate = User::factory()->create(['rol' => User::ROLE_DELEGADO]);
        $team = Equipo::create(['nombre' => $delegate->name, 'delegado_id' => $delegate->id]);
        $cup->equipos()->attach($team, ['estado' => $estado]);
        return $team;
    }

    private function fixture(User $owner, Campeonato $cup, int $cupos = 2): void
    {
        $this->actingAs($owner)->post(route('campeonatos.generarFixture', $cup), [
            'cupos_reservados' => $cupos, 'jornada_limite' => 1,
        ])->assertSessionHasNoErrors();
        $cup->refresh();
    }

    public function test_late_teams_complete_reserved_schedule_without_changing_existing_results(): void
    {
        [$owner, $cup] = $this->campeonato();
        $this->fixture($owner, $cup);
        $this->assertNull($cup->fecha_limite_inscripcion);
        $played = $cup->partidos()->where('ronda_number', 1)->firstOrFail();
        $played->update(['estado' => 'jugado', 'goles_local' => 2, 'goles_visita' => 1]);
        $before = $cup->partidos()->get()->mapWithKeys(fn ($p) => [$p->id => $p->getAttributes()]);
        for ($i = 0; $i < 2; $i++) {
            $team = $this->equipo($cup);
            $this->actingAs($owner)->post(route('campeonatos.equipos.gestionar', [$cup, $team]), ['estado' => 'aprobado'])->assertSessionHasNoErrors();
        }
        $this->assertSame(15, $cup->partidos()->count());
        foreach ($before as $id => $attributes) $this->assertSame($attributes, $cup->partidos()->findOrFail($id)->getAttributes());
        $this->assertFalse($cup->aceptaInscripciones());
        $this->assertSame(0, app(InscripcionTardiaService::class)->cuposDisponibles($cup));
        $this->assertSame(15, $cup->partidos()->get()->map(fn ($p) => collect([$p->equipo_local_id, $p->equipo_visita_id])->sort()->implode('-'))->unique()->count());
        $this->actingAs($owner)->get(route('campeonatos.show', $cup))->assertOk()->assertSee('Inscripciones hasta la jornada 1');
    }

    public function test_deadline_blocks_requests_and_pending_approvals(): void
    {
        [$owner, $cup] = $this->campeonato();
        $this->fixture($owner, $cup);
        $pending = $this->equipo($cup);
        $cup->partidos()->where('ronda_number', 2)->firstOrFail()->update(['estado' => 'jugado']);
        $this->assertFalse($cup->aceptaInscripciones());
        $this->actingAs($owner)->post(route('campeonatos.equipos.gestionar', [$cup, $pending]), ['estado' => 'aprobado'])->assertSessionHasErrors('inscripcion');
        $this->assertDatabaseHas('campeonato_equipo', ['campeonato_id' => $cup->id, 'equipo_id' => $pending->id, 'estado' => 'pendiente']);
        $this->actingAs($pending->delegado)->post(route('equipos.solicitarInscripcion'), ['campeonato_id' => $cup->id, 'equipo_id' => $pending->id])->assertSessionHasErrors();
        $this->get(route('campeonatos.descubrir'))->assertOk()->assertDontSee('Copa con cupos');
    }

    public function test_scheduled_start_closes_deadline_even_without_results(): void
    {
        [$owner, $cup] = $this->campeonato();
        $this->fixture($owner, $cup);
        $cup->partidos()->where('ronda_number', 2)->firstOrFail()->update(['fecha' => today()->subDay(), 'hora' => '12:00']);
        $this->assertFalse($cup->aceptaInscripciones());
    }

    public function test_home_and_away_reservations_create_both_legs_once(): void
    {
        [$owner, $cup] = $this->campeonato('liga', 2);
        $this->fixture($owner, $cup, 1);
        $team = $this->equipo($cup);
        $this->post(route('campeonatos.equipos.gestionar', [$cup, $team]), ['estado' => 'aprobado'])->assertSessionHasNoErrors();
        $this->assertSame(20, $cup->partidos()->count());
        $this->post(route('campeonatos.equipos.gestionar', [$cup, $team]), ['estado' => 'aprobado'])->assertSessionHasErrors();
        $this->assertSame(20, $cup->partidos()->count());
    }

    public function test_legacy_home_and_away_format_keeps_two_legs(): void
    {
        [$owner, $cup] = $this->campeonato('liga_ida_vuelta');
        $this->fixture($owner, $cup, 1);
        $team = $this->equipo($cup);
        $this->post(route('campeonatos.equipos.gestionar', [$cup, $team]), ['estado' => 'aprobado'])->assertSessionHasNoErrors();
        $this->assertSame(20, $cup->partidos()->count());
    }

    public function test_groups_wait_for_registration_closure_before_qualifying(): void
    {
        [$owner, $cup] = $this->campeonato('grupos_eliminacion');
        $this->fixture($owner, $cup);
        // All current games may finish while slots still remain open.
        $cup->partidos()->update(['estado' => 'jugado', 'ronda_number' => 1]);
        $this->assertFalse(app(ClasificacionGruposService::class)->generar($cup));
        $this->post(route('campeonatos.cerrarInscripciones', $cup))->assertSessionHasNoErrors();
        $this->assertFalse($cup->refresh()->aceptaInscripciones());
        $this->assertSame(1, $cup->fases()->where('tipo', 'eliminacion')->count());
    }

    public function test_groups_add_team_to_metadata_and_only_their_reserved_group(): void
    {
        [$owner, $cup] = $this->campeonato('grupos_eliminacion', 2);
        $this->fixture($owner, $cup);
        $team = $this->equipo($cup);
        $this->post(route('campeonatos.equipos.gestionar', [$cup, $team]), ['estado' => 'aprobado'])->assertSessionHasNoErrors();
        $groups = $cup->fases()->get();
        $group = $groups->first(fn ($g) => in_array($team->id, $g->metadata['teams']));
        $this->assertNotNull($group);
        $this->assertSame(4, $group->partidos()->where(fn ($q) => $q->where('equipo_local_id', $team->id)->orWhere('equipo_visita_id', $team->id))->count());
        $this->assertSame(1, $groups->filter(fn ($g) => in_array($team->id, $g->metadata['teams']))->count());
    }

    public function test_invalid_configuration_rolls_back_and_elimination_rejects_reservations(): void
    {
        [$owner, $cup] = $this->campeonato();
        $this->actingAs($owner)->post(route('campeonatos.generarFixture', $cup), ['cupos_reservados' => 1, 'jornada_limite' => 99])->assertSessionHasErrors();
        $this->assertSame(0, $cup->fases()->count());
        $cup->update(['formato' => 'eliminacion']);
        $this->post(route('campeonatos.generarFixture', $cup), ['cupos_reservados' => 1, 'jornada_limite' => 1])->assertSessionHasErrors();
        $this->assertSame(0, $cup->partidos()->count());
    }

    public function test_no_reservations_keeps_registration_closed_and_protects_old_fixtures(): void
    {
        [$owner, $cup] = $this->campeonato();
        $this->fixture($owner, $cup, 0);
        $this->assertFalse($cup->aceptaInscripciones());
        $this->assertSame(6, $cup->partidos()->count());
        $this->post(route('campeonatos.generarFixture', $cup), ['cupos_reservados' => 2, 'jornada_limite' => 1])->assertSessionHasErrors();
        $this->assertSame(6, $cup->partidos()->count());
    }

    public function test_last_slot_cannot_be_reused_after_approval_or_withdrawal(): void
    {
        [$owner, $cup] = $this->campeonato();
        $this->fixture($owner, $cup, 1);
        $first = $this->equipo($cup);
        $second = $this->equipo($cup);
        $this->post(route('campeonatos.equipos.gestionar', [$cup, $first]), ['estado' => 'aprobado'])->assertSessionHasNoErrors();
        $cup->equipos()->updateExistingPivot($first->id, ['estado' => 'retirado']);
        $this->post(route('campeonatos.equipos.gestionar', [$cup, $second]), ['estado' => 'aprobado'])->assertSessionHasErrors();
        $this->assertSame(10, $cup->partidos()->count());
    }

    public function test_calendar_cannot_be_deleted_or_extended_manually_and_delegates_cannot_close_it(): void
    {
        [$owner, $cup] = $this->campeonato();
        $this->fixture($owner, $cup);
        $match = $cup->partidos()->firstOrFail();
        $this->delete(route('partidos.destroy', $match))->assertSessionHasErrors('partido');
        $this->post(route('partidos.store'), [
            'campeonato_id' => $cup->id, 'equipo_local_id' => $match->equipo_local_id,
            'equipo_visita_id' => $match->equipo_visita_id, 'fecha' => today()->toDateString(),
            'hora' => '12:00', 'lugar' => 'Cancha central',
        ])->assertSessionHasErrors('campeonato_id');
        $this->actingAs($match->equipoLocal->delegado)->post(route('campeonatos.cerrarInscripciones', $cup))->assertForbidden();
    }

    public function test_reprogramming_started_later_round_cannot_reopen_registration(): void
    {
        [$owner, $cup] = $this->campeonato();
        $this->fixture($owner, $cup);
        $match = $cup->partidos()->where('ronda_number', 2)->firstOrFail();
        $match->update(['fecha' => today()->subDay(), 'hora' => '12:00']);
        $this->put(route('partidos.update', $match), [
            'campeonato_id' => $cup->id, 'equipo_local_id' => $match->equipo_local_id,
            'equipo_visita_id' => $match->equipo_visita_id,
            'fecha' => today()->addDays(5)->toDateString(), 'hora' => '15:00', 'lugar' => 'Cancha central',
        ])->assertSessionHasNoErrors();
        $this->assertFalse($cup->refresh()->inscripciones_abiertas);
        $this->assertFalse($cup->aceptaInscripciones());
    }
}
