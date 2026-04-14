<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FirmaController extends Controller
{
    public function edit(): Response
    {
        $user = auth()->user();

        return Inertia::render('admin/costos/firma/edit', [
            'firmaUrl' => $user->firma_path
                ? Storage::url($user->firma_path)
                : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'firma' => ['required', 'string'],
        ]);

        $user = $request->user();

        if ($user->firma_path) {
            Storage::disk('public')->delete($user->firma_path);
        }

        $dataUrl = $request->input('firma');
        $imageData = base64_decode(Str::after($dataUrl, 'base64,'));
        $path = 'firmas/'.$user->id.'_'.time().'.png';

        Storage::disk('public')->put($path, $imageData);

        $user->update(['firma_path' => $path]);

        return back()->with('success', 'Firma actualizada correctamente.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->firma_path) {
            Storage::disk('public')->delete($user->firma_path);
            $user->update(['firma_path' => null]);
        }

        return back()->with('success', 'Firma eliminada.');
    }
}
