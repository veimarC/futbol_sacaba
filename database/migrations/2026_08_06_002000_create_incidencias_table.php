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
        Schema::create('incidencias', function (Blueprint $table) {
            $table->id();
            // partido_id puede ser null si la incidencia se registra fuera de un partido concreto
            $table->unsignedBigInteger('partido_id')->nullable()->index();
            $table->unsignedBigInteger('jugador_id')->index();
            // tipo: 'gol', 'amarilla', 'roja' (string para evitar dependencias con enum)
            $table->string('tipo');
            // minuto opcional
            $table->unsignedSmallInteger('minuto')->nullable();
            // quien registró la incidencia
            $table->unsignedBigInteger('creado_por')->nullable()->index();
            $table->timestamps();

            // No forzamos FK para evitar problemas en entornos con tablas no creadas todavía
            // pero mantenemos índices para consultas rápidas
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidencias');
    }
};
