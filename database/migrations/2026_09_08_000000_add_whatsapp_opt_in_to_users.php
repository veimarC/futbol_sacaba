<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Registra cuándo el usuario autorizó expresamente los avisos.
            $table->timestamp('whatsapp_opt_in_at')->nullable()->after('whatsapp');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('whatsapp_opt_in_at'));
    }
};
