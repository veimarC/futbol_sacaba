<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Guarda imágenes en el disco público sin depender de servicios externos. */
class LocalImageService
{
    public function subir(UploadedFile $archivo, string $carpeta): string
    {
        $ruta = $archivo->store($carpeta, 'public');

        if ($ruta === false) {
            throw new RuntimeException('No se pudo guardar la imagen en el almacenamiento local.');
        }

        return $ruta;
    }

    public function eliminar(?string $ruta): void
    {
        if ($ruta && ! Storage::disk('public')->delete($ruta)) {
            throw new RuntimeException('No se pudo eliminar la imagen local.');
        }
    }
}
