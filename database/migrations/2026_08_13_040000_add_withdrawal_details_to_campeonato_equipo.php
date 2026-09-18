<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campeonato_equipo', function (Blueprint $table) {
            $table->text('motivo_retiro')->nullable()->after('estado');
            $table->timestamp('retirado_at')->nullable()->after('motivo_retiro');
            $table->foreignId('retirado_por')->nullable()->after('retirado_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campeonato_equipo', function (Blueprint $table) {
            $table->dropConstrainedForeignId('retirado_por');
            $table->dropColumn(['motivo_retiro', 'retirado_at']);
        });
    }
};
