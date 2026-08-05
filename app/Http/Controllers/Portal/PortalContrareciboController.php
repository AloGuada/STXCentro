<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Costos\Factura;
use App\Services\Costos\ContrareciboPdf;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Contrarecibo de una factura para el proveedor. El formato solo tiene sentido
 * cuando Contabilidad ya programó el pago: antes de eso saldría sin la fecha,
 * que es justo el dato que el proveedor viene a buscar, así que se niega y el
 * tablero muestra "Por programar".
 */
class PortalContrareciboController extends Controller
{
    public function __construct(private readonly ContrareciboPdf $contrarecibo) {}

    public function show(Factura $factura): HttpResponse
    {
        abort_if($factura->proveedor_id !== Auth::guard('proveedor')->id(), 403);
        abort_if($factura->pagoRaiz?->fecha_pago_programada === null, 404);

        return $this->contrarecibo->render($factura)->stream("Contrarecibo-{$factura->folio}.pdf");
    }
}
