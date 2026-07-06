<?php

namespace App\Exports\Costos;

use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exporta el listado de órdenes de compra aplanado: una línea por cada
 * (orden de compra × producto/partida), repitiendo los datos de la OC en cada
 * renglón. Respeta los mismos filtros del index.
 */
class OrdenesCompraExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    private Collection $datos;

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function __construct(private array $filtros = [])
    {
        $this->datos = $this->buildData();
    }

    public function title(): string
    {
        return 'Órdenes de compra';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Folio OC',
            'Estatus',
            'Proveedor',
            'Presupuesto',
            'Tipo de pago',
            'Total OC',
            'Producto',
            'Cantidad',
            'Unidad',
            'P. unitario',
            'Subtotal',
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
        $sheet->getStyle('A1:K1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
        $sheet->getStyle('A1:K1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');

        return [];
    }

    private function buildData(): Collection
    {
        $search = $this->filtros['search'] ?? null;
        $estatus = $this->filtros['estatus'] ?? null;
        $proveedorId = $this->filtros['proveedor_id'] ?? null;
        $presupuestoId = $this->filtros['presupuesto_id'] ?? null;
        $tipoPago = $this->filtros['tipo_pago'] ?? null;

        $ordenes = OrdenCompra::query()
            ->with([
                'proveedor:id,razon_social,nombre_comercial',
                'detalles:id,orden_compra_id,obra_rubro_id,descripcion,unidad,cantidad,precio_unitario,subtotal',
                'detalles.obraRubro.presupuesto.presupuestable',
            ])
            ->when($search, function ($query, $s) {
                $query->where(function ($q) use ($s) {
                    $q->where('folio', 'like', "%{$s}%")
                        ->orWhereHas('proveedor', fn ($p) => $p->where('razon_social', 'like', "%{$s}%"))
                        ->orWhereHas('detalles', fn ($d) => $d->where('descripcion', 'like', "%{$s}%"));
                });
            })
            ->when($estatus, fn ($q, $e) => $q->where('estatus', $e))
            ->when($proveedorId, fn ($q, $id) => $q->where('proveedor_id', $id))
            ->when($tipoPago, fn ($q, $tp) => $q->where('tipo_pago', $tp))
            ->when($presupuestoId, function ($q, $id) {
                $q->whereHas('detalles.obraRubro', fn ($or) => $or->where('presupuesto_id', $id));
            })
            ->latest()
            ->get();

        $filas = new Collection;

        foreach ($ordenes as $oc) {
            $base = [
                'folio' => $oc->folio,
                'estatus' => $oc->estatus->label(),
                'proveedor' => $oc->proveedor?->razon_social ?? '—',
                'obra' => $oc->presupuesto_label,
                'tipo_pago' => $oc->tipo_pago?->label() ?? '—',
                'total' => (float) $oc->total,
            ];

            if ($oc->detalles->isEmpty()) {
                $filas->push([...$base, 'producto' => '—', 'cantidad' => null, 'unidad' => null, 'precio' => null, 'subtotal' => null]);

                continue;
            }

            foreach ($oc->detalles as $detalle) {
                /** @var OrdenCompraDetalle $detalle */
                $filas->push([
                    ...$base,
                    'producto' => $detalle->descripcion,
                    'cantidad' => (float) $detalle->cantidad,
                    'unidad' => $detalle->unidad,
                    'precio' => (float) $detalle->precio_unitario,
                    'subtotal' => (float) $detalle->subtotal,
                ]);
            }
        }

        return $filas;
    }
}
