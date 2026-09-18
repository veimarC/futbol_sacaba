<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campeonatos', function (Blueprint $table) {
            $table->string('portada_cloudinary_id')->nullable()->after('portada');
        });
    }

    public function down(): void
    {
        Schema::table('campeonatos', function (Blueprint $table) {
            $table->dropColumn('portada_cloudinary_id');
        });
    }
};
