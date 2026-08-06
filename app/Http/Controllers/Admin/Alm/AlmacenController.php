<?php

namespace App\Http\Controllers\Admin\Alm;

use App\Enums\Alm\AlmacenTipo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Alm\AlmacenStoreRequest;
use App\Http\Requests\Admin\Alm\AlmacenUpdateRequest;
use App\Models\Alm\Almacen;
use App\Models\Obra;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catálogo de almacenes. Es la base del módulo: sin almacén no hay dónde
 * registrar una entrada, una salida ni una transferencia.
 */
class AlmacenController extends Controller
{
    public function index(Request $request): Response
    {
        $almacenes = Almacen::query()
            ->with(['obra:id,no,descripcion', 'responsable:id,name'])
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('clave', 'like', "%{$s}%")
                ->orWhere('nombre', 'like', "%{$s}%")
                ->orWhereHas('obra', fn ($o) => $o->where('no', 'like', "%{$s}%")
                    ->orWhere('descripcion', 'like', "%{$s}%"))))
            ->orderBy('obra_id')
            ->orderBy('clave')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/almacen/almacenes/index', [
            'almacenes' => $almacenes,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/almacen/almacenes/create', $this->opciones());
    }

    public function store(AlmacenStoreRequest $request): RedirectResponse
    {
        Almacen::create($request->validated());

        return to_route('admin.alm.almacenes.index');
    }

    public function edit(Almacen $almacen): Response
    {
        return Inertia::render('admin/almacen/almacenes/edit', [
            'almacen' => $almacen,
            ...$this->opciones(),
        ]);
    }

    public function update(AlmacenUpdateRequest $request, Almacen $almacen): RedirectResponse
    {
        $almacen->update($request->validated());

        return to_route('admin.alm.almacenes.index');
    }

    /**
     * Se borra sólo mientras esté virgen. En cuanto tenga movimientos, el
     * almacén es parte del histórico del kardex y sólo se desactiva.
     */
    public function destroy(Almacen $almacen): RedirectResponse
    {
        $almacen->delete();

        return to_route('admin.alm.almacenes.index');
    }

    /**
     * Catálogos que necesitan las dos pantallas de captura.
     *
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
            'obras' => Obra::query()
                ->where('activa', true)
                ->orderBy('no')
                ->get(['id', 'no', 'descripcion']),
            'usuarios' => Usuario::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'tipos' => collect(AlmacenTipo::cases())
                ->map(fn (AlmacenTipo $t): array => ['value' => $t->value, 'label' => $t->etiqueta()])
                ->all(),
        ];
    }
}
