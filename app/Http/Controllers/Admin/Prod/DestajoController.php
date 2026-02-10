<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\DestajoStoreRequest;
use App\Http\Requests\Admin\Prod\FabricadoStoreRequest;
use App\Http\Requests\Admin\Prod\PagoExtraStoreRequest;
use App\Models\Pieza;
use App\Models\Prod\Destajo;
use App\Models\Prod\Fabricado;
use App\Models\Prod\Grupo;
use App\Models\Prod\PagoExtra;
use App\Models\Prod\Tipo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DestajoController extends Controller
{
    public function index(Request $request): Response
    {
        $destajos = Destajo::query()
            ->withCount(['fabricados', 'pagosExtra'])
            ->withSum('fabricados', 'total_calculado')
            ->when($request->search, fn ($q, $s) => $q->where('semana', 'like', "%{$s}%"))
            ->orderByDesc('semana')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/prod/destajos/index', [
            'destajos' => $destajos,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/prod/destajos/create');
    }

    public function store(DestajoStoreRequest $request): RedirectResponse
    {
        $destajo = Destajo::create([
            'semana' => $request->semana,
        ]);

        return to_route('admin.prod.destajos.show', $destajo);
    }

    public function show(Destajo $destajo): Response
    {
        $destajo->load([
            'fabricados.pieza.obra',
            'fabricados.destGrupo',
            'pagosExtra.tipo',
            'pagosExtra.destGrupo',
        ]);

        return Inertia::render('admin/prod/destajos/show', [
            'destajo' => $destajo,
            'grupos' => Grupo::orderBy('descripcion')->get(),
            'piezas' => Pieza::with('obra')->orderBy('marca')->get(),
            'tipos' => Tipo::orderBy('orden')->orderBy('descripcion')->get(),
        ]);
    }

    public function destroy(Destajo $destajo): RedirectResponse
    {
        if ($destajo->cerrada) {
            return back()->withErrors(['error' => 'No se puede eliminar un destajo cerrado.']);
        }

        $destajo->fabricados()->delete();
        $destajo->pagosExtra()->delete();
        $destajo->delete();

        return to_route('admin.prod.destajos.index');
    }

    public function storeFabricado(FabricadoStoreRequest $request, Destajo $destajo): RedirectResponse
    {
        if ($destajo->cerrada) {
            return back()->withErrors(['error' => 'No se puede agregar fabricados a un destajo cerrado.']);
        }

        $pieza = Pieza::findOrFail($request->pieza_id);
        $totalCalculado = round(
            $request->cantidad * $pieza->peso * ($request->porcentual / 100) * $request->precio_unitario_aplicado,
            2
        );

        $saldoPendiente = $request->porcentual < 100 ? $totalCalculado : 0;

        $destajo->fabricados()->create([
            'dest_grupo_id' => $request->dest_grupo_id,
            'pieza_id' => $request->pieza_id,
            'cantidad' => $request->cantidad,
            'porcentual' => $request->porcentual,
            'precio_unitario_aplicado' => $request->precio_unitario_aplicado,
            'total_calculado' => $totalCalculado,
            'saldo_pendiente' => $saldoPendiente,
        ]);

        return back();
    }

    public function updateFabricado(Request $request, Destajo $destajo, Fabricado $fabricado): RedirectResponse
    {
        if ($destajo->cerrada) {
            return back()->withErrors(['error' => 'No se puede editar fabricados de un destajo cerrado.']);
        }

        if ($fabricado->destajo_id !== $destajo->id) {
            abort(404);
        }

        $request->validate([
            'cantidad' => ['required', 'integer', 'min:1'],
            'porcentual' => ['required', 'numeric', 'min:0', 'max:100'],
            'precio_unitario_aplicado' => ['required', 'numeric', 'min:0'],
        ]);

        $pieza = Pieza::findOrFail($fabricado->pieza_id);
        $totalCalculado = round(
            $request->cantidad * $pieza->peso * ($request->porcentual / 100) * $request->precio_unitario_aplicado,
            2
        );
        $saldoPendiente = $request->porcentual < 100 ? $totalCalculado : 0;

        $fabricado->update([
            'cantidad' => $request->cantidad,
            'porcentual' => $request->porcentual,
            'precio_unitario_aplicado' => $request->precio_unitario_aplicado,
            'total_calculado' => $totalCalculado,
            'saldo_pendiente' => $saldoPendiente,
        ]);

        return back();
    }

    public function destroyFabricado(Destajo $destajo, Fabricado $fabricado): RedirectResponse
    {
        if ($destajo->cerrada) {
            return back()->withErrors(['error' => 'No se puede eliminar fabricados de un destajo cerrado.']);
        }

        if ($fabricado->destajo_id !== $destajo->id) {
            abort(404);
        }

        $fabricado->delete();

        return back();
    }

    public function storePagoExtra(PagoExtraStoreRequest $request, Destajo $destajo): RedirectResponse
    {
        if ($destajo->cerrada) {
            return back()->withErrors(['error' => 'No se puede agregar pagos extra a un destajo cerrado.']);
        }

        $destajo->pagosExtra()->create([
            'dest_grupo_id' => $request->dest_grupo_id,
            'tipo_id' => $request->tipo_id,
            'descripcion' => $request->descripcion,
            'precio' => $request->precio,
            'dias' => $request->dias,
            'personas' => $request->personas,
        ]);

        return back();
    }

    public function destroyPagoExtra(Destajo $destajo, PagoExtra $pagoExtra): RedirectResponse
    {
        if ($destajo->cerrada) {
            return back()->withErrors(['error' => 'No se puede eliminar pagos extra de un destajo cerrado.']);
        }

        if ($pagoExtra->destajo_id !== $destajo->id) {
            abort(404);
        }

        $pagoExtra->delete();

        return back();
    }

    public function cerrar(Destajo $destajo): RedirectResponse
    {
        if ($destajo->cerrada) {
            return back()->withErrors(['error' => 'Este destajo ya esta cerrado.']);
        }

        return DB::transaction(function () use ($destajo) {
            $cantidadTotal = $destajo->fabricados()->sum('total_calculado');

            $destajo->update([
                'cerrada' => true,
                'cantidad' => $cantidadTotal,
                'fecha_cierre' => now(),
            ]);

            // Auto-arrastre: si hay fabricados con porcentual < 100, crear siguiente destajo
            $fabricadosPendientes = $destajo->fabricados()
                ->where('porcentual', '<', 100)
                ->where('saldo_pendiente', '>', 0)
                ->get();

            if ($fabricadosPendientes->isNotEmpty()) {
                $nuevoDestajo = Destajo::create([
                    'semana' => $destajo->semana + 1,
                ]);

                foreach ($fabricadosPendientes as $fabricadoAnterior) {
                    $pieza = Pieza::find($fabricadoAnterior->pieza_id);
                    $porcentualRestante = 100 - $fabricadoAnterior->porcentual;

                    if ($porcentualRestante <= 0) {
                        continue;
                    }

                    $totalCalculado = round(
                        $fabricadoAnterior->cantidad * $pieza->peso * ($porcentualRestante / 100) * $fabricadoAnterior->precio_unitario_aplicado,
                        2
                    );

                    $nuevoDestajo->fabricados()->create([
                        'dest_grupo_id' => $fabricadoAnterior->dest_grupo_id,
                        'pieza_id' => $fabricadoAnterior->pieza_id,
                        'cantidad' => $fabricadoAnterior->cantidad,
                        'porcentual' => $porcentualRestante,
                        'precio_unitario_aplicado' => $fabricadoAnterior->precio_unitario_aplicado,
                        'total_calculado' => $totalCalculado,
                        'saldo_pendiente' => 0,
                    ]);
                }
            }

            return back();
        });
    }
}
