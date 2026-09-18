<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convierte los formatos antiguos a las cuatro opciones actuales sin
     * borrar partidos, fases, equipos ni resultados ya registrados.
     */
    public function up(): void
    {
        DB::table('campeonatos')
            ->whereIn('formato', [
                'liga_ida_vuelta',
                'liga_semifinal_final',
                'liga_cuadrangular_final',
            ])
            ->orderBy('id')
            ->get()
            ->each(function ($campeonato): void {
                $config = json_decode($campeonato->formato_config ?: '{}', true) ?: [];
                $config['legacy_format'] = $campeonato->formato;

                if ($campeonato->formato === 'liga_ida_vuelta') {
                    $nuevoFormato = 'liga';
                    $config['league_rounds'] = 2;
                } else {
                    // Las variantes antiguas combinaban una primera fase con
                    // una fase final. En el formulario nuevo pertenecen a Copa.
                    $nuevoFormato = 'grupos_eliminacion';
                }

                DB::table('campeonatos')->where('id', $campeonato->id)->update([
                    'formato' => $nuevoFormato,
                    'formato_config' => json_encode($config, JSON_UNESCAPED_UNICODE),
                ]);
            });
    }

    /** Restaura el nombre anterior si se revierte esta migración. */
    public function down(): void
    {
        DB::table('campeonatos')
            ->whereNotNull('formato_config')
            ->orderBy('id')
            ->get()
            ->each(function ($campeonato): void {
                $config = json_decode($campeonato->formato_config ?: '{}', true) ?: [];
                $formatoAnterior = $config['legacy_format'] ?? null;

                if (! in_array($formatoAnterior, [
                    'liga_ida_vuelta',
                    'liga_semifinal_final',
                    'liga_cuadrangular_final',
                ], true)) {
                    return;
                }

                unset($config['legacy_format']);
                DB::table('campeonatos')->where('id', $campeonato->id)->update([
                    'formato' => $formatoAnterior,
                    'formato_config' => $config === []
                        ? null
                        : json_encode($config, JSON_UNESCAPED_UNICODE),
                ]);
            });
    }
};
