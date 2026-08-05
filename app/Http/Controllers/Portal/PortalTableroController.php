<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Costos\OrdenCompra;
use App\Services\Portal\PreviewFacturaSesion;
use App\Services\Portal\TableroProveedorBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tablero del proveedor: una sola tabla con sus órdenes de compra, las facturas
 * de cada una y los documentos de todo el ciclo (factura, recepción,
 * contrarecibo y comprobante de pago).
 */
class PortalTableroController extends Controller
{
    public function __construct(
        private readonly TableroProveedorBuilder $tablero,
        private readonly PreviewFacturaSesion $preview,
    ) {}

    public function index(Request $request): Response
    {
        $proveedor = Auth::guard('proveedor')->user();
        $tab = TableroProveedorBuilder::tabValido($request->string('tab')->toString());

        return Inertia::render('portal/tablero', [
            'ordenes' => $this->tablero->ordenes($proveedor, $tab),
            'conteos' => $this->tablero->conteos($proveedor),
            'resumen' => $this->tablero->resumen($proveedor),
            'tab' => $tab,
            'facturaPreview' => $this->facturaPreview($proveedor->id),
        ]);
    }

    /**
     * Paso 2 del alta de factura: los datos leídos del CFDI que esperan
     * confirmación. Llegan como prop para que el tablero los muestre en un modal
     * en vez de mandar al proveedor a otra pantalla.
     *
     * @return array<string, mixed>|null
     */
    private function facturaPreview(int $proveedorId): ?array
    {
        $preview = $this->preview->obtener();

        if (! $preview || ! $this->preview->vieneDelTablero()) {
            return null;
        }

        $oc = OrdenCompra::find($preview['orden_compra_id']);

        if (! $oc || $oc->proveedor_id !== $proveedorId) {
            return null;
        }

        return [
            'orden_compra_id' => $oc->id,
            'orden_compra_folio' => $oc->folio,
            'moneda' => $oc->moneda,
            'fiscal' => $preview['fiscal'],
            'archivos' => [
                'xml_original' => $preview['xml_original'],
                'pdf_original' => $preview['pdf_original'],
            ],
            'notas' => $preview['notas'],
        ];
    }
}
