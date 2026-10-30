<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('match_whatsapp_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partido_id')->constrained('partidos')->cascadeOnDelete();
            $table->string('fingerprint', 64)->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('match_whatsapp_deliveries'); }
};
