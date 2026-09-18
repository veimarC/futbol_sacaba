<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de Fase.
 *
 * Representa una fase o etapa de un campeonato (grupo, liga, eliminatoria).
 * Usa metadata para guardar datos específicos de la fase.
 */
class Fase extends Model
{
    use HasFactory;

    protected $fillable = ['campeonato_id','nombre','tipo','orden','metadata','estado'];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function campeonato()
    {
        return $this->belongsTo(Campeonato::class);
    }

    public function partidos()
    {
        return $this->hasMany(Partido::class, 'fase_id');
    }
}
