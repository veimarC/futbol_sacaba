<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Método robusto: buscar índices sobre la columna 'dni' y eliminarlos por nombre.
        if (Schema::hasTable('jugadores') && Schema::hasColumn('jugadores', 'dni')) {
            try {
                $indexes = \Illuminate\Support\Facades\DB::select("SHOW INDEX FROM jugadores WHERE Column_name = 'dni'");
                foreach ($indexes as $idx) {
                    $keyName = $idx->Key_name ?? $idx['Key_name'] ?? null;
                    if ($keyName) {
                        try {
                            \Illuminate\Support\Facades\DB::statement("ALTER TABLE jugadores DROP INDEX `{$keyName}`");
                        } catch (\Throwable $e) {
                            // ignorar errores puntuales y seguir
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Si no se puede listar o eliminar, no detener la migración (fall-back safe)
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            if (Schema::hasColumn('jugadores', 'dni')) {
                $table->unique('dni');
            }
        });
    }
};
