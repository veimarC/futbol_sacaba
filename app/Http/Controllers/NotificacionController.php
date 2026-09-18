<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificacionController extends Controller
{
    public function index(Request $request)
    {
        $notificaciones = $request->user()->notifications()->paginate(15);
        return view('notificaciones.index', compact('notificaciones'));
    }

    public function abrir(Request $request, DatabaseNotification $notificacion): RedirectResponse
    {
        abort_unless((int) $notificacion->notifiable_id === (int) $request->user()->id
            && $notificacion->notifiable_type === $request->user()::class, 403);
        $notificacion->markAsRead();

        $destino = $notificacion->data['url'] ?? route('home');
        return redirect()->to($destino);
    }

    public function marcarTodas(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();
        return back()->with('mensaje', 'Todas las notificaciones fueron marcadas como leídas.');
    }
}
