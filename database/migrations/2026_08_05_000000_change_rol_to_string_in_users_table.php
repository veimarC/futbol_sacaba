<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modifica la columna 'rol' de enum a string para permitir más roles y flexibilidad.
     * También normaliza valores existentes: si hay 'administrador' se convertirá a 'desarrollador'.
     */
    public function up(): void
    {
        // Usar string en vez de enum para evitar problemas al añadir roles futuros.
        Schema::table('users', function (Blueprint $table) {
            // Si la columna existe y es enum, cambiar a string.
            // En algunos drivers (MySQL) el cambio puede necesitar doctrine/dbal; si falla,
            // ejecutar manualmente la alteración de columna en la base de datos.
            $table->string('rol', 20)->default('publico')->nullable(false)->change();
        });

        // Normalizar valores existentes: 'administrador' -> 'desarrollador'
        try {
            \DB::table('users')
                ->where('rol', 'administrador')
                ->update(['rol' => 'desarrollador']);
        } catch (\Throwable $e) {
            // Si no se puede acceder a la tabla (por ejemplo en migración en fresh install), ignorar.
        }
    }

    /**
     * Revertir: intentar volver a enum con los valores originales (si necesario).
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Volver a enum original con los valores mínimos; este paso puede requerir doctrine/dbal.
            $table->enum('rol', ['administrador', 'delegado', 'arbitro'])->default('delegado')->change();
        });
    }
};
