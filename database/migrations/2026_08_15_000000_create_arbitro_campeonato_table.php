<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arbitro_campeonato', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campeonato_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arbitro_id')->constrained('users')->cascadeOnDelete();
            $table->enum('estado', ['pendiente', 'aprobado', 'rechazado', 'retirado'])->default('pendiente');
            $table->text('mensaje')->nullable();
            $table->text('motivo_respuesta')->nullable();
            $table->foreignId('gestionado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('gestionado_at')->nullable();
            $table->timestamps();

            $table->unique(['campeonato_id', 'arbitro_id']);
            $table->index(['campeonato_id', 'estado']);
            $table->index(['arbitro_id', 'estado']);
        });

        // Conserva como aprobadas las asignaciones realizadas antes de este flujo.
        DB::table('partidos')->whereNotNull('arbitro_id')
            ->select('campeonato_id', 'arbitro_id')->distinct()->orderBy('campeonato_id')->get()
            ->each(fn ($registro) => DB::table('arbitro_campeonato')->insertOrIgnore([
                'campeonato_id' => $registro->campeonato_id,
                'arbitro_id' => $registro->arbitro_id,
                'estado' => 'aprobado',
                'gestionado_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('arbitro_campeonato');
    }
};
