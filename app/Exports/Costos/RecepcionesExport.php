<?php

namespace App\Exports\Costos;

use App\Models\Costos\Entrega;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exporta el listado de recepciones acotado por fecha de recepción —el sello
 * del sistema, no la fecha operativa que captura el almacenista—, con las
 * mismas columnas que la pantalla: un renglón por recepción. Los filtros
 * (búsqueda, tipo y visibilidad) se aplican con el mismo scope que el index,
 * para que el reporte no muestre de más ni de menos.
 */
class RecepcionesExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    private Collection $datos;

    /**
     * @param  array{fecha_inicio?: ?string, fecha_fin?: ?string, search?: ?string, tipo?: ?string, solicitante_id?: ?string}  $filtros
     */
    public function __construct(private array $filtros = [])
    {
        $this->datos = $this->buildData();
    }

    public function title(): string
    {
        return 'Recepciones';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Folio',
            'Fecha de recepción',
            'Fecha de Entrega',
            'Orden de compra',
            'Solicitudes de pago',
            'Proveedor',
            'Obra',
            'Factura',
            'Recibió',
            'Tipo',
            'Total recibido',
            'Estatus',
        ];
    }

    public function collection(): Collection
    {
        return $this->datos;
    }

    /**
     * @return array<string, mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:L1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
        $sheet->getStyle('A1:L1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');

        return [];
    }

    private function buildData(): Collection
    {
        return Entrega::query()
            ->with([
                'detalles',
                'detalles.ordenCompraDetalle:id,precio_unitario',
                'ordenCompra:id,folio,proveedor_id',
                'ordenCompra.proveedor:id,razon_social,nombre_comercial',
                // El destino presupuestal sale de las partidas: la columna
                // `obra_id` de la OC quedó sin uso con el presupuesto polimórfico.
                // Se precargan los tres caminos de `nombresDePresupuesto()`.
                'ordenCompra.detalles:id,orden_compra_id,obra_rubro_id',
                'ordenCompra.detalles.obraRubro.presupuesto.presupuestable',
                'ordenCompra.solicitudesPago',
                'ordenCompra.solicitudesPago.detalles.obraRubro.presupuesto.presupuestable',
                'ordenCompra.requisicion.presupuesto.presupuestable',
                'factura:id,folio',
                'recibidor:id,name',
            ])
            ->filtradas($this->filtros)
            ->latest('fecha_entrega')
            ->get()
            ->map(function (Entrega $entrega): array {
                $oc = $entrega->ordenCompra;
                $proveedor = $oc?->proveedor;

                return [
                    'folio' => $entrega->folio ?? '—',
                    // Cuándo se elaboró el documento (sistema) y cuándo entró el
                    // material (operativa): son fechas distintas y se reportan las dos.
                    'fecha_recepcion' => $entrega->fechaRecepcionLocal()?->format('d/m/Y') ?? '—',
                    'fecha_entrega' => $entrega->fecha_entrega?->format('d/m/Y') ?? '—',
                    'orden_compra' => $oc?->folio ?? '—',
                    // Una OC de contado puede tener varias solicitudes ligadas;
                    // se listan en la misma celda para no romper el renglón.
                    'solicitudes_pago' => $oc?->solicitudesPago->pluck('folio')->implode(' / ') ?: '—',
                    'proveedor' => $proveedor ? ($proveedor->razon_social ?: $proveedor->nombre_comercial) : '—',
                    // Todas las obras a las que pega la recepción, no una etiqueta
                    // genérica: el reporte se concilia contra presupuesto.
                    'obra' => implode(' · ', $oc?->nombresDePresupuesto() ?? []) ?: '—',
                    'factura' => $entrega->factura?->folio ?? '—',
                    'recibido_por' => $entrega->recibidor?->name ?? '—',
                    'tipo' => $entrega->tipo === 'completa' ? 'Completa' : 'Parcial',
                    // Importe sin IVA de lo recibido, igual que el formato de recepción.
                    'total' => $entrega->importeRecibido(),
                    'estatus' => $entrega->estaCancelada() ? 'Cancelada' : 'Vigente',
                ];
            });
    }
}
