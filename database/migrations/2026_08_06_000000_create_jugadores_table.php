<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crear tabla 'jugadores' para almacenar jugadores de equipos.
     * Campos básicos: nombre, apellido, dni, fecha_nacimiento, posicion, numero, foto (nullable), equipo_id (FK).
     */
    public function up(): void
    {
        Schema::create('jugadores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('apellido')->nullable();
            $table->string('dni')->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('posicion')->nullable();
            $table->unsignedInteger('numero')->nullable();
            $table->string('foto')->nullable(); // path relativo en storage/app/public/jugadores
            $table->unsignedBigInteger('equipo_id');
            $table->timestamps();

            $table->foreign('equipo_id')->references('id')->on('equipos')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jugadores');
    }
};
