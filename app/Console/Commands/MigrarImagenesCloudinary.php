<?php

namespace App\Console\Commands;

use App\Models\Campeonato;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Services\CloudinaryImageService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Migra imágenes locales de forma reanudable y conserva los originales. */
class MigrarImagenesCloudinary extends Command
{
    protected $signature = 'cloudinary:migrar-imagenes
        {--solo= : Categoría: jugadores, escudos o portadas}
        {--simular : Solo cuenta y valida los archivos, sin subirlos}
        {--reorganizar : Coloca en carpetas visibles las imágenes ya migradas}';

    protected $description = 'Migra a Cloudinary las imágenes locales registradas en la plataforma';

    private int $migradas = 0;

    private int $omitidas = 0;

    private int $faltantes = 0;

    private int $errores = 0;

    public function handle(CloudinaryImageService $imagenes): int
    {
        $categorias = [
            'jugadores' => [Jugador::class, 'foto', 'foto_cloudinary_id', 'jugadores', 'jugador'],
            'escudos' => [Equipo::class, 'escudo', 'escudo_cloudinary_id', 'escudos', 'equipo'],
            'portadas' => [Campeonato::class, 'portada', 'portada_cloudinary_id', 'campeonatos', 'campeonato'],
        ];

        $solo = $this->option('solo');
        if ($solo && ! isset($categorias[$solo])) {
            $this->error('Categoría inválida. Usa: jugadores, escudos o portadas.');

            return self::INVALID;
        }

        $seleccionadas = $solo ? [$solo => $categorias[$solo]] : $categorias;
        $this->info($this->option('simular')
            ? 'Simulación: no se modificará Cloudinary ni la base de datos.'
            : ($this->option('reorganizar')
                ? 'Reorganización iniciada. Se conservarán los public_id y archivos locales.'
                : 'Migración iniciada. Los archivos locales se conservarán como respaldo.'));

        foreach ($seleccionadas as $nombre => $configuracion) {
            $this->migrarCategoria($nombre, $configuracion, $imagenes);
        }

        $this->newLine();
        $this->table(['Resultado', 'Cantidad'], [
            ['Migradas', $this->migradas],
            ['Ya migradas', $this->omitidas],
            ['Archivo local faltante', $this->faltantes],
            ['Errores', $this->errores],
        ]);

        return $this->errores > 0 || $this->faltantes > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @param array{class-string<Model>, string, string, string, string} $configuracion */
    private function migrarCategoria(string $nombre, array $configuracion, CloudinaryImageService $imagenes): void
    {
        [$modelo, $campoLocal, $campoCloudinary, $carpeta, $prefijo] = $configuracion;
        $total = $modelo::query()->whereNotNull($campoLocal)->where($campoLocal, '<>', '')->count();
        $consulta = $modelo::query()
            ->whereNotNull($campoLocal)
            ->where($campoLocal, '<>', '');
        $this->option('reorganizar')
            ? $consulta->whereNotNull($campoCloudinary)
            : $consulta->whereNull($campoCloudinary);
        $pendientes = (clone $consulta)->count();

        $this->newLine();
        $this->line(sprintf('<info>%s</info>: %d registradas, %d pendientes.', ucfirst($nombre), $total, $pendientes));
        $this->omitidas += $total - $pendientes;

        $consulta
            ->orderBy('id')
            ->chunkById(50, function ($registros) use ($imagenes, $campoLocal, $campoCloudinary, $carpeta, $prefijo) {
                foreach ($registros as $registro) {
                    $rutaRelativa = (string) $registro->getAttribute($campoLocal);
                    if (! Storage::disk('public')->exists($rutaRelativa)) {
                        $this->warn("  [FALTA] ID {$registro->getKey()}: {$rutaRelativa}");
                        $this->faltantes++;
                        continue;
                    }

                    if ($this->option('simular')) {
                        $this->line("  [PENDIENTE] ID {$registro->getKey()}: {$rutaRelativa}");
                        $this->migradas++;
                        continue;
                    }

                    $publicId = null;
                    try {
                        $nombreEstable = $this->option('reorganizar')
                            ? basename(str_replace('\\', '/', (string) $registro->getAttribute($campoCloudinary)))
                            : "{$prefijo}-{$registro->getKey()}";
                        $resultado = $imagenes->subirDesdeRuta(
                            Storage::disk('public')->path($rutaRelativa),
                            $carpeta,
                            $nombreEstable,
                        );
                        $publicId = $resultado['public_id'];
                        $registro->update([$campoCloudinary => $publicId]);
                        $this->info("  [OK] ID {$registro->getKey()} → {$publicId}");
                        $this->migradas++;
                    } catch (Throwable $error) {
                        if ($publicId) {
                            try {
                                $imagenes->eliminar($publicId);
                            } catch (Throwable) {
                                // La siguiente ejecución reutilizará el mismo public_id estable.
                            }
                        }

                        $this->error("  [ERROR] ID {$registro->getKey()}: {$error->getMessage()}");
                        $this->errores++;
                    }
                }
            });
    }
}
