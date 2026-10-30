<?php

namespace Tests\Feature;

use App\Models\Campeonato;
use App\Models\Equipo;
use App\Models\Partido;
use App\Models\User;
use App\Services\ClasificacionGruposService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClasificacionGruposTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_groups_generate_sixteen_qualifiers_once(): void
    {
        $this->verifyQualification(false);
    }

    public function test_home_away_creates_two_matches_per_tie(): void
    {
        $this->verifyQualification(true);
    }

    public function test_last_group_match_closed_by_walkover_generates_the_bracket(): void
    {
        $this->verifyQualification(false, true);
    }

    private function verifyQualification(bool $homeAway, bool $walkover = false): void
    {
        $owner = User::factory()->create(['rol' => User::ROLE_ORGANIZADOR]);
        $cup = Campeonato::forceCreate([
            'nombre' => 'Copa octavos', 'fecha_inicio' => now()->toDateString(),
            'usuario_creador' => $owner->id, 'formato' => 'grupos_eliminacion',
            'aprobacion_estado' => 'aprobado',
            'formato_config' => ['qualifiers_per_group' => 4, 'elimination_start' => 'octavos', 'eliminations_home_away' => $homeAway],
        ]);
        $teams = [];
        for ($g = 0; $g < 4; $g++) {
            for ($i = 0; $i < 5; $i++) {
                $teams[$g][$i] = Equipo::create(['nombre' => "Grupo $g Equipo $i", 'delegado_id' => $owner->id]);
            }
            $phase = $cup->fases()->create(['tipo' => 'grupo', 'orden' => $g + 1,
                'metadata' => ['teams' => array_map(fn ($t) => $t->id, $teams[$g])], 'estado' => 'pendiente']);
            for ($i = 0; $i < 5; $i++) {
                for ($j = $i + 1; $j < 5; $j++) {
                    $last = Partido::create(['campeonato_id' => $cup->id, 'fase_id' => $phase->id,
                        'equipo_local_id' => $teams[$g][$i]->id, 'equipo_visita_id' => $teams[$g][$j]->id,
                        'goles_local' => 1, 'goles_visita' => 0, 'estado' => 'jugado']);
                }
            }
        }
        $last->update(['estado' => 'programado']);
        $service = app(ClasificacionGruposService::class);
        $this->assertFalse($service->generar($cup));
        $this->assertSame(0, $cup->fases()->where('tipo', 'eliminacion')->count());
        if ($walkover) {
            $this->actingAs($owner)->post(route('partidos.wo', $last), [
                'equipo_perdedor_id' => $last->equipo_visita_id,
                'motivo' => 'inasistencia',
            ])->assertSessionHasNoErrors();
        } else {
            $last->update(['estado' => 'jugado']);
            $this->assertTrue($service->generar($cup));
        }
        $phase = $cup->fases()->where('tipo', 'eliminacion')->sole();
        $this->assertSame(16, $phase->metadata['bracket_size']);
        $matches = $phase->partidos()->orderBy('id')->get();
        $this->assertCount($homeAway ? 16 : 8, $matches);
        $this->assertEquals($teams[0][0]->id, $matches[0]->equipo_local_id);
        $this->assertEquals($teams[1][3]->id, $matches[0]->equipo_visita_id);
        $qualified = $matches->pluck('equipo_local_id')->merge($matches->pluck('equipo_visita_id'))->unique();
        $this->assertCount(16, $qualified);
        foreach ($teams as $group) {
            $this->assertFalse($qualified->contains($group[4]->id));
        }
        $this->assertFalse($service->generar($cup));
        $this->assertSame(1, $cup->fases()->where('tipo', 'eliminacion')->count());
        $this->assertSame($homeAway ? 16 : 8, $phase->partidos()->count());
    }
}