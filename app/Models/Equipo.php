<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de Equipo.
 *
 * Representa un equipo que puede participar en varios campeonatos. Contiene
 * relaciones a:
 *  - campeonatos (relación principal mediante campeonato_equipo)
 *  - campeonato (columna antigua, conservada para compatibilidad)
 *  - delegado
 *  - jugadores
 *  - partidos como local y visitante
 */
class Equipo extends Model
{
    use HasFactory;

    // campeonato_id se conserva para instalaciones antiguas; las nuevas
    // inscripciones siempre deben escribirse también en campeonato_equipo.
    protected $fillable = ['nombre', 'campeonato_id', 'delegado_id', 'ciudad', 'escudo'];

    /** URL pública de la imagen almacenada en el disco local. */
    public function getEscudoUrlAttribute(): ?string
    {
        return $this->escudo
            ? asset('storage/'.ltrim($this->escudo, '/'))
            : null;
    }

    public function campeonato()
    {
        return $this->belongsTo(Campeonato::class);
    }

    public function campeonatos()
    {
        return $this->belongsToMany(Campeonato::class)
            ->withPivot('estado', 'motivo_retiro', 'retirado_at', 'retirado_por')
            ->withTimestamps();
    }

    public function delegado()
    {
        return $this->belongsTo(User::class, 'delegado_id');
    }

    // Relación con jugadores
    public function jugadores()
    {
        return $this->hasMany(\App\Models\Jugador::class, 'equipo_id');
    }

    // ✅ Relaciones para saber si tiene partidos antes de borrar
    public function partidosLocal()
    {
        return $this->hasMany(Partido::class, 'equipo_local_id');
    }

    public function partidosVisita()
    {
        return $this->hasMany(Partido::class, 'equipo_visita_id');
    }

    /** Nóminas que dejan constancia de una participación en un partido. */
    public function nominasPartido()
    {
        return $this->hasMany(NominaPartido::class);
    }

    /** Historial disciplinario/deportivo registrado para jugadores del equipo. */
    public function incidencias()
    {
        return $this->hasManyThrough(
            Incidencia::class,
            Jugador::class,
            'equipo_id',
            'jugador_id',
            'id',
            'id',
        );
    }
}
