<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\AnticipoStoreRequest;
use App\Http\Requests\Admin\Cob\AnticipoUpdateRequest;
use App\Models\Cob\Anticipo;
use App\Models\Obra;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AnticipoController extends Controller
{
    public function create(Obra $obra): Response
    {
        return Inertia::render('admin/cob/anticipos/create', [
            'obra' => $obra,
        ]);
    }

    public function store(AnticipoStoreRequest $request, Obra $obra): RedirectResponse
    {
        $data = $request->safe()->except('comprobante');

        $anticipo = $obra->anticipos()->create($data);

        if ($request->hasFile('comprobante')) {
            $file = $request->file('comprobante');
            $anticipo->media()->create([
                'descripcion' => 'comprobante',
                'nombre_original' => $file->getClientOriginalName(),
                'path' => $file->store('cob/anticipos', 'public'),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        return to_route('admin.cob.obras.show', $obra);
    }

    public function edit(Obra $obra, Anticipo $anticipo): Response
    {
        $anticipo->load('media');

        return Inertia::render('admin/cob/anticipos/edit', [
            'obra' => $obra,
            'anticipo' => $anticipo,
        ]);
    }

    public function update(AnticipoUpdateRequest $request, Obra $obra, Anticipo $anticipo): RedirectResponse
    {
        $data = $request->safe()->except('comprobante');

        if ($request->hasFile('comprobante')) {
            if ($anticipo->media) {
                Storage::disk('public')->delete($anticipo->media->path);
                $anticipo->media->delete();
            }

            $file = $request->file('comprobante');
            $anticipo->media()->create([
                'descripcion' => 'comprobante',
                'nombre_original' => $file->getClientOriginalName(),
                'path' => $file->store('cob/anticipos', 'public'),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        $anticipo->update($data);

        return to_route('admin.cob.obras.show', $obra);
    }

    public function destroy(Obra $obra, Anticipo $anticipo): RedirectResponse
    {
        if ($anticipo->media) {
            Storage::disk('public')->delete($anticipo->media->path);
            $anticipo->media->delete();
        }

        $anticipo->delete();

        return back();
    }

    public function marcarPagado(Obra $obra, Anticipo $anticipo): RedirectResponse
    {
        $anticipo->update([
            'estado' => 'aplicado',
            'fecha_pagado' => now(),
        ]);

        return back();
    }
}
