<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NominaPartidoJugador extends Model
{
    protected $table = 'nomina_partido_jugadores';
    protected $fillable = ['nomina_partido_id', 'jugador_id', 'dorsal', 'titular'];
    protected $casts = ['titular' => 'boolean'];

    public function nomina() { return $this->belongsTo(NominaPartido::class, 'nomina_partido_id'); }
    public function jugador() { return $this->belongsTo(Jugador::class); }
}
