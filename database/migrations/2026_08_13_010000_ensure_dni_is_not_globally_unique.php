<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jugadores') || ! Schema::hasColumn('jugadores', 'dni')) {
            return;
        }

        foreach (Schema::getIndexes('jugadores') as $index) {
            $columns = array_map('strtolower', $index['columns'] ?? []);
            if (($index['unique'] ?? false) && $columns === ['dni']) {
                Schema::table('jugadores', fn (Blueprint $table) => $table->dropUnique($index['name']));
            }
        }
    }

    public function down(): void
    {
        // El DNI no debe ser único globalmente: su unicidad depende del campeonato.
    }
};
