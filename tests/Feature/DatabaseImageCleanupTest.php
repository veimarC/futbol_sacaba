<?php

namespace Tests\Feature;

use App\Models\Equipo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseImageCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_preserves_local_paths_and_can_restore_column_structure(): void
    {
        $migration = require database_path('migrations/2026_09_27_000000_remove_unused_cloudinary_columns.php');
        $columns = ['jugadores' => 'foto_cloudinary_id', 'equipos' => 'escudo_cloudinary_id', 'campeonatos' => 'portada_cloudinary_id'];
        foreach ($columns as $table => $column) {
            $this->assertFalse(Schema::hasColumn($table, $column));
        }

        $migration->down();
        foreach ($columns as $table => $column) {
            $this->assertTrue(Schema::hasColumn($table, $column));
        }

        $equipo = Equipo::create([
            'nombre' => 'Equipo conservado',
            'delegado_id' => User::factory()->create()->id,
            'escudo' => 'escudos/existente.png',
        ]);
        DB::table('equipos')->where('id', $equipo->id)->update(['escudo_cloudinary_id' => 'identificador-historico']);

        $migration->up();
        foreach ($columns as $table => $column) {
            $this->assertFalse(Schema::hasColumn($table, $column));
        }
        $this->assertSame('escudos/existente.png', $equipo->fresh()->escudo);
        $this->assertSame('Equipo conservado', $equipo->fresh()->nombre);
    }
}
