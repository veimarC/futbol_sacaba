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
        'goles_local',
        'goles_visita',
        'penales_local',
        'penales_visita',
        'estado',
        'wo_motivo',
        'wo_equipo_perdedor_id',
        'wo_registrado_por',
        'wo_registrado_at',
        'whatsapp_recordatorio_enviado_at',
    ];

    protected $casts = [
        'penales_local' => 'integer',
        'penales_visita' => 'integer',
        'fecha' => 'date',
        'wo_registrado_at' => 'datetime',
        'whatsapp_recordatorio_enviado_at' => 'datetime',
    ];

    public function campeonato()
    {
        return $this->belongsTo(Campeonato::class);
    }

    /** Número visible entre los partidos existentes de este campeonato, por orden de creación. */
    public function getNumeroCampeonatoAttribute(): int
    {
        return static::where('campeonato_id', $this->campeonato_id)
            ->where('id', '<=', $this->id)->count();
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

    public function nominas()
    {
        return $this->hasMany(NominaPartido::class);
    }

    public function incidencias()
    {
        return $this->hasMany(Incidencia::class);
    }
}
