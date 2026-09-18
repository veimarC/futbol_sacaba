<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campeonato_id');
            $table->string('tipo'); // ej: liga, grupos, eliminacion
            $table->integer('orden')->default(0);
            $table->json('metadata')->nullable();
            $table->enum('estado', ['pendiente','en_curso','finalizada'])->default('pendiente');
            $table->timestamps();

            $table->foreign('campeonato_id')->references('id')->on('campeonatos')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fases');
    }
};
