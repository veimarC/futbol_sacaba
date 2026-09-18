<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campeonatos', function (Blueprint $table) {
            $table->unsignedTinyInteger('modalidad')->default(11)->after('formato_config');
            $table->unsignedSmallInteger('jugadores_cancha')->default(11)->after('modalidad');
            $table->unsignedSmallInteger('min_jugadores_equipo')->default(15)->after('jugadores_cancha');
            $table->unsignedSmallInteger('max_jugadores_equipo')->default(25)->after('min_jugadores_equipo');
        });
    }

    public function down(): void
    {
        Schema::table('campeonatos', fn (Blueprint $table) => $table->dropColumn([
            'modalidad',
            'jugadores_cancha',
            'min_jugadores_equipo',
            'max_jugadores_equipo',
        ]));
    }
};
