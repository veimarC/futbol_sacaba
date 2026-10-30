<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partidos', function (Blueprint $table) {
            $table->string('wo_motivo', 40)->nullable();
            $table->unsignedBigInteger('wo_equipo_perdedor_id')->nullable();
            $table->unsignedBigInteger('wo_registrado_por')->nullable();
            $table->timestamp('wo_registrado_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('partidos', fn (Blueprint $table) => $table->dropColumn([
            'wo_motivo', 'wo_equipo_perdedor_id', 'wo_registrado_por', 'wo_registrado_at',
        ]));
    }
};