<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('whatsapp', 16)->nullable()->after('email');
        });

        Schema::table('jugadores', function (Blueprint $table) {
            $table->string('whatsapp', 16)->nullable()->after('dni');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('whatsapp'));
        Schema::table('jugadores', fn (Blueprint $table) => $table->dropColumn('whatsapp'));
    }
};
