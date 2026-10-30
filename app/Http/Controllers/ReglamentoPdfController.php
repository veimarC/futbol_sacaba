<?php

namespace App\Http\Controllers;

use App\Models\Campeonato;
use App\Services\DocumentoPdfService;
use Illuminate\Support\Facades\Gate;

class ReglamentoPdfController extends Controller
{
    public function __invoke(Campeonato $campeonato, DocumentoPdfService $pdf)
    {
        Gate::authorize('downloadRules', $campeonato);
        $campeonato->load('organizador');
        return $pdf->descargar('pdf.reglamento', compact('campeonato'), "reglamento-{$campeonato->id}.pdf");
    }
}
