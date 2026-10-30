<?php

namespace Tests\Feature;

use App\Models\Campeonato;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChampionshipRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_change_approved_championship_end_date_without_excluding_matches(): void
    {
        $owner = User::factory()->create(['rol' => User::ROLE_ORGANIZADOR]);
        $cup = Campeonato::forceCreate($this->datos() + [
            'usuario_creador' => $owner->id, 'aprobacion_estado' => 'aprobado',
        ]);
        $start = $cup->fecha_inicio->toDateString();
        $end = $cup->fecha_inicio->copy()->addDays(20)->toDateString();
        $this->actingAs($owner)->get(route('campeonatos.edit', $cup))
            ->assertOk()->assertSee('name="fecha_fin"', false)
            ->assertDontSee('name="fecha_inicio"', false);
        $this->put(route('campeonatos.update', $cup), [
            'nombre' => $cup->nombre, 'fecha_fin' => $end,
        ])->assertSessionDoesntHaveErrors();
        $this->assertSame($end, $cup->fresh()->fecha_fin->toDateString());
        $this->put(route('campeonatos.update', $cup), [
            'nombre' => $cup->nombre, 'fecha_fin' => $start,
            'fecha_inicio' => '2000-01-01',
        ])->assertSessionHasErrors('fecha_fin');
        $this->assertSame($start, $cup->fresh()->fecha_inicio->toDateString());

        $teams = collect(['Local', 'Visitante'])->map(fn ($name) => \App\Models\Equipo::create([
            'nombre' => $name, 'delegado_id' => $owner->id,
        ]));
        \App\Models\Partido::create([
            'campeonato_id' => $cup->id, 'equipo_local_id' => $teams[0]->id,
            'equipo_visita_id' => $teams[1]->id, 'fecha' => $end,
        ]);
        $this->put(route('campeonatos.update', $cup), [
            'nombre' => $cup->nombre,
            'fecha_fin' => $cup->fecha_inicio->copy()->addDays(10)->toDateString(),
        ])->assertSessionHasErrors('fecha_fin');
        $this->assertSame($end, $cup->fresh()->fecha_fin->toDateString());
        $this->put(route('campeonatos.update', $cup), [
            'nombre' => $cup->nombre, 'fecha_fin' => $end,
        ])->assertSessionDoesntHaveErrors();

        $other = User::factory()->create(['rol' => User::ROLE_ORGANIZADOR]);
        $this->actingAs($other)->put(route('campeonatos.update', $cup), [
            'nombre' => $cup->nombre, 'fecha_fin' => null,
        ])->assertForbidden();
        $this->assertSame($end, $cup->fresh()->fecha_fin->toDateString());
        $this->actingAs($owner)->put(route('campeonatos.update', $cup), [
            'nombre' => $cup->nombre, 'fecha_fin' => null,
        ])->assertSessionDoesntHaveErrors();
        $this->assertNull($cup->fresh()->fecha_fin);
    }

    public function test_owner_can_configure_match_minimum_after_approval_within_side_size(): void
    {
        $owner = User::factory()->create(['rol' => User::ROLE_ORGANIZADOR]);
        $cup = Campeonato::forceCreate($this->datos() + ['usuario_creador' => $owner->id, 'aprobacion_estado' => 'aprobado']);
        $this->actingAs($owner)->put(route('campeonatos.update', $cup), [
            'nombre' => $cup->nombre, 'min_jugadores_partido' => 5,
        ])->assertSessionDoesntHaveErrors();
        $this->assertSame(5, $cup->fresh()->minimo_para_jugar);
        $this->put(route('campeonatos.update', $cup), [
            'nombre' => $cup->nombre, 'min_jugadores_partido' => 8,
        ])->assertSessionHasErrors('min_jugadores_partido');
        $this->assertSame(5, $cup->fresh()->minimo_para_jugar);
    }

    private function datos(): array
    {
        return [
            'nombre' => 'Copa con reglamento', 'fecha_inicio' => now()->addMonth()->toDateString(),
            'formato' => 'liga', 'modalidad' => 7, 'jugadores_cancha' => 7,
            'min_jugadores_equipo' => 10, 'max_jugadores_equipo' => 15,
            'categoria_edad' => 'libre', 'sexo' => 'mixto',
        ];
    }

    public function test_organizer_can_save_edit_and_publish_rules_as_escaped_text(): void
    {
        $organizador = User::factory()->create(['rol' => User::ROLE_ORGANIZADOR]);
        $texto = "1. Llegar temprano.\n2. Presentar documento.";
        $this->actingAs($organizador)->post(route('campeonatos.store'), $this->datos() + [
            'reglamento_interno' => $texto,
        ])->assertSessionDoesntHaveErrors();
        $campeonato = Campeonato::firstOrFail();
        $this->assertSame($texto, $campeonato->reglamento_interno);
        $this->get(route('campeonatos.edit', $campeonato))->assertOk()->assertSee($texto);

        $texto .= "\n<script>alert(1)</script>";
        $this->put(route('campeonatos.update', $campeonato), $this->datos() + [
            'reglamento_interno' => $texto,
        ])->assertSessionDoesntHaveErrors();
        $this->assertSame($texto, $campeonato->fresh()->reglamento_interno);
        $campeonato->forceFill(['aprobacion_estado' => 'aprobado'])->save();
        auth()->logout();
        $this->get(route('publico.show', $campeonato))->assertOk()
            ->assertSee('Reglamento del campeonato')->assertSee('Reglamentos internos')
            ->assertSee($texto)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_rules_validate_length_and_cannot_be_changed_by_another_organizer(): void
    {
        $owner = User::factory()->create(['rol' => User::ROLE_ORGANIZADOR]);
        $this->actingAs($owner)->post(route('campeonatos.store'), $this->datos() + [
            'reglamento_interno' => str_repeat('a', 10001),
        ])->assertSessionHasErrors('reglamento_interno');
        $this->assertDatabaseCount('campeonatos', 0);

        $this->post(route('campeonatos.store'), $this->datos() + ['reglamento_interno' => 'Reglas originales.'])
            ->assertSessionDoesntHaveErrors();
        $campeonato = Campeonato::firstOrFail();
        $other = User::factory()->create(['rol' => User::ROLE_ORGANIZADOR]);
        $this->actingAs($other)->put(route('campeonatos.update', $campeonato), $this->datos() + [
            'reglamento_interno' => 'Cambio no autorizado.',
        ])->assertForbidden();
        $this->assertSame('Reglas originales.', $campeonato->fresh()->reglamento_interno);
    }

    public function test_owner_can_edit_only_informational_fields_after_approval(): void
    {
        $organizador = User::factory()->create(['rol' => User::ROLE_ORGANIZADOR]);
        $campeonato = Campeonato::forceCreate($this->datos() + [
            'usuario_creador' => $organizador->id,
            'aprobacion_estado' => 'aprobado',
        ]);
        $fechaInicioOriginal = $campeonato->fecha_inicio->toDateString();

        $this->actingAs($organizador)->get(route('campeonatos.edit', $campeonato))
            ->assertOk()
            ->assertSee('Editar datos del campeonato')
            ->assertDontSee('name="fecha_inicio"', false)
            ->assertDontSee('name="formato"', false);

        $this->put(route('campeonatos.update', $campeonato), [
            'nombre' => 'Copa actualizada',
            'descripcion' => 'Descripción actualizada',
            'reglamento_interno' => 'Reglamento actualizado',
            'fecha_inicio' => now()->addYears(2)->toDateString(),
            'formato' => 'eliminacion',
            'modalidad' => 5,
        ])->assertRedirect(route('campeonatos.show', $campeonato));

        $campeonatoActualizado = $campeonato->fresh();
        $this->assertSame('Copa actualizada', $campeonatoActualizado->nombre);
        $this->assertSame('Descripción actualizada', $campeonatoActualizado->descripcion);
        $this->assertSame('Reglamento actualizado', $campeonatoActualizado->reglamento_interno);
        $this->assertSame($fechaInicioOriginal, $campeonatoActualizado->fecha_inicio->toDateString());
        $this->assertSame('liga', $campeonatoActualizado->formato);
        $this->assertSame(7, $campeonatoActualizado->modalidad);
    }

    public function test_existing_championship_without_rules_shows_empty_message(): void
    {
        $owner = User::factory()->create(['rol' => User::ROLE_ORGANIZADOR]);
        $campeonato = Campeonato::forceCreate($this->datos() + [
            'usuario_creador' => $owner->id, 'aprobacion_estado' => 'aprobado',
        ]);
        $this->get(route('publico.show', $campeonato))->assertOk()
            ->assertSee('El organizador todavía no publicó reglamentos internos.');
    }
}
