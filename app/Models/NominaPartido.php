<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NominaPartido extends Model
{
    protected $table = 'nominas_partido';

    protected $fillable = [
        'partido_id', 'equipo_id', 'estado', 'motivo_respuesta',
        'enviado_at', 'aprobado_at', 'aprobado_por',
    ];

    protected $casts = ['enviado_at' => 'datetime', 'aprobado_at' => 'datetime'];

    public function partido() { return $this->belongsTo(Partido::class); }
    public function equipo() { return $this->belongsTo(Equipo::class); }
    public function jugadores() { return $this->hasMany(NominaPartidoJugador::class)->orderBy('dorsal'); }
    public function aprobador() { return $this->belongsTo(User::class, 'aprobado_por'); }
}
