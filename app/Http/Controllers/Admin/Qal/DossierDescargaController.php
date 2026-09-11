<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Http\Controllers\Controller;
use App\Models\Qal\Dossier;
use App\Services\Qal\Dosier\GeneradorDeDosier;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * «Descargar dosier»: portada, índice, separadores y los PDF de cada sección
 * unidos en uno. Se arma al pedirlo; si con dosieres reales resulta lento, se
 * pasa a un job que avise al terminar.
 */
class DossierDescargaController extends Controller
{
    public function __invoke(Dossier $dossier, GeneradorDeDosier $generador): BinaryFileResponse
    {
        set_time_limit(0);

        ['ruta' => $ruta] = $generador->descargar($dossier);
        $nombre = Str::slug('dosier de calidad '.($dossier->obra?->no ?? $dossier->id)).'.pdf';

        return response()->download($ruta, $nombre, ['Content-Type' => 'application/pdf'])->deleteFileAfterSend();
    }
}
