<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Qal\SubloteRequest;
use App\Models\Qal\LoteAccesorio;
use App\Models\Qal\Sublote;
use App\Services\Qal\PantallaDeCaptura;
use App\Services\Qal\PrecargaDeFormulario;
use App\Services\Qal\RegistradorSublote;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

/**
 * Los lotes de accesorios: cuánto de cada marca llegó, cuánto se liberó y qué
 * está detenido.
 *
 * El avance se consulta en la pestaña Accesorios del tablero; la captura de
 * cada entrega es la de Formularios en modo «lote de accesorios», que es donde
 * está el inspector. Nueva entrega, reinspección y edición abren esa misma
 * pantalla ya llenada.
 */
class AccesorioController extends Controller
{
    public function __construct(
        private readonly RegistradorSublote $registrador,
        private readonly PantallaDeCaptura $pantalla,
        private readonly PrecargaDeFormulario $precarga,
    ) {}

    public function store(SubloteRequest $request): RedirectResponse
    {
        $sublote = $this->registrador->registrar($request->validated(), $request->user());

        return redirect()
            ->route('admin.qal.formularios', ['obra' => $sublote->lote->obra_id])
            ->with('success', "Sublote de {$sublote->lote->marca} guardado · {$sublote->unidades} unidades.");
    }

    public function nuevoSublote(LoteAccesorio $lote): Response
    {
        return $this->pantalla->mostrar($lote->obra_id, $this->precarga->deLote($lote));
    }

    public function reinspeccionar(Sublote $sublote): Response
    {
        return $this->pantalla->mostrar($sublote->lote->obra_id, $this->precarga->deSublote($sublote, reinspeccion: true));
    }

    public function edit(Sublote $sublote): Response
    {
        return $this->pantalla->mostrar($sublote->lote->obra_id, $this->precarga->deSublote($sublote, reinspeccion: false));
    }

    public function update(SubloteRequest $request, Sublote $sublote): RedirectResponse
    {
        $this->registrador->registrar($request->validated(), $request->user(), $sublote);

        return redirect()
            ->route('admin.qal.dashboard', ['tab' => 'accesorios', 'obra' => $sublote->lote->obra_id])
            ->with('success', "Inspección del sublote de {$sublote->lote->marca} actualizada.");
    }

    /**
     * La primera inspección de un sublote no se borra mientras tenga
     * reinspecciones: el grupo perdería la que dice que hubo un rechazo.
     */
    public function destroy(Sublote $sublote): RedirectResponse
    {
        if ($sublote->reinspecciones()->exists()) {
            return back()->withErrors([
                'sublote' => 'Este sublote tiene reinspecciones: bórralas primero, de la más reciente a la original.',
            ]);
        }

        $marca = $sublote->lote->marca;
        $sublote->delete();

        return back()->with('success', "Inspección del sublote de {$marca} eliminada.");
    }
}
