<?php

use App\Http\Controllers\CampeonatoArbitroController;
use App\Http\Controllers\CampeonatoController;
use App\Http\Controllers\CampeonatoEliminatoriaController;
use App\Http\Controllers\EquipoController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IncidenciaController;
use App\Http\Controllers\JugadorController;
use App\Http\Controllers\NominaPartidoController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\PartidoController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\PortalPublicoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas web de la plataforma
|--------------------------------------------------------------------------
|
| Una ruta conecta una URL con un método de un controlador.
| Ejemplo:
| Route::get('/ruta', [Controlador::class, 'metodo'])->name('nombre');
|
| - get/post/put/delete: acción HTTP permitida.
| - middleware: condición que debe cumplir el usuario.
| - name: nombre estable usado para crear enlaces con route().
| - {campeonato}: Laravel busca automáticamente ese modelo por su ID.
|
*/

// Rutas públicas: cualquier visitante puede consultar estos contenidos.
Route::get('/', [PortalPublicoController::class, 'index'])->name('publico.index');
Route::get('/torneos/{campeonato}', [PortalPublicoController::class, 'show'])->name('publico.show');

// Agrega las rutas de ingreso, registro y recuperación de contraseña.
Auth::routes();

// Rutas protegidas por autenticación
Route::middleware('auth')->group(function () {
    // Entrada posterior al acceso: redirige cada rol a su área correspondiente.
    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::get('/perfil', [PerfilController::class, 'edit'])->name('perfil.edit');
    Route::put('/perfil', [PerfilController::class, 'update'])->name('perfil.update');
    Route::get('/notificaciones', [NotificacionController::class, 'index'])->name('notificaciones.index');
    Route::get('/notificaciones/{notificacion}', [NotificacionController::class, 'abrir'])->name('notificaciones.abrir');
    Route::post('/notificaciones-leer-todas', [NotificacionController::class, 'marcarTodas'])->name('notificaciones.leer-todas');

    Route::get('campeonatos-descubrir', [CampeonatoController::class, 'descubrir'])
        ->middleware('role:delegado')
        ->name('campeonatos.descubrir');

    /*
     * Flujo del árbitro.
     * `prefix` agrega /arbitro a las URL y `name` agrega arbitros. a los nombres.
     * Por ejemplo, `panel` termina llamándose `arbitros.panel`.
     */
    Route::middleware('role:arbitro')->prefix('arbitro')->name('arbitros.')->group(function () {
        Route::get('campeonatos', [CampeonatoArbitroController::class, 'explorar'])->name('explorar');
        Route::get('mis-arbitrajes', [CampeonatoArbitroController::class, 'panel'])->name('panel');
        Route::post('campeonatos/{campeonato}/postular', [CampeonatoArbitroController::class, 'solicitar'])->name('solicitar');
    });

    // El organizador responde una postulación; el árbitro no puede aprobarse solo.
    Route::post('campeonatos/{campeonato}/arbitros/{arbitro}/gestionar', [CampeonatoArbitroController::class, 'gestionar'])
        ->middleware('role:organizador|desarrollador')->name('campeonatos.arbitros.gestionar');
    Route::get('campeonatos/{campeonato}/arbitros', [CampeonatoArbitroController::class, 'administrar'])
        ->middleware('role:organizador|desarrollador')->name('campeonatos.arbitros.administrar');
    // Respuesta JSON que llena dinámicamente el selector de árbitros de un partido.
    Route::get('api/arbitros-campeonato/{campeonato}', [CampeonatoArbitroController::class, 'opciones'])
        ->middleware('role:organizador|desarrollador')->name('campeonatos.arbitros.opciones');

    // Campeonatos: CRUD completo para crear, editar, ver y eliminar campeonatos
    Route::resource('campeonatos', CampeonatoController::class);

    // Generar fixture automático según el formato del campeonato
    Route::post('campeonatos/{campeonato}/generar-fixture', [CampeonatoController::class, 'generarFixture'])
        ->name('campeonatos.generarFixture');

    Route::patch('campeonatos/{campeonato}/clasificados-por-grupo', [CampeonatoController::class, 'actualizarClasificados'])
        ->name('campeonatos.actualizarClasificados');

    // Generar fase eliminatoria a partir de grupos ya finalizados
    Route::post('campeonatos/{campeonato}/generar-eliminatoria', [CampeonatoEliminatoriaController::class, 'generar'])
        ->name('campeonatos.generarEliminatoria');

    // Equipos: gestión parcial. El listado completo se muestra desde el campeonato,
    // pero se permiten crear/editar/borrar equipos individualmente.
    Route::get('equipos', [EquipoController::class, 'index'])
        ->middleware('role:delegado|desarrollador')
        ->name('equipos.index');
    Route::resource('equipos', EquipoController::class)->except('index');
    Route::post('equipos/solicitar-inscripcion', [EquipoController::class, 'solicitarInscripcion'])->middleware('role:delegado')->name('equipos.solicitarInscripcion');
    Route::post('campeonatos/{campeonato}/equipos/{equipo}/gestionar', [EquipoController::class, 'gestionarSolicitud'])->middleware('role:organizador|desarrollador')->name('campeonatos.equipos.gestionar');
    Route::post('campeonatos/{campeonato}/equipos/{equipo}/retirar', [EquipoController::class, 'retirarDelCampeonato'])->middleware('role:organizador|desarrollador')->name('campeonatos.equipos.retirar');

    // Partidos: crear, editar, actualizar y eliminar partidos. El índice general no se usa aquí.
    Route::resource('partidos', PartidoController::class)->except(['index', 'show']);
    Route::get('partidos/{partido}/nominas', [NominaPartidoController::class, 'index'])->name('partidos.nominas.index');
    Route::get('partidos/{partido}/nominas/{equipo}/editar', [NominaPartidoController::class, 'editar'])->name('partidos.nominas.editar');
    Route::put('partidos/{partido}/nominas/{equipo}', [NominaPartidoController::class, 'guardar'])->name('partidos.nominas.guardar');
    Route::post('partidos/{partido}/nominas/{nomina}/gestionar', [NominaPartidoController::class, 'gestionar'])->name('partidos.nominas.gestionar');

    /*
     |--------------------------------------------------------------------------
     | Jugadores
     |--------------------------------------------------------------------------
     | Los jugadores se administran dentro de la plantilla de cada equipo.
     | Los delegados y administradores generales registran y editan; los
     | organizadores revisan las solicitudes desde ese mismo lugar.
     */

    Route::middleware('role:delegado|desarrollador')->group(function () {
        Route::get('equipos/{equipo}/jugadores/create', [JugadorController::class, 'create'])
            ->name('equipos.jugadores.create');
        Route::post('jugadores', [JugadorController::class, 'store'])
            ->name('jugadores.store');
        Route::get('jugadores/{jugador}/edit', [JugadorController::class, 'edit'])
            ->name('jugadores.edit');
        Route::put('jugadores/{jugador}', [JugadorController::class, 'update'])
            ->name('jugadores.update');
        Route::delete('jugadores/{jugador}', [JugadorController::class, 'destroy'])
            ->name('jugadores.destroy');
    });

    Route::get('jugadores/{jugador}', [JugadorController::class, 'show'])
        ->middleware('role:delegado|organizador|desarrollador')
        ->name('jugadores.show');

    Route::middleware('role:organizador')->group(function () {
        Route::post('jugadores/{jugador}/aprobar', [JugadorController::class, 'aprobar'])
            ->name('jugadores.aprobar');
        Route::post('jugadores/{jugador}/rechazar', [JugadorController::class, 'rechazar'])
            ->name('jugadores.rechazar');
    });

    // Endpoint auxiliar usado por JavaScript para cargar los equipos de un campeonato
    Route::get('/api/equipos-campeonato/{campeonato}', [PartidoController::class, 'equiposPorCampeonato']);

    // Rutas para registrar resultados de partidos desde la interfaz de partido
    Route::get('/partidos/{partido}/resultado', [PartidoController::class, 'resultado'])
        ->name('partidos.resultado');
    Route::put('/partidos/{partido}/guardar-resultado', [PartidoController::class, 'guardarResultado'])
        ->name('partidos.guardarResultado');

    // Guardar incidencias como goles y tarjetas asociadas a un partido
    Route::post('incidencias', [IncidenciaController::class, 'store'])
        ->name('incidencias.store');
    Route::delete('incidencias/{incidencia}', [IncidenciaController::class, 'destroy'])
        ->name('incidencias.destroy');
});
