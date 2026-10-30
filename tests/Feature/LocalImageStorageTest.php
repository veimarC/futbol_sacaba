<?php

namespace Tests\Feature;

use App\Models\Campeonato;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\User;
use App\Services\LocalImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class LocalImageStorageTest extends TestCase
{
    use RefreshDatabase;

    public static function tiposImagen(): array
    {
        return [
            'escudo' => ['equipos', Equipo::class, 'escudo'],
            'foto' => ['jugadores', Jugador::class, 'foto'],
            'portada' => ['campeonatos', Campeonato::class, 'portada'],
        ];
    }

    #[DataProvider('tiposImagen')]
    public function test_image_can_be_uploaded_preserved_replaced_and_deleted(string $recurso, string $modelo, string $campo): void
    {
        $datos = $this->preparar($recurso);
        $this->post(route($recurso.'.store'), $datos + [$campo => $this->imagen()])
            ->assertRedirect()->assertSessionDoesntHaveErrors();
        $registro = $modelo::latest('id')->firstOrFail();
        $anterior = $registro->$campo;
        Storage::disk('public')->assertExists($anterior);
        $this->assertSame(asset('storage/'.$anterior), $registro->{$campo.'_url'});

        $this->put(route($recurso.'.update', $registro), $datos + [$campo => null])
            ->assertRedirect()->assertSessionDoesntHaveErrors();
        $this->assertSame($anterior, $registro->fresh()->$campo);
        Storage::disk('public')->assertExists($anterior);

        $this->put(route($recurso.'.update', $registro), $datos + [$campo => $this->imagen()])
            ->assertRedirect()->assertSessionDoesntHaveErrors();
        $nueva = $registro->fresh()->$campo;
        $this->assertNotSame($anterior, $nueva);
        Storage::disk('public')->assertMissing($anterior);
        Storage::disk('public')->assertExists($nueva);

        $this->delete(route($recurso.'.destroy', $registro))
            ->assertRedirect()->assertSessionDoesntHaveErrors();
        $this->assertModelMissing($registro);
        Storage::disk('public')->assertMissing($nueva);
    }

    #[DataProvider('tiposImagen')]
    public function test_failed_replacement_preserves_previous_image(string $recurso, string $modelo, string $campo): void
    {
        $datos = $this->preparar($recurso);
        $this->post(route($recurso.'.store'), $datos + [$campo => $this->imagen()])
            ->assertSessionDoesntHaveErrors();
        $registro = $modelo::latest('id')->firstOrFail();
        $anterior = $registro->$campo;

        $imagenes = Mockery::mock(LocalImageService::class);
        $imagenes->shouldReceive('subir')->once()->andThrow(new RuntimeException('Disco no disponible.'));
        $this->app->instance(LocalImageService::class, $imagenes);

        $this->put(route($recurso.'.update', $registro), $datos + [$campo => $this->imagen()])
            ->assertSessionHasErrors($campo);
        $this->assertSame($anterior, $registro->fresh()->$campo);
        Storage::disk('public')->assertExists($anterior);
    }

    public function test_cover_can_be_removed_without_deleting_championship(): void
    {
        $datos = $this->preparar('campeonatos');
        $this->post(route('campeonatos.store'), $datos + ['portada' => $this->imagen()])
            ->assertSessionDoesntHaveErrors();
        $campeonato = Campeonato::firstOrFail();
        $anterior = $campeonato->portada;

        $this->put(route('campeonatos.update', $campeonato), $datos + ['eliminar_portada' => true])
            ->assertSessionDoesntHaveErrors();
        $this->assertNull($campeonato->fresh()->portada);
        Storage::disk('public')->assertMissing($anterior);
    }

    private function preparar(string $recurso): array
    {
        Storage::fake('public');
        $usuario = User::factory()->create([
            'rol' => $recurso === 'campeonatos' ? User::ROLE_ORGANIZADOR : User::ROLE_DELEGADO,
        ]);
        $this->actingAs($usuario);

        return match ($recurso) {
            'equipos' => ['nombre' => 'Equipo de prueba', 'ciudad' => 'Sacaba'],
            'jugadores' => [
                'nombre' => 'Jugador de prueba', 'sexo' => 'masculino',
                'equipo_id' => Equipo::create(['nombre' => 'Equipo de prueba', 'delegado_id' => $usuario->id])->id,
            ],
            'campeonatos' => [
                'nombre' => 'Copa de prueba', 'fecha_inicio' => now()->addMonth()->toDateString(),
                'formato' => 'liga', 'modalidad' => 7, 'jugadores_cancha' => 7,
                'min_jugadores_equipo' => 10, 'max_jugadores_equipo' => 15,
                'categoria_edad' => 'libre', 'sexo' => 'mixto',
            ],
        };
    }

    private function imagen(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('imagen.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL+WQAAAABJRU5ErkJggg=='
        ));
    }
}
