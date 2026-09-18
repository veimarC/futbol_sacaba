<?php

namespace App\Console\Commands;

use App\Models\Partido;
use App\Services\NotificadorWhatsAppPartido;
use Carbon\Carbon;
use Illuminate\Console\Command;

/** Busca partidos próximos y envía una sola vez el recordatorio de 24 horas. */
class EnviarRecordatoriosPartidos extends Command
{
    protected $signature = 'partidos:recordar';

    protected $description = 'Envía recordatorios de WhatsApp para partidos dentro de las próximas 24 horas';

    public function handle(NotificadorWhatsAppPartido $notificador): int
    {
        $enviados = 0;

        Partido::query()
            ->where('estado', 'programado')
            ->whereNull('whatsapp_recordatorio_enviado_at')
            ->whereNotNull('fecha')
            ->whereNotNull('hora')
            ->whereBetween('fecha', [today(), today()->addDay()])
            ->with(['campeonato', 'equipoLocal.delegado', 'equipoVisita.delegado'])
            ->get()
            ->each(function (Partido $partido) use ($notificador, &$enviados) {
                $inicio = Carbon::parse($partido->fecha->format('Y-m-d').' '.$partido->hora);
                if (! $inicio->isFuture() || $inicio->gt(now()->addDay())) {
                    return;
                }

                if ($notificador->programacion($partido, true) > 0) {
                    $enviados++;
                }
            });

        $this->info("Recordatorios procesados: {$enviados}");

        return self::SUCCESS;
    }
}
