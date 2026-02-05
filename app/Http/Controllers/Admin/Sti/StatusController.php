<?php

namespace App\Http\Controllers\Admin\Sti;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Sti\StatusStoreRequest;
use App\Http\Requests\Admin\Sti\StatusUpdateRequest;
use App\Models\Sti\Status;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StatusController extends Controller
{
    public function index(Request $request): Response
    {
        $statuses = Status::query()
            ->when($request->search, fn ($q, $s) => $q->where('descripcion', 'like', "%{$s}%"))
            ->orderBy('orden')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/sti/status/index', [
            'statuses' => $statuses,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/sti/status/create');
    }

    public function store(StatusStoreRequest $request): RedirectResponse
    {
        Status::create([
            'descripcion' => $request->descripcion,
            'orden' => $request->orden ?? 0,
            'detiene_tiempo' => $request->boolean('detiene_tiempo'),
        ]);

        return to_route('admin.sti.status.index');
    }

    public function edit(Status $status): Response
    {
        return Inertia::render('admin/sti/status/edit', [
            'status' => $status,
        ]);
    }

    public function update(StatusUpdateRequest $request, Status $status): RedirectResponse
    {
        $status->update([
            'descripcion' => $request->descripcion,
            'orden' => $request->orden ?? 0,
            'detiene_tiempo' => $request->boolean('detiene_tiempo'),
        ]);

        return to_route('admin.sti.status.index');
    }

    public function destroy(Status $status): RedirectResponse
    {
        if ($status->historial()->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar un estado que está siendo usado en tickets.']);
        }

        $status->delete();

        return to_route('admin.sti.status.index');
    }
}
