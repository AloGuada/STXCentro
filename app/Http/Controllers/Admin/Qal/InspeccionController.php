<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Qal\InspeccionRequest;
use App\Services\Qal\PantallaDeCaptura;
use App\Services\Qal\RegistradorInspeccion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * La captura de inspección: el `captura.html` de la aplicación anterior, ahora
 * contra la base.
 *
 * Una sola pantalla para las tres transformaciones, porque así la usa el
 * inspector: la tablet se queda abierta en Formularios y lo que cambia es la
 * fase.
 */
class InspeccionController extends Controller
{
    public function __construct(
        private readonly RegistradorInspeccion $registrador,
        private readonly PantallaDeCaptura $pantalla,
    ) {}

    public function create(Request $request): Response
    {
        return $this->pantalla->mostrar($request->integer('obra') ?: null);
    }

    /**
     * Vuelve a Formularios en la misma obra: el inspector sigue con la pieza
     * siguiente.
     */
    public function store(InspeccionRequest $request): RedirectResponse
    {
        $inspeccion = $this->registrador->registrar(
            $request->safe()->except('fotos'),
            $request->user(),
            $request->file('fotos', []),
        );

        return redirect()
            ->route('admin.qal.formularios', ['obra' => $inspeccion->obra_id])
            ->with('success', "Inspección {$inspeccion->folio} guardada.");
    }
}
