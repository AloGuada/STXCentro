<?php

namespace App\Http\Controllers\Admin\Drive;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Drive\ExternoStoreRequest;
use App\Http\Requests\Admin\Drive\ExternoUpdateRequest;
use App\Models\Drive\Externo;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DriveExternoController extends Controller
{
    public function index(): Response
    {
        $this->authorize('drive.gestionar');

        $externos = Externo::query()
            ->withCount('carpetas')
            ->latest()
            ->paginate(15);

        return Inertia::render('admin/drive/externos/index', [
            'externos' => $externos,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('drive.gestionar');

        return Inertia::render('admin/drive/externos/create');
    }

    public function store(ExternoStoreRequest $request): RedirectResponse
    {
        $this->authorize('drive.gestionar');

        Externo::create([
            ...$request->validated(),
            'created_by' => $request->user()->getKey(),
        ]);

        return to_route('admin.drive.externos.index')->with('success', 'Usuario externo creado correctamente.');
    }

    public function edit(Externo $externo): Response
    {
        $this->authorize('drive.gestionar');

        $externo->load('carpetas');

        return Inertia::render('admin/drive/externos/edit', [
            'externo' => $externo,
        ]);
    }

    public function update(ExternoUpdateRequest $request, Externo $externo): RedirectResponse
    {
        $this->authorize('drive.gestionar');

        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $externo->update($data);

        return to_route('admin.drive.externos.index')->with('success', 'Usuario externo actualizado correctamente.');
    }

    public function destroy(Externo $externo): RedirectResponse
    {
        $this->authorize('drive.gestionar');

        $externo->delete();

        return to_route('admin.drive.externos.index')->with('success', 'Usuario externo eliminado correctamente.');
    }
}
