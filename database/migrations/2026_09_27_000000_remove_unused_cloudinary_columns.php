<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'jugadores' => 'foto_cloudinary_id',
        'equipos' => 'escudo_cloudinary_id',
        'campeonatos' => 'portada_cloudinary_id',
    ];

    public function up(): void
    {
        // Las rutas locales permanecen intactas. Respaldar los IDs antes de aplicar.
        foreach (self::COLUMNS as $table => $column) {
            if (Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($column));
            }
        }
    }

    public function down(): void
    {
        // Restaura la estructura; los valores antiguos requieren el respaldo.
        foreach (self::COLUMNS as $table => $column) {
            if (! Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->string($column)->nullable());
            }
        }
    }
};
