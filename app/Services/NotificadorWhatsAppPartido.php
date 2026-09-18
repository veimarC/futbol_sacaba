<?php

namespace App\Services;

use App\Models\Partido;
use App\Notifications\NotificacionPlataforma;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/** Envía la programación del partido a jugadores y delegados relacionados. */
class NotificadorWhatsAppPartido
{
    public function __construct(private WhatsAppService $whatsapp) {}

    public function programacion(Partido $partido, bool $esRecordatorio = false): int
    {
        if (! config('services.whatsapp.enabled') || ! $partido->fecha || ! $partido->hora) {
            return 0;
        }

        $entregasAceptadas = 0;

        $partido->loadMissing(['campeonato', 'equipoLocal.delegado', 'equipoVisita.delegado']);
        $equipos = [
            [$partido->equipoLocal, $partido->equipoVisita],
            [$partido->equipoVisita, $partido->equipoLocal],
        ];

        foreach ($equipos as [$equipo, $rival]) {
            $parametrosBase = [
                $equipo->nombre,
                $rival->nombre,
                $partido->fecha->format('d/m/Y'),
                substr((string) $partido->hora, 0, 5),
                $partido->cancha ?: 'cancha por confirmar',
            ];

            $equipo->jugadores()
                ->where('estado', 'aprobado')
                ->whereNotNull('whatsapp')
                ->whereNotNull('whatsapp_opt_in_at')
                ->get()
                ->each(function ($jugador) use ($parametrosBase, &$entregasAceptadas) {
                    if ($this->enviarAJugador($jugador, $parametrosBase)) {
                        $entregasAceptadas++;
                    }
                });

            $delegado = $equipo->delegado;
            if ($delegado) {
                $titulo = $esRecordatorio ? 'Recordatorio de partido' : 'Partido programado';
                $delegado->notify(new NotificacionPlataforma(
                    $titulo,
                    "{$equipo->nombre} jugará contra {$rival->nombre} el {$parametrosBase[2]} a las {$parametrosBase[3]}. Prepara la nómina.",
                    route('campeonatos.show', $partido->campeonato),
                    'bi-calendar-event',
                    'info',
                    whatsappTemplate: config('services.whatsapp.templates.delegate_match'),
                    whatsappParameters: array_merge([$delegado->name], $parametrosBase),
                    soloBaseDatos: true,
                ));

                if ($this->enviarADelegado($delegado, $parametrosBase)) {
                    $entregasAceptadas++;
                }
            }
        }

        if ($entregasAceptadas > 0 && ($esRecordatorio || $this->iniciaDentroDeUnDia($partido))) {
            $partido->update(['whatsapp_recordatorio_enviado_at' => now()]);
        }

        return $entregasAceptadas;
    }

    /** @param array<int, string> $parametrosBase */
    private function enviarAJugador(object $jugador, array $parametrosBase): bool
    {
        try {
            $this->whatsapp->enviarPlantilla(
                $jugador->whatsapp,
                (string) config('services.whatsapp.templates.player_match'),
                array_merge([$jugador->nombre], $parametrosBase),
                (string) config('services.whatsapp.templates.language', 'es'),
            );

            return true;
        } catch (\Throwable $error) {
            // Un fallo externo de Meta no debe impedir programar el partido.
            Log::warning('No se pudo avisar por WhatsApp el partido al jugador.', [
                'jugador_id' => $jugador->id,
                'error' => $error->getMessage(),
            ]);

            return false;
        }
    }

    /** @param array<int, string> $parametrosBase */
    private function enviarADelegado(object $delegado, array $parametrosBase): bool
    {
        if (blank($delegado->whatsapp) || blank($delegado->whatsapp_opt_in_at)) {
            return false;
        }

        try {
            $this->whatsapp->enviarPlantilla(
                $delegado->whatsapp,
                (string) config('services.whatsapp.templates.delegate_match'),
                array_merge([$delegado->name], $parametrosBase),
                (string) config('services.whatsapp.templates.language', 'es'),
            );

            return true;
        } catch (\Throwable $error) {
            Log::warning('No se pudo avisar por WhatsApp el partido al delegado.', [
                'usuario_id' => $delegado->id,
                'error' => $error->getMessage(),
            ]);

            return false;
        }
    }

    private function iniciaDentroDeUnDia(Partido $partido): bool
    {
        $inicio = Carbon::parse($partido->fecha->format('Y-m-d').' '.$partido->hora);

        return $inicio->isFuture() && $inicio->lte(now()->addDay());
    }
}
