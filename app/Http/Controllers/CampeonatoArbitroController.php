<?php

namespace App\Http\Controllers;

use App\Models\Campeonato;
use App\Models\Partido;
use App\Models\User;
use App\Notifications\NotificacionPlataforma;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Gestiona el ciclo de participación de un árbitro en los campeonatos.
 *
 * Flujo general:
 * 1. El árbitro explora campeonatos disponibles.
 * 2. Envía una postulación al campeonato que le interesa.
 * 3. El organizador aprueba o rechaza esa postulación.
 * 4. Si fue aprobado, puede ser asignado a partidos de ese campeonato.
 *
 * La relación entre árbitros y campeonatos se guarda en la tabla intermedia
 * `arbitro_campeonato`. Allí también se conserva el estado de la solicitud.
 */
class CampeonatoArbitroController extends Controller
{
    public function administrar(Request $request, Campeonato $campeonato)
    {
        $this->autorizarOrganizador($request->user(), $campeonato);
        $solicitudesArbitros = $campeonato->arbitros()->wherePivot('estado', 'pendiente')->orderBy('name')->get();
        $arbitrosAprobados = $campeonato->arbitros()->wherePivot('estado', 'aprobado')->orderBy('name')->get();

        return view('arbitros.administrar', compact('campeonato', 'solicitudesArbitros', 'arbitrosAprobados'));
    }

    /**
     * Muestra al árbitro los campeonatos donde puede postularse.
     *
     * `with()` carga su posible solicitud y evita consultas repetidas.
     * `withCount()` cuenta los árbitros que el organizador ya aprobó.
     * `when()` agrega el filtro por nombre solamente cuando se escribió algo.
     */
    public function explorar(Request $request)
    {
        // Aunque la ruta ya tiene middleware, esta comprobación protege también
        // el método si en el futuro alguien lo llama desde otra ruta.
        $this->autorizarArbitro($request->user());
        $busqueda = trim((string) $request->query('buscar'));

        // Construimos la consulta paso a paso y paginamos de 9 en 9 para no
        // cargar todos los campeonatos existentes en una sola respuesta.
        $campeonatos = Campeonato::query()
            ->with(['arbitros' => fn ($q) => $q->where('users.id', $request->user()->id)])
            ->withCount(['arbitros as arbitros_aprobados_count' => fn ($q) => $q->where('arbitro_campeonato.estado', 'aprobado')])
            ->noFinalizadosActuales()
            ->when($busqueda !== '', fn ($q) => $q->where('nombre', 'like', '%'.$busqueda.'%'))
            ->orderBy('fecha_inicio')
            ->paginate(9)
            ->withQueryString();

        return view('arbitros.explorar', compact('campeonatos', 'busqueda'));
    }

    /**
     * Panel privado del árbitro.
     *
     * Entrega a la vista dos grupos de información:
     * - sus postulaciones y el estado de cada una;
     * - los partidos que tiene asignados como árbitro.
     */
    public function panel(Request $request)
    {
        $this->autorizarArbitro($request->user());

        // Esta relación pertenece al modelo User y trae datos adicionales de
        // la tabla pivote, por ejemplo: pendiente, aprobado o rechazado.
        $solicitudes = $request->user()->campeonatosComoArbitro()
            ->orderBy('fecha_inicio')
            ->get();

        // Los partidos programados aparecen primero; después se muestran los
        // ya jugados. Las relaciones cargadas permiten imprimir sus nombres.
        $partidos = Partido::with(['campeonato', 'equipoLocal', 'equipoVisita'])
            ->where('arbitro_id', $request->user()->id)
            ->orderByRaw("CASE WHEN estado = 'programado' THEN 0 ELSE 1 END")
            ->orderBy('fecha')
            ->orderBy('hora')
            ->get();

        return view('arbitros.panel', compact('solicitudes', 'partidos'));
    }

    /**
     * Registra la postulación del árbitro en un campeonato.
     *
     * Laravel inyecta automáticamente el campeonato usando el identificador
     * presente en la URL (route model binding).
     */
    public function solicitar(Request $request, Campeonato $campeonato)
    {
        $this->autorizarArbitro($request->user());
        abort_if($campeonato->estado_actual === 'finalizado', 422, 'Este campeonato ya finalizó y no recibe postulaciones.');

        $data = $request->validate([
            'mensaje' => ['nullable', 'string', 'max:500'],
        ]);

        // Una misma persona solamente puede tener una solicitud por campeonato.
        if ($campeonato->arbitros()->where('users.id', $request->user()->id)->exists()) {
            return back()->withErrors(['error' => 'Ya tienes una solicitud registrada en este campeonato. Puedes revisar su estado en Mis arbitrajes.']);
        }

        // `attach()` crea el registro en la tabla pivote árbitro-campeonato.
        $campeonato->arbitros()->attach($request->user()->id, [
            'estado' => 'pendiente',
            'mensaje' => trim((string) ($data['mensaje'] ?? '')) ?: null,
        ]);

        // El operador `?->` evita un error si el usuario organizador ya no existe.
        User::find($campeonato->usuario_creador)?->notify(new NotificacionPlataforma(
            'Nueva postulación de árbitro',
            "{$request->user()->name} quiere arbitrar en {$campeonato->nombre}.",
            route('campeonatos.arbitros.administrar', $campeonato),
            'bi-flag-fill',
            'warning',
        ));

        return redirect()->route('arbitros.panel')->with('mensaje', 'Tu postulación fue enviada al organizador.');
    }

    /**
     * Permite al organizador aprobar o rechazar una postulación.
     *
     * Solo el creador del campeonato (o el desarrollador) puede hacerlo.
     */
    public function gestionar(Request $request, Campeonato $campeonato, User $arbitro)
    {
        $this->autorizarOrganizador($request->user(), $campeonato);
        abort_unless($arbitro->rol === User::ROLE_ARBITRO, 404);

        $data = $request->validate([
            'estado' => ['required', 'in:aprobado,rechazado'],
            'motivo_respuesta' => ['nullable', 'string', 'max:500'],
        ]);

        // Aquí usamos el constructor de consultas porque necesitamos actualizar
        // directamente varios campos propios de la tabla intermedia.
        $solicitud = DB::table('arbitro_campeonato')
            ->where('campeonato_id', $campeonato->id)
            ->where('arbitro_id', $arbitro->id)
            ->first();
        abort_unless($solicitud, 404);

        DB::table('arbitro_campeonato')
            ->where('id', $solicitud->id)
            ->update([
                'estado' => $data['estado'],
                'motivo_respuesta' => trim((string) ($data['motivo_respuesta'] ?? '')) ?: null,
                'gestionado_por' => $request->user()->id,
                'gestionado_at' => now(),
                'updated_at' => now(),
            ]);

        // La respuesta se transforma en textos, iconos y colores comprensibles
        // para la notificación que recibirá el árbitro.
        $aprobada = $data['estado'] === 'aprobado';
        $arbitro->notify(new NotificacionPlataforma(
            $aprobada ? 'Postulación de árbitro aprobada' : 'Postulación de árbitro rechazada',
            ($aprobada ? 'Ya puedes recibir partidos en ' : 'El organizador revisó tu postulación para ').$campeonato->nombre.'.',
            route('arbitros.panel'),
            $aprobada ? 'bi-check-circle-fill' : 'bi-x-circle-fill',
            $aprobada ? 'success' : 'danger',
        ));

        return back()->with('mensaje', $aprobada ? 'Árbitro aprobado correctamente.' : 'Postulación rechazada.');
    }

    /**
     * Endpoint JSON usado por el formulario de partidos.
     * Devuelve únicamente árbitros aprobados para el campeonato seleccionado.
     */
    public function opciones(Request $request, Campeonato $campeonato)
    {
        $this->autorizarOrganizador($request->user(), $campeonato);

        return response()->json($campeonato->arbitros()
            ->wherePivot('estado', 'aprobado')
            ->orderBy('name')
            ->get(['users.id', 'users.name', 'users.email']));
    }

    /** Detiene la petición con error 403 si el usuario no es árbitro. */
    private function autorizarArbitro(User $user): void
    {
        abort_unless($user->rol === User::ROLE_ARBITRO, 403);
    }

    /**
     * Autoriza al dueño del campeonato o al desarrollador con acceso global.
     */
    private function autorizarOrganizador(User $user, Campeonato $campeonato): void
    {
        abort_unless($user->esDesarrollador() || ($user->rol === User::ROLE_ORGANIZADOR && (int) $campeonato->usuario_creador === (int) $user->id), 403);
    }
}
