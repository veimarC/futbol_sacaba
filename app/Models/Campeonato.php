<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de Campeonato.
 *
 * Guarda información general del torneo y permite configurar el formato
 * en la columna `formato`, con detalles en `formato_config`.
 */
class Campeonato extends Model
{
    public function tieneTablaPosiciones(): bool
    {
        return in_array($this->formato, ['liga', 'liga_ida_vuelta', 'grupos_eliminacion', 'series_interseries', 'liga_semifinal_final'], true);
    }

    use HasFactory;

    protected $attributes = ['aprobacion_estado' => 'borrador'];

    public function scopeAprobados($query)
    {
        return $query->where($this->qualifyColumn('aprobacion_estado'), 'aprobado');
    }

    public function getAprobacionLabelAttribute(): string
    {
        return [
            'borrador' => 'Borrador',
            'pendiente' => 'Pendiente de aprobación',
            'devuelto' => 'Devuelto para corregir',
            'aprobado' => 'Aprobado',
        ][$this->aprobacion_estado] ?? 'Borrador';
    }

    // Campos que se pueden completar en creación/actualización
    protected $fillable = [
        'nombre', 'descripcion', 'reglamento_interno', 'portada', 'fecha_inicio', 'fecha_fin', 'estado',
        'usuario_creador', 'formato', 'formato_config',
        'fecha_limite_inscripcion', 'inscripciones_abiertas',
        'modalidad', 'jugadores_cancha', 'min_jugadores_partido', 'min_jugadores_equipo', 'max_jugadores_equipo',
        'categoria_edad', 'edad_min', 'edad_max', 'sexo',
    ];

    // El campo formato_config se guarda como JSON en la base de datos y se trabaja como array
    protected $casts = [
        'formato_config' => 'array',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'fecha_limite_inscripcion' => 'date',
        'inscripciones_abiertas' => 'boolean',
    ];

    public function getMinimoParaJugarAttribute(): int
    {
        return (int) ($this->min_jugadores_partido ?? $this->jugadores_cancha);
    }

    // Formatos que se muestran al usuario. Las variantes (una/dos vueltas)
    // se configuran después y ya no aparecen como formatos separados.
    public static $formatos = [
        'liga' => 'Liga — Todos contra todos',
        'grupos_eliminacion' => 'Copa — Grupos y eliminatorias',
        'eliminacion' => 'Relámpago — Eliminación directa',
    ];

    /** URL pública de la imagen almacenada en el disco local. */
    public function getPortadaUrlAttribute(): ?string
    {
        return $this->portada
            ? asset('storage/'.ltrim($this->portada, '/'))
            : null;
    }

    public static array $categoriasEdad = [
        'sub_7' => 'Sub-7', 'sub_9' => 'Sub-9', 'sub_11' => 'Sub-11',
        'sub_13' => 'Sub-13', 'sub_15' => 'Sub-15', 'sub_17' => 'Sub-17',
        'sub_20' => 'Sub-20', 'libre' => 'Libre', 'mas_30' => '+30',
        'mas_35' => '+35', 'mas_40' => '+40', 'mas_45' => '+45', 'mas_50' => '+50',
    ];

    public static array $sexos = [
        'masculino' => 'Masculino', 'femenino' => 'Femenino', 'mixto' => 'Mixto',
    ];

    // Obtener etiqueta legible del formato
    public function getFormatoLabelAttribute()
    {
        // Los nombres antiguos siguen siendo legibles para campeonatos que ya
        // estaban guardados antes de simplificar el formulario.
        $formatosAnteriores = [
            'series_interseries' => 'Series — Con clásicos interseries (anterior)',
            'liga_ida_vuelta' => 'Liga — Ida y vuelta',
            'liga_semifinal_final' => 'Liga + semifinales/final (anterior)',
            'liga_cuadrangular_final' => 'Liga + cuadrangular final (anterior)',
        ];

        return self::$formatos[$this->formato]
            ?? $formatosAnteriores[$this->formato]
            ?? ucfirst(str_replace('_',' ',$this->formato));
    }

    public function getCategoriaEdadLabelAttribute(): string
    {
        return self::$categoriasEdad[$this->categoria_edad] ?? $this->categoria_edad;
    }

    public function getSexoLabelAttribute(): string
    {
        return self::$sexos[$this->sexo] ?? ucfirst($this->sexo);
    }

    /**
     * Estado deportivo calculado al consultar la página.
     *
     * Las fechas actualizan el estado sin cron ni cambios manuales. Un cierre
     * explícito conserva prioridad para permitir finalizar anticipadamente.
     */
    public function getEstadoActualAttribute(): string
    {
        if ($this->estado === 'finalizado') {
            return 'finalizado';
        }

        $hoy = today();

        if ($this->fecha_fin && $this->fecha_fin->lt($hoy)) {
            return 'finalizado';
        }

        if ($this->fecha_inicio && $this->fecha_inicio->lte($hoy)) {
            return 'en_curso';
        }

        return 'pendiente';
    }

    public function getEstadoActualLabelAttribute(): string
    {
        return [
            'pendiente' => 'Próximo',
            'en_curso' => 'En curso',
            'finalizado' => 'Finalizado',
        ][$this->estado_actual];
    }

    /** Excluye campeonatos finalizados manualmente o por fecha. */
    public function scopeNoFinalizadosActuales($query)
    {
        // Se califican las columnas porque la tabla pivote campeonato_equipo
        // también posee un campo llamado "estado".
        $estado = $this->qualifyColumn('estado');
        $fechaFin = $this->qualifyColumn('fecha_fin');

        return $query
            ->where($estado, '<>', 'finalizado')
            ->where(fn ($q) => $q
                ->whereNull($fechaFin)
                ->orWhereDate($fechaFin, '>=', today()));
    }

    /** Ordena primero los campeonatos en curso, luego los próximos y finalizados. */
    public function scopeOrdenEstadoActual($query)
    {
        $hoy = today()->toDateString();
        $tabla = $this->getTable();

        return $query->orderByRaw(
            "CASE
                WHEN {$tabla}.estado = 'finalizado'
                    OR ({$tabla}.fecha_fin IS NOT NULL AND {$tabla}.fecha_fin < ?) THEN 2
                WHEN {$tabla}.fecha_inicio <= ? THEN 0
                ELSE 1
            END",
            [$hoy, $hoy]
        );
    }

    // ✅ Relación con Equipos
    /** Usuario organizador que creó y administra el campeonato. */
    public function organizador()
    {
        return $this->belongsTo(User::class, 'usuario_creador');
    }

    public function equipos()
    {
        return $this->belongsToMany(Equipo::class)
            ->withPivot('estado', 'motivo_retiro', 'retirado_at', 'retirado_por')
            ->withTimestamps();
    }

    // ✅ Relación con Fases (para agrupar fases/etapas del campeonato)
    public function fases()
    {
        return $this->hasMany(Fase::class);
    }

    // ✅ Relación con Partidos
    public function partidos()
    {
        return $this->hasMany(Partido::class);
    }
}
