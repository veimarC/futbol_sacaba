<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de Partido.
 *
 * Guarda partidos entre equipos de un campeonato, estado y resultado.
 * Se relaciona con:
 *  - campeonato
 *  - equipo local
 *  - equipo visitante
 *  - árbitro
 */
class Partido extends Model
{
    use HasFactory;

    protected $fillable = [
        'campeonato_id',
        'fase_id',
        'ronda_number',
        'orden_en_ronda',
        'equipo_local_id',
        'equipo_visita_id',
        'fecha',
        'hora',
        'cancha',
        'arbitro_id',
        'goles_local',
        'goles_visita',
        'estado',
        'whatsapp_recordatorio_enviado_at',
    ];

    protected $casts = [
        'fecha' => 'date',
        'whatsapp_recordatorio_enviado_at' => 'datetime',
    ];

    public function campeonato()
    {
        return $this->belongsTo(Campeonato::class);
    }

    public function equipoLocal()
    {
        return $this->belongsTo(Equipo::class, 'equipo_local_id');
    }

    public function fase()
    {
        return $this->belongsTo(Fase::class);
    }

    public function equipoVisita()
    {
        return $this->belongsTo(Equipo::class, 'equipo_visita_id');
    }

    /**
     * Árbitro asignado (usuario con rol 'arbitro')
     */
    public function arbitro()
    {
        return $this->belongsTo(\App\Models\User::class, 'arbitro_id');
    }

    public function nominas()
    {
        return $this->hasMany(NominaPartido::class);
    }

    public function incidencias()
    {
        return $this->hasMany(Incidencia::class);
    }
}
