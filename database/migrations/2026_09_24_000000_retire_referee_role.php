<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guardar los IDs permite revertir el cambio sin perder las cuentas.
        Schema::create('retired_referee_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
        });

        DB::transaction(function () {
            $ids = DB::table('users')->where('rol', 'arbitro')->pluck('id');
            foreach ($ids as $id) {
                DB::table('retired_referee_accounts')->insert(['user_id' => $id]);
            }
            DB::table('users')->whereIn('id', $ids)->update(['rol' => 'publico']);
        });
    }

    public function down(): void
    {
        DB::table('users')
            ->whereIn('id', DB::table('retired_referee_accounts')->select('user_id'))
            ->where('rol', 'publico')
            ->update(['rol' => 'arbitro']);

        Schema::dropIfExists('retired_referee_accounts');
    }
};