<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProveedorEstatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProveedorStoreRequest;
use App\Http\Requests\Admin\ProveedorUpdateRequest;
use App\Models\Departamento;
use App\Models\Proveedor;
use App\Models\RegimenFiscal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProveedorController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('costos.proveedores.ver');

        $proveedores = Proveedor::query()
            ->with('departamento')
            ->when($request->search, fn ($q, $s) => $q->where('razon_social', 'like', "%{$s}%")
                ->orWhere('codigo', 'like', "%{$s}%")
                ->orWhere('rfc', 'like', "%{$s}%")
                ->orWhere('nombre_comercial', 'like', "%{$s}%"))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/proveedores/index', [
            'proveedores' => $proveedores,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('costos.proveedores.crear');

        return Inertia::render('admin/proveedores/create', [
            'departamentos' => Departamento::query()->orderBy('descripcion')->get(),
            'regimenes' => RegimenFiscal::where('activo', true)->orderBy('clave')->get(['id', 'clave', 'descripcion']),
        ]);
    }

    public function store(ProveedorStoreRequest $request): RedirectResponse
    {
        Gate::authorize('costos.proveedores.crear');

        $data = $request->validated();
        unset($data['constancia'], $data['caratula']);

        // Alta centralizada en Compras: el proveedor nace desactivado y se
        // activa al validarse su documentación en el último nivel de aprobación.
        $data['estatus'] = ProveedorEstatus::PendienteValidacion;
        $data['activo'] = false;
        $data['creado_por'] = $request->user()->id;

        $proveedor = Proveedor::create($data);

        $this->guardarArchivo($proveedor, $request->file('constancia'), 'constancia_fiscal');
        $this->guardarArchivo($proveedor, $request->file('caratula'), 'caratula_bancaria');

        return to_route('admin.proveedores.index')
            ->with('success', 'Proveedor registrado. Quedará desactivado hasta validar su documentación.');
    }

    public function edit(Proveedor $proveedor): Response
    {
        Gate::authorize('costos.proveedores.editar');

        return Inertia::render('admin/proveedores/edit', [
            'proveedor' => $proveedor->load(['departamento', 'regimenFiscal', 'media', 'validador:id,name']),
            'tienePassword' => (bool) $proveedor->password,
            'departamentos' => Departamento::query()->orderBy('descripcion')->get(),
            'regimenes' => RegimenFiscal::where('activo', true)->orderBy('clave')->get(['id', 'clave', 'descripcion']),
        ]);
    }

    public function update(ProveedorUpdateRequest $request, Proveedor $proveedor): RedirectResponse
    {
        Gate::authorize('costos.proveedores.editar');

        $data = $request->validated();
        unset($data['constancia'], $data['caratula']);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $proveedor->update($data);

        if ($request->hasFile('constancia')) {
            $this->guardarArchivo($proveedor, $request->file('constancia'), 'constancia_fiscal', reemplazar: true);
        }

        if ($request->hasFile('caratula')) {
            $this->guardarArchivo($proveedor, $request->file('caratula'), 'caratula_bancaria', reemplazar: true);
        }

        return to_route('admin.proveedores.index')->with('success', 'Proveedor actualizado.');
    }

    public function destroy(Proveedor $proveedor): RedirectResponse
    {
        Gate::authorize('costos.proveedores.eliminar');

        $proveedor->delete();

        return to_route('admin.proveedores.index');
    }

    private function guardarArchivo(Proveedor $proveedor, UploadedFile $file, string $descripcion, bool $reemplazar = false): void
    {
        if ($reemplazar) {
            foreach ($proveedor->media()->where('descripcion', $descripcion)->get() as $previo) {
                Storage::disk('public')->delete($previo->path);
                $previo->delete();
            }
        }

        $proveedor->media()->create([
            'descripcion' => $descripcion,
            'nombre_original' => $file->getClientOriginalName(),
            'path' => $file->store("proveedores/{$proveedor->id}", 'public'),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }
}
