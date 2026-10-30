<?php

namespace App\Notifications\Channels;

use App\Services\WhatsAppService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/** Envía el aviso por WhatsApp sin interrumpir la operación principal. */
class WhatsAppChannel
{
    public function __construct(private WhatsAppService $whatsapp) {}

    public function send(object $notifiable, Notification $notification): ?array
    {
        if (! config('services.whatsapp.enabled')
            || blank($notifiable->whatsapp)
            || blank($notifiable->whatsapp_opt_in_at)) {
            return null;
        }

        try {
            if (method_exists($notification, 'toWhatsAppTemplate')) {
                $plantilla = $notification->toWhatsAppTemplate($notifiable);
                if ($plantilla !== null) {
                    return $this->whatsapp->enviarPlantilla(
                        $notifiable->whatsapp,
                        $plantilla['name'],
                        $plantilla['parameters'] ?? [],
                        $plantilla['language'] ?? 'es',
                    );
                }
            }

            if (! method_exists($notification, 'toWhatsApp')) {
                return null;
            }

            return $this->whatsapp->enviarTexto(
                $notifiable->whatsapp,
                $notification->toWhatsApp($notifiable),
            );
        } catch (\Throwable $error) {
            Log::warning('No se pudo enviar una notificación por WhatsApp.', [
                'usuario_id' => method_exists($notifiable, 'getKey') ? $notifiable->getKey() : null,
                'notificacion' => $notification::class,
                'error' => $error->getMessage(),
            ]);

            return null;
        }
    }
}
