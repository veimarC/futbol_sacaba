<?php

namespace App\Models\Concerns;

/**
 * Normaliza un celular boliviano y ofrece valores listos para formularios y enlaces.
 * En la base se guarda con el prefijo internacional, por ejemplo: +59170000000.
 */
trait TieneWhatsappBoliviano
{
    public function setWhatsappAttribute(?string $valor): void
    {
        $digitos = preg_replace('/\D+/', '', (string) $valor);

        if ($digitos === '') {
            $this->attributes['whatsapp'] = null;
            return;
        }

        if (str_starts_with($digitos, '591')) {
            $digitos = substr($digitos, 3);
        }

        $this->attributes['whatsapp'] = '+591'.$digitos;
    }

    /** Devuelve solo los ocho dígitos que el usuario debe editar. */
    public function getWhatsappNacionalAttribute(): string
    {
        return preg_replace('/^\+?591/', '', (string) $this->whatsapp);
    }

    /** Enlace que abre directamente una conversación de WhatsApp. */
    public function getWhatsappUrlAttribute(): ?string
    {
        if (! $this->whatsapp) {
            return null;
        }

        return 'https://wa.me/'.preg_replace('/\D+/', '', $this->whatsapp);
    }
}
