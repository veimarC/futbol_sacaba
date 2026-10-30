<?php

namespace App\Http\Controllers;

use App\Models\Campeonato;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AprobacionCampeonatoController extends Controller
{
    public function enviar(Request $request, Campeonato $campeonato)
    {
        $this->authorize('update', $campeonato);
        DB::transaction(function () use ($campeonato) {
            $actual = Campeonato::lockForUpdate()->findOrFail($campeonato->id);
            abort_unless(in_array($actual->aprobacion_estado, ['borrador', 'devuelto'], true), 409, 'La solicitud ya fue enviada.');
            abort_if($actual->estado_actual === 'finalizado', 422, 'Actualiza las fechas antes de enviar la solicitud.');
            $actual->forceFill([
                'aprobacion_estado' => 'pendiente',
                'aprobacion_motivo' => null,
                'solicitado_at' => now(),
                'revisado_at' => null,
                'revisado_por' => null,
            ])->save();
        });
        return back()->with('mensaje', 'Solicitud enviada al administrador general.');
    }

    public function revisar(Request $request, Campeonato $campeonato)
    {
        abort_unless($request->user()->esDesarrollador(), 403);
        $data = $request->validate([
            'decision' => 'required|in:aprobado,devuelto',
            'motivo' => 'nullable|required_if:decision,devuelto|string|min:10|max:2000',
        ]);
        DB::transaction(function () use ($campeonato, $request, $data) {
            $actual = Campeonato::lockForUpdate()->findOrFail($campeonato->id);
            abort_unless($actual->aprobacion_estado === 'pendiente', 409, 'Esta solicitud ya no esta pendiente.');
            if ($data['decision'] === 'aprobado') {
                abort_if($actual->estado_actual === 'finalizado', 422, 'Devuelve la solicitud para corregir las fechas.');
            }
            $actual->forceFill([
                'aprobacion_estado' => $data['decision'],
                'aprobacion_motivo' => $data['decision'] === 'devuelto' ? $data['motivo'] : null,
                'revisado_at' => now(),
                'revisado_por' => $request->user()->id,
            ])->save();
        });
        return back()->with('mensaje', $data['decision'] === 'aprobado'
            ? 'Campeonato aprobado y publicado.'
            : 'Solicitud devuelta al organizador para corregir.');
    }
}
