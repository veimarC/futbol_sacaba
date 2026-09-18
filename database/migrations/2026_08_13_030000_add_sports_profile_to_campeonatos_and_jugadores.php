<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campeonatos', function (Blueprint $table) {
            $table->string('nivel', 80)->default('libre')->after('max_jugadores_equipo');
            $table->string('categoria_edad', 80)->default('libre')->after('nivel');
            $table->unsignedTinyInteger('edad_min')->nullable()->after('categoria_edad');
            $table->unsignedTinyInteger('edad_max')->nullable()->after('edad_min');
            $table->string('sexo', 20)->default('mixto')->after('edad_max');
        });

        Schema::table('jugadores', function (Blueprint $table) {
            $table->string('sexo', 20)->nullable()->after('fecha_nacimiento');
        });
    }

    public function down(): void
    {
        Schema::table('jugadores', fn (Blueprint $table) => $table->dropColumn('sexo'));
        Schema::table('campeonatos', fn (Blueprint $table) => $table->dropColumn([
            'nivel', 'categoria_edad', 'edad_min', 'edad_max', 'sexo',
        ]));
    }
};
