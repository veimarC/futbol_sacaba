<?php

namespace Tests\Feature;

use App\Models\Campeonato;
use App\Models\Equipo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChampionshipApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function datos(): array
    {
        return [
            'nombre' => 'Copa en revision', 'fecha_inicio' => now()->addMonth()->toDateString(),
            'fecha_fin' => now()->addMonths(2)->toDateString(), 'formato' => 'liga',
            'modalidad' => 7, 'jugadores_cancha' => 7, 'min_jugadores_equipo' => 7,
            'max_jugadores_equipo' => 15, 'categoria_edad' => 'libre', 'sexo' => 'mixto',
            'inscripciones_abiertas' => true,
        ];
    }

    public function test_format_choices_are_saved_used_by_fixture_and_locked_after_approval(): void
    {
        $owner = User::factory()->create(['rol' => 'organizador']);
        $this->actingAs($owner)->get(route('campeonatos.create'))->assertOk()
            ->assertSee('name="vueltas_liga"', false)->assertSee('name="vueltas_grupos"', false)
            ->assertSee('name="ida_vuelta_eliminatorias"', false)->assertSee('name="partido_tercer_lugar"', false);
        foreach (['liga', 'grupos_eliminacion', 'eliminacion'] as $format) {
            $data = array_replace($this->datos(), ['formato' => $format, 'vueltas_liga' => 2,
                'vueltas_grupos' => 2, 'ida_vuelta_eliminatorias' => 1, 'partido_tercer_lugar' => 1]);
            $this->post(route('campeonatos.store'), $data)->assertSessionHasNoErrors();
            $cup = Campeonato::latest('id')->firstOrFail();
            $expected = $format === 'liga' ? ['league_rounds' => 2]
                : ['eliminations_home_away' => true, 'third_place_match' => true];
            if ($format === 'grupos_eliminacion') $expected['group_rounds'] = 2;
            $this->assertSame($expected, $cup->formato_config);
            $this->get(route('campeonatos.edit', $cup))->assertOk()
                ->assertSee($format === 'eliminacion' ? 'value="1" checked' : 'value="2" selected', false);
            $this->put(route('campeonatos.update', $cup), $data)->assertSessionHasNoErrors();
            $this->assertSame($expected, $cup->fresh()->formato_config);
            $cup->forceFill(['aprobacion_estado' => 'aprobado'])->save();
            foreach (range(1, 4) as $number) {
                $team = Equipo::create(['nombre' => 'Equipo '.$number, 'delegado_id' => $owner->id]);
                $cup->equipos()->attach($team->id, ['estado' => 'aprobado']);
            }
            $this->post(route('campeonatos.generarFixture', $cup))->assertSessionHasNoErrors();
            $this->assertSame($format === 'liga' ? 12 : 4, $cup->partidos()->count());
            foreach ($cup->partidos()->get() as $match) {
                $this->assertTrue($cup->partidos()->where('equipo_local_id', $match->equipo_visita_id)
                    ->where('equipo_visita_id', $match->equipo_local_id)->exists());
            }
            $approved = $cup->fresh()->formato_config;
            $this->put(route('campeonatos.update', $cup), array_replace($data, [
                'vueltas_liga' => 1, 'vueltas_grupos' => 1, 'ida_vuelta_eliminatorias' => 0, 'partido_tercer_lugar' => 0,
            ]))->assertSessionHasNoErrors();
            $this->assertSame($approved, $cup->fresh()->formato_config);
        }
    }

    public function test_draft_is_private_and_cannot_publish_itself(): void
    {
        $owner = User::factory()->create(['rol' => 'organizador']);
        $this->actingAs($owner)->post(route('campeonatos.store'), $this->datos() + [
            'aprobacion_estado' => 'aprobado', 'revisado_por' => $owner->id,
        ])->assertSessionHasNoErrors();
        $cup = Campeonato::firstOrFail();
        $this->assertSame('borrador', $cup->aprobacion_estado);
        $this->assertNull($cup->revisado_por);
        $this->get(route('campeonatos.show', $cup))->assertOk()->assertSee('Enviar a revisión');
        $this->get(route('publico.index'))->assertDontSee($cup->nombre);
        $this->get(route('publico.show', $cup))->assertNotFound();
        $this->post(route('campeonatos.generarFixture', $cup))->assertForbidden();
        $this->get(route('partidos.create', ['campeonato' => $cup->id]))->assertForbidden();
        $this->post(route('campeonatos.revisar', $cup), ['decision' => 'aprobado'])->assertForbidden();

        $delegate = User::factory()->create(['rol' => 'delegado']);
        $team = Equipo::create(['nombre' => 'Equipo solicitante', 'delegado_id' => $delegate->id]);
        $this->actingAs($delegate)->get(route('campeonatos.show', $cup))->assertForbidden();
        $this->get(route('campeonatos.descubrir'))->assertDontSee($cup->nombre);
        $this->post(route('equipos.solicitarInscripcion'), ['equipo_id' => $team->id, 'campeonato_id' => $cup->id])
            ->assertSessionHasErrors('error');
        $this->assertDatabaseMissing('campeonato_equipo', ['campeonato_id' => $cup->id]);
        $other = User::factory()->create(['rol' => 'organizador']);
        $this->actingAs($other)->post(route('campeonatos.enviarRevision', $cup))->assertForbidden();
        $this->get(route('campeonatos.show', $cup))->assertForbidden();
    }

    public function test_return_correct_resubmit_and_approve_workflow(): void
    {
        $owner = User::factory()->create(['rol' => 'organizador']);
        $admin = User::factory()->create(['rol' => 'desarrollador']);
        $cup = Campeonato::create($this->datos() + ['usuario_creador' => $owner->id, 'estado' => 'pendiente']);
        $this->actingAs($owner)->post(route('campeonatos.enviarRevision', $cup))->assertRedirect();
        $this->assertSame('pendiente', $cup->fresh()->aprobacion_estado);
        $this->post(route('campeonatos.enviarRevision', $cup))->assertStatus(409);
        $this->put(route('campeonatos.update', $cup), $this->datos())->assertStatus(409);
        $this->actingAs($admin)->get(route('campeonatos.index'))->assertSee($cup->nombre);
        $this->get(route('campeonatos.show', $cup))->assertOk()->assertSee('Aprobar y publicar');
        $this->post(route('campeonatos.revisar', $cup), ['decision' => 'devuelto'])->assertSessionHasErrors('motivo');
        $this->post(route('campeonatos.revisar', $cup), ['decision' => 'devuelto', 'motivo' => 'Revisar las fechas del campeonato.'])->assertSessionHasNoErrors();
        $this->assertSame('devuelto', $cup->fresh()->aprobacion_estado);
        $this->actingAs($owner)->get(route('campeonatos.show', $cup))->assertSee('Revisar las fechas');
        $this->put(route('campeonatos.update', $cup), array_replace($this->datos(), ['nombre' => 'Copa corregida']))->assertSessionHasNoErrors();
        $this->post(route('campeonatos.enviarRevision', $cup))->assertRedirect();
        $this->actingAs($admin)->post(route('campeonatos.revisar', $cup), ['decision' => 'aprobado'])->assertSessionHasNoErrors();
        $cup->refresh();
        $this->assertSame('aprobado', $cup->aprobacion_estado);
        $this->assertSame($admin->id, (int) $cup->revisado_por);
        $this->assertNotNull($cup->revisado_at);
        $this->assertNull($cup->aprobacion_motivo);
        $this->post(route('campeonatos.revisar', $cup), ['decision' => 'devuelto', 'motivo' => 'Respuesta repetida del administrador.'])->assertStatus(409);
        $this->get(route('publico.index'))->assertSee('Copa corregida');
        $this->get(route('publico.show', $cup))->assertOk();
        $this->actingAs($owner)->put(route('campeonatos.update', $cup), $this->datos())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('aprobado', $cup->fresh()->aprobacion_estado);
        $this->get(route('partidos.create', ['campeonato' => $cup->id]))->assertOk();
        $delegate = User::factory()->create(['rol' => 'delegado']);
        $team = Equipo::create(['nombre' => 'Equipo aprobado', 'delegado_id' => $delegate->id]);
        $this->actingAs($delegate)->post(route('equipos.solicitarInscripcion'), ['equipo_id' => $team->id, 'campeonato_id' => $cup->id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('campeonato_equipo', ['campeonato_id' => $cup->id, 'equipo_id' => $team->id, 'estado' => 'pendiente']);
    }

    public function test_admin_cannot_approve_a_draft_without_submission(): void
    {
        $admin = User::factory()->create(['rol' => 'desarrollador']);
        $owner = User::factory()->create(['rol' => 'organizador']);
        $cup = Campeonato::create($this->datos() + ['usuario_creador' => $owner->id, 'estado' => 'pendiente']);
        $this->actingAs($admin)->post(route('campeonatos.revisar', $cup), ['decision' => 'aprobado'])->assertStatus(409);
        $this->assertSame('borrador', $cup->fresh()->aprobacion_estado);
    }
}
