<?php

namespace App\Http\Controllers\Admin\Alm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Alm\AsignacionReasignarRequest;
use App\Models\Alm\Almacen;
use App\Models\Alm\Existencia;
use App\Services\Alm\ReasignacionMaterial;
use Illuminate\Http\RedirectResponse;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * La asignación de material por obra no tiene pantalla propia: se ve dentro de
 * Existencias y del Kardex, que es donde alguien está mirando el material
 * cuando necesita repartirlo.
 *
 * Por eso aquí sólo vive la acción que la corrige. La asignación nace sola en la
 * recepción y se consume sola en la salida; esto es lo único que la mueve a
 * mano.
 */
class AsignacionController extends Controller
{
    public function __construct(private readonly ReasignacionMaterial $reasignacion) {}

    public function reasignar(AsignacionReasignarRequest $request): RedirectResponse
    {
        $existencia = Existencia::query()
            ->whereKey($request->integer('existencia_id'))
            ->firstOrFail();

        // El permiso abre la acción; la visibilidad decide sobre qué material.
        // Sin esto, quien puede reasignar en su almacén podría repartir el de
        // una bodega que ni siquiera ve en la pantalla.
        if (! Almacen::query()->visiblesPara($request->user())->whereKey($existencia->almacen_id)->exists()) {
            throw new NotFoundHttpException;
        }

        try {
            $this->reasignacion->reasignar(
                existencia: $existencia,
                deObraId: $request->input('de_obra_id') === null ? null : $request->integer('de_obra_id'),
                aObraId: $request->input('a_obra_id') === null ? null : $request->integer('a_obra_id'),
                cantidad: (float) $request->input('cantidad'),
                motivo: (string) $request->input('motivo'),
                userId: $request->user()?->getAuthIdentifier(),
            );
        } catch (RuntimeException $e) {
            // Reasignar de más no es un error de sistema: es que entre que se
            // pintó el desglose y llegó este POST, alguien se llevó material.
            return back()->withInput()->withErrors(['cantidad' => $e->getMessage()]);
        }

        return back();
    }
}
