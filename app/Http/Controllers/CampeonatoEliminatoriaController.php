<?php

namespace App\Http\Controllers;

use App\Models\Campeonato;
use App\Models\Fase;
use App\Models\Partido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador para generar fases eliminatorias.
 *
 * Se utiliza cuando un campeonato con grupos ya finalizados necesita
 * crear automáticamente la siguiente etapa eliminatoria.
 * Ruta clave:
 *  - campeonatos/{campeonato}/generar-eliminatoria
 */
class CampeonatoEliminatoriaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Generar fase eliminatoria a partir de fases de grupo cuando estén finalizadas.
     * Reglas:
     * - Se espera que existan fases tipo 'grupo' con metadata['teams'] = [ids]
     * - formato_config debe contener 'qualifiers_per_group' y 'elimination_start'
     * - Emparejamientos predefinidos: para cada par de grupos (A,B), crear: 1A vs 2B y 1B vs 2A
     */
    public function generar(Campeonato $campeonato, Request $request)
    {
        $user = auth()->user();
        if (!($user->rol === \App\Models\User::ROLE_DESARROLLADOR || ($user->rol === \App\Models\User::ROLE_ORGANIZADOR && $campeonato->usuario_creador == $user->id))) {
            abort(403);
        }

        // Verificar que existan fases de grupo
        if (!\Illuminate\Support\Facades\Schema::hasTable('fases')) {
            return redirect()->route('campeonatos.show', $campeonato)->withErrors(['error' => 'No hay fases disponibles para generar eliminatoria.']);
        }

        $groupFases = $campeonato->fases()->where('tipo', 'grupo')->orderBy('orden')->get();
        if ($groupFases->isEmpty()) {
            return redirect()->route('campeonatos.show', $campeonato)->withErrors(['error' => 'No se encontraron fases de grupo para este campeonato.']);
        }

        // Verificar que todas las fases de grupo estén finalizadas (todos sus partidos jugados)
        foreach ($groupFases as $gf) {
            $partidos = $gf->partidos()->get();
            if ($partidos->isEmpty()) {
                return redirect()->route('campeonatos.show', $campeonato)->withErrors(['error' => 'La fase de grupo ' . ($gf->metadata['grupo'] ?? $gf->id) . ' no tiene partidos.']);
            }
            $notPlayed = $partidos->where('estado', '!=', 'jugado')->count();
            if ($notPlayed > 0) {
                return redirect()->route('campeonatos.show', $campeonato)->withErrors(['error' => 'Todas las fases de grupo deben estar finalizadas antes de generar la eliminatoria.']);
            }
        }

        // Obtener qualifiers_per_group
        $qualifiers = $campeonato->formato_config['qualifiers_per_group'] ?? 1;
        if (!in_array($qualifiers, [1, 2, 4])) {
            return redirect()->route('campeonatos.show', $campeonato)->withErrors(['error' => 'La cantidad de clasificados por grupo no es válida.']);
        }

        // Calcular clasificación por grupo
        $clasificadosPorGrupo = [];
        foreach ($groupFases as $gf) {
            $partidos = $gf->partidos()->get();
            $equiposIds = $gf->metadata['teams'] ?? [];
            $tabla = [];
            foreach ($equiposIds as $eid) {
                $equipo = \App\Models\Equipo::find($eid);
                if (!$equipo) continue;

                $pj = 0; $pg = 0; $pe = 0; $pp = 0; $gf_total = 0; $gc_total = 0;
                foreach ($partidos as $p) {
                    if ($p->equipo_local_id == $equipo->id) {
                        $pj++; $gf_total += $p->goles_local; $gc_total += $p->goles_visita;
                        if ($p->goles_local > $p->goles_visita) $pg++; elseif ($p->goles_local == $p->goles_visita) $pe++; else $pp++;
                    }
                    if ($p->equipo_visita_id == $equipo->id) {
                        $pj++; $gf_total += $p->goles_visita; $gc_total += $p->goles_local;
                        if ($p->goles_visita > $p->goles_local) $pg++; elseif ($p->goles_visita == $p->goles_local) $pe++; else $pp++;
                    }
                }

                $tabla[] = ['equipo' => $equipo, 'pj'=>$pj,'pg'=>$pg,'pe'=>$pe,'pp'=>$pp,'gf'=>$gf_total,'gc'=>$gc_total,'dif'=>$gf_total-$gc_total,'pts'=>($pg*3+$pe)];
            }

            usort($tabla, function($a,$b){ if($a['pts']!=$b['pts']) return $b['pts']-$a['pts']; if($a['dif']!=$b['dif']) return $b['dif']-$a['dif']; return $b['gf']-$a['gf'];});

            $clasificados = array_slice($tabla, 0, $qualifiers);
            $clasificadosPorGrupo[] = array_map(fn($r)=> $r['equipo'], $clasificados);
        }

        // Construir fase eliminatoria según emparejamientos por pares de grupos
        DB::transaction(function() use ($campeonato, $groupFases, $clasificadosPorGrupo, $qualifiers) {
            $orden = $campeonato->fases()->max('orden') ?? 0;
            $orden++;
            $elimFase = Fase::create([
                'campeonato_id' => $campeonato->id,
                'tipo' => 'eliminacion',
                'orden' => $orden,
                'metadata' => ['from_groups' => true, 'qualifiers_per_group' => $qualifiers],
                'estado' => 'pendiente'
            ]);

            $totalClasificados = collect($clasificadosPorGrupo)->sum(fn ($grupo) => count($grupo));
            $idaVuelta = $totalClasificados > 2
                && ! empty($campeonato->formato_config['eliminations_home_away']);
            $ordenPartido = 1;

            // Crea uno o dos encuentros de una misma llave. La final permanece
            // a partido único aunque las rondas anteriores sean ida y vuelta.
            $crearLlave = function ($local, $visita) use ($campeonato, $elimFase, $idaVuelta, &$ordenPartido): void {
                Partido::create(['campeonato_id'=>$campeonato->id,'fase_id'=>$elimFase->id,'equipo_local_id'=>$local?->id,'equipo_visita_id'=>$visita?->id,'fecha'=>null,'hora'=>null,'cancha'=>null,'ronda_number'=>1,'orden_en_ronda'=>$ordenPartido]);
                if ($idaVuelta && $local && $visita) {
                    Partido::create(['campeonato_id'=>$campeonato->id,'fase_id'=>$elimFase->id,'equipo_local_id'=>$visita->id,'equipo_visita_id'=>$local->id,'fecha'=>null,'hora'=>null,'cancha'=>null,'ronda_number'=>1,'orden_en_ronda'=>$ordenPartido]);
                }
                $ordenPartido++;
            };

            // Para pares de grupos (0&1, 2&3, ...)
            $numGroups = count($clasificadosPorGrupo);
            for ($g = 0; $g < $numGroups; $g += 2) {
                $gA = $clasificadosPorGrupo[$g] ?? [];
                $gB = $clasificadosPorGrupo[$g+1] ?? [];

                // Si qualifiers == 1: crear 1A vs 1B? But spec wants 1A vs 2B and 1B vs 2A when qualifiers=2
                if (count($gA) == 0 && count($gB) == 0) continue;

                if (count($gA) > 0 && count($gB) > 0 && $qualifiers == 1) {
                    // For 1 qualifier per group, pair 1A vs 1B
                    $crearLlave($gA[0], $gB[0]);
                } elseif (count($gA) > 0 && count($gB) > 0) {
                    // Cruces equilibrados: 1A-ultimoB, 1B-ultimoA,
                    // 2A-penultimoB, 2B-penultimoA, etc.
                    for ($puesto = 0; $puesto < intdiv($qualifiers, 2); $puesto++) {
                        $contrario = $qualifiers - 1 - $puesto;
                        $crearLlave($gA[$puesto] ?? null, $gB[$contrario] ?? null);
                        $crearLlave($gB[$puesto] ?? null, $gA[$contrario] ?? null);
                    }
                } else {
                    // If only one group (edge cases), promote qualifiers as byes
                    if (count($gA) > 0 && count($gB) == 0) {
                        $crearLlave($gA[0], null);
                    }
                }
            }

            $elimFase->save();
        });

        return redirect()->route('campeonatos.show', $campeonato)->with('mensaje','✅ Fase eliminatoria generada con los clasificados de grupos.');
    }
}
