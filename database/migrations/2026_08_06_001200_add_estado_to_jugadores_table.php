<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Añade columna 'estado' a jugadores: 'aprobado','pendiente','rechazado'.
     * Por compatibilidad con datos existentes se pone por defecto 'aprobado'.
     */
    public function up(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            $table->string('estado', 20)->default('aprobado')->after('foto');
        });

        // Para seguridad, si debes convertir registros antiguos puedes hacerlo aquí.
    }

    public function down(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};
