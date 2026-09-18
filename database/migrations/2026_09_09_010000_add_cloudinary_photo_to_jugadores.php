<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            $table->string('foto_cloudinary_id')->nullable()->after('foto');
        });
    }

    public function down(): void
    {
        Schema::table('jugadores', fn (Blueprint $table) => $table->dropColumn('foto_cloudinary_id'));
    }
};
