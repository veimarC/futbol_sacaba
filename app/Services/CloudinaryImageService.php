<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Transformation\Format;
use Cloudinary\Transformation\Quality;
use Cloudinary\Transformation\Resize;
use Cloudinary\Transformation\Transformation;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use RuntimeException;

/** Centraliza la subida, eliminación y entrega optimizada de imágenes. */
class CloudinaryImageService
{
    private ?Cloudinary $cloudinary;

    public function __construct(?Cloudinary $cloudinary = null)
    {
        $this->cloudinary = $cloudinary;
    }

    /**
     * @return array{public_id: string, secure_url: string, width: int, height: int, bytes: int, format: string}
     */
    public function subir(UploadedFile $archivo, string $carpeta): array
    {
        return $this->subirDesdeRuta($archivo->getRealPath(), $carpeta);
    }

    /**
     * Sube una imagen existente. El nombre estable permite reanudar migraciones
     * sin crear copias duplicadas en Cloudinary.
     *
     * @return array{public_id: string, secure_url: string, width: int, height: int, bytes: int, format: string}
     */
    public function subirDesdeRuta(string $ruta, string $carpeta, ?string $nombreEstable = null): array
    {
        if (! is_file($ruta) || ! is_readable($ruta)) {
            throw new InvalidArgumentException('La imagen local no existe o no se puede leer.');
        }

        $carpeta = $this->normalizarCarpeta($carpeta);
        $carpetaCompleta = "futbol-sacaba/{$carpeta}";
        $opciones = [
            'asset_folder' => $carpetaCompleta,
            'public_id_prefix' => $carpetaCompleta,
            'resource_type' => 'image',
            'unique_filename' => true,
            'overwrite' => false,
        ];

        if ($nombreEstable !== null) {
            if (! preg_match('/^[a-z0-9_-]+$/i', $nombreEstable)) {
                throw new InvalidArgumentException('El nombre estable de Cloudinary no es válido.');
            }

            $opciones = [
                'public_id' => "{$carpetaCompleta}/{$nombreEstable}",
                'asset_folder' => $carpetaCompleta,
                'resource_type' => 'image',
                'overwrite' => true,
                'invalidate' => true,
            ];
        }

        $respuesta = $this->cliente()->uploadApi()->upload($ruta, $opciones);

        return [
            'public_id' => (string) $respuesta['public_id'],
            'secure_url' => (string) $respuesta['secure_url'],
            'width' => (int) ($respuesta['width'] ?? 0),
            'height' => (int) ($respuesta['height'] ?? 0),
            'bytes' => (int) ($respuesta['bytes'] ?? 0),
            'format' => (string) ($respuesta['format'] ?? ''),
        ];
    }

    public function eliminar(?string $publicId): void
    {
        if (blank($publicId)) {
            return;
        }

        $this->cliente()->uploadApi()->destroy($publicId, [
            'resource_type' => 'image',
            'invalidate' => true,
        ]);
    }

    /** Genera una URL CDN con formato y calidad automáticos. */
    public function urlOptimizada(string $publicId, int $ancho, int $alto): string
    {
        if ($ancho < 1 || $alto < 1) {
            throw new InvalidArgumentException('Las dimensiones deben ser mayores que cero.');
        }

        $transformacion = (new Transformation())
            ->resize(Resize::fill($ancho, $alto))
            ->format(Format::auto())
            ->quality(Quality::auto());

        return $this->cliente()->image($publicId)->toUrl($transformacion);
    }

    private function cliente(): Cloudinary
    {
        return $this->cloudinary ??= new Cloudinary($this->configuracion());
    }

    /** @return array{cloud: array{cloud_name: string, api_key: string, api_secret: string}, url: array{secure: bool}} */
    private function configuracion(): array
    {
        $config = config('services.cloudinary');

        foreach (['cloud_name', 'api_key', 'api_secret'] as $campo) {
            if (blank($config[$campo] ?? null)) {
                throw new RuntimeException("Falta configurar services.cloudinary.{$campo}.");
            }
        }

        return [
            'cloud' => [
                'cloud_name' => (string) $config['cloud_name'],
                'api_key' => (string) $config['api_key'],
                'api_secret' => (string) $config['api_secret'],
            ],
            'url' => [
                'secure' => (bool) ($config['secure'] ?? true),
            ],
        ];
    }

    private function normalizarCarpeta(string $carpeta): string
    {
        $carpeta = trim($carpeta, '/');

        if ($carpeta === '' || ! preg_match('/^[a-z0-9_\/-]+$/i', $carpeta)) {
            throw new InvalidArgumentException('La carpeta de Cloudinary no es válida.');
        }

        return $carpeta;
    }
}
