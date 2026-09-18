<?php

namespace App\Console\Commands;

use App\Services\CloudinaryImageService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Throwable;

/** Permite comprobar manualmente la subida real sin pasar por un formulario. */
class ProbarCloudinary extends Command
{
    protected $signature = 'cloudinary:probar
        {archivo=public/images/persona-anonima.svg : Ruta de una imagen dentro del proyecto}
        {--eliminar : Elimina de Cloudinary la imagen inmediatamente después de comprobarla}';

    protected $description = 'Sube una imagen de prueba a Cloudinary y muestra sus resultados';

    public function handle(CloudinaryImageService $imagenes): int
    {
        $ruta = $this->resolverRuta((string) $this->argument('archivo'));
        if ($ruta === null) {
            return self::FAILURE;
        }

        $mime = mime_content_type($ruta) ?: '';
        if (! Str::startsWith($mime, 'image/')) {
            $this->error('El archivo indicado no es una imagen válida.');

            return self::FAILURE;
        }

        try {
            $archivo = new UploadedFile($ruta, basename($ruta), $mime, null, true);
            $resultado = $imagenes->subir($archivo, 'pruebas-manuales');
            $urlOptimizada = $imagenes->urlOptimizada($resultado['public_id'], 240, 240);

            $this->info('Cloudinary recibió y procesó correctamente la imagen.');
            $this->table(['Dato', 'Resultado'], [
                ['Public ID', $resultado['public_id']],
                ['Formato original', $resultado['format']],
                ['Dimensiones', "{$resultado['width']} × {$resultado['height']} px"],
                ['Peso', "{$resultado['bytes']} bytes"],
                ['URL original', $resultado['secure_url']],
                ['URL optimizada', $urlOptimizada],
            ]);

            if ($this->option('eliminar')) {
                $imagenes->eliminar($resultado['public_id']);
                $this->warn('La imagen de prueba fue eliminada de Cloudinary.');
            } else {
                $this->line('Puedes verla en Media Library > futbol-sacaba > pruebas-manuales.');
            }

            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error('Cloudinary rechazó la prueba: '.$error->getMessage());

            return self::FAILURE;
        }
    }

    private function resolverRuta(string $entrada): ?string
    {
        $ruta = preg_match('/^(?:[a-zA-Z]:[\\\\\/]|[\\\\\/])/', $entrada)
            ? $entrada
            : base_path($entrada);
        $rutaReal = realpath($ruta);
        $raiz = realpath(base_path());

        if ($rutaReal === false || ! is_file($rutaReal)) {
            $this->error('No se encontró el archivo: '.$entrada);

            return null;
        }

        if ($raiz === false || ! Str::startsWith(
            Str::lower(str_replace('\\', '/', $rutaReal)),
            Str::lower(str_replace('\\', '/', $raiz)).'/',
        )) {
            $this->error('Por seguridad, la imagen debe estar dentro del proyecto.');

            return null;
        }

        return $rutaReal;
    }
}
