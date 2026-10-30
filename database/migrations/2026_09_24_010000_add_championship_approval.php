<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('campeonatos', function (Blueprint $table) {
            $table->string('aprobacion_estado')->default('borrador')->index();
            $table->text('aprobacion_motivo')->nullable();
            $table->timestamp('solicitado_at')->nullable();
            $table->timestamp('revisado_at')->nullable();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
        });
        // Los campeonatos existentes mantienen su funcionamiento.
        DB::table('campeonatos')->update(['aprobacion_estado' => 'aprobado']);
    }

    public function down(): void
    {
        Schema::table('campeonatos', function (Blueprint $table) {
            $table->dropForeign(['revisado_por']);
            $table->dropIndex(['aprobacion_estado']);
            $table->dropColumn(['aprobacion_estado', 'aprobacion_motivo', 'solicitado_at', 'revisado_at', 'revisado_por']);
        });
    }
};
