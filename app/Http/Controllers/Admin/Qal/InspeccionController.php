<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Qal\InspeccionRequest;
use App\Models\Qal\Inspeccion;
use App\Services\Qal\PantallaDeCaptura;
use App\Services\Qal\PrecargaDeFormulario;
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
 * fase. Editar y reinspeccionar abren esa misma pantalla ya llenada.
 */
class InspeccionController extends Controller
{
    public function __construct(
        private readonly RegistradorInspeccion $registrador,
        private readonly PantallaDeCaptura $pantalla,
        private readonly PrecargaDeFormulario $precarga,
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
            $request->safe()->except(['fotos', 'fotos_quitar']),
            $request->user(),
            $request->file('fotos', []),
        );

        return redirect()
            ->route('admin.qal.formularios', ['obra' => $inspeccion->obra_id])
            ->with('success', "Inspección {$inspeccion->folio} guardada.");
    }

    /**
     * Una inspección nueva de la misma pieza, que se reparó. La anterior se
     * conserva: es la que dice que hubo un rechazo.
     */
    public function reinspeccionar(Inspeccion $inspeccion): Response
    {
        return $this->pantalla->mostrar($inspeccion->obra_id, $this->precarga->deInspeccion($inspeccion, reinspeccion: true));
    }

    public function edit(Inspeccion $inspeccion): Response
    {
        return $this->pantalla->mostrar($inspeccion->obra_id, $this->precarga->deInspeccion($inspeccion, reinspeccion: false));
    }

    public function update(InspeccionRequest $request, Inspeccion $inspeccion): RedirectResponse
    {
        $this->registrador->actualizar(
            $inspeccion,
            $request->safe()->except(['fotos', 'fotos_quitar']),
            $request->file('fotos', []),
            array_map('intval', (array) $request->input('fotos_quitar', [])),
        );

        return redirect()
            ->route('admin.qal.registros.index', ['ficha' => $inspeccion->id])
            ->with('success', "Inspección {$inspeccion->folio} actualizada.");
    }

    public function destroy(Inspeccion $inspeccion): RedirectResponse
    {
        $this->registrador->borrar($inspeccion);

        return redirect()
            ->route('admin.qal.registros.index')
            ->with('success', "Inspección {$inspeccion->folio} eliminada.");
    }
}
