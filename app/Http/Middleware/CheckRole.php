<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Comprueba que el usuario autenticado tenga alguno de los roles permitidos.
     * Se pasan los roles como parámetro separados por '|' en la definición del middleware.
     * Ejemplo en rutas: ->middleware('role:organizador|desarrollador')
     *
     * @param Request $request
     * @param Closure $next
     * @param string $roles
     * @return Response
     */
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        // Si no hay usuario autenticado, redirigir al login
        if (! $request->user()) {
            return redirect()->route('login');
        }

        $allowed = explode('|', $roles);

        // Si el rol del usuario no está en la lista de permitidos, devolver 403
        if (! in_array($request->user()->rol, $allowed, true)) {
            abort(403, 'Acceso denegado. No tienes el rol necesario.');
        }

        return $next($request);
    }
}
