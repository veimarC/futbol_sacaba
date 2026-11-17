<?php

namespace App\Services;

use App\Models\Campeonato;
use App\Models\Equipo;
use App\Models\Fase;
use App\Models\Partido;
use App\Services\FormatGenerators\LigaGenerator;
use Illuminate\Validation\ValidationException;

class InscripcionTardiaService
{
    public function motivoCierre(Campeonato $campeonato): ?string
    {
        if ($campeonato->aprobacion_estado !== 'aprobado' || $campeonato->estado_actual === 'finalizado') {
            return 'El campeonato no está disponible para inscripciones.';
        }
        if (! $campeonato->inscripciones_abiertas) return data_get($campeonato->formato_config, 'inscripcion_tardia.motivo_cierre', 'Las inscripciones están cerradas.');
        if ($campeonato->fecha_limite_inscripcion?->copy()->endOfDay()->isPast()) return 'Venció la fecha de inscripción.';
        if (! $campeonato->fases()->exists() && ! $campeonato->partidos()->exists()) return null;
        $limite = (int) data_get($campeonato->formato_config, 'inscripcion_tardia.jornada_limite', 0);
        if (! $limite || ! in_array($campeonato->formato, ['liga', 'liga_ida_vuelta', 'grupos_eliminacion'])) {
            return 'El fixture ya está cerrado y no tiene cupos reservados.';
        }
        if ($campeonato->fases()->where('tipo', 'eliminacion')->exists()) return 'La fase eliminatoria ya fue generada.';
        $posteriores = $campeonato->partidos()->where('ronda_number', '>', $limite);
        if ($posteriores->where(function ($q) {
            $q->where('estado', 'jugado')->orWhereHas('incidencias')
                ->orWhereHas('nominas', fn ($n) => $n->where('estado', 'aprobada'))
                ->orWhere(function ($fecha) {
                    $fecha->whereDate('fecha', '<', today())->orWhere(function ($hoy) {
                        $hoy->whereDate('fecha', today())->where(function ($hora) {
                            $hora->whereNull('hora')->orWhere('hora', '<=', now()->format('H:i:s'));
                        });
                    });
                });
        })->exists()) return "Terminó el plazo de inscripción hasta la jornada {$limite}.";
        if ($this->cuposDisponibles($campeonato) === 0) return 'Ya se ocuparon todos los cupos reservados.';
        return null;
    }

    public function cuposDisponibles(Campeonato $campeonato): int
    {
        return $campeonato->fases()->whereIn('tipo', ['liga', 'grupo'])->get()->sum(
            fn ($fase) => count(array_filter($fase->metadata['cupos'] ?? [], fn ($id) => $id === null))
        );
    }

    /** Persistir el cierre al registrar actividad impide reabrirlo al corregir una nómina o fecha. */
    public function cerrarSiVencio(Campeonato $campeonato): void
    {
        if (! data_get($campeonato->formato_config, 'inscripcion_tardia') || ! $campeonato->inscripciones_abiertas) return;
        if ($motivo = $this->motivoCierre($campeonato)) {
            $config = $campeonato->formato_config;
            $config['inscripcion_tardia']['motivo_cierre'] = $motivo;
            $campeonato->update(['inscripciones_abiertas' => false, 'formato_config' => $config]);
        }
    }

    /** Guarda el calendario de cupos sin crear equipos ficticios ni partidos sin rivales. */
    public function generar(Campeonato $campeonato, array $config, int $reservas, int $limite): void
    {
        $equipos = $campeonato->equipos()->wherePivot('estado', 'aprobado')->orderBy('nombre')->pluck('equipos.id')->all();
        $cantidadGrupos = $campeonato->formato === 'grupos_eliminacion' ? $config['number_of_groups'] : 1;
        if ($reservas > count($equipos)) $this->error('Los cupos reservados no pueden superar la cantidad de equipos aprobados.');
        if ($cantidadGrupos > 1) shuffle($equipos);
        $grupos = array_fill(0, $cantidadGrupos, []);
        foreach ($equipos as $i => $id) $grupos[$i % $cantidadGrupos][] = $id;
        for ($i = 0; $i < $reservas; $i++) {
            $menor = array_search(min(array_map('count', $grupos)), array_map('count', $grupos));
            $grupos[$menor][] = null;
        }
        foreach ($grupos as $i => $cupos) {
            $vueltas = $campeonato->formato === 'liga_ida_vuelta' ? 2
                : (int) ($cantidadGrupos > 1 ? ($config['group_rounds'] ?? 1) : ($config['league_rounds'] ?? 1));
            $indices = collect(array_keys($cupos))->map(fn ($indice) => (object) ['id' => $indice]);
            $rondas = (new LigaGenerator)->roundRobin($indices);
            if ($limite >= count($rondas) * $vueltas) $this->error('La jornada límite debe ser anterior a la última jornada de liga o grupos.');
            $calendario = [];
            for ($vuelta = 0; $vuelta < $vueltas; $vuelta++) {
                foreach ($rondas as $ronda => $parejas) {
                    foreach ($parejas as $orden => [$local, $visita]) {
                        if ($local === null || $visita === null) continue;
                        $calendario[] = [
                            'local' => $vuelta === 0 ? $local->id : $visita->id,
                            'visita' => $vuelta === 0 ? $visita->id : $local->id,
                            'jornada' => $vuelta * count($rondas) + $ronda + 1,
                            'orden' => $orden + 1, 'partido_id' => null,
                        ];
                    }
                }
            }
            $fase = $campeonato->fases()->create([
                'tipo' => $cantidadGrupos > 1 ? 'grupo' : 'liga', 'orden' => $i + 1, 'estado' => 'pendiente',
                'metadata' => ['grupo' => $i + 1, 'teams' => array_values(array_filter($cupos)), 'cupos' => $cupos, 'calendario_cupos' => $calendario],
            ]);
            $this->materializar($campeonato, $fase);
        }
    }

    /** El llamador bloquea primero el campeonato dentro de una transacción. */
    public function incorporar(Campeonato $campeonato, Equipo $equipo): void
    {
        if ($motivo = $this->motivoCierre($campeonato)) $this->error($motivo);
        if (! $campeonato->fases()->exists() && ! $campeonato->partidos()->exists()) return;
        $fases = $campeonato->fases()->whereIn('tipo', ['liga', 'grupo'])->orderBy('orden')->get();
        // Completar primero los grupos con menos participantes reales.
        $fases = $fases->sortBy(fn ($fase) => count(array_filter($fase->metadata['cupos'] ?? [])));
        foreach ($fases as $fase) {
            $metadata = $fase->metadata;
            $indice = array_search(null, $metadata['cupos'] ?? [], true);
            if ($indice === false) continue;
            $metadata['cupos'][$indice] = $equipo->id;
            $metadata['teams'][] = $equipo->id;
            $fase->update(['metadata' => $metadata]);
            $this->materializar($campeonato, $fase);
            return;
        }
        $this->error('No quedan cupos disponibles.');
    }

    private function materializar(Campeonato $campeonato, Fase $fase): void
    {
        $metadata = $fase->metadata;
        foreach ($metadata['calendario_cupos'] as &$encuentro) {
            $local = $metadata['cupos'][$encuentro['local']];
            $visita = $metadata['cupos'][$encuentro['visita']];
            if ($encuentro['partido_id'] || ! $local || ! $visita) continue;
            $partido = Partido::create([
                'campeonato_id' => $campeonato->id, 'fase_id' => $fase->id,
                'equipo_local_id' => $local, 'equipo_visita_id' => $visita,
                'ronda_number' => $encuentro['jornada'], 'orden_en_ronda' => $encuentro['orden'],
                'estado' => 'programado', 'fecha' => null, 'hora' => null, 'cancha' => null,
            ]);
            $encuentro['partido_id'] = $partido->id;
        }
        unset($encuentro);
        $fase->update(['metadata' => $metadata]);
    }

    private function error(string $mensaje): never
    {
        throw ValidationException::withMessages(['inscripcion' => $mensaje]);
    }
}
