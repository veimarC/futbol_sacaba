<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campeonatos', function (Blueprint $table) {
            $table->string('formato')->nullable()->after('estado');
            $table->json('formato_config')->nullable()->after('formato');
        });

        Schema::table('partidos', function (Blueprint $table) {
            $table->unsignedBigInteger('fase_id')->nullable()->after('campeonato_id');
            $table->integer('ronda_number')->nullable()->after('fase_id');
            $table->integer('orden_en_ronda')->nullable()->after('ronda_number');
            $table->foreign('fase_id')->references('id')->on('fases')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('partidos', function (Blueprint $table) {
            $table->dropForeign(['fase_id']);
            $table->dropColumn(['fase_id','ronda_number','orden_en_ronda']);
        });

        Schema::table('campeonatos', function (Blueprint $table) {
            $table->dropColumn(['formato','formato_config']);
        });
    }
};
