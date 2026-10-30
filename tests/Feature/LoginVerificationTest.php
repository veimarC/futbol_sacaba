<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_login_and_role_destinations(): void
    {
        foreach ([User::ROLE_DESARROLLADOR, User::ROLE_ORGANIZADOR, User::ROLE_DELEGADO, 'publico'] as $role) {
            $user = User::factory()->create(['rol' => $role, 'password' => Hash::make('TestPassword123')]);
            $this->post('/login', ['email' => $user->email, 'password' => 'TestPassword123'])->assertRedirect('/home');
            $this->assertAuthenticatedAs($user);
            $this->get('/home')->assertRedirect(route($role === 'publico' ? 'publico.index' : 'campeonatos.index'));
            $this->post('/logout')->assertRedirect('/');
            $this->assertGuest();
        }
    }

    public function test_organizer_recovers_from_missing_championship_after_login(): void
    {
        $user = User::factory()->create([
            'rol' => User::ROLE_ORGANIZADOR,
            'password' => Hash::make('TestPassword123'),
        ]);
        $missingUrl = route('campeonatos.show', 999999);

        $this->get($missingUrl)->assertRedirect(route('login'));
        $this->post('/login', ['email' => $user->email, 'password' => 'TestPassword123'])
            ->assertRedirect($missingUrl);
        $this->get($missingUrl)->assertRedirect(route('campeonatos.index'))
            ->assertSessionHas('mensaje', 'El campeonato solicitado ya no existe. Selecciona uno del listado.');
        $this->get(route('campeonatos.index'))->assertOk();

        $this->getJson($missingUrl)->assertNotFound();
        $this->get(route('campeonatos.edit', 999999))->assertNotFound();
        $this->delete(route('campeonatos.destroy', 999999))->assertNotFound();
    }

    public function test_rejected_credentials_and_required_fields(): void
    {
        $user = User::factory()->create(['password' => Hash::make('TestPassword123')]);
        $cases = [
            [['email' => $user->email, 'password' => 'wrong'], ['password' => 'La contraseña es incorrecta. Inténtalo nuevamente.']],
            [['email' => 'missing@example.test', 'password' => 'wrong'], ['email' => 'No existe una cuenta con este correo. Revisa el correo o regístrate.']],
            [[], ['email', 'password']],
            [['password' => 'TestPassword123'], ['email']],
            [['email' => $user->email], ['password']],
            [['email' => 'usuario', 'password' => 'TestPassword123'], ['email']],
        ];
        foreach ($cases as [$data, $errors]) {
            $this->from('/login')->post('/login', $data)->assertRedirect('/login')->assertSessionHasErrors($errors);
            $this->assertGuest();
        }
        $this->get('/login')->assertOk()->assertSee('type="email"', false)->assertSee('required autocomplete="email"', false)->assertSee('required autocomplete="current-password"', false);
    }

    public function test_private_access_before_login_and_after_logout(): void
    {
        $this->get('/perfil')->assertRedirect(route('login'));
        $user = User::factory()->create(['password' => Hash::make('TestPassword123')]);
        $this->post('/login', ['email' => $user->email, 'password' => 'TestPassword123']);
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->get('/perfil')->assertRedirect(route('login'));
    }
}
