<?php

namespace App\Http\Controllers;

use App\Models\Campeonato;
use App\Services\DocumentoPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FixturePdfController extends Controller
{
    public function __invoke(Request $request, Campeonato $campeonato, DocumentoPdfService $pdf)
    {
        Gate::authorize('downloadFixture', $campeonato);
        $request->validate(['fecha' => ['required', 'string']]);
        $fecha = $request->string('fecha')->toString();
        if ($fecha !== 'sin-fecha') $request->validate(['fecha' => ['date_format:Y-m-d']]);
        $consulta = $campeonato->partidos()->with(['equipoLocal', 'equipoVisita', 'fase']);
        $fecha === 'sin-fecha' ? $consulta->whereNull('fecha') : $consulta->whereDate('fecha', $fecha);
        $partidos = $consulta->orderBy('hora')->orderBy('id')->get();
        abort_if($partidos->isEmpty(), 404, 'No hay partidos en esta jornada.');
        return $pdf->descargar('pdf.fixture', compact('campeonato', 'partidos', 'fecha'), "jornada-{$campeonato->id}-{$fecha}.pdf");
    }
}
