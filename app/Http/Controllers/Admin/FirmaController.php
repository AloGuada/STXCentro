<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La rúbrica de cada usuario, dibujada una vez y estampada en los documentos
 * que firma.
 *
 * Es de la persona, no de un módulo: la estampan las aprobaciones de Costos y
 * los formatos de Calidad sobre el mismo `usuarios.firma_path`. Por eso esta
 * pantalla la abre cualquier usuario y Costos sólo cambia la página que enseña.
 */
class FirmaController extends Controller
{
    public function edit(Request $request): Response
    {
        $usuario = $request->user();

        return Inertia::render($this->pagina(), [
            'firmaUrl' => $usuario->firma_path ? Storage::url($usuario->firma_path) : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'firma' => ['required', 'string'],
        ]);

        $usuario = $request->user();

        if ($usuario->firma_path) {
            Storage::disk('public')->delete($usuario->firma_path);
        }

        $path = 'firmas/'.$usuario->id.'_'.time().'.png';
        Storage::disk('public')->put($path, base64_decode(Str::after($request->input('firma'), 'base64,')));

        $usuario->update(['firma_path' => $path]);

        return back()->with('success', 'Firma actualizada correctamente.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $usuario = $request->user();

        if ($usuario->firma_path) {
            Storage::disk('public')->delete($usuario->firma_path);
            $usuario->update(['firma_path' => null]);
        }

        return back()->with('success', 'Firma eliminada.');
    }

    protected function pagina(): string
    {
        return 'admin/firma/edit';
    }
}
