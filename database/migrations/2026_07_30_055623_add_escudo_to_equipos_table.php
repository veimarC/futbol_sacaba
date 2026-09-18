<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Añadir la columna solo si no existe (hace la migración idempotente)
        if (!Schema::hasColumn('equipos', 'escudo')) {
            Schema::table('equipos', function (Blueprint $table) {
                // Agregamos el campo escudo (opcional)
                $table->string('escudo')->nullable()->after('color_camiseta');
            });
        }
    }

    public function down(): void
    {
        // Eliminar la columna solo si existe
        if (Schema::hasColumn('equipos', 'escudo')) {
            Schema::table('equipos', function (Blueprint $table) {
                $table->dropColumn('escudo');
            });
        }
    }
};
