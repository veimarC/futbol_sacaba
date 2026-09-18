<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incidencia extends Model
{
    protected $table = 'incidencias';

    protected $fillable = [
        'partido_id',
        'jugador_id',
        'dorsal',
        'tipo',
        'minuto',
        'creado_por',
    ];

    // Tipos disponibles
    public const TIPO_GOL = 'gol';

    public const TIPO_AMARILLA = 'amarilla';

    public const TIPO_DOBLE_AMARILLA = 'doble_amarilla';

    public const TIPO_ROJA = 'roja';

    public function jugador(): BelongsTo
    {
        return $this->belongsTo(Jugador::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function partido(): BelongsTo
    {
        return $this->belongsTo(Partido::class);
    }
}
