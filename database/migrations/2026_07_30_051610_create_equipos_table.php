<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('ciudad')->nullable();
            $table->string('color_camiseta')->nullable();
            $table->string('escudo')->nullable(); // ✅ Campo para el escudo (opcional)
            $table->unsignedBigInteger('campeonato_id');
            $table->unsignedBigInteger('delegado_id');
            $table->timestamps();

            $table->foreign('campeonato_id')->references('id')->on('campeonatos')->onDelete('cascade');
            $table->foreign('delegado_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipos');
    }
};
