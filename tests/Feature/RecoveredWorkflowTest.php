<?php

namespace Tests\Feature;

use App\Models\Campeonato;
use App\Models\Equipo;
use App\Models\Fase;
use App\Models\Incidencia;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\User;
use App\Notifications\NotificacionPlataforma;
use Database\Seeders\DelegadosEquiposSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecoveredWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_messages_are_shown_in_spanish_with_friendly_field_names(): void
    {
        app()->setLocale('es');

        $validator = validator(
            ['fecha_inicio' => '2026-08-14', 'fecha_limite_inscripcion' => '2026-08-15'],
            ['fecha_limite_inscripcion' => 'before_or_equal:fecha_inicio']
        );

        $this->assertSame(
            'La fecha límite de inscripción debe ser anterior o igual a la fecha de inicio del campeonato.',
            $validator->errors()->first('fecha_limite_inscripcion')
        );
    }

    public function test_organizer_can_save_a_championship_from_the_wizard(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);

        $this->actingAs($organizador)->post(route('campeonatos.store'), [
            'nombre' => 'Copa Sacaba',
            'fecha_inicio' => '2026-08-14',
            'fecha_fin' => '2026-08-30',
            'fecha_limite_inscripcion' => '2026-08-14',
            'inscripciones_abiertas' => '1',
            'estado' => 'pendiente',
            'formato' => 'grupos_eliminacion',
            'modalidad' => 7,
            'jugadores_cancha' => 7,
            'min_jugadores_equipo' => 10,
            'max_jugadores_equipo' => 15,
            'categoria_edad' => 'libre',
            'sexo' => 'masculino',
            'formato_config' => [
                'league_rounds' => '2',
                'elimination_start' => 'semifinal',
                'eliminations_home_away' => '0',
                'third_place_match' => '0',
            ],
        ])->assertRedirect(route('campeonatos.index'));

        $this->assertDatabaseHas('campeonatos', [
            'nombre' => 'Copa Sacaba',
            'usuario_creador' => $organizador->id,
            'formato' => 'grupos_eliminacion',
            'modalidad' => 7,
            'max_jugadores_equipo' => 15,
            'sexo' => 'masculino',
        ]);

        $campeonato = Campeonato::where('nombre', 'Copa Sacaba')->firstOrFail();
        $this->assertSame('pendiente', $campeonato->estado);
        $this->assertSame(['eliminations_home_away' => false, 'third_place_match' => false, 'group_rounds' => 1], $campeonato->formato_config);
    }

    public function test_championship_roster_rules_are_validated_clearly(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);

        $this->actingAs($organizador)->post(route('campeonatos.store'), [
            'nombre' => 'Copa con reglas inválidas',
            'fecha_inicio' => now()->addMonth()->format('Y-m-d'),
            'estado' => 'pendiente',
            'formato' => 'liga',
            'modalidad' => 7,
            'jugadores_cancha' => 7,
            'min_jugadores_equipo' => 6,
            'max_jugadores_equipo' => 5,
            'categoria_edad' => 'libre',
            'sexo' => 'mixto',
        ])->assertSessionHasErrors(['min_jugadores_equipo', 'max_jugadores_equipo']);
    }

    public function test_organizer_can_create_custom_age_category(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);

        $this->actingAs($organizador)->post(route('campeonatos.store'), [
            'nombre' => 'Copa Universitaria',
            'fecha_inicio' => now()->addMonth()->format('Y-m-d'),
            'estado' => 'pendiente', 'formato' => 'liga',
            'modalidad' => 7, 'jugadores_cancha' => 7,
            'min_jugadores_equipo' => 10, 'max_jugadores_equipo' => 16,
            'categoria_edad' => 'personalizada', 'categoria_personalizada' => 'Universitarios 18 a 24',
            'edad_min' => 18, 'edad_max' => 24, 'sexo' => 'mixto',
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('campeonatos', [
            'nombre' => 'Copa Universitaria',
            'categoria_edad' => 'Universitarios 18 a 24', 'edad_min' => 18, 'edad_max' => 24,
        ]);
    }

    public function test_delegate_can_search_open_championships_and_open_their_registration_page(): void
    {
        $delegado = $this->user(User::ROLE_DELEGADO);
        $organizador = $this->user(User::ROLE_ORGANIZADOR);
        $copa = $this->championship($organizador);
        $copa->update(['nombre' => 'Copa Sacaba Juvenil']);
        $this->championship($organizador)->update(['nombre' => 'Torneo del Valle']);

        $this->actingAs($delegado)
            ->get(route('campeonatos.descubrir', ['buscar' => 'Sacaba']))
            ->assertOk()
            ->assertSee('Copa Sacaba Juvenil')
            ->assertDontSee('Torneo del Valle');

        $this->actingAs($delegado)
            ->get(route('campeonatos.show', $copa))
            ->assertOk()
            ->assertSee('Participa con tu equipo')
            ->assertSee('Crear equipo e inscribirme');
    }

    public function test_public_registration_allows_operational_roles_but_not_developer(): void
    {
        $this->post('/register', ['name' => 'Organizador', 'email' => 'organizador@test.com', 'password' => 'password123', 'password_confirmation' => 'password123', 'rol' => 'organizador'])
            ->assertRedirect('/home');
        $this->assertDatabaseHas('users', ['email' => 'organizador@test.com', 'rol' => 'organizador']);

        auth()->logout();
        $this->post('/register', ['name' => 'Intruso', 'email' => 'dev@test.com', 'password' => 'password123', 'password_confirmation' => 'password123', 'rol' => 'desarrollador'])
            ->assertSessionHasErrors('rol');
        $this->assertDatabaseMissing('users', ['email' => 'dev@test.com']);
    }

    public function test_delegate_requests_enrollment_and_organizer_approves_it(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);
        $delegado = $this->user(User::ROLE_DELEGADO);
        $campeonato = $this->championship($organizador);

        $this->actingAs($delegado)
            ->get(route('equipos.create', ['campeonato' => $campeonato]))
            ->assertOk()
            ->assertSee('Te inscribirás en')
            ->assertSee($campeonato->nombre)
            ->assertDontSee('Lo elegiré más adelante');
        $this->actingAs($delegado)->post(route('equipos.store'), ['nombre' => 'Sacaba FC', 'ciudad' => 'Sacaba', 'campeonato_id' => $campeonato->id])->assertRedirect();
        $equipo = Equipo::where('nombre', 'Sacaba FC')->firstOrFail();
        $this->assertSame(1, $organizador->fresh()->unreadNotifications()->count());
        $this->assertDatabaseHas('campeonato_equipo', ['campeonato_id' => $campeonato->id, 'equipo_id' => $equipo->id, 'estado' => 'pendiente']);
        $this->actingAs($delegado)->get(route('campeonatos.show', $campeonato))->assertOk()->assertSee('Esperando respuesta');

        $this->actingAs($organizador)->post(route('campeonatos.equipos.gestionar', [$campeonato, $equipo]), ['estado' => 'aprobado'])->assertRedirect();
        $this->assertDatabaseHas('campeonato_equipo', ['campeonato_id' => $campeonato->id, 'equipo_id' => $equipo->id, 'estado' => 'aprobado']);
        $this->assertSame(1, $delegado->fresh()->unreadNotifications()->count());
        $this->actingAs($delegado)->get(route('campeonatos.show', $campeonato))
            ->assertOk()
            ->assertDontSee('Mi participación')
            ->assertSee('Equipos')
            ->assertSee('Partidos')
            ->assertDontSee('Inscribir Equipo');
    }

    public function test_team_enrollment_notifies_the_organizer_by_database_and_whatsapp(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.version' => 'v25.0',
            'services.whatsapp.phone_number_id' => '123456789',
            'services.whatsapp.access_token' => 'token-only-for-tests',
        ]);
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]]),
        ]);

        $organizador = User::factory()->create([
            'rol' => User::ROLE_ORGANIZADOR,
            'whatsapp' => '71234567',
            'whatsapp_opt_in_at' => now(),
        ]);
        $delegado = $this->user(User::ROLE_DELEGADO);
        $campeonato = $this->championship($organizador);

        $this->actingAs($delegado)->post(route('equipos.store'), [
            'nombre' => 'Equipo con aviso WhatsApp',
            'campeonato_id' => $campeonato->id,
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame(1, $organizador->fresh()->unreadNotifications()->count());
        Http::assertSent(fn ($request) => $request->url() === 'https://graph.facebook.com/v25.0/123456789/messages'
            && $request['to'] === '59171234567'
            && $request['type'] === 'template'
            && $request['template']['name'] === 'solicitud_equipo_recibida'
            && $request['template']['language']['code'] === 'es'
            && $request['template']['components'][0]['parameters'][1]['text'] === 'Equipo con aviso WhatsApp'
        );
    }

    public function test_whatsapp_notification_requires_the_users_consent(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.phone_number_id' => '123456789',
            'services.whatsapp.access_token' => 'token-only-for-tests',
        ]);
        Http::fake();

        $organizador = User::factory()->create([
            'rol' => User::ROLE_ORGANIZADOR,
            'whatsapp' => '71234567',
            'whatsapp_opt_in_at' => null,
        ]);
        $delegado = $this->user(User::ROLE_DELEGADO);
        $campeonato = $this->championship($organizador);

        $this->actingAs($delegado)->post(route('equipos.store'), [
            'nombre' => 'Equipo sin autorización externa',
            'campeonato_id' => $campeonato->id,
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame(1, $organizador->fresh()->unreadNotifications()->count());
        Http::assertNothingSent();
    }

    public function test_championship_owner_can_edit_enrolled_team_but_another_organizer_cannot(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);
        $otroOrganizador = $this->user(User::ROLE_ORGANIZADOR);
        $delegado = $this->user(User::ROLE_DELEGADO);
        $campeonato = $this->championship($organizador);
        $equipo = Equipo::create(['nombre' => 'Nombre anterior', 'delegado_id' => $delegado->id]);
        $campeonato->equipos()->attach($equipo->id, ['estado' => 'aprobado']);

        $this->actingAs($organizador)->get(route('equipos.edit', $equipo))->assertOk();
        $this->actingAs($organizador)->put(route('equipos.update', $equipo), [
            'nombre' => 'Nombre corregido',
            'ciudad' => 'Sacaba',
        ])->assertRedirect(route('equipos.show', $equipo));
        $this->assertDatabaseHas('equipos', ['id' => $equipo->id, 'nombre' => 'Nombre corregido']);

        $this->actingAs($otroOrganizador)->get(route('equipos.edit', $equipo))->assertForbidden();
    }

    public function test_championship_owner_can_view_players_from_approved_teams(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);
        $otroOrganizador = $this->user(User::ROLE_ORGANIZADOR);
        $delegado = $this->user(User::ROLE_DELEGADO);
        $campeonato = $this->championship($organizador);
        $equipo = Equipo::create(['nombre' => 'Equipo aprobado', 'delegado_id' => $delegado->id]);
        $campeonato->equipos()->attach($equipo->id, ['estado' => 'aprobado']);
        $jugador = Jugador::create([
            'nombre' => 'Jugador visible',
            'dni' => '445566',
            'equipo_id' => $equipo->id,
            'estado' => Jugador::ESTADO_PENDIENTE,
        ]);

        $this->actingAs($organizador)
            ->get(route('jugadores.show', $jugador))
            ->assertOk()
            ->assertSee('Jugador visible')
            ->assertSee('Equipo aprobado');

        $this->actingAs($organizador)
            ->get(route('equipos.show', [$equipo, 'campeonato' => $campeonato->id]))
            ->assertOk()
            ->assertSee('Jugador visible')
            ->assertSee('Aprobar')
            ->assertSee('Rechazar');

        $this->actingAs($organizador)
            ->post(route('jugadores.aprobar', $jugador))
            ->assertRedirect();
        $this->assertDatabaseHas('jugadores', [
            'id' => $jugador->id,
            'estado' => Jugador::ESTADO_APROBADO,
        ]);

        $this->actingAs($otroOrganizador)->get(route('jugadores.show', $jugador))->assertForbidden();
    }

    public function test_organizer_can_withdraw_team_without_deleting_its_data_or_history(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);
        $otroOrganizador = $this->user(User::ROLE_ORGANIZADOR);
        $delegado = $this->user(User::ROLE_DELEGADO);
        $campeonato = $this->championship($organizador);
        $equipo = Equipo::create(['nombre' => 'Equipo sancionado', 'delegado_id' => $delegado->id]);
        $rival = Equipo::create(['nombre' => 'Rival histórico', 'delegado_id' => $this->user(User::ROLE_DELEGADO)->id]);
        $campeonato->equipos()->attach($equipo->id, ['estado' => 'aprobado']);
        $campeonato->equipos()->attach($rival->id, ['estado' => 'aprobado']);
        $jugador = Jugador::create(['nombre' => 'Jugador conservado', 'dni' => '551122', 'equipo_id' => $equipo->id]);
        $partido = Partido::create([
            'campeonato_id' => $campeonato->id, 'equipo_local_id' => $equipo->id,
            'equipo_visita_id' => $rival->id, 'estado' => 'programado',
        ]);

        $this->actingAs($otroOrganizador)->post(route('campeonatos.equipos.retirar', [$campeonato, $equipo]), [
            'motivo_retiro' => 'No debe tener permiso.',
        ])->assertForbidden();

        $this->actingAs($organizador)->post(route('campeonatos.equipos.retirar', [$campeonato, $equipo]), [
            'motivo_retiro' => 'Incumplimiento reiterado del reglamento deportivo.',
        ])->assertRedirect();

        $this->assertDatabaseHas('campeonato_equipo', [
            'campeonato_id' => $campeonato->id, 'equipo_id' => $equipo->id,
            'estado' => 'retirado', 'retirado_por' => $organizador->id,
        ]);
        $this->assertDatabaseHas('equipos', ['id' => $equipo->id]);
        $this->assertDatabaseHas('jugadores', ['id' => $jugador->id]);
        $this->assertDatabaseHas('partidos', ['id' => $partido->id]);
        $this->actingAs($delegado)->get(route('campeonatos.show', $campeonato))
            ->assertOk()->assertSee('Retirado del campeonato')->assertSee('Incumplimiento reiterado');
        $this->assertSame('Equipo retirado del campeonato', $delegado->fresh()->unreadNotifications()->first()->data['titulo']);
    }

    public function test_user_can_open_own_notification_but_not_another_users_notification(): void
    {
        $delegado = $this->user(User::ROLE_DELEGADO);
        $otro = $this->user(User::ROLE_DELEGADO);
        $delegado->notify(new NotificacionPlataforma('Aviso de prueba', 'Mensaje de prueba', route('home')));
        $notificacion = $delegado->notifications()->firstOrFail();

        $this->actingAs($otro)->get(route('notificaciones.abrir', $notificacion))->assertForbidden();
        $this->actingAs($delegado)->get(route('notificaciones.index'))->assertOk()->assertSee('Aviso de prueba');
        $this->actingAs($delegado)->get(route('notificaciones.abrir', $notificacion))->assertRedirect(route('home'));
        $this->assertNotNull($notificacion->fresh()->read_at);
    }

    public function test_home_redirects_each_role_to_its_work_area(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);
        $delegado = $this->user(User::ROLE_DELEGADO);
        $arbitro = $this->user('arbitro');
        $administradorGeneral = $this->user(User::ROLE_DESARROLLADOR);

        $this->actingAs($organizador)->get(route('home'))
            ->assertRedirect(route('campeonatos.index'));
        $this->actingAs($delegado)->get(route('home'))
            ->assertRedirect(route('campeonatos.index'));
        $this->actingAs($arbitro)->get(route('home'))
            ->assertRedirect(route('publico.index'));
        $this->actingAs($administradorGeneral)->get(route('home'))
            ->assertRedirect(route('campeonatos.index'));
    }

    public function test_authenticated_navigation_does_not_repeat_home_or_show_a_global_player_list(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);

        $this->actingAs($organizador)->get(route('campeonatos.index'))
            ->assertOk()
            ->assertDontSee('<i class="bi bi-house-fill"></i> Inicio', false)
            ->assertDontSee('<i class="bi bi-people"></i> Jugadores', false);

        $this->assertFalse(app('router')->has('jugadores.index'));
        $this->assertFalse(app('router')->has('jugadores.pending'));
    }

    public function test_delegate_can_join_different_championships_but_only_once_in_each_one(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);
        $delegado = $this->user(User::ROLE_DELEGADO);
        $primero = $this->championship($organizador);
        $segundo = $this->championship($organizador);

        $this->actingAs($delegado)->post(route('equipos.store'), [
            'nombre' => 'Equipo Uno',
            'campeonato_id' => $primero->id,
        ])->assertSessionDoesntHaveErrors();

        $this->actingAs($delegado)->post(route('equipos.store'), [
            'nombre' => 'Equipo Repetido',
            'campeonato_id' => $primero->id,
        ])->assertSessionHasErrors('campeonato_id');
        $this->assertDatabaseMissing('equipos', ['nombre' => 'Equipo Repetido']);

        $this->actingAs($delegado)->post(route('equipos.store'), [
            'nombre' => 'Equipo Dos',
            'campeonato_id' => $segundo->id,
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('equipos', ['nombre' => 'Equipo Uno', 'delegado_id' => $delegado->id]);
        $this->assertDatabaseHas('equipos', ['nombre' => 'Equipo Dos', 'delegado_id' => $delegado->id]);
    }

    public function test_player_dni_cannot_repeat_in_same_championship_but_can_in_another(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);
        $delegado = $this->user(User::ROLE_DELEGADO);
        $otroDelegado = $this->user(User::ROLE_DELEGADO);
        $primero = $this->championship($organizador);
        $segundo = $this->championship($organizador);

        $equipoPropio = Equipo::create(['nombre' => 'Propio', 'delegado_id' => $delegado->id]);
        $equipoRival = Equipo::create(['nombre' => 'Rival', 'delegado_id' => $otroDelegado->id]);
        $equipoOtroTorneo = Equipo::create(['nombre' => 'Otro torneo', 'delegado_id' => $delegado->id]);
        $primero->equipos()->attach($equipoPropio->id, ['estado' => 'aprobado']);
        $primero->equipos()->attach($equipoRival->id, ['estado' => 'aprobado']);
        $segundo->equipos()->attach($equipoOtroTorneo->id, ['estado' => 'aprobado']);
        Jugador::create(['nombre' => 'Carlos', 'dni' => '778899', 'equipo_id' => $equipoRival->id]);

        $this->actingAs($delegado)->post(route('jugadores.store'), [
            'nombre' => 'Carlos repetido',
            'dni' => '778899',
            'equipo_id' => $equipoPropio->id,
            'sexo' => 'masculino',
        ])->assertSessionHasErrors('dni');

        $this->actingAs($delegado)->post(route('jugadores.store'), [
            'nombre' => 'Carlos en otro torneo',
            'dni' => '778899',
            'equipo_id' => $equipoOtroTorneo->id,
            'sexo' => 'masculino',
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame(2, Jugador::where('dni', '778899')->count());
    }

    public function test_team_cannot_exceed_the_most_restrictive_active_championship_limit(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);
        $delegado = $this->user(User::ROLE_DELEGADO);
        $campeonato = $this->championship($organizador);
        $campeonato->update([
            'modalidad' => 5,
            'jugadores_cancha' => 1,
            'min_jugadores_equipo' => 1,
            'max_jugadores_equipo' => 1,
        ]);
        $equipo = Equipo::create(['nombre' => 'Plantilla limitada', 'delegado_id' => $delegado->id]);
        $campeonato->equipos()->attach($equipo->id, ['estado' => 'aprobado']);
        Jugador::create(['nombre' => 'Primero', 'dni' => '100', 'equipo_id' => $equipo->id]);

        $this->actingAs($delegado)->post(route('jugadores.store'), [
            'nombre' => 'Segundo',
            'dni' => '101',
            'equipo_id' => $equipo->id,
            'sexo' => 'masculino',
        ])->assertSessionHasErrors('equipo_id');

        $this->assertDatabaseMissing('jugadores', ['nombre' => 'Segundo']);
    }

    public function test_player_must_match_championship_age_and_sex_rules(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);
        $delegado = $this->user(User::ROLE_DELEGADO);
        $campeonato = $this->championship($organizador);
        $campeonato->update([
            'categoria_edad' => 'sub_17',
            'edad_min' => null,
            'edad_max' => 16,
            'sexo' => 'femenino',
        ]);
        $equipo = Equipo::create(['nombre' => 'Femenino Sub-17', 'delegado_id' => $delegado->id]);
        $campeonato->equipos()->attach($equipo->id, ['estado' => 'aprobado']);

        $this->actingAs($delegado)->post(route('jugadores.store'), [
            'nombre' => 'No cumple sexo', 'dni' => '201', 'sexo' => 'masculino',
            'fecha_nacimiento' => $campeonato->fecha_inicio->copy()->subYears(15)->format('Y-m-d'),
            'equipo_id' => $equipo->id,
        ])->assertSessionHasErrors('sexo');

        $this->actingAs($delegado)->post(route('jugadores.store'), [
            'nombre' => 'No cumple edad', 'dni' => '202', 'sexo' => 'femenino',
            'fecha_nacimiento' => $campeonato->fecha_inicio->copy()->subYears(18)->format('Y-m-d'),
            'equipo_id' => $equipo->id,
        ])->assertSessionHasErrors('fecha_nacimiento');

        $this->actingAs($delegado)->post(route('jugadores.store'), [
            'nombre' => 'Jugadora válida', 'dni' => '203', 'sexo' => 'femenino',
            'fecha_nacimiento' => $campeonato->fecha_inicio->copy()->subYears(15)->format('Y-m-d'),
            'equipo_id' => $equipo->id,
        ])->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('jugadores', ['nombre' => 'Jugadora válida', 'sexo' => 'femenino']);
    }

    public function test_fixture_keeps_phase_round_and_order(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);
        $campeonato = $this->championship($organizador);
        foreach (['A', 'B'] as $name) {
            $equipo = Equipo::create(['nombre' => $name, 'delegado_id' => $this->user(User::ROLE_DELEGADO)->id]);
            $campeonato->equipos()->attach($equipo->id, ['estado' => 'aprobado']);
        }
        $this->actingAs($organizador)->post(route('campeonatos.generarFixture', $campeonato))->assertRedirect();
        $partido = Partido::firstOrFail();
        $this->assertNotNull($partido->fase_id);
        $this->assertSame(1, $partido->ronda_number);
        $this->assertSame(1, $partido->orden_en_ronda);
    }

    public function test_series_format_creates_two_series_and_interseries_matches(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);
        $campeonato = $this->championship($organizador);
        $campeonato->update([
            'formato' => 'series_interseries',
            'formato_config' => ['series_rounds' => 2, 'interseries_rounds' => 2],
        ]);

        foreach (range(1, 4) as $numero) {
            $delegado = $this->user(User::ROLE_DELEGADO);
            $equipo = Equipo::create(['nombre' => "Serie {$numero}", 'delegado_id' => $delegado->id]);
            $campeonato->equipos()->attach($equipo->id, ['estado' => 'aprobado']);
        }

        $this->actingAs($organizador)
            ->post(route('campeonatos.generarFixture', $campeonato))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $campeonato->fases()->where('tipo', 'grupo')->count());
        $this->assertSame(1, $campeonato->fases()->where('tipo', 'interserie')->count());
        $this->assertSame(8, $campeonato->partidos()->count());
    }

    public function test_knockout_home_and_away_creates_two_matches_per_opening_key(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);
        $campeonato = $this->championship($organizador);
        $campeonato->update([
            'formato' => 'eliminacion',
            'formato_config' => ['eliminations_home_away' => true],
        ]);

        foreach (range(1, 4) as $numero) {
            $delegado = $this->user(User::ROLE_DELEGADO);
            $equipo = Equipo::create(['nombre' => "Llave {$numero}", 'delegado_id' => $delegado->id]);
            $campeonato->equipos()->attach($equipo->id, ['estado' => 'aprobado']);
        }

        $this->actingAs($organizador)
            ->post(route('campeonatos.generarFixture', $campeonato))
            ->assertSessionHasNoErrors();

        $this->assertSame(4, $campeonato->partidos()->count());
        $this->assertSame([2, 2], $campeonato->partidos()
            ->selectRaw('orden_en_ronda, COUNT(*) as total')
            ->groupBy('orden_en_ronda')
            ->orderBy('orden_en_ronda')
            ->pluck('total')
            ->map(fn ($total) => (int) $total)
            ->all());
    }

    public function test_championship_form_only_shows_three_simple_formats(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);

        $this->actingAs($organizador)->get(route('campeonatos.create'))
            ->assertOk()
            ->assertDontSee('name="nivel"', false)
            ->assertSee('Liga — Todos contra todos')
            ->assertSee('Copa — Grupos y eliminatorias')
            ->assertSee('Relámpago — Eliminación directa')
            ->assertDontSee('Series — Con clásicos interseries')
            ->assertDontSee('Liga + Cuadrangular Final');
    }

    public function test_championship_form_hides_state_and_manual_format_configuration(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);

        $this->actingAs($organizador)->get(route('campeonatos.create'))
            ->assertOk()
            ->assertDontSee('name="estado"', false)
            ->assertDontSee('name="formato_config[', false)
            ->assertSee('respetando las vueltas que elegiste.');
    }

    public function test_fixture_calculates_group_configuration_from_approved_teams(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);
        $campeonato = $this->championship($organizador);
        $campeonato->update(['formato' => 'grupos_eliminacion', 'formato_config' => []]);

        foreach (range(1, 12) as $numero) {
            $delegado = $this->user(User::ROLE_DELEGADO);
            $equipo = Equipo::create(['nombre' => "Equipo automÃ¡tico {$numero}", 'delegado_id' => $delegado->id]);
            $campeonato->equipos()->attach($equipo->id, ['estado' => 'aprobado']);
        }

        $this->actingAs($organizador)
            ->post(route('campeonatos.generarFixture', $campeonato))
            ->assertSessionHasNoErrors();

        $campeonato->refresh();
        $this->assertFalse($campeonato->inscripciones_abiertas);
        $this->assertSame(4, $campeonato->formato_config['number_of_groups']);
        $this->assertSame(3, $campeonato->formato_config['teams_per_group']);
        $this->assertSame(2, $campeonato->formato_config['qualifiers_per_group']);
        $this->assertSame('cuartos', $campeonato->formato_config['elimination_start']);
        $this->assertSame(12, $campeonato->formato_config['calculated_for_teams']);
        $this->assertSame(4, $campeonato->fases()->where('tipo', 'grupo')->count());

        foreach ($campeonato->fases()->where('tipo', 'grupo')->get() as $fase) {
            $equiposGrupo = collect($fase->metadata['teams'])->map(fn ($id) => (int) $id);
            $this->assertTrue($fase->partidos->every(fn ($partido) => $equiposGrupo->contains((int) $partido->equipo_local_id)
                && $equiposGrupo->contains((int) $partido->equipo_visita_id)
            ));
        }
    }

    public function test_cup_standings_are_separated_by_group(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);
        $campeonato = $this->championship($organizador);
        $campeonato->update(['formato' => 'grupos_eliminacion', 'formato_config' => []]);

        foreach (range(1, 12) as $numero) {
            $delegado = $this->user(User::ROLE_DELEGADO);
            $equipo = Equipo::create(['nombre' => "Equipo de copa {$numero}", 'delegado_id' => $delegado->id]);
            $campeonato->equipos()->attach($equipo->id, ['estado' => 'aprobado']);
        }

        $this->actingAs($organizador)
            ->post(route('campeonatos.generarFixture', $campeonato))
            ->assertSessionHasNoErrors();

        $this->actingAs($organizador)
            ->get(route('campeonatos.show', $campeonato))
            ->assertOk()
            ->assertSee('Tabla de posiciones')
            ->assertSee('Grupo A')
            ->assertSee('Grupo D')
            ->assertSee('Clasifican por grupo')
            ->assertSee('2 (8 en total)')
            ->assertSee('Llaves')
            ->assertSee('Goleadores')
            ->assertSee('Tarjetas')
            ->assertDontSee('data-bs-target="#jug"', false)
            ->assertSee('Agenda de partidos')
            ->assertSee('Por programar')
            ->assertSee('agenda-match', false)
            ->assertDontSee('Programar Partido')
            ->assertViewHas('tablasGrupos', function ($grupos) {
                return $grupos->count() === 4
                    && $grupos->every(fn ($grupo) => $grupo['cantidad_equipos'] === 3 && count($grupo['tabla']) === 3);
            });
    }

    public function test_organizer_can_choose_four_qualifiers_and_generate_round_of_sixteen(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);
        $campeonato = $this->championship($organizador);
        $campeonato->update(['formato' => 'grupos_eliminacion', 'formato_config' => ['eliminations_home_away' => true, 'third_place_match' => true]]);

        foreach (range(1, 20) as $numero) {
            $equipo = Equipo::create([
                'nombre' => "Equipo para octavos {$numero}",
                'delegado_id' => $this->user(User::ROLE_DELEGADO)->id,
            ]);
            $campeonato->equipos()->attach($equipo->id, ['estado' => 'aprobado']);
        }

        $this->actingAs($organizador)
            ->post(route('campeonatos.generarFixture', $campeonato))
            ->assertSessionHasNoErrors();

        $this->actingAs($organizador)
            ->patch(route('campeonatos.actualizarClasificados', $campeonato), [
                'clasificados_por_grupo' => 4,
                'ida_vuelta_eliminatorias' => 0,
                'partido_tercer_lugar' => 0,
            ])
            ->assertSessionHasNoErrors();

        $campeonato->refresh();
        $this->assertSame(4, $campeonato->formato_config['qualifiers_per_group']);
        $this->assertSame('octavos', $campeonato->formato_config['elimination_start']);
        $this->assertTrue($campeonato->formato_config['eliminations_home_away']);
        $this->assertTrue($campeonato->formato_config['third_place_match']);

        $this->actingAs($organizador)
            ->patch(route('campeonatos.actualizarClasificados', $campeonato), [
                'clasificados_por_grupo' => 3,
            ])
            ->assertSessionHasErrors('clasificados_por_grupo');

        $campeonato->partidos()->update([
            'estado' => 'jugado',
            'goles_local' => 1,
            'goles_visita' => 0,
        ]);

        $this->actingAs($organizador)
            ->patch(route('campeonatos.actualizarClasificados', $campeonato), [
                'clasificados_por_grupo' => 2,
            ])
            ->assertSessionHasErrors('error');

        $this->assertSame(4, $campeonato->fresh()->formato_config['qualifiers_per_group']);

        $this->actingAs($organizador)
            ->post(route('campeonatos.generarEliminatoria', $campeonato))
            ->assertSessionHasNoErrors();

        $faseEliminatoria = $campeonato->fases()->where('tipo', 'eliminacion')->firstOrFail();
        $this->assertSame(16, $faseEliminatoria->partidos()->count());
        $this->assertTrue($faseEliminatoria->partidos()->get()->groupBy('orden_en_ronda')->every(
            fn ($partidosLlave) => $partidosLlave->count() === 2
        ));
        $this->assertFalse($faseEliminatoria->partidos()->get()->contains(
            fn (Partido $partido) => $partido->equipo_local_id === null || $partido->equipo_visita_id === null
        ));
    }

    public function test_group_fixture_cannot_be_changed_to_mix_different_groups(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);
        $campeonato = $this->championship($organizador);
        $campeonato->update(['formato' => 'grupos_eliminacion', 'formato_config' => []]);

        foreach (range(1, 12) as $numero) {
            $equipo = Equipo::create([
                'nombre' => "Equipo protegido {$numero}",
                'delegado_id' => $this->user(User::ROLE_DELEGADO)->id,
            ]);
            $campeonato->equipos()->attach($equipo->id, ['estado' => 'aprobado']);
        }

        $this->actingAs($organizador)->post(route('campeonatos.generarFixture', $campeonato));

        $fase = $campeonato->fases()->where('tipo', 'grupo')->orderBy('orden')->firstOrFail();
        $partido = $fase->partidos()->firstOrFail();
        $equipoOtroGrupo = $campeonato->equipos()
            ->whereNotIn('equipos.id', $fase->metadata['teams'])
            ->firstOrFail();

        $this->actingAs($organizador)
            ->put(route('partidos.update', $partido), [
                'campeonato_id' => $campeonato->id,
                'equipo_local_id' => $partido->equipo_local_id,
                'equipo_visita_id' => $equipoOtroGrupo->id,
                'fecha' => now()->addMonth()->format('Y-m-d'),
                'hora' => '15:00',
                'lugar' => 'Cancha principal',
            ])
            ->assertSessionHasErrors('equipo_visita_id');

        $this->assertSame($partido->equipo_visita_id, $partido->fresh()->equipo_visita_id);
    }

    public function test_fixture_waits_until_all_team_requests_are_resolved(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);
        $campeonato = $this->championship($organizador);

        foreach (['Aprobado A', 'Aprobado B'] as $nombre) {
            $equipo = Equipo::create(['nombre' => $nombre, 'delegado_id' => $this->user(User::ROLE_DELEGADO)->id]);
            $campeonato->equipos()->attach($equipo->id, ['estado' => 'aprobado']);
        }
        $pendiente = Equipo::create(['nombre' => 'Solicitud pendiente', 'delegado_id' => $this->user(User::ROLE_DELEGADO)->id]);
        $campeonato->equipos()->attach($pendiente->id, ['estado' => 'pendiente']);

        $this->actingAs($organizador)
            ->post(route('campeonatos.generarFixture', $campeonato))
            ->assertSessionHasErrors('error');

        $this->assertSame(0, $campeonato->fases()->count());
        $this->assertTrue($campeonato->fresh()->inscripciones_abiertas);
    }

    public function test_new_championship_cannot_use_the_removed_series_format(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);

        $this->actingAs($organizador)->post(route('campeonatos.store'), [
            'nombre' => 'Copa sin series',
            'fecha_inicio' => now()->addMonth()->format('Y-m-d'),
            'estado' => 'pendiente',
            'formato' => 'series_interseries',
            'modalidad' => 7,
            'jugadores_cancha' => 7,
            'min_jugadores_equipo' => 10,
            'max_jugadores_equipo' => 15,
            'categoria_edad' => 'libre',
            'sexo' => 'mixto',
        ])->assertSessionHasErrors('formato');

        $this->assertDatabaseMissing('campeonatos', ['nombre' => 'Copa sin series']);
    }

    public function test_finished_match_result_redirect_does_not_loop_to_same_page(): void
    {
        $owner = $this->user(User::ROLE_ORGANIZADOR);
        $cup = $this->championship($owner);
        $delegate = $this->user(User::ROLE_DELEGADO);
        $local = Equipo::create(['nombre' => 'Local terminado', 'delegado_id' => $delegate->id]);
        $away = Equipo::create(['nombre' => 'Visita terminada', 'delegado_id' => $delegate->id]);
        $match = Partido::create(['campeonato_id' => $cup->id, 'equipo_local_id' => $local->id,
            'equipo_visita_id' => $away->id, 'fecha' => now(), 'estado' => 'jugado']);
        $resultUrl = route('partidos.resultado', $match);
        $destination = route('partidos.nominas.index', $match);
        $this->actingAs($owner)->from($resultUrl)->get($resultUrl)
            ->assertRedirect($destination)->assertSessionHasErrors('error');
        $this->get($destination)->assertOk();
        $this->from($resultUrl)->put(route('partidos.guardarResultado', $match))
            ->assertRedirect($destination)->assertSessionHasErrors('error');
        $this->get($destination)->assertOk();
    }

    public function test_retired_referees_cannot_enter_results(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);
        $asignado = $this->user('arbitro');
        $otro = $this->user('arbitro');
        $campeonato = $this->championship($organizador);
        $local = Equipo::create(['nombre' => 'Local', 'delegado_id' => $this->user(User::ROLE_DELEGADO)->id]);
        $visita = Equipo::create(['nombre' => 'Visita', 'delegado_id' => $this->user(User::ROLE_DELEGADO)->id]);
        $partido = Partido::forceCreate(['campeonato_id' => $campeonato->id, 'equipo_local_id' => $local->id, 'equipo_visita_id' => $visita->id, 'estado' => 'programado']);
        $this->actingAs($otro)->get(route('partidos.resultado', $partido))->assertForbidden();
        $this->actingAs($asignado)->get(route('partidos.resultado', $partido))->assertForbidden();
        $this->actingAs($organizador)->get(route('partidos.resultado', $partido))->assertOk();
    }

    public function test_roster_uses_championship_minimum_instead_of_full_side_size(): void
    {
        $owner = $this->user(User::ROLE_ORGANIZADOR);
        $delegate = $this->user(User::ROLE_DELEGADO);
        $cup = $this->championship($owner);
        $cup->update(['jugadores_cancha' => 11, 'min_jugadores_partido' => 7]);
        $local = Equipo::create(['nombre' => 'Local minimo', 'delegado_id' => $delegate->id]);
        $away = Equipo::create(['nombre' => 'Visita minimo', 'delegado_id' => $delegate->id]);
        $match = Partido::create(['campeonato_id' => $cup->id, 'equipo_local_id' => $local->id,
            'equipo_visita_id' => $away->id, 'fecha' => now()->addDay(), 'estado' => 'programado']);
        $numbers = [];
        foreach (range(1, 7) as $number) {
            $player = Jugador::create(['nombre' => 'Jugador '.$number, 'equipo_id' => $local->id, 'estado' => Jugador::ESTADO_APROBADO]);
            $numbers[$player->id] = $number;
        }
        $this->actingAs($owner)->put(route('partidos.nominas.guardar', [$match, $local]),
            ['dorsales' => array_slice($numbers, 0, 6, true)])->assertSessionHasErrors('dorsales');
        $this->put(route('partidos.nominas.guardar', [$match, $local]), ['dorsales' => $numbers])
            ->assertSessionHasNoErrors();
        $roster = $match->nominas()->firstOrFail();
        $this->assertSame('aprobada', $roster->estado);
        $this->assertSame(7, $roster->jugadores()->count());
        $roster->update(['estado' => 'pendiente']);
        $this->post(route('partidos.nominas.gestionar', [$match, $roster]), ['estado' => 'aprobada'])
            ->assertSessionHasNoErrors();
        $cup->update(['min_jugadores_partido' => 8]);
        $this->post(route('partidos.nominas.gestionar', [$match, $roster]), ['estado' => 'aprobada'])
            ->assertSessionHasErrors('error');
        $cup->update(['min_jugadores_partido' => null]);
        $this->assertSame(11, $cup->fresh()->minimo_para_jugar);
    }

    public function test_match_rosters_use_per_match_numbers_and_block_a_red_card_suspension(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);
        $delegadoLocal = $this->user(User::ROLE_DELEGADO);
        $delegadoVisita = $this->user(User::ROLE_DELEGADO);
        $campeonato = $this->championship($organizador);
        $campeonato->update(['jugadores_cancha' => 1, 'min_jugadores_equipo' => 1, 'max_jugadores_equipo' => 5]);
        $local = Equipo::create(['nombre' => 'Local nómina', 'delegado_id' => $delegadoLocal->id]);
        $visita = Equipo::create(['nombre' => 'Visita nómina', 'delegado_id' => $delegadoVisita->id]);
        $jugadorLocal = Jugador::create(['nombre' => 'Jugador sancionado', 'equipo_id' => $local->id, 'estado' => Jugador::ESTADO_APROBADO]);
        $otroLocal = Jugador::create(['nombre' => 'Jugador adicional', 'equipo_id' => $local->id, 'estado' => Jugador::ESTADO_APROBADO]);
        $jugadorVisita = Jugador::create(['nombre' => 'Jugador visitante', 'dni' => '7654321', 'equipo_id' => $visita->id, 'estado' => Jugador::ESTADO_APROBADO]);
        $partido = Partido::create([
            'campeonato_id' => $campeonato->id,
            'equipo_local_id' => $local->id,
            'equipo_visita_id' => $visita->id,
            'fecha' => now()->addDays(2),
            'estado' => 'programado',
        ]);

        $this->actingAs($delegadoLocal)->put(route('partidos.nominas.guardar', [$partido, $local]), [
            'dorsales' => [$jugadorLocal->id => 10, $otroLocal->id => 10],
        ])->assertSessionHasErrors('dorsales');

        $this->actingAs($delegadoLocal)->put(route('partidos.nominas.guardar', [$partido, $local]), [
            'dorsales' => [$jugadorLocal->id => 10],
        ])->assertSessionHasNoErrors();
        $this->actingAs($organizador)->get(route('partidos.nominas.editar', [$partido, $visita]))
            ->assertOk()->assertSee('Jugador visitante')->assertSee('CI: 7654321');
        $this->actingAs($organizador)->put(route('partidos.nominas.guardar', [$partido, $visita]), [
            'dorsales' => [$jugadorVisita->id => 7],
        ])->assertSessionHasNoErrors()->assertRedirect(route('partidos.nominas.index', $partido));
        $this->assertDatabaseHas('nominas_partido', [
            'partido_id' => $partido->id,
            'equipo_id' => $visita->id,
            'estado' => 'aprobada',
            'aprobado_por' => $organizador->id,
        ]);

        foreach ($partido->nominas()->where('estado', 'pendiente')->get() as $nomina) {
            $this->actingAs($organizador)->post(route('partidos.nominas.gestionar', [$partido, $nomina]), [
                'estado' => 'aprobada',
            ])->assertSessionHasNoErrors();
        }

        $this->actingAs($organizador)->get(route('campeonatos.show', $campeonato))
            ->assertOk()->assertDontSee(route('partidos.resultado', $partido));
        $this->actingAs($organizador)->get(route('partidos.nominas.index', $partido))
            ->assertOk()->assertSee('Planilla del partido')->assertSee('Revisar y finalizar')
            ->assertSee(route('partidos.guardarResultado', $partido))
            ->assertSee('data-sheet-event', false);
        $this->actingAs($delegadoLocal)->get(route('partidos.nominas.index', $partido))
            ->assertOk()->assertDontSee('data-sheet-event', false)->assertDontSee('Revisar y finalizar');
        $this->actingAs($organizador)->get(route('partidos.resultado', $partido))
            ->assertOk()
            ->assertSee('Local nómina · Local')
            ->assertSee('Visita nómina · Visita')
            ->assertSee('Jugador sancionado')
            ->assertSee('Jugador visitante')
            ->assertDontSee('name="minuto"', false);

        $this->actingAs($organizador)->post(route('incidencias.store'), [
            'partido_id' => $partido->id,
            'jugador_id' => $jugadorLocal->id,
            'tipo' => Incidencia::TIPO_ROJA,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('incidencias', [
            'partido_id' => $partido->id,
            'jugador_id' => $jugadorLocal->id,
            'tipo' => Incidencia::TIPO_ROJA,
            'dorsal' => 10,
            'minuto' => null,
        ]);

        $this->actingAs($organizador)->post(route('incidencias.store'), [
            'partido_id' => $partido->id,
            'jugador_id' => $jugadorLocal->id,
            'tipo' => Incidencia::TIPO_GOL,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('partidos', [
            'id' => $partido->id,
            'goles_local' => 1,
            'goles_visita' => 0,
        ]);

        $this->actingAs($organizador)->post(route('incidencias.store'), [
            'partido_id' => $partido->id,
            'jugador_id' => $jugadorVisita->id,
            'tipo' => Incidencia::TIPO_GOL,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('partidos', [
            'id' => $partido->id,
            'goles_local' => 1,
            'goles_visita' => 1,
        ]);

        $golVisitante = Incidencia::where('partido_id', $partido->id)
            ->where('jugador_id', $jugadorVisita->id)
            ->where('tipo', Incidencia::TIPO_GOL)
            ->firstOrFail();
        $this->actingAs($organizador)->delete(route('incidencias.destroy', $golVisitante))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('partidos', [
            'id' => $partido->id,
            'goles_local' => 1,
            'goles_visita' => 0,
        ]);

        $this->actingAs($organizador)->post(route('incidencias.store'), [
            'partido_id' => $partido->id,
            'jugador_id' => $jugadorVisita->id,
            'tipo' => Incidencia::TIPO_AMARILLA,
        ])->assertSessionHasNoErrors();
        $this->actingAs($organizador)->post(route('incidencias.store'), [
            'partido_id' => $partido->id,
            'jugador_id' => $jugadorVisita->id,
            'tipo' => Incidencia::TIPO_AMARILLA,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('incidencias', [
            'partido_id' => $partido->id,
            'jugador_id' => $jugadorVisita->id,
            'tipo' => Incidencia::TIPO_DOBLE_AMARILLA,
        ]);

        $primeraAmarilla = Incidencia::where('partido_id', $partido->id)
            ->where('jugador_id', $jugadorVisita->id)
            ->where('tipo', Incidencia::TIPO_AMARILLA)
            ->firstOrFail();
        $this->actingAs($organizador)->delete(route('incidencias.destroy', $primeraAmarilla))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('incidencias', [
            'partido_id' => $partido->id,
            'jugador_id' => $jugadorVisita->id,
            'tipo' => Incidencia::TIPO_DOBLE_AMARILLA,
        ]);
        $this->assertSame(1, Incidencia::where('partido_id', $partido->id)
            ->where('jugador_id', $jugadorVisita->id)
            ->where('tipo', Incidencia::TIPO_AMARILLA)
            ->count());

        $this->actingAs($organizador)->post(route('incidencias.store'), [
            'partido_id' => $partido->id,
            'jugador_id' => $jugadorVisita->id,
            'tipo' => Incidencia::TIPO_AMARILLA,
        ])->assertSessionHasNoErrors();
        $this->actingAs($organizador)->post(route('incidencias.store'), [
            'partido_id' => $partido->id,
            'jugador_id' => $jugadorVisita->id,
            'tipo' => Incidencia::TIPO_AMARILLA,
        ])->assertSessionHasErrors('jugador_id');
        $this->actingAs($organizador)->get(route('partidos.resultado', $partido))
            ->assertOk()->assertSee('Doble amarilla');

        $this->actingAs($organizador)->put(route('partidos.guardarResultado', $partido))
            ->assertSessionHasNoErrors()->assertRedirect(route('partidos.nominas.index', $partido));
        $this->assertDatabaseHas('partidos', [
            'id' => $partido->id,
            'estado' => 'jugado',
            'goles_local' => 1,
            'goles_visita' => 0,
        ]);

        $siguiente = Partido::create([
            'campeonato_id' => $campeonato->id,
            'equipo_local_id' => $local->id,
            'equipo_visita_id' => $visita->id,
            'fecha' => now()->addDays(3),
            'estado' => 'programado',
        ]);
        $this->actingAs($delegadoLocal)
            ->get(route('partidos.nominas.editar', [$siguiente, $local]))
            ->assertOk()->assertSee('Suspendido');
        $this->actingAs($delegadoVisita)
            ->get(route('partidos.nominas.editar', [$siguiente, $visita]))
            ->assertOk()->assertSee('Suspendido');
        $this->actingAs($delegadoLocal)->put(route('partidos.nominas.guardar', [$siguiente, $local]), [
            'dorsales' => [$jugadorLocal->id => 22],
        ])->assertSessionHasErrors('dorsales');
    }

    public function test_retiring_referee_accounts_preserves_history_and_can_be_reversed(): void
    {
        $cleanup = require database_path('migrations/2026_09_27_010000_remove_retired_referee_schema.php');
        $cleanup->down();
        $migration = require database_path('migrations/2026_09_24_000000_retire_referee_role.php');
        $migration->down();
        $arbitro = $this->user('arbitro');
        $organizador = $this->user(User::ROLE_ORGANIZADOR);
        $campeonato = $this->championship($organizador);
        $local = Equipo::create(['nombre' => 'Local historico', 'delegado_id' => $this->user(User::ROLE_DELEGADO)->id]);
        $visita = Equipo::create(['nombre' => 'Visita historica', 'delegado_id' => $this->user(User::ROLE_DELEGADO)->id]);
        $partido = Partido::forceCreate([
            'campeonato_id' => $campeonato->id,
            'equipo_local_id' => $local->id,
            'equipo_visita_id' => $visita->id,
            'arbitro_id' => $arbitro->id,
            'estado' => 'jugado',
        ]);
        $migration->up();
        $this->assertSame(User::ROLE_PUBLICO, $arbitro->fresh()->rol);
        $this->assertSame(User::ROLE_ORGANIZADOR, $organizador->fresh()->rol);
        $this->assertSame($arbitro->id, (int) $partido->fresh()->arbitro_id);
        $this->actingAs($arbitro->fresh())->get(route('home'))->assertRedirect(route('publico.index'));
        $this->get(route('partidos.nominas.index', $partido))->assertForbidden();
        $migration->down();
        $this->assertSame('arbitro', $arbitro->fresh()->rol);
        $migration->up();

        $partido->update(['goles_local' => 2, 'goles_visita' => 1]);
        \Illuminate\Support\Facades\DB::table('arbitro_campeonato')->insert([
            'campeonato_id' => $campeonato->id,
            'arbitro_id' => $arbitro->id,
            'estado' => 'aprobado',
        ]);
        $usersBefore = User::count();
        $matchesBefore = Partido::count();
        $cleanup->up();
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('arbitro_campeonato'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('retired_referee_accounts'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('partidos', 'arbitro_id'));
        $this->assertSame($usersBefore, User::count());
        $this->assertSame($matchesBefore, Partido::count());
        $this->assertSame(User::ROLE_PUBLICO, $arbitro->fresh()->rol);
        $this->assertDatabaseHas('partidos', [
            'id' => $partido->id, 'estado' => 'jugado', 'goles_local' => 2, 'goles_visita' => 1,
        ]);
    }

    public function test_referee_role_is_no_longer_available_in_registration_or_routes(): void
    {
        $this->get(route('register'))->assertOk()->assertDontSee('value="arbitro"', false);
        $this->post(route('register'), [
            'name' => 'Rol retirado',
            'email' => 'retirado@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'rol' => 'arbitro',
        ])->assertSessionHasErrors('rol');
        $this->assertDatabaseMissing('users', ['email' => 'retirado@example.test']);

        $organizador = $this->user(User::ROLE_ORGANIZADOR);
        $campeonato = $this->championship($organizador);
        $this->actingAs($organizador)->get(route('campeonatos.show', $campeonato))
            ->assertOk()->assertDontSee('Árbitros')->assertDontSee('Mis arbitrajes');
        $this->get('/arbitro/mis-arbitrajes')->assertNotFound();
        $this->get('/campeonatos/'.$campeonato->id.'/arbitros')->assertNotFound();
        $this->get('/api/arbitros-campeonato/'.$campeonato->id)->assertNotFound();
        $this->post('/arbitro/campeonatos/'.$campeonato->id.'/postular')->assertNotFound();
    }

    public function test_organizer_programs_matches_without_referee_assignment(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);
        $campeonato = $this->championship($organizador);
        $local = Equipo::create(['nombre' => 'Local sin arbitro', 'delegado_id' => $this->user(User::ROLE_DELEGADO)->id]);
        $visita = Equipo::create(['nombre' => 'Visita sin arbitro', 'delegado_id' => $this->user(User::ROLE_DELEGADO)->id]);
        $campeonato->equipos()->attach([$local->id => ['estado' => 'aprobado'], $visita->id => ['estado' => 'aprobado']]);
        $datos = [
            'campeonato_id' => $campeonato->id,
            'equipo_local_id' => $local->id,
            'equipo_visita_id' => $visita->id,
            'fecha' => now()->addDay()->toDateString(),
            'hora' => '15:00',
            'lugar' => 'Cancha Municipal',
        ];
        $this->actingAs($organizador)->get(route('partidos.create', ['campeonato' => $campeonato->id]))
            ->assertOk()->assertSee('name="hora"', false)->assertDontSee('name="arbitro_id"', false);
        // Un cliente antiguo no puede reintroducir la asignacion retirada.
        $this->post(route('partidos.store'), $datos + ['arbitro_id' => $organizador->id])
            ->assertSessionHasNoErrors();
        $partido = Partido::where('campeonato_id', $campeonato->id)->firstOrFail();
        $this->assertArrayNotHasKey('arbitro_id', $partido->getAttributes());
        $this->get(route('partidos.edit', $partido))
            ->assertOk()->assertSee('name="hora"', false)->assertDontSee('name="arbitro_id"', false);
        $this->get(route('partidos.nominas.index', $partido))->assertOk();
        $this->get(route('partidos.resultado', $partido))->assertOk();
        $this->actingAs($this->user(User::ROLE_ORGANIZADOR))
            ->get(route('partidos.resultado', $partido))->assertForbidden();
    }

    public function test_guest_can_search_the_public_championship_feed_without_an_account(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);
        $visible = $this->championship($organizador);
        $visible->update(['nombre' => 'Liga Pública Sacaba', 'estado' => 'en_curso', 'modalidad' => 7, 'sexo' => 'mixto']);
        $this->championship($organizador)->update(['nombre' => 'Copa Diferente', 'estado' => 'finalizado', 'modalidad' => 11]);

        $this->get(route('publico.index', ['buscar' => 'Pública']))
            ->assertOk()->assertSee('Liga Pública Sacaba')->assertDontSee('Copa Diferente')
            ->assertSee('Buscar campeonato por nombre')
            ->assertDontSee('Más filtros');

        $this->get(route('campeonatos.show', $visible))->assertRedirect(route('login'));
    }

    public function test_public_championship_detail_shows_sports_information_but_hides_private_data(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);
        $delegado = $this->user(User::ROLE_DELEGADO);
        $campeonato = $this->championship($organizador);
        $equipo = Equipo::create(['nombre' => 'Equipo Público', 'delegado_id' => $delegado->id]);
        $pendiente = Equipo::create(['nombre' => 'Equipo Secreto', 'delegado_id' => $delegado->id]);
        $campeonato->equipos()->attach($equipo->id, ['estado' => 'aprobado']);
        $campeonato->equipos()->attach($pendiente->id, ['estado' => 'pendiente']);
        $jugador = Jugador::create(['nombre' => 'Juan Visible', 'dni' => 'DNI-PRIVADO-123', 'equipo_id' => $equipo->id, 'estado' => Jugador::ESTADO_APROBADO, 'numero' => 10]);
        Jugador::create(['nombre' => 'Jugador Pendiente', 'dni' => 'OTRO-DNI', 'equipo_id' => $equipo->id, 'estado' => Jugador::ESTADO_PENDIENTE]);
        $partido = Partido::create(['campeonato_id' => $campeonato->id, 'equipo_local_id' => $equipo->id, 'equipo_visita_id' => $equipo->id, 'estado' => 'jugado', 'goles_local' => 2, 'goles_visita' => 1]);
        Incidencia::create(['partido_id' => $partido->id, 'jugador_id' => $jugador->id, 'tipo' => Incidencia::TIPO_AMARILLA, 'minuto' => 35]);
        Incidencia::create(['partido_id' => $partido->id, 'jugador_id' => $jugador->id, 'tipo' => Incidencia::TIPO_GOL, 'minuto' => 24]);

        $this->get(route('publico.show', $campeonato))->assertOk()
            ->assertSee('Equipo Público')->assertSee('Juan Visible')->assertSee('Tabla de posiciones')
            ->assertSee('href="#plantillas-equipo-'.$equipo->id.'"', false)
            ->assertSee('roster-player-photo', false)->assertSee('persona-anonima.svg', false)
            ->assertSee('Delegado: '.$delegado->name)
            ->assertSee('Historial de goleadores')->assertSee('Ver historial de goles')->assertDontSee("24'")
            ->assertSee('Tarjetas amarillas y rojas')->assertSee('yellow-card', false)
            ->assertDontSee('Equipo Secreto')->assertDontSee('Jugador Pendiente')->assertDontSee('DNI-PRIVADO-123')
            ->assertDontSee($organizador->email);
    }

    public function test_public_elimination_championship_shows_a_clear_bracket_with_future_rounds(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);
        $delegado = $this->user(User::ROLE_DELEGADO);
        $campeonato = $this->championship($organizador);
        $campeonato->update([
            'nombre' => 'Copa Eliminatoria',
            'formato' => 'eliminacion',
            'formato_config' => ['eliminations_home_away' => 1, 'third_place_match' => 1],
        ]);
        $fase = Fase::create([
            'campeonato_id' => $campeonato->id,
            'tipo' => 'eliminacion',
            'orden' => 1,
            'metadata' => ['bracket_size' => 4],
            'estado' => 'en_curso',
        ]);
        $equipos = collect(['Aurora', 'Wilstermann', 'Palmaflor', 'Universitario'])
            ->map(fn (string $nombre) => Equipo::create(['nombre' => $nombre, 'delegado_id' => $delegado->id]));
        $campeonato->equipos()->attach($equipos->pluck('id'), ['estado' => 'aprobado']);

        Partido::create([
            'campeonato_id' => $campeonato->id,
            'fase_id' => $fase->id,
            'ronda_number' => 1,
            'orden_en_ronda' => 1,
            'equipo_local_id' => $equipos[0]->id,
            'equipo_visita_id' => $equipos[1]->id,
            'fecha' => now()->addDay(),
            'hora' => '15:00',
            'estado' => 'jugado',
            'goles_local' => 2,
            'goles_visita' => 0,
        ]);
        Partido::create([
            'campeonato_id' => $campeonato->id,
            'fase_id' => $fase->id,
            'ronda_number' => 1,
            'orden_en_ronda' => 2,
            'equipo_local_id' => $equipos[2]->id,
            'equipo_visita_id' => $equipos[3]->id,
            'fecha' => now()->addDay(),
            'hora' => '17:00',
            'estado' => 'programado',
        ]);
        Partido::create([
            'campeonato_id' => $campeonato->id,
            'fase_id' => $fase->id,
            'ronda_number' => 1,
            'orden_en_ronda' => 1,
            'equipo_local_id' => $equipos[1]->id,
            'equipo_visita_id' => $equipos[0]->id,
            'fecha' => now()->addDays(8),
            'hora' => '15:00',
            'estado' => 'jugado',
            'goles_local' => 1,
            'goles_visita' => 1,
        ]);
        Partido::create([
            'campeonato_id' => $campeonato->id,
            'fase_id' => $fase->id,
            'ronda_number' => 2,
            'orden_en_ronda' => 1,
            'equipo_local_id' => $equipos[0]->id,
            'equipo_visita_id' => $equipos[2]->id,
            'fecha' => now()->addDays(15),
            'hora' => '16:00',
            'estado' => 'jugado',
            'goles_local' => 3,
            'goles_visita' => 2,
        ]);
        Partido::create([
            'campeonato_id' => $campeonato->id,
            'fase_id' => $fase->id,
            'ronda_number' => 2,
            'orden_en_ronda' => 2,
            'equipo_local_id' => $equipos[1]->id,
            'equipo_visita_id' => $equipos[3]->id,
            'fecha' => now()->addDays(14),
            'hora' => '14:00',
            'estado' => 'jugado',
            'goles_local' => 2,
            'goles_visita' => 1,
        ]);

        $this->get(route('publico.show', $campeonato))
            ->assertOk()
            ->assertSee('href="#llaves"', false)
            ->assertSee('Llaves del campeonato')
            ->assertSee('Semifinales')
            ->assertSee('Gran final')
            ->assertSee('Aurora')
            ->assertSee('Resultado global')
            ->assertSee('Partido único')
            ->assertSee('CAMINO A LA COPA')
            ->assertSee('Campeón')
            ->assertSee('Tercer lugar')
            ->assertSee('Partido de bronce')
            ->assertSee('perdedores de las semifinales');
    }

    public function test_public_role_is_not_available_for_new_registrations(): void
    {
        $this->post('/register', [
            'name' => 'Solo visitante', 'email' => 'visitante@test.com',
            'password' => 'password123', 'password_confirmation' => 'password123', 'rol' => 'publico',
        ])->assertSessionHasErrors('rol');
        $this->assertDatabaseMissing('users', ['email' => 'visitante@test.com']);
        $this->get(route('register'))->assertOk()->assertDontSee('Usuario público');
    }

    public function test_organizer_can_add_an_optional_championship_cover_image(): void
    {
        Storage::fake('public');

        $organizador = $this->user(User::ROLE_ORGANIZADOR);
        $pngMinimo = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL+WQAAAABJRU5ErkJggg==');
        $portada = UploadedFile::fake()->createWithContent('copa-sacaba.png', $pngMinimo);

        $this->actingAs($organizador)->post(route('campeonatos.store'), [
            'nombre' => 'Copa con portada', 'descripcion' => 'Campeonato con imagen pública.',
            'portada' => $portada, 'fecha_inicio' => now()->addMonth()->format('Y-m-d'),
            'estado' => 'pendiente', 'formato' => 'liga', 'modalidad' => 7,
            'jugadores_cancha' => 7, 'min_jugadores_equipo' => 10, 'max_jugadores_equipo' => 15,
            'categoria_edad' => 'libre', 'sexo' => 'mixto',
        ])->assertSessionDoesntHaveErrors();

        $campeonato = Campeonato::where('nombre', 'Copa con portada')->firstOrFail();
        $this->assertStringStartsWith('campeonatos/', $campeonato->portada);
        Storage::disk('public')->assertExists($campeonato->portada);
        $campeonato->forceFill(['aprobacion_estado' => 'aprobado'])->save();
        $this->get(route('publico.index'))->assertOk()->assertSee($campeonato->portada_url, false);
        $this->get(route('publico.show', $campeonato))->assertOk()->assertSee($campeonato->portada_url, false);
    }

    public function test_championship_cover_rejects_unsupported_files(): void
    {
        Storage::fake('public');
        $organizador = $this->user(User::ROLE_ORGANIZADOR);
        $this->actingAs($organizador)->post(route('campeonatos.store'), [
            'nombre' => 'Archivo inválido', 'portada' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'),
            'fecha_inicio' => now()->addMonth()->format('Y-m-d'), 'estado' => 'pendiente',
            'formato' => 'liga', 'modalidad' => 7, 'jugadores_cancha' => 7,
            'min_jugadores_equipo' => 10, 'max_jugadores_equipo' => 15,
            'categoria_edad' => 'libre', 'sexo' => 'mixto',
        ])->assertSessionHasErrors('portada');
    }

    public function test_authenticated_user_can_open_and_update_their_profile(): void
    {
        $usuario = User::factory()->create([
            'rol' => User::ROLE_DELEGADO,
            'password' => Hash::make('ClaveActual123'),
        ]);

        $this->actingAs($usuario)->get(route('perfil.edit'))
            ->assertOk()
            ->assertSee('Mi perfil')
            ->assertSee($usuario->email)
            ->assertDontSee('Probar WhatsApp')
            ->assertDontSee('Enviar mensaje de prueba');

        $this->actingAs($usuario)->put(route('perfil.update'), [
            'name' => 'Delegado actualizado',
            'email' => 'delegado.actualizado@example.com',
            'whatsapp' => '76543210',
            'whatsapp_notifications' => '1',
            'current_password' => 'ClaveActual123',
            'password' => 'NuevaClave456',
            'password_confirmation' => 'NuevaClave456',
        ])->assertRedirect(route('perfil.edit'));

        $usuario->refresh();
        $this->assertSame('Delegado actualizado', $usuario->name);
        $this->assertSame('delegado.actualizado@example.com', $usuario->email);
        $this->assertSame('+59176543210', $usuario->whatsapp);
        $this->assertNotNull($usuario->whatsapp_opt_in_at);
        $this->assertTrue(Hash::check('NuevaClave456', $usuario->password));
    }

    public function test_profile_rejects_an_incorrect_current_password(): void
    {
        $usuario = User::factory()->create([
            'password' => Hash::make('ClaveCorrecta123'),
        ]);

        $this->actingAs($usuario)->put(route('perfil.update'), [
            'name' => $usuario->name,
            'email' => $usuario->email,
            'current_password' => 'ClaveEquivocada',
            'password' => 'NuevaClave456',
            'password_confirmation' => 'NuevaClave456',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('ClaveCorrecta123', $usuario->fresh()->password));
    }

    public function test_delegate_menu_and_team_panel_only_show_their_own_teams(): void
    {
        $delegado = $this->user(User::ROLE_DELEGADO);
        $otroDelegado = $this->user(User::ROLE_DELEGADO);
        Equipo::create(['nombre' => 'Mi equipo visible', 'delegado_id' => $delegado->id]);
        Equipo::create(['nombre' => 'Equipo ajeno oculto', 'delegado_id' => $otroDelegado->id]);

        $this->actingAs($delegado)->get(route('equipos.index'))
            ->assertOk()
            ->assertSee('Mis equipos')
            ->assertSee('Mi equipo visible')
            ->assertDontSee('Equipo ajeno oculto')
            ->assertSee('Ver plantilla');
    }

    public function test_delegate_sees_roster_access_only_inside_their_championship(): void
    {
        $delegado = $this->user(User::ROLE_DELEGADO);
        $organizador = $this->user(User::ROLE_ORGANIZADOR);
        $equipo = Equipo::create(['nombre' => 'Equipo del delegado', 'delegado_id' => $delegado->id]);
        $rival = Equipo::create([
            'nombre' => 'Rival confirmado',
            'delegado_id' => $this->user(User::ROLE_DELEGADO)->id,
        ]);
        $campeonato = $this->championship($organizador);
        $campeonato->update(['nombre' => 'Copa visible']);
        $otroCampeonato = $this->championship($organizador);
        $otroCampeonato->update(['nombre' => 'Copa ajena']);
        $equipo->campeonatos()->attach($campeonato->id, ['estado' => 'aprobado']);
        $rival->campeonatos()->attach($campeonato->id, ['estado' => 'aprobado']);
        $partido = Partido::create([
            'campeonato_id' => $campeonato->id,
            'equipo_local_id' => $equipo->id,
            'equipo_visita_id' => $rival->id,
            'fecha' => now()->addDay()->format('Y-m-d'),
            'hora' => '18:30',
            'estado' => 'programado',
        ]);

        $this->actingAs($delegado)->get(route('equipos.index'))
            ->assertOk()
            ->assertSee('Equipo del delegado')
            ->assertDontSee('Rival confirmado')
            ->assertDontSee('Elegir jugadores');

        $this->actingAs($delegado)->get(route('campeonatos.index'))
            ->assertOk()
            ->assertSee('Mis campeonatos')
            ->assertSee('Copa visible')
            ->assertDontSee('Copa ajena');

        $this->actingAs($delegado)->get(route('campeonatos.show', $campeonato))
            ->assertOk()
            ->assertSee('Copa visible')
            ->assertSee('Elegir jugadores')
            ->assertSee(route('partidos.nominas.editar', [$partido, $equipo]), false);
    }

    public function test_team_page_does_not_show_the_championship_enrollment_form(): void
    {
        $delegado = $this->user(User::ROLE_DELEGADO);
        $equipo = Equipo::create(['nombre' => 'Equipo sin formulario', 'delegado_id' => $delegado->id]);

        $this->actingAs($delegado)->get(route('equipos.show', $equipo))
            ->assertOk()
            ->assertDontSee('Solicitar inscripción')
            ->assertDontSee('Enviar solicitud')
            ->assertDontSee('Selecciona un campeonato abierto');
    }

    public function test_organizer_cannot_open_the_delegates_private_team_panel(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);

        $this->actingAs($organizador)->get(route('equipos.index'))->assertForbidden();
    }

    public function test_player_views_use_ci_and_show_the_calculated_age(): void
    {
        $delegado = $this->user(User::ROLE_DELEGADO);
        $equipo = Equipo::create(['nombre' => 'Equipo con edades', 'delegado_id' => $delegado->id]);
        Jugador::create([
            'nombre' => 'Jugador boliviano',
            'dni' => '7654321',
            'fecha_nacimiento' => today()->subYears(21)->format('Y-m-d'),
            'equipo_id' => $equipo->id,
        ]);

        $this->actingAs($delegado)->get(route('equipos.show', $equipo))
            ->assertOk()
            ->assertSee('CI')
            ->assertSee('21 años')
            ->assertDontSee('DNI');

        $this->assertFalse(app('router')->has('jugadores.index'));
    }

    public function test_player_birth_date_cannot_be_in_the_future(): void
    {
        $delegado = $this->user(User::ROLE_DELEGADO);
        $equipo = Equipo::create(['nombre' => 'Equipo fechas', 'delegado_id' => $delegado->id]);

        $this->actingAs($delegado)->post(route('jugadores.store'), [
            'nombre' => 'Fecha imposible',
            'dni' => '9988776',
            'fecha_nacimiento' => today()->addDay()->format('Y-m-d'),
            'equipo_id' => $equipo->id,
        ])->assertSessionHasErrors('fecha_nacimiento');
    }

    public function test_registering_a_player_returns_to_the_teams_roster(): void
    {
        $delegado = $this->user(User::ROLE_DELEGADO);
        $equipo = Equipo::create(['nombre' => 'Equipo retorno', 'delegado_id' => $delegado->id]);

        $this->actingAs($delegado)->get(route('equipos.jugadores.create', $equipo))
            ->assertOk()->assertDontSee('name="numero"', false);

        $this->actingAs($delegado)->post(route('jugadores.store'), [
            'nombre' => 'Nuevo integrante',
            'dni' => 'CI-RETORNO-1',
            'sexo' => 'masculino',
            'fecha_nacimiento' => today()->subYears(20)->format('Y-m-d'),
            'numero' => 99,
            'equipo_id' => $equipo->id,
        ])->assertRedirect(route('equipos.show', $equipo).'#jugadores')
            ->assertSessionHas('success', 'Jugador registrado. Queda pendiente de aprobación del organizador antes de poder jugar.');

        $this->assertDatabaseHas('jugadores', [
            'nombre' => 'Nuevo integrante',
            'equipo_id' => $equipo->id,
            'numero' => null,
        ]);
    }

    public function test_team_owner_can_see_and_use_the_delete_player_action(): void
    {
        $delegado = $this->user(User::ROLE_DELEGADO);
        $otroDelegado = $this->user(User::ROLE_DELEGADO);
        $equipo = Equipo::create(['nombre' => 'Equipo propietario', 'delegado_id' => $delegado->id]);
        $jugador = Jugador::create(['nombre' => 'Jugador eliminable', 'equipo_id' => $equipo->id]);

        $this->actingAs($delegado)->get(route('equipos.show', $equipo))
            ->assertOk()
            ->assertSee(route('jugadores.destroy', $jugador), false)
            ->assertSee('Eliminar');

        $this->actingAs($otroDelegado)->get(route('equipos.show', $equipo))
            ->assertOk()
            ->assertDontSee(route('jugadores.destroy', $jugador), false);

        $this->actingAs($delegado)
            ->from(route('equipos.show', $equipo).'#jugadores')
            ->delete(route('jugadores.destroy', $jugador))
            ->assertRedirect(route('equipos.show', $equipo).'#jugadores');

        $this->assertDatabaseMissing('jugadores', ['id' => $jugador->id]);
    }

    public function test_player_with_sports_history_cannot_be_deleted(): void
    {
        $delegado = $this->user(User::ROLE_DELEGADO);
        $equipo = Equipo::create(['nombre' => 'Equipo historial', 'delegado_id' => $delegado->id]);
        $jugador = Jugador::create(['nombre' => 'Jugador con gol', 'equipo_id' => $equipo->id]);
        Incidencia::create(['jugador_id' => $jugador->id, 'tipo' => Incidencia::TIPO_GOL, 'minuto' => 10]);

        $this->actingAs($delegado)
            ->from(route('equipos.show', $equipo).'#jugadores')
            ->delete(route('jugadores.destroy', $jugador))
            ->assertSessionHasErrors('jugador');

        $this->assertDatabaseHas('jugadores', ['id' => $jugador->id]);
    }

    public function test_championships_show_the_organizer_name_without_exposing_their_email(): void
    {
        $organizador = User::factory()->create([
            'name' => 'Organizadora Copa Sacaba',
            'email' => 'correo.privado.organizador@example.com',
            'rol' => User::ROLE_ORGANIZADOR,
        ]);
        $campeonato = $this->championship($organizador);

        $this->get(route('publico.index'))
            ->assertOk()
            ->assertSee('Organiza:')
            ->assertSee('Organizadora Copa Sacaba')
            ->assertDontSee('correo.privado.organizador@example.com');

        $this->get(route('publico.show', $campeonato))
            ->assertOk()
            ->assertSee('Organiza:')
            ->assertSee('Organizadora Copa Sacaba')
            ->assertDontSee('correo.privado.organizador@example.com');
    }

    public function test_championship_status_changes_automatically_according_to_its_dates(): void
    {
        $organizador = $this->user(User::ROLE_ORGANIZADOR);

        $proximo = $this->championship($organizador);
        $proximo->update([
            'nombre' => 'Torneo futuro',
            'fecha_inicio' => today()->addDay(),
            'fecha_fin' => today()->addDays(3),
            // Comprueba que una marca antigua no prevalece sobre las fechas.
            'estado' => 'en_curso',
        ]);

        $enCurso = $this->championship($organizador);
        $enCurso->update([
            'nombre' => 'Torneo activo',
            'fecha_inicio' => today()->subDay(),
            'fecha_fin' => today()->addDay(),
            'estado' => 'pendiente',
        ]);

        $terminadoPorFecha = $this->championship($organizador);
        $terminadoPorFecha->update([
            'nombre' => 'Torneo terminado',
            'fecha_inicio' => today()->subDays(5),
            'fecha_fin' => today()->subDay(),
            'estado' => 'pendiente',
        ]);

        $cerradoManualmente = $this->championship($organizador);
        $cerradoManualmente->update([
            'nombre' => 'Torneo cerrado',
            'fecha_inicio' => today()->addDay(),
            'fecha_fin' => today()->addDays(5),
            'estado' => 'finalizado',
        ]);

        $this->assertSame('pendiente', $proximo->fresh()->estado_actual);
        $this->assertSame('en_curso', $enCurso->fresh()->estado_actual);
        $this->assertSame('finalizado', $terminadoPorFecha->fresh()->estado_actual);
        $this->assertSame('finalizado', $cerradoManualmente->fresh()->estado_actual);

        $this->get(route('publico.show', $proximo))->assertOk()->assertSee('Próximo');
        $this->get(route('publico.show', $enCurso))->assertOk()->assertSee('En curso');
        $this->get(route('publico.show', $terminadoPorFecha))->assertOk()->assertSee('Finalizado');

        $this->assertFalse(Campeonato::noFinalizadosActuales()->whereKey($terminadoPorFecha)->exists());
        $this->assertFalse(Campeonato::noFinalizadosActuales()->whereKey($cerradoManualmente)->exists());
    }

    public function test_users_and_players_can_save_a_simple_private_whatsapp_number(): void
    {
        $this->post('/register', [
            'name' => 'Delegado con WhatsApp',
            'email' => 'delegado.whatsapp@test.com',
            'whatsapp' => '71234567',
            'whatsapp_notifications' => '1',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'rol' => User::ROLE_DELEGADO,
        ])->assertRedirect('/home');

        $delegado = User::where('email', 'delegado.whatsapp@test.com')->firstOrFail();
        $this->assertSame('+59171234567', $delegado->whatsapp);
        $this->assertSame('71234567', $delegado->whatsapp_nacional);
        $this->assertNotNull($delegado->whatsapp_opt_in_at);
        $this->assertArrayNotHasKey('whatsapp', $delegado->toArray());

        $equipo = Equipo::create(['nombre' => 'Equipo con contacto', 'delegado_id' => $delegado->id]);
        $this->actingAs($delegado)->post(route('jugadores.store'), [
            'nombre' => 'Jugador con WhatsApp',
            'dni' => 'CI-WHATSAPP-1',
            'whatsapp' => '62345678',
            'whatsapp_notifications' => '1',
            'sexo' => 'masculino',
            'equipo_id' => $equipo->id,
        ])->assertSessionDoesntHaveErrors();

        $jugador = Jugador::where('nombre', 'Jugador con WhatsApp')->firstOrFail();
        $this->assertSame('+59162345678', $jugador->whatsapp);
        $this->assertNotNull($jugador->whatsapp_opt_in_at);
        $this->assertSame('https://wa.me/59162345678', $jugador->whatsapp_url);
        $this->assertArrayNotHasKey('whatsapp', $jugador->toArray());

        $this->get(route('jugadores.show', $jugador))
            ->assertOk()
            ->assertSee('+591 62345678')
            ->assertSee('https://wa.me/59162345678', false);
    }

    public function test_player_photo_is_stored_locally_and_its_path_is_saved(): void
    {
        Storage::fake('public');

        $delegado = $this->user(User::ROLE_DELEGADO);
        $equipo = Equipo::create(['nombre' => 'Equipo local', 'delegado_id' => $delegado->id]);
        $pngMinimo = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL+WQAAAABJRU5ErkJggg==');

        $this->actingAs($delegado)->post(route('jugadores.store'), [
            'nombre' => 'Jugador local',
            'sexo' => 'masculino',
            'equipo_id' => $equipo->id,
            'foto' => UploadedFile::fake()->createWithContent('jugador.png', $pngMinimo),
        ])->assertSessionDoesntHaveErrors();

        $jugador = Jugador::where('nombre', 'Jugador local')->firstOrFail();
        $this->assertStringStartsWith('jugadores/', $jugador->foto);
        Storage::disk('public')->assertExists($jugador->foto);
        $this->assertSame(asset('storage/'.$jugador->foto), $jugador->foto_url);
    }

    public function test_team_crest_is_stored_locally_and_its_path_is_saved(): void
    {
        Storage::fake('public');

        $delegado = $this->user(User::ROLE_DELEGADO);
        $pngMinimo = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL+WQAAAABJRU5ErkJggg==');

        $this->actingAs($delegado)->post(route('equipos.store'), [
            'nombre' => 'Equipo con escudo local',
            'ciudad' => 'Sacaba',
            'escudo' => UploadedFile::fake()->createWithContent('escudo.png', $pngMinimo),
        ])->assertSessionDoesntHaveErrors();

        $equipo = Equipo::where('nombre', 'Equipo con escudo local')->firstOrFail();
        $this->assertStringStartsWith('escudos/', $equipo->escudo);
        Storage::disk('public')->assertExists($equipo->escudo);
        $this->assertSame(asset('storage/'.$equipo->escudo), $equipo->escudo_url);
    }

    public function test_programming_a_match_notifies_players_with_a_number_and_opted_in_delegates(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.version' => 'v25.0',
            'services.whatsapp.phone_number_id' => '123456789',
            'services.whatsapp.access_token' => 'token-only-for-tests',
        ]);
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.match']]]),
        ]);

        $organizador = $this->user(User::ROLE_ORGANIZADOR);
        $delegadoLocal = $this->user(User::ROLE_DELEGADO);
        $delegadoLocal->update([
            'whatsapp' => '61234567',
            'whatsapp_opt_in_at' => now(),
        ]);
        $delegadoVisita = $this->user(User::ROLE_DELEGADO);
        $campeonato = $this->championship($organizador);
        $local = Equipo::create(['nombre' => 'Equipo local', 'delegado_id' => $delegadoLocal->id]);
        $visita = Equipo::create(['nombre' => 'Equipo visita', 'delegado_id' => $delegadoVisita->id]);
        $campeonato->equipos()->attach([$local->id, $visita->id], ['estado' => 'aprobado']);
        Jugador::create([
            'nombre' => 'Jugador avisado',
            'whatsapp' => '71234567',
            'whatsapp_opt_in_at' => now(),
            'equipo_id' => $local->id,
            'estado' => Jugador::ESTADO_APROBADO,
        ]);
        Jugador::create([
            'nombre' => 'Jugador sin autorización',
            'whatsapp' => '73456789',
            'equipo_id' => $local->id,
            'estado' => Jugador::ESTADO_APROBADO,
        ]);
        Jugador::create([
            'nombre' => 'Jugador sin número',
            'equipo_id' => $visita->id,
            'estado' => Jugador::ESTADO_APROBADO,
        ]);

        $this->actingAs($organizador)
            ->post(route('partidos.store'), [
                'campeonato_id' => $campeonato->id,
                'equipo_local_id' => $local->id,
                'equipo_visita_id' => $visita->id,
                'fecha' => '2026-09-15',
                'hora' => '15:30',
                'lugar' => 'Cancha municipal',
            ])
            ->assertSessionDoesntHaveErrors();

        Http::assertSent(fn ($request) => $request['to'] === '59171234567'
            && $request['type'] === 'template'
            && $request['template']['name'] === 'partido_jugador_programado'
            && $request['template']['components'][0]['parameters'][0]['text'] === 'Jugador avisado'
            && $request['template']['components'][0]['parameters'][1]['text'] === 'Equipo local'
            && $request['template']['components'][0]['parameters'][2]['text'] === 'Equipo visita'
            && $request['template']['components'][0]['parameters'][3]['text'] === '15/09/2026'
            && $request['template']['components'][0]['parameters'][4]['text'] === '15:30'
            && $request['template']['components'][0]['parameters'][5]['text'] === 'Cancha municipal'
        );
        Http::assertSent(fn ($request) => $request['to'] === '59161234567'
            && $request['template']['name'] === 'partido_delegado_programado'
            && $request['template']['components'][0]['parameters'][1]['text'] === 'Equipo local'
        );
        Http::assertSentCount(2);
    }

    public function test_match_whatsapp_is_deduplicated_by_phone_and_schedule(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.version' => 'v25.0',
            'services.whatsapp.phone_number_id' => '123456789',
            'services.whatsapp.access_token' => 'test-token',
        ]);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.unique']]])]);
        $owner = $this->user(User::ROLE_ORGANIZADOR);
        $delegate = $this->user(User::ROLE_DELEGADO);
        $delegate->update(['whatsapp' => '71234567', 'whatsapp_opt_in_at' => now()]);
        $local = Equipo::create(['nombre' => 'Local', 'delegado_id' => $delegate->id]);
        $away = Equipo::create(['nombre' => 'Visita', 'delegado_id' => $delegate->id]);
        foreach ([$local, $local, $away] as $team) {
            Jugador::create(['nombre' => 'Telefono compartido', 'equipo_id' => $team->id,
                'estado' => Jugador::ESTADO_APROBADO, 'whatsapp' => '71234567', 'whatsapp_opt_in_at' => now()]);
        }
        $match = Partido::create(['campeonato_id' => $this->championship($owner)->id,
            'equipo_local_id' => $local->id, 'equipo_visita_id' => $away->id,
            'fecha' => now()->addDays(3), 'hora' => '15:00', 'cancha' => 'Cancha A']);
        $service = app(\App\Services\NotificadorWhatsAppPartido::class);
        $this->assertSame(1, $service->programacion($match));
        $this->assertSame(0, $service->programacion($match->fresh()));
        $this->assertSame(0, $service->programacion($match->fresh(), true));
        Http::assertSentCount(1);
        $match->update(['hora' => '16:00']);
        $this->assertSame(1, $service->programacion($match->fresh()));
        Http::assertSentCount(2);
        $this->assertDatabaseCount('match_whatsapp_deliveries', 2);
    }

    public function test_match_reminder_is_sent_once_during_the_last_24_hours(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.version' => 'v25.0',
            'services.whatsapp.phone_number_id' => '123456789',
            'services.whatsapp.access_token' => 'token-only-for-tests',
        ]);
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.reminder']]]),
        ]);

        $organizador = $this->user(User::ROLE_ORGANIZADOR);
        $local = Equipo::create(['nombre' => 'Local recordatorio', 'delegado_id' => $this->user(User::ROLE_DELEGADO)->id]);
        $visita = Equipo::create(['nombre' => 'Visita recordatorio', 'delegado_id' => $this->user(User::ROLE_DELEGADO)->id]);
        $jugador = Jugador::create([
            'nombre' => 'Jugador recordado',
            'whatsapp' => '72345678',
            'whatsapp_opt_in_at' => now(),
            'equipo_id' => $local->id,
            'estado' => Jugador::ESTADO_APROBADO,
        ]);
        $inicio = now()->addHours(23);
        $partido = Partido::create([
            'campeonato_id' => $this->championship($organizador)->id,
            'equipo_local_id' => $local->id,
            'equipo_visita_id' => $visita->id,
            'fecha' => $inicio->toDateString(),
            'hora' => $inicio->format('H:i'),
            'estado' => 'programado',
        ]);

        $this->artisan('partidos:recordar')->assertSuccessful();

        $this->assertNotNull($partido->fresh()->whatsapp_recordatorio_enviado_at);
        Http::assertSent(fn ($request) => $request['to'] === '59172345678'
            && $request['template']['name'] === 'partido_jugador_programado'
        );
        Http::assertSentCount(1);

        $this->artisan('partidos:recordar')->assertSuccessful();
        Http::assertSentCount(1);
    }

    public function test_whatsapp_field_rejects_an_invalid_bolivian_cellphone(): void
    {
        $this->post('/register', [
            'name' => 'Número inválido',
            'email' => 'numero.invalido@test.com',
            'whatsapp' => '1234',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'rol' => User::ROLE_DELEGADO,
        ])->assertSessionHasErrors('whatsapp');

        $this->assertDatabaseMissing('users', ['email' => 'numero.invalido@test.com']);
    }

    public function test_demo_delegates_have_one_team_and_eleven_male_players_each(): void
    {
        $this->seed(DelegadosEquiposSeeder::class);

        $emails = collect(range(3, 20))
            ->map(fn (int $numero) => "delegado{$numero}@gmail.com");
        $delegados = User::whereIn('email', $emails)->get();
        $equipos = Equipo::whereIn('delegado_id', $delegados->pluck('id'))->get();
        $jugadores = Jugador::whereIn('equipo_id', $equipos->pluck('id'))->get();

        $this->assertCount(18, $delegados);
        $this->assertCount(18, $equipos);
        $this->assertCount(198, $jugadores);
        $this->assertTrue($equipos->every(
            fn (Equipo $equipo) => $jugadores->where('equipo_id', $equipo->id)->count() === 11
        ));
        $this->assertTrue($equipos->every(
            fn (Equipo $equipo) => $jugadores
                ->where('equipo_id', $equipo->id)
                ->pluck('apellido')
                ->unique()
                ->count() === 11
        ));
        $this->assertTrue($jugadores->every(
            fn (Jugador $jugador) => $jugador->sexo === 'masculino'
                && $jugador->edad >= 15
                && $jugador->edad <= 40
        ));
        $this->assertTrue($delegados->every(fn (User $delegado) => str_starts_with($delegado->name, 'Delegado ')));
        $this->assertTrue($equipos->every(fn (Equipo $equipo) => str_starts_with($equipo->nombre, 'Equipo Delegado ')));
        $this->assertFalse($jugadores->contains(fn (Jugador $jugador) => str_starts_with($jugador->nombre, 'Jugador ')));
        $this->assertTrue(Hash::check('75959158', $delegados->first()->password));
    }

    private function user(string $role): User
    {
        return User::factory()->create(['rol' => $role]);
    }

    private function championship(User $owner): Campeonato
    {
        return Campeonato::forceCreate(['aprobacion_estado' => 'aprobado', 'nombre' => 'Torneo', 'fecha_inicio' => now()->addMonth(), 'estado' => 'pendiente', 'usuario_creador' => $owner->id, 'formato' => 'liga', 'inscripciones_abiertas' => true]);
    }
}
