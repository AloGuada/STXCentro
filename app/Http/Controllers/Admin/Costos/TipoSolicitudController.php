<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\TipoSolicitudStoreRequest;
use App\Http\Requests\Admin\Costos\TipoSolicitudUpdateRequest;
use App\Models\Costos\Documento;
use App\Models\Costos\TipoSolicitud;
use App\Support\OrdenaColumnas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TipoSolicitudController extends Controller
{
    use OrdenaColumnas;

    public function index(Request $request): Response
    {
        $query = TipoSolicitud::query()
            ->withCount('documentos')
            ->when($request->search, fn ($q, $s) => $q->where('titulo', 'like', "%{$s}%"));

        $orden = $this->aplicarOrden($query, $request, [
            'titulo' => 'titulo',
            'rubros' => 'rubros',
            'documentos_count' => 'documentos_count',
            'created_at' => 'created_at',
        ], 'titulo', 'asc');

        $tipoSolicitudes = $query->paginate(15)->withQueryString();

        return Inertia::render('admin/costos/tipo-solicitudes/index', [
            'tipoSolicitudes' => $tipoSolicitudes,
            'filters' => $request->only('search'),
            'sortBy' => $orden['by'],
            'sortDir' => $orden['dir'],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/costos/tipo-solicitudes/create');
    }

    public function store(TipoSolicitudStoreRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $tipoSolicitud = TipoSolicitud::create($request->safe()->except('documentos'));

            if ($request->has('documentos')) {
                foreach ($request->input('documentos', []) as $doc) {
                    $tipoSolicitud->documentos()->create([
                        'titulo' => $doc['titulo'],
                        'multiple' => $doc['multiple'] ?? false,
                        'texto' => $doc['texto'] ?? null,
                        'texto_adicional' => $doc['texto_adicional'] ?? false,
                    ]);
                }
            }
        });

        return to_route('admin.costos.tipo-solicitudes.index');
    }

    public function edit(TipoSolicitud $tipoSolicitud): Response
    {
        return Inertia::render('admin/costos/tipo-solicitudes/edit', [
            'tipoSolicitud' => $tipoSolicitud->load('documentos'),
        ]);
    }

    public function update(TipoSolicitudUpdateRequest $request, TipoSolicitud $tipoSolicitud): RedirectResponse
    {
        DB::transaction(function () use ($request, $tipoSolicitud) {
            $tipoSolicitud->update($request->safe()->except('documentos'));

            $incomingIds = collect($request->input('documentos', []))
                ->pluck('id')
                ->filter()
                ->all();

            $tipoSolicitud->documentos()
                ->whereNotIn('id', $incomingIds)
                ->delete();

            foreach ($request->input('documentos', []) as $doc) {
                if (! empty($doc['id'])) {
                    Documento::where('id', $doc['id'])->update([
                        'titulo' => $doc['titulo'],
                        'multiple' => $doc['multiple'] ?? false,
                        'texto' => $doc['texto'] ?? null,
                        'texto_adicional' => $doc['texto_adicional'] ?? false,
                    ]);
                } else {
                    $tipoSolicitud->documentos()->create([
                        'titulo' => $doc['titulo'],
                        'multiple' => $doc['multiple'] ?? false,
                        'texto' => $doc['texto'] ?? null,
                        'texto_adicional' => $doc['texto_adicional'] ?? false,
                    ]);
                }
            }
        });

        return to_route('admin.costos.tipo-solicitudes.index');
    }

    public function destroy(TipoSolicitud $tipoSolicitud): RedirectResponse
    {
        $tipoSolicitud->delete();

        return to_route('admin.costos.tipo-solicitudes.index');
    }
}
