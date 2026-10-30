<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('campeonatos', function (Blueprint $table) {
            $table->unsignedSmallInteger('min_jugadores_partido')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('campeonatos', fn (Blueprint $table) => $table->dropColumn('min_jugadores_partido'));
    }
};
