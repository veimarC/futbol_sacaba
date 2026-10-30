<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

class DocumentoPdfService
{
    public function imagen(?string $ruta): ?string
    {
        if (!$ruta) return null;
        $base = realpath(Storage::disk('public')->path(''));
        $archivo = realpath(Storage::disk('public')->path($ruta));
        if (!$base || !$archivo || !str_starts_with($archivo, $base.DIRECTORY_SEPARATOR) || !is_file($archivo)) return null;
        $mime = mime_content_type($archivo);
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'])) return null;
        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($archivo));
    }

    public function descargar(string $vista, array $datos, string $nombre)
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('chroot', storage_path('app/public'));
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view($vista, $datos + ['imagenes' => $this])->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();
        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
