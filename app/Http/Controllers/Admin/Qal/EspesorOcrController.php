<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Http\Controllers\Controller;
use App\Services\Qal\LecturasDelCalibre;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Leer los espesores de una foto de la pantalla del calibre.
 *
 * Es una ayuda de captura, no un registro: no guarda nada. Devuelve los
 * números para que el inspector los vea en la rejilla y los corrija si el OCR
 * se equivocó, y lo que se guarda sigue siendo lo que él deja escrito.
 */
class EspesorOcrController extends Controller
{
    public function __invoke(Request $request, LecturasDelCalibre $lecturas): JsonResponse
    {
        $request->validate([
            'foto' => ['required', 'image', 'max:12288'],
        ], [
            'foto.required' => 'Falta la foto de la pantalla del calibre.',
            'foto.image' => 'El archivo no es una imagen.',
            'foto.max' => 'La foto pesa más de 12 MB.',
        ]);

        try {
            $espesores = $lecturas->deFoto($request->file('foto'));
        } catch (RuntimeException $error) {
            return response()->json(['message' => $error->getMessage()], 503);
        }

        return response()->json(['lecturas' => $espesores]);
    }
}
