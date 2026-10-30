<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Respaldar las referencias históricas antes de aplicar esta limpieza.
        if (Schema::hasColumn('partidos', 'arbitro_id')) {
            Schema::table('partidos', function (Blueprint $table) {
                $table->dropForeign(['arbitro_id']);
                $table->dropColumn('arbitro_id');
            });
        }

        Schema::dropIfExists('arbitro_campeonato');
        Schema::dropIfExists('retired_referee_accounts');
    }

    public function down(): void
    {
        // Restaura la estructura; los datos históricos requieren el respaldo.
        if (! Schema::hasColumn('partidos', 'arbitro_id')) {
            Schema::table('partidos', function (Blueprint $table) {
                $table->foreignId('arbitro_id')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('arbitro_campeonato')) {
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
        }

        if (! Schema::hasTable('retired_referee_accounts')) {
            Schema::create('retired_referee_accounts', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->primary();
            });
        }
    }
};
