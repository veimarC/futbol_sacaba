<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('jugadores', 'posicion')) {
            Schema::table('jugadores', fn (Blueprint $table) => $table->dropColumn('posicion'));
        }
    }

    public function down(): void
    {
        // Solo restaura la estructura; los valores eliminados no se recuperan.
        if (! Schema::hasColumn('jugadores', 'posicion')) {
            Schema::table('jugadores', fn (Blueprint $table) => $table->string('posicion')->nullable());
        }
    }
};