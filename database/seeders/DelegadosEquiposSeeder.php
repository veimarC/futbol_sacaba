<?php

namespace Database\Seeders;

use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Datos de demostración para probar plantillas de Fútbol 11.
 *
 * Crea los delegados 3 al 20, un equipo por delegado y once jugadores
 * masculinos por equipo. Se puede ejecutar varias veces sin duplicar datos.
 */
class DelegadosEquiposSeeder extends Seeder
{
    public function run(): void
    {
        $nombresJugadores = [
            'Adrián', 'Alejandro', 'Álvaro', 'Andrés', 'Brayan', 'Carlos',
            'Cristian', 'Daniel', 'David', 'Diego', 'Eduardo', 'Emiliano',
            'Fernando', 'Gabriel', 'Javier', 'José', 'Kevin', 'Leonardo',
            'Luis', 'Marco', 'Martín', 'Mateo', 'Miguel', 'Nicolás',
        ];

        $apellidosJugadores = [
            'Aguilar', 'Cabrera', 'Castro', 'Condori', 'Fernández', 'Flores',
            'García', 'Guzmán', 'López', 'Mamani', 'Martínez', 'Mendoza',
            'Pérez', 'Quispe', 'Ramírez', 'Rojas', 'Romero', 'Salazar',
            'Sánchez', 'Torrico', 'Vargas', 'Vega', 'Villarroel', 'Zambrana',
        ];

        $posiciones = [
            1 => 'Portero',
            2 => 'Lateral Derecho',
            3 => 'Defensa Central',
            4 => 'Defensa Central',
            5 => 'Lateral Izquierdo',
            6 => 'Mediocampista Defensivo',
            7 => 'Extremo Derecho',
            8 => 'Mediocampista',
            9 => 'Delantero',
            10 => 'Mediapunta',
            11 => 'Extremo Izquierdo',
        ];

        for ($numeroDelegado = 3; $numeroDelegado <= 20; $numeroDelegado++) {
            $delegado = User::updateOrCreate(
                ['email' => "delegado{$numeroDelegado}@gmail.com"],
                [
                    'name' => "Delegado {$numeroDelegado}",
                    'password' => Hash::make('75959158'),
                    'rol' => User::ROLE_DELEGADO,
                ],
            );

            $equipo = Equipo::updateOrCreate(
                [
                    'delegado_id' => $delegado->id,
                    'nombre' => "Equipo Delegado {$numeroDelegado}",
                ],
                ['ciudad' => 'Sacaba'],
            );

            for ($dorsal = 1; $dorsal <= 11; $dorsal++) {
                $indiceJugador = (($numeroDelegado - 3) * 11) + ($dorsal - 1);
                $edad = 15 + ($indiceJugador % 26);
                $ci = (string) (90000000 + ($numeroDelegado * 100) + $dorsal);

                Jugador::updateOrCreate(
                    [
                        'equipo_id' => $equipo->id,
                        'numero' => $dorsal,
                    ],
                    [
                        'nombre' => $nombresJugadores[$indiceJugador % count($nombresJugadores)],
                        // En una misma plantilla cada dorsal recibe un apellido distinto.
                        'apellido' => $apellidosJugadores[$indiceJugador % count($apellidosJugadores)],
                        'dni' => $ci,
                        'fecha_nacimiento' => today()->subYears($edad)->toDateString(),
                        'sexo' => 'masculino',
                        'posicion' => $posiciones[$dorsal],
                        'estado' => Jugador::ESTADO_APROBADO,
                    ],
                );
            }
        }

        $this->command?->info('Delegados 3–20 creados con un equipo y 11 jugadores masculinos cada uno.');
    }
}
