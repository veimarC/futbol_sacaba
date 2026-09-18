<?php

namespace App\Notifications;

use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NotificacionPlataforma extends Notification
{
    use Queueable;

    public function __construct(
        private string $titulo,
        private string $mensaje,
        private string $url,
        private string $icono = 'bi-bell',
        private string $color = 'primary',
        private ?string $whatsappTemplate = null,
        private array $whatsappParameters = [],
        private bool $soloBaseDatos = false,
    ) {}

    public function via(object $notifiable): array
    {
        $canales = ['database'];

        if (! $this->soloBaseDatos
            && config('services.whatsapp.enabled')
            && filled($notifiable->whatsapp)
            && filled($notifiable->whatsapp_opt_in_at)) {
            $canales[] = WhatsAppChannel::class;
        }

        return $canales;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'titulo' => $this->titulo,
            'mensaje' => $this->mensaje,
            'url' => $this->url,
            'icono' => $this->icono,
            'color' => $this->color,
        ];
    }

    public function toWhatsApp(object $notifiable): string
    {
        return "Fútbol Sacaba ⚽\n\n{$this->titulo}\n{$this->mensaje}";
    }

    /** @return array{name: string, language: string, parameters: array<int, string>}|null */
    public function toWhatsAppTemplate(object $notifiable): ?array
    {
        if (blank($this->whatsappTemplate)) {
            return null;
        }

        return [
            'name' => $this->whatsappTemplate,
            'language' => (string) config('services.whatsapp.templates.language', 'es'),
            'parameters' => array_map(
                fn (mixed $valor): string => (string) $valor,
                $this->whatsappParameters,
            ),
        ];
    }
}
