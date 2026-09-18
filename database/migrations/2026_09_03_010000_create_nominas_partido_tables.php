<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nominas_partido', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partido_id')->constrained('partidos')->cascadeOnDelete();
            $table->foreignId('equipo_id')->constrained('equipos')->cascadeOnDelete();
            $table->string('estado', 20)->default('pendiente');
            $table->text('motivo_respuesta')->nullable();
            $table->timestamp('enviado_at')->nullable();
            $table->timestamp('aprobado_at')->nullable();
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['partido_id', 'equipo_id']);
        });

        Schema::create('nomina_partido_jugadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nomina_partido_id')->constrained('nominas_partido')->cascadeOnDelete();
            $table->foreignId('jugador_id')->constrained('jugadores')->cascadeOnDelete();
            $table->unsignedTinyInteger('dorsal');
            $table->boolean('titular')->default(false);
            $table->timestamps();
            $table->unique(['nomina_partido_id', 'jugador_id']);
            $table->unique(['nomina_partido_id', 'dorsal']);
        });

        Schema::table('incidencias', function (Blueprint $table) {
            $table->unsignedTinyInteger('dorsal')->nullable()->after('jugador_id');
        });
    }

    public function down(): void
    {
        Schema::table('incidencias', function (Blueprint $table) {
            $table->dropColumn('dorsal');
        });
        Schema::dropIfExists('nomina_partido_jugadores');
        Schema::dropIfExists('nominas_partido');
    }
};
