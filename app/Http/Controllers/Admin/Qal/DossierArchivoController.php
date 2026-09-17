<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Qal\DossierArchivoRequest;
use App\Models\Qal\Dossier;
use App\Models\Qal\DossierArchivo;
use App\Models\Qal\DossierSeccion;
use App\Services\Qal\Dosier\UnidorDePdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Los PDF de cada sección del dosier: subir varios a la vez, verlos, quitarlos
 * y acomodar su orden, que es el orden en que salen en la descarga.
 *
 * Al subir cada uno se prueba con el motor de unión: el que no abre se guarda
 * igual —es un documento del cliente— pero queda marcado para que la pantalla
 * avise que no entrará en el dosier unido.
 */
class DossierArchivoController extends Controller
{
    public function store(DossierArchivoRequest $request, Dossier $dossier, DossierSeccion $seccion, UnidorDePdf $unidor): RedirectResponse
    {
        abort_unless($seccion->dossier_id === $dossier->id, 404);

        $disco = Storage::disk(DossierArchivo::DISCO);
        $orden = (int) $seccion->archivos()->max('orden');
        $incompatibles = 0;

        foreach ($request->file('archivos') as $archivo) {
            $path = $archivo->store("{$dossier->carpeta()}/{$seccion->id}", DossierArchivo::DISCO);
            $paginas = $unidor->paginas($disco->path($path));
            $incompatibles += $paginas === null ? 1 : 0;

            $seccion->archivos()->create([
                'orden' => ++$orden,
                'nombre_original' => $archivo->getClientOriginalName(),
                'path' => $path,
                'size' => $archivo->getSize(),
                'paginas' => $paginas,
                'compatible' => $paginas !== null,
                'capturista_id' => $request->user()->id,
            ]);
        }

        $dossier->touch();
        $subidos = count($request->file('archivos'));

        return back()->with('success', "{$subidos} PDF subido(s)".($incompatibles
            ? " · {$incompatibles} no se pueden unir con el motor actual y quedarán fuera de la descarga."
            : '.'));
    }

    public function ver(Dossier $dossier, DossierArchivo $archivo): StreamedResponse
    {
        abort_unless($archivo->seccion?->dossier_id === $dossier->id, 404);

        return Storage::disk(DossierArchivo::DISCO)->response($archivo->path, $archivo->nombre_original, ['Content-Type' => 'application/pdf']);
    }

    public function destroy(Dossier $dossier, DossierArchivo $archivo): RedirectResponse
    {
        $seccion = $archivo->seccion;
        abort_unless($seccion?->dossier_id === $dossier->id, 404);

        Storage::disk(DossierArchivo::DISCO)->delete($archivo->path);
        $archivo->delete();
        $this->renumerar($seccion->archivos()->pluck('id')->all());
        $dossier->touch();

        return back()->with('success', "«{$archivo->nombre_original}» quitado.");
    }

    /**
     * Los ids de la sección en su orden nuevo. Tienen que venir todos.
     */
    public function reordenar(Request $request, Dossier $dossier, DossierSeccion $seccion): RedirectResponse
    {
        abort_unless($seccion->dossier_id === $dossier->id, 404);
        $validos = $seccion->archivos()->pluck('id')->all();

        $datos = $request->validate([
            'ids' => ['required', 'array', 'size:'.count($validos)],
            'ids.*' => ['integer', 'distinct', Rule::in($validos)],
        ], [
            'ids.size' => 'La lista cambió mientras la ordenabas; recarga la pantalla.',
        ]);

        $this->renumerar($datos['ids']);

        return back();
    }

    /**
     * @param  list<int>  $ids
     */
    private function renumerar(array $ids): void
    {
        DB::transaction(function () use ($ids): void {
            foreach (array_values($ids) as $posicion => $id) {
                DossierArchivo::query()->whereKey($id)->update(['orden' => $posicion + 1]);
            }
        });
    }
}
