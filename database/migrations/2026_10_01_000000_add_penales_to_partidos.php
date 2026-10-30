<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('partidos', function (Blueprint $table) {
            $table->unsignedSmallInteger('penales_local')->nullable();
            $table->unsignedSmallInteger('penales_visita')->nullable();
        });
    }
    public function down(): void {
        Schema::table('partidos', fn (Blueprint $table) => $table->dropColumn(['penales_local', 'penales_visita']));
    }
};
