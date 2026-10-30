<?php

namespace Tests\Feature;

use App\Models\{Campeonato, Equipo, Incidencia, Jugador, Partido, User};
use App\Notifications\Channels\WhatsAppChannel;
use App\Services\{SuspensionJugadorService, WhatsAppService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Tests\TestCase;

class ProjectReviewTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $owner = User::factory()->create(['rol' => 'organizador']);
        $cup = Campeonato::forceCreate(['nombre' => 'Copa', 'fecha_inicio' => today(),
            'usuario_creador' => $owner->id, 'formato' => 'liga', 'aprobacion_estado' => 'aprobado']);
        $teams = collect(['Local', 'Visita', 'Otro'])->map(fn ($name) => Equipo::create([
            'nombre' => $name, 'delegado_id' => $owner->id,
        ]));
        foreach ($teams as $team) $cup->equipos()->attach($team->id, ['estado' => 'aprobado']);
        $match = Partido::create(['campeonato_id' => $cup->id, 'equipo_local_id' => $teams[0]->id,
            'equipo_visita_id' => $teams[1]->id, 'fecha' => today(), 'hora' => '15:00', 'estado' => 'programado']);
        $this->actingAs($owner);
        return [$cup, $teams, $match];
    }

    public function test_invalid_match_time_is_rejected_before_saving(): void
    {
        [$cup, $teams] = $this->fixture();
        foreach (['25:00', 'texto', ['15:00']] as $time) {
            $this->post(route('partidos.store'), ['campeonato_id' => $cup->id,
                'equipo_local_id' => $teams[0]->id, 'equipo_visita_id' => $teams[1]->id,
                'fecha' => today()->toDateString(), 'hora' => $time, 'lugar' => 'Cancha'])
                ->assertSessionHasErrors('hora');
        }
        $this->assertDatabaseCount('partidos', 1);
    }

    public function test_rostered_match_keeps_teams_but_can_be_rescheduled(): void
    {
        [$cup, $teams, $match] = $this->fixture();
        $match->nominas()->create(['equipo_id' => $teams[0]->id, 'estado' => 'aprobada']);
        $data = ['campeonato_id' => $cup->id, 'equipo_local_id' => $teams[2]->id,
            'equipo_visita_id' => $teams[1]->id, 'fecha' => today()->toDateString(),
            'hora' => '17:00', 'lugar' => 'Cancha'];
        $this->put(route('partidos.update', $match), $data)->assertSessionHasErrors('equipo_local_id');
        $this->assertSame($teams[0]->id, $match->fresh()->equipo_local_id);
        $data['equipo_local_id'] = $teams[0]->id;
        $this->put(route('partidos.update', $match), $data)->assertSessionHasNoErrors();
        $this->assertSame('17:00', substr($match->fresh()->hora, 0, 5));
    }

    public function test_suspension_uses_match_time_instead_of_creation_order_on_same_day(): void
    {
        [, $teams, $later] = $this->fixture();
        $earlier = $later->replicate();
        $earlier->fill(['hora' => '10:00', 'estado' => 'jugado'])->save();
        $player = Jugador::create(['nombre' => 'Jugador', 'equipo_id' => $teams[0]->id, 'estado' => 'aprobado']);
        Incidencia::create(['partido_id' => $earlier->id, 'jugador_id' => $player->id, 'tipo' => 'roja']);
        $service = app(SuspensionJugadorService::class);
        $this->assertTrue($service->estaSuspendidoPara($player, $later));
        $served = $later->replicate();
        $served->fill(['hora' => '12:00', 'estado' => 'jugado'])->save();
        $this->assertFalse($service->estaSuspendidoPara($player, $later));
    }

    public function test_whatsapp_channel_accepts_template_only_notifications(): void
    {
        config(['services.whatsapp.enabled' => true]);
        $service = $this->mock(WhatsAppService::class);
        $service->shouldReceive('enviarPlantilla')->once()->with('71234567', 'aviso', [], 'es')->andReturn(['ok' => true]);
        $notification = new class extends Notification {
            public function toWhatsAppTemplate(object $notifiable): array { return ['name' => 'aviso']; }
        };
        $recipient = (object) ['whatsapp' => '71234567', 'whatsapp_opt_in_at' => now()];
        $this->assertSame(['ok' => true], app(WhatsAppChannel::class)->send($recipient, $notification));
    }

    public function test_delegate_cannot_overwrite_an_approved_roster_with_a_direct_request(): void
    {
        [, $teams, $match] = $this->fixture();
        $delegate = User::factory()->create(['rol' => 'delegado']);
        $teams[0]->update(['delegado_id' => $delegate->id]);
        $player = Jugador::create(['nombre' => 'Jugador', 'equipo_id' => $teams[0]->id, 'estado' => 'aprobado']);
        $roster = $match->nominas()->create(['equipo_id' => $teams[0]->id, 'estado' => 'aprobada']);
        $roster->jugadores()->create(['jugador_id' => $player->id, 'dorsal' => 9]);
        $this->actingAs($delegate)->put(route('partidos.nominas.guardar', [$match, $teams[0]]), [
            'dorsales' => [$player->id => 10],
        ])->assertStatus(422);
        $this->assertSame('aprobada', $roster->fresh()->estado);
        $this->assertSame(9, (int) $roster->jugadores()->first()->dorsal);
    }

    public function test_whatsapp_channel_requires_consent_and_handles_non_model_recipients(): void
    {
        config(['services.whatsapp.enabled' => true]);
        $service = $this->mock(WhatsAppService::class);
        $service->shouldReceive('enviarTexto')->once()->andThrow(new \RuntimeException('Fallo simulado'));
        $notification = new class extends Notification {
            public function toWhatsApp(object $notifiable): string { return 'Aviso'; }
        };
        $recipient = (object) ['whatsapp' => '71234567', 'whatsapp_opt_in_at' => null];
        $channel = app(WhatsAppChannel::class);
        $this->assertNull($channel->send($recipient, $notification));
        $recipient->whatsapp_opt_in_at = now();
        $this->assertNull($channel->send($recipient, $notification));
    }
}
