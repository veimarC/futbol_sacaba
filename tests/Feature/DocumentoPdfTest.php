<?php

namespace Tests\Feature;

use App\Models\{Campeonato, Equipo, Jugador, Partido, User};
use App\Services\DocumentoPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentoPdfTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $owner = User::factory()->create(['rol' => User::ROLE_ORGANIZADOR]);
        $cup = Campeonato::forceCreate(['nombre' => 'Copa PDF', 'fecha_inicio' => now(), 'usuario_creador' => $owner->id, 'formato' => 'liga', 'aprobacion_estado' => 'aprobado']);
        $local = Equipo::create(['nombre' => 'Local PDF', 'delegado_id' => $owner->id]);
        $away = Equipo::create(['nombre' => 'Visitante PDF', 'delegado_id' => $owner->id]);
        $match = Partido::create(['campeonato_id' => $cup->id, 'equipo_local_id' => $local->id, 'equipo_visita_id' => $away->id, 'fecha' => '2026-10-12', 'estado' => 'programado']);
        $this->actingAs($owner);
        return [$cup, $match, $local];
    }

    public function test_sheet_download_is_a_pdf_with_roster_and_local_images(): void
    {
        [$cup, $match, $local] = $this->fixture();
        Storage::fake('public');
        Storage::disk('public')->put('photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aM1sAAAAASUVORK5CYII='));
        $local->update(['escudo' => 'photo.png']);
        $player = Jugador::create(['nombre' => 'Jugador PDF', 'equipo_id' => $local->id, 'foto' => 'photo.png', 'estado' => 'aprobado']);
        $roster = $match->nominas()->create(['equipo_id' => $local->id, 'estado' => 'aprobada']);
        $roster->jugadores()->create(['jugador_id' => $player->id, 'dorsal' => 9]);
        $response = $this->get(route('partidos.planilla.pdf', $match))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $html = view('pdf.planilla', ['partido' => $match->fresh()->load(['campeonato', 'nominas.jugadores.jugador', 'incidencias']), 'equipos' => collect([$local, $match->equipoVisita]), 'imagenes' => app(DocumentoPdfService::class)])->render();
        $this->assertStringContainsString('PROVISIONAL', $html);
        $this->assertStringContainsString('Jugador PDF', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->actingAs(User::factory()->create(['rol' => User::ROLE_ORGANIZADOR]))->get(route('partidos.planilla.pdf', $match))->assertForbidden();
    }

    public function test_sheet_has_writable_empty_cells_and_preserves_recorded_results(): void
    {
        [$cup, $match, $local] = $this->fixture();
        $cup->update(['max_jugadores_equipo' => 16]);
        $render = function () use ($match, $local): \DOMXPath {
            $html = view('pdf.planilla', [
                'partido' => $match->fresh()->load(['campeonato', 'nominas.jugadores.jugador', 'incidencias']),
                'equipos' => collect([$local, $match->equipoVisita]),
                'imagenes' => app(DocumentoPdfService::class),
            ])->render();
            $document = new \DOMDocument();
            @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
            return new \DOMXPath($document);
        };
        $xpath = $render();
        $this->assertSame(16, $xpath->query('//table[@class="roster"]/tbody/tr')->length);
        $this->assertSame(160, $xpath->query('//table[@class="roster"]/tbody/tr/td')->length);
        $this->assertSame(0, $xpath->query('//table[@class="roster"]/tbody/tr/td[normalize-space(.) != ""]')->length);
        $this->assertSame(2, $xpath->query('//span[@class="score-box"][normalize-space(.) = ""]')->length);

        $player = Jugador::create(['nombre' => 'Jugador manual', 'equipo_id' => $local->id, 'estado' => 'aprobado']);
        $roster = $match->nominas()->create(['equipo_id' => $local->id, 'estado' => 'aprobada']);
        $roster->jugadores()->create(['jugador_id' => $player->id, 'dorsal' => 9]);
        $match->incidencias()->create(['jugador_id' => $player->id, 'tipo' => 'gol']);
        $match->update(['goles_local' => 1, 'goles_visita' => 0]);
        $xpath = $render();
        $this->assertSame('1', trim($xpath->query('//span[@class="score-box"]')->item(0)->textContent));
        $this->assertSame('0', trim($xpath->query('//span[@class="score-box"]')->item(1)->textContent));
        $cells = $xpath->query('//table[@class="roster"]/tbody/tr[1]/td');
        $this->assertSame('Jugador manual', trim($cells->item(1)->textContent));
        $this->assertSame('1', trim($cells->item(2)->textContent));
        $this->assertSame('', trim($cells->item(3)->textContent));
        $this->assertSame('', trim($cells->item(4)->textContent));

        $match->incidencias()->delete();
        $match->update(['estado' => 'jugado', 'goles_local' => 0, 'goles_visita' => 0]);
        $xpath = $render();
        $this->assertSame(2, $xpath->query('//span[@class="score-box"][normalize-space(.) = "0"]')->length);
        $this->get(route('partidos.planilla.pdf', $match))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_sheet_and_signatures_fit_on_one_a4_page(): void
    {
        [$cup, $match, $local] = $this->fixture();
        $roster = $match->nominas()->create(['equipo_id' => $local->id, 'estado' => 'aprobada']);
        for ($i = 1; $i <= 25; $i++) {
            $player = Jugador::create(['nombre' => 'Carlos Alejandro', 'apellido' => 'Martinez Alvarez', 'equipo_id' => $local->id]);
            $roster->jugadores()->create(['jugador_id' => $player->id, 'dorsal' => $i]);
        }
        foreach ([25, 30, 40, 100] as $limit) {
            $cup->update(['max_jugadores_equipo' => $limit]);
            $response = $this->get(route('partidos.planilla.pdf', $match))->assertOk();
            $this->assertSame(1, preg_match_all('~/Type\s*/Page\b~', $response->getContent()), "Planilla de {$limit} filas");
        }
    }

    public function test_rules_pdf_includes_saved_rules_and_signature_and_protects_drafts(): void
    {
        [$cup] = $this->fixture();
        $cup->update(['reglamento_interno' => "Primera norma\nSegunda norma <script>no ejecutar</script>"]);
        $response = $this->get(route('campeonatos.reglamento.pdf', $cup))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $html = view('pdf.reglamento', ['campeonato' => $cup->load('organizador')])->render();
        $this->assertStringContainsString('Primera norma', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('Firma del organizador', $html);
        $this->assertStringContainsString(e($cup->organizador->name), $html);
        auth()->logout();
        $this->get(route('campeonatos.reglamento.pdf', $cup))->assertForbidden();
        $cup->forceFill(['aprobacion_estado' => 'borrador'])->save();
        $this->get(route('campeonatos.reglamento.pdf', $cup))->assertForbidden();
        $this->actingAs($cup->organizador)->get(route('campeonatos.reglamento.pdf', $cup))->assertOk();
    }

    public function test_fixture_filters_date_and_checks_permissions_and_empty_days(): void
    {
        [$cup, $match] = $this->fixture();
        $response = $this->get(route('campeonatos.jornada.pdf', [$cup, 'fecha' => '2026-10-12']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->get(route('campeonatos.jornada.pdf', [$cup, 'fecha' => '2026-10-13']))->assertNotFound();
        $this->get(route('campeonatos.jornada.pdf', [$cup, 'fecha' => 'invalid']))->assertSessionHasErrors('fecha');
        $match->update(['fecha' => null]);
        $this->get(route('campeonatos.jornada.pdf', [$cup, 'fecha' => 'sin-fecha']))->assertOk();
        $cup->forceFill(['aprobacion_estado' => 'pendiente'])->save();
        $this->actingAs(User::factory()->create(['rol' => User::ROLE_ORGANIZADOR]))->get(route('campeonatos.jornada.pdf', [$cup, 'fecha' => 'sin-fecha']))->assertForbidden();
    }

    public function test_pdf_permissions_for_public_delegate_and_administrator(): void
    {
        [$cup, $match, $local] = $this->fixture();
        $rules = route('campeonatos.reglamento.pdf', $cup);
        $sheet = route('partidos.planilla.pdf', $match);
        $fixture = route('campeonatos.jornada.pdf', [$cup, 'fecha' => '2026-10-12']);
        $this->actingAs(User::factory()->create(['rol' => 'publico']));
        foreach ([$rules, $sheet, $fixture] as $url) $this->get($url)->assertForbidden();
        $delegate = User::factory()->create(['rol' => User::ROLE_DELEGADO]);
        $local->update(['delegado_id' => $delegate->id]);
        $this->actingAs($delegate);
        $this->get($rules)->assertForbidden();
        $this->get($sheet)->assertForbidden();
        $this->get($fixture)->assertOk();
        $this->get(route('partidos.nominas.index', $match))->assertOk()->assertDontSee($sheet);
        $cup->forceFill(['aprobacion_estado' => 'borrador'])->save();
        $this->get($fixture)->assertForbidden();
        $this->actingAs(User::factory()->create(['rol' => User::ROLE_DESARROLLADOR]));
        foreach ([$rules, $sheet, $fixture] as $url) $this->get($url)->assertOk();
        auth()->logout();
        $this->get($rules)->assertForbidden();
        $this->get($sheet)->assertRedirect(route('login'));
        $this->get($fixture)->assertRedirect(route('login'));
    }

    public function test_match_numbers_restart_for_each_championship(): void
    {
        [$cup, $first] = $this->fixture();
        [, $other] = $this->fixture();
        $second = $first->replicate();
        $second->fecha = '2026-10-01';
        $second->save();
        $this->assertSame(1, $first->numero_campeonato);
        $this->assertSame(1, $other->numero_campeonato);
        $this->assertSame(2, $second->numero_campeonato);
        $this->actingAs($cup->organizador);
        $this->get(route('partidos.planilla.pdf', $second))->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="planilla-campeonato-'.$cup->id.'-partido-2.pdf"');
        $html = view('pdf.planilla', ['partido' => $second, 'equipos' => collect([$second->equipoLocal, $second->equipoVisita]), 'imagenes' => app(DocumentoPdfService::class)])->render();
        $this->assertStringContainsString('Planilla del partido #2', $html);
    }
}
