<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Cliente pequeño para enviar mensajes mediante WhatsApp Cloud API.
 *
 * Mantiene fuera de los controladores la URL, autenticación y formato que
 * exige Meta. El token se obtiene siempre desde config/services.php.
 */
class WhatsAppService
{
    public function enviarTexto(string $destinatario, string $mensaje): array
    {
        $this->comprobarConfiguracion();
        $numero = $this->normalizarDestinatario($destinatario);

        $respuesta = Http::withToken((string) config('services.whatsapp.access_token'))
            ->acceptJson()
            ->timeout(15)
            ->post($this->urlMensajes(), [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $numero,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $mensaje,
                ],
            ]);

        $this->comprobarRespuesta($respuesta);

        return $respuesta->json();
    }

    /**
     * Envía una plantilla aprobada. Este formato permite iniciar avisos de
     * utilidad sin depender de una conversación abierta durante 24 horas.
     *
     * @param  array<int, string>  $parametros
     */
    public function enviarPlantilla(
        string $destinatario,
        string $plantilla,
        array $parametros = [],
        string $idioma = 'es',
    ): array {
        $this->comprobarConfiguracion();

        $contenido = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizarDestinatario($destinatario),
            'type' => 'template',
            'template' => [
                'name' => $plantilla,
                'language' => ['code' => $idioma],
            ],
        ];

        if ($parametros !== []) {
            $contenido['template']['components'] = [[
                'type' => 'body',
                'parameters' => array_map(
                    fn (string $valor): array => ['type' => 'text', 'text' => $valor],
                    $parametros,
                ),
            ]];
        }

        $respuesta = Http::withToken((string) config('services.whatsapp.access_token'))
            ->acceptJson()
            ->timeout(15)
            ->post($this->urlMensajes(), $contenido);

        $this->comprobarRespuesta($respuesta);

        return $respuesta->json();
    }

    private function comprobarConfiguracion(): void
    {
        if (! config('services.whatsapp.enabled')) {
            throw new RuntimeException('La integración de WhatsApp está desactivada.');
        }

        foreach (['version', 'phone_number_id', 'access_token'] as $campo) {
            if (blank(config("services.whatsapp.{$campo}"))) {
                throw new RuntimeException("Falta configurar services.whatsapp.{$campo}.");
            }
        }
    }

    private function normalizarDestinatario(string $destinatario): string
    {
        $digitos = preg_replace('/\D+/', '', $destinatario);
        if (! str_starts_with($digitos, '591')) {
            $digitos = '591'.$digitos;
        }

        if (! preg_match('/^591[67][0-9]{7}$/', $digitos)) {
            throw new InvalidArgumentException('El destinatario no es un celular boliviano válido.');
        }

        return $digitos;
    }

    private function urlMensajes(): string
    {
        $version = trim((string) config('services.whatsapp.version'), '/');
        $phoneNumberId = config('services.whatsapp.phone_number_id');

        return "https://graph.facebook.com/{$version}/{$phoneNumberId}/messages";
    }

    private function comprobarRespuesta(Response $respuesta): void
    {
        if ($respuesta->successful()) {
            return;
        }

        $mensaje = $respuesta->json('error.message') ?: 'Meta rechazó el envío del mensaje.';
        throw new RuntimeException($mensaje);
    }
}
