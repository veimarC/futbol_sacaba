<?php

namespace App\Models;

use App\Models\Concerns\TieneWhatsappBoliviano;
use App\Services\CloudinaryImageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class Jugador extends Model
{
    use HasFactory, TieneWhatsappBoliviano;

    protected $table = 'jugadores';

    // Estados posibles para el flujo de aprobación
    public const ESTADO_APROBADO = 'aprobado';
    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_RECHAZADO = 'rechazado';

    protected $fillable = [
        'nombre',
        'apellido',
        'dni',
        'whatsapp',
        'whatsapp_opt_in_at',
        'fecha_nacimiento',
        'sexo',
        'posicion',
        'numero',
        'foto',
        'foto_cloudinary_id',
        'equipo_id',
        'estado',
    ];

    // El contacto nunca se incluye accidentalmente al convertir el jugador a JSON.
    protected $hidden = ['whatsapp'];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'whatsapp_opt_in_at' => 'datetime',
    ];

    /** Edad actual calculada desde la fecha de nacimiento. */
    public function getEdadAttribute(): ?int
    {
        return $this->fecha_nacimiento?->age;
    }

    /**
     * Devuelve la URL pública de la foto o una ruta al icono por defecto si no existe.
     * Uso en vistas: $jugador->foto_url
     */
    public function getFotoUrlAttribute(): string
    {
        if ($this->foto_cloudinary_id) {
            return app(CloudinaryImageService::class)
                ->urlOptimizada($this->foto_cloudinary_id, 480, 480);
        }

        if ($this->foto && Storage::disk('public')->exists($this->foto)) {
            return $this->urlConBase('/storage/' . ltrim($this->foto, '/'));
        }

        return $this->urlConBase('/images/persona-anonima.svg');
    }

    private function urlConBase(string $relativePath): string
    {
        if (app()->runningInConsole()) {
            return asset(ltrim($relativePath, '/'));
        }

        $request = request();
        $basePath = trim($request->getBasePath(), '/');
        $relativePath = '/' . ltrim($relativePath, '/');

        if ($basePath !== '') {
            return $request->getSchemeAndHttpHost() . '/' . $basePath . $relativePath;
        }

        return $request->getSchemeAndHttpHost() . $relativePath;
    }

    /**
     * Relación al equipo
     */
    public function equipo()
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    public function incidencias()
    {
        return $this->hasMany(Incidencia::class, 'jugador_id');
    }

    public function participacionesPartido()
    {
        return $this->hasMany(NominaPartidoJugador::class);
    }
}
