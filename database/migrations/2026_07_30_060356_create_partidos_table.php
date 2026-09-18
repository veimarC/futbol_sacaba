<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partidos', function (Blueprint $table) {
            // Identificador único
            $table->id();

            // Relaciones obligatorias
            $table->unsignedBigInteger('campeonato_id');
            $table->unsignedBigInteger('equipo_local_id');
            $table->unsignedBigInteger('equipo_visita_id');

            // Datos del partido
            $table->date('fecha');
            $table->time('hora');
            $table->string('cancha')->nullable();
            // En la migración de partidos o en la base de datos:
            $table->integer('goles_local')->nullable()->default(null);
            $table->integer('goles_visita')->nullable()->default(null);
            $table->enum('estado', ['programado', 'jugado'])->default('programado');


            // Fechas de creación y modificación
            $table->timestamps();

            // Llaves foráneas (si borras el campeonato, se borran sus partidos)
            $table->foreign('campeonato_id')->references('id')->on('campeonatos')->onDelete('cascade');
            $table->foreign('equipo_local_id')->references('id')->on('equipos')->onDelete('cascade');
            $table->foreign('equipo_visita_id')->references('id')->on('equipos')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partidos');
    }
};
