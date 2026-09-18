<?php

namespace Database\Seeders;

use App\Models\Campeonato;
use App\Models\Equipo;
use App\Models\Fase;
use App\Models\Incidencia;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoCampeonatoSeeder extends Seeder
{
    private const PASSWORD = 'Demo1234!';

    public function run(): void
    {
        DB::transaction(function () {
            $organizador = $this->usuario('Organizador DEMO', 'organizador.demo@futbol.test', User::ROLE_ORGANIZADOR);
            $arbitro = $this->usuario('Árbitro DEMO Aprobado', 'arbitro.demo@futbol.test', User::ROLE_ARBITRO);
            $arbitroPendiente = $this->usuario('Árbitro DEMO Pendiente', 'arbitro.pendiente@futbol.test', User::ROLE_ARBITRO);

            $delegados = collect(range(1, 8))->map(fn (int $numero) => $this->usuario(
                "Delegado DEMO {$numero}",
                "delegado{$numero}.demo@futbol.test",
                User::ROLE_DELEGADO,
            ));

            $copa = Campeonato::updateOrCreate(
                ['nombre' => 'DEMO Copa Sacaba 2026'],
                [
                    'descripcion' => 'Campeonato de demostración con equipos, jugadores, resultados y próximos partidos.',
                    'fecha_inicio' => today()->subDays(10),
                    'fecha_fin' => today()->addDays(35),
                    'fecha_limite_inscripcion' => today()->subDay(),
                    'inscripciones_abiertas' => false,
                    'estado' => 'en_curso',
                    'usuario_creador' => $organizador->id,
                    'formato' => 'liga',
                    'formato_config' => [],
                    'modalidad' => 7,
                    'jugadores_cancha' => 7,
                    'min_jugadores_equipo' => 10,
                    'max_jugadores_equipo' => 15,
                    'categoria_edad' => 'libre',
                    'edad_min' => null,
                    'edad_max' => null,
                    'sexo' => 'masculino',
                ],
            );

            $liga = Campeonato::updateOrCreate(
                ['nombre' => 'DEMO Liga Barrial Sacaba'],
                [
                    'descripcion' => 'Campeonato abierto para probar solicitudes de equipos y generación del fixture.',
                    'fecha_inicio' => today()->addDays(30),
                    'fecha_fin' => today()->addDays(70),
                    'fecha_limite_inscripcion' => today()->addDays(20),
                    'inscripciones_abiertas' => true,
                    'estado' => 'pendiente',
                    'usuario_creador' => $organizador->id,
                    'formato' => 'liga_ida_vuelta',
                    'formato_config' => [],
                    'modalidad' => 7,
                    'jugadores_cancha' => 7,
                    'min_jugadores_equipo' => 10,
                    'max_jugadores_equipo' => 15,
                    'categoria_edad' => 'libre',
                    'edad_min' => null,
                    'edad_max' => null,
                    'sexo' => 'mixto',
                ],
            );

            $finalizado = Campeonato::updateOrCreate(
                ['nombre' => 'DEMO Copa de Grupos Finalizada'],
                [
                    'descripcion' => 'Ejemplo completo: dos grupos, semifinales de ida y vuelta, tercer puesto y final a partido único.',
                    'fecha_inicio' => today()->subDays(90),
                    'fecha_fin' => today()->subDays(5),
                    'fecha_limite_inscripcion' => today()->subDays(100),
                    'inscripciones_abiertas' => false,
                    'estado' => 'finalizado',
                    'usuario_creador' => $organizador->id,
                    'formato' => 'grupos_eliminacion',
                    'formato_config' => [
                        'number_of_groups' => 2,
                        'teams_per_group' => 4,
                        'qualifiers_per_group' => 2,
                        'elimination_start' => 'semifinal',
                        'eliminations_home_away' => 1,
                        'third_place_match' => 1,
                    ],
                    'modalidad' => 7,
                    'jugadores_cancha' => 7,
                    'min_jugadores_equipo' => 10,
                    'max_jugadores_equipo' => 15,
                    'categoria_edad' => 'libre',
                    'edad_min' => null,
                    'edad_max' => null,
                    'sexo' => 'masculino',
                ],
            );

            $equiposCopa = $this->equipos(
                $copa,
                $delegados,
                ['Tigres de Sacaba', 'Halcones FC', 'Real Sacaba', 'Deportivo Central'],
                10,
                'aprobado',
            );

            $this->equipos(
                $liga,
                $delegados,
                ['Los Andes', 'Villa Esperanza', 'Juventud Sacaba', 'Atlético Norte'],
                8,
                fn (int $indice) => $indice === 3 ? 'pendiente' : 'aprobado',
            );

            $equiposFinalizado = $this->equipos(
                $finalizado,
                $delegados,
                ['Barrio Norte', 'San Pedro', 'Juventud Central', 'Villa Nueva', 'Deportivo Sur', 'Los Pinos', 'Unión Sacaba', 'Atlético Valle'],
                10,
                'aprobado',
            );

            $copa->arbitros()->syncWithoutDetaching([
                $arbitro->id => [
                    'estado' => 'aprobado',
                    'mensaje' => 'Disponible para dirigir los fines de semana.',
                    'gestionado_por' => $organizador->id,
                    'gestionado_at' => now(),
                ],
                $arbitroPendiente->id => [
                    'estado' => 'pendiente',
                    'mensaje' => 'Solicito participar en este campeonato.',
                ],
            ]);
            $liga->arbitros()->syncWithoutDetaching([
                $arbitro->id => ['estado' => 'aprobado', 'gestionado_por' => $organizador->id, 'gestionado_at' => now()],
            ]);

            $this->partidosCopa($copa, $equiposCopa, $arbitro);
            $this->campeonatoFinalizado($finalizado, $equiposFinalizado, $arbitro);
        });

        $equiposDemo = Equipo::where('nombre', 'like', '%[DEMO]%');
        $this->command?->info(sprintf(
            'DEMO listo: %d campeonatos, %d equipos, %d jugadores y %d partidos. Contraseña: %s',
            Campeonato::where('nombre', 'like', 'DEMO%')->count(),
            (clone $equiposDemo)->count(),
            Jugador::whereIn('equipo_id', (clone $equiposDemo)->pluck('id'))->count(),
            Partido::whereIn('campeonato_id', Campeonato::where('nombre', 'like', 'DEMO%')->pluck('id'))->count(),
            self::PASSWORD,
        ));
    }

    private function usuario(string $nombre, string $email, string $rol): User
    {
        $usuario = User::updateOrCreate(
            ['email' => $email],
            ['name' => $nombre, 'password' => Hash::make(self::PASSWORD), 'rol' => $rol],
        );
        $usuario->forceFill(['email_verified_at' => now()])->save();

        return $usuario;
    }

    private function equipos(Campeonato $campeonato, $delegados, array $nombres, int $cantidadJugadores, string|callable $estado): array
    {
        $equipos = [];

        foreach ($nombres as $indice => $nombre) {
            $equipo = Equipo::updateOrCreate(
                ['nombre' => "{$nombre} [DEMO]"],
                ['delegado_id' => $delegados[$indice]->id, 'ciudad' => 'Sacaba'],
            );
            $estadoInscripcion = is_callable($estado) ? $estado($indice) : $estado;
            $campeonato->equipos()->syncWithoutDetaching([
                $equipo->id => ['estado' => $estadoInscripcion],
            ]);
            $this->jugadores($equipo, $indice, $cantidadJugadores, $campeonato->sexo === 'mixto');
            $equipos[] = $equipo;
        }

        return $equipos;
    }

    private function jugadores(Equipo $equipo, int $equipoIndice, int $cantidad, bool $mixto): void
    {
        $posiciones = ['Arquero', 'Defensa', 'Defensa', 'Mediocampista', 'Mediocampista', 'Delantero'];

        foreach (range(1, $cantidad) as $numero) {
            Jugador::updateOrCreate(
                ['equipo_id' => $equipo->id, 'numero' => $numero],
                [
                    'nombre' => "Jugador {$numero}",
                    'apellido' => "DEMO {$equipoIndice}",
                    'dni' => sprintf('DEMO-%02d-%02d', $equipoIndice + 1, $numero),
                    'fecha_nacimiento' => today()->subYears(20 + ($numero % 10)),
                    'sexo' => $mixto && $numero % 4 === 0 ? 'femenino' : 'masculino',
                    'posicion' => $posiciones[($numero - 1) % count($posiciones)],
                    'estado' => Jugador::ESTADO_APROBADO,
                ],
            );
        }
    }

    private function partidosCopa(Campeonato $campeonato, array $equipos, User $arbitro): void
    {
        $partidos = [
            [0, 1, -7, 'jugado', 2, 1],
            [2, 3, -6, 'jugado', 1, 1],
            [0, 2, 2, 'programado', null, null],
            [1, 3, 3, 'programado', null, null],
            [0, 3, 7, 'programado', null, null],
            [1, 2, 7, 'programado', null, null],
        ];

        foreach ($partidos as $orden => [$local, $visita, $dias, $estado, $golesLocal, $golesVisita]) {
            $partido = Partido::updateOrCreate(
                [
                    'campeonato_id' => $campeonato->id,
                    'equipo_local_id' => $equipos[$local]->id,
                    'equipo_visita_id' => $equipos[$visita]->id,
                ],
                [
                    'ronda_number' => intdiv($orden, 2) + 1,
                    'orden_en_ronda' => ($orden % 2) + 1,
                    'fecha' => today()->addDays($dias),
                    'hora' => $orden % 2 === 0 ? '15:00' : '17:00',
                    'cancha' => 'Estadio Municipal de Sacaba',
                    'arbitro_id' => $arbitro->id,
                    'goles_local' => $golesLocal,
                    'goles_visita' => $golesVisita,
                    'estado' => $estado,
                ],
            );

            if ($estado !== 'jugado') {
                continue;
            }

            $goleadorLocal = $equipos[$local]->jugadores()->orderBy('numero')->first();
            $goleadorVisita = $equipos[$visita]->jugadores()->orderBy('numero')->first();
            for ($gol = 1; $gol <= $golesLocal; $gol++) {
                Incidencia::updateOrCreate(
                    ['partido_id' => $partido->id, 'jugador_id' => $goleadorLocal->id, 'tipo' => Incidencia::TIPO_GOL, 'minuto' => 15 * $gol],
                    ['creado_por' => $arbitro->id],
                );
            }
            for ($gol = 1; $gol <= $golesVisita; $gol++) {
                Incidencia::updateOrCreate(
                    ['partido_id' => $partido->id, 'jugador_id' => $goleadorVisita->id, 'tipo' => Incidencia::TIPO_GOL, 'minuto' => 20 * $gol],
                    ['creado_por' => $arbitro->id],
                );
            }

            $amonestado = $equipos[$local]->jugadores()->orderBy('numero')->skip(1)->first();
            Incidencia::updateOrCreate(
                ['partido_id' => $partido->id, 'jugador_id' => $amonestado->id, 'tipo' => Incidencia::TIPO_AMARILLA, 'minuto' => 42],
                ['creado_por' => $arbitro->id],
            );
            if ($orden === 1) {
                $expulsado = $equipos[$visita]->jugadores()->orderBy('numero')->skip(2)->first();
                Incidencia::updateOrCreate(
                    ['partido_id' => $partido->id, 'jugador_id' => $expulsado->id, 'tipo' => Incidencia::TIPO_ROJA, 'minuto' => 76],
                    ['creado_por' => $arbitro->id],
                );
            }
        }
    }

    private function campeonatoFinalizado(Campeonato $campeonato, array $equipos, User $arbitro): void
    {
        Incidencia::whereIn('partido_id', $campeonato->partidos()->pluck('id'))->delete();
        $campeonato->partidos()->delete();
        $campeonato->fases()->delete();

        $grupos = [array_slice($equipos, 0, 4), array_slice($equipos, 4, 4)];
        $dia = -82;

        foreach ($grupos as $indice => $equiposGrupo) {
            $fase = Fase::create([
                'campeonato_id' => $campeonato->id,
                'tipo' => 'grupo',
                'orden' => $indice + 1,
                'metadata' => ['grupo' => $indice + 1, 'teams' => collect($equiposGrupo)->pluck('id')->all()],
                'estado' => 'finalizada',
            ]);
            $cruces = [[0, 1], [2, 3], [0, 2], [1, 3], [0, 3], [1, 2]];

            foreach ($cruces as $orden => [$local, $visita]) {
                $this->partidoDemo($campeonato, $fase, $equiposGrupo[$local], $equiposGrupo[$visita], $dia++, $arbitro, 1 + (($orden + $indice) % 3), $orden % 2, intdiv($orden, 2) + 1, ($orden % 2) + 1);
            }
        }

        $eliminacion = Fase::create([
            'campeonato_id' => $campeonato->id,
            'tipo' => 'eliminacion',
            'orden' => 3,
            'metadata' => ['from_groups' => true, 'bracket_size' => 4, 'home_away' => true],
            'estado' => 'finalizada',
        ]);

        // Semifinal 1: Barrio Norte supera a Los Pinos por 4-2 en el global.
        $this->partidoDemo($campeonato, $eliminacion, $equipos[0], $equipos[5], -25, $arbitro, 2, 1, 1, 1);
        $this->partidoDemo($campeonato, $eliminacion, $equipos[5], $equipos[0], -18, $arbitro, 1, 2, 1, 1);
        // Semifinal 2: Deportivo Sur supera a San Pedro por 3-2 en el global.
        $this->partidoDemo($campeonato, $eliminacion, $equipos[4], $equipos[1], -24, $arbitro, 1, 1, 1, 2);
        $this->partidoDemo($campeonato, $eliminacion, $equipos[1], $equipos[4], -17, $arbitro, 1, 2, 1, 2);
        // La final siempre se disputa a partido único.
        $this->partidoDemo($campeonato, $eliminacion, $equipos[0], $equipos[4], -5, $arbitro, 3, 1, 2, 1);
        // Los perdedores de semifinales disputan el tercer lugar a partido único.
        $this->partidoDemo($campeonato, $eliminacion, $equipos[5], $equipos[1], -6, $arbitro, 2, 0, 2, 2);
    }

    private function partidoDemo(Campeonato $campeonato, Fase $fase, Equipo $local, Equipo $visita, int $dias, User $arbitro, int $golesLocal, int $golesVisita, int $ronda, int $orden): Partido
    {
        $partido = Partido::create([
            'campeonato_id' => $campeonato->id,
            'fase_id' => $fase->id,
            'equipo_local_id' => $local->id,
            'equipo_visita_id' => $visita->id,
            'ronda_number' => $ronda,
            'orden_en_ronda' => $orden,
            'fecha' => today()->addDays($dias),
            'hora' => $orden % 2 === 0 ? '17:00' : '15:00',
            'cancha' => 'Estadio Municipal de Sacaba',
            'arbitro_id' => $arbitro->id,
            'goles_local' => $golesLocal,
            'goles_visita' => $golesVisita,
            'estado' => 'jugado',
        ]);

        $goleadoresLocal = $local->jugadores()->where('estado', Jugador::ESTADO_APROBADO)->orderBy('numero')->take(3)->get();
        $goleadoresVisita = $visita->jugadores()->where('estado', Jugador::ESTADO_APROBADO)->orderBy('numero')->take(3)->get();

        for ($gol = 1; $gol <= $golesLocal; $gol++) {
            Incidencia::create([
                'partido_id' => $partido->id,
                'jugador_id' => $goleadoresLocal[($gol - 1) % $goleadoresLocal->count()]->id,
                'tipo' => Incidencia::TIPO_GOL,
                'minuto' => min(89, 14 * $gol),
                'creado_por' => $arbitro->id,
            ]);
        }
        for ($gol = 1; $gol <= $golesVisita; $gol++) {
            Incidencia::create([
                'partido_id' => $partido->id,
                'jugador_id' => $goleadoresVisita[($gol - 1) % $goleadoresVisita->count()]->id,
                'tipo' => Incidencia::TIPO_GOL,
                'minuto' => min(89, 18 * $gol),
                'creado_por' => $arbitro->id,
            ]);
        }

        return $partido;
    }
}
