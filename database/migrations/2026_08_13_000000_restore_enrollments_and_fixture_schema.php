<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campeonatos', function (Blueprint $table) {
            $table->date('fecha_limite_inscripcion')->nullable()->after('fecha_fin');
            $table->boolean('inscripciones_abiertas')->default(false)->after('fecha_limite_inscripcion');
        });

        Schema::create('campeonato_equipo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campeonato_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipo_id')->constrained()->cascadeOnDelete();
            $table->string('estado', 20)->default('pendiente');
            $table->timestamps();
            $table->unique(['campeonato_id', 'equipo_id']);
        });

        $ahora = now();
        DB::table('equipos')->whereNotNull('campeonato_id')->orderBy('id')
            ->each(function ($equipo) use ($ahora) {
                DB::table('campeonato_equipo')->insertOrIgnore([
                    'campeonato_id' => $equipo->campeonato_id,
                    'equipo_id' => $equipo->id,
                    'estado' => 'aprobado',
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            });

        Schema::table('equipos', fn (Blueprint $table) => $table->unsignedBigInteger('campeonato_id')->nullable()->change());
        Schema::table('partidos', function (Blueprint $table) {
            $table->date('fecha')->nullable()->change();
            $table->time('hora')->nullable()->change();
        });
        Schema::table('fases', fn (Blueprint $table) => $table->string('nombre')->nullable()->after('campeonato_id'));
    }

    public function down(): void
    {
        Schema::table('fases', fn (Blueprint $table) => $table->dropColumn('nombre'));
        Schema::dropIfExists('campeonato_equipo');
        Schema::table('campeonatos', fn (Blueprint $table) => $table->dropColumn(['fecha_limite_inscripcion', 'inscripciones_abiertas']));
    }
};
